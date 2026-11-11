<?php
require_once __DIR__ . '/access.php';
appRequirePage('gestao-acolhidos.cadastros/acolhidos');

$idInput = $_GET['id'] ?? null;
$idAcolhido = is_scalar($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;
if ($idAcolhido === false || $idAcolhido < 1) {
    http_response_code(404);
    exit;
}

$statement = appPdo()->prepare('SELECT caminhoFoto FROM tbAcolhido WHERE idAcolhido = :id');
$statement->execute(['id' => $idAcolhido]);
$relativePath = $statement->fetchColumn();
if (!is_string($relativePath)
    || !preg_match('#^uploads/acolhidos/[a-f0-9]{32}\.(?:jpg|png|webp)$#', $relativePath)) {
    http_response_code(404);
    exit;
}

$uploadDirectory = realpath(__DIR__ . '/../uploads/acolhidos');
$photoPath = realpath(__DIR__ . '/../' . $relativePath);
if ($uploadDirectory === false || $photoPath === false
    || dirname($photoPath) !== $uploadDirectory || !is_file($photoPath)) {
    http_response_code(404);
    exit;
}

$fileInfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $fileInfo->file($photoPath);
if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    error_log('Tipo inesperado no arquivo de foto do acolhido ' . $idAcolhido . '.');
    http_response_code(415);
    exit;
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($photoPath));
header('Content-Disposition: inline; filename="foto-acolhido-' . $idAcolhido . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($photoPath);
exit;
