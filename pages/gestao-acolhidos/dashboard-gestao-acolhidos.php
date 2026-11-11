<?php
require_once __DIR__ . '/../../auth/access.php';
appRequirePage('gestao-acolhidos.dashboard-gestao-acolhidos');
$baseUrl = appBaseUrl();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Gestão de Acolhidos | Sistema Elo</title>
    <link rel="icon" type="image/png" href="<?php echo appEscape($baseUrl . '/assets/imgs/logo-elo.png'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/tailwind-config.js'); ?>"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="flex h-screen flex-col overflow-hidden bg-gray-100 font-sans text-gray-800">
    <?php include __DIR__ . '/../../components/gestao-acolhidos/navbar-gestaoacolhidos.php'; ?>
    <div class="flex min-h-0 flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../components/gestao-acolhidos/sidebar-gestaoacolhidos.php'; ?>
        <main class="min-w-0 flex-1 overflow-y-auto bg-gray-50 p-4 md:p-6">
            <h1 class="mb-6 text-2xl font-bold text-gray-800">Dashboard Gestão de Acolhidos</h1>
        </main>
    </div>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
</body>
</html>