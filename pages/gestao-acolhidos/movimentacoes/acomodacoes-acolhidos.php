<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('gestao-acolhidos.movimentacoes/acomodacoes-acolhidos');
$pdo = appPdo();
$baseUrl = appBaseUrl();
$urlAtual = $baseUrl . '/pages/gestao-acolhidos/movimentacoes/acomodacoes-acolhidos.php';
$erro = '';
$resultado = is_scalar($_GET['resultado'] ?? null) ? (string) $_GET['resultado'] : '';
$dados = ['idAcolhimento' => '', 'idLeito' => '', 'observacoes' => ''];
$formEnviado = false;
$textoPost = static function (string $campo): string {
    $valor = $_POST[$campo] ?? '';
    return is_scalar($valor) ? trim((string) $valor) : '';
};
$formatarAuditoria = static function ($id, $nome, $data): string {
    if ($id === null || $nome === null || $data === null) return '—';
    $timestamp = strtotime((string) $data);
    return $timestamp === false ? '—' : (int) $id . ' - ' . $nome . ' - ' . date('d/m/Y H:i', $timestamp);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    $acaoInput = $_POST['acao'] ?? '';
    $acao = is_scalar($acaoInput) ? (string) $acaoInput : '';
    try {
        if ($acao !== 'salvar') throw new InvalidArgumentException('Ação inválida.');
        $formEnviado = true;
        $idAcolhimento = filter_var($textoPost('idAcolhimento'), FILTER_VALIDATE_INT);
        $idLeito = filter_var($textoPost('idLeito'), FILTER_VALIDATE_INT);
        $observacoes = $textoPost('observacoes');
        $dados = [
            'idAcolhimento' => $idAcolhimento !== false ? (int) $idAcolhimento : '',
            'idLeito' => $idLeito !== false ? (int) $idLeito : '',
            'observacoes' => $observacoes,
        ];
        if ($idAcolhimento === false || $idAcolhimento < 1 || $idLeito === false || $idLeito < 1
            || mb_strlen($observacoes, 'UTF-8') > 500) {
            throw new InvalidArgumentException('Selecione um acolhimento ativo e um leito disponível. A observação deve ter até 500 caracteres.');
        }

        $pdo->beginTransaction();
        $admissaoStatement = $pdo->prepare(
            'SELECT a.idAcolhimento, a.idAcolhido, a.dataEntrada, acolhido.nomeCompleto
             FROM tbAcolhimento a
             INNER JOIN tbAcolhido acolhido ON acolhido.idAcolhido = a.idAcolhido
             WHERE a.idAcolhimento = :id AND a.dataSaida IS NULL
             FOR UPDATE'
        );
        $admissaoStatement->execute(['id' => $idAcolhimento]);
        $admissao = $admissaoStatement->fetch(PDO::FETCH_ASSOC);
        if (!$admissao) throw new InvalidArgumentException('O acolhimento selecionado não está ativo.');
        if ((string) $admissao['dataEntrada'] > date('Y-m-d')) {
            throw new InvalidArgumentException('A acomodação só pode ser registrada a partir da data de entrada do acolhido.');
        }

        $leitoStatement = $pdo->prepare(
            'SELECT l.idLeito, l.identificacaoLeito, l.statusLeito,
                    q.idQuarto, q.identificacaoQuarto, q.ativo AS quartoAtivo,
                    b.idBloco, b.nomeBloco, b.ativo AS blocoAtivo
             FROM tbLeito l
             INNER JOIN tbQuarto q ON q.idQuarto = l.idQuarto
             INNER JOIN tbBloco b ON b.idBloco = q.idBloco
             WHERE l.idLeito = :id
             FOR UPDATE'
        );
        $leitoStatement->execute(['id' => $idLeito]);
        $leito = $leitoStatement->fetch(PDO::FETCH_ASSOC);
        if (!$leito || $leito['statusLeito'] !== 'Ativo'
            || (int) $leito['quartoAtivo'] !== 1 || (int) $leito['blocoAtivo'] !== 1) {
            throw new InvalidArgumentException('O leito selecionado não está ativo para ocupação.');
        }

        $ocupacaoLeitoStatement = $pdo->prepare(
            'SELECT idAcomodacao, idAcolhimento FROM tbAcomodacaoAcolhido
             WHERE idLeito = :idLeito AND fimEm IS NULL LIMIT 1 FOR UPDATE'
        );
        $ocupacaoLeitoStatement->execute(['idLeito' => $idLeito]);
        $ocupacaoLeito = $ocupacaoLeitoStatement->fetch(PDO::FETCH_ASSOC);
        if ($ocupacaoLeito) {
            throw new InvalidArgumentException((int) $ocupacaoLeito['idAcolhimento'] === $idAcolhimento
                ? 'Este acolhido já está alocado neste leito.'
                : 'Este leito já está ocupado por outro acolhido.');
        }

        $ocupacaoAtualStatement = $pdo->prepare(
            'SELECT idAcomodacao, idLeito FROM tbAcomodacaoAcolhido
             WHERE idAcolhimento = :id AND fimEm IS NULL LIMIT 1 FOR UPDATE'
        );
        $ocupacaoAtualStatement->execute(['id' => $idAcolhimento]);
        $ocupacaoAtual = $ocupacaoAtualStatement->fetch(PDO::FETCH_ASSOC);
        if ($ocupacaoAtual) {
            $encerraAtual = $pdo->prepare(
                'UPDATE tbAcomodacaoAcolhido
                 SET fimEm = CURRENT_TIMESTAMP, idUsuarioAlteracao = :usuario
                 WHERE idAcomodacao = :id AND fimEm IS NULL'
            );
            $encerraAtual->execute([
                'usuario' => (int) $usuarioAtual['idUsuario'],
                'id' => (int) $ocupacaoAtual['idAcomodacao'],
            ]);
        }

        $insere = $pdo->prepare(
            'INSERT INTO tbAcomodacaoAcolhido
                (idAcolhimento, idLeito, blocoSnapshot, quartoSnapshot, leitoSnapshot,
                 inicioEm, observacoes, idUsuarioCriacao)
             VALUES
                (:idAcolhimento, :idLeito, :bloco, :quarto, :leito,
                 CURRENT_TIMESTAMP, :observacoes, :usuario)'
        );
        $insere->execute([
            'idAcolhimento' => $idAcolhimento,
            'idLeito' => $idLeito,
            'bloco' => $leito['nomeBloco'],
            'quarto' => $leito['identificacaoQuarto'],
            'leito' => $leito['identificacaoLeito'],
            'observacoes' => $observacoes !== '' ? $observacoes : null,
            'usuario' => (int) $usuarioAtual['idUsuario'],
        ]);
        $pdo->commit();
        header('Location: ' . appTabUrl('acomodacoes-acolhidos.php?resultado=remanejado#pesquisa'));
        exit;
    } catch (InvalidArgumentException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $erro = $exception->getMessage();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Falha ao registrar acomodação de acolhido: ' . $exception->getMessage());
        $erro = 'Não foi possível registrar a acomodação. Atualize a página e confira a disponibilidade do leito.';
    }
}

$admissoesStatement = $pdo->query(
    'SELECT a.idAcolhimento, a.idAcolhido, a.dataEntrada, acolhido.nomeCompleto
     FROM tbAcolhimento a
     INNER JOIN tbAcolhido acolhido ON acolhido.idAcolhido = a.idAcolhido
     WHERE a.dataSaida IS NULL
     ORDER BY acolhido.nomeCompleto, a.dataEntrada, a.idAcolhimento'
);
$admissoesAbertas = $admissoesStatement->fetchAll(PDO::FETCH_ASSOC);
$leitosDisponiveis = $pdo->query(
    'SELECT l.idLeito, l.identificacaoLeito, q.identificacaoQuarto, b.nomeBloco
     FROM tbLeito l
     INNER JOIN tbQuarto q ON q.idQuarto = l.idQuarto
     INNER JOIN tbBloco b ON b.idBloco = q.idBloco
     WHERE l.statusLeito = \'Ativo\' AND q.ativo = 1 AND b.ativo = 1
       AND NOT EXISTS (
           SELECT 1 FROM tbAcomodacaoAcolhido ocupacao
           WHERE ocupacao.idLeito = l.idLeito AND ocupacao.fimEm IS NULL
       )
     ORDER BY b.nomeBloco, q.identificacaoQuarto, l.identificacaoLeito'
)->fetchAll(PDO::FETCH_ASSOC);
$mapaStatement = $pdo->query(
    'SELECT b.idBloco, b.nomeBloco, b.ativo AS blocoAtivo,
            q.idQuarto, q.identificacaoQuarto, q.ativo AS quartoAtivo,
            l.idLeito, l.identificacaoLeito, l.statusLeito,
            acomodacao.idAcolhimento AS acolhimentoAtual,
            acomodacao.inicioEm AS inicioAcomodacao,
            acolhido.nomeCompleto AS nomeAcolhido
     FROM tbBloco b
     LEFT JOIN tbQuarto q ON q.idBloco = b.idBloco
     LEFT JOIN tbLeito l ON l.idQuarto = q.idQuarto
     LEFT JOIN tbAcomodacaoAcolhido acomodacao
        ON acomodacao.idLeito = l.idLeito AND acomodacao.fimEm IS NULL
     LEFT JOIN tbAcolhimento acolhimento
        ON acolhimento.idAcolhimento = acomodacao.idAcolhimento
     LEFT JOIN tbAcolhido acolhido
        ON acolhido.idAcolhido = acolhimento.idAcolhido
     ORDER BY b.nomeBloco, q.identificacaoQuarto, l.identificacaoLeito'
);
$mapaBlocos = [];
$resumoMapa = ['total' => 0, 'disponiveis' => 0, 'ocupados' => 0, 'manutencao' => 0, 'inativos' => 0, 'operacionais' => 0, 'ocupadosOperacionais' => 0];
while ($itemMapa = $mapaStatement->fetch(PDO::FETCH_ASSOC)) {
    $idBloco = (int) $itemMapa['idBloco'];
    if (!isset($mapaBlocos[$idBloco])) {
        $mapaBlocos[$idBloco] = [
            'nome' => $itemMapa['nomeBloco'],
            'ativo' => (int) $itemMapa['blocoAtivo'] === 1,
            'quartos' => [],
        ];
    }
    if ($itemMapa['idQuarto'] === null) continue;

    $idQuarto = (int) $itemMapa['idQuarto'];
    if (!isset($mapaBlocos[$idBloco]['quartos'][$idQuarto])) {
        $mapaBlocos[$idBloco]['quartos'][$idQuarto] = [
            'identificacao' => $itemMapa['identificacaoQuarto'],
            'ativo' => (int) $itemMapa['quartoAtivo'] === 1,
            'leitos' => [],
        ];
    }
    if ($itemMapa['idLeito'] === null) continue;

    $ocupado = $itemMapa['acolhimentoAtual'] !== null;
    $emManutencao = $itemMapa['statusLeito'] === 'Manutenção';
    $estruturaInativa = !$mapaBlocos[$idBloco]['ativo'] || !$mapaBlocos[$idBloco]['quartos'][$idQuarto]['ativo'];
    if ($itemMapa['statusLeito'] === 'Ativo' && !$estruturaInativa) {
        $resumoMapa['operacionais']++;
        if ($ocupado) $resumoMapa['ocupadosOperacionais']++;
    }
    if ($ocupado) {
        $statusMapa = 'Ocupado';
        $resumoMapa['ocupados']++;
    } elseif ($emManutencao) {
        $statusMapa = 'Manutenção';
        $resumoMapa['manutencao']++;
    } elseif ($itemMapa['statusLeito'] !== 'Ativo' || $estruturaInativa) {
        $statusMapa = 'Inativo';
        $resumoMapa['inativos']++;
    } else {
        $statusMapa = 'Disponível';
        $resumoMapa['disponiveis']++;
    }
    $resumoMapa['total']++;
    $mapaBlocos[$idBloco]['quartos'][$idQuarto]['leitos'][] = [
        'id' => (int) $itemMapa['idLeito'],
        'identificacao' => $itemMapa['identificacaoLeito'],
        'status' => $statusMapa,
        'acolhido' => $itemMapa['nomeAcolhido'],
        'acolhimento' => $itemMapa['acolhimentoAtual'],
        'inicio' => $itemMapa['inicioAcomodacao'],
        'estruturaInativa' => $estruturaInativa,
    ];
}
$todosLeitosCheios = $resumoMapa['operacionais'] > 0
    && $resumoMapa['operacionais'] === $resumoMapa['ocupadosOperacionais'];
$historico = $pdo->query(
    'SELECT h.*, a.idAcolhido, a.dataEntrada, a.dataSaida,
            acolhido.nomeCompleto, criador.idUsuario AS criadoPorId,
            criador.nomeCompleto AS criadoPor, alterador.idUsuario AS alteradoPorId,
            alterador.nomeCompleto AS alteradoPor
     FROM tbAcomodacaoAcolhido h
     INNER JOIN tbAcolhimento a ON a.idAcolhimento = h.idAcolhimento
     INNER JOIN tbAcolhido acolhido ON acolhido.idAcolhido = a.idAcolhido
     INNER JOIN tbUsuario criador ON criador.idUsuario = h.idUsuarioCriacao
     LEFT JOIN tbUsuario alterador ON alterador.idUsuario = h.idUsuarioAlteracao
     ORDER BY h.inicioEm DESC, h.idAcomodacao DESC'
)->fetchAll(PDO::FETCH_ASSOC);
$admissaoSelecionada = null;
foreach ($admissoesAbertas as $admissaoAberta) {
    if ((string) $admissaoAberta['idAcolhimento'] === (string) $dados['idAcolhimento']) {
        $admissaoSelecionada = $admissaoAberta;
        break;
    }
}
$leitoSelecionado = null;
foreach ($leitosDisponiveis as $leitoDisponivel) {
    if ((string) $leitoDisponivel['idLeito'] === (string) $dados['idLeito']) {
        $leitoSelecionado = $leitoDisponivel;
        break;
    }
}
$abaInicial = ($formEnviado && $erro !== '') || in_array($_GET['aba'] ?? null, ['pesquisa', 'manutencao'], true)
    ? (($_GET['aba'] ?? '') === 'pesquisa' ? 'pesquisa' : 'manutencao') : 'pesquisa';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acomodações dos Acolhidos | Sistema Elo</title>
    <link rel="icon" type="image/png" href="<?php echo appEscape($baseUrl . '/assets/imgs/logo-elo.png'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script><script src="<?php echo appEscape($baseUrl . '/assets/js/tailwind-config.js'); ?>"></script><script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="flex h-screen flex-col overflow-hidden bg-gray-100 font-sans text-gray-800">
    <?php include __DIR__ . '/../../../components/gestao-acolhidos/navbar-gestaoacolhidos.php'; ?>
    <div class="flex min-h-0 flex-1 overflow-hidden"><?php include __DIR__ . '/../../../components/gestao-acolhidos/sidebar-gestaoacolhidos.php'; ?>
        <main class="min-h-0 min-w-0 flex-1 overflow-y-auto bg-gray-50 p-4 md:p-6">
            <h1 class="mb-6 text-2xl font-bold text-gray-800">Acomodações dos Acolhidos</h1>
            <?php if ($erro !== ''): ?><p role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"><?php echo appEscape($erro); ?></p>
            <?php elseif ($resultado === 'remanejado'): ?><p role="status" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">Acomodação registrada. A acomodação anterior, se existente, foi encerrada e mantida no histórico.</p><?php endif; ?>
            <?php include __DIR__ . '/../../../components/tabs-pesquisa-manutencao.php'; ?>
            <section id="painel-manutencao" role="tabpanel" aria-labelledby="tab-manutencao" <?php echo $abaInicial === 'manutencao' ? '' : 'hidden'; ?>>
                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:p-6">
                    <div class="mb-5"><h2 class="text-lg font-semibold">Alocar ou remanejar acolhido</h2></div>
                    <?php if (!$admissoesAbertas): ?><p class="mb-4 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">Não há acolhimentos em andamento. Registre primeiro um acolhimento ativo.</p><?php endif; ?>
                    <?php if (!$leitosDisponiveis && $todosLeitosCheios): ?><p role="status" class="mb-4 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">Todos os leitos ativos estão ocupados. Libere um leito ou remaneje um acolhido para continuar.</p>
                    <?php elseif (!$leitosDisponiveis): ?><p class="mb-4 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">Não há leitos ativos disponíveis. Verifique os cadastros de blocos, quartos e leitos.</p><?php endif; ?>
                    <form method="post" class="space-y-5">
                        <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>"><input type="hidden" name="acao" value="salvar">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="relative" data-searchable-combobox>
                                <label for="idAcolhimentoPesquisa" class="text-sm font-medium text-gray-700">Acolhimento ativo *</label>
                                <span class="mt-1 flex"><input id="idAcolhimentoPesquisa" type="search" data-searchable-input required autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="opcoes-acolhimento" value="<?php echo $admissaoSelecionada ? appEscape((int) $admissaoSelecionada['idAcolhimento'] . ' — ' . $admissaoSelecionada['nomeCompleto'] . ' — Entrada: ' . date('d/m/Y', strtotime($admissaoSelecionada['dataEntrada']))) : ''; ?>" placeholder="Pesquise ou selecione um acolhimento" <?php echo !$admissoesAbertas ? 'disabled' : ''; ?> class="min-w-0 flex-1 rounded-l border border-r-0 border-gray-300 px-3 py-2 disabled:bg-gray-100"><button type="button" data-searchable-toggle aria-label="Mostrar acolhimentos ativos" class="inline-flex h-10 w-10 items-center justify-center rounded-r border border-gray-300 bg-gray-50 text-gray-600 disabled:opacity-50" <?php echo !$admissoesAbertas ? 'disabled' : ''; ?>><i class="ph ph-caret-down" aria-hidden="true"></i></button></span>
                                <input type="hidden" name="idAcolhimento" data-searchable-value value="<?php echo appEscape((string) $dados['idAcolhimento']); ?>">
                                <div id="opcoes-acolhimento" data-searchable-results role="listbox" class="absolute z-20 mt-1 hidden max-h-60 w-full overflow-y-auto rounded border border-gray-200 bg-white shadow-lg">
                                    <?php foreach ($admissoesAbertas as $admissaoAberta): ?><button type="button" role="option" data-searchable-option data-value="<?php echo (int) $admissaoAberta['idAcolhimento']; ?>" class="block w-full px-3 py-2 text-left text-sm hover:bg-elo-teal/10"><?php echo appEscape((int) $admissaoAberta['idAcolhimento'] . ' — ' . $admissaoAberta['nomeCompleto'] . ' — Entrada: ' . date('d/m/Y', strtotime($admissaoAberta['dataEntrada']))); ?></button><?php endforeach; ?>
                                </div><p data-searchable-message role="status" class="mt-1 text-xs text-gray-500">Abra a lista ou digite para filtrar.</p>
                            </div>
                            <div class="relative" data-searchable-combobox>
                                <label for="idLeitoPesquisa" class="text-sm font-medium text-gray-700">Leito disponível (bloco › quarto › leito) *</label>
                                <span class="mt-1 flex"><input id="idLeitoPesquisa" type="search" data-searchable-input required autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="opcoes-leito" value="<?php echo $leitoSelecionado ? appEscape($leitoSelecionado['nomeBloco'] . ' › Quarto ' . $leitoSelecionado['identificacaoQuarto'] . ' › Leito ' . $leitoSelecionado['identificacaoLeito']) : ''; ?>" placeholder="Pesquise ou selecione um leito" <?php echo !$leitosDisponiveis ? 'disabled' : ''; ?> class="min-w-0 flex-1 rounded-l border border-r-0 border-gray-300 px-3 py-2 disabled:bg-gray-100"><button type="button" data-searchable-toggle aria-label="Mostrar leitos disponíveis" class="inline-flex h-10 w-10 items-center justify-center rounded-r border border-gray-300 bg-gray-50 text-gray-600 disabled:opacity-50" <?php echo !$leitosDisponiveis ? 'disabled' : ''; ?>><i class="ph ph-caret-down" aria-hidden="true"></i></button></span>
                                <input type="hidden" name="idLeito" data-searchable-value value="<?php echo appEscape((string) $dados['idLeito']); ?>">
                                <div id="opcoes-leito" data-searchable-results role="listbox" class="absolute z-20 mt-1 hidden max-h-60 w-full overflow-y-auto rounded border border-gray-200 bg-white shadow-lg">
                                    <?php foreach ($leitosDisponiveis as $leitoDisponivel): ?><button type="button" role="option" data-searchable-option data-preserve-case data-value="<?php echo (int) $leitoDisponivel['idLeito']; ?>" class="block w-full px-3 py-2 text-left text-sm hover:bg-elo-teal/10"><?php echo appEscape($leitoDisponivel['nomeBloco'] . ' › Quarto ' . $leitoDisponivel['identificacaoQuarto'] . ' › Leito ' . $leitoDisponivel['identificacaoLeito']); ?></button><?php endforeach; ?>
                                </div><p data-searchable-message role="status" class="mt-1 text-xs text-gray-500">A lista exibe somente leitos livres em blocos e quartos ativos.</p>
                            </div>
                            <label class="text-sm font-medium text-gray-700 md:col-span-2">Observações do remanejamento (opcional)<textarea name="observacoes" maxlength="500" rows="3" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"><?php echo appEscape((string) $dados['observacoes']); ?></textarea></label>
                        </div>
                        <div class="flex justify-end gap-2"><a href="<?php echo appEscape(appTabUrl($urlAtual . '#pesquisa')); ?>" data-cancelar-acomodacao class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a><button type="submit" class="rounded bg-elo-teal px-5 py-2 text-sm font-semibold text-white hover:bg-elo-green" <?php echo !$admissoesAbertas || !$leitosDisponiveis ? 'disabled' : ''; ?>>Registrar acomodação</button></div>
                    </form>
                </section>
            </section>
            <section id="painel-pesquisa" role="tabpanel" aria-labelledby="tab-pesquisa" <?php echo $abaInicial === 'pesquisa' ? '' : 'hidden'; ?>>
                <section class="mb-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div class="bg-gradient-to-r from-elo-teal to-elo-green p-5 text-white md:flex md:items-center md:justify-between md:gap-6 md:p-7">
                        <div>
                            <p class="mb-1 text-sm font-medium text-white/80">Visão geral da casa</p>
                            <h2 class="text-2xl font-bold">Mapa de acomodações</h2>
                        </div>
                        <button type="button" data-tab-open="manutencao" class="mt-4 inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-elo-teal shadow-sm transition hover:bg-teal-50 md:mt-0">
                            <i class="ph ph-arrows-left-right text-lg" aria-hidden="true"></i> Alocar / remanejar
                        </button>
                    </div>
                    <div class="p-4 md:p-6">
                        <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-5">
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4"><p class="text-xs font-medium uppercase tracking-wide text-gray-500">Leitos cadastrados</p><p class="mt-1 text-2xl font-bold text-gray-800"><?php echo $resumoMapa['total']; ?></p></div>
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4"><p class="text-xs font-medium uppercase tracking-wide text-emerald-700">Disponíveis</p><p class="mt-1 text-2xl font-bold text-emerald-800"><?php echo $resumoMapa['disponiveis']; ?></p></div>
                            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4"><p class="text-xs font-medium uppercase tracking-wide text-blue-700">Ocupados</p><p class="mt-1 text-2xl font-bold text-blue-800"><?php echo $resumoMapa['ocupados']; ?></p></div>
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4"><p class="text-xs font-medium uppercase tracking-wide text-amber-700">Em manutenção</p><p class="mt-1 text-2xl font-bold text-amber-800"><?php echo $resumoMapa['manutencao']; ?></p></div>
                            <div class="rounded-xl border border-gray-200 bg-gray-100 p-4"><p class="text-xs font-medium uppercase tracking-wide text-gray-600">Inativos</p><p class="mt-1 text-2xl font-bold text-gray-700"><?php echo $resumoMapa['inativos']; ?></p></div>
                        </div>
                        <?php if ($todosLeitosCheios): ?><p role="status" class="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"><i class="ph ph-warning-circle mr-1 align-middle text-lg" aria-hidden="true"></i>Todos os leitos ativos estão ocupados.</p><?php endif; ?>

                        <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <label class="relative block w-full lg:max-w-sm">
                                <span class="sr-only">Buscar bloco, quarto ou leito</span>
                                <i class="ph ph-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" aria-hidden="true"></i>
                                <input id="buscaMapaAcomodacoes" type="search" placeholder="Buscar bloco, quarto ou leito..." class="w-full rounded-lg border border-gray-300 py-2.5 pl-9 pr-3 text-sm focus:border-elo-teal focus:outline-none focus:ring-2 focus:ring-elo-teal/20">
                            </label>
                            <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs text-gray-600" aria-label="Legenda do mapa">
                                <span class="inline-flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-full bg-emerald-500" aria-hidden="true"></i>Disponível</span>
                                <span class="inline-flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-full bg-blue-500" aria-hidden="true"></i>Ocupado</span>
                                <span class="inline-flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-full bg-amber-500" aria-hidden="true"></i>Manutenção</span>
                                <span class="inline-flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-full bg-gray-400" aria-hidden="true"></i>Inativo</span>
                            </div>
                        </div>
                        <p id="mensagemMapaAcomodacoes" role="status" aria-live="polite" class="mb-3 hidden text-sm text-gray-500"></p>

                        <?php if (!$mapaBlocos): ?>
                            <div class="rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 px-5 py-10 text-center">
                                <i class="ph ph-buildings mb-3 inline-block text-4xl text-gray-400" aria-hidden="true"></i>
                                <h3 class="font-semibold text-gray-800">A estrutura ainda não foi cadastrada</h3>
                                <p class="mx-auto mt-1 max-w-lg text-sm text-gray-500">Cadastre os blocos, quartos e leitos para visualizar a ocupação neste mapa.</p>
                                <div class="mt-4 flex flex-wrap justify-center gap-2">
                                    <a href="<?php echo appEscape(appTabUrl($baseUrl . '/pages/gestao-acolhidos/cadastros/blocos.php')); ?>" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cadastrar blocos</a>
                                    <a href="<?php echo appEscape(appTabUrl($baseUrl . '/pages/gestao-acolhidos/cadastros/quartos.php')); ?>" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cadastrar quartos</a>
                                    <a href="<?php echo appEscape(appTabUrl($baseUrl . '/pages/gestao-acolhidos/cadastros/leitos.php')); ?>" class="rounded-lg bg-elo-teal px-3 py-2 text-sm font-medium text-white hover:bg-elo-green">Cadastrar leitos</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div id="mapaBlocos" class="space-y-5">
                                <?php foreach ($mapaBlocos as $bloco): ?>
                                    <details open data-mapa-bloco class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                                        <summary class="group flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 bg-gray-50 px-4 py-3 outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-elo-teal md:px-5">
                                            <div class="flex items-center gap-3">
                                                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-elo-teal/10 text-elo-teal"><i class="ph ph-buildings text-xl" aria-hidden="true"></i></span>
                                                <div><h3 class="font-semibold text-gray-800"><?php echo appEscape($bloco['nome']); ?></h3><p class="text-xs text-gray-500"><?php echo count($bloco['quartos']); ?> quarto(s)</p></div>
                                            </div>
                                            <?php if (!$bloco['ativo']): ?><span class="rounded-full bg-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700">Bloco inativo</span><?php endif; ?>
                                            <i class="ph ph-caret-down text-lg text-gray-500 transition-transform group-open:rotate-180" aria-hidden="true"></i>
                                        </summary>
                                        <div class="space-y-4 p-3 md:p-4">
                                            <?php if (!$bloco['quartos']): ?>
                                                <p class="rounded-lg bg-gray-50 p-4 text-sm text-gray-500">Nenhum quarto cadastrado neste bloco.</p>
                                            <?php endif; ?>
                                            <?php foreach ($bloco['quartos'] as $quarto): ?>
                                                <section class="rounded-lg border border-gray-200 p-3 md:p-4">
                                                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                                        <h4 class="flex items-center gap-2 font-medium text-gray-800"><i class="ph ph-door text-lg text-gray-500" aria-hidden="true"></i> Quarto <?php echo appEscape($quarto['identificacao']); ?></h4>
                                                        <div class="flex items-center gap-2 text-xs text-gray-500">
                                                            <span><?php echo count($quarto['leitos']); ?> leito(s)</span>
                                                            <?php if (!$quarto['ativo']): ?><span class="rounded-full bg-gray-100 px-2 py-1 font-medium text-gray-600">Inativo</span><?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php if (!$quarto['leitos']): ?>
                                                        <p class="rounded-lg bg-gray-50 p-3 text-sm text-gray-500">Nenhum leito cadastrado neste quarto.</p>
                                                    <?php else: ?>
                                                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                                            <?php foreach ($quarto['leitos'] as $leitoMapa):
                                                                $classesLeito = 'border-gray-200 bg-white text-gray-700';
                                                                $classesStatus = 'bg-gray-100 text-gray-700';
                                                                $iconeLeito = 'ph-bed';
                                                                if ($leitoMapa['status'] === 'Disponível') {
                                                                    $classesLeito = 'border-emerald-200 bg-emerald-50 text-emerald-900 hover:border-emerald-400 hover:bg-emerald-100 focus:ring-2 focus:ring-emerald-300';
                                                                    $classesStatus = 'bg-emerald-100 text-emerald-800';
                                                                    $iconeLeito = 'ph-plus-circle';
                                                                } elseif ($leitoMapa['status'] === 'Ocupado') {
                                                                    $classesLeito = 'border-blue-200 bg-blue-50 text-blue-900';
                                                                    $classesStatus = 'bg-blue-100 text-blue-800';
                                                                    $iconeLeito = 'ph-user';
                                                                } elseif ($leitoMapa['status'] === 'Manutenção') {
                                                                    $classesLeito = 'border-amber-200 bg-amber-50 text-amber-900';
                                                                    $classesStatus = 'bg-amber-100 text-amber-800';
                                                                    $iconeLeito = 'ph-wrench';
                                                                }
                                                            ?>
                                                                <?php if ($leitoMapa['status'] === 'Disponível'): ?>
                                                                    <button type="button" data-selecionar-leito="<?php echo $leitoMapa['id']; ?>" aria-label="<?php echo appEscape('Selecionar leito ' . $leitoMapa['identificacao'] . ' do quarto ' . $quarto['identificacao'] . ', bloco ' . $bloco['nome']); ?>" class="group w-full rounded-lg border p-3 text-left transition focus:outline-none <?php echo $classesLeito; ?>">
                                                                <?php else: ?>
                                                                    <div class="w-full rounded-lg border p-3 <?php echo $classesLeito; ?>">
                                                                <?php endif; ?>
                                                                    <div class="flex items-start justify-between gap-2">
                                                                        <span class="flex items-center gap-2 font-semibold"><i class="ph <?php echo $iconeLeito; ?> text-xl" aria-hidden="true"></i> Leito <?php echo appEscape($leitoMapa['identificacao']); ?></span>
                                                                        <span class="rounded-full px-2 py-1 text-[11px] font-semibold <?php echo $classesStatus; ?>"><?php echo appEscape($leitoMapa['status']); ?></span>
                                                                    </div>
                                                                    <?php if ($leitoMapa['status'] === 'Ocupado'): ?>
                                                                        <p class="mt-2 truncate text-sm font-medium"><?php echo appEscape((string) ($leitoMapa['acolhido'] ?? 'Acolhido')); ?></p>
                                                                        <p class="mt-0.5 text-xs opacity-75">Acolhimento #<?php echo (int) $leitoMapa['acolhimento']; ?> · Desde <?php echo appEscape(date('d/m/Y H:i', strtotime($leitoMapa['inicio']))); ?></p>
                                                                        <?php if ($leitoMapa['estruturaInativa']): ?><p class="mt-1 text-xs font-medium text-amber-800">Atenção: quarto ou bloco inativo</p><?php endif; ?>
                                                                    <?php elseif ($leitoMapa['status'] === 'Disponível'): ?>
                                                                        <p class="mt-2 text-xs opacity-80">Clique para selecionar este leito</p>
                                                                    <?php elseif ($leitoMapa['status'] === 'Manutenção'): ?>
                                                                        <p class="mt-2 text-xs opacity-80">Indisponível para alocação</p>
                                                                    <?php else: ?>
                                                                        <p class="mt-2 text-xs opacity-80">Leito ou estrutura inativa</p>
                                                                    <?php endif; ?>
                                                                <?php if ($leitoMapa['status'] === 'Disponível'): ?></button><?php else: ?></div><?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </section>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                <?php endforeach; ?>
                            </div>
                            <p id="mapaSemResultados" class="hidden rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">Nenhum bloco, quarto ou leito corresponde à busca.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><h2 class="text-lg font-semibold">Histórico de acomodações</h2><div class="flex gap-2"><input data-filter-input="listaAcomodacoes" type="search" placeholder="Pesquisar histórico..." aria-label="Pesquisar histórico de acomodações" class="rounded border border-gray-300 px-3 py-2 text-sm"><button type="button" data-tab-open="manutencao" class="whitespace-nowrap rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">+ Alocar / remanejar</button></div></div>
                    <div class="overflow-x-auto"><table class="w-full min-w-[1250px] text-left text-sm"><thead><tr class="border-b bg-gray-50 text-gray-600"><th class="p-3">Acolhido</th><th class="p-3">Bloco</th><th class="p-3">Quarto</th><th class="p-3">Leito</th><th class="p-3">Início</th><th class="p-3">Fim</th><th class="p-3">Observações</th><th class="p-3">Auditoria</th></tr></thead><tbody id="listaAcomodacoes">
                        <?php foreach ($historico as $acomodacao): ?><tr class="border-b hover:bg-gray-50"><td class="p-3 font-medium"><?php echo appEscape($acomodacao['nomeCompleto']); ?><span class="block text-xs font-normal text-gray-500">Acolhimento #<?php echo (int) $acomodacao['idAcolhimento']; ?></span></td><td class="p-3"><?php echo appEscape($acomodacao['blocoSnapshot']); ?></td><td class="p-3"><?php echo appEscape($acomodacao['quartoSnapshot']); ?></td><td class="p-3"><?php echo appEscape($acomodacao['leitoSnapshot']); ?></td><td class="whitespace-nowrap p-3"><?php echo appEscape(date('d/m/Y H:i', strtotime($acomodacao['inicioEm']))); ?></td><td class="whitespace-nowrap p-3"><?php echo $acomodacao['fimEm'] === null ? '<span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800">Atual</span>' : appEscape(date('d/m/Y H:i', strtotime($acomodacao['fimEm']))); ?></td><td class="max-w-xs whitespace-normal p-3"><?php echo appEscape((string) ($acomodacao['observacoes'] ?: '—')); ?></td><td class="min-w-72 p-3 text-xs"><p><b>Criado:</b> <?php echo appEscape($formatarAuditoria($acomodacao['idUsuarioCriacao'], $acomodacao['criadoPor'], $acomodacao['criadoEm'])); ?></p><p class="mt-1"><b>Alterado:</b> <?php echo appEscape($formatarAuditoria($acomodacao['idUsuarioAlteracao'], $acomodacao['alteradoPor'], $acomodacao['atualizadoEm'])); ?></p></td></tr><?php endforeach; ?>
                        <?php if (!$historico): ?><tr><td colspan="8" class="p-8 text-center text-gray-500">Nenhuma acomodação registrada.</td></tr><?php endif; ?>
                    </tbody></table></div>
                </section>
            </section>
        </main>
    </div>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/searchable-combobox.js?v=' . filemtime(__DIR__ . '/../../../assets/js/searchable-combobox.js')); ?>"></script>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
    <script>
        (() => {
            const search = document.getElementById('buscaMapaAcomodacoes');
            const blocks = Array.from(document.querySelectorAll('[data-mapa-bloco]'));
            const noResults = document.getElementById('mapaSemResultados');
            const message = document.getElementById('mensagemMapaAcomodacoes');
            const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR');

            search?.addEventListener('input', () => {
                const query = normalize(search.value.trim());
                let visible = 0;
                blocks.forEach((block) => {
                    const matches = normalize(block.textContent).includes(query);
                    block.hidden = !matches;
                    if (matches) visible++;
                });
                noResults?.classList.toggle('hidden', visible > 0);
            });

            document.querySelectorAll('[data-selecionar-leito]').forEach((button) => {
                button.addEventListener('click', () => {
                    const leitoId = button.dataset.selecionarLeito;
                    const option = Array.from(document.querySelectorAll('#opcoes-leito [data-searchable-option]'))
                        .find((item) => item.dataset.value === leitoId);
                    if (!option) {
                        message.textContent = 'Este leito não está mais disponível. Atualize a página para consultar o mapa atualizado.';
                        message.classList.remove('hidden');
                        return;
                    }
                    option.click();
                    document.getElementById('tab-manutencao')?.click();
                    document.getElementById('idAcolhimentoPesquisa')?.focus();
                });
            });

            document.querySelector('[data-cancelar-acomodacao]')?.addEventListener('click', (event) => {
                event.preventDefault();
                const form = document.querySelector('#painel-manutencao form');
                form?.reset();
                form?.querySelectorAll('[data-searchable-combobox]').forEach((combobox) => {
                    const input = combobox.querySelector('[data-searchable-input]');
                    const value = combobox.querySelector('[data-searchable-value]');
                    const results = combobox.querySelector('[data-searchable-results]');
                    if (input) {
                        input.value = '';
                        input.setCustomValidity('');
                        input.setAttribute('aria-expanded', 'false');
                    }
                    if (value) value.value = '';
                    results?.classList.add('hidden');
                });
                const observations = form?.querySelector('[name="observacoes"]');
                if (observations) observations.value = '';
                document.getElementById('tab-pesquisa')?.click();
            });
        })();
    </script>
</body>
</html>
