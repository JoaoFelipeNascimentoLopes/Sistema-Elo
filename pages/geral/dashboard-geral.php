<?php
require_once __DIR__ . '/../../auth/access.php';
$usuarioAtual = appRequirePage('geral.dashboard-geral');
$pdo = appPdo();
$baseUrl = appBaseUrl();

$meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
];
$hoje = new DateTimeImmutable('today');
$dataExtenso = $hoje->format('d') . ' de ' . $meses[(int) $hoje->format('n')] . ' de ' . $hoje->format('Y');
$formatarMoeda = static fn(float $valor): string => 'R$ ' . number_format($valor, 2, ',', '.');

$podeVerPlantao = appCanAccess('geral.movimentacoes/livro-plantao', $usuarioAtual);
$podeVerColaboradores = appCanAccess('geral.cadastros/colaboradores', $usuarioAtual);
$podeVerUsuarios = appCanAccess('geral.usuarios', $usuarioAtual);
$podeVerAuditoria = appCanAccess('geral.movimentacoes/log-auditoria', $usuarioAtual);
$podeVerAcolhidos = appCanAccess('gestao-acolhidos.cadastros/acolhidos', $usuarioAtual)
    || appCanAccess('gestao-acolhidos.movimentacoes/acolhimento', $usuarioAtual)
    || appCanAccess('gestao-acolhidos.movimentacoes/acomodacoes-acolhidos', $usuarioAtual);
$podeVerFinanceiro = appCanAccess('financeiro.dashboard-financeiro', $usuarioAtual)
    || appCanAccess('financeiro.movimentacoes/lancamentos-financeiros', $usuarioAtual);
$podeEditarInstituicao = appCanAccess('instituicao.informacoes', $usuarioAtual);

$instituicao = $pdo->query(
    'SELECT nomeFantasia, razaoSocial, cnpj, cidade, uf
     FROM tbInformacoesInstituicao
     WHERE idInformacoes = 1
     LIMIT 1'
)->fetch(PDO::FETCH_ASSOC) ?: null;
$nomeInstituicao = trim((string) ($instituicao['nomeFantasia'] ?? ''))
    ?: trim((string) ($instituicao['razaoSocial'] ?? ''));

$acolhidosResumo = $pdo->query(
    'SELECT
        (SELECT COUNT(*) FROM tbAcolhido) AS totalCadastrados,
        (SELECT COUNT(*) FROM tbAcolhimento WHERE dataSaida IS NULL) AS acolhimentosAtivos'
)->fetch(PDO::FETCH_ASSOC) ?: ['totalCadastrados' => 0, 'acolhimentosAtivos' => 0];

$leitosResumo = $pdo->query(
    "SELECT
        COUNT(*) AS totalLeitos,
        COALESCE(SUM(CASE WHEN l.statusLeito = 'Ativo' AND q.ativo = 1 AND b.ativo = 1 THEN 1 ELSE 0 END), 0) AS leitosAtivos,
        COALESCE(SUM(CASE WHEN l.statusLeito = 'Manutenção' THEN 1 ELSE 0 END), 0) AS leitosManutencao,
        COALESCE(SUM(CASE WHEN l.statusLeito = 'Ativo' AND q.ativo = 1 AND b.ativo = 1 AND EXISTS (
            SELECT 1 FROM tbAcomodacaoAcolhido ac WHERE ac.idLeito = l.idLeito AND ac.fimEm IS NULL
        ) THEN 1 ELSE 0 END), 0) AS leitosOcupados
     FROM tbLeito l
     INNER JOIN tbQuarto q ON q.idQuarto = l.idQuarto
     INNER JOIN tbBloco b ON b.idBloco = q.idBloco"
)->fetch(PDO::FETCH_ASSOC) ?: ['totalLeitos' => 0, 'leitosAtivos' => 0, 'leitosManutencao' => 0, 'leitosOcupados' => 0];

$leitosAtivos = (int) $leitosResumo['leitosAtivos'];
$leitosOcupados = (int) $leitosResumo['leitosOcupados'];
$leitosDisponiveis = max(0, $leitosAtivos - $leitosOcupados);
$leitosManutencao = (int) $leitosResumo['leitosManutencao'];
$taxaOcupacao = $leitosAtivos > 0 ? round(($leitosOcupados / $leitosAtivos) * 100) : 0;

$equipeResumo = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END), 0) AS ativos,
        COALESCE(SUM(CASE WHEN ativo = 1 AND tipoVinculo = 'Voluntário' THEN 1 ELSE 0 END), 0) AS voluntariosAtivos,
        COALESCE(SUM(CASE WHEN ativo = 1 AND tipoVinculo <> 'Voluntário' THEN 1 ELSE 0 END), 0) AS colaboradoresAtivos
     FROM tbColaborador"
)->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'ativos' => 0, 'voluntariosAtivos' => 0, 'colaboradoresAtivos' => 0];

$plantaoResumo = $pdo->query(
    "SELECT
        COALESCE(SUM(CASE WHEN statusRegistro <> 'Resolvido' THEN 1 ELSE 0 END), 0) AS abertos,
        COALESCE(SUM(CASE WHEN statusRegistro <> 'Resolvido' AND prioridade = 'Urgente' THEN 1 ELSE 0 END), 0) AS urgentes,
        COALESCE(SUM(CASE WHEN fixado = 1 THEN 1 ELSE 0 END), 0) AS fixados
     FROM tbRegistroPlantao"
)->fetch(PDO::FETCH_ASSOC) ?: ['abertos' => 0, 'urgentes' => 0, 'fixados' => 0];

$destaquesPlantao = $pdo->query(
    "SELECT r.idRegistroPlantao, r.tipoRegistro, r.prioridade, r.statusRegistro, r.fixado,
            r.dataOcorrencia, r.turno, r.titulo, r.descricao,
            c.nomeCompleto AS nomeColaborador, a.nomeCompleto AS nomeAcolhido
     FROM tbRegistroPlantao r
     LEFT JOIN tbColaborador c ON c.idColaborador = r.idColaborador
     LEFT JOIN tbAcolhido a ON a.idAcolhido = r.idAcolhido
     WHERE r.fixado = 1 OR r.statusRegistro <> 'Resolvido'
     ORDER BY r.fixado DESC, FIELD(r.prioridade, 'Urgente', 'Atenção', 'Normal'), r.dataOcorrencia DESC, r.idRegistroPlantao DESC
     LIMIT 6"
)->fetchAll(PDO::FETCH_ASSOC);

$usuariosResumo = $pdo->query(
    'SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN ativo = 1 AND bloqueado = 0 THEN 1 ELSE 0 END), 0) AS ativos,
        COALESCE(SUM(CASE WHEN bloqueado = 1 THEN 1 ELSE 0 END), 0) AS bloqueados,
        (SELECT COUNT(*) FROM tbPerfilAcesso) AS totalPerfis
     FROM tbUsuario'
)->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'ativos' => 0, 'bloqueados' => 0, 'totalPerfis' => 0];

$alertasSeguranca7d = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM tbLogAuditoria
     WHERE dataHora >= NOW() - INTERVAL 7 DAY
       AND acao IN ('login_falha', 'login_bloqueado', 'troca_senha_falha')"
)->fetchColumn();

$eventosRecentes = [];
if ($podeVerAuditoria) {
    $eventosRecentes = $pdo->query(
        'SELECT l.idLog, l.dataHora, l.loginInformado, l.modulo, l.acao, l.descricao,
                u.nomeCompleto AS nomeUsuario
         FROM tbLogAuditoria l
         LEFT JOIN tbUsuario u ON u.idUsuario = l.idUsuario
         ORDER BY l.dataHora DESC, l.idLog DESC
         LIMIT 6'
    )->fetchAll(PDO::FETCH_ASSOC);
}

$financeiroResumo = null;
if ($podeVerFinanceiro) {
    $inicioMes = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
    $proximoMes = (new DateTimeImmutable('first day of next month'))->format('Y-m-d');
    $finStmt = $pdo->prepare(
        'SELECT c.naturezaCategoria, l.statusLancamento, COUNT(*) AS qtd, COALESCE(SUM(l.valorLancamento), 0) AS total
         FROM tbLancamentoFinanceiro l
         INNER JOIN tbCategoriaLancamentos c ON c.idCategoria = l.idCategoria
         WHERE l.dataLancamento >= :inicio AND l.dataLancamento < :fim
         GROUP BY c.naturezaCategoria, l.statusLancamento'
    );
    $finStmt->execute(['inicio' => $inicioMes, 'fim' => $proximoMes]);
    $entradas = 0.0;
    $saidas = 0.0;
    $pendentesValor = 0.0;
    $pendentesQtd = 0;
    foreach ($finStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['statusLancamento'] === 'Pendente') {
            $pendentesValor += (float) $row['total'];
            $pendentesQtd += (int) $row['qtd'];
        } elseif ($row['naturezaCategoria'] === 'Entrada') {
            $entradas += (float) $row['total'];
        } else {
            $saidas += (float) $row['total'];
        }
    }
    $financeiroResumo = [
        'entradas' => $entradas,
        'saidas' => $saidas,
        'saldo' => $entradas - $saidas,
        'pendentesValor' => $pendentesValor,
        'pendentesQtd' => $pendentesQtd,
    ];
}

$atalhos = [];
$candidatosAtalhos = [
    ['key' => 'geral.movimentacoes/livro-plantao', 'titulo' => 'Livro de Plantão', 'icone' => 'ph-notebook', 'url' => $baseUrl . '/pages/geral/movimentacoes/livro-plantao.php'],
    ['key' => 'geral.cadastros/colaboradores', 'titulo' => 'Colaboradores e Voluntários', 'icone' => 'ph-identification-badge', 'url' => $baseUrl . '/pages/geral/cadastros/colaboradores.php'],
    ['key' => 'gestao-acolhidos.movimentacoes/acomodacoes-acolhidos', 'titulo' => 'Mapa de Acomodações', 'icone' => 'ph-bed', 'url' => $baseUrl . '/pages/gestao-acolhidos/movimentacoes/acomodacoes-acolhidos.php'],
    ['key' => 'gestao-acolhidos.movimentacoes/acolhimento', 'titulo' => 'Acolhimentos', 'icone' => 'ph-house-line', 'url' => $baseUrl . '/pages/gestao-acolhidos/movimentacoes/acolhimento.php'],
    ['key' => 'financeiro.dashboard-financeiro', 'titulo' => 'Dashboard Financeiro', 'icone' => 'ph-currency-circle-dollar', 'url' => $baseUrl . '/pages/financeiro/dashboard-financeiro.php'],
    ['key' => 'geral.usuarios', 'titulo' => 'Usuários', 'icone' => 'ph-users-three', 'url' => $baseUrl . '/pages/geral/cadastros/usuarios.php'],
    ['key' => 'geral.perfis-acesso', 'titulo' => 'Perfis de Acesso', 'icone' => 'ph-key', 'url' => $baseUrl . '/pages/geral/cadastros/perfis-acesso.php'],
    ['key' => 'geral.relatorios/matriz-permissoes', 'titulo' => 'Matriz de Permissões', 'icone' => 'ph-shield-check', 'url' => $baseUrl . '/pages/geral/relatorios/matriz-permissoes.php'],
];
foreach ($candidatosAtalhos as $atalho) {
    if (appCanAccess($atalho['key'], $usuarioAtual)) {
        $atalhos[] = $atalho;
    }
}

$acoesAuditoriaLabels = [
    'login_sucesso' => ['label' => 'Login', 'classe' => 'bg-emerald-100 text-emerald-800'],
    'login_falha' => ['label' => 'Falha login', 'classe' => 'bg-red-100 text-red-800'],
    'login_bloqueado' => ['label' => 'Bloqueado', 'classe' => 'bg-amber-100 text-amber-800'],
    'logout' => ['label' => 'Logout', 'classe' => 'bg-slate-100 text-slate-700'],
    'troca_senha' => ['label' => 'Senha', 'classe' => 'bg-sky-100 text-sky-800'],
    'troca_senha_falha' => ['label' => 'Senha falha', 'classe' => 'bg-red-100 text-red-800'],
    'criar' => ['label' => 'Criação', 'classe' => 'bg-emerald-100 text-emerald-800'],
    'editar' => ['label' => 'Edição', 'classe' => 'bg-blue-100 text-blue-800'],
    'excluir' => ['label' => 'Exclusão', 'classe' => 'bg-rose-100 text-rose-800'],
];
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
<body class="flex h-screen flex-col overflow-hidden bg-slate-50 font-sans text-slate-800">
    <?php include __DIR__ . '/../../components/geral/navbar-geral.php'; ?>
    <div class="flex min-h-0 flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../components/geral/sidebar-geral.php'; ?>
        <main class="min-w-0 flex-1 overflow-y-auto bg-gray-50 p-4 md:p-6">
            <div class="space-y-6">
                <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Dashboard Geral</h1>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <?php if ($nomeInstituicao !== ''): ?>
                            <span class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm" data-preserve-case>
                                <i class="ph ph-buildings text-base text-elo-teal" aria-hidden="true"></i>
                                <?php echo appEscape($nomeInstituicao); ?>
                            </span>
                        <?php elseif ($podeEditarInstituicao): ?>
                            <button type="button" data-modal-toggle="informacoesInstituicaoModal"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-800 hover:bg-amber-100">
                                <i class="ph ph-warning-circle text-base" aria-hidden="true"></i>
                                Completar dados da instituição
                            </button>
                        <?php endif; ?>
                        <span class="text-xs font-medium text-slate-500" data-preserve-case><?php echo appEscape($dataExtenso); ?></span>
                        <?php if ($podeVerPlantao): ?>
                            <a href="<?php echo appEscape(appTabUrl($baseUrl . '/pages/geral/movimentacoes/livro-plantao.php?aba=manutencao#manutencao')); ?>"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-elo-teal px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-elo-green">
                                <i class="ph ph-plus-circle text-lg" aria-hidden="true"></i>
                                Novo registro no plantão
                            </a>
                        <?php endif; ?>
                    </div>
                </header>

                <section aria-label="Indicadores gerais" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-teal-800 to-teal-600 p-5 text-white shadow-sm">
                        <div class="absolute -right-7 -top-8 h-32 w-32 rounded-full bg-white/10"></div>
                        <div class="relative flex items-start justify-between">
                            <div>
                                <p class="text-sm font-medium text-teal-50">Acolhidos ativos</p>
                                <p class="mt-2.5 text-3xl font-bold tabular-nums"><?php echo (int) $acolhidosResumo['acolhimentosAtivos']; ?></p>
                                <p class="mt-2 text-xs text-teal-100">
                                    <?php echo (int) $acolhidosResumo['totalCadastrados']; ?> cadastrado(s) · <?php echo (int) $taxaOcupacao; ?>% dos leitos ocupados
                                </p>
                            </div>
                            <span class="rounded-xl bg-white/15 p-3"><i class="ph ph-users text-2xl" aria-hidden="true"></i></span>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm font-medium text-slate-500">Equipe e voluntários</p>
                                <p class="mt-2.5 text-3xl font-bold tabular-nums text-slate-900"><?php echo (int) $equipeResumo['ativos']; ?></p>
                                <p class="mt-2 text-xs text-slate-500">
                                    <?php echo (int) $equipeResumo['colaboradoresAtivos']; ?> colaborador(es) · <?php echo (int) $equipeResumo['voluntariosAtivos']; ?> voluntário(s)
                                </p>
                            </div>
                            <span class="rounded-xl bg-teal-50 p-3 text-teal-700"><i class="ph ph-identification-badge text-2xl" aria-hidden="true"></i></span>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm font-medium text-slate-500">Plantão em aberto</p>
                                <p class="mt-2.5 text-3xl font-bold tabular-nums <?php echo (int) $plantaoResumo['urgentes'] > 0 ? 'text-rose-700' : 'text-amber-700'; ?>">
                                    <?php echo (int) $plantaoResumo['abertos']; ?>
                                </p>
                                <p class="mt-2 text-xs text-slate-500">
                                    <?php echo (int) $plantaoResumo['urgentes']; ?> urgente(s) · <?php echo (int) $plantaoResumo['fixados']; ?> aviso(s) fixado(s)
                                </p>
                            </div>
                            <span class="rounded-xl bg-amber-50 p-3 text-amber-700"><i class="ph ph-notebook text-2xl" aria-hidden="true"></i></span>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm font-medium text-slate-500">Usuários ativos</p>
                                <p class="mt-2.5 text-3xl font-bold tabular-nums text-slate-900"><?php echo (int) $usuariosResumo['ativos']; ?></p>
                                <p class="mt-2 text-xs text-slate-500">
                                    <?php echo (int) $usuariosResumo['totalPerfis']; ?> perfil(is) · <?php echo (int) $usuariosResumo['bloqueados']; ?> bloqueado(s) · <?php echo $alertasSeguranca7d; ?> alerta(s) 7d
                                </p>
                            </div>
                            <span class="rounded-xl bg-sky-50 p-3 text-sky-700"><i class="ph ph-shield-check text-2xl" aria-hidden="true"></i></span>
                        </div>
                    </article>
                </section>

                <section class="grid gap-4 xl:grid-cols-3">
                    <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm xl:col-span-2">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <h2 class="flex items-center gap-2 font-bold text-slate-900">
                                <i class="ph ph-push-pin text-lg text-elo-teal" aria-hidden="true"></i>
                                Mural do Plantão e Pendências
                            </h2>
                            <?php if ($podeVerPlantao): ?>
                                <a href="<?php echo appEscape(appTabUrl($baseUrl . '/pages/geral/movimentacoes/livro-plantao.php')); ?>"
                                   class="text-xs font-semibold text-teal-700 hover:text-teal-900">
                                    Abrir livro completo
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php if (!$destaquesPlantao): ?>
                            <div class="rounded-xl bg-slate-50 px-4 py-8 text-center">
                                <i class="ph ph-check-circle text-3xl text-emerald-500" aria-hidden="true"></i>
                                <p class="mt-2 text-sm font-medium text-slate-700">Tudo tranquilo no plantão.</p>
                                <p class="mt-1 text-xs text-slate-500">Não há avisos fixados nem ocorrências em aberto no momento.</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-2.5">
                                <?php foreach ($destaquesPlantao as $item): ?>
                                    <?php
                                    $prioClasse = match ($item['prioridade']) {
                                        'Urgente' => 'bg-red-100 text-red-800',
                                        'Atenção' => 'bg-amber-100 text-amber-800',
                                        default => 'bg-slate-100 text-slate-700',
                                    };
                                    ?>
                                    <div class="flex flex-col justify-between gap-2 rounded-xl border border-slate-100 bg-slate-50/70 p-3.5 sm:flex-row sm:items-center">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <?php if ((int) $item['fixado'] === 1): ?>
                                                    <i class="ph-fill ph-push-pin text-sm text-elo-teal" title="Fixado no mural" aria-hidden="true"></i>
                                                <?php endif; ?>
                                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold <?php echo $prioClasse; ?>">
                                                    <?php echo appEscape($item['prioridade']); ?>
                                                </span>
                                                <span class="text-xs font-medium text-slate-500"><?php echo appEscape($item['tipoRegistro']); ?></span>
                                                <span class="text-xs text-slate-400" data-preserve-case>
                                                    <?php echo appEscape(date('d/m/Y', strtotime((string) $item['dataOcorrencia'])) . ' · ' . $item['turno']); ?>
                                                </span>
                                            </div>
                                            <p class="mt-1 truncate text-sm font-semibold text-slate-800" data-preserve-case><?php echo appEscape($item['titulo']); ?></p>
                                            <p class="mt-0.5 line-clamp-1 text-xs text-slate-600" data-preserve-case><?php echo appEscape($item['descricao']); ?></p>
                                        </div>
                                        <?php if ($podeVerPlantao): ?>
                                            <a href="<?php echo appEscape(appTabUrl($baseUrl . '/pages/geral/movimentacoes/livro-plantao.php?editar=' . (int) $item['idRegistroPlantao'] . '#manutencao')); ?>"
                                               class="shrink-0 self-start rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-elo-teal hover:bg-elo-teal/5 sm:self-center">
                                                Ver / Tratar
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>

                    <div class="space-y-4">
                        <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between gap-2">
                                <h2 class="font-bold text-slate-900">Ocupação de Leitos</h2>
                                <?php if (appCanAccess('gestao-acolhidos.movimentacoes/acomodacoes-acolhidos', $usuarioAtual)): ?>
                                    <a href="<?php echo appEscape(appTabUrl($baseUrl . '/pages/gestao-acolhidos/movimentacoes/acomodacoes-acolhidos.php')); ?>"
                                       class="text-xs font-semibold text-teal-700 hover:text-teal-900">Ver mapa</a>
                                <?php endif; ?>
                            </div>
                            <div class="mt-4 flex items-end justify-between">
                                <div>
                                    <p class="text-xs text-slate-500">Ocupados / Ativos</p>
                                    <p class="mt-0.5 text-2xl font-bold tabular-nums text-slate-900"><?php echo $leitosOcupados; ?> / <?php echo $leitosAtivos; ?></p>
                                </div>
                                <span class="text-sm font-bold <?php echo $taxaOcupacao >= 90 ? 'text-rose-700' : 'text-teal-700'; ?>"><?php echo (int) $taxaOcupacao; ?>%</span>
                            </div>
                            <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full <?php echo $taxaOcupacao >= 90 ? 'bg-rose-500' : 'bg-teal-600'; ?>" style="width: <?php echo min(100, (int) $taxaOcupacao); ?>%"></div>
                            </div>
                            <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                                <div class="rounded-lg bg-emerald-50 p-2">
                                    <p class="font-bold text-emerald-800"><?php echo $leitosDisponiveis; ?></p>
                                    <p class="text-emerald-700">Disponíveis</p>
                                </div>
                                <div class="rounded-lg bg-blue-50 p-2">
                                    <p class="font-bold text-blue-800"><?php echo $leitosOcupados; ?></p>
                                    <p class="text-blue-700">Ocupados</p>
                                </div>
                                <div class="rounded-lg bg-amber-50 p-2">
                                    <p class="font-bold text-amber-800"><?php echo $leitosManutencao; ?></p>
                                    <p class="text-amber-700">Manutenção</p>
                                </div>
                            </div>
                        </article>

                        <?php if ($financeiroResumo !== null): ?>
                            <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <h2 class="font-bold text-slate-900">Financeiro (<?php echo appEscape($meses[(int) $hoje->format('n')]); ?>)</h2>
                                    <?php if (appCanAccess('financeiro.dashboard-financeiro', $usuarioAtual)): ?>
                                        <a href="<?php echo appEscape(appTabUrl($baseUrl . '/pages/financeiro/dashboard-financeiro.php')); ?>"
                                           class="text-xs font-semibold text-teal-700 hover:text-teal-900">Detalhes</a>
                                    <?php endif; ?>
                                </div>
                                <dl class="mt-4 space-y-2.5 text-sm">
                                    <div class="flex items-center justify-between">
                                        <dt class="text-slate-500">Entradas efetivadas</dt>
                                        <dd class="font-semibold tabular-nums text-emerald-700"><?php echo appEscape($formatarMoeda($financeiroResumo['entradas'])); ?></dd>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <dt class="text-slate-500">Saídas efetivadas</dt>
                                        <dd class="font-semibold tabular-nums text-rose-700"><?php echo appEscape($formatarMoeda($financeiroResumo['saidas'])); ?></dd>
                                    </div>
                                    <div class="flex items-center justify-between border-t border-slate-100 pt-2">
                                        <dt class="font-medium text-slate-700">Saldo do mês</dt>
                                        <dd class="font-bold tabular-nums <?php echo $financeiroResumo['saldo'] >= 0 ? 'text-teal-700' : 'text-rose-700'; ?>">
                                            <?php echo appEscape($formatarMoeda($financeiroResumo['saldo'])); ?>
                                        </dd>
                                    </div>
                                    <?php if ($financeiroResumo['pendentesQtd'] > 0): ?>
                                        <div class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                            <?php echo $financeiroResumo['pendentesQtd']; ?> lançamento(s) pendente(s): <strong><?php echo appEscape($formatarMoeda($financeiroResumo['pendentesValor'])); ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </dl>
                            </article>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="grid gap-4 xl:grid-cols-3">
                    <?php if ($podeVerAuditoria): ?>
                        <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm xl:col-span-2">
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <h2 class="flex items-center gap-2 font-bold text-slate-900">
                                    <i class="ph ph-clock-counter-clockwise text-lg text-elo-teal" aria-hidden="true"></i>
                                    Atividade Recente do Sistema
                                </h2>
                                <a href="<?php echo appEscape(appTabUrl($baseUrl . '/pages/geral/movimentacoes/log-auditoria.php')); ?>"
                                   class="text-xs font-semibold text-teal-700 hover:text-teal-900">
                                    Ver log completo
                                </a>
                            </div>
                            <?php if (!$eventosRecentes): ?>
                                <p class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-500">Nenhum evento de auditoria registrado ainda.</p>
                            <?php else: ?>
                                <div class="divide-y divide-slate-100">
                                    <?php foreach ($eventosRecentes as $ev): ?>
                                        <?php
                                        $acaoMeta = $acoesAuditoriaLabels[$ev['acao']] ?? ['label' => $ev['acao'], 'classe' => 'bg-slate-100 text-slate-700'];
                                        $atorTexto = $ev['nomeUsuario'] !== null ? $ev['nomeUsuario'] : ($ev['loginInformado'] ?? 'Sistema');
                                        ?>
                                        <div class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold <?php echo $acaoMeta['classe']; ?>">
                                                        <?php echo appEscape($acaoMeta['label']); ?>
                                                    </span>
                                                    <span class="truncate font-medium text-slate-800" data-preserve-case><?php echo appEscape((string) $ev['descricao']); ?></span>
                                                </div>
                                                <p class="mt-0.5 text-xs text-slate-500" data-preserve-case><?php echo appEscape((string) $atorTexto); ?></p>
                                            </div>
                                            <span class="whitespace-nowrap text-xs text-slate-400" data-preserve-case>
                                                <?php echo appEscape(date('d/m/Y H:i', strtotime((string) $ev['dataHora']))); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endif; ?>

                    <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm <?php echo $podeVerAuditoria ? '' : 'xl:col-span-3'; ?>">
                        <h2 class="mb-4 font-bold text-slate-900">Acesso Rápido</h2>
                        <?php if (!$atalhos): ?>
                            <p class="text-sm text-slate-500">Use o menu lateral para navegar pelas telas liberadas ao seu perfil.</p>
                        <?php else: ?>
                            <div class="grid gap-2 <?php echo $podeVerAuditoria ? 'grid-cols-1 sm:grid-cols-2 xl:grid-cols-1' : 'sm:grid-cols-2 lg:grid-cols-4'; ?>">
                                <?php foreach ($atalhos as $atalho): ?>
                                    <a href="<?php echo appEscape(appTabUrl($atalho['url'])); ?>"
                                       class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/70 px-3.5 py-2.5 text-sm font-medium text-slate-700 transition hover:border-elo-teal/40 hover:bg-elo-teal/5 hover:text-elo-teal">
                                        <span class="flex items-center gap-2.5">
                                            <i class="ph <?php echo appEscape($atalho['icone']); ?> text-lg text-elo-teal" aria-hidden="true"></i>
                                            <?php echo appEscape($atalho['titulo']); ?>
                                        </span>
                                        <i class="ph ph-caret-right text-slate-400" aria-hidden="true"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                </section>
            </div>
        </main>
    </div>

    <script src="<?php echo appEscape($baseUrl . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
</body>
</html>
