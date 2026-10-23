<?php
require_once __DIR__ . '/access.php';
$user = appCurrentUser(true);

if (!$user) {
    header('Location: ' . appBaseUrl() . '/auth/login.php');
    exit;
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    $currentPassword = (string) ($_POST['senhaAtual'] ?? '');
    $newPassword = (string) ($_POST['novaSenha'] ?? '');
    $confirmation = (string) ($_POST['confirmarSenha'] ?? '');

    if (strlen($newPassword) < 12) {
        $error = 'A nova senha deve ter ao menos 12 caracteres.';
    } elseif ($newPassword !== $confirmation) {
        $error = 'A confirmação não corresponde à nova senha.';
    } else {
        $query = appPdo()->prepare('SELECT senhaHash FROM tbUsuario WHERE idUsuario = :idUsuario');
        $query->execute(['idUsuario' => (int) $user['idUsuario']]);
        $passwordHash = $query->fetchColumn();

        if (!is_string($passwordHash) || !password_verify($currentPassword, $passwordHash)) {
            $error = 'A senha atual está incorreta.';
        } else {
            $update = appPdo()->prepare('UPDATE tbUsuario SET senhaHash = :senhaHash WHERE idUsuario = :idUsuario');
            $update->execute([
                'senhaHash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'idUsuario' => (int) $user['idUsuario'],
            ]);
            $success = 'Senha alterada com sucesso.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alterar senha - Sistema Elo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Poppins', 'sans-serif'] }, colors: { 'elo-teal': '#007C89', 'elo-green': '#48C0B5' } } } };</script>
</head>
<body class="min-h-screen bg-gray-100 font-sans">
    <main class="mx-auto mt-12 w-full max-w-lg rounded-xl bg-white p-6 shadow">
        <h1 class="mb-2 text-2xl font-semibold text-gray-800">Alterar senha</h1>
        <p class="mb-5 text-sm text-gray-500">Conta: <?php echo appEscape($user['login']); ?></p>
        <?php if ($error !== ''): ?><p role="alert" class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700"><?php echo appEscape($error); ?></p><?php endif; ?>
        <form method="post" class="space-y-4">
            <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
            <label class="block text-sm font-medium text-gray-700">Senha atual
                <input type="password" name="senhaAtual" autocomplete="current-password" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
            </label>
            <label class="block text-sm font-medium text-gray-700">Nova senha (mínimo 12 caracteres)
                <input type="password" name="novaSenha" autocomplete="new-password" minlength="12" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
            </label>
            <label class="block text-sm font-medium text-gray-700">Confirme a nova senha
                <input type="password" name="confirmarSenha" autocomplete="new-password" minlength="12" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
            </label>
            <div class="flex gap-2">
                <button class="rounded bg-elo-teal px-4 py-2 font-medium text-white hover:bg-elo-green">Salvar nova senha</button>
                <a href="<?php echo appEscape(appBaseUrl() . '/pages/geral/dashboard-geral.php'); ?>" class="rounded border px-4 py-2 text-gray-700 hover:bg-gray-50">Voltar</a>
            </div>
        </form>
    </main>
    <?php $toastSuccess = $success; include __DIR__ . '/../components/toast.php'; ?>
</body>
</html>
