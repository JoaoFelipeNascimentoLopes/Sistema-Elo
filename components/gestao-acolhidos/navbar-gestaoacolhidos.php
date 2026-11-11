<?php
require_once __DIR__ . '/../../auth/access.php';
$baseUrl = appBaseUrl();
$logoUrl = $baseUrl . '/assets/imgs/logo-elo.png';
$usuarioAtual = appCurrentUser();
$catalogoModulos = appPageCatalog();
?>
<nav class="flex h-16 items-center justify-between bg-elo-teal px-4 text-white shadow-md">
    <div class="flex items-center gap-4">
        <button type="button" data-modal-toggle="modulosModal" class="flex h-10 w-10 items-center justify-center rounded border border-white/40 transition-colors hover:bg-white/10" title="Trocar módulo" aria-label="Trocar módulo">
            <i class="ph ph-squares-four text-2xl" aria-hidden="true"></i>
        </button>
        <img src="<?php echo appEscape($logoUrl); ?>" alt="Logo Elo" class="h-8 rounded object-contain">
        <div class="mx-1 h-6 w-px bg-white/40" aria-hidden="true"></div>
        <span class="text-lg font-medium tracking-wide">Gestão de Acolhidos</span>
    </div>
    <div class="flex shrink-0 items-center gap-2 sm:gap-4">
        <div class="shrink-0 whitespace-nowrap text-right text-[11px] sm:text-sm">
            <p class="font-medium"><?php echo appEscape($usuarioAtual['nomeCompleto']); ?></p>
            <p class="text-xs text-white/80">Sessão restante:
                <span data-session-countdown
                    data-expires-at="<?php echo (int) ($usuarioAtual['sessionExpiresAt'] ?? time()); ?>"
                    data-refresh-url="<?php echo appEscape($baseUrl . '/auth/refresh-session.php'); ?>"
                    data-login-url="<?php echo appEscape($baseUrl . '/auth/login.php'); ?>"
                    data-password-url="<?php echo appEscape($baseUrl . '/auth/change-password.php'); ?>"
                    data-tab-token="<?php echo appEscape((string) appCurrentTabToken()); ?>"
                    data-csrf="<?php echo appEscape(appCsrfToken()); ?>">--:--</span>
            </p>
        </div>
        <div class="h-8 w-px shrink-0 bg-white/40" aria-hidden="true"></div>
        <button type="button" data-modal-toggle="configuracoesModal" class="flex h-10 w-10 items-center justify-center rounded border border-white/40 transition-colors hover:bg-white/10" title="Configurações pessoais" aria-label="Configurações pessoais">
            <i class="ph ph-gear text-2xl" aria-hidden="true"></i>
        </button>
        <form method="post" action="<?php echo appEscape($baseUrl . '/auth/logout.php'); ?>">
            <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
            <input type="hidden" name="tabToken" value="<?php echo appEscape((string) appCurrentTabToken()); ?>">
            <button type="submit" class="flex h-10 w-10 items-center justify-center rounded border border-white/40 transition-all hover:border-red-500 hover:bg-red-500" title="Sair do sistema" aria-label="Sair do sistema">
                <i class="ph ph-sign-out text-2xl" aria-hidden="true"></i>
            </button>
        </form>
    </div>
</nav>
<?php include __DIR__ . '/../personal-settings-modal.php'; ?>
<div id="modulosModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50">
    <div class="relative w-[650px] max-w-[95%] rounded-xl bg-white p-6 shadow-2xl">
        <button type="button" data-modal-toggle="modulosModal" class="absolute right-4 top-4 text-gray-400 transition-colors hover:text-gray-700" aria-label="Fechar">
            <i class="ph ph-x text-2xl" aria-hidden="true"></i>
        </button>
        <h2 class="mb-6 text-xl font-semibold text-gray-800">Módulos do Sistema</h2>
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
            <?php foreach ($catalogoModulos as $moduleKey => $modulo): ?>
                <?php $moduleUrl = appFirstAllowedModuleUrl($moduleKey, $usuarioAtual); ?>
                <?php if ($moduleUrl === null) { continue; } ?>
                <a href="<?php echo appEscape($moduleUrl); ?>" class="group flex flex-col items-center justify-center rounded-lg border p-4 text-gray-600 transition-all hover:border-elo-green hover:bg-elo-green/10 hover:text-elo-teal">
                    <i class="ph <?php echo appEscape($modulo['icone']); ?> mb-2 text-4xl transition-transform group-hover:scale-110" aria-hidden="true"></i>
                    <span class="text-center text-sm font-medium"><?php echo appEscape($modulo['nome']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>