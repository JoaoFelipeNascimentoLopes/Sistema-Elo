<?php
require_once __DIR__ . '/../../auth/access.php';
$usuarioAtual = appRequirePage('financeiro.dashboard-financeiro');
$pdo = appPdo();

$meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
];
$formatarMoeda = static function (float $valor): string {
    return 'R$ ' . number_format($valor, 2, ',', '.');
};
$inicioMesAtual = new DateTimeImmutable('first day of this month');
$inicioProximoMes = $inicioMesAtual->modify('+1 month');
$inicioHistorico = $inicioMesAtual->modify('-5 months');
$inicioMesSql = $inicioMesAtual->format('Y-m-d');
$proximoMesSql = $inicioProximoMes->format('Y-m-d');

$resumoStatement = $pdo->prepare(
    'SELECT c.naturezaCategoria, l.statusLancamento,
            COUNT(*) AS quantidade, COALESCE(SUM(l.valorLancamento), 0) AS total
     FROM tbLancamentoFinanceiro l
     INNER JOIN tbCategoriaLancamentos c ON c.idCategoria = l.idCategoria
     WHERE l.dataLancamento >= :inicio AND l.dataLancamento < :fim
     GROUP BY c.naturezaCategoria, l.statusLancamento'
);
$resumoStatement->execute(['inicio' => $inicioMesSql, 'fim' => $proximoMesSql]);
$entradasMes = 0.0;
$saidasMes = 0.0;
$entradasPendentesMes = 0.0;
$saidasPendentesMes = 0.0;
$pendenciasTotal = 0;
$quantidadeMes = 0;
foreach ($resumoStatement->fetchAll(PDO::FETCH_ASSOC) as $linha) {
    $total = (float) $linha['total'];
    $quantidade = (int) $linha['quantidade'];
    $quantidadeMes += $quantidade;
    if ($linha['statusLancamento'] === 'Pendente') {
        $pendenciasTotal += $quantidade;
        if ($linha['naturezaCategoria'] === 'Entrada') {
            $entradasPendentesMes += $total;
        } else {
            $saidasPendentesMes += $total;
        }
    } elseif ($linha['naturezaCategoria'] === 'Entrada') {
        $entradasMes += $total;
    } else {
        $saidasMes += $total;
    }
}
$saldoMes = $entradasMes - $saidasMes;

$saldoStatement = $pdo->query(
    "SELECT COALESCE(SUM(CASE WHEN c.naturezaCategoria = 'Entrada'
                              THEN l.valorLancamento ELSE -l.valorLancamento END), 0)
     FROM tbLancamentoFinanceiro l
     INNER JOIN tbCategoriaLancamentos c ON c.idCategoria = l.idCategoria
     WHERE l.statusLancamento = 'Efetivado'"
);
$saldoAcumulado = (float) $saldoStatement->fetchColumn();

$historicoStatement = $pdo->prepare(
    'SELECT DATE_FORMAT(l.dataLancamento, \'%Y-%m\') AS competencia,
            c.naturezaCategoria, COALESCE(SUM(l.valorLancamento), 0) AS total
     FROM tbLancamentoFinanceiro l
     INNER JOIN tbCategoriaLancamentos c ON c.idCategoria = l.idCategoria
     WHERE l.statusLancamento = \'Efetivado\'
       AND l.dataLancamento >= :inicio AND l.dataLancamento < :fim
     GROUP BY DATE_FORMAT(l.dataLancamento, \'%Y-%m\'), c.naturezaCategoria
     ORDER BY competencia'
);
$historicoStatement->execute([
    'inicio' => $inicioHistorico->format('Y-m-d'),
    'fim' => $proximoMesSql,
]);
$historicoPorMes = [];
foreach ($historicoStatement->fetchAll(PDO::FETCH_ASSOC) as $linha) {
    $historicoPorMes[$linha['competencia']][$linha['naturezaCategoria']] = (float) $linha['total'];
}
$historico = [];
$maximoHistorico = 0.0;
for ($mes = $inicioHistorico; $mes < $inicioProximoMes; $mes = $mes->modify('+1 month')) {
    $competencia = $mes->format('Y-m');
    $entrada = $historicoPorMes[$competencia]['Entrada'] ?? 0.0;
    $saida = $historicoPorMes[$competencia]['Saída'] ?? 0.0;
    $historico[] = [
        'label' => ucfirst(substr($meses[(int) $mes->format('n')], 0, 3)),
        'entrada' => $entrada,
        'saida' => $saida,
    ];
    $maximoHistorico = max($maximoHistorico, $entrada, $saida);
}

$categoriasStatement = $pdo->prepare(
    'SELECT c.nomeCategoria, COALESCE(SUM(l.valorLancamento), 0) AS total
     FROM tbLancamentoFinanceiro l
     INNER JOIN tbCategoriaLancamentos c ON c.idCategoria = l.idCategoria
     WHERE c.naturezaCategoria = \'Saída\'
       AND l.statusLancamento = \'Efetivado\'
       AND l.dataLancamento >= :inicio AND l.dataLancamento < :fim
     GROUP BY c.idCategoria, c.nomeCategoria
     ORDER BY total DESC, c.nomeCategoria'
);
$categoriasStatement->execute(['inicio' => $inicioMesSql, 'fim' => $proximoMesSql]);
$categorias = $categoriasStatement->fetchAll(PDO::FETCH_ASSOC);
$totalCategorias = array_sum(array_map(static fn (array $linha): float => (float) $linha['total'], $categorias));
$coresGrafico = ['#0f766e', '#14b8a6', '#0ea5e9', '#6366f1', '#f59e0b', '#f97316'];
$segmentosGrafico = [];
$anguloGrafico = 0.0;
foreach ($categorias as $indice => $categoria) {
    $percentual = $totalCategorias > 0 ? (float) $categoria['total'] / $totalCategorias * 100 : 0;
    $proximoAngulo = $anguloGrafico + $percentual * 3.6;
    $segmentosGrafico[] = $coresGrafico[$indice % count($coresGrafico)] . ' ' . $anguloGrafico . 'deg ' . $proximoAngulo . 'deg';
    $categorias[$indice]['percentual'] = $percentual;
    $categorias[$indice]['cor'] = $coresGrafico[$indice % count($coresGrafico)];
    $anguloGrafico = $proximoAngulo;
}
$graficoCategorias = $segmentosGrafico
    ? 'conic-gradient(' . implode(', ', $segmentosGrafico) . ')'
    : 'conic-gradient(#e5e7eb 0deg 360deg)';

$metaStatement = $pdo->prepare(
    'SELECT valorOrcado FROM tbMetaOrcamentaria WHERE ano = :ano AND mes = :mes LIMIT 1'
);
$metaStatement->execute([
    'ano' => (int) $inicioMesAtual->format('Y'),
    'mes' => (int) $inicioMesAtual->format('n'),
]);
$metaValor = $metaStatement->fetchColumn();
$metaMensal = $metaValor === false ? null : (float) $metaValor;
$progressoMeta = $metaMensal === null
    ? 0
    : ($metaMensal > 0 ? min(100, $saidasMes / $metaMensal * 100) : ($saidasMes > 0 ? 100 : 0));

$recentesStatement = $pdo->query(
    'SELECT l.idLancamento, l.dataLancamento, l.descricaoLancamento, l.valorLancamento,
            l.statusLancamento, c.naturezaCategoria, c.nomeCategoria
     FROM tbLancamentoFinanceiro l
     INNER JOIN tbCategoriaLancamentos c ON c.idCategoria = l.idCategoria
     ORDER BY l.dataLancamento DESC, l.idLancamento DESC
     LIMIT 7'
);
$lancamentosRecentes = $recentesStatement->fetchAll(PDO::FETCH_ASSOC);
$baseUrl = appBaseUrl();
$urlLancamentos = appTabUrl($baseUrl . '/pages/financeiro/movimentacoes/lancamentos-financeiros.php');
$urlMetas = appTabUrl($baseUrl . '/pages/financeiro/movimentacoes/metas-orcamentarias.php');
$urlExtrato = appTabUrl($baseUrl . '/pages/financeiro/relatorios/extrato-lancamentos.php');
$urlResumoMetas = appTabUrl($baseUrl . '/pages/financeiro/relatorios/resumo-metas-orcamentarias.php');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Financeiro | Sistema Elo</title>
    <link rel="icon" type="image/png" href="<?php echo appEscape($baseUrl . '/assets/imgs/logo-elo.png'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/tailwind-config.js'); ?>"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .finance-chart { height: 190px; }
        .finance-donut { background: <?php echo appEscape($graficoCategorias); ?>; }
        [data-values-hidden="true"] .financial-bar { opacity: 0 !important; }
        [data-values-hidden="true"] .finance-donut { visibility: hidden; }
        @media (max-width: 640px) { .finance-chart { height: 150px; } }
    </style>
</head>
<body class="flex h-screen flex-col overflow-hidden bg-slate-50 font-sans text-slate-800">
    <?php include __DIR__ . '/../../components/financeiro/navbar-financeiro.php'; ?>
    <div class="flex min-h-0 flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../components/financeiro/sidebar-financeiro.php'; ?>
        <main data-finance-dashboard data-values-hidden="false" class="min-w-0 flex-1 overflow-y-auto bg-gray-50 p-4 md:p-6">
            <div class="space-y-6">
                <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Dashboard Financeiro</h1>
                        <p class="mt-1 text-sm text-gray-500">Acompanhe o movimento e a saúde financeira da instituição.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="mr-1 text-sm text-slate-500"><?php echo appEscape($meses[(int) $inicioMesAtual->format('n')] . ' de ' . $inicioMesAtual->format('Y')); ?></span>
                        <button type="button" data-values-toggle aria-pressed="false" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                            <i data-values-icon class="ph ph-eye text-lg" aria-hidden="true"></i>
                            <span data-values-label>Ocultar valores</span>
                        </button>
                        <a href="<?php echo appEscape($urlLancamentos); ?>" class="inline-flex items-center gap-2 rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-800">
                            <i class="ph ph-plus-circle text-lg" aria-hidden="true"></i> Novo lançamento
                        </a>
                    </div>
                </header>

                <section aria-label="Indicadores financeiros do mês" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-teal-800 to-teal-600 p-5 text-white shadow-sm">
                        <div class="absolute -right-7 -top-8 h-32 w-32 rounded-full bg-white/10"></div>
                        <div class="relative flex items-start justify-between">
                            <div>
                                <p class="text-sm font-medium text-teal-50">Saldo líquido acumulado</p>
                                <p class="financial-value mt-3 text-2xl font-bold tabular-nums"><?php echo appEscape($formatarMoeda($saldoAcumulado)); ?></p>
                                <p class="mt-2 text-xs text-teal-100">Entradas menos saídas efetivadas</p>
                            </div>
                            <span class="rounded-xl bg-white/15 p-3"><i class="ph ph-wallet text-2xl" aria-hidden="true"></i></span>
                        </div>
                    </article>
                    <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm font-medium text-slate-500">Entradas efetivadas</p>
                                <p class="financial-value mt-3 text-2xl font-bold tabular-nums text-emerald-700"><?php echo appEscape($formatarMoeda($entradasMes)); ?></p>
                                <p class="mt-2 text-xs text-slate-500">Neste mês</p>
                            </div>
                            <span class="rounded-xl bg-emerald-50 p-3 text-emerald-700"><i class="ph ph-arrow-down-left text-2xl" aria-hidden="true"></i></span>
                        </div>
                    </article>
                    <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm font-medium text-slate-500">Saídas efetivadas</p>
                                <p class="financial-value mt-3 text-2xl font-bold tabular-nums text-rose-700"><?php echo appEscape($formatarMoeda($saidasMes)); ?></p>
                                <p class="mt-2 text-xs text-slate-500">Neste mês</p>
                            </div>
                            <span class="rounded-xl bg-rose-50 p-3 text-rose-700"><i class="ph ph-arrow-up-right text-2xl" aria-hidden="true"></i></span>
                        </div>
                    </article>
                    <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm font-medium text-slate-500">Lançamentos pendentes</p>
                                <p class="financial-value mt-3 text-2xl font-bold tabular-nums text-amber-700"><?php echo appEscape($formatarMoeda($entradasPendentesMes + $saidasPendentesMes)); ?></p>
                                <p class="mt-2 text-xs text-slate-500"><?php echo $pendenciasTotal; ?> pendente(s) no mês</p>
                            </div>
                            <span class="rounded-xl bg-amber-50 p-3 text-amber-700"><i class="ph ph-hourglass-medium text-2xl" aria-hidden="true"></i></span>
                        </div>
                    </article>
                </section>

                <section class="grid gap-4 xl:grid-cols-3">
                    <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm xl:col-span-2">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="font-bold text-slate-900">Entradas e saídas</h2>
                                <p class="mt-1 text-xs text-slate-500">Movimentações efetivadas nos últimos seis meses</p>
                            </div>
                            <div class="flex gap-4 text-xs text-slate-500">
                                <span class="inline-flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-full bg-teal-600"></i>Entradas</span>
                                <span class="inline-flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-full bg-rose-400"></i>Saídas</span>
                            </div>
                        </div>
                        <div class="finance-chart mt-6 flex items-end justify-around gap-3 border-b border-slate-200 px-2">
                            <?php foreach ($historico as $mes): ?>
                                <?php
                                $alturaEntrada = $maximoHistorico > 0 ? max(2, (int) round($mes['entrada'] / $maximoHistorico * 100)) : 2;
                                $alturaSaida = $maximoHistorico > 0 ? max(2, (int) round($mes['saida'] / $maximoHistorico * 100)) : 2;
                                ?>
                                <div class="flex h-full min-w-0 flex-1 flex-col items-center justify-end">
                                    <div class="flex h-[calc(100%-24px)] w-full max-w-16 items-end justify-center gap-1">
                                        <div class="financial-bar min-h-[2px] w-1/3 rounded-t bg-teal-600 transition-all" style="height: <?php echo $alturaEntrada; ?>%"></div>
                                        <div class="financial-bar min-h-[2px] w-1/3 rounded-t bg-rose-400 transition-all" style="height: <?php echo $alturaSaida; ?>%"></div>
                                    </div>
                                    <span class="pb-1 pt-2 text-xs text-slate-500"><?php echo appEscape($mes['label']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span class="text-slate-500">Resultado líquido deste mês</span>
                            <strong class="financial-value tabular-nums <?php echo $saldoMes >= 0 ? 'text-teal-700' : 'text-rose-700'; ?>"><?php echo appEscape($formatarMoeda($saldoMes)); ?></strong>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                        <div>
                            <h2 class="font-bold text-slate-900">Saídas por categoria</h2>
                            <p class="mt-1 text-xs text-slate-500">Despesas efetivadas neste mês</p>
                        </div>
                        <?php if ($categorias): ?>
                            <div class="mt-5 flex items-center justify-center">
                                <div class="finance-donut relative h-36 w-36 rounded-full">
                                    <div class="absolute inset-5 flex flex-col items-center justify-center rounded-full bg-white text-center">
                                        <span class="text-[10px] uppercase tracking-wide text-slate-400">Total de saídas</span>
                                        <strong class="financial-value mt-1 max-w-full px-1 text-sm font-bold tabular-nums text-slate-800"><?php echo appEscape($formatarMoeda($totalCategorias)); ?></strong>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-5 space-y-3">
                                <?php foreach ($categorias as $categoria): ?>
                                    <?php $larguraCategoria = min(100, (float) $categoria['percentual']); ?>
                                    <div>
                                        <div class="mb-1 flex items-center justify-between gap-3 text-xs">
                                            <span class="flex min-w-0 items-center gap-2 truncate text-slate-600"><i class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: <?php echo appEscape($categoria['cor']); ?>"></i><?php echo appEscape($categoria['nomeCategoria']); ?></span>
                                            <span class="financial-value shrink-0 font-semibold tabular-nums text-slate-700"><?php echo appEscape($formatarMoeda((float) $categoria['total'])); ?></span>
                                        </div>
                                        <div class="h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="financial-bar h-full rounded-full" style="width: <?php echo $larguraCategoria; ?>%; background-color: <?php echo appEscape($categoria['cor']); ?>"></div></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="mt-8 rounded-xl bg-slate-50 px-4 py-8 text-center">
                                <i class="ph ph-chart-donut text-3xl text-slate-300" aria-hidden="true"></i>
                                <p class="mt-2 text-sm text-slate-500">Sem saídas efetivadas neste mês.</p>
                            </div>
                        <?php endif; ?>
                    </article>
                </section>

                <section class="grid gap-4 xl:grid-cols-3">
                    <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="font-bold text-slate-900">Meta do mês</h2>
                                <p class="mt-1 text-xs text-slate-500"><?php echo appEscape($meses[(int) $inicioMesAtual->format('n')]); ?></p>
                            </div>
                            <a href="<?php echo appEscape($urlMetas); ?>" class="text-xs font-semibold text-teal-700 hover:text-teal-900">Gerenciar metas</a>
                        </div>
                        <?php if ($metaMensal !== null): ?>
                            <div class="mt-5 flex items-end justify-between gap-3">
                                <div>
                                    <p class="text-xs text-slate-500">Orçamento definido</p>
                                    <p class="financial-value mt-1 text-xl font-bold tabular-nums text-slate-900"><?php echo appEscape($formatarMoeda($metaMensal)); ?></p>
                                </div>
                                <p class="text-xs font-semibold <?php echo $saidasMes > $metaMensal ? 'text-rose-700' : 'text-teal-700'; ?>"><?php echo number_format($progressoMeta, 0); ?>% utilizado</p>
                            </div>
                            <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="financial-bar h-full rounded-full <?php echo $saidasMes > $metaMensal ? 'bg-rose-500' : 'bg-teal-600'; ?>" style="width: <?php echo $progressoMeta; ?>%"></div>
                            </div>
                            <p class="financial-value mt-2 text-xs text-slate-500"><?php echo appEscape($formatarMoeda($saidasMes)); ?> em saídas efetivadas</p>
                        <?php else: ?>
                            <div class="mt-5 rounded-xl bg-slate-50 p-4">
                                <p class="text-sm font-medium text-slate-700">Nenhuma meta cadastrada para este mês.</p>
                                <p class="mt-1 text-xs text-slate-500">Defina um orçamento para acompanhar as despesas por aqui.</p>
                                <a href="<?php echo appEscape($urlMetas); ?>" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-teal-700 hover:text-teal-900">Configurar meta <i class="ph ph-arrow-right" aria-hidden="true"></i></a>
                            </div>
                        <?php endif; ?>
                    </article>

                    <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm xl:col-span-2">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="font-bold text-slate-900">Lançamentos recentes</h2>
                                <p class="mt-1 text-xs text-slate-500">Últimas movimentações registradas</p>
                            </div>
                            <a href="<?php echo appEscape($urlLancamentos); ?>" class="text-xs font-semibold text-teal-700 hover:text-teal-900">Ver todos</a>
                        </div>
                        <?php if ($lancamentosRecentes): ?>
                            <div class="mt-4 divide-y divide-slate-100">
                                <?php foreach ($lancamentosRecentes as $lancamento): ?>
                                    <?php $entrada = $lancamento['naturezaCategoria'] === 'Entrada'; ?>
                                    <div class="flex items-center gap-3 py-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full <?php echo $entrada ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'; ?>">
                                            <i class="ph <?php echo $entrada ? 'ph-arrow-down-left' : 'ph-arrow-up-right'; ?> text-lg" aria-hidden="true"></i>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-slate-800"><?php echo appEscape($lancamento['descricaoLancamento']); ?></p>
                                            <p class="mt-0.5 truncate text-xs text-slate-500"><?php echo appEscape($lancamento['nomeCategoria']); ?> · <?php echo appEscape(date('d/m/Y', strtotime($lancamento['dataLancamento']))); ?></p>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <p class="financial-value text-sm font-bold tabular-nums <?php echo $entrada ? 'text-emerald-700' : 'text-slate-800'; ?>"><?php echo $entrada ? '+' : '−'; ?> <?php echo appEscape($formatarMoeda((float) $lancamento['valorLancamento'])); ?></p>
                                            <p class="mt-0.5 text-[10px] <?php echo $lancamento['statusLancamento'] === 'Efetivado' ? 'text-emerald-700' : 'text-amber-700'; ?>"><?php echo appEscape($lancamento['statusLancamento']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="mt-4 rounded-xl bg-slate-50 p-6 text-center">
                                <p class="text-sm text-slate-500">Ainda não há lançamentos financeiros.</p>
                                <a href="<?php echo appEscape($urlLancamentos); ?>" class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-teal-700 hover:text-teal-900">Criar primeiro lançamento <i class="ph ph-arrow-right" aria-hidden="true"></i></a>
                            </div>
                        <?php endif; ?>
                    </article>
                </section>

                <footer class="flex flex-wrap gap-x-5 gap-y-2 pb-2 text-xs text-slate-500">
                    <a href="<?php echo appEscape($urlExtrato); ?>" class="inline-flex items-center gap-1 hover:text-teal-700"><i class="ph ph-receipt" aria-hidden="true"></i> Extrato de lançamentos</a>
                    <a href="<?php echo appEscape($urlResumoMetas); ?>" class="inline-flex items-center gap-1 hover:text-teal-700"><i class="ph ph-chart-line-up" aria-hidden="true"></i> Resumo de metas</a>
                    <span><?php echo $quantidadeMes; ?> lançamento(s) neste mês</span>
                </footer>
            </div>
        </main>
    </div>
    <script>
        (() => {
            const dashboard = document.querySelector('[data-finance-dashboard]');
            const toggle = document.querySelector('[data-values-toggle]');
            if (!dashboard || !toggle) return;

            const icon = toggle.querySelector('[data-values-icon]');
            const label = toggle.querySelector('[data-values-label]');
            const values = Array.from(dashboard.querySelectorAll('.financial-value'), (element) => ({
                element,
                visibleText: element.textContent
            }));
            toggle.addEventListener('click', () => {
                const hidden = dashboard.dataset.valuesHidden !== 'true';
                dashboard.dataset.valuesHidden = String(hidden);
                values.forEach(({ element, visibleText }) => {
                    element.textContent = hidden ? '••••••' : visibleText;
                });
                toggle.setAttribute('aria-pressed', String(hidden));
                toggle.setAttribute('aria-label', hidden ? 'Mostrar valores financeiros' : 'Ocultar valores financeiros');
                icon.classList.toggle('ph-eye', !hidden);
                icon.classList.toggle('ph-eye-slash', hidden);
                label.textContent = hidden ? 'Mostrar valores' : 'Ocultar valores';
            });
        })();
    </script>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
</body>
</html>
