<?php
require_once __DIR__ . '/access.php';
require_once __DIR__ . '/lancamento-comprovantes.php';

$usuarioAtual = appCurrentUser();
if (!$usuarioAtual) {
    http_response_code(403);
    exit;
}
$permitido = appCanAccess('financeiro.movimentacoes/lancamentos-financeiros', $usuarioAtual)
    || appCanAccess('financeiro.dashboard-financeiro', $usuarioAtual)
    || appCanAccess('financeiro.relatorios/extrato-lancamentos', $usuarioAtual);
if (!$permitido) {
    http_response_code(403);
    exit;
}

$idInput = $_GET['id'] ?? null;
$idLancamento = is_scalar($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;
if ($idLancamento === false || $idLancamento < 1) {
    http_response_code(404);
    exit;
}

$statement = appPdo()->prepare(
    'SELECT caminhoComprovante, nomeOriginalComprovante FROM tbLancamentoFinanceiro WHERE idLancamento = :id LIMIT 1'
);
$statement->execute(['id' => $idLancamento]);
$row = $statement->fetch(PDO::FETCH_ASSOC);
$filePath = is_array($row) && is_string($row['caminhoComprovante'] ?? null)
    ? appLancamentoComprovantePath($row['caminhoComprovante'])
    : null;
if ($filePath === null) {
    http_response_code(404);
    exit('Comprovante não encontrado.');
}

$fileInfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $fileInfo->file($filePath);
if (!in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(415);
    exit;
}

$safeName = preg_replace('/[^A-Za-z0-9._\- ]/', '_', (string) ($row['nomeOriginalComprovante'] ?? ('comprovante-' . $idLancamento)));
$safeName = trim($safeName) !== '' ? trim($safeName) : ('comprovante-' . $idLancamento);
$disposition = ($_GET['download'] ?? '') === '1' ? 'attachment' : 'inline';

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
