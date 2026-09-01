<?php

namespace App;

final class AsaasCustomerIdentity
{
    public function __construct(
        private AsaasClient $asaas,
        private SupabaseClient $database
    ) {
    }

    public function resolve(array $guardian, ?string $submittedDocument = null): array
    {
        $guardianId = trim((string) ($guardian['id'] ?? ''));
        $name = trim((string) ($guardian['parent_name'] ?? ''));
        $email = strtolower(trim((string) ($guardian['email'] ?? '')));
        $phone = self::normalizeDocument((string) ($guardian['parent_phone'] ?? ''));
        $storedDocument = self::normalizeDocument((string) ($guardian['parent_document'] ?? ''));
        $submitted = self::normalizeDocument((string) ($submittedDocument ?? ''));

        if ($guardianId === '' || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return self::failure(
                'GUARDIAN_IDENTITY_INCOMPLETE',
                'O cadastro do responsável está incompleto. Atualize nome e e-mail antes de gerar a cobrança.',
                422
            );
        }

        if ($storedDocument !== '' && $submitted !== '' && $storedDocument !== $submitted) {
            return self::failure(
                'GUARDIAN_DOCUMENT_MISMATCH',
                'O CPF/CNPJ informado não corresponde ao documento cadastrado para este responsável.',
                409
            );
        }

        $document = $storedDocument !== '' ? $storedDocument : $submitted;
        if (!self::isValidCpfOrCnpj($document)) {
            return self::failure(
                'GUARDIAN_DOCUMENT_INVALID',
                'Informe um CPF ou CNPJ válido para o responsável selecionado.',
                422
            );
        }

        $conflict = $this->findLocalDocumentConflict($guardianId, $name, $document);
        if (!($conflict['ok'] ?? false)) {
            return $conflict;
        }

        $customerId = trim((string) ($guardian['asaas_customer_id'] ?? ''));
        if ($customerId !== '') {
            $remote = $this->asaas->getCustomer($customerId);
            if (($remote['ok'] ?? false) && is_array($remote['data'] ?? null)) {
                $remoteData = $remote['data'];
                if (!((bool) ($remoteData['deleted'] ?? false))) {
                    $remoteDocument = self::normalizeDocument((string) ($remoteData['cpfCnpj'] ?? ''));
                    if ($remoteDocument !== '' && $remoteDocument !== $document) {
                        return self::failure(
                            'ASAAS_CUSTOMER_DOCUMENT_CONFLICT',
                            'O cliente Asaas vinculado possui outro CPF/CNPJ. A cobrança foi bloqueada para revisão.',
                            409
                        );
                    }

                    $remoteName = self::normalizeName((string) ($remoteData['name'] ?? ''));
                    $remoteEmail = strtolower(trim((string) ($remoteData['email'] ?? '')));
                    if (
                        $remoteDocument === ''
                        && $remoteName !== self::normalizeName($name)
                        && $remoteEmail !== $email
                    ) {
                        return self::failure(
                            'ASAAS_CUSTOMER_IDENTITY_CONFLICT',
                            'O cliente Asaas vinculado pertence a outra identidade. A cobrança foi bloqueada para revisão.',
                            409
                        );
                    }

                    $update = $this->asaas->updateCustomer(
                        $customerId,
                        $this->buildCustomerPayload($name, $email, $document, $phone)
                    );
                    if (!($update['ok'] ?? false)) {
                        return self::failure(
                            'ASAAS_CUSTOMER_SYNC_FAILED',
                            'Não foi possível validar e sincronizar o cliente no Asaas.',
                            502
                        );
                    }

                    $persist = $this->persistGuardianIdentity($guardianId, $customerId, $document);
                    if (!($persist['ok'] ?? false)) {
                        return $persist;
                    }

                    return [
                        'ok' => true,
                        'customer_id' => $customerId,
                        'document' => $document,
                        'created' => false,
                    ];
                }
            } elseif ((int) ($remote['status'] ?? 0) !== 404) {
                return self::failure(
                    'ASAAS_CUSTOMER_LOOKUP_FAILED',
                    'Não foi possível conferir a identidade do cliente no Asaas.',
                    502
                );
            }
        }

        $created = $this->asaas->createCustomer(
            $this->buildCustomerPayload($name, $email, $document, $phone)
        );
        if (!($created['ok'] ?? false)) {
            return self::failure(
                'ASAAS_CUSTOMER_CREATE_FAILED',
                'Não foi possível criar um cliente validado no Asaas.',
                502
            );
        }

        $newCustomerId = trim((string) ($created['data']['id'] ?? ''));
        if ($newCustomerId === '') {
            return self::failure(
                'ASAAS_CUSTOMER_CREATE_FAILED',
                'O Asaas não retornou um cliente válido.',
                502
            );
        }

        $persist = $this->persistGuardianIdentity($guardianId, $newCustomerId, $document);
        if (!($persist['ok'] ?? false)) {
            return $persist;
        }

        return [
            'ok' => true,
            'customer_id' => $newCustomerId,
            'document' => $document,
            'created' => true,
        ];
    }

    public static function normalizeDocument(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    public static function isValidCpfOrCnpj(string $document): bool
    {
        $digits = self::normalizeDocument($document);
        if (strlen($digits) === 11) {
            return self::isValidCpf($digits);
        }
        if (strlen($digits) === 14) {
            return self::isValidCnpj($digits);
        }
        return false;
    }

    public static function matchesRemoteCustomer(
        array $customer,
        string $name,
        string $email,
        string $document
    ): bool {
        $expectedName = self::normalizeName($name);
        $expectedEmail = strtolower(trim($email));
        $expectedDocument = self::normalizeDocument($document);
        if ($expectedName === '' || $expectedDocument === '') {
            return false;
        }

        $remoteName = self::normalizeName((string) ($customer['name'] ?? ''));
        $remoteEmail = strtolower(trim((string) ($customer['email'] ?? '')));
        $remoteDocument = self::normalizeDocument((string) ($customer['cpfCnpj'] ?? ''));

        return $remoteName === $expectedName
            && $remoteDocument === $expectedDocument
            && ($expectedEmail === '' || $remoteEmail === $expectedEmail);
    }

    public static function matchesLocalGuardian(
        array $guardian,
        string $name,
        string $email,
        string $document
    ): bool {
        return self::normalizeName((string) ($guardian['parent_name'] ?? '')) === self::normalizeName($name)
            && strtolower(trim((string) ($guardian['email'] ?? ''))) === strtolower(trim($email))
            && self::normalizeDocument((string) ($guardian['parent_document'] ?? ''))
                === self::normalizeDocument($document);
    }

    private function findLocalDocumentConflict(string $guardianId, string $name, string $document): array
    {
        $result = $this->database->select(
            'guardians',
            'select=id,parent_name,parent_document&parent_document=not.is.null&limit=10000'
        );
        if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
            return self::failure(
                'GUARDIAN_DOCUMENT_CHECK_FAILED',
                'Não foi possível conferir o CPF/CNPJ no cadastro local.',
                503
            );
        }

        $normalizedName = self::normalizeName($name);
        foreach ($result['data'] as $otherGuardian) {
            if (!is_array($otherGuardian)) {
                continue;
            }
            if ((string) ($otherGuardian['id'] ?? '') === $guardianId) {
                continue;
            }
            if (self::normalizeDocument((string) ($otherGuardian['parent_document'] ?? '')) !== $document) {
                continue;
            }
            if (self::normalizeName((string) ($otherGuardian['parent_name'] ?? '')) !== $normalizedName) {
                return self::failure(
                    'GUARDIAN_DOCUMENT_CONFLICT',
                    'Este CPF/CNPJ já está associado a outro responsável. A cobrança foi bloqueada para revisão.',
                    409
                );
            }
        }

        return ['ok' => true];
    }

    private function persistGuardianIdentity(string $guardianId, string $customerId, string $document): array
    {
        $result = $this->database->update(
            'guardians',
            'id=eq.' . rawurlencode($guardianId),
            [
                'asaas_customer_id' => $customerId,
                'parent_document' => $document,
            ]
        );
        if (!($result['ok'] ?? false) || empty($result['data'][0])) {
            return self::failure(
                'GUARDIAN_IDENTITY_PERSIST_FAILED',
                'Não foi possível salvar o vínculo validado do responsável.',
                500
            );
        }
        return ['ok' => true];
    }

    private function buildCustomerPayload(
        string $name,
        string $email,
        string $document,
        string $phone
    ): array {
        $payload = [
            'name' => $name,
            'email' => $email,
            'cpfCnpj' => $document,
        ];
        if ($phone !== '') {
            $payload['mobilePhone'] = $phone;
        }
        return $payload;
    }

    private static function normalizeName(string $value): string
    {
        $normalized = mb_strtoupper(trim($value), 'UTF-8');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if ($ascii !== false) {
            $normalized = $ascii;
        }
        return preg_replace('/[^A-Z0-9]+/', '', $normalized) ?? '';
    }

    private static function isValidCpf(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        for ($position = 9; $position <= 10; $position++) {
            $sum = 0;
            for ($index = 0; $index < $position; $index++) {
                $sum += ((int) $cpf[$index]) * (($position + 1) - $index);
            }
            $digit = (10 * $sum) % 11;
            $digit = $digit === 10 ? 0 : $digit;
            if ((int) $cpf[$position] !== $digit) {
                return false;
            }
        }
        return true;
    }

    private static function isValidCnpj(string $cnpj): bool
    {
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }
        $weights = [
            [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
            [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        ];
        for ($round = 0; $round < 2; $round++) {
            $sum = 0;
            foreach ($weights[$round] as $index => $weight) {
                $sum += ((int) $cnpj[$index]) * $weight;
            }
            $remainder = $sum % 11;
            $digit = $remainder < 2 ? 0 : 11 - $remainder;
            if ((int) $cnpj[12 + $round] !== $digit) {
                return false;
            }
        }
        return true;
    }

    private static function failure(string $code, string $message, int $status): array
    {
        return [
            'ok' => false,
            'code' => $code,
            'error' => $message,
            'status' => $status,
        ];
    }
}
