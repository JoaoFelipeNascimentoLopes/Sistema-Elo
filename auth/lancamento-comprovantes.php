<?php
require_once __DIR__ . '/access.php';

function appLancamentoComprovanteDirectory(): string
{
    return dirname(__DIR__) . '/uploads/comprovantes-financeiros';
}

function appLancamentoComprovantePath(?string $relativePath): ?string
{
    if ($relativePath === null
        || !preg_match('#^uploads/comprovantes-financeiros/[a-f0-9]{32}\.(?:pdf|jpg|png|webp)$#', $relativePath)) {
        return null;
    }

    $directory = realpath(appLancamentoComprovanteDirectory());
    $path = realpath(dirname(__DIR__) . '/' . $relativePath);
    return $directory !== false && $path !== false && dirname($path) === $directory && is_file($path)
        ? $path
        : null;
}

function appValidarUploadComprovanteFinanceiro(array $upload): array
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))
        || (int) ($upload['size'] ?? 0) < 1
        || (int) $upload['size'] > 10 * 1024 * 1024) {
        throw new InvalidArgumentException('O comprovante deve ter até 10 MB e ser enviado novamente.');
    }

    $temporaryPath = (string) $upload['tmp_name'];
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($temporaryPath);
    $allowed = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!is_string($mimeType) || !isset($allowed[$mimeType])) {
        throw new InvalidArgumentException('Envie um comprovante válido em PDF, JPEG, PNG ou WebP (até 10 MB).');
    }
    if ($mimeType !== 'application/pdf') {
        $image = @getimagesize($temporaryPath);
        if ($image === false || ($image['mime'] ?? '') !== $mimeType
            || $image[0] < 1 || $image[1] < 1
            || $image[0] > 8000 || $image[1] > 8000) {
            throw new InvalidArgumentException('A imagem enviada não é válida ou excede o limite permitido.');
        }
    }

    $directory = appLancamentoComprovanteDirectory();
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Não foi possível preparar o armazenamento privado dos comprovantes.');
    }
    $htaccessPath = $directory . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($htaccessPath)) {
        @file_put_contents($htaccessPath, "Require all denied\n");
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mimeType];
    $destination = $directory . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($temporaryPath, $destination)) {
        throw new RuntimeException('Não foi possível salvar o comprovante enviado.');
    }

    $original = trim(basename((string) ($upload['name'] ?? 'comprovante.' . $allowed[$mimeType])));
    $original = preg_replace('/[^\p{L}\p{N}._\-\s]/u', '_', $original) ?? ('comprovante.' . $allowed[$mimeType]);
    $original = mb_substr($original, 0, 200, 'UTF-8');

    return [
        'caminho' => 'uploads/comprovantes-financeiros/' . $filename,
        'nomeOriginal' => $original !== '' ? $original : ('comprovante.' . $allowed[$mimeType]),
    ];
}

function appRemoverComprovanteFinanceiro(?string $relativePath): void
{
    $path = appLancamentoComprovantePath($relativePath);
    if ($path !== null && !@unlink($path)) {
        throw new RuntimeException('O lançamento foi salvo, mas não foi possível remover o comprovante anterior.');
    }
}
