<?php
require_once __DIR__ . '/access.php';

if (appCurrentUser()) {
    $user = appCurrentUser(true);
    $targetUrl = $user ? appFirstAllowedUrl($user) : null;
    if ($targetUrl !== null) {
        header('Location: ' . $targetUrl);
        exit;
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    $login = trim((string) ($_POST['login'] ?? ''));
    $password = (string) ($_POST['senha'] ?? '');

    $query = appPdo()->prepare(
        'SELECT idUsuario, senhaHash, ativo
         FROM tbUsuario
         WHERE login = :login
         LIMIT 1'
    );
    $query->execute(['login' => $login]);
    $user = $query->fetch();

    if ($user && (int) $user['ativo'] === 1 && password_verify($password, $user['senhaHash'])) {
        session_regenerate_id(true);
        $_SESSION['idUsuario'] = (int) $user['idUsuario'];
        unset($_SESSION['usuario']);
        $authenticatedUser = appCurrentUser(true);

        $targetUrl = $authenticatedUser ? appFirstAllowedUrl($authenticatedUser) : null;
        if ($targetUrl !== null) {
            header('Location: ' . $targetUrl);
            exit;
        }

        $_SESSION = [];
        session_destroy();
        $error = 'Este perfil ainda não possui acesso a nenhuma tela.';
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
    <title>Entrar - Sistema Elo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Poppins', 'sans-serif'] }, colors: { 'elo-teal': '#007C89', 'elo-green': '#48C0B5' } } } };
    </script>
</head>
<body class="min-h-screen bg-gray-100 font-sans flex items-center justify-center p-4">
    <main class="w-full max-w-md rounded-xl bg-white p-8 shadow-lg">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-semibold text-elo-teal">Sistema Elo</h1>
            <p class="mt-1 text-sm text-gray-500">Entre com seu usuário e senha</p>
        </div>
        <?php if ($error !== ''): ?>
            <p role="alert" class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700"><?php echo appEscape($error); ?></p>
        <?php endif; ?>
        <form method="post" class="space-y-4">
            <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
            <label class="block text-sm font-medium text-gray-700">
                Login
                <input name="login" type="text" autocomplete="username" required maxlength="80"
                       class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-elo-teal focus:outline-none focus:ring-1 focus:ring-elo-teal">
            </label>
            <label class="block text-sm font-medium text-gray-700">
                Senha
                <input name="senha" type="password" autocomplete="current-password" required
                       class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-elo-teal focus:outline-none focus:ring-1 focus:ring-elo-teal">
            </label>
            <button type="submit" class="w-full rounded bg-elo-teal px-4 py-2 font-medium text-white hover:bg-elo-green">
                Entrar
            </button>
        </form>
    </main>
</body>
</html>
