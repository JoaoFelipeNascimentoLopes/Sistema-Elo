<?php
require_once __DIR__ . '/access.php';
require_once __DIR__ . '/colaborador-fotos.php';

$usuarioAtual = appCurrentUser();
if (!$usuarioAtual) {
    http_response_code(403);
    exit;
}
$permitido = appCanAccess('geral.cadastros/colaboradores', $usuarioAtual)
    || appCanAccess('geral.movimentacoes/agenda-institucional', $usuarioAtual)
    || appCanAccess('geral.movimentacoes/escala-plantoes', $usuarioAtual)
    || appCanAccess('geral.movimentacoes/livro-plantao', $usuarioAtual)
    || appCanAccess('geral.dashboard-geral', $usuarioAtual);
if (!$permitido) {
    http_response_code(403);
    exit;
}

$idInput = $_GET['id'] ?? null;
$idColaborador = is_scalar($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;
if ($idColaborador === false || $idColaborador < 1) {
    http_response_code(404);
    exit;
}

$statement = appPdo()->prepare('SELECT caminhoFoto FROM tbColaborador WHERE idColaborador = :id');
$statement->execute(['id' => $idColaborador]);
$relativePath = $statement->fetchColumn();
$photoPath = is_string($relativePath) ? appColaboradorFotoPath($relativePath) : null;
if ($photoPath === null) {
    http_response_code(404);
    exit;
}

$fileInfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $fileInfo->file($photoPath);
if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    error_log('Tipo inesperado no arquivo de foto do colaborador ' . $idColaborador . '.');
    http_response_code(415);
    exit;
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($photoPath));
header('Content-Disposition: inline; filename="foto-colaborador-' . $idColaborador . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($photoPath);
exit;
