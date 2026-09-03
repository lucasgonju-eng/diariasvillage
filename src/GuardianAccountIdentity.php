<?php
declare(strict_types=1);

namespace App;

final class GuardianAccountIdentity
{
    /**
     * @param array<int, mixed> $rows
     * @return array<string, mixed>
     */
    public static function analyze(array $rows, ?string $selectedGuardianId = null): array
    {
        $guardians = array_values(array_filter($rows, static function (mixed $row): bool {
            return is_array($row) && trim((string) ($row['id'] ?? '')) !== '';
        }));
        if ($guardians === []) {
            return self::failure('GUARDIAN_ACCOUNT_NOT_FOUND');
        }

        $selectedGuardianId = trim((string) $selectedGuardianId);
        $selected = null;
        if ($selectedGuardianId !== '') {
            foreach ($guardians as $guardian) {
                if (hash_equals(trim((string) ($guardian['id'] ?? '')), $selectedGuardianId)) {
                    $selected = $guardian;
                    break;
                }
            }
            if (!is_array($selected)) {
                return self::failure('GUARDIAN_SELECTION_MISMATCH');
            }
        }

        $documents = [];
        $names = [];
        $authUserIds = [];
        $rowsWithoutAuth = 0;
        $emails = [];
        $hasIncompleteDocument = false;
        $hasIncompleteName = false;
        $hasCompletedFirstAccess = false;

        foreach ($guardians as $guardian) {
            $document = AsaasCustomerIdentity::normalizeDocument(
                (string) ($guardian['parent_document'] ?? '')
            );
            if ($document === '') {
                $hasIncompleteDocument = true;
            } else {
                $documents[$document] = true;
            }

            $name = self::normalizeName((string) ($guardian['parent_name'] ?? ''));
            if ($name === '') {
                $hasIncompleteName = true;
            } else {
                $names[$name] = true;
            }

            $authUserId = trim((string) ($guardian['auth_user_id'] ?? ''));
            if ($authUserId === '') {
                $rowsWithoutAuth++;
            } else {
                $authUserIds[$authUserId] = true;
            }

            $email = strtolower(trim((string) ($guardian['email'] ?? '')));
            if (self::isUsableEmail($email)) {
                $emails[$email] = true;
            }
            if (!empty($guardian['first_access_completed_at'])) {
                $hasCompletedFirstAccess = true;
            }
        }

        if (
            $hasIncompleteDocument
            || count($documents) !== 1
            || !AsaasCustomerIdentity::isValidCpfOrCnpj((string) array_key_first($documents))
        ) {
            return self::failure('GUARDIAN_DOCUMENT_CONFLICT');
        }
        if ($hasIncompleteName || count($names) !== 1) {
            return self::failure('GUARDIAN_NAME_CONFLICT');
        }
        if (count($authUserIds) > 1) {
            return self::failure('GUARDIAN_AUTH_ACCOUNT_CONFLICT');
        }
        if (count($authUserIds) === 1 && $rowsWithoutAuth > 0) {
            return self::failure('GUARDIAN_AUTH_LINK_INCOMPLETE');
        }

        if (count($authUserIds) === 1) {
            if ($emails === []) {
                return self::failure('GUARDIAN_AUTH_EMAIL_MISSING');
            }
            return [
                'ok' => true,
                'mode' => 'supabase_auth',
                'auth_user_id' => (string) array_key_first($authUserIds),
                'document' => (string) array_key_first($documents),
                'emails' => array_keys($emails),
                'guardians' => $guardians,
                'selected' => $selected,
            ];
        }

        if ($hasCompletedFirstAccess) {
            return self::failure('GUARDIAN_AUTH_LINK_MISSING_AFTER_ACTIVATION');
        }
        if (count($guardians) !== 1) {
            return self::failure('GUARDIAN_LEGACY_IDENTITY_AMBIGUOUS');
        }

        return [
            'ok' => true,
            'mode' => 'legacy_local',
            'auth_user_id' => '',
            'document' => (string) array_key_first($documents),
            'emails' => array_keys($emails),
            'guardians' => $guardians,
            'selected' => $selected ?? $guardians[0],
        ];
    }

    public static function isUsableEmail(string $email): bool
    {
        $normalized = strtolower(trim($email));
        return filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false
            && !str_contains($normalized, '@placeholder.');
    }

    /**
     * @return array{ok: false, code: string}
     */
    private static function failure(string $code): array
    {
        return ['ok' => false, 'code' => $code];
    }

    private static function normalizeName(string $name): string
    {
        $normalized = mb_strtoupper(trim($name), 'UTF-8');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if ($ascii !== false) {
            $normalized = $ascii;
        }
        return preg_replace('/[^A-Z0-9]+/', '', $normalized) ?? '';
    }
}
