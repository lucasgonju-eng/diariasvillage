<?php

namespace App;

final class UploadSecurity
{
    /**
     * @param array<string, mixed> $file
     * @param array<string, array<int, string>> $allowedTypes Extensão => MIME types.
     * @return array{path:string,name:string,extension:string,mime:string,size:int}
     */
    public static function validate(array $file, array $allowedTypes, int $maxBytes): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException(self::uploadErrorMessage($error));
        }

        $path = (string) ($file['tmp_name'] ?? '');
        $name = trim((string) ($file['name'] ?? ''));
        if ($path === '' || $name === '' || str_contains($name, "\0")) {
            throw new \InvalidArgumentException('Arquivo inválido.');
        }
        if (PHP_SAPI !== 'cli' && !is_uploaded_file($path)) {
            throw new \InvalidArgumentException('Upload inválido.');
        }
        $size = is_file($path) ? (int) filesize($path) : 0;
        if (!is_file($path) || $size < 1 || $size > $maxBytes) {
            throw new \InvalidArgumentException(
                'O arquivo deve possuir no máximo ' . self::formatMegabytes($maxBytes) . ' MB.'
            );
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($extension === '' || !isset($allowedTypes[$extension])) {
            throw new \InvalidArgumentException(
                'Formato inválido. Permitidos: ' . strtoupper(implode(', ', array_keys($allowedTypes))) . '.'
            );
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower((string) $finfo->file($path));
        $allowedMimes = array_map('strtolower', $allowedTypes[$extension]);
        if ($mime === '' || !in_array($mime, $allowedMimes, true)) {
            throw new \InvalidArgumentException('O conteúdo do arquivo não corresponde ao formato informado.');
        }

        return [
            'path' => $path,
            'name' => $name,
            'extension' => $extension,
            'mime' => $mime,
            'size' => $size,
        ];
    }

    private static function formatMegabytes(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ''), '0'), '.');
    }

    private static function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Arquivo maior que o limite permitido.',
            UPLOAD_ERR_PARTIAL => 'O upload não foi concluído.',
            UPLOAD_ERR_NO_FILE => 'Arquivo não enviado.',
            default => 'Falha ao receber o arquivo.',
        };
    }
}
