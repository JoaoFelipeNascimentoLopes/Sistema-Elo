<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('geral.movimentacoes/log-auditoria');
$pdo = appPdo();
$baseUrl = appBaseUrl();
$urlAtual = $baseUrl . '/pages/geral/movimentacoes/log-auditoria.php';

$erroAcao = '';
$sucessoAcao = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    $acaoPost = is_scalar($_POST['acao'] ?? null) ? (string) $_POST['acao'] : '';
    if ($acaoPost === 'buscarUsuarios') {
        header('Content-Type: application/json; charset=UTF-8');
        $busca = is_scalar($_POST['busca'] ?? null) ? trim((string) $_POST['busca']) : '';
        if (mb_strlen($busca, 'UTF-8') > 100) {
            http_response_code(400);
            echo json_encode(['message' => 'A pesquisa deve ter até 100 caracteres.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $buscarPorId = ctype_digit($busca);
            $termo = '%' . $busca . '%';
            if ($busca === '') {
                $statement = $pdo->query(
                    'SELECT idUsuario, nomeCompleto, login, ativo, bloqueado
                     FROM tbUsuario
                     ORDER BY nomeCompleto
                     LIMIT 50'
                );
            } elseif ($buscarPorId) {
                $statement = $pdo->prepare(
                    'SELECT idUsuario, nomeCompleto, login, ativo, bloqueado
                     FROM tbUsuario
                     WHERE idUsuario LIKE ? OR nomeCompleto LIKE ? OR login LIKE ?
                     ORDER BY idUsuario LIKE ? DESC, nomeCompleto
                     LIMIT 50'
                );
                $statement->execute([$busca . '%', $termo, $termo, $busca . '%']);
            } else {
                $statement = $pdo->prepare(
                    'SELECT idUsuario, nomeCompleto, login, ativo, bloqueado
                     FROM tbUsuario
                     WHERE nomeCompleto LIKE ? OR login LIKE ?
                     ORDER BY nomeCompleto
                     LIMIT 50'
                );
                $statement->execute([$termo, $termo]);
            }

            $items = array_map(static function (array $row): array {
                $statusSufixo = '';
                if ((int) $row['bloqueado'] === 1) {
                    $statusSufixo = ' (Bloqueado)';
                } elseif ((int) $row['ativo'] !== 1) {
                    $statusSufixo = ' (Inativo)';
                }
                return [
                    'id' => (int) $row['idUsuario'],
                    'label' => (int) $row['idUsuario'] . ' — ' . $row['nomeCompleto'] . ' (' . $row['login'] . ')' . $statusSufixo,
                ];
            }, $statement->fetchAll(PDO::FETCH_ASSOC));

            echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (PDOException $exception) {
            error_log('Falha ao pesquisar usuários no log de auditoria: ' . $exception->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Não foi possível pesquisar os usuários agora.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    if ($acaoPost === 'salvarConfigLog') {
        $gravarArquivo = isset($_POST['gravarArquivoLog']) ? 1 : 0;
        $retencaoModo = is_scalar($_POST['retencaoModo'] ?? null) ? trim((string) $_POST['retencaoModo']) : 'nunca';
        $retencaoDias = null;
        if ($retencaoModo === 'personalizado') {
            $diasCustom = filter_var($_POST['retencaoPersonalizada'] ?? null, FILTER_VALIDATE_INT);
            if ($diasCustom === false || $diasCustom < 7 || $diasCustom > 3650) {
                $erroAcao = 'Informe um prazo personalizado entre 7 e 3650 dias.';
            } else {
                $retencaoDias = (int) $diasCustom;
            }
        } elseif (in_array($retencaoModo, ['30', '60', '90', '180', '365'], true)) {
            $retencaoDias = (int) $retencaoModo;
        } elseif ($retencaoModo !== 'nunca') {
            $erroAcao = 'Selecione uma opção de retenção válida.';
        }

        if ($erroAcao === '') {
            $upd = $pdo->prepare(
                'INSERT INTO tbConfiguracaoAuditoria (idConfiguracao, gravarArquivoLog, retencaoDias, idUsuarioAlteracao)
                 VALUES (1, :gravar, :dias, :uid)
                 ON DUPLICATE KEY UPDATE gravarArquivoLog = VALUES(gravarArquivoLog), retencaoDias = VALUES(retencaoDias), idUsuarioAlteracao = VALUES(idUsuarioAlteracao)'
            );
            $upd->execute([
                'gravar' => $gravarArquivo,
                'dias' => $retencaoDias,
                'uid' => (int) $usuarioAtual['idUsuario'],
            ]);

            $removidosAgora = 0;
            if (isset($_POST['limparAgora']) && $retencaoDias !== null && $retencaoDias > 0) {
                $removidosAgora = appExecutarLimpezaRetencaoAuditoria($retencaoDias);
            }

            appRegistrarAuditoria(
                'geral',
                'editar',
                'Política de auditoria atualizada (arquivo .log: ' . ($gravarArquivo ? 'ativado' : 'desativado')
                . ', retenção: ' . ($retencaoDias !== null ? $retencaoDias . ' dias' : 'nunca apagar') . ').',
                'tbConfiguracaoAuditoria',
                1,
                ['gravarArquivoLog' => (bool) $gravarArquivo, 'retencaoDias' => $retencaoDias, 'removidosAgora' => $removidosAgora],
                $usuarioAtual
            );

            $qs = 'resultado=config-salva' . ($removidosAgora > 0 ? '&removidos=' . $removidosAgora : '');
            header('Location: ' . appTabUrl($urlAtual . '?' . $qs));
            exit;
        }
    } elseif ($acaoPost === 'executarLimpeza') {
        $diasLimpeza = filter_var($_POST['diasLimpeza'] ?? null, FILTER_VALIDATE_INT);
        if ($diasLimpeza === false || $diasLimpeza < 1 || $diasLimpeza > 3650) {
            $erroAcao = 'Informe uma quantidade válida de dias (1 a 3650) para limpar logs antigos.';
        } else {
            $removidos = appExecutarLimpezaRetencaoAuditoria((int) $diasLimpeza);
            appRegistrarAuditoria(
                'geral',
                'excluir',
                'Limpeza manual de logs com mais de ' . $diasLimpeza . ' dia(s): ' . $removidos . ' registro(s) removido(s).',
                'tbLogAuditoria',
                null,
                ['dias' => (int) $diasLimpeza, 'removidos' => $removidos],
                $usuarioAtual
            );
            header('Location: ' . appTabUrl($urlAtual . '?resultado=limpeza-ok&removidos=' . $removidos));
            exit;
        }
    } elseif ($acaoPost === 'apagarSelecionados') {
        $idsRaw = $_POST['ids'] ?? [];
        $ids = [];
        if (is_array($idsRaw)) {
            foreach ($idsRaw as $v) {
                $idInt = filter_var($v, FILTER_VALIDATE_INT);
                if ($idInt !== false && $idInt > 0) {
                    $ids[(int) $idInt] = (int) $idInt;
                }
            }
        }
        $ids = array_values($ids);
        if (!$ids) {
            $erroAcao = 'Selecione ao menos um item de log para apagar.';
        } else {
            $ph = implode(', ', array_fill(0, count($ids), '?'));
            $del = $pdo->prepare("DELETE FROM tbLogAuditoria WHERE idLog IN ($ph)");
            $del->execute($ids);
            $removidos = $del->rowCount();

            appRegistrarAuditoria(
                'geral',
                'excluir',
                'Exclusão seletiva de auditoria: ' . $removidos . ' registro(s) apagado(s).',
                'tbLogAuditoria',
                null,
                ['quantidadeRemovida' => $removidos, 'idsAmostra' => array_slice($ids, 0, 20)],
                $usuarioAtual
            );
            header('Location: ' . appTabUrl($urlAtual . '?resultado=selecionados-apagados&removidos=' . $removidos));
            exit;
        }
    } elseif ($acaoPost === 'baixarArquivoLog') {
        $nomeArquivo = is_scalar($_POST['arquivo'] ?? null) ? trim((string) $_POST['arquivo']) : '';
        if (!preg_match('/^auditoria-\d{4}-\d{2}\.log$/', $nomeArquivo)) {
            http_response_code(400);
            exit('Arquivo de log inválido.');
        }
        $logDir = realpath(appAuditoriaLogDirectory());
        $fullPath = realpath(appAuditoriaLogDirectory() . DIRECTORY_SEPARATOR . $nomeArquivo);
        if ($logDir === false || $fullPath === false || dirname($fullPath) !== $logDir || !is_file($fullPath)) {
            http_response_code(404);
            exit('Arquivo de log não encontrado.');
        }
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Length: ' . (string) filesize($fullPath));
        header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
        header('Cache-Control: private, no-store, max-age=0');
        readfile($fullPath);
        exit;
    } else {
        http_response_code(400);
        exit('Ação inválida.');
    }
}

$configAuditoria = $pdo->query(
    'SELECT gravarArquivoLog, retencaoDias, ultimaLimpezaEm
     FROM tbConfiguracaoAuditoria
     WHERE idConfiguracao = 1
     LIMIT 1'
)->fetch(PDO::FETCH_ASSOC) ?: ['gravarArquivoLog' => 1, 'retencaoDias' => null, 'ultimaLimpezaEm' => null];
$gravarArquivoLogAtivo = (int) ($configAuditoria['gravarArquivoLog'] ?? 1) === 1;
$retencaoDiasAtual = $configAuditoria['retencaoDias'] !== null ? (int) $configAuditoria['retencaoDias'] : null;
$ultimaLimpezaAtual = $configAuditoria['ultimaLimpezaEm'] !== null ? (string) $configAuditoria['ultimaLimpezaEm'] : null;

$arquivosLogDisco = [];
$logDirPath = appAuditoriaLogDirectory();
if (is_dir($logDirPath)) {
    foreach (glob($logDirPath . DIRECTORY_SEPARATOR . 'auditoria-*.log') ?: [] as $f) {
        $base = basename($f);
        if (preg_match('/^auditoria-\d{4}-\d{2}\.log$/', $base) && is_file($f)) {
            $arquivosLogDisco[] = [
                'nome' => $base,
                'tamanho' => (int) filesize($f),
                'modificadoEm' => date('d/m/Y H:i', (int) filemtime($f)),
            ];
        }
    }
    usort($arquivosLogDisco, static fn(array $a, array $b): int => strcmp($b['nome'], $a['nome']));
}

$removidosGet = filter_input(INPUT_GET, 'removidos', FILTER_VALIDATE_INT) ?: 0;
$resultadoKey = is_scalar($_GET['resultado'] ?? null) ? (string) $_GET['resultado'] : '';
if ($resultadoKey === 'config-salva') {
    $sucessoAcao = 'Configurações de auditoria salvas com sucesso.' . ($removidosGet > 0 ? ' ' . $removidosGet . ' registro(s) antigo(s) removido(s).' : '');
} elseif ($resultadoKey === 'limpeza-ok') {
    $sucessoAcao = 'Limpeza executada: ' . $removidosGet . ' registro(s) antigo(s) removido(s).';
} elseif ($resultadoKey === 'selecionados-apagados') {
    $sucessoAcao = $removidosGet . ' item(ns) de log apagado(s) com sucesso.';
}

$modulos = [
    'auth' => 'Autenticação',
    'geral' => 'Geral',
    'instituicao' => 'Instituição',
    'financeiro' => 'Financeiro',
    'gestao-acolhidos' => 'Gestão de Acolhidos',
    'atendimento' => 'Atendimento',
    'estoque' => 'Estoque',
];

$acoes = [
    'login_sucesso' => ['label' => 'Login com sucesso', 'classe' => 'bg-emerald-100 text-emerald-800 border-emerald-200'],
    'login_falha' => ['label' => 'Falha de login', 'classe' => 'bg-red-100 text-red-800 border-red-200'],
    'login_bloqueado' => ['label' => 'Conta bloqueada', 'classe' => 'bg-amber-100 text-amber-800 border-amber-200'],
    'logout' => ['label' => 'Logout', 'classe' => 'bg-gray-100 text-gray-700 border-gray-200'],
    'troca_senha' => ['label' => 'Troca de senha', 'classe' => 'bg-sky-100 text-sky-800 border-sky-200'],
    'troca_senha_falha' => ['label' => 'Falha na troca de senha', 'classe' => 'bg-red-100 text-red-800 border-red-200'],
    'criar' => ['label' => 'Criação', 'classe' => 'bg-emerald-100 text-emerald-800 border-emerald-200'],
    'editar' => ['label' => 'Edição', 'classe' => 'bg-blue-100 text-blue-800 border-blue-200'],
    'excluir' => ['label' => 'Exclusão', 'classe' => 'bg-rose-100 text-rose-800 border-rose-200'],
    'exportar' => ['label' => 'Exportação', 'classe' => 'bg-indigo-100 text-indigo-800 border-indigo-200'],
];

$validarData = static function (string $data): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
    return $date !== false && $date->format('Y-m-d') === $data;
};

$filtroDataInicio = is_scalar($_GET['dataInicio'] ?? null) ? trim((string) $_GET['dataInicio']) : '';
$filtroDataFim = is_scalar($_GET['dataFim'] ?? null) ? trim((string) $_GET['dataFim']) : '';
$filtroUsuarioInput = $_GET['idUsuario'] ?? '';
$filtroUsuario = is_scalar($filtroUsuarioInput) && $filtroUsuarioInput !== ''
    ? filter_var($filtroUsuarioInput, FILTER_VALIDATE_INT)
    : false;
$filtroModulo = is_scalar($_GET['modulo'] ?? null) ? trim((string) $_GET['modulo']) : '';
$filtroAcao = is_scalar($_GET['acao'] ?? null) ? trim((string) $_GET['acao']) : '';
$filtroBusca = is_scalar($_GET['busca'] ?? null) ? trim((string) $_GET['busca']) : '';
$paginaInput = $_GET['pagina'] ?? 1;
$paginaAtual = is_scalar($paginaInput) ? filter_var($paginaInput, FILTER_VALIDATE_INT) : 1;
$paginaAtual = ($paginaAtual !== false && $paginaAtual >= 1) ? (int) $paginaAtual : 1;
$opcoesPorPagina = [10, 25, 50, 100];
$porPaginaInput = $_GET['porPagina'] ?? 25;
$porPaginaParsed = is_scalar($porPaginaInput) ? filter_var($porPaginaInput, FILTER_VALIDATE_INT) : 25;
$porPagina = ($porPaginaParsed !== false && in_array((int) $porPaginaParsed, $opcoesPorPagina, true))
    ? (int) $porPaginaParsed
    : 25;

if ($filtroDataInicio !== '' && !$validarData($filtroDataInicio)) {
    $filtroDataInicio = '';
}
if ($filtroDataFim !== '' && !$validarData($filtroDataFim)) {
    $filtroDataFim = '';
}
if ($filtroModulo !== '' && !isset($modulos[$filtroModulo])) {
    $filtroModulo = '';
}
if ($filtroAcao !== '' && !isset($acoes[$filtroAcao])) {
    $filtroAcao = '';
}
if (mb_strlen($filtroBusca, 'UTF-8') > 120) {
    $filtroBusca = mb_substr($filtroBusca, 0, 120, 'UTF-8');
}

$usuarioSelecionadoLabel = '';
if ($filtroUsuario !== false && $filtroUsuario > 0) {
    $selectedUserStmt = $pdo->prepare(
        'SELECT idUsuario, nomeCompleto, login, ativo, bloqueado
         FROM tbUsuario
         WHERE idUsuario = :idUsuario
         LIMIT 1'
    );
    $selectedUserStmt->execute(['idUsuario' => (int) $filtroUsuario]);
    if ($selectedUserRow = $selectedUserStmt->fetch(PDO::FETCH_ASSOC)) {
        $statusSufixo = '';
        if ((int) $selectedUserRow['bloqueado'] === 1) {
            $statusSufixo = ' (Bloqueado)';
        } elseif ((int) $selectedUserRow['ativo'] !== 1) {
            $statusSufixo = ' (Inativo)';
        }
        $usuarioSelecionadoLabel = (int) $selectedUserRow['idUsuario'] . ' — '
            . $selectedUserRow['nomeCompleto'] . ' (' . $selectedUserRow['login'] . ')' . $statusSufixo;
    } else {
        $filtroUsuario = false;
    }
}

$resumoQuery = $pdo->query(
    "SELECT
        SUM(CASE WHEN dataHora >= NOW() - INTERVAL 24 HOUR THEN 1 ELSE 0 END) AS eventos24h,
        SUM(CASE WHEN dataHora >= NOW() - INTERVAL 30 DAY AND acao = 'login_sucesso' THEN 1 ELSE 0 END) AS logins30d,
        SUM(CASE WHEN dataHora >= NOW() - INTERVAL 30 DAY AND acao IN ('login_falha', 'login_bloqueado', 'troca_senha_falha') THEN 1 ELSE 0 END) AS alertas30d,
        SUM(CASE WHEN dataHora >= NOW() - INTERVAL 30 DAY AND acao IN ('criar', 'editar', 'excluir') THEN 1 ELSE 0 END) AS alteracoes30d
     FROM tbLogAuditoria"
);
$resumo = $resumoQuery->fetch() ?: [
    'eventos24h' => 0,
    'logins30d' => 0,
    'alertas30d' => 0,
    'alteracoes30d' => 0,
];

$where = [];
$params = [];
if ($filtroDataInicio !== '') {
    $where[] = 'l.dataHora >= :dataInicio';
    $params['dataInicio'] = $filtroDataInicio . ' 00:00:00';
}
if ($filtroDataFim !== '') {
    $where[] = 'l.dataHora <= :dataFim';
    $params['dataFim'] = $filtroDataFim . ' 23:59:59';
}
if ($filtroUsuario !== false && $filtroUsuario > 0) {
    $where[] = 'l.idUsuario = :idUsuario';
    $params['idUsuario'] = (int) $filtroUsuario;
}
if ($filtroModulo !== '') {
    $where[] = 'l.modulo = :modulo';
    $params['modulo'] = $filtroModulo;
}
if ($filtroAcao !== '') {
    $where[] = 'l.acao = :acao';
    $params['acao'] = $filtroAcao;
}
if ($filtroBusca !== '') {
    $where[] = '(l.descricao LIKE :busca1
        OR l.loginInformado LIKE :busca2
        OR l.entidade LIKE :busca3
        OR l.idRegistro LIKE :busca4
        OR l.enderecoIp LIKE :busca5
        OR u.nomeCompleto LIKE :busca6)';
    $termo = '%' . $filtroBusca . '%';
    for ($i = 1; $i <= 6; $i++) {
        $params['busca' . $i] = $termo;
    }
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStatement = $pdo->prepare(
    "SELECT COUNT(*)
     FROM tbLogAuditoria l
     LEFT JOIN tbUsuario u ON u.idUsuario = l.idUsuario
     $whereSql"
);
$countStatement->execute($params);
$totalRegistros = (int) $countStatement->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalRegistros / $porPagina));
if ($paginaAtual > $totalPaginas) {
    $paginaAtual = $totalPaginas;
}
$offset = ($paginaAtual - 1) * $porPagina;

$listStatement = $pdo->prepare(
    "SELECT l.idLog, l.dataHora, l.idUsuario, l.loginInformado, l.modulo, l.acao,
            l.entidade, l.idRegistro, l.descricao, l.detalhesJson, l.enderecoIp, l.userAgent,
            u.nomeCompleto AS nomeUsuario
     FROM tbLogAuditoria l
     LEFT JOIN tbUsuario u ON u.idUsuario = l.idUsuario
     $whereSql
     ORDER BY l.dataHora DESC, l.idLog DESC
     LIMIT :limite OFFSET :offset"
);
foreach ($params as $key => $value) {
    $listStatement->bindValue(':' . $key, $value);
}
$listStatement->bindValue(':limite', $porPagina, PDO::PARAM_INT);
$listStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStatement->execute();
$logs = $listStatement->fetchAll();

$temFiltros = $filtroDataInicio !== '' || $filtroDataFim !== '' || ($filtroUsuario !== false && $filtroUsuario > 0)
    || $filtroModulo !== '' || $filtroAcao !== '' || $filtroBusca !== '';

$montarUrlPagina = static function (int $pagina, ?int $limite = null, string $modo = '') use (
    $urlAtual,
    $filtroDataInicio,
    $filtroDataFim,
    $filtroUsuario,
    $filtroModulo,
    $filtroAcao,
    $filtroBusca,
    $porPagina
): string {
    $limiteFinal = $limite ?? $porPagina;
    $query = array_filter([
        'dataInicio' => $filtroDataInicio,
        'dataFim' => $filtroDataFim,
        'idUsuario' => $filtroUsuario !== false && $filtroUsuario > 0 ? (string) $filtroUsuario : '',
        'modulo' => $filtroModulo,
        'acao' => $filtroAcao,
        'busca' => $filtroBusca,
        'porPagina' => $limiteFinal !== 25 ? (string) $limiteFinal : '',
        'pagina' => $pagina > 1 ? (string) $pagina : '',
        'modo' => $modo,
    ], static fn(string $value): bool => $value !== '');

    $url = $urlAtual . ($query ? '?' . http_build_query($query) : '');
    return appTabUrl($url);
};

$modoImpressao = is_scalar($_GET['modo'] ?? null) ? trim((string) $_GET['modo']) : '';
if ($modoImpressao === 'log') {
    $exportStmt = $pdo->prepare(
        "SELECT l.idLog, l.dataHora, l.idUsuario, l.loginInformado, l.modulo, l.acao,
                l.entidade, l.idRegistro, l.descricao, l.detalhesJson, l.enderecoIp
         FROM tbLogAuditoria l
         $whereSql
         ORDER BY l.dataHora DESC, l.idLog DESC
         LIMIT 5000"
    );
    foreach ($params as $key => $value) {
        $exportStmt->bindValue(':' . $key, $value);
    }
    $exportStmt->execute();
    $linhas = [];
    foreach ($exportStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $atorTxt = ($row['idUsuario'] !== null ? '#' . $row['idUsuario'] : '-') . ':' . ($row['loginInformado'] ?? 'anonimo');
        $entTxt = $row['entidade'] !== null ? ($row['entidade'] . ($row['idRegistro'] !== null ? '#' . $row['idRegistro'] : '')) : '-';
        $descLimpa = preg_replace('/[\r\n]+/', ' ', (string) $row['descricao']) ?? (string) $row['descricao'];
        $linhas[] = sprintf(
            '[%s] [id=%d] [modulo=%s] [acao=%s] [usuario=%s] [ip=%s] [entidade=%s] %s%s',
            (string) $row['dataHora'],
            (int) $row['idLog'],
            (string) $row['modulo'],
            (string) $row['acao'],
            $atorTxt,
            (string) ($row['enderecoIp'] ?? '-'),
            $entTxt,
            $descLimpa,
            !empty($row['detalhesJson']) ? ' | detalhes=' . (string) $row['detalhesJson'] : ''
        );
    }
    $conteudo = implode("\n", $linhas) . ($linhas ? "\n" : "# Nenhum registro encontrado para os filtros informados.\n");
    $nomeDownload = 'log-auditoria-' . date('Ymd-Hi') . '.log';
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Length: ' . (string) strlen($conteudo));
    header('Content-Disposition: attachment; filename="' . $nomeDownload . '"');
    header('Cache-Control: private, no-store, max-age=0');
    echo $conteudo;
    exit;
}

if (in_array($modoImpressao, ['imprimir', 'pdf'], true)) {
    $limiteImpressao = 2000;
    $printStatement = $pdo->prepare(
        "SELECT l.idLog, l.dataHora, l.idUsuario, l.loginInformado, l.modulo, l.acao,
                l.entidade, l.idRegistro, l.descricao, l.enderecoIp,
                u.nomeCompleto AS nomeUsuario
         FROM tbLogAuditoria l
         LEFT JOIN tbUsuario u ON u.idUsuario = l.idUsuario
         $whereSql
         ORDER BY l.dataHora DESC, l.idLog DESC
         LIMIT :limite"
    );
    foreach ($params as $key => $value) {
        $printStatement->bindValue(':' . $key, $value);
    }
    $printStatement->bindValue(':limite', $limiteImpressao, PDO::PARAM_INT);
    $printStatement->execute();
    $logsImpressao = $printStatement->fetchAll();

    $filtrosResumo = [];
    if ($filtroDataInicio !== '' || $filtroDataFim !== '') {
        $inicioFmt = $filtroDataInicio !== '' ? date('d/m/Y', strtotime($filtroDataInicio)) : 'Início';
        $fimFmt = $filtroDataFim !== '' ? date('d/m/Y', strtotime($filtroDataFim)) : 'Hoje';
        $filtrosResumo[] = 'Período: ' . $inicioFmt . ' a ' . $fimFmt;
    }
    if ($usuarioSelecionadoLabel !== '') {
        $filtrosResumo[] = 'Usuário: ' . $usuarioSelecionadoLabel;
    }
    if ($filtroModulo !== '') {
        $filtrosResumo[] = 'Módulo: ' . ($modulos[$filtroModulo] ?? $filtroModulo);
    }
    if ($filtroAcao !== '') {
        $filtrosResumo[] = 'Ação: ' . ($acoes[$filtroAcao]['label'] ?? $filtroAcao);
    }
    if ($filtroBusca !== '') {
        $filtrosResumo[] = 'Pesquisa: "' . $filtroBusca . '"';
    }
    $textoFiltros = $filtrosResumo ? implode(' · ', $filtrosResumo) : 'Sem filtros (todos os eventos)';
    $truncado = $totalRegistros > $limiteImpressao;

    $renderTabelaImpressao = static function () use ($logsImpressao, $modulos, $acoes): void {
        if (!$logsImpressao) {
            echo '<p class="report-empty">Nenhum evento de auditoria corresponde aos filtros informados.</p>';
            return;
        }
        echo '<table class="report-table"><thead><tr>'
            . '<th style="width:13%">Data / Hora</th>'
            . '<th style="width:19%">Usuário</th>'
            . '<th style="width:11%">Módulo</th>'
            . '<th style="width:14%">Ação</th>'
            . '<th>Descrição / Entidade</th>'
            . '<th style="width:11%">IP</th>'
            . '</tr></thead><tbody>';
        foreach ($logsImpressao as $index => $log) {
            $zebraClass = $index % 2 === 1 ? ' report-zebra-row' : '';
            $dataFmt = date('d/m/Y H:i:s', strtotime((string) $log['dataHora']));
            $usuarioTexto = $log['nomeUsuario'] !== null
                ? $log['nomeUsuario'] . ($log['loginInformado'] !== null ? ' (' . $log['loginInformado'] . ')' : '')
                : ($log['loginInformado'] !== null ? (string) $log['loginInformado'] : '—');
            $moduloLabel = $modulos[$log['modulo']] ?? (string) $log['modulo'];
            $acaoLabel = $acoes[$log['acao']]['label'] ?? (string) $log['acao'];
            $descricaoTexto = (string) $log['descricao'];
            if ($log['entidade'] !== null) {
                $descricaoTexto .= ' [' . $log['entidade'] . ($log['idRegistro'] !== null ? ' #' . $log['idRegistro'] : '') . ']';
            }
            $ipTexto = $log['enderecoIp'] !== null ? (string) $log['enderecoIp'] : '—';

            echo '<tr class="report-data-row' . $zebraClass . '">'
                . '<td>' . appEscape($dataFmt) . '</td>'
                . '<td>' . appEscape($usuarioTexto) . '</td>'
                . '<td>' . appEscape($moduloLabel) . '</td>'
                . '<td>' . appEscape($acaoLabel) . '</td>'
                . '<td>' . appEscape($descricaoTexto) . '</td>'
                . '<td>' . appEscape($ipTexto) . '</td>'
                . '</tr>';
        }
        echo '</tbody></table>';
    };

    $subtituloImpressao = 'Gerado em ' . date('d/m/Y H:i') . ' · ' . count($logsImpressao) . ' evento(s)'
        . ($truncado ? ' (limitado aos primeiros ' . $limiteImpressao . ' de ' . $totalRegistros . ')' : '');

    if ($modoImpressao === 'pdf') {
        require_once __DIR__ . '/../../../vendor/autoload.php';
        $pdfOptions = new \Dompdf\Options();
        $pdfOptions->set('defaultFont', 'DejaVu Sans');
        $pdfOptions->set('isRemoteEnabled', false);
        $dompdf = new \Dompdf\Dompdf($pdfOptions);

        ob_start();
        $renderTabelaImpressao();
        $tabelaHtml = (string) ob_get_clean();

        $pdfHtml = '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><style>'
            . '@page{size:297mm 210mm;margin:10mm 10mm 12mm 10mm;}'
            . 'body{font-family:"DejaVu Sans",sans-serif;font-size:8pt;color:#111;margin:0;}'
            . '.report-heading{margin-bottom:4mm;padding-bottom:3mm;border-bottom:2px solid #1f2937;}'
            . '.report-heading:before{display:block;margin-bottom:1.5mm;color:#4b5563;content:"SISTEMA ELO · AUDITORIA";font-size:7pt;font-weight:bold;letter-spacing:1.4pt;}'
            . '.report-heading h1{margin:0 0 1mm;font-size:14pt;font-weight:bold;letter-spacing:.4pt;text-transform:uppercase;}'
            . '.report-heading p{margin:0;color:#374151;font-size:8pt;}'
            . '.audit-filters{margin:0 0 4mm;padding:2mm 2.5mm;border:1px solid #d1d5db;background:#f8fafc;color:#374151;font-size:7.5pt;}'
            . '.report-table{width:100%;table-layout:fixed;border-collapse:collapse;text-align:left;font-size:7.5pt;}'
            . '.report-table th,.report-table td{border:1px solid #9ca3af;padding:1.3mm;vertical-align:top;overflow-wrap:anywhere;}'
            . '.report-table thead th{background:#1f2937;color:#fff;font-weight:bold;}'
            . '.report-data-row.report-zebra-row td{background:#f2f2f2;}'
            . '.report-empty{padding:5mm;color:#6b7280;text-align:center;}'
            . '</style></head><body>'
            . '<header class="report-heading"><h1>Log de Auditoria</h1><p>' . appEscape($subtituloImpressao) . '</p></header>'
            . '<p class="audit-filters"><strong>Filtros aplicados:</strong> ' . appEscape($textoFiltros) . '</p>'
            . $tabelaHtml
            . '</body></html>';

        $dompdf->loadHtml($pdfHtml, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream('log-auditoria-' . date('Ymd-Hi') . '.pdf', ['Attachment' => true]);
        exit;
    }

    ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Elo - Log de Auditoria</title>
    <link rel="icon" type="image/png" href="<?php echo appEscape($baseUrl . '/assets/imgs/logo-elo.png'); ?>">
    <link rel="stylesheet" href="<?php echo appEscape($baseUrl . '/assets/css/usuarios-por-perfis.css?v=' . filemtime(__DIR__ . '/../../../assets/css/usuarios-por-perfis.css')); ?>">
    <style>
        @page { size: 297mm 210mm; margin: 10mm 10mm 12mm 10mm; }
        .report-paper { --zebra-color: #f2f2f2; }
        .audit-filters {
            margin: 0 0 1rem;
            padding: 0.55rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.25rem;
            background: #f8fafc;
            color: #374151;
            font-size: 0.8rem;
        }
    </style>
</head>
<body class="report-stream">
    <div class="report-toolbar">
        <a href="<?php echo appEscape($montarUrlPagina($paginaAtual)); ?>" class="report-back-button">Voltar</a>
        <button type="button" id="audit-print-button">Imprimir</button>
        <a href="<?php echo appEscape($montarUrlPagina(1, $porPagina, 'pdf')); ?>">Baixar PDF</a>
    </div>
    <main id="report-output" class="report-paper" data-zebra="true">
        <header class="report-heading">
            <h1>Log de Auditoria</h1>
            <p><?php echo appEscape($subtituloImpressao); ?></p>
        </header>
        <p class="audit-filters"><strong>Filtros aplicados:</strong> <?php echo appEscape($textoFiltros); ?></p>
        <?php $renderTabelaImpressao(); ?>
    </main>
    <script>
        document.getElementById('audit-print-button')?.addEventListener('click', () => window.print());
    </script>
</body>
</html>
    <?php
    exit;
}

$paginasVisiveis = [];
if ($totalPaginas <= 7) {
    $paginasVisiveis = range(1, $totalPaginas);
} else {
    $paginasVisiveis[] = 1;
    $inicioJanela = max(2, $paginaAtual - 1);
    $fimJanela = min($totalPaginas - 1, $paginaAtual + 1);
    if ($inicioJanela > 2) {
        $paginasVisiveis[] = null;
    }
    for ($p = $inicioJanela; $p <= $fimJanela; $p++) {
        $paginasVisiveis[] = $p;
    }
    if ($fimJanela < $totalPaginas - 1) {
        $paginasVisiveis[] = null;
    }
    $paginasVisiveis[] = $totalPaginas;
}
$registroInicial = $totalRegistros > 0 ? $offset + 1 : 0;
$registroFinal = $totalRegistros > 0 ? min($totalRegistros, $offset + count($logs)) : 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Elo</title>
    <link rel="icon" type="image/png" href="<?php echo appEscape($baseUrl . '/assets/imgs/logo-elo.png'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/tailwind-config.js'); ?>"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="flex h-screen flex-col overflow-hidden bg-gray-100 font-sans text-gray-800">
    <?php include __DIR__ . '/../../../components/geral/navbar-geral.php'; ?>
    <div class="flex min-h-0 flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../../components/geral/sidebar-geral.php'; ?>
        <main class="min-w-0 flex-1 overflow-y-auto bg-gray-50 p-4 md:p-6">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Log de Auditoria</h1>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-elo-teal/30 bg-elo-teal/10 px-3 py-1 text-xs font-medium text-elo-teal">
                        <i class="ph ph-file-text text-base" aria-hidden="true"></i>
                        Arquivo .log: <?php echo $gravarArquivoLogAtivo ? 'Ativo' : 'Desativado'; ?>
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-700">
                        <i class="ph ph-clock-countdown text-base text-elo-teal" aria-hidden="true"></i>
                        Retenção: <?php echo $retencaoDiasAtual !== null ? ($retencaoDiasAtual . ' dias') : 'Nunca apagar'; ?>
                    </span>
                    <button type="button" id="btnAbrirConfigAuditoria"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-elo-teal bg-white px-3.5 py-1.5 text-xs font-semibold text-elo-teal shadow-sm hover:bg-elo-teal/5">
                        <i class="ph ph-gear-six text-base" aria-hidden="true"></i>
                        Configurações e limpeza
                    </button>
                </div>
            </div>

            <section class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-elo-teal/10 text-elo-teal">
                        <i class="ph ph-clock-counter-clockwise text-2xl" aria-hidden="true"></i>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Eventos (24h)</p>
                        <p class="mt-0.5 text-2xl font-bold text-gray-800"><?php echo (int) $resumo['eventos24h']; ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 shadow-sm">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-emerald-200/80 text-emerald-700">
                        <i class="ph ph-sign-in text-2xl" aria-hidden="true"></i>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-emerald-700">Logins (30 dias)</p>
                        <p class="mt-0.5 text-2xl font-bold text-emerald-900"><?php echo (int) $resumo['logins30d']; ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50/70 p-4 shadow-sm">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-amber-200/80 text-amber-700">
                        <i class="ph ph-warning-octagon text-2xl" aria-hidden="true"></i>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-amber-700">Alertas de acesso (30d)</p>
                        <p class="mt-0.5 text-2xl font-bold text-amber-900"><?php echo (int) $resumo['alertas30d']; ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-blue-200 bg-blue-50/70 p-4 shadow-sm">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-blue-200/80 text-blue-700">
                        <i class="ph ph-sliders-horizontal text-2xl" aria-hidden="true"></i>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-blue-700">Alterações (30 dias)</p>
                        <p class="mt-0.5 text-2xl font-bold text-blue-900"><?php echo (int) $resumo['alteracoes30d']; ?></p>
                    </div>
                </div>
            </section>

            <section class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:p-5">
                <form method="get" action="<?php echo appEscape($urlAtual); ?>" class="space-y-4">
                    <?php if (appCurrentTabToken() !== null): ?>
                        <input type="hidden" name="tabToken" value="<?php echo appEscape((string) appCurrentTabToken()); ?>">
                    <?php endif; ?>
                    <?php if ($porPagina !== 25): ?>
                        <input type="hidden" name="porPagina" value="<?php echo $porPagina; ?>">
                    <?php endif; ?>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                        <label class="text-xs font-medium text-gray-700">Data inicial
                            <input type="date" name="dataInicio" value="<?php echo appEscape($filtroDataInicio); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 bg-white px-2.5 py-2 text-sm text-gray-800 focus:border-elo-teal focus:outline-none focus:ring-1 focus:ring-elo-teal">
                        </label>
                        <label class="text-xs font-medium text-gray-700">Data final
                            <input type="date" name="dataFim" value="<?php echo appEscape($filtroDataFim); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 bg-white px-2.5 py-2 text-sm text-gray-800 focus:border-elo-teal focus:outline-none focus:ring-1 focus:ring-elo-teal">
                        </label>
                        <div class="relative" data-user-search data-csrf="<?php echo appEscape(appCsrfToken()); ?>">
                            <label for="filtroUsuarioPesquisa" class="block text-xs font-medium text-gray-700">Usuário</label>
                            <span class="mt-1 flex">
                                <input id="filtroUsuarioPesquisa"
                                       type="search"
                                       data-user-input
                                       autocomplete="off"
                                       role="combobox"
                                       aria-autocomplete="list"
                                       aria-expanded="false"
                                       aria-controls="sugestoes-usuario"
                                       value="<?php echo appEscape($usuarioSelecionadoLabel); ?>"
                                       placeholder="ID, nome ou login..."
                                       class="min-w-0 flex-1 rounded-l border border-r-0 border-gray-300 bg-white px-2.5 py-2 text-sm text-gray-800 focus:z-10 focus:border-elo-teal focus:outline-none focus:ring-1 focus:ring-elo-teal">
                                <button type="button"
                                        data-user-toggle
                                        aria-label="Mostrar usuários disponíveis"
                                        title="Mostrar usuários disponíveis"
                                        class="inline-flex h-[38px] w-9 shrink-0 items-center justify-center rounded-r border border-gray-300 bg-gray-50 text-gray-600 hover:bg-gray-100 focus:z-10 focus:outline-none focus:ring-1 focus:ring-elo-teal">
                                    <i class="ph ph-caret-down" aria-hidden="true"></i>
                                </button>
                            </span>
                            <input type="hidden" name="idUsuario" data-user-id value="<?php echo $filtroUsuario !== false && $filtroUsuario > 0 ? (int) $filtroUsuario : ''; ?>">
                            <div id="sugestoes-usuario" data-user-results data-preserve-case role="listbox" class="absolute z-20 mt-1 hidden max-h-60 w-full overflow-y-auto rounded border border-gray-200 bg-white shadow-lg"></div>
                            <p data-user-message role="status" aria-live="polite" class="mt-1 text-[11px] text-gray-500">Abra a lista ou digite ID/nome.</p>
                        </div>
                        <label class="text-xs font-medium text-gray-700">Módulo
                            <select name="modulo"
                                    class="mt-1 w-full rounded border border-gray-300 bg-white px-2.5 py-2 text-sm text-gray-800 focus:border-elo-teal focus:outline-none focus:ring-1 focus:ring-elo-teal">
                                <option value="">Todos os módulos</option>
                                <?php foreach ($modulos as $moduloKey => $moduloLabel): ?>
                                    <option value="<?php echo appEscape($moduloKey); ?>" <?php echo $filtroModulo === $moduloKey ? 'selected' : ''; ?>>
                                        <?php echo appEscape($moduloLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="text-xs font-medium text-gray-700">Tipo de ação
                            <select name="acao"
                                    class="mt-1 w-full rounded border border-gray-300 bg-white px-2.5 py-2 text-sm text-gray-800 focus:border-elo-teal focus:outline-none focus:ring-1 focus:ring-elo-teal">
                                <option value="">Todas as ações</option>
                                <?php foreach ($acoes as $acaoKey => $acaoMeta): ?>
                                    <option value="<?php echo appEscape($acaoKey); ?>" <?php echo $filtroAcao === $acaoKey ? 'selected' : ''; ?>>
                                        <?php echo appEscape($acaoMeta['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="text-xs font-medium text-gray-700">Pesquisa livre
                            <input type="search" name="busca" maxlength="120" placeholder="Descrição, login, IP..."
                                   value="<?php echo appEscape($filtroBusca); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 bg-white px-2.5 py-2 text-sm text-gray-800 focus:border-elo-teal focus:outline-none focus:ring-1 focus:ring-elo-teal">
                        </label>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-3">
                        <p class="text-xs text-gray-500">
                            Exibindo <strong><?php echo count($logs); ?></strong> de <strong><?php echo $totalRegistros; ?></strong> evento(s) encontrado(s).
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <?php if ($temFiltros): ?>
                                <a href="<?php echo appEscape(appTabUrl($urlAtual)); ?>"
                                   class="inline-flex items-center gap-1.5 rounded border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                                    <i class="ph ph-eraser" aria-hidden="true"></i>
                                    Limpar
                                </a>
                            <?php endif; ?>
                            <button type="submit" name="modo" value="log"
                                    class="inline-flex items-center gap-1.5 rounded border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                <i class="ph ph-download-simple" aria-hidden="true"></i>
                                Exportar .log
                            </button>
                            <button type="submit" name="modo" value="imprimir"
                                    class="inline-flex items-center gap-1.5 rounded border border-elo-teal bg-white px-3.5 py-2 text-sm font-medium text-elo-teal transition hover:bg-elo-teal/5">
                                <i class="ph ph-printer" aria-hidden="true"></i>
                                Imprimir
                            </button>
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white transition hover:bg-elo-green">
                                <i class="ph ph-funnel" aria-hidden="true"></i>
                                Filtrar
                            </button>
                        </div>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <?php if (!$logs): ?>
                    <div class="p-10 text-center text-gray-500">
                        <i class="ph ph-clipboard-text mb-2 text-4xl text-gray-300" aria-hidden="true"></i>
                        <p class="text-sm font-medium text-gray-600">Nenhum evento de auditoria encontrado.</p>
                        <p class="mt-1 text-xs text-gray-400">Os eventos serão registrados automaticamente conforme os usuários acessam e alteram o sistema.</p>
                    </div>
                <?php else: ?>
                    <form id="formApagarLogsSelecionados" method="post" data-confirm="Confirma apagar permanentemente os itens de log selecionados?">
                        <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                        <input type="hidden" name="acao" value="apagarSelecionados">
                        <div id="auditBulkBar" class="hidden flex-wrap items-center justify-between gap-2 border-b border-rose-200 bg-rose-50 px-4 py-2.5 text-xs">
                            <span class="font-semibold text-rose-800">
                                <span id="auditSelectedCount">0</span> item(ns) selecionado(s) nesta página
                            </span>
                            <div class="flex items-center gap-2">
                                <button type="button" id="btnDesmarcarLogs" class="rounded border border-rose-200 bg-white px-2.5 py-1 font-medium text-rose-700 hover:bg-rose-100">
                                    Desmarcar
                                </button>
                                <button type="submit" class="inline-flex items-center gap-1 rounded bg-rose-600 px-3 py-1 font-semibold text-white hover:bg-rose-700">
                                    <i class="ph ph-trash" aria-hidden="true"></i> Apagar selecionados
                                </button>
                            </div>
                        </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600">
                                <tr>
                                    <th class="w-10 px-3 py-3 text-center">
                                        <input type="checkbox" id="auditSelectAllPage" aria-label="Selecionar todos os logs desta página"
                                               class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                                    </th>
                                    <th class="px-4 py-3">Data / Hora</th>
                                    <th class="px-4 py-3">Usuário</th>
                                    <th class="px-4 py-3">Módulo</th>
                                    <th class="px-4 py-3">Ação</th>
                                    <th class="px-4 py-3">Descrição</th>
                                    <th class="px-4 py-3">IP</th>
                                    <th class="px-4 py-3 text-right">Detalhes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <?php foreach ($logs as $log): ?>
                                    <?php
                                    $moduloLabel = $modulos[$log['modulo']] ?? $log['modulo'];
                                    $acaoMeta = $acoes[$log['acao']] ?? [
                                        'label' => $log['acao'],
                                        'classe' => 'bg-gray-100 text-gray-700 border-gray-200',
                                    ];
                                    $dataHoraFmt = date('d/m/Y H:i:s', strtotime((string) $log['dataHora']));
                                    $usuarioNome = $log['nomeUsuario'] !== null ? (string) $log['nomeUsuario'] : null;
                                    $loginTexto = $log['loginInformado'] !== null ? (string) $log['loginInformado'] : '—';
                                    $entidadeTexto = $log['entidade'] !== null
                                        ? $log['entidade'] . ($log['idRegistro'] !== null ? ' #' . $log['idRegistro'] : '')
                                        : '—';
                                    $detalhesFormatados = null;
                                    if ($log['detalhesJson'] !== null && $log['detalhesJson'] !== '') {
                                        $decoded = json_decode((string) $log['detalhesJson'], true);
                                        $detalhesFormatados = $decoded !== null
                                            ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                                            : (string) $log['detalhesJson'];
                                    }
                                    $modalPayload = json_encode([
                                        'idLog' => (int) $log['idLog'],
                                        'dataHora' => $dataHoraFmt,
                                        'usuario' => $usuarioNome ? ($usuarioNome . ' (' . $loginTexto . ')') : $loginTexto,
                                        'modulo' => $moduloLabel,
                                        'acao' => $acaoMeta['label'],
                                        'entidade' => $entidadeTexto,
                                        'descricao' => (string) $log['descricao'],
                                        'ip' => $log['enderecoIp'] !== null ? (string) $log['enderecoIp'] : '—',
                                        'userAgent' => $log['userAgent'] !== null ? (string) $log['userAgent'] : '—',
                                        'detalhes' => $detalhesFormatados,
                                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                                    ?>
                                    <tr class="transition hover:bg-gray-50/80">
                                        <td class="w-10 px-3 py-3 text-center">
                                            <input type="checkbox" name="ids[]" value="<?php echo (int) $log['idLog']; ?>" data-audit-check
                                                   aria-label="Selecionar log #<?php echo (int) $log['idLog']; ?>"
                                                   class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-xs font-medium text-gray-700" data-preserve-case>
                                            <?php echo appEscape($dataHoraFmt); ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php if ($usuarioNome !== null): ?>
                                                <div class="font-medium text-gray-800" data-preserve-case><?php echo appEscape($usuarioNome); ?></div>
                                                <div class="text-xs text-gray-500" data-preserve-case><?php echo appEscape($loginTexto); ?></div>
                                            <?php else: ?>
                                                <div class="font-medium text-gray-700" data-preserve-case><?php echo appEscape($loginTexto); ?></div>
                                                <div class="text-xs text-gray-400">Não vinculado</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3">
                                            <span class="inline-flex rounded-md border border-gray-200 bg-gray-50 px-2 py-0.5 text-xs font-medium text-gray-700">
                                                <?php echo appEscape($moduloLabel); ?>
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3">
                                            <span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold <?php echo appEscape($acaoMeta['classe']); ?>">
                                                <?php echo appEscape($acaoMeta['label']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700" data-preserve-case>
                                            <div><?php echo appEscape((string) $log['descricao']); ?></div>
                                            <?php if ($entidadeTexto !== '—'): ?>
                                                <div class="mt-0.5 text-xs text-gray-400"><?php echo appEscape($entidadeTexto); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-gray-500" data-preserve-case>
                                            <?php echo appEscape($log['enderecoIp'] !== null ? (string) $log['enderecoIp'] : '—'); ?>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right">
                                            <button type="button"
                                                    data-audit-open
                                                    data-audit-payload="<?php echo appEscape((string) $modalPayload); ?>"
                                                    class="inline-flex items-center gap-1 rounded border border-elo-teal/40 px-2.5 py-1 text-xs font-medium text-elo-teal transition hover:bg-elo-teal/10">
                                                <i class="ph ph-eye" aria-hidden="true"></i>
                                                Detalhes
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    </form>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-4 py-3 text-sm" data-preserve-case>
                        <div class="flex flex-wrap items-center gap-3 text-xs text-gray-600">
                            <label class="inline-flex items-center gap-2">
                                <span>Mostrar</span>
                                <select id="auditPerPageSelect"
                                        aria-label="Registros por página"
                                        class="rounded border border-gray-300 bg-white px-2 py-1 text-xs text-gray-800 focus:border-elo-teal focus:outline-none focus:ring-1 focus:ring-elo-teal">
                                    <?php foreach ($opcoesPorPagina as $opcao): ?>
                                        <option value="<?php echo appEscape($montarUrlPagina(1, $opcao)); ?>" <?php echo $porPagina === $opcao ? 'selected' : ''; ?>>
                                            <?php echo $opcao; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span>por página</span>
                            </label>
                            <span class="text-gray-300">|</span>
                            <span>
                                Mostrando <strong><?php echo $registroInicial; ?>–<?php echo $registroFinal; ?></strong> de <strong><?php echo $totalRegistros; ?></strong> evento(s)
                            </span>
                        </div>
                        <nav class="flex flex-wrap items-center gap-1.5" aria-label="Paginação do log de auditoria">
                            <?php if ($paginaAtual > 1): ?>
                                <a href="<?php echo appEscape($montarUrlPagina($paginaAtual - 1)); ?>"
                                   class="inline-flex items-center gap-1 rounded border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100">
                                    <i class="ph ph-caret-left" aria-hidden="true"></i> Anterior
                                </a>
                            <?php else: ?>
                                <span class="inline-flex cursor-not-allowed items-center gap-1 rounded border border-gray-200 bg-gray-100 px-2.5 py-1.5 text-xs font-medium text-gray-400">
                                    <i class="ph ph-caret-left" aria-hidden="true"></i> Anterior
                                </span>
                            <?php endif; ?>

                            <?php foreach ($paginasVisiveis as $numPagina): ?>
                                <?php if ($numPagina === null): ?>
                                    <span class="px-1.5 text-xs text-gray-400">…</span>
                                <?php elseif ($numPagina === $paginaAtual): ?>
                                    <span aria-current="page"
                                          class="inline-flex min-w-[28px] items-center justify-center rounded border border-elo-teal bg-elo-teal px-2 py-1.5 text-xs font-semibold text-white">
                                        <?php echo $numPagina; ?>
                                    </span>
                                <?php else: ?>
                                    <a href="<?php echo appEscape($montarUrlPagina($numPagina)); ?>"
                                       class="inline-flex min-w-[28px] items-center justify-center rounded border border-gray-300 bg-white px-2 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100">
                                        <?php echo $numPagina; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <?php if ($paginaAtual < $totalPaginas): ?>
                                <a href="<?php echo appEscape($montarUrlPagina($paginaAtual + 1)); ?>"
                                   class="inline-flex items-center gap-1 rounded border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100">
                                    Próxima <i class="ph ph-caret-right" aria-hidden="true"></i>
                                </a>
                            <?php else: ?>
                                <span class="inline-flex cursor-not-allowed items-center gap-1 rounded border border-gray-200 bg-gray-100 px-2.5 py-1.5 text-xs font-medium text-gray-400">
                                    Próxima <i class="ph ph-caret-right" aria-hidden="true"></i>
                                </span>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <!-- Modal de Configurações de Log (.log), Retenção e Limpeza -->
    <div id="auditSettingsModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="auditSettingsTitle">
        <div class="relative max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl space-y-5">
            <button type="button" data-settings-close class="absolute right-4 top-4 text-gray-400 transition hover:text-gray-700" aria-label="Fechar">
                <i class="ph ph-x text-2xl" aria-hidden="true"></i>
            </button>
            <div>
                <h2 id="auditSettingsTitle" class="pr-8 text-lg font-bold text-gray-800">Configurações do Log de Auditoria</h2>
                <p class="mt-0.5 text-xs text-gray-500">Defina a gravação em arquivo `.log` no servidor e a política de limpeza automática de registros antigos.</p>
            </div>

            <?php
            $retPreset = 'nunca';
            if ($retencaoDiasAtual !== null) {
                $retPreset = in_array((string) $retencaoDiasAtual, ['30', '60', '90', '180', '365'], true)
                    ? (string) $retencaoDiasAtual
                    : 'personalizado';
            }
            ?>
            <form method="post" class="space-y-4 rounded-xl border border-gray-200 bg-gray-50/70 p-4">
                <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                <input type="hidden" name="acao" value="salvarConfigLog">

                <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-gray-200 bg-white p-3 text-sm">
                    <input type="checkbox" name="gravarArquivoLog" value="1" <?php echo $gravarArquivoLogAtivo ? 'checked' : ''; ?>
                           class="mt-0.5 rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                    <span>
                        <span class="block font-semibold text-gray-800">Salvar cópia em arquivo `.log` no servidor</span>
                        <span class="block text-xs text-gray-500">Registra cada evento também em <code>logs/auditoria-AAAA-MM.log</code> (diretório privado).</span>
                    </span>
                </label>

                <div class="space-y-2 rounded-lg border border-gray-200 bg-white p-3">
                    <label for="retencaoModoSelect" class="block text-sm font-semibold text-gray-800">Limpeza automática de logs antigos (banco de dados)</label>
                    <select id="retencaoModoSelect" name="retencaoModo" class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                        <option value="nunca" <?php echo $retPreset === 'nunca' ? 'selected' : ''; ?>>Nunca apagar automaticamente (manter histórico completo)</option>
                        <option value="30" <?php echo $retPreset === '30' ? 'selected' : ''; ?>>Apagar registros com mais de 30 dias</option>
                        <option value="60" <?php echo $retPreset === '60' ? 'selected' : ''; ?>>Apagar registros com mais de 60 dias</option>
                        <option value="90" <?php echo $retPreset === '90' ? 'selected' : ''; ?>>Apagar registros com mais de 90 dias</option>
                        <option value="180" <?php echo $retPreset === '180' ? 'selected' : ''; ?>>Apagar registros com mais de 180 dias</option>
                        <option value="365" <?php echo $retPreset === '365' ? 'selected' : ''; ?>>Apagar registros com mais de 365 dias (1 ano)</option>
                        <option value="personalizado" <?php echo $retPreset === 'personalizado' ? 'selected' : ''; ?>>Prazo personalizado (em dias)</option>
                    </select>
                    <div id="retencaoCustomWrap" class="<?php echo $retPreset === 'personalizado' ? '' : 'hidden'; ?> pt-1">
                        <label class="block text-xs font-medium text-gray-700">Manter logs dos últimos quantos dias? (7 a 3650)
                            <input type="number" name="retencaoPersonalizada" min="7" max="3650"
                                   value="<?php echo $retencaoDiasAtual ?? 90; ?>"
                                   class="mt-1 w-40 rounded border border-gray-300 px-3 py-1.5 text-sm">
                        </label>
                    </div>
                    <label class="inline-flex items-center gap-2 pt-1 text-xs text-gray-600">
                        <input type="checkbox" name="limparAgora" value="1" class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                        Aplicar limpeza imediatamente ao salvar (se houver prazo definido)
                    </label>
                    <?php if ($ultimaLimpezaAtual !== null): ?>
                        <p class="text-[11px] text-gray-400">Última limpeza executada em <?php echo date('d/m/Y H:i', strtotime($ultimaLimpezaAtual)); ?>.</p>
                    <?php endif; ?>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-lg bg-elo-teal px-4 py-2 text-sm font-semibold text-white hover:bg-elo-green">
                        Salvar configurações
                    </button>
                </div>
            </form>

            <!-- Limpeza manual sob demanda -->
            <form method="post" class="flex flex-wrap items-end justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50/70 p-4"
                  data-confirm="Confirma apagar permanentemente os logs mais antigos que o prazo informado?">
                <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                <input type="hidden" name="acao" value="executarLimpeza">
                <div>
                    <h3 class="text-sm font-semibold text-amber-900">Limpeza manual imediata</h3>
                    <p class="text-xs text-amber-800">Apaga do banco os registros mais antigos que o número de dias abaixo (os arquivos `.log` no disco permanecem preservados).</p>
                </div>
                <div class="flex items-center gap-2">
                    <input type="number" name="diasLimpeza" min="1" max="3650" value="<?php echo $retencaoDiasAtual ?? 90; ?>"
                           aria-label="Dias para limpeza manual"
                           class="w-24 rounded border border-amber-300 bg-white px-2.5 py-1.5 text-sm">
                    <span class="text-xs font-medium text-amber-900">dias</span>
                    <button type="submit" class="rounded-lg bg-amber-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">
                        Limpar agora
                    </button>
                </div>
            </form>

            <!-- Arquivos .log gravados no servidor -->
            <div class="rounded-xl border border-gray-200 p-4">
                <h3 class="text-sm font-semibold text-gray-800">Arquivos `.log` salvos no servidor</h3>
                <?php if (!$arquivosLogDisco): ?>
                    <p class="mt-2 text-xs text-gray-500">Nenhum arquivo `.log` gerado ainda. Assim que novos eventos forem registrados com a opção ativa, eles aparecerão aqui.</p>
                <?php else: ?>
                    <div class="mt-2.5 divide-y divide-gray-100">
                        <?php foreach ($arquivosLogDisco as $arq): ?>
                            <div class="flex flex-wrap items-center justify-between gap-2 py-2 text-xs">
                                <div>
                                    <span class="font-mono font-semibold text-gray-800"><?php echo appEscape($arq['nome']); ?></span>
                                    <span class="ml-2 text-gray-500"><?php echo number_format($arq['tamanho'] / 1024, 1, ',', '.'); ?> KB · atualizado em <?php echo appEscape($arq['modificadoEm']); ?></span>
                                </div>
                                <form method="post">
                                    <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                                    <input type="hidden" name="acao" value="baixarArquivoLog">
                                    <input type="hidden" name="arquivo" value="<?php echo appEscape($arq['nome']); ?>">
                                    <button type="submit" class="inline-flex items-center gap-1 rounded border border-elo-teal/40 bg-elo-teal/5 px-2.5 py-1 font-medium text-elo-teal hover:bg-elo-teal/10">
                                        <i class="ph ph-download-simple" aria-hidden="true"></i> Baixar .log
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="auditDetailModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="auditModalTitle">
        <div class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-2xl">
            <button type="button" data-audit-close class="absolute right-4 top-4 text-gray-400 transition hover:text-gray-700" aria-label="Fechar">
                <i class="ph ph-x text-2xl" aria-hidden="true"></i>
            </button>
            <h2 id="auditModalTitle" class="mb-4 pr-8 text-lg font-semibold text-gray-800">Detalhes do Evento</h2>
            <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2" data-preserve-case>
                <div class="rounded-lg bg-gray-50 p-3">
                    <dt class="text-xs font-semibold uppercase text-gray-500">Registro</dt>
                    <dd id="auditDetailId" class="mt-0.5 font-medium text-gray-800"></dd>
                </div>
                <div class="rounded-lg bg-gray-50 p-3">
                    <dt class="text-xs font-semibold uppercase text-gray-500">Data / Hora</dt>
                    <dd id="auditDetailDataHora" class="mt-0.5 font-medium text-gray-800"></dd>
                </div>
                <div class="rounded-lg bg-gray-50 p-3">
                    <dt class="text-xs font-semibold uppercase text-gray-500">Usuário</dt>
                    <dd id="auditDetailUsuario" class="mt-0.5 font-medium text-gray-800"></dd>
                </div>
                <div class="rounded-lg bg-gray-50 p-3">
                    <dt class="text-xs font-semibold uppercase text-gray-500">Módulo / Ação</dt>
                    <dd id="auditDetailModuloAcao" class="mt-0.5 font-medium text-gray-800"></dd>
                </div>
                <div class="rounded-lg bg-gray-50 p-3">
                    <dt class="text-xs font-semibold uppercase text-gray-500">Entidade</dt>
                    <dd id="auditDetailEntidade" class="mt-0.5 font-medium text-gray-800"></dd>
                </div>
                <div class="rounded-lg bg-gray-50 p-3">
                    <dt class="text-xs font-semibold uppercase text-gray-500">Endereço IP</dt>
                    <dd id="auditDetailIp" class="mt-0.5 font-mono text-gray-800"></dd>
                </div>
                <div class="rounded-lg bg-gray-50 p-3 sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase text-gray-500">Descrição</dt>
                    <dd id="auditDetailDescricao" class="mt-0.5 text-gray-800"></dd>
                </div>
                <div class="rounded-lg bg-gray-50 p-3 sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase text-gray-500">Navegador (User-Agent)</dt>
                    <dd id="auditDetailUserAgent" class="mt-0.5 break-all text-xs text-gray-600"></dd>
                </div>
            </dl>
            <div id="auditDetailJsonWrapper" class="mt-4 hidden">
                <h3 class="mb-1.5 text-xs font-semibold uppercase text-gray-500">Metadados registrados</h3>
                <pre id="auditDetailJson" class="max-h-64 overflow-auto rounded-lg border border-gray-200 bg-gray-900 p-3 text-xs text-gray-100"></pre>
            </div>
            <div class="mt-5 flex justify-end">
                <button type="button" data-audit-close class="rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">
                    Fechar
                </button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const userSearch = document.querySelector('[data-user-search]');
            if (userSearch) {
                const form = userSearch.closest('form');
                const input = userSearch.querySelector('[data-user-input]');
                const hidden = userSearch.querySelector('[data-user-id]');
                const toggle = userSearch.querySelector('[data-user-toggle]');
                const results = userSearch.querySelector('[data-user-results]');
                const message = userSearch.querySelector('[data-user-message]');
                const csrfToken = userSearch.getAttribute('data-csrf') || '';
                const defaultMessage = 'Abra a lista ou digite ID/nome.';
                let debounceTimer = null;
                let requestController = null;
                let activeIndex = -1;

                const showResults = (statusText) => {
                    results.classList.remove('hidden');
                    input.setAttribute('aria-expanded', 'true');
                    if (statusText !== undefined) message.textContent = statusText;
                };
                const closeResults = () => {
                    results.classList.add('hidden');
                    input.setAttribute('aria-expanded', 'false');
                    input.removeAttribute('aria-activedescendant');
                    activeIndex = -1;
                };
                const selectItem = (item) => {
                    input.value = item.label;
                    hidden.value = String(item.id);
                    input.setCustomValidity('');
                    message.textContent = 'Usuário selecionado.';
                    closeResults();
                };
                const renderItems = (items) => {
                    results.replaceChildren();
                    activeIndex = -1;
                    if (!items.length) {
                        const empty = document.createElement('p');
                        empty.className = 'px-3 py-2 text-xs text-gray-500';
                        empty.textContent = 'Nenhum usuário encontrado.';
                        results.append(empty);
                        showResults('Nenhum usuário encontrado.');
                        return;
                    }
                    items.forEach((item, index) => {
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.id = `${results.id}-opcao-${index}`;
                        option.className = 'block w-full px-3 py-2 text-left text-xs text-gray-700 hover:bg-elo-teal/10 focus:bg-elo-teal/10 focus:outline-none';
                        option.setAttribute('role', 'option');
                        option.setAttribute('aria-selected', 'false');
                        option.textContent = item.label;
                        option.addEventListener('pointerdown', (event) => {
                            event.preventDefault();
                            selectItem(item);
                        });
                        option.addEventListener('click', () => {
                            if (hidden.value !== String(item.id)) selectItem(item);
                        });
                        results.append(option);
                    });
                    const countMessage = items.length === 50
                        ? 'Mostrando até 50 usuários. Digite para filtrar.'
                        : `${items.length} usuário(s). Use setas e Enter para escolher.`;
                    showResults(countMessage);
                };
                const searchUsers = async (browseAll = false) => {
                    const query = browseAll ? '' : input.value.trim();
                    requestController?.abort();
                    const controller = new AbortController();
                    requestController = controller;

                    const tabToken = form?.elements.namedItem('tabToken')?.value
                        || new URLSearchParams(window.location.search).get('tabToken')
                        || window.sessionStorage.getItem('sistemaEloTabToken')
                        || '';

                    const body = new FormData();
                    body.set('csrfToken', csrfToken);
                    body.set('tabToken', tabToken);
                    body.set('acao', 'buscarUsuarios');
                    body.set('busca', query);

                    showResults('Pesquisando...');
                    results.replaceChildren();
                    try {
                        const response = await fetch(window.location.pathname + window.location.search, {
                            method: 'POST',
                            body,
                            headers: {'X-Requested-With': 'XMLHttpRequest'},
                            credentials: 'same-origin',
                            signal: controller.signal
                        });
                        if (!(response.headers.get('content-type') || '').includes('application/json')) {
                            throw new Error('Não foi possível validar sua sessão. Atualize a página.');
                        }
                        const result = await response.json();
                        if (!response.ok) throw new Error(result.message || 'Falha ao pesquisar usuários.');
                        if (requestController !== controller) return;
                        renderItems(result.items || []);
                    } catch (error) {
                        if (error.name === 'AbortError') return;
                        if (requestController !== controller) return;
                        results.replaceChildren();
                        showResults(error.message || 'Falha ao pesquisar usuários.');
                    }
                };

                toggle.addEventListener('pointerdown', (event) => event.preventDefault());
                toggle.addEventListener('click', () => {
                    if (input.getAttribute('aria-expanded') === 'true') {
                        requestController?.abort();
                        closeResults();
                        return;
                    }
                    searchUsers(true);
                    input.focus();
                });
                input.addEventListener('focus', () => {
                    if (input.getAttribute('aria-expanded') !== 'true') {
                        searchUsers(hidden.value !== '' || input.value.trim() === '');
                    }
                });
                input.addEventListener('input', () => {
                    window.clearTimeout(debounceTimer);
                    requestController?.abort();
                    hidden.value = '';
                    const trimmed = input.value.trim();
                    input.setCustomValidity(trimmed === '' ? '' : 'Selecione um usuário nas sugestões ou apague o texto.');
                    if (trimmed === '') {
                        message.textContent = defaultMessage;
                    }
                    debounceTimer = window.setTimeout(() => searchUsers(false), 250);
                });
                input.addEventListener('keydown', (event) => {
                    const options = Array.from(results.querySelectorAll('[role="option"]'));
                    if (event.key === 'Escape') {
                        closeResults();
                        return;
                    }
                    if (event.key === 'Enter' && input.getAttribute('aria-expanded') === 'true') {
                        if (activeIndex >= 0 && options[activeIndex]) {
                            event.preventDefault();
                            options[activeIndex].click();
                        } else if (!options.length) {
                            event.preventDefault();
                        }
                        return;
                    }
                    if (!options.length || !['ArrowDown', 'ArrowUp'].includes(event.key)) return;
                    event.preventDefault();
                    activeIndex = event.key === 'ArrowDown'
                        ? (activeIndex + 1) % options.length
                        : (activeIndex - 1 + options.length) % options.length;
                    options.forEach((option, index) => {
                        const active = index === activeIndex;
                        option.setAttribute('aria-selected', String(active));
                        option.classList.toggle('bg-elo-teal/10', active);
                    });
                    input.setAttribute('aria-activedescendant', options[activeIndex].id);
                    options[activeIndex].scrollIntoView({block: 'nearest'});
                });
                input.addEventListener('blur', () => {
                    window.setTimeout(() => {
                        if (!userSearch.contains(document.activeElement)) closeResults();
                    }, 100);
                });
                form?.addEventListener('submit', (event) => {
                    if (input.value.trim() !== '' && hidden.value === '') {
                        input.setCustomValidity('Selecione um usuário nas sugestões ou apague o texto.');
                        input.reportValidity();
                        event.preventDefault();
                        input.focus();
                    }
                });
            }

            document.getElementById('auditPerPageSelect')?.addEventListener('change', (event) => {
                const targetUrl = event.target.value;
                if (targetUrl) window.location.assign(targetUrl);
            });

            const modal = document.getElementById('auditDetailModal');
            if (!modal) return;

            const setText = (id, value) => {
                const el = document.getElementById(id);
                if (el) el.textContent = value || '—';
            };
            const jsonWrapper = document.getElementById('auditDetailJsonWrapper');
            const jsonPre = document.getElementById('auditDetailJson');

            const closeModal = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            };

            document.querySelectorAll('[data-audit-open]').forEach((button) => {
                button.addEventListener('click', () => {
                    try {
                        const data = JSON.parse(button.getAttribute('data-audit-payload') || '{}');
                        setText('auditDetailId', '#' + (data.idLog || ''));
                        setText('auditDetailDataHora', data.dataHora);
                        setText('auditDetailUsuario', data.usuario);
                        setText('auditDetailModuloAcao', (data.modulo || '') + ' · ' + (data.acao || ''));
                        setText('auditDetailEntidade', data.entidade);
                        setText('auditDetailIp', data.ip);
                        setText('auditDetailDescricao', data.descricao);
                        setText('auditDetailUserAgent', data.userAgent);
                        if (data.detalhes && jsonWrapper && jsonPre) {
                            jsonPre.textContent = data.detalhes;
                            jsonWrapper.classList.remove('hidden');
                        } else if (jsonWrapper) {
                            jsonWrapper.classList.add('hidden');
                        }
                        modal.classList.remove('hidden');
                        modal.classList.add('flex');
                    } catch (err) {
                        console.error(err);
                    }
                });
            });

            modal.querySelectorAll('[data-audit-close]').forEach((btn) => {
                btn.addEventListener('click', closeModal);
            });
            modal.addEventListener('click', (event) => {
                if (event.target === modal) closeModal();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });

            // Seleção em lote de logs para exclusão seletiva
            const selectAllChk = document.getElementById('auditSelectAllPage');
            const rowChks = Array.from(document.querySelectorAll('[data-audit-check]'));
            const bulkBar = document.getElementById('auditBulkBar');
            const selectedCountEl = document.getElementById('auditSelectedCount');
            const syncSelection = () => {
                const checked = rowChks.filter((c) => c.checked).length;
                if (selectedCountEl) selectedCountEl.textContent = String(checked);
                if (bulkBar) {
                    bulkBar.classList.toggle('hidden', checked === 0);
                    bulkBar.classList.toggle('flex', checked > 0);
                }
                if (selectAllChk) {
                    selectAllChk.checked = rowChks.length > 0 && checked === rowChks.length;
                    selectAllChk.indeterminate = checked > 0 && checked < rowChks.length;
                }
            };
            selectAllChk?.addEventListener('change', () => {
                rowChks.forEach((c) => { c.checked = selectAllChk.checked; });
                syncSelection();
            });
            rowChks.forEach((c) => c.addEventListener('change', syncSelection));
            document.getElementById('btnDesmarcarLogs')?.addEventListener('click', () => {
                rowChks.forEach((c) => { c.checked = false; });
                syncSelection();
            });

            // Modal de configurações & retenção
            const settingsModal = document.getElementById('auditSettingsModal');
            const closeSettings = () => {
                settingsModal?.classList.add('hidden');
                settingsModal?.classList.remove('flex');
            };
            document.getElementById('btnAbrirConfigAuditoria')?.addEventListener('click', () => {
                settingsModal?.classList.remove('hidden');
                settingsModal?.classList.add('flex');
            });
            settingsModal?.querySelectorAll('[data-settings-close]').forEach((b) => b.addEventListener('click', closeSettings));
            settingsModal?.addEventListener('click', (e) => { if (e.target === settingsModal) closeSettings(); });
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && settingsModal && !settingsModal.classList.contains('hidden')) closeSettings(); });

            const retSelect = document.getElementById('retencaoModoSelect');
            const retCustomWrap = document.getElementById('retencaoCustomWrap');
            retSelect?.addEventListener('change', () => {
                retCustomWrap?.classList.toggle('hidden', retSelect.value !== 'personalizado');
            });
        })();
    </script>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
    <?php $toastSuccess = $sucessoAcao; $toastError = $erroAcao; include __DIR__ . '/../../../components/toast.php'; ?>
</body>
</html>
