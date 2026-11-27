<?php
require_once __DIR__ . '/access.php';
require_once __DIR__ . '/documento-institucional-arquivos.php';

$usuarioAtual = appCurrentUser();
if (!$usuarioAtual) {
    http_response_code(403);
    exit;
}
$permitido = appCanAccess('geral.cadastros/documentos-institucionais', $usuarioAtual)
    || appCanAccess('geral.dashboard-geral', $usuarioAtual);
if (!$permitido) {
    http_response_code(403);
    exit;
}

$idInput = $_GET['id'] ?? null;
$idDocumento = is_scalar($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;
if ($idDocumento === false || $idDocumento < 1) {
    http_response_code(404);
    exit;
}

$statement = appPdo()->prepare(
    'SELECT caminhoArquivo, nomeOriginalArquivo FROM tbDocumentoInstitucional WHERE idDocumento = :id LIMIT 1'
);
$statement->execute(['id' => $idDocumento]);
$row = $statement->fetch(PDO::FETCH_ASSOC);
$filePath = is_array($row) && is_string($row['caminhoArquivo'] ?? null)
    ? appDocumentoInstitucionalPath($row['caminhoArquivo'])
    : null;
if ($filePath === null) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

$fileInfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $fileInfo->file($filePath);
if (!in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(415);
    exit;
}

$safeName = preg_replace('/[^A-Za-z0-9._\- ]/', '_', (string) ($row['nomeOriginalArquivo'] ?? ('documento-' . $idDocumento)));
$safeName = trim($safeName) !== '' ? trim($safeName) : ('documento-' . $idDocumento);
$disposition = ($_GET['download'] ?? '') === '1' ? 'attachment' : 'inline';

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
