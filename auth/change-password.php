<?php
require_once __DIR__ . '/access.php';
$user = appCurrentUser(true);

if (!$user) {
    header('Location: ' . appTabLoginUrl());
    exit;
}

$forcedChange = (int) $user['mustChangePassword'] === 1;
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
$respond = static function (int $status, array $payload) use ($isAjax, $user): void {
    if ($isAjax) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_THROW_ON_ERROR);
        exit;
    }

    if ($status === 200) {
        $targetUrl = appFirstAllowedUrl($user);
        if ($targetUrl !== null) {
            $targetUrl = appTabUrl($targetUrl);
        }
        header('Location: ' . ($targetUrl ?? appTabLoginUrl()));
        exit;
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    $currentPassword = (string) ($_POST['senhaAtual'] ?? '');
    $newPassword = (string) ($_POST['novaSenha'] ?? '');
    $confirmation = (string) ($_POST['confirmarSenha'] ?? '');
    $error = '';

    $passwordPolicyError = appPasswordPolicyError($newPassword);
    if ($passwordPolicyError !== null) {
        $error = $passwordPolicyError;
    } elseif ($newPassword !== $confirmation) {
        $error = 'A confirmação não corresponde à nova senha.';
    } else {
        $query = appPdo()->prepare('SELECT senhaHash FROM tbUsuario WHERE idUsuario = :idUsuario');
        $query->execute(['idUsuario' => (int) $user['idUsuario']]);
        $passwordHash = $query->fetchColumn();

        if (!is_string($passwordHash) || !password_verify($currentPassword, $passwordHash)) {
            $error = 'A senha atual está incorreta.';
        } else {
            $update = appPdo()->prepare(
                'UPDATE tbUsuario
                 SET senhaHash = :senhaHash, forcarTrocaSenha = 0
                 WHERE idUsuario = :idUsuario'
            );
            $update->execute([
                'senhaHash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'idUsuario' => (int) $user['idUsuario'],
            ]);
            $tabToken = appCurrentTabToken();
            $_SESSION['tabSessions'][$tabToken]['mustChangePassword'] = 0;
            session_regenerate_id(false);
            $_SESSION['tabSessions'][$tabToken]['lastActivityAt'] = time();
            $_SESSION['tabSessions'][$tabToken]['expiresAt'] = time() + (int) $user['tempoExpiracaoSessao'] * 60;
            $respond(200, ['success' => true, 'message' => 'Senha alterada com sucesso.']);
        }
    }

    if ($isAjax) {
        $respond(422, ['success' => false, 'message' => $error]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Método não permitido.');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $targetUrl = appFirstAllowedUrl($user);
    if ($targetUrl !== null) {
        header('Location: ' . appTabUrl($targetUrl));
        exit;
    }
    http_response_code(403);
    exit('Este perfil ainda não possui acesso a nenhuma tela.');
}

$error = $error ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Elo</title>
    <link rel="icon" type="image/png" href="<?php echo appEscape(appBaseUrl() . '/assets/imgs/logo-elo.png'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo appEscape(appBaseUrl() . '/assets/js/tailwind-config.js'); ?>"></script>
</head>
<body class="min-h-screen bg-gray-100 font-sans">
    <main class="mx-auto mt-12 w-full max-w-lg rounded-xl bg-white p-6 shadow">
        <h1 class="mb-2 text-2xl font-semibold text-gray-800">Alterar Senha</h1>
        <?php if ($forcedChange): ?>
            <p class="mb-5 text-sm text-gray-600">Por segurança, defina uma nova senha antes de continuar.</p>
        <?php else: ?>
            <p class="mb-5 text-sm text-gray-500">Conta: <?php echo appEscape($user['nomeCompleto']); ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert"><?php echo appEscape($error); ?></p>
        <?php endif; ?>
        <form method="post" data-password-policy-form class="space-y-4">
            <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
            <label class="block text-sm font-medium text-gray-700">Senha Atual
                <input type="password" name="senhaAtual" autocomplete="current-password" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
            </label>
            <label class="block text-sm font-medium text-gray-700">Nova Senha
                <input type="password" name="novaSenha" autocomplete="new-password" data-password-policy-input minlength="8" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
            </label>
            <label class="block text-sm font-medium text-gray-700">Confirmar Nova Senha
                <input type="password" name="confirmarSenha" autocomplete="new-password" data-password-confirm-input minlength="8" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
            </label>
            <p class="text-xs text-gray-500">Use pelo menos 8 caracteres, com uma letra maiúscula, uma minúscula, um número e um caractere especial.</p>
            <button class="rounded bg-elo-teal px-4 py-2 font-medium text-white hover:bg-elo-green">Salvar Nova Senha</button>
        </form>
    </main>
</body>
</html>
