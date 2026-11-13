<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('gestao-acolhidos.cadastros/blocos');
$pdo = appPdo();
$baseUrl = appBaseUrl();
$urlAtual = $baseUrl . '/pages/gestao-acolhidos/cadastros/blocos.php';
$erro = '';
$resultado = is_scalar($_GET['resultado'] ?? null) ? (string) $_GET['resultado'] : '';
$idInput = $_GET['editar'] ?? null;
$idEdicao = is_scalar($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;
$formEnviado = false;
$dados = ['idBloco' => 0, 'nomeBloco' => '', 'descricao' => '', 'ativo' => 1];
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
            $idBloco = $textoPost('idBloco') === '' ? 0 : filter_var($textoPost('idBloco'), FILTER_VALIDATE_INT);
            $nomeBloco = $textoPost('nomeBloco');
            $descricao = $textoPost('descricao');
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $dados = ['idBloco' => $idBloco !== false ? (int) $idBloco : 0, 'nomeBloco' => $nomeBloco, 'descricao' => $descricao, 'ativo' => $ativo];
            if ($idBloco === false || $idBloco < 0 || $nomeBloco === '' || mb_strlen($nomeBloco, 'UTF-8') > 100 || mb_strlen($descricao, 'UTF-8') > 255
                || (($idEdicao !== false && $idEdicao > 0) ? $idBloco !== $idEdicao : $idBloco !== 0)) {
                throw new InvalidArgumentException('Confira o nome e a descrição do bloco.');
            }
            if ($idBloco > 0 && $ativo === 0) {
                $pdo->beginTransaction();
                $blocoLock = $pdo->prepare('SELECT idBloco FROM tbBloco WHERE idBloco = :id FOR UPDATE');
                $blocoLock->execute(['id' => $idBloco]);
                if (!$blocoLock->fetchColumn()) throw new InvalidArgumentException('Bloco não encontrado.');
                $ocupacaoStatement = $pdo->prepare(
                    'SELECT h.idAcomodacao
                     FROM tbBloco b
                     INNER JOIN tbQuarto q ON q.idBloco = b.idBloco
                     INNER JOIN tbLeito l ON l.idQuarto = q.idQuarto
                     INNER JOIN tbAcomodacaoAcolhido h ON h.idLeito = l.idLeito AND h.fimEm IS NULL
                     WHERE b.idBloco = :id LIMIT 1'
                );
                $ocupacaoStatement->execute(['id' => $idBloco]);
                if ($ocupacaoStatement->fetchColumn()) throw new InvalidArgumentException('Remaneje os acolhidos do bloco antes de inativá-lo.');
            }
            if ($idBloco > 0) {
                $existeStatement = $pdo->prepare('SELECT idBloco FROM tbBloco WHERE idBloco = :id');
                $existeStatement->execute(['id' => $idBloco]);
                if (!$existeStatement->fetchColumn()) throw new InvalidArgumentException('Bloco não encontrado.');
                $statement = $pdo->prepare('UPDATE tbBloco SET nomeBloco = :nome, descricao = :descricao, ativo = :ativo, idUsuarioAlteracao = :usuario WHERE idBloco = :id');
                $statement->execute(['nome' => $nomeBloco, 'descricao' => $descricao !== '' ? $descricao : null, 'ativo' => $ativo, 'usuario' => (int) $usuarioAtual['idUsuario'], 'id' => $idBloco]);
                if ($pdo->inTransaction()) $pdo->commit();
                $resultadoSalvo = 'atualizado';
            } else {
                $statement = $pdo->prepare('INSERT INTO tbBloco (nomeBloco, descricao, ativo, idUsuarioCriacao) VALUES (:nome, :descricao, :ativo, :usuario)');
                $statement->execute(['nome' => $nomeBloco, 'descricao' => $descricao !== '' ? $descricao : null, 'ativo' => $ativo, 'usuario' => (int) $usuarioAtual['idUsuario']]);
                $resultadoSalvo = 'criado';
            }
            header('Location: ' . appTabUrl('blocos.php?resultado=' . $resultadoSalvo . '#pesquisa'));
            exit;
        }
        if ($acao === 'excluir') {
            $id = filter_var($textoPost('idBloco'), FILTER_VALIDATE_INT);
            if (!$id || $id < 1) throw new InvalidArgumentException('Bloco inválido.');
            $statement = $pdo->prepare('DELETE FROM tbBloco WHERE idBloco = :id');
            $statement->execute(['id' => $id]);
            if ($statement->rowCount() === 0) throw new InvalidArgumentException('Bloco não encontrado.');
            header('Location: ' . appTabUrl('blocos.php?resultado=excluido#pesquisa'));
            exit;
        }
        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $erro = $exception->getMessage();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Falha ao salvar bloco: ' . $exception->getMessage());
        $erro = (int) ($exception->errorInfo[1] ?? 0) === 1062
            ? 'Já existe um bloco com esse nome.'
            : ((int) ($exception->errorInfo[1] ?? 0) === 1451
                ? 'Não é possível excluir um bloco com quartos vinculados.'
                : 'Não foi possível concluir a operação com o bloco.');
    }
}

if ($idEdicao !== false && $idEdicao > 0) {
    $statement = $pdo->prepare(
        'SELECT b.*, c.idUsuario AS criadoPorId, c.nomeCompleto AS criadoPor,
                a.idUsuario AS alteradoPorId, a.nomeCompleto AS alteradoPor
         FROM tbBloco b
         INNER JOIN tbUsuario c ON c.idUsuario = b.idUsuarioCriacao
         LEFT JOIN tbUsuario a ON a.idUsuario = b.idUsuarioAlteracao
         WHERE b.idBloco = :id'
    );
    $statement->execute(['id' => $idEdicao]);
    $registro = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$registro) {
        $erro = $erro ?: 'Bloco não encontrado.';
    } else {
        $auditoria = ['criadoPorId' => $registro['criadoPorId'], 'criadoPor' => $registro['criadoPor'], 'criadoEm' => $registro['criadoEm'], 'alteradoPorId' => $registro['alteradoPorId'], 'alteradoPor' => $registro['alteradoPor'], 'atualizadoEm' => $registro['atualizadoEm']];
        if (!$formEnviado) $dados = ['idBloco' => (int) $registro['idBloco'], 'nomeBloco' => $registro['nomeBloco'], 'descricao' => (string) ($registro['descricao'] ?? ''), 'ativo' => (int) $registro['ativo']];
    }
}
$blocos = $pdo->query(
    'SELECT b.*, c.idUsuario AS criadoPorId, c.nomeCompleto AS criadoPor,
            a.idUsuario AS alteradoPorId, a.nomeCompleto AS alteradoPor,
            (SELECT COUNT(*) FROM tbQuarto q WHERE q.idBloco = b.idBloco) AS totalQuartos
     FROM tbBloco b
     INNER JOIN tbUsuario c ON c.idUsuario = b.idUsuarioCriacao
     LEFT JOIN tbUsuario a ON a.idUsuario = b.idUsuarioAlteracao
     ORDER BY b.nomeBloco'
)->fetchAll(PDO::FETCH_ASSOC);
$abaInicial = (($idEdicao !== false && $idEdicao > 0) || ($formEnviado && $erro !== '') || in_array($_GET['aba'] ?? null, ['pesquisa', 'manutencao'], true))
    ? (($_GET['aba'] ?? '') === 'pesquisa' ? 'pesquisa' : 'manutencao') : 'pesquisa';
$mensagens = ['criado' => 'Bloco cadastrado.', 'atualizado' => 'Bloco atualizado.', 'excluido' => 'Bloco excluído.'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blocos | Sistema Elo</title>
    <link rel="icon" type="image/png" href="<?php echo appEscape($baseUrl . '/assets/imgs/logo-elo.png'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script><script src="<?php echo appEscape($baseUrl . '/assets/js/tailwind-config.js'); ?>"></script><script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="flex h-screen flex-col overflow-hidden bg-gray-100 font-sans text-gray-800">
    <?php include __DIR__ . '/../../../components/gestao-acolhidos/navbar-gestaoacolhidos.php'; ?>
    <div class="flex min-h-0 flex-1 overflow-hidden"><?php include __DIR__ . '/../../../components/gestao-acolhidos/sidebar-gestaoacolhidos.php'; ?>
        <main class="min-h-0 min-w-0 flex-1 overflow-y-auto bg-gray-50 p-4 md:p-6">
            <h1 class="mb-6 text-2xl font-bold text-gray-800">Blocos</h1>
            <?php if ($erro !== ''): ?><p role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"><?php echo appEscape($erro); ?></p>
            <?php elseif (isset($mensagens[$resultado])): ?><p role="status" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800"><?php echo appEscape($mensagens[$resultado]); ?></p><?php endif; ?>
            <?php include __DIR__ . '/../../../components/tabs-pesquisa-manutencao.php'; ?>
            <section id="painel-manutencao" role="tabpanel" aria-labelledby="tab-manutencao" <?php echo $abaInicial === 'manutencao' ? '' : 'hidden'; ?>>
                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:p-6">
                    <div class="mb-5 flex items-start justify-between gap-3">
                        <div><h2 class="text-lg font-semibold"><?php echo (int) $dados['idBloco'] > 0 ? 'Editar bloco' : 'Novo bloco'; ?></h2><p class="mt-1 text-sm text-gray-500">Organize os espaços físicos da casa de apoio por bloco.</p></div>
                        <?php if ((int) $dados['idBloco'] > 0): ?><a href="<?php echo appEscape(appTabUrl($urlAtual . '?aba=manutencao#manutencao')); ?>" class="text-sm font-medium text-elo-teal hover:underline">Novo bloco</a><?php endif; ?>
                    </div>
                    <form method="post" class="space-y-5">
                        <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>"><input type="hidden" name="acao" value="salvar"><input type="hidden" name="idBloco" value="<?php echo (int) $dados['idBloco']; ?>">
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="text-sm font-medium text-gray-700">Nome do bloco *<input name="nomeBloco" maxlength="100" required value="<?php echo appEscape((string) $dados['nomeBloco']); ?>" placeholder="Ex.: Bloco A" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"></label>
                            <label class="flex items-center gap-2 self-end pb-2 text-sm font-medium text-gray-700"><input type="checkbox" name="ativo" value="1" <?php echo (int) $dados['ativo'] === 1 ? 'checked' : ''; ?> class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">Bloco ativo</label>
                            <label class="text-sm font-medium text-gray-700 md:col-span-2">Descrição (opcional)<textarea name="descricao" maxlength="255" rows="3" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"><?php echo appEscape((string) $dados['descricao']); ?></textarea></label>
                        </div>
                        <fieldset class="grid gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm md:grid-cols-2"><legend class="px-2 font-semibold text-gray-700">Auditoria</legend>
                            <label class="font-medium text-gray-700">Criado por<input disabled value="<?php echo appEscape($formatarAuditoria($auditoria['criadoPorId'], $auditoria['criadoPor'], $auditoria['criadoEm'])); ?>" class="mt-1 w-full rounded border border-gray-200 bg-white px-3 py-2 text-gray-600"></label>
                            <label class="font-medium text-gray-700">Alterado por<input disabled value="<?php echo appEscape($formatarAuditoria($auditoria['alteradoPorId'], $auditoria['alteradoPor'], $auditoria['atualizadoEm'])); ?>" class="mt-1 w-full rounded border border-gray-200 bg-white px-3 py-2 text-gray-600"></label>
                        </fieldset>
                        <div class="flex justify-end gap-2"><a href="<?php echo appEscape(appTabUrl($urlAtual . '#pesquisa')); ?>" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a><button type="submit" class="rounded bg-elo-teal px-5 py-2 text-sm font-semibold text-white hover:bg-elo-green"><?php echo (int) $dados['idBloco'] > 0 ? 'Salvar alterações' : 'Cadastrar bloco'; ?></button></div>
                    </form>
                </section>
            </section>
            <section id="painel-pesquisa" role="tabpanel" aria-labelledby="tab-pesquisa" <?php echo $abaInicial === 'pesquisa' ? '' : 'hidden'; ?>>
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><h2 class="text-lg font-semibold">Blocos cadastrados</h2><div class="flex gap-2"><input data-filter-input="listaBlocos" type="search" placeholder="Pesquisar blocos..." aria-label="Pesquisar blocos" class="rounded border border-gray-300 px-3 py-2 text-sm"><a href="<?php echo appEscape(appTabUrl($urlAtual . '?aba=manutencao#manutencao')); ?>" class="whitespace-nowrap rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">+ Novo bloco</a></div></div>
                    <div class="overflow-x-auto"><table class="w-full min-w-[850px] text-left text-sm"><thead><tr class="border-b bg-gray-50 text-gray-600"><th class="p-3">Bloco</th><th class="p-3">Descrição</th><th class="p-3">Quartos</th><th class="p-3">Status</th><th class="p-3">Auditoria</th><th class="p-3">Ações</th></tr></thead><tbody id="listaBlocos">
                        <?php foreach ($blocos as $bloco): ?><tr class="border-b hover:bg-gray-50"><td class="p-3 font-medium"><?php echo (int) $bloco['idBloco']; ?> — <?php echo appEscape($bloco['nomeBloco']); ?></td><td class="p-3"><?php echo appEscape((string) ($bloco['descricao'] ?: '—')); ?></td><td class="p-3"><?php echo (int) $bloco['totalQuartos']; ?></td><td class="p-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?php echo (int) $bloco['ativo'] === 1 ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-700'; ?>"><?php echo (int) $bloco['ativo'] === 1 ? 'Ativo' : 'Inativo'; ?></span></td><td class="min-w-72 p-3 text-xs"><p><b>Criado:</b> <?php echo appEscape($formatarAuditoria($bloco['criadoPorId'], $bloco['criadoPor'], $bloco['criadoEm'])); ?></p><p class="mt-1"><b>Alterado:</b> <?php echo appEscape($formatarAuditoria($bloco['alteradoPorId'], $bloco['alteradoPor'], $bloco['atualizadoEm'])); ?></p></td><td class="p-3"><div class="flex gap-2"><a href="<?php echo appEscape(appTabUrl($urlAtual . '?editar=' . (int) $bloco['idBloco'] . '#manutencao')); ?>" class="inline-flex h-9 w-9 items-center justify-center rounded border text-elo-teal" aria-label="Editar bloco"><i class="ph ph-pencil-simple" aria-hidden="true"></i></a><form method="post" data-confirm="Excluir este bloco?"><input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="idBloco" value="<?php echo (int) $bloco['idBloco']; ?>"><button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded border border-red-200 text-red-600" aria-label="Excluir bloco"><i class="ph ph-trash" aria-hidden="true"></i></button></form></div></td></tr><?php endforeach; ?>
                        <?php if (!$blocos): ?><tr><td colspan="6" class="p-8 text-center text-gray-500">Nenhum bloco cadastrado.</td></tr><?php endif; ?>
                    </tbody></table></div>
                </section>
            </section>
        </main>
    </div>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
</body>
</html>
