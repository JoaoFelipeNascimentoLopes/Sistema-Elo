<?php
require_once __DIR__ . '/access.php';

function appAcolhidoFotoDirectory(): string
{
    return dirname(__DIR__) . '/uploads/acolhidos';
}

function appAcolhidoFotoPath(?string $relativePath): ?string
{
    if ($relativePath === null
        || !preg_match('#^uploads/acolhidos/[a-f0-9]{32}\.(?:jpg|png|webp)$#', $relativePath)) {
        return null;
    }

    $directory = realpath(appAcolhidoFotoDirectory());
    $path = realpath(dirname(__DIR__) . '/' . $relativePath);
    return $directory !== false && $path !== false && dirname($path) === $directory && is_file($path)
        ? $path
        : null;
}

function appValidarUploadFotoAcolhido(array $upload): string
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))
        || (int) ($upload['size'] ?? 0) < 1
        || (int) $upload['size'] > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('A foto deve ter até 5 MB e ser enviada novamente.');
    }

    $temporaryPath = (string) $upload['tmp_name'];
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($temporaryPath);
    $image = getimagesize($temporaryPath);
    $imageTypes = [
        'image/jpeg' => ['extension' => 'jpg', 'type' => IMAGETYPE_JPEG],
        'image/png' => ['extension' => 'png', 'type' => IMAGETYPE_PNG],
        'image/webp' => ['extension' => 'webp', 'type' => IMAGETYPE_WEBP],
    ];
    if (!is_string($mimeType) || !isset($imageTypes[$mimeType])
        || $image === false || $image['mime'] !== $mimeType
        || $image[2] !== $imageTypes[$mimeType]['type']
        || $image[0] < 1 || $image[1] < 1
        || $image[0] > 8000 || $image[1] > 8000
        || $image[0] * $image[1] > 20000000) {
        throw new InvalidArgumentException('Envie uma foto válida em JPEG, PNG ou WebP, com resolução de até 20 megapixels.');
    }

    $directory = appAcolhidoFotoDirectory();
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Não foi possível preparar o armazenamento privado das fotos.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $imageTypes[$mimeType]['extension'];
    $destination = $directory . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($temporaryPath, $destination)) {
        throw new RuntimeException('Não foi possível salvar a foto enviada.');
    }

    return 'uploads/acolhidos/' . $filename;
}

function appRemoverFotoAcolhido(?string $relativePath): void
{
    $path = appAcolhidoFotoPath($relativePath);
    if ($path !== null && !unlink($path)) {
        throw new RuntimeException('O cadastro foi salvo, mas não foi possível remover a foto anterior.');
    }
}
