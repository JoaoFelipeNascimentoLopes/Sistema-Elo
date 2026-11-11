<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('financeiro.relatorios/resumo-metas-orcamentarias');
$pdo = appPdo();

$meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
];
$formatarCentavos = static function (int $centavos): string {
    $negativo = $centavos < 0;
    $centavos = abs($centavos);
    $reais = (string) intdiv($centavos, 100);
    $reais = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $reais) ?? $reais;
    return 'R$ ' . ($negativo ? '-' : '') . $reais . ',' . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
};
$converterCentavos = static function (mixed $value): int {
    $value = (string) $value;
    if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $parts)) {
        throw new UnexpectedValueException('Valor monetário inválido retornado pelo banco.');
    }
    return ((int) $parts[1] * 100) + (int) str_pad($parts[2] ?? '', 2, '0');
};
$validarAno = static function (mixed $value): int|false {
    if (!is_scalar($value)) {
        return false;
    }
    $year = filter_var($value, FILTER_VALIDATE_INT);
    return $year !== false && $year >= 2000 && $year <= 9998 ? $year : false;
};

$gerarRelatorio = is_scalar($_GET['gerar'] ?? null) && (string) $_GET['gerar'] === '1';
$requestedYear = $validarAno($_GET['ano'] ?? date('Y'));
$requestedMode = is_scalar($_GET['modo'] ?? null) ? (string) $_GET['modo'] : 'efetivados';
$erro = '';
if ($requestedYear === false || !in_array($requestedMode, ['efetivados', 'pendentes'], true)) {
    if ($gerarRelatorio) {
        $erro = 'Confira o ano e a situação selecionada.';
    }
    $requestedYear = (int) date('Y');
    $requestedMode = 'efetivados';
}
$year = $requestedYear;
$start = sprintf('%04d-01-01', $year);
$end = sprintf('%04d-01-01', $year + 1);
$statusLabel = $requestedMode === 'pendentes' ? 'Efetivados e pendentes' : 'Somente efetivados';
$statuses = $requestedMode === 'pendentes' ? ['Efetivado', 'Pendente'] : ['Efetivado'];
$statusPlaceholders = implode(', ', array_fill(0, count($statuses), '?'));

$metasPorMes = [];
$gastosPorMes = array_fill(1, 12, 0);
$detalhamentoCategorias = [];
$totalOrcado = 0;
$totalGastoAno = array_sum($gastosPorMes);
$saldoMesesOrcados = 0;
$mesesComMeta = 0;
if ($gerarRelatorio && $erro === '') {
    $goalStatement = $pdo->prepare(
        'SELECT mes, valorOrcado
         FROM tbMetaOrcamentaria
         WHERE ano = :ano
         ORDER BY mes'
    );
    $goalStatement->execute(['ano' => $year]);
    foreach ($goalStatement->fetchAll(PDO::FETCH_ASSOC) as $goal) {
        $metasPorMes[(int) $goal['mes']] = $converterCentavos($goal['valorOrcado']);
    }

    $spendingStatement = $pdo->prepare(
        'SELECT MONTH(l.dataLancamento) AS mes, c.idCategoria, c.nomeCategoria,
                SUM(l.valorLancamento) AS totalCategoria
         FROM tbLancamentoFinanceiro l
         INNER JOIN tbCategoriaLancamentos c ON c.idCategoria = l.idCategoria
         WHERE c.naturezaCategoria = \'Saída\'
           AND l.dataLancamento >= ? AND l.dataLancamento < ?
           AND l.statusLancamento IN (' . $statusPlaceholders . ')
         GROUP BY MONTH(l.dataLancamento), c.idCategoria, c.nomeCategoria
         ORDER BY MONTH(l.dataLancamento), c.nomeCategoria'
    );
    $spendingStatement->execute(array_merge([$start, $end], $statuses));
    foreach ($spendingStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $month = (int) $row['mes'];
        $amount = $converterCentavos($row['totalCategoria']);
        $gastosPorMes[$month] += $amount;
        $detalhamentoCategorias[] = [
            'mes' => $month,
            'categoria' => $row['nomeCategoria'],
            'total' => $amount,
        ];
    }

    $totalGastoAno = array_sum($gastosPorMes);
    foreach ($metasPorMes as $month => $goalAmount) {
        $totalOrcado += $goalAmount;
        $saldoMesesOrcados += $goalAmount - $gastosPorMes[$month];
        $mesesComMeta++;
    }
}
$periodoLabel = '01/01/' . $year . ' a 31/12/' . $year;
$baseUrl = appBaseUrl();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumo de Metas Orçamentárias</title>
    <link rel="icon" type="image/png" href="<?php echo appEscape($baseUrl . '/assets/imgs/logo-elo.png'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/tailwind-config.js'); ?>"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        @media print {
            html, body { width: 100%; min-height: 0; margin: 0 !important; padding: 0 !important; background: white !important; color: #111827 !important; font: 9pt Arial, sans-serif !important; }
            body > nav, body > div > aside, .no-print, .fixed { display: none !important; }
            body > div { display: block !important; min-height: 0 !important; overflow: visible !important; }
            main { display: block !important; width: 100% !important; min-width: 0 !important; height: auto !important; overflow: visible !important; padding: 0 !important; }
            .print-sheet { width: 100% !important; max-width: none !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; border: 0 !important; }
            .print-sheet > header { break-inside: avoid; }
            .print-sheet > header h2 { font-size: 17pt !important; }
            .print-sheet .grid { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; gap: 2mm !important; }
            .print-sheet .my-5 { margin-top: 3mm !important; margin-bottom: 4mm !important; }
            .print-sheet .p-3 { padding: 2mm !important; }
            .overflow-x-auto { overflow: visible !important; }
            .print-table { width: 100% !important; min-width: 0 !important; table-layout: fixed; border-collapse: collapse; }
            .print-table th, .print-table td { padding: 1.5mm !important; border-color: #9ca3af !important; overflow-wrap: anywhere; }
            .print-table th { background: #e5e7eb !important; color: #111827 !important; }
            .print-table thead { display: table-header-group; }
            .print-table tr { break-inside: avoid; page-break-inside: avoid; }
            section { break-inside: auto; }
            section h3 { break-after: avoid; }
            footer { margin-top: 8mm !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100 font-sans text-gray-800">
    <?php include __DIR__ . '/../../../components/financeiro/navbar-financeiro.php'; ?>
    <div class="flex min-h-[calc(100vh-4rem)]">
        <?php include __DIR__ . '/../../../components/financeiro/sidebar-financeiro.php'; ?>
        <main class="min-w-0 flex-1 overflow-y-auto p-4 md:p-6">
            <header class="no-print mb-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Resumo de Metas Orçamentárias</h1>
                    <p class="mt-1 text-sm text-gray-500">Demonstrativo anual de orçamento previsto e saídas lançadas.</p>
                </div>
                <?php if ($gerarRelatorio && $erro === ''): ?>
                    <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">
                        <i class="ph ph-printer text-lg" aria-hidden="true"></i> Imprimir resumo
                    </button>
                <?php endif; ?>
            </header>

            <form method="get" class="no-print mb-5 flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <input type="hidden" name="tabToken" value="<?php echo appEscape((string) appCurrentTabToken()); ?>">
                <label class="text-sm font-medium text-gray-700">Ano
                    <input type="number" name="ano" required min="2000" max="9998" value="<?php echo $year; ?>" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2">
                </label>
                <fieldset class="text-sm">
                    <legend class="mb-1 font-medium text-gray-700">Considerar lançamentos</legend>
                    <label class="mr-3 inline-flex items-center gap-1.5"><input type="radio" name="modo" value="efetivados" <?php echo $requestedMode === 'efetivados' ? 'checked' : ''; ?>> Efetivados</label>
                    <label class="inline-flex items-center gap-1.5"><input type="radio" name="modo" value="pendentes" <?php echo $requestedMode === 'pendentes' ? 'checked' : ''; ?>> Incluir pendentes</label>
                </fieldset>
                <button type="submit" name="gerar" value="1" class="rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">Gerar resumo</button>
            </form>

            <?php if ($erro !== ''): ?>
                <p role="alert" class="no-print mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700"><?php echo appEscape($erro); ?></p>
            <?php endif; ?>
            <?php if ($gerarRelatorio && $erro === '' && $mesesComMeta === 0): ?>
                <p class="no-print mb-4 rounded border border-elo-teal/30 bg-elo-teal/5 p-3 text-sm text-gray-700">Não há metas definidas para <?php echo $year; ?>. O orçamento é opcional; o demonstrativo ainda apresenta as saídas registradas.</p>
            <?php endif; ?>

            <?php if ($gerarRelatorio && $erro === ''): ?>
            <article class="print-sheet mx-auto max-w-5xl rounded-lg border border-gray-200 bg-white p-5 shadow-sm md:p-8">
                <header class="border-b-2 border-gray-800 pb-4">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Sistema Elo · Financeiro</p>
                            <h2 class="mt-1 text-2xl font-bold uppercase tracking-wide text-gray-900">Resumo de Metas Orçamentárias</h2>
                            <p class="mt-1 text-sm text-gray-600">Exercício: <strong><?php echo $year; ?></strong> · Período: <?php echo appEscape($periodoLabel); ?></p>
                        </div>
                        <div class="text-right text-xs text-gray-600">
                            <p>Emitido em <?php echo date('d/m/Y H:i'); ?></p>
                            <p>Responsável: <?php echo appEscape($usuarioAtual['nomeCompleto']); ?></p>
                            <p>Base de cálculo: <?php echo appEscape($statusLabel); ?></p>
                        </div>
                    </div>
                </header>

                <div class="my-5 grid gap-3 sm:grid-cols-3">
                    <div class="border border-gray-300 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Orçamento definido no ano</p>
                        <p class="mt-1 text-lg font-bold tabular-nums"><?php echo appEscape($formatarCentavos($totalOrcado)); ?></p>
                        <p class="mt-1 text-[10px] text-gray-500"><?php echo $mesesComMeta; ?> de 12 meses com meta</p>
                    </div>
                    <div class="border border-gray-300 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Saídas consideradas no ano</p>
                        <p class="mt-1 text-lg font-bold tabular-nums"><?php echo appEscape($formatarCentavos($totalGastoAno)); ?></p>
                    </div>
                    <div class="border border-gray-300 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Saldo das metas definidas</p>
                        <p class="mt-1 text-lg font-bold tabular-nums"><?php echo appEscape($formatarCentavos($saldoMesesOrcados)); ?></p>
                        <p class="mt-1 text-[10px] text-gray-500">Orçamento menos saídas nos meses com meta</p>
                    </div>
                </div>

                <section>
                    <h3 class="mb-2 text-sm font-bold uppercase tracking-wide">Acompanhamento mensal</h3>
                    <div class="overflow-x-auto">
                        <table class="print-table w-full border-collapse text-sm">
                            <thead>
                                <tr class="bg-gray-800 text-white">
                                    <th class="border border-gray-700 px-3 py-2 text-left">Competência</th>
                                    <th class="border border-gray-700 px-3 py-2 text-right">Orçamento</th>
                                    <th class="border border-gray-700 px-3 py-2 text-right">Saídas</th>
                                    <th class="border border-gray-700 px-3 py-2 text-right">Saldo do orçamento</th>
                                    <th class="border border-gray-700 px-3 py-2 text-center">Situação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($month = 1; $month <= 12; $month++): ?>
                                    <?php
                                    $goal = $metasPorMes[$month] ?? null;
                                    $spent = $gastosPorMes[$month];
                                    $balance = $goal !== null ? $goal - $spent : null;
                                    $situation = $goal === null ? 'Sem meta' : ($balance < 0 ? 'Acima do orçamento' : ($balance === 0 ? 'No limite' : 'Dentro do orçamento'));
                                    ?>
                                    <tr class="border-b border-gray-300 even:bg-gray-50">
                                        <td class="border-x border-gray-300 px-3 py-2 font-medium"><?php echo appEscape($meses[$month]); ?></td>
                                        <td class="whitespace-nowrap border-x border-gray-300 px-3 py-2 text-right tabular-nums"><?php echo $goal !== null ? appEscape($formatarCentavos($goal)) : 'Não definido'; ?></td>
                                        <td class="whitespace-nowrap border-x border-gray-300 px-3 py-2 text-right tabular-nums"><?php echo appEscape($formatarCentavos($spent)); ?></td>
                                        <td class="whitespace-nowrap border-x border-gray-300 px-3 py-2 text-right tabular-nums"><?php echo $balance !== null ? appEscape($formatarCentavos($balance)) : '—'; ?></td>
                                        <td class="border-x border-gray-300 px-3 py-2 text-center"><?php echo appEscape($situation); ?></td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-gray-800 bg-gray-100 font-bold">
                                    <td class="border border-gray-400 px-3 py-2">Total anual</td>
                                    <td class="whitespace-nowrap border border-gray-400 px-3 py-2 text-right tabular-nums"><?php echo appEscape($formatarCentavos($totalOrcado)); ?></td>
                                    <td class="whitespace-nowrap border border-gray-400 px-3 py-2 text-right tabular-nums"><?php echo appEscape($formatarCentavos($totalGastoAno)); ?></td>
                                    <td class="whitespace-nowrap border border-gray-400 px-3 py-2 text-right tabular-nums"><?php echo appEscape($formatarCentavos($saldoMesesOrcados)); ?></td>
                                    <td class="border border-gray-400 px-3 py-2 text-center"><?php echo $mesesComMeta; ?> meses orçados</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="mt-2 text-[10px] text-gray-500">O saldo anual soma somente os saldos dos meses com meta definida. As saídas dos meses sem meta são exibidas, mas não entram nessa comparação.</p>
                </section>

                <section class="mt-7">
                    <h3 class="mb-2 text-sm font-bold uppercase tracking-wide">Composição das saídas por categoria</h3>
                    <div class="overflow-x-auto">
                        <table class="print-table w-full border-collapse text-xs">
                            <thead>
                                <tr class="bg-gray-800 text-white">
                                    <th class="border border-gray-700 px-3 py-2 text-left">Competência</th>
                                    <th class="border border-gray-700 px-3 py-2 text-left">Categoria</th>
                                    <th class="border border-gray-700 px-3 py-2 text-right">Total de saídas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detalhamentoCategorias as $item): ?>
                                    <tr class="border-b border-gray-300 even:bg-gray-50">
                                        <td class="border-x border-gray-300 px-3 py-2"><?php echo appEscape($meses[$item['mes']]); ?></td>
                                        <td class="border-x border-gray-300 px-3 py-2"><?php echo appEscape($item['categoria']); ?></td>
                                        <td class="whitespace-nowrap border-x border-gray-300 px-3 py-2 text-right tabular-nums"><?php echo appEscape($formatarCentavos($item['total'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$detalhamentoCategorias): ?>
                                    <tr><td colspan="3" class="border border-gray-300 px-3 py-5 text-center text-gray-500">Nenhuma saída encontrada para o exercício e a situação selecionados.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <footer class="mt-12 grid grid-cols-2 gap-10 text-center text-xs">
                    <div class="border-t border-gray-500 pt-2">Responsável pela emissão</div>
                    <div class="border-t border-gray-500 pt-2">Conferência / assinatura</div>
                </footer>
            </article>
            <?php endif; ?>
        </main>
    </div>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
</body>
</html>