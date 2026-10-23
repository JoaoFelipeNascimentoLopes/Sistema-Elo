<?php
require_once __DIR__ . '/../../auth/access.php';
$baseUrl = appBaseUrl();
$logoUrl = $baseUrl . '/assets/imgs/logo-elo.png';
$usuarioAtual = appCurrentUser();
$catalogoModulos = appPageCatalog();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Elo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Poppins', 'sans-serif'] },
                    colors: { 'elo-teal': '#007C89', 'elo-green': '#48C0B5' }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100 font-sans">
    <nav class="flex h-16 items-center justify-between bg-elo-teal px-4 text-white shadow-md">
        <div class="flex items-center gap-4">
            <button onclick="toggleModal('modulosModal')" class="w-10 h-10 border border-white/40 rounded flex items-center justify-center hover:bg-white/10 transition-colors" title="Trocar Módulo">
                <i class="ph ph-squares-four text-2xl"></i>
            </button>
            <div class="flex items-center">
                <img src="<?php echo appEscape($logoUrl); ?>" alt="Logo Elo" class="h-8 object-contain rounded">
            </div>
            <div class="h-6 w-px bg-white/40 mx-1"></div>
            <span class="text-lg font-medium tracking-wide">Geral</span>
        </div>
        <div class="flex items-center gap-4">
            <button onclick="toggleModal('configuracoesModal')" class="w-10 h-10 border border-white/40 rounded flex items-center justify-center hover:bg-white/10 transition-colors" title="Configurações pessoais">
                <i class="ph ph-gear text-2xl"></i>
            </button>
            <form method="post" action="<?php echo appEscape($baseUrl . '/auth/logout.php'); ?>">
                <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                <button type="submit" class="w-10 h-10 border border-white/40 rounded flex items-center justify-center hover:bg-red-500 hover:border-red-500 transition-all" title="Sair do Sistema">
                    <i class="ph ph-sign-out text-2xl"></i>
                </button>
            </form>
        </div>
    </nav>

    <div id="modulosModal" class="fixed inset-0 bg-black/50 hidden flex items-center justify-center z-50">
        <div class="relative w-[650px] max-w-[95%] rounded-xl bg-white p-6 shadow-2xl">
            <button onclick="toggleModal('modulosModal')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 transition-colors" aria-label="Fechar">
                <i class="ph ph-x text-2xl"></i>
            </button>
            <h2 class="text-xl font-semibold text-gray-800 mb-6">Módulos do Sistema</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <?php foreach ($catalogoModulos as $moduleKey => $modulo): ?>
                    <?php $moduleUrl = appFirstAllowedModuleUrl($moduleKey, $usuarioAtual); ?>
                    <?php if ($moduleUrl === null) { continue; } ?>
                    <a href="<?php echo appEscape($moduleUrl); ?>" class="flex flex-col items-center justify-center p-4 border rounded-lg hover:bg-elo-green/10 hover:border-elo-green text-gray-600 hover:text-elo-teal transition-all group">
                        <i class="ph <?php echo appEscape($modulo['icone']); ?> text-4xl mb-2 group-hover:scale-110 transition-transform"></i>
                        <span class="font-medium text-sm text-center"><?php echo appEscape($modulo['nome']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div id="configuracoesModal" class="fixed inset-0 bg-black/50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl w-[450px] max-w-[95%] p-6 relative">
            <button onclick="toggleModal('configuracoesModal')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 transition-colors" aria-label="Fechar">
                <i class="ph ph-x text-2xl"></i>
            </button>
            <h2 class="text-xl font-semibold text-gray-800 mb-6">Configurações pessoais</h2>
            <a href="<?php echo appEscape($baseUrl . '/auth/change-password.php'); ?>" class="flex items-center gap-3 rounded-lg border p-4 text-gray-700 transition-colors hover:border-elo-green hover:bg-elo-green/10 hover:text-elo-teal">
                <i class="ph ph-lock-key text-2xl"></i>
                <span class="font-medium">Alterar senha</span>
            </a>
        </div>
    </div>
    <script>
        function toggleModal(modalID) {
            const modal = document.getElementById(modalID);
            modal.classList.toggle("hidden");
        }
    </script>
</body>
</html>
