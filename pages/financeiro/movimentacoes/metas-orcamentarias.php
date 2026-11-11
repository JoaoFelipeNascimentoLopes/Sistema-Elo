<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('financeiro.movimentacoes/metas-orcamentarias');
$pdo = appPdo();
$erro = '';
$metaEdicao = null;
$meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
];
$converterCentavos = static function (mixed $value): int {
    $value = (string) $value;
    if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $parts)) {
        throw new UnexpectedValueException('Valor monetário inválido retornado pelo banco.');
    }
    return ((int) $parts[1] * 100) + (int) str_pad($parts[2] ?? '', 2, '0');
};
$formatarCentavos = static function (int $centavos): string {
    $negativo = $centavos < 0;
    $centavos = abs($centavos);
    $reais = (string) intdiv($centavos, 100);
    $reais = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $reais) ?? $reais;
    $fracao = str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    return 'R$ ' . ($negativo ? '-' : '') . $reais . ',' . $fracao;
};
$formatarMoeda = static fn (mixed $value): string => $formatarCentavos($converterCentavos($value));
$converterValor = static function (string $valor): ?string {
    $valor = trim($valor);
    if (!preg_match('/^(?:\d{1,12}|\d{1,3}(?:\.\d{3}){1,3})(?:,\d{1,2})?$/', $valor)) {
        return null;
    }
    return str_replace(',', '.', str_replace('.', '', $valor));
};
$validarAno = static function (mixed $value): int|false {
    if (!is_scalar($value)) {
        return false;
    }
    $year = filter_var($value, FILTER_VALIDATE_INT);
    return $year !== false && $year >= 2000 && $year <= 9998 ? $year : false;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    $acao = is_scalar($_POST['acao'] ?? null) ? (string) $_POST['acao'] : '';
    if ($acao === 'detalharCompetencia') {
        header('Content-Type: application/json; charset=UTF-8');
        $year = $validarAno($_POST['ano'] ?? null);
        $month = is_scalar($_POST['mes'] ?? null) ? filter_var($_POST['mes'], FILTER_VALIDATE_INT) : false;
        $mode = is_scalar($_POST['modo'] ?? null) ? (string) $_POST['modo'] : '';
        if ($year === false || $month === false || $month < 1 || $month > 12
            || !in_array($mode, ['efetivados', 'pendentes'], true)) {
            http_response_code(400);
            echo json_encode(['message' => 'A competência selecionada é inválida.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        try {
            $start = sprintf('%04d-%02d-01', $year, $month);
            $end = (new DateTimeImmutable($start))->modify('+1 month')->format('Y-m-d');
            $statuses = $mode === 'pendentes' ? ['Pendente', 'Efetivado'] : ['Efetivado'];
            $placeholders = implode(', ', array_fill(0, count($statuses), '?'));
            $statement = $pdo->prepare(
                'SELECT c.idCategoria, c.nomeCategoria, l.idLancamento, l.dataLancamento,
                        l.descricaoLancamento, l.valorLancamento, l.statusLancamento,
                        COALESCE(fp.nomeRazaoSocial, dc.nomeRazaoSocial) AS pessoaRelacionada
                 FROM tbLancamentoFinanceiro l
                 INNER JOIN tbCategoriaLancamentos c ON c.idCategoria = l.idCategoria
                 LEFT JOIN tbFornecedorPrestador fp ON fp.idFornecedorPrestador = l.idFornecedorPrestador
                 LEFT JOIN tbDoadorContribuinte dc ON dc.idDoadorContribuinte = l.idDoadorContribuinte
                 WHERE c.naturezaCategoria = \'Saída\'
                   AND l.dataLancamento >= ? AND l.dataLancamento < ?
                   AND l.statusLancamento IN (' . $placeholders . ')
                 ORDER BY c.nomeCategoria, l.dataLancamento, l.idLancamento'
            );
            $statement->execute(array_merge([$start, $end], $statuses));
            $categories = [];
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $categoryId = (int) $row['idCategoria'];
                if (!isset($categories[$categoryId])) {
                    $categories[$categoryId] = [
                        'nome' => $row['nomeCategoria'],
                        'total' => 0,
                        'lancamentos' => [],
                    ];
                }
                $categories[$categoryId]['total'] += $converterCentavos($row['valorLancamento']);
                $categories[$categoryId]['lancamentos'][] = [
                    'data' => date('d/m/Y', strtotime($row['dataLancamento'])),
                    'descricao' => $row['descricaoLancamento'],
                    'pessoa' => $row['pessoaRelacionada'] ?? '',
                    'status' => $row['statusLancamento'],
                    'valor' => $formatarMoeda($row['valorLancamento']),
                ];
            }
            foreach ($categories as &$category) {
                $category['total'] = $formatarCentavos($category['total']);
            }
            unset($category);
            echo json_encode(['categorias' => array_values($categories)], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (PDOException $exception) {
            error_log('Falha ao detalhar gastos da meta orçamentária: ' . $exception->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Não foi possível carregar o detalhamento desta competência.'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    try {
        if ($acao === 'salvar') {
            $idInput = $_POST['idMetaOrcamentaria'] ?? 0;
            $idMeta = is_scalar($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;
            $year = $validarAno($_POST['ano'] ?? null);
            $month = is_scalar($_POST['mes'] ?? null) ? filter_var($_POST['mes'], FILTER_VALIDATE_INT) : false;
            $valorInput = is_scalar($_POST['valorOrcado'] ?? null) ? (string) $_POST['valorOrcado'] : '';
            $valor = $converterValor($valorInput);
            if ($idMeta === false || $idMeta < 0 || $year === false
                || $month === false || $month < 1 || $month > 12
                || $valor === null || (float) $valor < 0) {
                throw new InvalidArgumentException('Confira o ano, o mês e o valor orçado.');
            }

            if ($idMeta > 0) {
                $existing = $pdo->prepare('SELECT idMetaOrcamentaria FROM tbMetaOrcamentaria WHERE idMetaOrcamentaria = :id');
                $existing->execute(['id' => $idMeta]);
                if (!$existing->fetchColumn()) {
                    throw new InvalidArgumentException('Meta orçamentária não encontrada.');
                }
                $statement = $pdo->prepare(
                    'UPDATE tbMetaOrcamentaria
                     SET ano = :ano, mes = :mes, valorOrcado = :valor,
                         idUsuarioAlteracao = :idUsuarioAlteracao
                     WHERE idMetaOrcamentaria = :id'
                );
                $statement->execute([
                    'ano' => $year,
                    'mes' => $month,
                    'valor' => $valor,
                    'idUsuarioAlteracao' => (int) $usuarioAtual['idUsuario'],
                    'id' => $idMeta,
                ]);
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO tbMetaOrcamentaria (ano, mes, valorOrcado, idUsuarioCriacao)
                     VALUES (:ano, :mes, :valor, :idUsuarioCriacao)'
                );
                $statement->execute([
                    'ano' => $year,
                    'mes' => $month,
                    'valor' => $valor,
                    'idUsuarioCriacao' => (int) $usuarioAtual['idUsuario'],
                ]);
            }
            header('Location: ' . appTabUrl('metas-orcamentarias.php?ano=' . $year . '&modo=' . rawurlencode((string) ($_POST['modo'] ?? 'efetivados')) . '&resultado=' . ($idMeta > 0 ? 'atualizado' : 'criado')));
            exit;
        }

        if ($acao === 'excluir') {
            $idInput = $_POST['idMetaOrcamentaria'] ?? 0;
            $idMeta = is_scalar($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;
            $year = $validarAno($_POST['ano'] ?? null);
            if (!$idMeta || $idMeta < 1 || $year === false) {
                throw new InvalidArgumentException('Meta orçamentária inválida.');
            }
            $statement = $pdo->prepare('DELETE FROM tbMetaOrcamentaria WHERE idMetaOrcamentaria = :id');
            $statement->execute(['id' => $idMeta]);
            if ($statement->rowCount() === 0) {
                throw new InvalidArgumentException('Meta orçamentária não encontrada.');
            }
            header('Location: ' . appTabUrl('metas-orcamentarias.php?ano=' . $year . '&resultado=excluido'));
            exit;
        }
        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException $exception) {
        $erro = $exception->getMessage();
        if ($acao === 'salvar') {
            $submittedId = $_POST['idMetaOrcamentaria'] ?? 0;
            $safeId = is_scalar($submittedId) ? filter_var($submittedId, FILTER_VALIDATE_INT) : false;
            $metaEdicao = [
                'idMetaOrcamentaria' => $safeId !== false ? $safeId : 0,
                'ano' => is_scalar($_POST['ano'] ?? null) ? (string) $_POST['ano'] : '',
                'mes' => is_scalar($_POST['mes'] ?? null) ? (string) $_POST['mes'] : '',
                'valorOrcado' => is_scalar($_POST['valorOrcado'] ?? null) ? (string) $_POST['valorOrcado'] : '',
            ];
        }
    } catch (PDOException $exception) {
        error_log('Falha ao salvar meta orçamentária: ' . $exception->getMessage());
        $erro = (int) ($exception->errorInfo[1] ?? 0) === 1062
            ? 'Já existe um valor orçado para este mês e ano.'
            : 'Não foi possível salvar a meta orçamentária.';
        if ($acao === 'salvar') {
            $submittedId = $_POST['idMetaOrcamentaria'] ?? 0;
            $safeId = is_scalar($submittedId) ? filter_var($submittedId, FILTER_VALIDATE_INT) : false;
            $metaEdicao = [
                'idMetaOrcamentaria' => $safeId !== false ? $safeId : 0,
                'ano' => is_scalar($_POST['ano'] ?? null) ? (string) $_POST['ano'] : '',
                'mes' => is_scalar($_POST['mes'] ?? null) ? (string) $_POST['mes'] : '',
                'valorOrcado' => is_scalar($_POST['valorOrcado'] ?? null) ? (string) $_POST['valorOrcado'] : '',
            ];
        }
    }
}

$yearInput = $_GET['ano'] ?? date('Y');
$selectedYear = $validarAno($yearInput);
if ($selectedYear === false) {
    $selectedYear = (int) date('Y');
    $erro = $erro !== '' ? $erro : 'O ano selecionado é inválido.';
}
$modeInput = $_GET['modo'] ?? 'efetivados';
$mode = is_scalar($modeInput) && in_array((string) $modeInput, ['efetivados', 'pendentes'], true)
    ? (string) $modeInput
    : 'efetivados';

$idEdicaoInput = $_GET['editar'] ?? null;
$idEdicao = is_scalar($idEdicaoInput) ? filter_var($idEdicaoInput, FILTER_VALIDATE_INT) : false;
if ($metaEdicao === null && $idEdicao) {
    $statement = $pdo->prepare(
        'SELECT m.idMetaOrcamentaria, m.ano, m.mes, m.valorOrcado, m.criadoEm, m.atualizadoEm,
                criador.idUsuario AS criadoPorId, criador.nomeCompleto AS criadoPor,
                alterador.idUsuario AS alteradoPorId, alterador.nomeCompleto AS alteradoPor
         FROM tbMetaOrcamentaria m
         INNER JOIN tbUsuario criador ON criador.idUsuario = m.idUsuarioCriacao
         LEFT JOIN tbUsuario alterador ON alterador.idUsuario = m.idUsuarioAlteracao
         WHERE m.idMetaOrcamentaria = :id'
    );
    $statement->execute(['id' => $idEdicao]);
    $metaEdicao = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$metaEdicao) {
        $erro = 'Meta orçamentária não encontrada.';
    } else {
        $selectedYear = (int) $metaEdicao['ano'];
    }
}
$metaEdicao ??= null;
$savedGoals = $pdo->prepare(
    'SELECT idMetaOrcamentaria, mes, valorOrcado
     FROM tbMetaOrcamentaria WHERE ano = :ano ORDER BY mes'
);
$savedGoals->execute(['ano' => $selectedYear]);
$goalsByMonth = [];
foreach ($savedGoals->fetchAll(PDO::FETCH_ASSOC) as $goal) {
    $goalsByMonth[(int) $goal['mes']] = $goal;
}
$startDate = sprintf('%04d-01-01', $selectedYear);
$endDate = sprintf('%04d-01-01', $selectedYear + 1);
$statusSql = $mode === 'pendentes' ? "IN ('Pendente', 'Efetivado')" : "= 'Efetivado'";
$spendingStatement = $pdo->prepare(
    "SELECT MONTH(l.dataLancamento) AS mes, SUM(l.valorLancamento) AS totalGasto
     FROM tbLancamentoFinanceiro l
     INNER JOIN tbCategoriaLancamentos c ON c.idCategoria = l.idCategoria
     WHERE c.naturezaCategoria = 'Saída'
       AND l.dataLancamento >= :inicio AND l.dataLancamento < :fim
       AND l.statusLancamento $statusSql
     GROUP BY MONTH(l.dataLancamento)"
);
$spendingStatement->execute(['inicio' => $startDate, 'fim' => $endDate]);
$spendingByMonth = [];
foreach ($spendingStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $spendingByMonth[(int) $row['mes']] = $converterCentavos($row['totalGasto']);
}
$totalBudget = array_sum(array_map(
    static fn (array $goal): int => $converterCentavos($goal['valorOrcado']),
    $goalsByMonth
));
$totalSpent = array_sum($spendingByMonth);
$totalBalance = $totalBudget - $totalSpent;
$resultados = [
    'criado' => 'Valor orçado cadastrado.',
    'atualizado' => 'Valor orçado atualizado.',
    'excluido' => 'Valor orçado excluído.',
];
$resultado = $resultados[(string) ($_GET['resultado'] ?? '')] ?? '';
$abaInicial = ($metaEdicao || ($erro !== '' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar'))
    ? 'manutencao'
    : 'pesquisa';
$criadoPor = isset($metaEdicao['criadoPorId'])
    ? $metaEdicao['criadoPorId'] . ' - ' . $metaEdicao['criadoPor']
        . ' - ' . date('d/m/Y H:i', strtotime((string) $metaEdicao['criadoEm']))
    : (int) $usuarioAtual['idUsuario'] . ' - ' . $usuarioAtual['nomeCompleto'] . ' - ' . date('d/m/Y H:i');
$alteradoPor = !empty($metaEdicao['alteradoPorId'])
    ? $metaEdicao['alteradoPorId'] . ' - ' . $metaEdicao['alteradoPor']
        . ' - ' . date('d/m/Y H:i', strtotime((string) $metaEdicao['atualizadoEm']))
    : '—';
$years = range(max(2000, (int) date('Y') - 10), (int) date('Y') + 5);
$knownYears = $pdo->query('SELECT DISTINCT ano FROM tbMetaOrcamentaria ORDER BY ano')->fetchAll(PDO::FETCH_COLUMN);
$years = array_unique(array_map('intval', array_merge($years, $knownYears, [$selectedYear])));
rsort($years);
$formattedInputGoal = '';
if (isset($metaEdicao['valorOrcado'])) {
    $rawGoal = is_numeric($metaEdicao['valorOrcado'])
        ? number_format((float) $metaEdicao['valorOrcado'], 2, ',', '.')
        : (string) $metaEdicao['valorOrcado'];
    $formGoal = str_replace(['.', ','], '', $rawGoal);
    $formattedInputGoal = strlen($formGoal) > 2
        ? substr($formGoal, 0, -2) . ',' . substr($formGoal, -2)
        : $formGoal;
    if (is_numeric($metaEdicao['valorOrcado'])) {
        $formattedInputGoal = number_format((float) $metaEdicao['valorOrcado'], 2, ',', '.');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Elo</title>
    <link rel="icon" type="image/png" href="<?php echo appEscape(appBaseUrl() . '/assets/imgs/logo-elo.png'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo appEscape(appBaseUrl() . '/assets/js/tailwind-config.js'); ?>"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="flex h-screen flex-col overflow-hidden bg-gray-100 font-sans">
    <?php include __DIR__ . '/../../../components/financeiro/navbar-financeiro.php'; ?>
    <div class="flex flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../../components/financeiro/sidebar-financeiro.php'; ?>
        <main class="flex-1 overflow-y-auto bg-gray-50 p-4 md:p-6">
            <h1 class="mb-2 text-2xl font-bold text-gray-800">Metas Orçamentárias</h1>
            <p class="mb-6 text-sm text-gray-600">Controle informativo do orçamento mensal. O sistema nunca bloqueia lançamentos por ultrapassar o valor orçado.</p>
            <?php include __DIR__ . '/../../../components/tabs-pesquisa-manutencao.php'; ?>
            <section id="painel-manutencao" role="tabpanel" aria-labelledby="tab-manutencao" class="mb-8 rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'manutencao' ? '' : 'hidden'; ?>>
                <h2 class="mb-4 text-lg font-semibold"><?php echo !empty($metaEdicao['idMetaOrcamentaria']) ? 'Alterar valor orçado' : 'Definir orçamento mensal'; ?></h2>
                <form method="post" data-confirm="Confirma gravar este valor orçado?" class="grid gap-4 md:grid-cols-2">
                    <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="idMetaOrcamentaria" value="<?php echo (int) ($metaEdicao['idMetaOrcamentaria'] ?? 0); ?>">
                    <input type="hidden" name="modo" value="<?php echo appEscape($mode); ?>">
                    <label class="text-sm font-medium text-gray-700">Ano
                        <input type="number" name="ano" required min="2000" max="9998" value="<?php echo appEscape((string) ($metaEdicao['ano'] ?? $selectedYear)); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    </label>
                    <label class="text-sm font-medium text-gray-700">Mês
                        <select name="mes" required class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                            <option value="">Selecione</option>
                            <?php foreach ($meses as $month => $name): ?>
                                <option value="<?php echo $month; ?>" <?php echo (string) ($metaEdicao['mes'] ?? '') === (string) $month ? 'selected' : ''; ?>><?php echo appEscape($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="text-sm font-medium text-gray-700 md:col-span-2">Valor orçado
                        <input name="valorOrcado" data-money-input inputmode="decimal" autocomplete="off" required maxlength="18" value="<?php echo appEscape($formattedInputGoal); ?>" placeholder="0,00" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        <span class="mt-1 block text-xs font-normal text-gray-500">Este valor é apenas informativo e não impede o registro de despesas.</span>
                    </label>
                    <div class="grid gap-4 md:grid-cols-2 md:col-span-2">
                        <label class="text-sm font-medium text-gray-700">Criado por
                            <input type="text" value="<?php echo appEscape($criadoPor); ?>" disabled class="mt-1 w-full cursor-not-allowed rounded border border-gray-200 bg-gray-100 px-3 py-2 text-gray-600">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Alterado por
                            <input type="text" value="<?php echo appEscape($alteradoPor); ?>" disabled class="mt-1 w-full cursor-not-allowed rounded border border-gray-200 bg-gray-100 px-3 py-2 text-gray-600">
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-2 md:col-span-2">
                        <button type="submit" class="inline-flex items-center gap-2 rounded bg-elo-teal px-4 py-2 font-medium text-white hover:bg-elo-green"><i class="ph ph-floppy-disk" aria-hidden="true"></i><?php echo !empty($metaEdicao['idMetaOrcamentaria']) ? 'Salvar alterações' : 'Salvar orçamento do mês'; ?></button>
                        <?php if (!empty($metaEdicao['idMetaOrcamentaria'])): ?><a href="metas-orcamentarias.php?ano=<?php echo $selectedYear; ?>&modo=<?php echo appEscape($mode); ?>#pesquisa" class="inline-flex items-center gap-2 rounded border px-4 py-2 text-gray-700 hover:bg-gray-50">Cancelar</a><?php endif; ?>
                    </div>
                </form>
            </section>

            <section id="painel-pesquisa" role="tabpanel" aria-labelledby="tab-pesquisa" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'pesquisa' ? '' : 'hidden'; ?>>
                <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">Acompanhamento mensal</h2>
                        <p class="mt-1 text-sm text-gray-500">O gasto considera somente categorias de Saída; entradas não alteram o saldo do orçamento.</p>
                    </div>
                    <form method="get" class="flex flex-wrap items-end gap-3">
                        <input type="hidden" name="tabToken" value="<?php echo appEscape((string) appCurrentTabToken()); ?>">
                        <label class="text-sm font-medium text-gray-700">Ano
                            <select name="ano" class="mt-1 block rounded border border-gray-300 bg-white px-3 py-2">
                                <?php foreach ($years as $year): ?><option value="<?php echo $year; ?>" <?php echo $year === $selectedYear ? 'selected' : ''; ?>><?php echo $year; ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <fieldset class="text-sm">
                            <legend class="mb-1 font-medium text-gray-700">Calcular gasto</legend>
                            <label class="mr-3 inline-flex items-center gap-1.5"><input type="radio" name="modo" value="efetivados" <?php echo $mode === 'efetivados' ? 'checked' : ''; ?>> Efetivados</label>
                            <label class="inline-flex items-center gap-1.5"><input type="radio" name="modo" value="pendentes" <?php echo $mode === 'pendentes' ? 'checked' : ''; ?>> Incluir pendentes</label>
                        </fieldset>
                        <button type="submit" class="rounded border border-elo-teal px-3 py-2 text-sm font-medium text-elo-teal hover:bg-elo-teal/5">Atualizar</button>
                        <button type="button" data-tab-open="manutencao" class="rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">+ Definir orçamento</button>
                    </form>
                </div>
                <?php if (!$goalsByMonth): ?>
                    <div class="mb-4 rounded border border-dashed border-elo-teal/40 bg-elo-teal/5 p-4 text-sm text-gray-700">
                        Ainda não há valores orçados para <?php echo $selectedYear; ?>. O controle é opcional; cadastre um mês apenas se a instituição quiser acompanhá-lo.
                    </div>
                <?php endif; ?>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[700px] text-left text-sm">
                        <thead class="border-b bg-gray-50 text-gray-600"><tr><th class="p-3">Mês</th><th class="p-3 text-right">Orçamento</th><th class="p-3 text-right">Gasto</th><th class="p-3 text-right">Saldo do orçamento</th><th class="p-3">Ações</th></tr></thead>
                        <tbody>
                        <?php for ($month = 1; $month <= 12; $month++):
                            $goal = $goalsByMonth[$month] ?? null;
                            $spent = $spendingByMonth[$month] ?? 0;
                            $balance = $goal !== null ? $converterCentavos($goal['valorOrcado']) - $spent : null;
                        ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-3 font-medium text-gray-800"><?php echo appEscape($meses[$month]); ?></td>
                                <td class="whitespace-nowrap p-3 text-right"><?php echo $goal !== null ? appEscape($formatarMoeda($goal['valorOrcado'])) : '<span class="text-gray-400">Não definido</span>'; ?></td>
                                <td class="whitespace-nowrap p-3 text-right">
                                    <?php echo appEscape($formatarCentavos($spent)); ?>
                                    <button type="button" data-budget-detail data-month="<?php echo $month; ?>" title="Detalhar gastos por categoria de <?php echo appEscape($meses[$month]); ?>" aria-label="Detalhar gastos por categoria de <?php echo appEscape($meses[$month]); ?>" class="ml-1 inline-flex h-7 w-7 items-center justify-center rounded-full text-elo-teal hover:bg-elo-teal/10"><i class="ph ph-info text-lg" aria-hidden="true"></i></button>
                                </td>
                                <td class="whitespace-nowrap p-3 text-right font-semibold <?php echo $balance !== null && $balance < 0 ? 'text-red-600' : 'text-gray-800'; ?>">
                                    <?php echo $balance !== null ? appEscape($formatarCentavos($balance)) : '—'; ?>
                                </td>
                                <td class="whitespace-nowrap p-3"><div class="flex items-center gap-2">
                                    <?php if ($goal !== null): ?>
                                        <a href="?editar=<?php echo (int) $goal['idMetaOrcamentaria']; ?>&modo=<?php echo appEscape($mode); ?>#manutencao" aria-label="Editar orçamento de <?php echo appEscape($meses[$month]); ?>" class="inline-flex h-8 w-8 items-center justify-center rounded border border-elo-teal/20 text-elo-teal hover:bg-elo-teal/10"><i class="ph ph-pencil-simple" aria-hidden="true"></i></a>
                                        <form method="post" data-confirm="Confirma excluir o orçamento de <?php echo appEscape($meses[$month]); ?>?"><input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="idMetaOrcamentaria" value="<?php echo (int) $goal['idMetaOrcamentaria']; ?>"><input type="hidden" name="ano" value="<?php echo $selectedYear; ?>"><button type="submit" aria-label="Excluir orçamento de <?php echo appEscape($meses[$month]); ?>" class="inline-flex h-8 w-8 items-center justify-center rounded border border-red-200 text-red-600 hover:bg-red-50"><i class="ph ph-trash" aria-hidden="true"></i></button></form>
                                    <?php else: ?>
                                        <a href="?novo=1&ano=<?php echo $selectedYear; ?>&mes=<?php echo $month; ?>#manutencao" aria-label="Definir orçamento de <?php echo appEscape($meses[$month]); ?>" class="inline-flex h-8 w-8 items-center justify-center rounded border border-elo-teal/20 text-elo-teal hover:bg-elo-teal/10"><i class="ph ph-plus" aria-hidden="true"></i></a>
                                    <?php endif; ?>
                                </div></td>
                            </tr>
                        <?php endfor; ?>
                        </tbody>
                        <tfoot class="border-t bg-gray-50 font-semibold text-gray-800">
                            <tr><td class="p-3">Total do ano</td><td class="whitespace-nowrap p-3 text-right"><?php echo appEscape($formatarCentavos($totalBudget)); ?></td><td class="whitespace-nowrap p-3 text-right"><?php echo appEscape($formatarCentavos($totalSpent)); ?></td><td class="whitespace-nowrap p-3 text-right <?php echo $totalBalance < 0 ? 'text-red-600' : ''; ?>"><?php echo appEscape($formatarCentavos($totalBalance)); ?></td><td class="p-3"></td></tr>
                        </tfoot>
                    </table>
                </div>
                <p class="mt-3 text-xs text-gray-500"><?php echo $mode === 'pendentes' ? 'O gasto inclui lançamentos pendentes e efetivados.' : 'O gasto considera somente lançamentos efetivados.'; ?> Valores negativos no saldo indicam que o gasto ultrapassou o orçamento informado; nenhum lançamento é bloqueado.</p>
            </section>
        </main>
    </div>
    <dialog data-budget-dialog class="w-[min(900px,calc(100%-2rem))] max-h-[85vh] overflow-y-auto rounded-lg p-0 shadow-xl backdrop:bg-black/50">
        <div class="border-b border-gray-200 p-5">
            <div class="flex items-start justify-between gap-4">
                <div><h2 data-budget-dialog-title class="text-lg font-semibold text-gray-800">Detalhamento</h2><p data-budget-dialog-message class="mt-1 text-sm text-gray-500">Gastos por categoria nesta competência.</p></div>
                <button type="button" data-budget-dialog-close aria-label="Fechar detalhamento" class="rounded p-2 text-gray-500 hover:bg-gray-100"><i class="ph ph-x text-xl" aria-hidden="true"></i></button>
            </div>
        </div>
        <div data-budget-dialog-content class="space-y-4 p-5"></div>
    </dialog>
    <?php $toastSuccess = $resultado; $toastError = $erro; include __DIR__ . '/../../../components/toast.php'; ?>
    <script src="<?php echo appEscape(appBaseUrl() . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
    <script>
        (() => {
            const dialog = document.querySelector('[data-budget-dialog]');
            const title = dialog.querySelector('[data-budget-dialog-title]');
            const message = dialog.querySelector('[data-budget-dialog-message]');
            const content = dialog.querySelector('[data-budget-dialog-content]');
            const months = <?php echo json_encode($meses, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); ?>;
            const csrfToken = <?php echo json_encode(appCsrfToken(), JSON_THROW_ON_ERROR); ?>;
            const tabToken = <?php echo json_encode((string) appCurrentTabToken(), JSON_THROW_ON_ERROR); ?>;
            const year = <?php echo $selectedYear; ?>;
            const mode = <?php echo json_encode($mode, JSON_THROW_ON_ERROR); ?>;
            const formatMoney = (value) => {
                const numeric = Number(String(value).replace(/[^\d,-]/g, '').replace(/\./g, '').replace(',', '.'));
                return Number.isFinite(numeric) ? new Intl.NumberFormat('pt-BR', {style: 'currency', currency: 'BRL'}).format(numeric) : String(value);
            };
            const addText = (parent, tag, className, value) => {
                const element = document.createElement(tag);
                element.className = className;
                element.textContent = value;
                parent.append(element);
                return element;
            };
            const renderDetails = (categories) => {
                content.replaceChildren();
                if (!categories.length) {
                    addText(content, 'p', 'rounded bg-gray-50 p-4 text-sm text-gray-500', 'Nenhuma saída encontrada para esta competência e forma de cálculo.');
                    return;
                }
                categories.forEach((category) => {
                    const section = document.createElement('section');
                    section.className = 'overflow-hidden rounded border border-gray-200';
                    const heading = document.createElement('div');
                    heading.className = 'flex justify-between gap-3 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-800';
                    addText(heading, 'span', '', category.nome);
                    addText(heading, 'span', 'whitespace-nowrap', category.total);
                    section.append(heading);
                    const list = document.createElement('div');
                    list.className = 'divide-y divide-gray-100';
                    category.lancamentos.forEach((item) => {
                        const row = document.createElement('div');
                        row.className = 'grid gap-1 px-4 py-3 text-sm sm:grid-cols-[6rem_minmax(0,1fr)_auto] sm:items-center';
                        addText(row, 'span', 'text-gray-500', item.data);
                        const description = item.pessoa ? `${item.descricao} · ${item.pessoa}` : item.descricao;
                        addText(row, 'span', 'text-gray-700', `${description} (${item.status})`);
                        addText(row, 'span', 'whitespace-nowrap text-right font-medium text-gray-800', item.valor);
                        list.append(row);
                    });
                    section.append(list);
                    content.append(section);
                });
            };
            document.querySelectorAll('[data-budget-detail]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const month = Number(button.dataset.month);
                    title.textContent = `Detalhamento de ${months[month]} ${year}`;
                    message.textContent = mode === 'pendentes'
                        ? 'Saídas pendentes e efetivadas agrupadas por categoria.'
                        : 'Saídas efetivadas agrupadas por categoria.';
                    content.replaceChildren();
                    addText(content, 'p', 'text-sm text-gray-500', 'Carregando detalhamento...');
                    dialog.showModal();
                    const formData = new FormData();
                    formData.set('csrfToken', csrfToken);
                    formData.set('tabToken', tabToken);
                    formData.set('acao', 'detalharCompetencia');
                    formData.set('ano', String(year));
                    formData.set('mes', String(month));
                    formData.set('modo', mode);
                    try {
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            body: formData,
                            headers: {'X-Requested-With': 'XMLHttpRequest'},
                            credentials: 'same-origin'
                        });
                        const result = await response.json();
                        if (!response.ok) throw new Error(result.message || 'Não foi possível carregar o detalhamento.');
                        renderDetails(result.categorias);
                    } catch (error) {
                        content.replaceChildren();
                        addText(content, 'p', 'rounded bg-red-50 p-4 text-sm text-red-700', error.message || 'Falha ao carregar o detalhamento.');
                    }
                });
            });
            dialog.querySelector('[data-budget-dialog-close]').addEventListener('click', () => dialog.close());
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) dialog.close();
            });
            const monthInput = new URLSearchParams(window.location.search).get('mes');
            if (new URLSearchParams(window.location.search).has('novo') && monthInput) {
                document.querySelector('[data-tab-open="manutencao"]')?.click();
                const form = document.querySelector('#painel-manutencao form');
                form.elements.namedItem('ano').value = String(year);
                form.elements.namedItem('mes').value = monthInput;
            }
            document.querySelectorAll('input[name="modo"], select[name="ano"]').forEach((input) => {
                input.addEventListener('change', () => input.form.requestSubmit());
            });
        })();
    </script>
</body>
</html>