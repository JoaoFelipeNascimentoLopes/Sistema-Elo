<?php
require_once __DIR__ . '/../auth/access.php';

$sidebarModuleKey = (string) ($sidebarModuleKey ?? '');
$sidebarCatalog = appPageCatalog()[$sidebarModuleKey] ?? null;
if ($sidebarCatalog === null) {
    throw new InvalidArgumentException('Módulo inválido para a sidebar.');
}

$sidebarGroups = appSidebarPageGroups($sidebarModuleKey);
$sidebarCurrentPage = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
$sidebarGroupOpen = [];
foreach ($sidebarGroups as $groupKey => $group) {
    $sidebarGroupOpen[$groupKey] = false;
    foreach ($group['paginas'] as $page) {
        $pagePath = realpath(__DIR__ . '/../pages/' . $sidebarModuleKey . '/' . $page['arquivo']);
        if ($pagePath !== false && $pagePath === $sidebarCurrentPage) {
            $sidebarGroupOpen[$groupKey] = true;
            break;
        }
    }
}
$sidebarId = 'sidebar-' . $sidebarModuleKey;
$searchId = 'pesquisa-telas-' . $sidebarModuleKey;
$emptyId = 'pesquisa-vazia-' . $sidebarModuleKey;
?>
<aside id="<?php echo appEscape($sidebarId); ?>" class="flex h-[calc(100vh-3.5rem)] w-72 flex-col border-r border-gray-300 bg-white shadow-sm">
    <div class="flex items-center justify-between border-b border-gray-300 bg-gray-200/60 px-3 py-2">
        <span class="text-sm font-semibold text-gray-800">Navegação</span>
    </div>

    <div class="border-b border-gray-200 bg-gray-50 p-2">
        <div class="relative">
            <input id="<?php echo appEscape($searchId); ?>" type="search" placeholder="Pesquise o programa desejado..." aria-label="Pesquisar telas"
                   class="w-full rounded border border-gray-300 py-1.5 pl-2 pr-8 text-xs text-gray-700 shadow-inner transition-all focus:border-elo-teal focus:outline-none focus:ring-1 focus:ring-elo-teal">
            <i class="ph ph-magnifying-glass absolute right-2 top-2 text-sm text-gray-400"></i>
        </div>
    </div>

    <div class="custom-scrollbar flex-1 overflow-y-auto p-2 pb-20 text-sm font-medium text-gray-700">
        <p id="<?php echo appEscape($emptyId); ?>" class="hidden p-2 text-xs text-gray-500">Nenhuma tela encontrada.</p>
        <?php foreach ($sidebarGroups as $groupKey => $group): ?>
            <?php
            $menuId = 'menu-' . $sidebarModuleKey . '-' . $groupKey;
            $iconId = 'icon-' . $sidebarModuleKey . '-' . $groupKey;
            $open = $sidebarGroupOpen[$groupKey];
            ?>
            <div class="mb-1" data-sidebar-folder>
                <button type="button" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>"
                        onclick="toggleModuleSidebarFolder('<?php echo appEscape($menuId); ?>', '<?php echo appEscape($iconId); ?>')"
                        class="group flex w-full items-center rounded p-1 transition-colors hover:bg-gray-100">
                    <i id="<?php echo appEscape($iconId); ?>" class="ph-fill <?php echo $open ? 'ph-minus-square' : 'ph-plus-square'; ?> mr-1.5 text-gray-400 group-hover:text-elo-teal"></i>
                    <i class="ph-fill ph-folder mr-2 text-xl text-yellow-400"></i>
                    <span><?php echo appEscape($group['nome']); ?></span>
                </button>
                <nav id="<?php echo appEscape($menuId); ?>" class="<?php echo $open ? 'flex' : 'hidden'; ?> mt-1 flex-col gap-0.5 pl-7">
                    <?php foreach ($group['paginas'] as $page): ?>
                        <?php
                        $pageIcon = match ($page['chave']) {
                            'usuarios' => 'ph-users',
                            'perfis-acesso' => 'ph-shield-check',
                            default => 'ph-file-text',
                        };
                        ?>
                        <a href="<?php echo appEscape($page['url']); ?>" class="flex items-center rounded p-1 text-gray-700 transition-colors hover:bg-elo-teal/10 hover:text-elo-teal">
                            <i class="ph-fill <?php echo appEscape($pageIcon); ?> mr-2 text-base text-gray-400"></i>
                            <?php echo appEscape($page['nome']); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        <?php endforeach; ?>
    </div>
</aside>

<script>
    function toggleModuleSidebarFolder(menuId, iconId) {
        const menu = document.getElementById(menuId);
        const icon = document.getElementById(iconId);
        const button = icon.closest('button');
        const isHidden = menu.classList.toggle('hidden');
        menu.classList.toggle('flex', !isHidden);
        icon.classList.toggle('ph-plus-square', isHidden);
        icon.classList.toggle('ph-minus-square', !isHidden);
        button.setAttribute('aria-expanded', String(!isHidden));
    }

    (() => {
        const sidebar = document.getElementById(<?php echo json_encode($sidebarId); ?>);
        const search = document.getElementById(<?php echo json_encode($searchId); ?>);
        const emptyMessage = document.getElementById(<?php echo json_encode($emptyId); ?>);
        const folders = Array.from(sidebar.querySelectorAll('[data-sidebar-folder]'));
        const initialFolderStates = folders.map((folder) => {
            const menu = folder.querySelector('nav');
            const button = folder.querySelector('button');
            const icon = button.querySelector('i[id]');
            return {
                folder,
                menuHidden: menu.classList.contains('hidden'),
                iconClosed: icon.classList.contains('ph-plus-square')
            };
        });

        search.addEventListener('input', () => {
            const term = search.value.trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
            let visiblePages = 0;

            folders.forEach((folder) => {
                const menu = folder.querySelector('nav');
                const button = folder.querySelector('button');
                const icon = button.querySelector('i[id]');
                const links = Array.from(menu.querySelectorAll('a'));
                let visibleInFolder = 0;

                links.forEach((link) => {
                    const text = link.textContent.trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
                    const matches = !term || text.includes(term);
                    link.classList.toggle('hidden', !matches);
                    if (matches) visibleInFolder++;
                });

                visiblePages += visibleInFolder;
                if (term) {
                    const hasMatch = visibleInFolder > 0;
                    folder.classList.toggle('hidden', !hasMatch);
                    menu.classList.toggle('hidden', !hasMatch);
                    menu.classList.toggle('flex', hasMatch);
                    icon.classList.toggle('ph-plus-square', !hasMatch);
                    icon.classList.toggle('ph-minus-square', hasMatch);
                    button.setAttribute('aria-expanded', String(hasMatch));
                }
            });

            if (!term) {
                initialFolderStates.forEach(({ folder, menuHidden, iconClosed }) => {
                    const menu = folder.querySelector('nav');
                    const button = folder.querySelector('button');
                    const icon = button.querySelector('i[id]');
                    folder.classList.remove('hidden');
                    menu.classList.toggle('hidden', menuHidden);
                    menu.classList.toggle('flex', !menuHidden);
                    icon.classList.toggle('ph-plus-square', iconClosed);
                    icon.classList.toggle('ph-minus-square', !iconClosed);
                    button.setAttribute('aria-expanded', String(!menuHidden));
                });
            }

            emptyMessage.classList.toggle('hidden', !term || visiblePages > 0);
        });

        sidebar.addEventListener('click', (event) => {
            const link = event.target.closest('a');
            if (link && link.origin === window.location.origin && link.pathname === window.location.pathname) {
                event.preventDefault();
            }
        });
    })();
</script>
