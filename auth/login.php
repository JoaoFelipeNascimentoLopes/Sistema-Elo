<?php
require_once __DIR__ . '/access.php';

if (appCurrentUser()) {
    $user = appCurrentUser(true);
    $targetUrl = $user ? appFirstAllowedUrl($user) : null;
    if ($targetUrl !== null) {
        header('Location: ' . appTabUrl($targetUrl));
        exit;
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    $login = trim((string) ($_POST['login'] ?? ''));
    $password = (string) ($_POST['senha'] ?? '');

    $query = appPdo()->prepare(
        'SELECT idUsuario, senhaHash, ativo, bloqueado, forcarTrocaSenha
         FROM tbUsuario
         WHERE login = :login
         LIMIT 1'
    );
    $query->execute(['login' => $login]);
    $user = $query->fetch();

    if ($user && password_verify($password, $user['senhaHash']) && (int) $user['bloqueado'] === 1) {
        $error = 'Esta conta está bloqueada. Entre em contato com um administrador.';
    } elseif ($user && (int) $user['ativo'] === 1 && password_verify($password, $user['senhaHash'])) {
        $tabToken = appCurrentTabToken();
        if ($tabToken === null) {
            $error = 'Não foi possível iniciar esta sessão. Atualize a página e tente novamente.';
        } else {
            $_SESSION['tabSessions'][$tabToken] = [
                'idUsuario' => (int) $user['idUsuario'],
                'lastActivityAt' => time(),
                'expiresAt' => time() + 30 * 60,
                'mustChangePassword' => (int) $user['forcarTrocaSenha'],
            ];
            session_regenerate_id(false);
            $authenticatedUser = appCurrentUser(true);

            $targetUrl = $authenticatedUser ? appFirstAllowedUrl($authenticatedUser) : null;
            if ($targetUrl !== null) {
                header('Location: ' . appTabUrl($targetUrl));
                exit;
            }

            unset($_SESSION['tabSessions'][$tabToken]);
            $error = 'Este perfil ainda não possui acesso a nenhuma tela.';
        }
    } else {
        $error = 'Login ou senha inválidos, ou usuário inativo.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Elo</title>
    <link rel="icon" type="image/png" href="<?php echo appEscape(appBaseUrl() . '/assets/imgs/logo-elo.png'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo appEscape(appBaseUrl() . '/assets/js/tailwind-config.js'); ?>"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="min-h-screen bg-elo-teal font-sans flex items-center justify-center p-4">
    <main class="w-full max-w-md rounded-xl bg-white p-8 shadow-lg">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-semibold text-elo-teal">Sistema Elo</h1>
            <p class="mt-1 text-sm text-gray-500">Entre com seu usuário e senha</p>
        </div>
        <form method="post" class="space-y-4">
            <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
            <label class="block text-sm font-medium text-gray-700">
                Login
                <input name="login" type="text" autocomplete="username" required maxlength="80"
                       class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-elo-teal focus:outline-none focus:ring-1 focus:ring-elo-teal">
            </label>
            <div>
                <label for="senha-login" class="block text-sm font-medium text-gray-700">Senha</label>
                <div class="relative mt-1">
                    <input id="senha-login" name="senha" type="password" autocomplete="current-password" required
                           class="w-full rounded border border-gray-300 px-3 py-2 pr-11 focus:border-elo-teal focus:outline-none focus:ring-1 focus:ring-elo-teal">
                    <button type="button" data-password-toggle="senha-login" aria-label="Mostrar senha" aria-pressed="false"
                            class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-elo-teal focus:outline-none focus:ring-2 focus:ring-inset focus:ring-elo-teal">
                        <i data-password-icon class="ph ph-eye text-xl" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="w-full rounded bg-elo-teal px-4 py-2 font-medium text-white hover:bg-elo-green">
                Entrar
            </button>
        </form>
    </main>
    <?php $toastError = $error; include __DIR__ . '/../components/toast.php'; ?>
    <script src="<?php echo appEscape(appBaseUrl() . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
</body>
</html>
