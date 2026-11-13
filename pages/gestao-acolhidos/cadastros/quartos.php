<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('gestao-acolhidos.cadastros/quartos');
$pdo = appPdo();
$baseUrl = appBaseUrl();
$urlAtual = $baseUrl . '/pages/gestao-acolhidos/cadastros/quartos.php';
$erro = '';
$resultado = is_scalar($_GET['resultado'] ?? null) ? (string) $_GET['resultado'] : '';
$idInput = $_GET['editar'] ?? null;
$idEdicao = is_scalar($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;
$formEnviado = false;
$dados = ['idQuarto' => 0, 'idBloco' => '', 'identificacaoQuarto' => '', 'descricao' => '', 'ativo' => 1];
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
            $idQuarto = $textoPost('idQuarto') === '' ? 0 : filter_var($textoPost('idQuarto'), FILTER_VALIDATE_INT);
            $idBloco = filter_var($textoPost('idBloco'), FILTER_VALIDATE_INT);
            $identificacao = $textoPost('identificacaoQuarto');
            $descricao = $textoPost('descricao');
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $dados = ['idQuarto' => $idQuarto !== false ? (int) $idQuarto : 0, 'idBloco' => $idBloco !== false ? (int) $idBloco : '', 'identificacaoQuarto' => $identificacao, 'descricao' => $descricao, 'ativo' => $ativo];
            if ($idQuarto === false || $idQuarto < 0 || $idBloco === false || $idBloco < 1
                || $identificacao === '' || mb_strlen($identificacao, 'UTF-8') > 30 || mb_strlen($descricao, 'UTF-8') > 255
                || (($idEdicao !== false && $idEdicao > 0) ? $idQuarto !== $idEdicao : $idQuarto !== 0)) {
                throw new InvalidArgumentException('Confira o bloco, a identificação e a descrição do quarto.');
            }
            $blocoStatement = $pdo->prepare('SELECT ativo FROM tbBloco WHERE idBloco = :id');
            $blocoStatement->execute(['id' => $idBloco]);
            $blocoAtivo = $blocoStatement->fetchColumn();
            if ($blocoAtivo === false) throw new InvalidArgumentException('O bloco selecionado não foi encontrado.');
            $quartoAtual = null;
            if ($idQuarto > 0) {
                $quartoAtual = $pdo->prepare('SELECT idBloco FROM tbQuarto WHERE idQuarto = :id');
                $quartoAtual->execute(['id' => $idQuarto]);
                $blocoAtualId = $quartoAtual->fetchColumn();
                if ($blocoAtualId === false) throw new InvalidArgumentException('Quarto não encontrado.');
            }
            if ((int) $blocoAtivo !== 1) {
                if ((int) ($blocoAtualId ?? 0) !== $idBloco) throw new InvalidArgumentException('Só é possível manter um quarto existente em um bloco inativo.');
            }
            if ($idQuarto > 0 && $ativo === 0) {
                $pdo->beginTransaction();
                $quartoLock = $pdo->prepare('SELECT idQuarto FROM tbQuarto WHERE idQuarto = :id FOR UPDATE');
                $quartoLock->execute(['id' => $idQuarto]);
                if (!$quartoLock->fetchColumn()) throw new InvalidArgumentException('Quarto não encontrado.');
                $ocupacaoStatement = $pdo->prepare(
                    'SELECT h.idAcomodacao
                     FROM tbQuarto q
                     INNER JOIN tbLeito l ON l.idQuarto = q.idQuarto
                     INNER JOIN tbAcomodacaoAcolhido h ON h.idLeito = l.idLeito AND h.fimEm IS NULL
                     WHERE q.idQuarto = :id LIMIT 1'
                );
                $ocupacaoStatement->execute(['id' => $idQuarto]);
                if ($ocupacaoStatement->fetchColumn()) throw new InvalidArgumentException('Remaneje os acolhidos do quarto antes de inativá-lo.');
            }
            if ($idQuarto > 0) {
                $statement = $pdo->prepare('UPDATE tbQuarto SET idBloco = :bloco, identificacaoQuarto = :identificacao, descricao = :descricao, ativo = :ativo, idUsuarioAlteracao = :usuario WHERE idQuarto = :id');
                $statement->execute(['bloco' => $idBloco, 'identificacao' => $identificacao, 'descricao' => $descricao !== '' ? $descricao : null, 'ativo' => $ativo, 'usuario' => (int) $usuarioAtual['idUsuario'], 'id' => $idQuarto]);
                if ($pdo->inTransaction()) $pdo->commit();
                $resultadoSalvo = 'atualizado';
            } else {
                $statement = $pdo->prepare('INSERT INTO tbQuarto (idBloco, identificacaoQuarto, descricao, ativo, idUsuarioCriacao) VALUES (:bloco, :identificacao, :descricao, :ativo, :usuario)');
                $statement->execute(['bloco' => $idBloco, 'identificacao' => $identificacao, 'descricao' => $descricao !== '' ? $descricao : null, 'ativo' => $ativo, 'usuario' => (int) $usuarioAtual['idUsuario']]);
                $resultadoSalvo = 'criado';
            }
            header('Location: ' . appTabUrl('quartos.php?resultado=' . $resultadoSalvo . '#pesquisa'));
            exit;
        }
        if ($acao === 'excluir') {
            $id = filter_var($textoPost('idQuarto'), FILTER_VALIDATE_INT);
            if (!$id || $id < 1) throw new InvalidArgumentException('Quarto inválido.');
            $statement = $pdo->prepare('DELETE FROM tbQuarto WHERE idQuarto = :id');
            $statement->execute(['id' => $id]);
            if ($statement->rowCount() === 0) throw new InvalidArgumentException('Quarto não encontrado.');
            header('Location: ' . appTabUrl('quartos.php?resultado=excluido#pesquisa'));
            exit;
        }
        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $erro = $exception->getMessage();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Falha ao salvar quarto: ' . $exception->getMessage());
        $erro = (int) ($exception->errorInfo[1] ?? 0) === 1062
            ? 'Já existe esse número/identificação de quarto no bloco selecionado.'
            : ((int) ($exception->errorInfo[1] ?? 0) === 1451
                ? 'Não é possível excluir um quarto com leitos ou histórico de acomodações.'
                : 'Não foi possível concluir a operação com o quarto.');
    }
}

if ($idEdicao !== false && $idEdicao > 0) {
    $statement = $pdo->prepare(
        'SELECT q.*, b.nomeBloco, c.idUsuario AS criadoPorId, c.nomeCompleto AS criadoPor,
                a.idUsuario AS alteradoPorId, a.nomeCompleto AS alteradoPor
         FROM tbQuarto q
         INNER JOIN tbBloco b ON b.idBloco = q.idBloco
         INNER JOIN tbUsuario c ON c.idUsuario = q.idUsuarioCriacao
         LEFT JOIN tbUsuario a ON a.idUsuario = q.idUsuarioAlteracao
         WHERE q.idQuarto = :id'
    );
    $statement->execute(['id' => $idEdicao]);
    $registro = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$registro) {
        $erro = $erro ?: 'Quarto não encontrado.';
    } else {
        $auditoria = ['criadoPorId' => $registro['criadoPorId'], 'criadoPor' => $registro['criadoPor'], 'criadoEm' => $registro['criadoEm'], 'alteradoPorId' => $registro['alteradoPorId'], 'alteradoPor' => $registro['alteradoPor'], 'atualizadoEm' => $registro['atualizadoEm']];
        if (!$formEnviado) $dados = ['idQuarto' => (int) $registro['idQuarto'], 'idBloco' => (int) $registro['idBloco'], 'identificacaoQuarto' => $registro['identificacaoQuarto'], 'descricao' => (string) ($registro['descricao'] ?? ''), 'ativo' => (int) $registro['ativo']];
    }
}
$blocosStatement = $pdo->prepare(
    'SELECT idBloco, nomeBloco, ativo FROM tbBloco
     WHERE ativo = 1 OR idBloco = :idEdicao ORDER BY nomeBloco'
);
$blocosStatement->execute(['idEdicao' => (int) ($dados['idBloco'] ?: 0)]);
$blocos = $blocosStatement->fetchAll(PDO::FETCH_ASSOC);
$quartos = $pdo->query(
    'SELECT q.*, b.nomeBloco, c.idUsuario AS criadoPorId, c.nomeCompleto AS criadoPor,
            a.idUsuario AS alteradoPorId, a.nomeCompleto AS alteradoPor,
            (SELECT COUNT(*) FROM tbLeito l WHERE l.idQuarto = q.idQuarto) AS totalLeitos
     FROM tbQuarto q
     INNER JOIN tbBloco b ON b.idBloco = q.idBloco
     INNER JOIN tbUsuario c ON c.idUsuario = q.idUsuarioCriacao
     LEFT JOIN tbUsuario a ON a.idUsuario = q.idUsuarioAlteracao
     ORDER BY b.nomeBloco, q.identificacaoQuarto'
)->fetchAll(PDO::FETCH_ASSOC);
$abaInicial = (($idEdicao !== false && $idEdicao > 0) || ($formEnviado && $erro !== '') || in_array($_GET['aba'] ?? null, ['pesquisa', 'manutencao'], true))
    ? (($_GET['aba'] ?? '') === 'pesquisa' ? 'pesquisa' : 'manutencao') : 'pesquisa';
$mensagens = ['criado' => 'Quarto cadastrado.', 'atualizado' => 'Quarto atualizado.', 'excluido' => 'Quarto excluído.'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quartos | Sistema Elo</title>
    <link rel="icon" type="image/png" href="<?php echo appEscape($baseUrl . '/assets/imgs/logo-elo.png'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script><script src="<?php echo appEscape($baseUrl . '/assets/js/tailwind-config.js'); ?>"></script><script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="flex h-screen flex-col overflow-hidden bg-gray-100 font-sans text-gray-800">
    <?php include __DIR__ . '/../../../components/gestao-acolhidos/navbar-gestaoacolhidos.php'; ?>
    <div class="flex min-h-0 flex-1 overflow-hidden"><?php include __DIR__ . '/../../../components/gestao-acolhidos/sidebar-gestaoacolhidos.php'; ?>
        <main class="min-h-0 min-w-0 flex-1 overflow-y-auto bg-gray-50 p-4 md:p-6">
            <h1 class="mb-6 text-2xl font-bold text-gray-800">Quartos</h1>
            <?php if ($erro !== ''): ?><p role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"><?php echo appEscape($erro); ?></p>
            <?php elseif (isset($mensagens[$resultado])): ?><p role="status" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800"><?php echo appEscape($mensagens[$resultado]); ?></p><?php endif; ?>
            <?php include __DIR__ . '/../../../components/tabs-pesquisa-manutencao.php'; ?>
            <section id="painel-manutencao" role="tabpanel" aria-labelledby="tab-manutencao" <?php echo $abaInicial === 'manutencao' ? '' : 'hidden'; ?>>
                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:p-6">
                    <div class="mb-5 flex items-start justify-between gap-3"><div><h2 class="text-lg font-semibold"><?php echo (int) $dados['idQuarto'] > 0 ? 'Editar quarto' : 'Novo quarto'; ?></h2><p class="mt-1 text-sm text-gray-500">Identifique o quarto e o bloco ao qual pertence.</p></div><?php if ((int) $dados['idQuarto'] > 0): ?><a href="<?php echo appEscape(appTabUrl($urlAtual . '?aba=manutencao#manutencao')); ?>" class="text-sm font-medium text-elo-teal hover:underline">Novo quarto</a><?php endif; ?></div>
                    <?php if (!$blocos): ?><p class="mb-4 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">Cadastre primeiro um bloco ativo. <a href="<?php echo appEscape(appTabUrl($baseUrl . '/pages/gestao-acolhidos/cadastros/blocos.php?aba=manutencao#manutencao')); ?>" class="font-semibold underline">Ir para Blocos</a></p><?php endif; ?>
                    <form method="post" class="space-y-5">
                        <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>"><input type="hidden" name="acao" value="salvar"><input type="hidden" name="idQuarto" value="<?php echo (int) $dados['idQuarto']; ?>">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="relative" data-searchable-combobox>
                                <label for="idBlocoPesquisa" class="text-sm font-medium text-gray-700">Bloco *</label>
                                <span class="mt-1 flex"><input id="idBlocoPesquisa" type="search" data-searchable-input required autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="opcoes-bloco" value="<?php
                                    $blocoAtual = null;
                                    foreach ($blocos as $bloco) if ((string) $bloco['idBloco'] === (string) $dados['idBloco']) $blocoAtual = $bloco;
                                    echo $blocoAtual ? appEscape($blocoAtual['nomeBloco']) : '';
                                ?>" placeholder="Pesquise ou selecione um bloco" <?php echo !$blocos ? 'disabled' : ''; ?> class="min-w-0 flex-1 rounded-l border border-r-0 border-gray-300 px-3 py-2 disabled:bg-gray-100">
                                    <button type="button" data-searchable-toggle aria-label="Mostrar blocos disponíveis" class="inline-flex h-10 w-10 items-center justify-center rounded-r border border-gray-300 bg-gray-50 text-gray-600 disabled:opacity-50" <?php echo !$blocos ? 'disabled' : ''; ?>><i class="ph ph-caret-down" aria-hidden="true"></i></button></span>
                                <input type="hidden" name="idBloco" data-searchable-value value="<?php echo appEscape((string) $dados['idBloco']); ?>">
                                <div id="opcoes-bloco" data-searchable-results role="listbox" class="absolute z-20 mt-1 hidden max-h-60 w-full overflow-y-auto rounded border border-gray-200 bg-white shadow-lg">
                                    <?php foreach ($blocos as $bloco): ?><button type="button" role="option" data-searchable-option data-preserve-case data-value="<?php echo (int) $bloco['idBloco']; ?>" class="block w-full px-3 py-2 text-left text-sm hover:bg-elo-teal/10"><?php echo appEscape($bloco['nomeBloco']); ?></button><?php endforeach; ?>
                                </div>
                                <p data-searchable-message role="status" class="mt-1 text-xs text-gray-500">Abra a lista ou digite para filtrar.</p>
                            </div>
                            <label class="text-sm font-medium text-gray-700">Número / identificação do quarto *<input name="identificacaoQuarto" required maxlength="30" value="<?php echo appEscape((string) $dados['identificacaoQuarto']); ?>" placeholder="Ex.: 01" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"></label>
                            <label class="flex items-center gap-2 self-end pb-2 text-sm font-medium text-gray-700"><input type="checkbox" name="ativo" value="1" <?php echo (int) $dados['ativo'] === 1 ? 'checked' : ''; ?> class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">Quarto ativo</label>
                            <label class="text-sm font-medium text-gray-700 md:col-span-2">Descrição (opcional)<textarea name="descricao" maxlength="255" rows="3" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"><?php echo appEscape((string) $dados['descricao']); ?></textarea></label>
                        </div>
                        <fieldset class="grid gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm md:grid-cols-2"><legend class="px-2 font-semibold text-gray-700">Auditoria</legend><label class="font-medium text-gray-700">Criado por<input disabled value="<?php echo appEscape($formatarAuditoria($auditoria['criadoPorId'], $auditoria['criadoPor'], $auditoria['criadoEm'])); ?>" class="mt-1 w-full rounded border border-gray-200 bg-white px-3 py-2 text-gray-600"></label><label class="font-medium text-gray-700">Alterado por<input disabled value="<?php echo appEscape($formatarAuditoria($auditoria['alteradoPorId'], $auditoria['alteradoPor'], $auditoria['atualizadoEm'])); ?>" class="mt-1 w-full rounded border border-gray-200 bg-white px-3 py-2 text-gray-600"></label></fieldset>
                        <div class="flex justify-end gap-2"><a href="<?php echo appEscape(appTabUrl($urlAtual . '#pesquisa')); ?>" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a><button type="submit" class="rounded bg-elo-teal px-5 py-2 text-sm font-semibold text-white hover:bg-elo-green" <?php echo !$blocos ? 'disabled' : ''; ?>><?php echo (int) $dados['idQuarto'] > 0 ? 'Salvar alterações' : 'Cadastrar quarto'; ?></button></div>
                    </form>
                </section>
            </section>
            <section id="painel-pesquisa" role="tabpanel" aria-labelledby="tab-pesquisa" <?php echo $abaInicial === 'pesquisa' ? '' : 'hidden'; ?>>
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><h2 class="text-lg font-semibold">Quartos cadastrados</h2><div class="flex gap-2"><input data-filter-input="listaQuartos" type="search" placeholder="Pesquisar quartos..." aria-label="Pesquisar quartos" class="rounded border border-gray-300 px-3 py-2 text-sm"><a href="<?php echo appEscape(appTabUrl($urlAtual . '?aba=manutencao#manutencao')); ?>" class="whitespace-nowrap rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">+ Novo quarto</a></div></div>
                    <div class="overflow-x-auto"><table class="w-full min-w-[950px] text-left text-sm"><thead><tr class="border-b bg-gray-50 text-gray-600"><th class="p-3">Bloco</th><th class="p-3">Quarto</th><th class="p-3">Descrição</th><th class="p-3">Leitos</th><th class="p-3">Status</th><th class="p-3">Auditoria</th><th class="p-3">Ações</th></tr></thead><tbody id="listaQuartos">
                        <?php foreach ($quartos as $quarto): ?><tr class="border-b hover:bg-gray-50"><td class="p-3"><?php echo appEscape($quarto['nomeBloco']); ?></td><td class="p-3 font-medium"><?php echo (int) $quarto['idQuarto']; ?> — <?php echo appEscape($quarto['identificacaoQuarto']); ?></td><td class="p-3"><?php echo appEscape((string) ($quarto['descricao'] ?: '—')); ?></td><td class="p-3"><?php echo (int) $quarto['totalLeitos']; ?></td><td class="p-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?php echo (int) $quarto['ativo'] === 1 ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-700'; ?>"><?php echo (int) $quarto['ativo'] === 1 ? 'Ativo' : 'Inativo'; ?></span></td><td class="min-w-72 p-3 text-xs"><p><b>Criado:</b> <?php echo appEscape($formatarAuditoria($quarto['criadoPorId'], $quarto['criadoPor'], $quarto['criadoEm'])); ?></p><p class="mt-1"><b>Alterado:</b> <?php echo appEscape($formatarAuditoria($quarto['alteradoPorId'], $quarto['alteradoPor'], $quarto['atualizadoEm'])); ?></p></td><td class="p-3"><div class="flex gap-2"><a href="<?php echo appEscape(appTabUrl($urlAtual . '?editar=' . (int) $quarto['idQuarto'] . '#manutencao')); ?>" class="inline-flex h-9 w-9 items-center justify-center rounded border text-elo-teal" aria-label="Editar quarto"><i class="ph ph-pencil-simple" aria-hidden="true"></i></a><form method="post" data-confirm="Excluir este quarto?"><input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="idQuarto" value="<?php echo (int) $quarto['idQuarto']; ?>"><button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded border border-red-200 text-red-600" aria-label="Excluir quarto"><i class="ph ph-trash" aria-hidden="true"></i></button></form></div></td></tr><?php endforeach; ?>
                        <?php if (!$quartos): ?><tr><td colspan="7" class="p-8 text-center text-gray-500">Nenhum quarto cadastrado.</td></tr><?php endif; ?>
                    </tbody></table></div>
                </section>
            </section>
        </main>
    </div>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/searchable-combobox.js?v=' . filemtime(__DIR__ . '/../../../assets/js/searchable-combobox.js')); ?>"></script>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
</body>
</html>
