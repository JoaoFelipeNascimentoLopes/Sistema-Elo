<?php
require_once __DIR__ . '/access.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

appValidateCsrf();
$usuarioAtual = appCurrentUser();
if ($usuarioAtual !== null) {
    appRegistrarAuditoria(
        'auth',
        'logout',
        'Sessão encerrada pelo usuário.',
        'tbUsuario',
        (int) $usuarioAtual['idUsuario'],
        null,
        $usuarioAtual
    );
}
$tabToken = appCurrentTabToken();
if ($tabToken !== null) {
    unset($_SESSION['tabSessions'][$tabToken]);
}

header('Location: ' . appTabLoginUrl());
exit;
