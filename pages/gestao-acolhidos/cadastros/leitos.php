<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('gestao-acolhidos.cadastros/leitos');
$pdo = appPdo();
$baseUrl = appBaseUrl();
$urlAtual = $baseUrl . '/pages/gestao-acolhidos/cadastros/leitos.php';
$erro = '';
$resultado = is_scalar($_GET['resultado'] ?? null) ? (string) $_GET['resultado'] : '';
$idInput = $_GET['editar'] ?? null;
$idEdicao = is_scalar($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;
$formEnviado = false;
$dados = ['idLeito' => 0, 'idQuarto' => '', 'identificacaoLeito' => '', 'statusLeito' => 'Ativo', 'descricao' => ''];
$auditoria = ['criadoPorId' => null, 'criadoPor' => null, 'criadoEm' => null, 'alteradoPorId' => null, 'alteradoPor' => null, 'atualizadoEm' => null];
$formatarAuditoria = static function ($id, $nome, $data): string {
    if ($id === null || $nome === null || $data === null) return '—';
    $timestamp = strtotime((string) $data);
    return $timestamp === false ? '—' : (int) $id . ' - ' . $nome . ' - ' . date('d/m/Y H:i', $timestamp);
};
$textoPost = static function (string $campo): string {
    $valor = $_POST[$campo] ?? '';
    return is_scalar($valor) ? trim((string) $valor) : '';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    $acaoInput = $_POST['acao'] ?? '';
    $acao = is_scalar($acaoInput) ? (string) $acaoInput : '';
    try {
        if ($acao === 'salvar') {
            $formEnviado = true;
            $idLeito = $textoPost('idLeito') === '' ? 0 : filter_var($textoPost('idLeito'), FILTER_VALIDATE_INT);
            $idQuarto = filter_var($textoPost('idQuarto'), FILTER_VALIDATE_INT);
            $identificacao = $textoPost('identificacaoLeito');
            $statusLeito = $textoPost('statusLeito');
            $descricao = $textoPost('descricao');
            $dados = ['idLeito' => $idLeito !== false ? (int) $idLeito : 0, 'idQuarto' => $idQuarto !== false ? (int) $idQuarto : '', 'identificacaoLeito' => $identificacao, 'statusLeito' => $statusLeito, 'descricao' => $descricao];
            if ($idLeito === false || $idLeito < 0 || $idQuarto === false || $idQuarto < 1
                || $identificacao === '' || mb_strlen($identificacao, 'UTF-8') > 30
                || !in_array($statusLeito, ['Ativo', 'Manutenção', 'Inativo'], true)
                || mb_strlen($descricao, 'UTF-8') > 255
                || (($idEdicao !== false && $idEdicao > 0) ? $idLeito !== $idEdicao : $idLeito !== 0)) {
                throw new InvalidArgumentException('Confira o quarto, a identificação e o status do leito.');
            }
            $pdo->beginTransaction();
            $leitoAtual = null;
            if ($idLeito > 0) {
                $leitoAtualStatement = $pdo->prepare('SELECT idQuarto, identificacaoLeito FROM tbLeito WHERE idLeito = :id FOR UPDATE');
                $leitoAtualStatement->execute(['id' => $idLeito]);
                $leitoAtual = $leitoAtualStatement->fetch(PDO::FETCH_ASSOC);
                if (!$leitoAtual) throw new InvalidArgumentException('Leito não encontrado.');
            }
            $quartoStatement = $pdo->prepare(
                'SELECT q.ativo AS quartoAtivo, b.ativo AS blocoAtivo
                 FROM tbQuarto q INNER JOIN tbBloco b ON b.idBloco = q.idBloco
                 WHERE q.idQuarto = :id FOR UPDATE'
            );
            $quartoStatement->execute(['id' => $idQuarto]);
            $quartoSelecionado = $quartoStatement->fetch(PDO::FETCH_ASSOC);
            if (!$quartoSelecionado) throw new InvalidArgumentException('O quarto selecionado não foi encontrado.');

            if ((int) $quartoSelecionado['quartoAtivo'] !== 1 || (int) $quartoSelecionado['blocoAtivo'] !== 1) {
                if ((int) ($leitoAtual['idQuarto'] ?? 0) !== $idQuarto) throw new InvalidArgumentException('Selecione um quarto e um bloco ativos.');
            }
            if ($idLeito > 0) {
                $ocupacaoStatement = $pdo->prepare('SELECT idAcomodacao FROM tbAcomodacaoAcolhido WHERE idLeito = :id AND fimEm IS NULL LIMIT 1 FOR UPDATE');
                $ocupacaoStatement->execute(['id' => $idLeito]);
                if ($ocupacaoStatement->fetchColumn()
                    && ($statusLeito !== 'Ativo'
                        || (int) $leitoAtual['idQuarto'] !== $idQuarto
                        || $leitoAtual['identificacaoLeito'] !== $identificacao)) {
                    throw new InvalidArgumentException('Remaneje o acolhido antes de alterar ou inativar o leito ocupado.');
                }
            }

            if ($idLeito > 0) {
                $statement = $pdo->prepare('UPDATE tbLeito SET idQuarto = :quarto, identificacaoLeito = :identificacao, statusLeito = :status, descricao = :descricao, idUsuarioAlteracao = :usuario WHERE idLeito = :id');
                $statement->execute(['quarto' => $idQuarto, 'identificacao' => $identificacao, 'status' => $statusLeito, 'descricao' => $descricao !== '' ? $descricao : null, 'usuario' => (int) $usuarioAtual['idUsuario'], 'id' => $idLeito]);
                $pdo->commit();
                $resultadoSalvo = 'atualizado';
            } else {
                $statement = $pdo->prepare('INSERT INTO tbLeito (idQuarto, identificacaoLeito, statusLeito, descricao, idUsuarioCriacao) VALUES (:quarto, :identificacao, :status, :descricao, :usuario)');
                $statement->execute(['quarto' => $idQuarto, 'identificacao' => $identificacao, 'status' => $statusLeito, 'descricao' => $descricao !== '' ? $descricao : null, 'usuario' => (int) $usuarioAtual['idUsuario']]);
                $pdo->commit();
                $resultadoSalvo = 'criado';
            }
            header('Location: ' . appTabUrl('leitos.php?resultado=' . $resultadoSalvo . '#pesquisa'));
            exit;
        }
        if ($acao === 'excluir') {
            $id = filter_var($textoPost('idLeito'), FILTER_VALIDATE_INT);
            if (!$id || $id < 1) throw new InvalidArgumentException('Leito inválido.');
            $statement = $pdo->prepare('DELETE FROM tbLeito WHERE idLeito = :id');
            $statement->execute(['id' => $id]);
            if ($statement->rowCount() === 0) throw new InvalidArgumentException('Leito não encontrado.');
            header('Location: ' . appTabUrl('leitos.php?resultado=excluido#pesquisa'));
            exit;
        }
        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $erro = $exception->getMessage();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Falha ao salvar leito: ' . $exception->getMessage());
        $erro = (int) ($exception->errorInfo[1] ?? 0) === 1062
            ? 'Já existe esse identificador de leito no quarto selecionado.'
            : ((int) ($exception->errorInfo[1] ?? 0) === 1451
                ? 'Este leito possui histórico de acomodações e não pode ser excluído.'
                : 'Não foi possível concluir a operação com o leito.');
    }
}

if ($idEdicao !== false && $idEdicao > 0) {
    $statement = $pdo->prepare(
        'SELECT l.*, q.identificacaoQuarto, b.idBloco, b.nomeBloco,
                c.idUsuario AS criadoPorId, c.nomeCompleto AS criadoPor,
                a.idUsuario AS alteradoPorId, a.nomeCompleto AS alteradoPor
         FROM tbLeito l
         INNER JOIN tbQuarto q ON q.idQuarto = l.idQuarto
         INNER JOIN tbBloco b ON b.idBloco = q.idBloco
         INNER JOIN tbUsuario c ON c.idUsuario = l.idUsuarioCriacao
         LEFT JOIN tbUsuario a ON a.idUsuario = l.idUsuarioAlteracao
         WHERE l.idLeito = :id'
    );
    $statement->execute(['id' => $idEdicao]);
    $registro = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$registro) {
        $erro = $erro ?: 'Leito não encontrado.';
    } else {
        $auditoria = ['criadoPorId' => $registro['criadoPorId'], 'criadoPor' => $registro['criadoPor'], 'criadoEm' => $registro['criadoEm'], 'alteradoPorId' => $registro['alteradoPorId'], 'alteradoPor' => $registro['alteradoPor'], 'atualizadoEm' => $registro['atualizadoEm']];
        if (!$formEnviado) $dados = ['idLeito' => (int) $registro['idLeito'], 'idQuarto' => (int) $registro['idQuarto'], 'identificacaoLeito' => $registro['identificacaoLeito'], 'statusLeito' => $registro['statusLeito'], 'descricao' => (string) ($registro['descricao'] ?? '')];
    }
}
$quartosStatement = $pdo->prepare(
    'SELECT q.idQuarto, q.identificacaoQuarto, q.ativo, b.nomeBloco, b.ativo AS blocoAtivo
     FROM tbQuarto q INNER JOIN tbBloco b ON b.idBloco = q.idBloco
     WHERE (q.ativo = 1 AND b.ativo = 1) OR q.idQuarto = :idEdicao
     ORDER BY b.nomeBloco, q.identificacaoQuarto'
);
$quartosStatement->execute(['idEdicao' => (int) ($dados['idQuarto'] ?: 0)]);
$quartos = $quartosStatement->fetchAll(PDO::FETCH_ASSOC);
$leitos = $pdo->query(
    'SELECT l.*, q.identificacaoQuarto, b.nomeBloco,
            c.idUsuario AS criadoPorId, c.nomeCompleto AS criadoPor,
            a.idUsuario AS alteradoPorId, a.nomeCompleto AS alteradoPor,
            atual.nomeCompleto AS nomeAcolhidoAtual,
            (SELECT COUNT(*) FROM tbAcomodacaoAcolhido h WHERE h.idLeito = l.idLeito) AS totalHistorico
     FROM tbLeito l
     INNER JOIN tbQuarto q ON q.idQuarto = l.idQuarto
     INNER JOIN tbBloco b ON b.idBloco = q.idBloco
     INNER JOIN tbUsuario c ON c.idUsuario = l.idUsuarioCriacao
     LEFT JOIN tbUsuario a ON a.idUsuario = l.idUsuarioAlteracao
     LEFT JOIN tbAcomodacaoAcolhido ocupacao ON ocupacao.idLeito = l.idLeito AND ocupacao.fimEm IS NULL
     LEFT JOIN tbAcolhimento acolhimento ON acolhimento.idAcolhimento = ocupacao.idAcolhimento
     LEFT JOIN tbAcolhido atual ON atual.idAcolhido = acolhimento.idAcolhido
     ORDER BY b.nomeBloco, q.identificacaoQuarto, l.identificacaoLeito'
)->fetchAll(PDO::FETCH_ASSOC);
$abaInicial = (($idEdicao !== false && $idEdicao > 0) || ($formEnviado && $erro !== '') || in_array($_GET['aba'] ?? null, ['pesquisa', 'manutencao'], true))
    ? (($_GET['aba'] ?? '') === 'pesquisa' ? 'pesquisa' : 'manutencao') : 'pesquisa';
$mensagens = ['criado' => 'Leito cadastrado.', 'atualizado' => 'Leito atualizado.', 'excluido' => 'Leito excluído.'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leitos | Sistema Elo</title>
    <link rel="icon" type="image/png" href="<?php echo appEscape($baseUrl . '/assets/imgs/logo-elo.png'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script><script src="<?php echo appEscape($baseUrl . '/assets/js/tailwind-config.js'); ?>"></script><script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="flex h-screen flex-col overflow-hidden bg-gray-100 font-sans text-gray-800">
    <?php include __DIR__ . '/../../../components/gestao-acolhidos/navbar-gestaoacolhidos.php'; ?>
    <div class="flex min-h-0 flex-1 overflow-hidden"><?php include __DIR__ . '/../../../components/gestao-acolhidos/sidebar-gestaoacolhidos.php'; ?>
        <main class="min-h-0 min-w-0 flex-1 overflow-y-auto bg-gray-50 p-4 md:p-6">
            <h1 class="mb-6 text-2xl font-bold text-gray-800">Leitos</h1>
            <?php if ($erro !== ''): ?><p role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"><?php echo appEscape($erro); ?></p>
            <?php elseif (isset($mensagens[$resultado])): ?><p role="status" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800"><?php echo appEscape($mensagens[$resultado]); ?></p><?php endif; ?>
            <?php include __DIR__ . '/../../../components/tabs-pesquisa-manutencao.php'; ?>
            <section id="painel-manutencao" role="tabpanel" aria-labelledby="tab-manutencao" <?php echo $abaInicial === 'manutencao' ? '' : 'hidden'; ?>>
                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:p-6">
                    <div class="mb-5 flex items-start justify-between gap-3"><div><h2 class="text-lg font-semibold"><?php echo (int) $dados['idLeito'] > 0 ? 'Editar leito' : 'Novo leito'; ?></h2><p class="mt-1 text-sm text-gray-500">Cada leito é controlado individualmente para permitir ocupação e remanejamento.</p></div><?php if ((int) $dados['idLeito'] > 0): ?><a href="<?php echo appEscape(appTabUrl($urlAtual . '?aba=manutencao#manutencao')); ?>" class="text-sm font-medium text-elo-teal hover:underline">Novo leito</a><?php endif; ?></div>
                    <?php if (!$quartos): ?><p class="mb-4 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">Cadastre primeiro um quarto em bloco ativo. <a href="<?php echo appEscape(appTabUrl($baseUrl . '/pages/gestao-acolhidos/cadastros/quartos.php?aba=manutencao#manutencao')); ?>" class="font-semibold underline">Ir para Quartos</a></p><?php endif; ?>
                    <form method="post" class="space-y-5">
                        <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>"><input type="hidden" name="acao" value="salvar"><input type="hidden" name="idLeito" value="<?php echo (int) $dados['idLeito']; ?>">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="relative" data-searchable-combobox>
                                <label for="idQuartoPesquisa" class="text-sm font-medium text-gray-700">Quarto *</label>
                                <span class="mt-1 flex"><input id="idQuartoPesquisa" type="search" data-searchable-input required autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="opcoes-quarto" value="<?php
                                    $quartoAtual = null;
                                    foreach ($quartos as $quarto) if ((string) $quarto['idQuarto'] === (string) $dados['idQuarto']) $quartoAtual = $quarto;
                                    echo $quartoAtual ? appEscape($quartoAtual['nomeBloco'] . ' — Quarto ' . $quartoAtual['identificacaoQuarto'] . ((int) $quartoAtual['ativo'] === 1 && (int) $quartoAtual['blocoAtivo'] === 1 ? '' : ' (Inativo)')) : '';
                                ?>" placeholder="Pesquise ou selecione um quarto" <?php echo !$quartos ? 'disabled' : ''; ?> class="min-w-0 flex-1 rounded-l border border-r-0 border-gray-300 px-3 py-2 disabled:bg-gray-100">
                                    <button type="button" data-searchable-toggle aria-label="Mostrar quartos disponíveis" class="inline-flex h-10 w-10 items-center justify-center rounded-r border border-gray-300 bg-gray-50 text-gray-600 disabled:opacity-50" <?php echo !$quartos ? 'disabled' : ''; ?>><i class="ph ph-caret-down" aria-hidden="true"></i></button></span>
                                <input type="hidden" name="idQuarto" data-searchable-value value="<?php echo appEscape((string) $dados['idQuarto']); ?>">
                                <div id="opcoes-quarto" data-searchable-results role="listbox" class="absolute z-20 mt-1 hidden max-h-60 w-full overflow-y-auto rounded border border-gray-200 bg-white shadow-lg">
                                    <?php foreach ($quartos as $quarto): ?><button type="button" role="option" data-searchable-option data-value="<?php echo (int) $quarto['idQuarto']; ?>" class="block w-full px-3 py-2 text-left text-sm hover:bg-elo-teal/10"><?php echo appEscape($quarto['nomeBloco'] . ' — Quarto ' . $quarto['identificacaoQuarto'] . ((int) $quarto['ativo'] === 1 && (int) $quarto['blocoAtivo'] === 1 ? '' : ' (Inativo)')); ?></button><?php endforeach; ?>
                                </div><p data-searchable-message role="status" class="mt-1 text-xs text-gray-500">Abra a lista ou digite para filtrar.</p>
                            </div>
                            <label class="text-sm font-medium text-gray-700">Identificação do leito *<input name="identificacaoLeito" required maxlength="30" value="<?php echo appEscape((string) $dados['identificacaoLeito']); ?>" placeholder="Ex.: Cama 1" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"></label>
                            <label class="text-sm font-medium text-gray-700">Situação do leito
                                <select name="statusLeito" required class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2"><?php foreach (['Ativo', 'Manutenção', 'Inativo'] as $status): ?><option value="<?php echo appEscape($status); ?>" <?php echo $dados['statusLeito'] === $status ? 'selected' : ''; ?>><?php echo appEscape($status); ?></option><?php endforeach; ?></select>
                            </label>
                            <label class="text-sm font-medium text-gray-700 md:col-span-2">Descrição (opcional)<textarea name="descricao" maxlength="255" rows="3" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"><?php echo appEscape((string) $dados['descricao']); ?></textarea></label>
                        </div>
                        <fieldset class="grid gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm md:grid-cols-2"><legend class="px-2 font-semibold text-gray-700">Auditoria</legend><label class="font-medium text-gray-700">Criado por<input disabled value="<?php echo appEscape($formatarAuditoria($auditoria['criadoPorId'], $auditoria['criadoPor'], $auditoria['criadoEm'])); ?>" class="mt-1 w-full rounded border border-gray-200 bg-white px-3 py-2 text-gray-600"></label><label class="font-medium text-gray-700">Alterado por<input disabled value="<?php echo appEscape($formatarAuditoria($auditoria['alteradoPorId'], $auditoria['alteradoPor'], $auditoria['atualizadoEm'])); ?>" class="mt-1 w-full rounded border border-gray-200 bg-white px-3 py-2 text-gray-600"></label></fieldset>
                        <div class="flex justify-end gap-2"><a href="<?php echo appEscape(appTabUrl($urlAtual . '#pesquisa')); ?>" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a><button type="submit" class="rounded bg-elo-teal px-5 py-2 text-sm font-semibold text-white hover:bg-elo-green" <?php echo !$quartos ? 'disabled' : ''; ?>><?php echo (int) $dados['idLeito'] > 0 ? 'Salvar alterações' : 'Cadastrar leito'; ?></button></div>
                    </form>
                </section>
            </section>
            <section id="painel-pesquisa" role="tabpanel" aria-labelledby="tab-pesquisa" <?php echo $abaInicial === 'pesquisa' ? '' : 'hidden'; ?>>
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><h2 class="text-lg font-semibold">Leitos cadastrados</h2><div class="flex gap-2"><input data-filter-input="listaLeitos" type="search" placeholder="Pesquisar leitos..." aria-label="Pesquisar leitos" class="rounded border border-gray-300 px-3 py-2 text-sm"><a href="<?php echo appEscape(appTabUrl($urlAtual . '?aba=manutencao#manutencao')); ?>" class="whitespace-nowrap rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">+ Novo leito</a></div></div>
                    <div class="overflow-x-auto"><table class="w-full min-w-[1050px] text-left text-sm"><thead><tr class="border-b bg-gray-50 text-gray-600"><th class="p-3">Bloco</th><th class="p-3">Quarto</th><th class="p-3">Leito</th><th class="p-3">Ocupação</th><th class="p-3">Situação</th><th class="p-3">Auditoria</th><th class="p-3">Ações</th></tr></thead><tbody id="listaLeitos">
                        <?php foreach ($leitos as $leito):
                            $classesStatus = $leito['statusLeito'] === 'Ativo'
                                ? 'bg-emerald-100 text-emerald-800'
                                : ($leito['statusLeito'] === 'Manutenção' ? 'bg-amber-100 text-amber-800' : 'bg-gray-200 text-gray-700');
                        ?><tr class="border-b hover:bg-gray-50"><td class="p-3"><?php echo appEscape($leito['nomeBloco']); ?></td><td class="p-3"><?php echo appEscape($leito['identificacaoQuarto']); ?></td><td class="p-3 font-medium"><?php echo (int) $leito['idLeito']; ?> — <?php echo appEscape($leito['identificacaoLeito']); ?></td><td class="p-3"><?php echo $leito['nomeAcolhidoAtual'] ? 'Ocupado por ' . appEscape($leito['nomeAcolhidoAtual']) : 'Disponível'; ?></td><td class="p-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?php echo $classesStatus; ?>"><?php echo appEscape((string) $leito['statusLeito']); ?></span></td><td class="min-w-72 p-3 text-xs"><p><b>Criado:</b> <?php echo appEscape($formatarAuditoria($leito['criadoPorId'], $leito['criadoPor'], $leito['criadoEm'])); ?></p><p class="mt-1"><b>Alterado:</b> <?php echo appEscape($formatarAuditoria($leito['alteradoPorId'], $leito['alteradoPor'], $leito['atualizadoEm'])); ?></p></td><td class="p-3"><div class="flex gap-2"><a href="<?php echo appEscape(appTabUrl($urlAtual . '?editar=' . (int) $leito['idLeito'] . '#manutencao')); ?>" class="inline-flex h-9 w-9 items-center justify-center rounded border text-elo-teal" aria-label="Editar leito"><i class="ph ph-pencil-simple" aria-hidden="true"></i></a><form method="post" data-confirm="Excluir este leito?"><input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="idLeito" value="<?php echo (int) $leito['idLeito']; ?>"><button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded border border-red-200 text-red-600" aria-label="Excluir leito"><i class="ph ph-trash" aria-hidden="true"></i></button></form></div></td></tr><?php endforeach; ?>
                        <?php if (!$leitos): ?><tr><td colspan="7" class="p-8 text-center text-gray-500">Nenhum leito cadastrado.</td></tr><?php endif; ?>
                    </tbody></table></div>
                </section>
            </section>
        </main>
    </div>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/searchable-combobox.js?v=' . filemtime(__DIR__ . '/../../../assets/js/searchable-combobox.js')); ?>"></script>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
</body>
</html>
