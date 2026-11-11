<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('financeiro.relatorios/extrato-lancamentos');
$pdo = appPdo();

$validarData = static function (mixed $value): bool {
    if (!is_string($value)) {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
};
$formatarCentavos = static function (int $centavos): string {
    $negativo = $centavos < 0;
    $centavos = abs($centavos);
    $reais = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', (string) intdiv($centavos, 100)) ?? (string) intdiv($centavos, 100);
    return 'R$ ' . ($negativo ? '-' : '') . $reais . ',' . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
};
$converterCentavos = static function (mixed $value): int {
    $value = (string) $value;
    if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $parts)) {
        throw new UnexpectedValueException('Valor monetário inválido retornado pelo banco.');
    }
    return ((int) $parts[1] * 100) + (int) str_pad($parts[2] ?? '', 2, '0');
};

$today = new DateTimeImmutable('today');
$defaultStart = $today->modify('first day of this month')->format('Y-m-d');
$gerarRelatorio = is_scalar($_GET['gerar'] ?? null) && (string) $_GET['gerar'] === '1';
$filters = [
    'inicio' => is_scalar($_GET['inicio'] ?? null) ? (string) $_GET['inicio'] : $defaultStart,
    'fim' => is_scalar($_GET['fim'] ?? null) ? (string) $_GET['fim'] : $today->format('Y-m-d'),
    'natureza' => is_scalar($_GET['natureza'] ?? null) ? (string) $_GET['natureza'] : 'Todas',
    'situacao' => is_scalar($_GET['situacao'] ?? null) ? (string) $_GET['situacao'] : 'Todos',
];
$erro = '';
if (!$validarData($filters['inicio']) || !$validarData($filters['fim'])
    || $filters['inicio'] > $filters['fim']
    || !in_array($filters['natureza'], ['Todas', 'Entrada', 'Saída'], true)
    || !in_array($filters['situacao'], ['Todos', 'Efetivado', 'Pendente'], true)) {
    if ($gerarRelatorio) {
        $erro = 'Confira o período e os filtros selecionados.';
    }
    $filters = ['inicio' => $defaultStart, 'fim' => $today->format('Y-m-d'), 'natureza' => 'Todas', 'situacao' => 'Todos'];
}

$lancamentos = [];
$totalEntradas = 0;
$totalSaidas = 0;
$movimentoLiquido = 0;
if ($gerarRelatorio && $erro === '') {
    $where = ['l.dataLancamento >= :inicio', 'l.dataLancamento <= :fim'];
    $params = ['inicio' => $filters['inicio'], 'fim' => $filters['fim']];
    if ($filters['natureza'] !== 'Todas') {
        $where[] = 'c.naturezaCategoria = :natureza';
        $params['natureza'] = $filters['natureza'];
    }
    if ($filters['situacao'] !== 'Todos') {
        $where[] = 'l.statusLancamento = :situacao';
        $params['situacao'] = $filters['situacao'];
    }
    $statement = $pdo->prepare(
        'SELECT l.idLancamento, l.dataLancamento, l.descricaoLancamento, l.valorLancamento,
                l.statusLancamento, l.observacoes, c.idCategoria, c.nomeCategoria, c.naturezaCategoria,
                f.nomeFonte, p.nomeFormaPagamento,
                COALESCE(fp.nomeRazaoSocial, dc.nomeRazaoSocial) AS contraparte
         FROM tbLancamentoFinanceiro l
         INNER JOIN tbCategoriaLancamentos c ON c.idCategoria = l.idCategoria
         LEFT JOIN tbFonteRecurso f ON f.idFonte = l.idFonte
         LEFT JOIN tbFormaPagamento p ON p.idFormaPagamento = l.idFormaPagamento
         LEFT JOIN tbFornecedorPrestador fp ON fp.idFornecedorPrestador = l.idFornecedorPrestador
         LEFT JOIN tbDoadorContribuinte dc ON dc.idDoadorContribuinte = l.idDoadorContribuinte
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY l.dataLancamento, l.idLancamento'
    );
    $statement->execute($params);
    $lancamentos = $statement->fetchAll(PDO::FETCH_ASSOC);
    foreach ($lancamentos as &$lancamento) {
        $lancamento['centavos'] = $converterCentavos($lancamento['valorLancamento']);
        if ($lancamento['naturezaCategoria'] === 'Entrada') {
            $totalEntradas += $lancamento['centavos'];
        } else {
            $totalSaidas += $lancamento['centavos'];
        }
    }
    unset($lancamento);
    $movimentoLiquido = $totalEntradas - $totalSaidas;
}
$periodoLabel = date('d/m/Y', strtotime($filters['inicio'])) . ' a ' . date('d/m/Y', strtotime($filters['fim']));
$baseUrl = appBaseUrl();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extrato de Lançamentos Financeiros</title>
    <link rel="icon" type="image/png" href="<?php echo appEscape($baseUrl . '/assets/imgs/logo-elo.png'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/tailwind-config.js'); ?>"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        @page { size: A4 landscape; margin: 8mm; }
        @media print {
            html, body { width: 100%; min-height: 0; margin: 0 !important; padding: 0 !important; background: white !important; color: #111827 !important; font: 8pt Arial, sans-serif !important; }
            body > nav, body > div > aside, .no-print, .fixed { display: none !important; }
            body > div { display: block !important; min-height: 0 !important; overflow: visible !important; }
            main { display: block !important; width: 100% !important; min-width: 0 !important; height: auto !important; overflow: visible !important; padding: 0 !important; }
            .print-sheet { width: 100% !important; max-width: none !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; border: 0 !important; }
            .print-sheet > header { break-inside: avoid; }
            .print-sheet > header h2 { font-size: 17pt !important; }
            .print-sheet > header > div { gap: 3mm !important; }
            .print-sheet .grid { gap: 2mm !important; }
            .print-sheet .my-5 { margin-top: 3mm !important; margin-bottom: 3mm !important; }
            .print-sheet .p-3 { padding: 2mm !important; }
            .overflow-x-auto { overflow: visible !important; }
            .print-table { width: 100% !important; min-width: 0 !important; table-layout: fixed; border-collapse: collapse; font-size: 7pt !important; }
            .print-table th, .print-table td { padding: 1.2mm !important; border-color: #9ca3af !important; overflow-wrap: anywhere; word-break: normal; }
            .print-table th { background: #e5e7eb !important; color: #111827 !important; }
            .print-table thead { display: table-header-group; }
            .print-table tr { break-inside: avoid; page-break-inside: avoid; }
            .print-table th:nth-child(1), .print-table td:nth-child(1) { width: 7%; }
            .print-table th:nth-child(2), .print-table td:nth-child(2) { width: 4%; }
            .print-table th:nth-child(3), .print-table td:nth-child(3) { width: 19%; }
            .print-table th:nth-child(4), .print-table td:nth-child(4) { width: 13%; }
            .print-table th:nth-child(5), .print-table td:nth-child(5) { width: 18%; }
            .print-table th:nth-child(6), .print-table td:nth-child(6) { width: 12%; }
            .print-table th:nth-child(7), .print-table td:nth-child(7) { width: 8%; }
            .print-table th:nth-child(8), .print-table td:nth-child(8),
            .print-table th:nth-child(9), .print-table td:nth-child(9) { width: 9.5%; }
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
                    <h1 class="text-2xl font-bold text-gray-800">Extrato de Lançamentos</h1>
                    <p class="mt-1 text-sm text-gray-500">Folha de movimentação financeira por período, em formato de extrato contábil.</p>
                </div>
                <?php if ($gerarRelatorio && $erro === ''): ?>
                    <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">
                        <i class="ph ph-printer text-lg" aria-hidden="true"></i> Imprimir extrato
                    </button>
                <?php endif; ?>
            </header>

            <form method="get" class="no-print mb-5 grid gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
                <input type="hidden" name="tabToken" value="<?php echo appEscape((string) appCurrentTabToken()); ?>">
                <label class="text-sm font-medium text-gray-700">Data inicial
                    <input type="date" name="inicio" required value="<?php echo appEscape($filters['inicio']); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                </label>
                <label class="text-sm font-medium text-gray-700">Data final
                    <input type="date" name="fim" required value="<?php echo appEscape($filters['fim']); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                </label>
                <label class="text-sm font-medium text-gray-700">Natureza
                    <select name="natureza" class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                        <?php foreach (['Todas', 'Entrada', 'Saída'] as $option): ?>
                            <option value="<?php echo appEscape($option); ?>" <?php echo $filters['natureza'] === $option ? 'selected' : ''; ?>><?php echo appEscape($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="text-sm font-medium text-gray-700">Situação
                    <select name="situacao" class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                        <?php foreach (['Todos', 'Efetivado', 'Pendente'] as $option): ?>
                            <option value="<?php echo appEscape($option); ?>" <?php echo $filters['situacao'] === $option ? 'selected' : ''; ?>><?php echo appEscape($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" name="gerar" value="1" class="rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">Gerar extrato</button>
            </form>

            <?php if ($erro !== ''): ?>
                <p role="alert" class="no-print mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700"><?php echo appEscape($erro); ?></p>
            <?php endif; ?>

            <?php if ($gerarRelatorio && $erro === ''): ?>
            <article class="print-sheet mx-auto max-w-[1500px] rounded-lg border border-gray-200 bg-white p-5 shadow-sm md:p-8">
                <header class="border-b-2 border-gray-800 pb-4">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Sistema Elo · Financeiro</p>
                            <h2 class="mt-1 text-2xl font-bold uppercase tracking-wide text-gray-900">Extrato de Lançamentos</h2>
                            <p class="mt-1 text-sm text-gray-600">Período: <strong><?php echo appEscape($periodoLabel); ?></strong></p>
                        </div>
                        <div class="text-right text-xs text-gray-600">
                            <p>Emitido em <?php echo date('d/m/Y H:i'); ?></p>
                            <p>Responsável: <?php echo appEscape($usuarioAtual['nomeCompleto']); ?></p>
                            <p>Registros: <?php echo count($lancamentos); ?></p>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-2 text-xs sm:grid-cols-3">
                        <p><span class="font-semibold uppercase tracking-wide text-gray-500">Natureza</span><br><?php echo appEscape($filters['natureza']); ?></p>
                        <p><span class="font-semibold uppercase tracking-wide text-gray-500">Situação considerada</span><br><?php echo appEscape($filters['situacao']); ?></p>
                        <p><span class="font-semibold uppercase tracking-wide text-gray-500">Critério</span><br>Data do lançamento</p>
                    </div>
                </header>

                <div class="my-5 grid gap-3 sm:grid-cols-3">
                    <div class="border border-gray-300 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total de entradas</p>
                        <p class="mt-1 text-lg font-bold tabular-nums"><?php echo appEscape($formatarCentavos($totalEntradas)); ?></p>
                    </div>
                    <div class="border border-gray-300 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total de saídas</p>
                        <p class="mt-1 text-lg font-bold tabular-nums"><?php echo appEscape($formatarCentavos($totalSaidas)); ?></p>
                    </div>
                    <div class="border border-gray-300 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Movimento líquido do período</p>
                        <p class="mt-1 text-lg font-bold tabular-nums"><?php echo appEscape($formatarCentavos($movimentoLiquido)); ?></p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="print-table w-full border-collapse text-left text-xs">
                        <thead>
                            <tr class="bg-gray-800 text-white">
                                <th class="border border-gray-700 px-2 py-2">Data</th>
                                <th class="border border-gray-700 px-2 py-2">Nº</th>
                                <th class="border border-gray-700 px-2 py-2">Histórico / descrição</th>
                                <th class="border border-gray-700 px-2 py-2">Conta / categoria</th>
                                <th class="border border-gray-700 px-2 py-2">Empresa / pessoa</th>
                                <th class="border border-gray-700 px-2 py-2">Fonte / forma</th>
                                <th class="border border-gray-700 px-2 py-2 text-center">Situação</th>
                                <th class="border border-gray-700 px-2 py-2 text-right">Entrada</th>
                                <th class="border border-gray-700 px-2 py-2 text-right">Saída</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lancamentos as $lancamento): ?>
                                <?php $isEntrada = $lancamento['naturezaCategoria'] === 'Entrada'; ?>
                                <tr class="border-b border-gray-300 even:bg-gray-50">
                                    <td class="whitespace-nowrap border-x border-gray-300 px-2 py-2"><?php echo appEscape(date('d/m/Y', strtotime($lancamento['dataLancamento']))); ?></td>
                                    <td class="border-x border-gray-300 px-2 py-2"><?php echo (int) $lancamento['idLancamento']; ?></td>
                                    <td class="border-x border-gray-300 px-2 py-2">
                                        <span class="font-semibold"><?php echo appEscape($lancamento['descricaoLancamento']); ?></span>
                                        <?php if (!empty($lancamento['observacoes'])): ?><span class="mt-0.5 block text-[10px] text-gray-500"><?php echo appEscape($lancamento['observacoes']); ?></span><?php endif; ?>
                                    </td>
                                    <td class="border-x border-gray-300 px-2 py-2"><?php echo appEscape($lancamento['nomeCategoria']); ?></td>
                                    <td class="border-x border-gray-300 px-2 py-2"><?php echo appEscape((string) ($lancamento['contraparte'] ?? '—')); ?></td>
                                    <td class="border-x border-gray-300 px-2 py-2">
                                        <?php echo appEscape((string) ($lancamento['nomeFonte'] ?? '—')); ?>
                                        <?php if (!empty($lancamento['nomeFormaPagamento'])): ?><span class="block text-[10px] text-gray-500"><?php echo appEscape($lancamento['nomeFormaPagamento']); ?></span><?php endif; ?>
                                    </td>
                                    <td class="border-x border-gray-300 px-2 py-2 text-center"><?php echo appEscape($lancamento['statusLancamento']); ?></td>
                                    <td class="whitespace-nowrap border-x border-gray-300 px-2 py-2 text-right tabular-nums"><?php echo $isEntrada ? appEscape($formatarCentavos($lancamento['centavos'])) : '—'; ?></td>
                                    <td class="whitespace-nowrap border-x border-gray-300 px-2 py-2 text-right tabular-nums"><?php echo !$isEntrada ? appEscape($formatarCentavos($lancamento['centavos'])) : '—'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$lancamentos): ?>
                                <tr><td colspan="9" class="border border-gray-300 px-3 py-8 text-center text-gray-500">Nenhum lançamento encontrado para os filtros selecionados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-800 bg-gray-100 font-bold">
                                <td colspan="7" class="border border-gray-400 px-2 py-2 text-right uppercase">Totais do período</td>
                                <td class="whitespace-nowrap border border-gray-400 px-2 py-2 text-right tabular-nums"><?php echo appEscape($formatarCentavos($totalEntradas)); ?></td>
                                <td class="whitespace-nowrap border border-gray-400 px-2 py-2 text-right tabular-nums"><?php echo appEscape($formatarCentavos($totalSaidas)); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="mt-3 text-[10px] text-gray-500">O movimento líquido corresponde à diferença entre entradas e saídas listadas. Não representa saldo bancário nem inclui saldo anterior.</p>
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
