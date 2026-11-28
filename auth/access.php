<?php

date_default_timezone_set('America/Sao_Paulo');

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
        'estoque' => 'Estoque',
    ];
    $moduleIcons = [
        'atendimento' => 'ph-stethoscope',
        'financeiro' => 'ph-currency-circle-dollar',
        'geral' => 'ph-gear',
        'gestao-acolhidos' => 'ph-users',
        'estoque' => 'ph-package',
    ];
    $pageNames = [
        // Atendimento
        'atendimento/dashboard-atendimento.php' => 'Dashboard de Atendimento',
        // Financeiro
        'financeiro/dashboard-financeiro.php' => 'Dashboard Financeiro',
        'financeiro/cadastros/categoria-lancamentos.php' => 'Categorias de Lançamentos',
        'financeiro/cadastros/fonte-recurso.php' => 'Fontes de Recursos',
        'financeiro/cadastros/forma-pagamento.php' => 'Formas de Pagamento',
        'financeiro/cadastros/fornecedores-prestadores.php' => 'Fornecedores/Prestadores',
        'financeiro/cadastros/doadores-contribuintes.php' => 'Doadores/Contribuintes',
        'financeiro/movimentacoes/lancamentos-financeiros.php' => 'Lançamentos Financeiros',
        'financeiro/movimentacoes/metas-orcamentarias.php' => 'Metas Orçamentárias',
        'financeiro/relatorios/categoria-lancamentos.php' => 'Categorias de Lançamentos',
        'financeiro/relatorios/fontes-recurso.php' => 'Fontes de Recursos',
        'financeiro/relatorios/formas-pagamento.php' => 'Formas de Pagamento',
        'financeiro/relatorios/fornecedores-prestadores.php' => 'Fornecedores/Prestadores',
        'financeiro/relatorios/doadores-contribuintes.php' => 'Doadores/Contribuintes',
        'financeiro/relatorios/extrato-lancamentos.php' => 'Extrato de Lançamentos Financeiros',
        'financeiro/relatorios/resumo-metas-orcamentarias.php' => 'Resumo de Metas Orçamentárias',
        // Geral
        'geral/dashboard-geral.php' => 'Dashboard Geral',
        'geral/cadastros/usuarios.php' => 'Usuários',
        'geral/cadastros/perfis-acesso.php' => 'Perfis de Acesso',
        'geral/cadastros/colaboradores.php' => 'Colaboradores e Voluntários',
        'geral/cadastros/cracha-colaborador.php' => 'Crachá do Colaborador',
        'geral/cadastros/documentos-institucionais.php' => 'Documentos e Certidões',
        'geral/movimentacoes/agenda-institucional.php' => 'Agenda Institucional',
        'geral/movimentacoes/escala-plantoes.php' => 'Escala de Plantões',
        'geral/movimentacoes/log-auditoria.php' => 'Log de Auditoria',
        'geral/movimentacoes/livro-plantao.php' => 'Livro de Plantão e Avisos',
        'geral/relatorios/usuarios-por-perfis.php' => 'Usuários por Perfis',
        'geral/relatorios/matriz-permissoes.php' => 'Matriz de Permissões por Perfil',
        'geral/relatorios/colaboradores.php' => 'Colaboradores e Voluntários',
        // Gestão de Acolhidos
        'gestao-acolhidos/dashboard-gestao-acolhidos.php' => 'Dashboard Gestão de Acolhidos',
        'gestao-acolhidos/cadastros/acolhidos.php' => 'Acolhidos',
        'gestao-acolhidos/cadastros/blocos.php' => 'Blocos',
        'gestao-acolhidos/cadastros/quartos.php' => 'Quartos',
        'gestao-acolhidos/cadastros/leitos.php' => 'Leitos',
        'gestao-acolhidos/movimentacoes/acolhimento.php' => 'Acolhimentos',
        'gestao-acolhidos/movimentacoes/acomodacoes-acolhidos.php' => 'Acomodações dos Acolhidos',
        'gestao-acolhidos/relatorios/acolhidos.php' => 'Acolhidos',
        'gestao-acolhidos/relatorios/fichas-medicas.php' => 'Fichas Médicas',
        'gestao-acolhidos/relatorios/acolhimentos.php' => 'Acolhimentos',
        'gestao-acolhidos/relatorios/blocos.php' => 'Blocos',
        'gestao-acolhidos/relatorios/quartos.php' => 'Quartos',
        'gestao-acolhidos/relatorios/leitos.php' => 'Leitos',
        'gestao-acolhidos/relatorios/acomodacoes.php' => 'Acomodações dos Acolhidos',
    ];
    $legacyPageKeys = [
        // Financeiro
        'financeiro/cadastros/categoria-lancamentos.php' => 'categoria-lancamentos',
        'financeiro/cadastros/fonte-recurso.php' => 'fonte-recurso',
        'financeiro/cadastros/forma-pagamento.php' => 'forma-pagamento',
        // Geral
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
            if (($moduleKey === 'gestao-acolhidos' && $relativePath === 'relatorios/fichas-medicas.php')
                || ($moduleKey === 'geral' && $relativePath === 'cadastros/cracha-colaborador.php')) {
                $catalog[$moduleKey]['paginas'][$pageKey]['mostrarNoMenu'] = false;
            }
        }

        ksort($catalog[$moduleKey]['paginas']);
    }

    ksort($catalog);

    return $catalog;
}

function appCurrentUser(): ?array
{
    appStartSession();
    $tabToken = appCurrentTabToken();
    if ($tabToken === null || empty($_SESSION['tabSessions'][$tabToken]['idUsuario'])) {
        return null;
    }

    $query = appPdo()->prepare(
        'SELECT u.idUsuario, u.nomeCompleto, u.login, u.ativo, u.bloqueado,
                u.forcarTrocaSenha, u.tempoExpiracaoSessao, u.tema, p.idPerfil, p.nomePerfil,
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
    $expiresAt = $_SESSION['tabSessions'][$tabToken]['expiresAt'] ?? null;
    if (!is_int($expiresAt) && !(is_string($expiresAt) && ctype_digit($expiresAt))) {
        $lastActivityAt = $_SESSION['tabSessions'][$tabToken]['lastActivityAt'] ?? null;
        if (!is_int($lastActivityAt) && !(is_string($lastActivityAt) && ctype_digit($lastActivityAt))) {
            unset($_SESSION['tabSessions'][$tabToken]);
            return null;
        }
        $expiresAt = (int) $lastActivityAt + (int) $user['tempoExpiracaoSessao'] * 60;
        $_SESSION['tabSessions'][$tabToken]['expiresAt'] = $expiresAt;
    }

    if ($now >= (int) $expiresAt) {
        unset($_SESSION['tabSessions'][$tabToken]);
        return null;
    }

    $user['permissoes'] = json_decode($user['permissoes'], true, 512, JSON_THROW_ON_ERROR);
    $user['sessionExpiresAt'] = (int) $expiresAt;
    $user['mustChangePassword'] = (int) $_SESSION['tabSessions'][$tabToken]['mustChangePassword'];

    return $user;
}

function appCanAccess(string $pageKey, ?array $user = null): bool
{
    $user = $user ?? appCurrentUser();
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
    $user = $user ?? appCurrentUser();

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
            if (($page['mostrarNoMenu'] ?? true) && appCanAccess("$moduleKey.$pageKey", $user)) {
                return $page['url'];
            }
        }
    }

    return null;
}

function appFirstAllowedModuleUrl(string $moduleKey, ?array $user = null): ?string
{
    $user = $user ?? appCurrentUser();
    $module = appPageCatalog()[$moduleKey] ?? null;
    if (!$user || !$module) {
        return null;
    }

    foreach ($module['paginas'] as $pageKey => $page) {
        if (($page['mostrarNoMenu'] ?? true)
            && str_starts_with(strtolower(pathinfo($page['arquivo'], PATHINFO_FILENAME)), 'dashboard')
            && appCanAccess("$moduleKey.$pageKey", $user)) {
            return $page['url'];
        }
    }

    foreach ($module['paginas'] as $pageKey => $page) {
        if (($page['mostrarNoMenu'] ?? true) && appCanAccess("$moduleKey.$pageKey", $user)) {
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
        if (($page['mostrarNoMenu'] ?? true) && appCanAccess("$moduleKey.$pageKey", $user)) {
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
    $user = appCurrentUser();
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

    $GLOBALS['__appCurrentPageKey'] = $pageKey;
    $GLOBALS['__appCurrentPageUser'] = $user;

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

    if (empty($GLOBALS['__appAutoAuditRegistered'])) {
        $GLOBALS['__appAutoAuditRegistered'] = true;
        register_shutdown_function('appRegistrarAuditoriaAutomatica');
    }
}

function appRegistrarAuditoriaAutomatica(): void
{
    try {
        if (!empty($GLOBALS['__appAuditoriaRegistrada'])
            || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }
        $lastError = error_get_last();
        if ($lastError !== null && in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        $statusCode = http_response_code();
        if (is_int($statusCode) && $statusCode >= 400) {
            return;
        }

        $pageKey = is_string($GLOBALS['__appCurrentPageKey'] ?? null) ? $GLOBALS['__appCurrentPageKey'] : '';
        if ($pageKey === '') {
            return;
        }

        $acaoPost = is_scalar($_POST['acao'] ?? null) ? trim((string) $_POST['acao']) : '';
        $reportAction = is_scalar($_POST['reportAction'] ?? null) ? trim((string) $_POST['reportAction']) : '';

        // Ignora consultas AJAX somente leitura e prévias/configurações de relatório
        if ($acaoPost !== '' && preg_match('/^(buscar|consultar|listar|detalhar|pesquisar|obter|carregar)/i', $acaoPost)) {
            return;
        }
        if ($reportAction !== '' && $reportAction !== 'download') {
            return;
        }

        $headers = headers_list();
        $locationHeader = null;
        $isJson = false;
        $isPdfOrDownload = false;
        foreach ($headers as $h) {
            if (stripos($h, 'Location:') === 0) {
                $locationHeader = trim(substr($h, 9));
            } elseif (stripos($h, 'Content-Type:') === 0 && stripos($h, 'application/json') !== false) {
                $isJson = true;
            } elseif ((stripos($h, 'Content-Type:') === 0 && stripos($h, 'application/pdf') !== false)
                || (stripos($h, 'Content-Disposition:') === 0 && stripos($h, 'attachment') !== false)) {
                $isPdfOrDownload = true;
            }
        }

        // Só registra mutações efetivamente concluídas (PRG redirect, download PDF ou mutação AJAX JSON 2xx)
        if ($locationHeader === null && !$isPdfOrDownload && !($isJson && $acaoPost !== '')) {
            return;
        }

        $parts = explode('.', $pageKey, 2);
        $modulo = $parts[0] !== '' ? $parts[0] : 'geral';
        $subKey = $parts[1] ?? '';
        $catalog = appPageCatalog();
        $pageLabel = $catalog[$modulo]['paginas'][$subKey]['nome'] ?? $pageKey;

        $resultadoRedirect = '';
        if ($locationHeader !== null) {
            $queryStr = parse_url($locationHeader, PHP_URL_QUERY);
            if (is_string($queryStr)) {
                parse_str($queryStr, $qParams);
                if (is_scalar($qParams['resultado'] ?? null)) {
                    $resultadoRedirect = strtolower(trim((string) $qParams['resultado']));
                }
            }
        }

        // Determina ação semântica padronizada
        if ($isPdfOrDownload || $reportAction === 'download') {
            $acao = 'exportar';
        } elseif (stripos($acaoPost, 'excluir') !== false || stripos($acaoPost, 'remover') !== false || stripos($acaoPost, 'deletar') !== false || str_contains($resultadoRedirect, 'exclu')) {
            $acao = 'excluir';
        } elseif (str_contains($resultadoRedirect, 'criad') || str_contains($resultadoRedirect, 'cadastrad') || str_contains($resultadoRedirect, 'alocad') || str_contains($resultadoRedirect, 'admitid') || stripos($acaoPost, 'criar') !== false || stripos($acaoPost, 'inserir') !== false) {
            $acao = 'criar';
        } elseif (str_contains($resultadoRedirect, 'atualizad') || str_contains($resultadoRedirect, 'editad') || str_contains($resultadoRedirect, 'transferid') || str_contains($resultadoRedirect, 'remanejad') || str_contains($resultadoRedirect, 'encerrad') || str_contains($resultadoRedirect, 'desligad') || str_contains($resultadoRedirect, 'alterad')) {
            $acao = 'editar';
        } else {
            // Fallback por presença de ID positivo no POST
            $temIdEdicao = false;
            foreach ($_POST as $k => $v) {
                if (is_string($k) && preg_match('/^id[A-Z]/', $k) && is_scalar($v) && ctype_digit((string) $v) && (int) $v > 0) {
                    $temIdEdicao = true;
                    break;
                }
            }
            $acao = $temIdEdicao ? 'editar' : 'criar';
        }

        // Extrai possível ID do registro manipulado
        $idRegistro = null;
        foreach ($_POST as $k => $v) {
            if (is_string($k) && ($k === 'id' || preg_match('/^id[A-Z]/', $k)) && is_scalar($v) && ctype_digit((string) $v) && (int) $v > 0) {
                $idRegistro = (int) $v;
                break;
            }
        }

        // Monta descrição legível
        $verbos = [
            'criar' => 'Registro criado em ',
            'editar' => 'Registro atualizado em ',
            'excluir' => 'Registro excluído em ',
            'exportar' => 'Relatório/documento exportado em ',
        ];
        $descricao = ($verbos[$acao] ?? 'Operação realizada em ') . $pageLabel;
        if ($resultadoRedirect !== '') {
            $descricao .= ' (' . str_replace('-', ' ', $resultadoRedirect) . ')';
        }
        $descricao .= '.';

        // Captura metadados seguros (nunca inclui senhas ou tokens)
        $detalhes = ['tela' => $pageKey];
        if ($acaoPost !== '') {
            $detalhes['acaoFormulario'] = $acaoPost;
        }
        if ($resultadoRedirect !== '') {
            $detalhes['resultado'] = $resultadoRedirect;
        }
        $camposSensiveis = ['csrfToken', 'tabToken', 'senha', 'password', 'senhaAtual', 'novaSenha', 'confirmarSenha'];
        $camposResumo = [];
        foreach ($_POST as $k => $v) {
            if (!is_string($k) || in_array($k, $camposSensiveis, true) || stripos($k, 'senha') !== false || stripos($k, 'token') !== false) {
                continue;
            }
            if (is_scalar($v)) {
                $val = trim((string) $v);
                if ($val !== '' && in_array($k, ['nome', 'nomeCompleto', 'nomeCategoria', 'nomeFonte', 'nomeFormaPagamento', 'nomeRazaoSocial', 'titulo', 'descricao', 'descricaoLancamento', 'valorLancamento', 'valorOrcado', 'statusLancamento', 'ano', 'mes', 'codigo'], true)) {
                    $camposResumo[$k] = mb_substr($val, 0, 80, 'UTF-8');
                }
            }
        }
        if ($camposResumo) {
            $detalhes['campos'] = $camposResumo;
        }

        $usuario = is_array($GLOBALS['__appCurrentPageUser'] ?? null) ? $GLOBALS['__appCurrentPageUser'] : null;
        appRegistrarAuditoria($modulo, $acao, $descricao, $pageLabel, $idRegistro, $detalhes, $usuario);
    } catch (Throwable $e) {
        error_log('Falha na auditoria automática global: ' . $e->getMessage());
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

function appAuditoriaLogDirectory(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
}

function appObterConfiguracaoAuditoria(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $defaults = [
        'gravarArquivoLog' => 1,
        'retencaoDias' => null,
        'ultimaLimpezaEm' => null,
    ];
    try {
        $row = appPdo()->query(
            'SELECT gravarArquivoLog, retencaoDias, ultimaLimpezaEm
             FROM tbConfiguracaoAuditoria
             WHERE idConfiguracao = 1
             LIMIT 1'
        )->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $cache = [
                'gravarArquivoLog' => (int) ($row['gravarArquivoLog'] ?? 1),
                'retencaoDias' => $row['retencaoDias'] !== null ? (int) $row['retencaoDias'] : null,
                'ultimaLimpezaEm' => $row['ultimaLimpezaEm'] !== null ? (string) $row['ultimaLimpezaEm'] : null,
            ];
            return $cache;
        }
    } catch (Throwable) {
    }
    return $defaults;
}

function appGravarLinhaArquivoLog(
    ?int $idUsuario,
    ?string $login,
    string $modulo,
    string $acao,
    ?string $entidade,
    ?string $idRegistro,
    string $descricao,
    ?string $detalhesJson,
    ?string $ip
): void {
    try {
        $dir = appAuditoriaLogDirectory();
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return;
        }
        $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\n");
        }
        $logFile = $dir . DIRECTORY_SEPARATOR . 'auditoria-' . date('Y-m') . '.log';
        $atorTxt = ($idUsuario !== null ? '#' . $idUsuario : '-') . ':' . ($login ?? 'anonimo');
        $entTxt = $entidade !== null ? ($entidade . ($idRegistro !== null ? '#' . $idRegistro : '')) : '-';
        $descLimpa = preg_replace('/[\r\n]+/', ' ', $descricao) ?? $descricao;
        $line = sprintf(
            "[%s] [modulo=%s] [acao=%s] [usuario=%s] [ip=%s] [entidade=%s] %s%s\n",
            date('Y-m-d H:i:s'),
            $modulo,
            $acao,
            $atorTxt,
            $ip ?? '-',
            $entTxt,
            $descLimpa,
            $detalhesJson !== null ? ' | detalhes=' . $detalhesJson : ''
        );
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    } catch (Throwable) {
    }
}

function appExecutarLimpezaRetencaoAuditoria(?int $diasForcados = null): int
{
    $cfg = appObterConfiguracaoAuditoria();
    $dias = $diasForcados ?? $cfg['retencaoDias'];
    if ($dias === null || $dias < 1) {
        return 0;
    }
    if ($diasForcados === null && $cfg['ultimaLimpezaEm'] !== null) {
        $ts = strtotime($cfg['ultimaLimpezaEm']);
        if ($ts !== false && (time() - $ts) < 21600) {
            return 0;
        }
    }
    $pdo = appPdo();
    $del = $pdo->prepare('DELETE FROM tbLogAuditoria WHERE dataHora < DATE_SUB(NOW(), INTERVAL :dias DAY)');
    $del->bindValue(':dias', $dias, PDO::PARAM_INT);
    $del->execute();
    $removidos = $del->rowCount();
    $pdo->exec('UPDATE tbConfiguracaoAuditoria SET ultimaLimpezaEm = NOW() WHERE idConfiguracao = 1');
    return $removidos;
}

function appRegistrarAuditoria(
    string $modulo,
    string $acao,
    string $descricao,
    ?string $entidade = null,
    int|string|null $idRegistro = null,
    ?array $detalhes = null,
    ?array $usuario = null,
    ?string $loginInformado = null
): void {
    try {
        $GLOBALS['__appAuditoriaRegistrada'] = true;
        $ator = $usuario;
        if ($ator === null && $loginInformado === null) {
            $ator = appCurrentUser();
        }
        $idUsuario = isset($ator['idUsuario']) ? (int) $ator['idUsuario'] : null;
        $login = $loginInformado ?? (isset($ator['login']) ? (string) $ator['login'] : null);
        if ($login !== null) {
            $login = mb_substr(trim($login), 0, 80, 'UTF-8');
            if ($login === '') {
                $login = null;
            }
        }

        $modulo = mb_substr(trim($modulo), 0, 50, 'UTF-8');
        $acao = mb_substr(trim($acao), 0, 50, 'UTF-8');
        $entidade = $entidade !== null ? mb_substr(trim($entidade), 0, 80, 'UTF-8') : null;
        $idRegistroTexto = $idRegistro !== null ? mb_substr(trim((string) $idRegistro), 0, 64, 'UTF-8') : null;
        $descricao = mb_substr(trim($descricao), 0, 255, 'UTF-8');
        $detalhesJson = $detalhes !== null && $detalhes !== []
            ? json_encode($detalhes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
            : null;
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $ip = $ip !== '' ? mb_substr($ip, 0, 45, 'UTF-8') : null;
        $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $userAgent = $userAgent !== '' ? mb_substr($userAgent, 0, 255, 'UTF-8') : null;

        $statement = appPdo()->prepare(
            'INSERT INTO tbLogAuditoria
                (idUsuario, loginInformado, modulo, acao, entidade, idRegistro,
                 descricao, detalhesJson, enderecoIp, userAgent)
             VALUES
                (:idUsuario, :loginInformado, :modulo, :acao, :entidade, :idRegistro,
                 :descricao, :detalhesJson, :enderecoIp, :userAgent)'
        );
        $statement->execute([
            'idUsuario' => $idUsuario,
            'loginInformado' => $login,
            'modulo' => $modulo,
            'acao' => $acao,
            'entidade' => $entidade,
            'idRegistro' => $idRegistroTexto,
            'descricao' => $descricao,
            'detalhesJson' => $detalhesJson,
            'enderecoIp' => $ip,
            'userAgent' => $userAgent,
        ]);

        $cfgAuditoria = appObterConfiguracaoAuditoria();
        if ((int) ($cfgAuditoria['gravarArquivoLog'] ?? 1) === 1) {
            appGravarLinhaArquivoLog($idUsuario, $login, $modulo, $acao, $entidade, $idRegistroTexto, $descricao, $detalhesJson, $ip);
        }
        if ($cfgAuditoria['retencaoDias'] !== null && (int) $cfgAuditoria['retencaoDias'] > 0) {
            appExecutarLimpezaRetencaoAuditoria();
        }
    } catch (Throwable $exception) {
        error_log('Falha ao registrar log de auditoria: ' . $exception->getMessage());
    }
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
