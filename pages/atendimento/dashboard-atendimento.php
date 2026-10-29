<?php
require_once __DIR__ . '/../../auth/access.php';
appRequirePage('atendimento.dashboard-atendimento');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Elo</title>
    <link rel="icon" type="image/png" href="<?php echo appEscape(appBaseUrl() . '/assets/imgs/logo-elo.png'); ?>">
    
    <!-- Fontes, Tailwind e Ícones -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo appEscape(appBaseUrl() . '/assets/js/tailwind-config.js'); ?>"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>

<!-- 1. BODY: O h-screen e overflow-hidden travam a página no tamanho exato do monitor -->
<body class="bg-gray-100 font-sans flex flex-col h-screen overflow-hidden">

    <!-- 2. NAVBAR: Fica fixa na primeira linha do topo -->
    <?php include __DIR__ . '/../../components/atendimento/navbar-atendimento.php'; ?>

    <!-- 3. CONTAINER INFERIOR: Ocupa todo o espaço vertical que sobrou abaixo da navbar (flex-1) -->
    <div class="flex-1 flex overflow-hidden">
        
        <?php include __DIR__ . '/../../components/atendimento/sidebar-atendimento.php'; ?>

        <!-- 5. ÁREA DE CONTEÚDO: Ocupa o resto da tela à direita (flex-1) e ganha a própria barra de rolagem (overflow-y-auto) -->
        <main class="flex-1 overflow-y-auto bg-gray-50 p-6">
            
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Versões do Sistema</h1>
            </div>
            
            <!-- Suas tabelas e formulários entram aqui -->
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 min-h-[800px] flex items-center justify-center text-gray-400">
                Se este conteúdo crescer, apenas esta área vai rolar. A Navbar e a Sidebar ficarão travadas.
            </div>

        </main>
    </div>

    <script src="<?php echo appEscape(appBaseUrl() . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
</body>
</html>