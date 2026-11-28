<?php
require_once __DIR__ . '/access.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

appValidateCsrf();
$usuarioAtual = appCurrentUser();
if (!$usuarioAtual) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$temaInput = $_POST['tema'] ?? '';
$tema = is_scalar($temaInput) ? strtolower(trim((string) $temaInput)) : '';
if (!in_array($tema, ['claro', 'escuro'], true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Tema inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = appPdo()->prepare('UPDATE tbUsuario SET tema = :tema WHERE idUsuario = :idUsuario');
    $stmt->execute([
        'tema' => $tema,
        'idUsuario' => (int) $usuarioAtual['idUsuario'],
    ]);
    appRegistrarAuditoria(
        'geral',
        'editar',
        'Tema da interface alterado para ' . ($tema === 'escuro' ? 'Escuro' : 'Claro') . '.',
        'tbUsuario',
        (int) $usuarioAtual['idUsuario'],
        ['tema' => $tema],
        $usuarioAtual
    );
    echo json_encode(['ok' => true, 'tema' => $tema], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Falha ao atualizar tema do usuário: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Não foi possível salvar a preferência de tema agora.'], JSON_UNESCAPED_UNICODE);
}
exit;
