<?php

function appStartSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

function appCurrentTabToken(): ?string
{
    $token = $_POST['tabToken'] ?? $_GET['tabToken'] ?? $_SERVER['HTTP_X_TAB_SESSION'] ?? null;
    if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/', $token)) {
        return null;
    }

    return $token;
}

function appTabLoginUrl(): string
{
    return appTabUrl(appBaseUrl() . '/auth/login.php');
}

function appTabUrl(string $url): string
{
    $token = appCurrentTabToken();
    if ($token === null) {
        return $url;
    }

    $fragmentPosition = strpos($url, '#');
    $fragment = $fragmentPosition === false ? '' : substr($url, $fragmentPosition);
    $urlWithoutFragment = $fragmentPosition === false ? $url : substr($url, 0, $fragmentPosition);
    $separator = str_contains($urlWithoutFragment, '?') ? '&' : '?';

    return $urlWithoutFragment . $separator . 'tabToken=' . rawurlencode($token) . $fragment;
}

function appPdo(): PDO
{
    static $connection;

    if (!$connection instanceof PDO) {
        require_once __DIR__ . '/../db/conexao.php';
        $connection = $pdo;
    }

    return $connection;
}

function appBaseUrl(): string
{
    $projectRoot = realpath(__DIR__ . '/..');
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');

    if ($projectRoot === false || $documentRoot === false) {
        throw new RuntimeException('Não foi possível determinar o caminho público do projeto.');
    }

    $documentRootPrefix = rtrim($documentRoot, '\\/') . DIRECTORY_SEPARATOR;
    $insideDocumentRoot = strncasecmp($projectRoot, $documentRootPrefix, strlen($documentRootPrefix)) === 0
        || strcasecmp($projectRoot, $documentRoot) === 0;

    if (!$insideDocumentRoot) {
        throw new RuntimeException('A pasta do projeto precisa estar dentro da raiz pública do servidor.');
    }

    return rtrim(str_replace(DIRECTORY_SEPARATOR, '/', substr($projectRoot, strlen($documentRoot))), '/');
}

function appPageCatalog(): array
{
    $base = appBaseUrl();
    $pagesRoot = realpath(__DIR__ . '/../pages');
    if ($pagesRoot === false) {
        throw new RuntimeException('A pasta pública das páginas não foi encontrada.');
    }
    $pagesRootPrefix = rtrim($pagesRoot, '\\/') . DIRECTORY_SEPARATOR;

    $moduleNames = [
        'atendimento' => 'Atendimento',
        'financeiro' => 'Financeiro',
        'geral' => 'Geral',
        'gestao-acolhidos' => 'Gestão de Acolhidos',
    ];
    $moduleIcons = [
        'atendimento' => 'ph-stethoscope',
        'financeiro' => 'ph-currency-circle-dollar',
        'geral' => 'ph-gear',
        'gestao-acolhidos' => 'ph-users',
    ];
    $pageNames = [
        'atendimento/dashboard-atendimento.php' => 'Dashboard de Atendimento',
        'financeiro/dashboard-financeiro.php' => 'Dashboard Financeiro',
        'financeiro/cadastros/categoria-lancamentos.php' => 'Categorias de Lançamentos',
        'financeiro/cadastros/fonte-recurso.php' => 'Fontes de Recursos',
        'financeiro/relatorios/categoria-lancamentos.php' => 'Categorias de Lançamentos',
        'financeiro/relatorios/fontes-recurso.php' => 'Fontes de Recursos',
        'geral/dashboard-geral.php' => 'Dashboard Geral',
        'geral/cadastros/usuarios.php' => 'Usuários',
        'geral/cadastros/perfis-acesso.php' => 'Perfis de Acesso',
        'geral/relatorios/usuarios-por-perfis.php' => 'Usuários por Perfis',
        'gestao-acolhidos/dashboard-gestao-acolhidos.php' => 'Dashboard Gestão de Acolhidos',
    ];
    $legacyPageKeys = [
        'financeiro/cadastros/categoria-lancamentos.php' => 'categoria-lancamentos',
        'financeiro/cadastros/fonte-recurso.php' => 'fonte-recurso',
        'geral/cadastros/usuarios.php' => 'usuarios',
        'geral/cadastros/perfis-acesso.php' => 'perfis-acesso',
    ];
    $catalog = [];

    foreach (new DirectoryIterator($pagesRoot) as $moduleDirectory) {
        if ($moduleDirectory->isDot() || !$moduleDirectory->isDir()) {
            continue;
        }

        $moduleKey = $moduleDirectory->getFilename();
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $moduleKey)) {
            continue;
        }
        $modulePath = $moduleDirectory->getRealPath();
        if ($modulePath === false
            || strncasecmp($modulePath, $pagesRootPrefix, strlen($pagesRootPrefix)) !== 0) {
            continue;
        }

        $moduleName = $moduleNames[$moduleKey] ?? ucwords(str_replace(['-', '_'], ' ', $moduleKey));
        $catalog[$moduleKey] = [
            'nome' => $moduleName,
            'icone' => $moduleIcons[$moduleKey] ?? 'ph-folder',
            'paginas' => [],
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($modulePath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isLink() || !$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($modulePath) + 1));
            $pageKey = $legacyPageKeys["$moduleKey/$relativePath"] ?? substr($relativePath, 0, -4);
            $relativeUrl = implode('/', array_map('rawurlencode', explode('/', substr($relativePath, 0, -4))));
            $pageLabel = $pageNames["$moduleKey/$relativePath"] ?? null;
            if ($pageLabel === null) {
                $fileName = pathinfo($relativePath, PATHINFO_FILENAME);
                if ($fileName === 'index') {
                    $pageLabel = "Início - $moduleName";
                } else {
                    $localizedWords = [
                        'usuario' => 'Usuário',
                        'usuarios' => 'Usuários',
                        'relatorio' => 'Relatório',
                        'relatorios' => 'Relatórios',
                        'movimentacao' => 'Movimentação',
                        'movimentacoes' => 'Movimentações',
                        'cadastro' => 'Cadastro',
                        'cadastros' => 'Cadastros',
                        'configuracao' => 'Configuração',
                        'configuracoes' => 'Configurações',
                        'lancamento' => 'Lançamento',
                        'lancamentos' => 'Lançamentos',
                        'categoria' => 'Categoria',
                        'categorias' => 'Categorias',
                        'gestao' => 'Gestão',
                        'acolhido' => 'Acolhido',
                        'acolhidos' => 'Acolhidos',
                        'beneficiario' => 'Beneficiário',
                        'beneficiarios' => 'Beneficiários',
                        'saida' => 'Saída',
                        'saidas' => 'Saídas',
                        'entrada' => 'Entrada',
                        'entradas' => 'Entradas',
                        'movimento' => 'Movimento',
                        'movimentos' => 'Movimentos',
                        'por' => 'por',
                        'de' => 'de',
                        'da' => 'da',
                        'do' => 'do',
                        'em' => 'em',
                        'e' => 'e',
                    ];
                    $words = preg_split('/[-_]+/', $fileName);
                    $words = array_map(
                        static fn(string $word): string => $localizedWords[strtolower($word)] ?? ucfirst($word),
                        $words
                    );
                    $pageLabel = implode(' ', $words);
                    $pageLabel = ucfirst($pageLabel);
                }
            }

            $catalog[$moduleKey]['paginas'][$pageKey] = [
                'nome' => $pageLabel,
                'url' => "$base/pages/$moduleKey/$relativeUrl.php",
                'arquivo' => $relativePath,
            ];
        }

        ksort($catalog[$moduleKey]['paginas']);
    }

    ksort($catalog);

    return $catalog;
}

function appCurrentUser(bool $refresh = false): ?array
{
    appStartSession();
    $tabToken = appCurrentTabToken();
    if ($tabToken === null || empty($_SESSION['tabSessions'][$tabToken]['idUsuario'])) {
        return null;
    }

    $query = appPdo()->prepare(
        'SELECT u.idUsuario, u.nomeCompleto, u.login, u.ativo, u.bloqueado,
                u.forcarTrocaSenha, u.tempoExpiracaoSessao, p.idPerfil, p.nomePerfil,
                p.permissoes, p.administradorGlobal
         FROM tbUsuario u
         INNER JOIN tbPerfilAcesso p ON p.idPerfil = u.idPerfil
         WHERE u.idUsuario = :idUsuario
         LIMIT 1'
    );
    $query->execute(['idUsuario' => (int) $_SESSION['tabSessions'][$tabToken]['idUsuario']]);
    $user = $query->fetch();

    if (!$user || !(int) $user['ativo'] || (int) $user['bloqueado'] === 1) {
        unset($_SESSION['tabSessions'][$tabToken]);
        return null;
    }

    $now = time();
    $timeoutSeconds = (int) $user['tempoExpiracaoSessao'] * 60;
    if ($now - (int) $_SESSION['tabSessions'][$tabToken]['lastActivityAt'] >= $timeoutSeconds) {
        unset($_SESSION['tabSessions'][$tabToken]);
        return null;
    }

    $_SESSION['tabSessions'][$tabToken]['lastActivityAt'] = $now;
    $_SESSION['tabSessions'][$tabToken]['expiresAt'] = $now + $timeoutSeconds;
    $user['permissoes'] = json_decode($user['permissoes'], true, 512, JSON_THROW_ON_ERROR);
    $user['sessionExpiresAt'] = $_SESSION['tabSessions'][$tabToken]['expiresAt'];
    $user['mustChangePassword'] = (int) $_SESSION['tabSessions'][$tabToken]['mustChangePassword'];

    return $user;
}

function appCanAccess(string $pageKey, ?array $user = null): bool
{
    $user = $user ?? appCurrentUser(true);
    if (!$user) {
        return false;
    }

    if ($pageKey === 'instituicao.informacoes') {
        return appIsPrimaryAdmin($user)
            || in_array('informacoes', $user['permissoes']['instituicao'] ?? [], true);
    }

    if ($user['login'] === 'admin-global' && $pageKey === 'geral.perfis-acesso') {
        return true;
    }

    [$module, $page] = array_pad(explode('.', $pageKey, 2), 2, '');
    $permissions = $user['permissoes'];

    return in_array('*', $permissions['*'] ?? [], true)
        || in_array('*', $permissions[$module] ?? [], true)
        || in_array($page, $permissions[$module] ?? [], true);
}

function appIsPrimaryAdmin(?array $user = null): bool
{
    $user = $user ?? appCurrentUser(true);

    return $user !== null && $user['login'] === 'admin-global';
}

function appFirstAllowedUrl(array $user): ?string
{
    $catalog = appPageCatalog();
    if (appCanAccess('geral.dashboard-geral', $user)) {
        return $catalog['geral']['paginas']['dashboard-geral']['url'];
    }

    foreach ($catalog as $moduleKey => $module) {
        foreach ($module['paginas'] as $pageKey => $page) {
            if (appCanAccess("$moduleKey.$pageKey", $user)) {
                return $page['url'];
            }
        }
    }

    return null;
}

function appFirstAllowedModuleUrl(string $moduleKey, ?array $user = null): ?string
{
    $user = $user ?? appCurrentUser(true);
    $module = appPageCatalog()[$moduleKey] ?? null;
    if (!$user || !$module) {
        return null;
    }

    foreach ($module['paginas'] as $pageKey => $page) {
        if (str_starts_with(strtolower(pathinfo($page['arquivo'], PATHINFO_FILENAME)), 'dashboard')
            && appCanAccess("$moduleKey.$pageKey", $user)) {
            return $page['url'];
        }
    }

    foreach ($module['paginas'] as $pageKey => $page) {
        if (appCanAccess("$moduleKey.$pageKey", $user)) {
            return $page['url'];
        }
    }

    return null;
}

function appVisibleModulePages(string $moduleKey, ?array $user = null): array
{
    $module = appPageCatalog()[$moduleKey] ?? null;
    if (!$module) {
        return [];
    }

    $user = $user ?? appCurrentUser();
    if (!$user) {
        return [];
    }

    $pages = [];
    foreach ($module['paginas'] as $pageKey => $page) {
        if (appCanAccess("$moduleKey.$pageKey", $user)) {
            $page['chave'] = $pageKey;
            $pages[] = $page;
        }
    }

    return $pages;
}

function appPageCategory(string $relativePath): string
{
    $fileName = strtolower(pathinfo($relativePath, PATHINFO_FILENAME));
    if ($fileName === 'dashboard' || str_starts_with($fileName, 'dashboard-')) {
        return 'dashboards';
    }

    $directory = strtolower(str_replace('\\', '/', dirname($relativePath)));
    $directory = str_replace(['á', 'ã', 'â'], 'a', $directory);
    $directory = str_replace(['ç'], 'c', $directory);
    $directory = str_replace(['é', 'ê'], 'e', $directory);
    $directory = str_replace(['í'], 'i', $directory);
    $directory = str_replace(['ó', 'ô', 'õ'], 'o', $directory);
    $directory = str_replace(['ú'], 'u', $directory);

    return match (explode('/', $directory)[0]) {
        'movimentacoes' => 'movimentacoes',
        'relatorios', 'relatorio' => 'relatorios',
        default => 'cadastros',
    };
}

function appSidebarPageGroups(string $moduleKey, ?array $user = null): array
{
    $groups = [
        'dashboards' => ['nome' => 'Dashboards', 'paginas' => []],
        'cadastros' => ['nome' => 'Cadastros', 'paginas' => []],
        'movimentacoes' => ['nome' => 'Movimentações', 'paginas' => []],
        'relatorios' => ['nome' => 'Relatórios', 'paginas' => []],
    ];

    foreach (appVisibleModulePages($moduleKey, $user) as $page) {
        $groupKey = appPageCategory($page['arquivo']);
        $groups[$groupKey]['paginas'][] = $page;
    }

    return $groups;
}

function appRequirePage(string $pageKey): array
{
    $user = appCurrentUser(true);
    if (!$user) {
        header('Location: ' . appTabLoginUrl());
        exit;
    }

    if ((int) $user['mustChangePassword'] === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(403);
        exit('Altere sua senha no modal obrigatório antes de executar esta ação.');
    }

    if (!appCanAccess($pageKey, $user)) {
        http_response_code(403);
        exit('Acesso negado: seu perfil não permite acessar esta tela.');
    }

    return $user;
}

function appCsrfToken(): string
{
    appStartSession();
    if (empty($_SESSION['csrfToken'])) {
        $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrfToken'];
}

function appValidateCsrf(): void
{
    appStartSession();
    $submitted = $_POST['csrfToken'] ?? '';
    if (!is_string($submitted) || !hash_equals(appCsrfToken(), $submitted)) {
        http_response_code(400);
        exit('Solicitação inválida. Atualize a página e tente novamente.');
    }
}

function appEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function appPasswordPolicyError(string $password): ?string
{
    if (mb_strlen($password, 'UTF-8') < 8) {
        return 'A senha deve ter pelo menos 8 caracteres.';
    }
    if (preg_match('/\p{Lu}/u', $password) !== 1
        || preg_match('/\p{Ll}/u', $password) !== 1
        || preg_match('/\p{N}/u', $password) !== 1
        || preg_match('/[\p{P}\p{S}]/u', $password) !== 1) {
        return 'A senha deve conter letra maiúscula, letra minúscula, número e caractere especial.';
    }

    return null;
}

function appJavascriptVersion(): string
{
    $version = filemtime(__DIR__ . '/../assets/js/app.js');
    if ($version === false) {
        throw new RuntimeException('Não foi possível determinar a versão dos scripts da interface.');
    }

    return (string) $version;
}

appStartSession();
