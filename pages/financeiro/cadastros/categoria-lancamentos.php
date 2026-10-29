<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('financeiro.categoria-lancamentos');
$pdo = appPdo();
$erro = '';
$categoriaEdicao = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    $acao = is_scalar($_POST['acao'] ?? null) ? (string) $_POST['acao'] : '';

    try {
        if ($acao === 'salvar') {
            $idCategoria = filter_var($_POST['idCategoria'] ?? 0, FILTER_VALIDATE_INT);
            $nomeInput = $_POST['nomeCategoria'] ?? null;
            $naturezaInput = $_POST['naturezaCategoria'] ?? null;
            $descricaoInput = $_POST['descricaoCategoria'] ?? null;
            $nome = is_scalar($nomeInput) ? trim((string) $nomeInput) : '';
            $natureza = is_scalar($naturezaInput) ? (string) $naturezaInput : '';
            $descricao = is_scalar($descricaoInput) ? trim((string) $descricaoInput) : '';
            $status = isset($_POST['statusCategoria']) ? 1 : 0;

            if ($idCategoria === false || $idCategoria < 0
                || $nome === '' || mb_strlen($nome) > 100
                || !in_array($natureza, ['Entrada', 'Saída'], true)
                || mb_strlen($descricao) > 255) {
                throw new InvalidArgumentException('Confira o nome, a natureza e a descrição da categoria.');
            }

            if ($idCategoria > 0) {
                $existingCategory = $pdo->prepare(
                    'SELECT idCategoria FROM tbCategoriaLancamentos WHERE idCategoria = :idCategoria'
                );
                $existingCategory->execute(['idCategoria' => $idCategoria]);
                if (!$existingCategory->fetchColumn()) {
                    throw new InvalidArgumentException('Categoria não encontrada.');
                }

                $statement = $pdo->prepare(
                    'UPDATE tbCategoriaLancamentos
                     SET nomeCategoria = :nome, naturezaCategoria = :natureza,
                         descricaoCategoria = :descricao, statusCategoria = :status,
                         idUsuarioAlteracao = :idUsuarioAlteracao
                     WHERE idCategoria = :idCategoria'
                );
                $statement->execute([
                    'nome' => $nome,
                    'natureza' => $natureza,
                    'descricao' => $descricao !== '' ? $descricao : null,
                    'status' => $status,
                    'idUsuarioAlteracao' => (int) $usuarioAtual['idUsuario'],
                    'idCategoria' => $idCategoria,
                ]);
                header('Location: ' . appTabUrl('categoria-lancamentos.php?resultado=atualizado'));
                exit;
            }

            $statement = $pdo->prepare(
                'INSERT INTO tbCategoriaLancamentos
                    (nomeCategoria, naturezaCategoria, descricaoCategoria, statusCategoria, idUsuarioCriacao)
                 VALUES (:nome, :natureza, :descricao, :status, :idUsuarioCriacao)'
            );
            $statement->execute([
                'nome' => $nome,
                'natureza' => $natureza,
                'descricao' => $descricao !== '' ? $descricao : null,
                'status' => $status,
                'idUsuarioCriacao' => (int) $usuarioAtual['idUsuario'],
            ]);
            header('Location: ' . appTabUrl('categoria-lancamentos.php?resultado=criado'));
            exit;
        }

        if ($acao === 'excluir') {
            $idCategoria = filter_var($_POST['idCategoria'] ?? 0, FILTER_VALIDATE_INT);
            if (!$idCategoria || $idCategoria < 1) {
                throw new InvalidArgumentException('Categoria inválida.');
            }

            $statement = $pdo->prepare('DELETE FROM tbCategoriaLancamentos WHERE idCategoria = :idCategoria');
            $statement->execute(['idCategoria' => $idCategoria]);
            if ($statement->rowCount() === 0) {
                throw new InvalidArgumentException('Categoria não encontrada.');
            }

            header('Location: ' . appTabUrl('categoria-lancamentos.php?resultado=excluido'));
            exit;
        }

        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException $exception) {
        $erro = $exception->getMessage();
        if ($acao === 'salvar') {
            $submittedId = filter_var($_POST['idCategoria'] ?? 0, FILTER_VALIDATE_INT);
            $categoriaEdicao = [
                'idCategoria' => $submittedId !== false ? $submittedId : 0,
                'nomeCategoria' => is_scalar($_POST['nomeCategoria'] ?? null) ? (string) $_POST['nomeCategoria'] : '',
                'naturezaCategoria' => is_scalar($_POST['naturezaCategoria'] ?? null) ? (string) $_POST['naturezaCategoria'] : '',
                'descricaoCategoria' => is_scalar($_POST['descricaoCategoria'] ?? null) ? (string) $_POST['descricaoCategoria'] : '',
                'statusCategoria' => isset($_POST['statusCategoria']) ? 1 : 0,
            ];
        }
    } catch (PDOException $exception) {
        error_log('Falha ao salvar categoria de lançamentos: ' . $exception->getMessage());
        $erro = (int) ($exception->errorInfo[1] ?? 0) === 1062
            ? 'Já existe uma categoria com esse nome e essa natureza.'
            : 'Não foi possível salvar a categoria. Verifique se ela está sendo utilizada.';
        if ($acao === 'salvar') {
            $submittedId = filter_var($_POST['idCategoria'] ?? 0, FILTER_VALIDATE_INT);
            $categoriaEdicao = [
                'idCategoria' => $submittedId !== false ? $submittedId : 0,
                'nomeCategoria' => is_scalar($_POST['nomeCategoria'] ?? null) ? (string) $_POST['nomeCategoria'] : '',
                'naturezaCategoria' => is_scalar($_POST['naturezaCategoria'] ?? null) ? (string) $_POST['naturezaCategoria'] : '',
                'descricaoCategoria' => is_scalar($_POST['descricaoCategoria'] ?? null) ? (string) $_POST['descricaoCategoria'] : '',
                'statusCategoria' => isset($_POST['statusCategoria']) ? 1 : 0,
            ];
        }
    }
}

$idEdicao = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
if ($categoriaEdicao === null && $idEdicao) {
    $statement = $pdo->prepare(
        'SELECT c.idCategoria, c.nomeCategoria, c.naturezaCategoria, c.descricaoCategoria,
                c.statusCategoria, criador.idUsuario AS criadoPorId,
                criador.nomeCompleto AS criadoPor, alterador.idUsuario AS alteradoPorId,
                alterador.nomeCompleto AS alteradoPor
         FROM tbCategoriaLancamentos c
         INNER JOIN tbUsuario criador ON criador.idUsuario = c.idUsuarioCriacao
         LEFT JOIN tbUsuario alterador ON alterador.idUsuario = c.idUsuarioAlteracao
         WHERE c.idCategoria = :idCategoria'
    );
    $statement->execute(['idCategoria' => $idEdicao]);
    $categoriaEdicao = $statement->fetch() ?: null;
    if (!$categoriaEdicao) {
        $erro = 'Categoria não encontrada.';
    }
}

if ($categoriaEdicao !== null && !isset($categoriaEdicao['criadoPor'])) {
    if ((int) ($categoriaEdicao['idCategoria'] ?? 0) > 0) {
        $auditStatement = $pdo->prepare(
            'SELECT criador.idUsuario AS criadoPorId, criador.nomeCompleto AS criadoPor,
                    alterador.idUsuario AS alteradoPorId, alterador.nomeCompleto AS alteradoPor
             FROM tbCategoriaLancamentos c
             INNER JOIN tbUsuario criador ON criador.idUsuario = c.idUsuarioCriacao
             LEFT JOIN tbUsuario alterador ON alterador.idUsuario = c.idUsuarioAlteracao
             WHERE c.idCategoria = :idCategoria'
        );
        $auditStatement->execute(['idCategoria' => (int) $categoriaEdicao['idCategoria']]);
        $auditData = $auditStatement->fetch();
        $categoriaEdicao['criadoPorId'] = $auditData['criadoPorId'] ?? null;
        $categoriaEdicao['criadoPor'] = $auditData['criadoPor'] ?? '';
        $categoriaEdicao['alteradoPorId'] = $auditData['alteradoPorId'] ?? null;
        $categoriaEdicao['alteradoPor'] = $auditData['alteradoPor'] ?? '';
    } else {
        $categoriaEdicao['criadoPorId'] = $usuarioAtual['idUsuario'];
        $categoriaEdicao['criadoPor'] = $usuarioAtual['nomeCompleto'];
        $categoriaEdicao['alteradoPorId'] = null;
        $categoriaEdicao['alteradoPor'] = '';
    }
}

$criadoPorId = $categoriaEdicao['criadoPorId'] ?? $usuarioAtual['idUsuario'];
$criadoPorNome = (string) ($categoriaEdicao['criadoPor'] ?? $usuarioAtual['nomeCompleto']);
$criadoPor = $criadoPorId !== null ? $criadoPorId . ' - ' . $criadoPorNome : $criadoPorNome;
$alteradoPorId = $categoriaEdicao['alteradoPorId'] ?? null;
$alteradoPorNome = (string) ($categoriaEdicao['alteradoPor'] ?? '');
$alteradoPor = $alteradoPorId !== null
    ? $alteradoPorId . ' - ' . $alteradoPorNome
    : $alteradoPorNome;

$categorias = $pdo->query(
    'SELECT c.idCategoria, c.nomeCategoria, c.naturezaCategoria, c.descricaoCategoria,
            c.statusCategoria
     FROM tbCategoriaLancamentos c
     ORDER BY c.naturezaCategoria, c.nomeCategoria'
)->fetchAll();

$resultado = [
    'criado' => 'Categoria cadastrada.',
    'atualizado' => 'Categoria atualizada.',
    'excluido' => 'Categoria excluída.',
][(string) ($_GET['resultado'] ?? '')] ?? '';
$abaInicial = ($categoriaEdicao || ($erro !== ''
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['acao'] ?? '') === 'salvar')) ? 'manutencao' : 'pesquisa';
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
            <h1 class="mb-6 text-2xl font-bold text-gray-800">Categorias de Lançamentos</h1>
            <?php include __DIR__ . '/../../../components/tabs-pesquisa-manutencao.php'; ?>

            <section id="painel-manutencao" role="tabpanel" aria-labelledby="tab-manutencao"
                     class="mb-8 rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'manutencao' ? '' : 'hidden'; ?>>
                <h2 class="mb-4 text-lg font-semibold"><?php echo !empty($categoriaEdicao['idCategoria']) ? 'Alterar categoria' : 'Nova categoria'; ?></h2>
                <form method="post" data-confirm="Confirma gravar estes dados?" class="grid gap-4 md:grid-cols-2">
                    <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="idCategoria" value="<?php echo (int) ($categoriaEdicao['idCategoria'] ?? 0); ?>">
                    <label class="text-sm font-medium text-gray-700">Nome da categoria
                        <input name="nomeCategoria" required maxlength="100" value="<?php echo appEscape((string) ($categoriaEdicao['nomeCategoria'] ?? '')); ?>"
                               class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    </label>
                    <label class="text-sm font-medium text-gray-700">Natureza
                        <select name="naturezaCategoria" required class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                            <option value="">Selecione</option>
                            <?php foreach (['Entrada', 'Saída'] as $natureza): ?>
                                <option value="<?php echo appEscape($natureza); ?>" <?php echo ($categoriaEdicao['naturezaCategoria'] ?? '') === $natureza ? 'selected' : ''; ?>>
                                    <?php echo appEscape($natureza); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="text-sm font-medium text-gray-700 md:col-span-2">Descrição
                        <textarea name="descricaoCategoria" maxlength="255" rows="3" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"><?php echo appEscape((string) ($categoriaEdicao['descricaoCategoria'] ?? '')); ?></textarea>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="statusCategoria" value="1" <?php echo !isset($categoriaEdicao['statusCategoria']) || (int) $categoriaEdicao['statusCategoria'] === 1 ? 'checked' : ''; ?>
                               class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                        Categoria ativa
                    </label>
                    <div class="grid gap-4 md:grid-cols-2 md:col-span-2">
                        <label class="text-sm font-medium text-gray-700">Criado por
                            <input type="text" value="<?php echo appEscape($criadoPor); ?>" disabled
                                   class="mt-1 w-full cursor-not-allowed rounded border border-gray-200 bg-gray-100 px-3 py-2 text-gray-600">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Alterado por
                            <input type="text" value="<?php echo appEscape($alteradoPor !== '' ? $alteradoPor : '—'); ?>" disabled
                                   class="mt-1 w-full cursor-not-allowed rounded border border-gray-200 bg-gray-100 px-3 py-2 text-gray-600">
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-2 md:col-span-2">
                        <button type="submit" class="inline-flex items-center gap-2 rounded bg-elo-teal px-4 py-2 font-medium text-white hover:bg-elo-green">
                            <i class="ph ph-floppy-disk" aria-hidden="true"></i>
                            <?php echo !empty($categoriaEdicao['idCategoria']) ? 'Salvar alterações' : 'Cadastrar categoria'; ?>
                        </button>
                        <?php if (!empty($categoriaEdicao['idCategoria'])): ?>
                            <a href="categoria-lancamentos.php#pesquisa" class="inline-flex items-center gap-2 rounded border px-4 py-2 text-gray-700 hover:bg-gray-50">
                                <i class="ph ph-x-circle" aria-hidden="true"></i> Cancelar
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section id="painel-pesquisa" role="tabpanel" aria-labelledby="tab-pesquisa"
                     class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'pesquisa' ? '' : 'hidden'; ?>>
                <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <h2 class="text-lg font-semibold">Categorias cadastradas</h2>
                    <div class="flex flex-wrap gap-2">
                        <input data-filter-input="listaCategorias" type="search" placeholder="Pesquisar categorias..." aria-label="Pesquisar categorias"
                               class="rounded border border-gray-300 px-3 py-2 text-sm">
                        <button type="button" data-tab-open="manutencao" class="rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">
                            + Nova categoria
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[780px] text-left text-sm">
                        <thead class="border-b bg-gray-50 text-gray-600">
                            <tr>
                                <th class="p-3">Categoria</th>
                                <th class="p-3">Natureza</th>
                                <th class="p-3">Descrição</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="listaCategorias">
                            <?php foreach ($categorias as $categoria): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-3 font-medium text-gray-800"><?php echo appEscape($categoria['nomeCategoria']); ?></td>
                                    <td class="p-3">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-medium <?php echo $categoria['naturezaCategoria'] === 'Entrada' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo appEscape($categoria['naturezaCategoria']); ?>
                                        </span>
                                    </td>
                                    <td class="max-w-sm whitespace-normal p-3 text-gray-600"><?php echo appEscape((string) ($categoria['descricaoCategoria'] ?? '')); ?></td>
                                    <td class="p-3">
                                        <span class="<?php echo (int) $categoria['statusCategoria'] === 1 ? 'text-green-700' : 'text-gray-500'; ?>">
                                            <?php echo (int) $categoria['statusCategoria'] === 1 ? 'Ativa' : 'Inativa'; ?>
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap p-3">
                                        <div class="flex items-center gap-3">
                                            <a href="?editar=<?php echo (int) $categoria['idCategoria']; ?>#manutencao"
                                               aria-label="Alterar categoria <?php echo appEscape($categoria['nomeCategoria']); ?>"
                                               title="Alterar categoria"
                                               class="inline-flex h-9 w-9 items-center justify-center rounded border border-elo-teal/20 text-elo-teal transition-colors hover:bg-elo-teal/10">
                                                <i class="ph ph-pencil-simple text-lg" aria-hidden="true"></i>
                                            </a>
                                            <form method="post" data-confirm="Confirma excluir esta categoria?">
                                                <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                                                <input type="hidden" name="acao" value="excluir">
                                                <input type="hidden" name="idCategoria" value="<?php echo (int) $categoria['idCategoria']; ?>">
                                                <button type="submit" aria-label="Excluir categoria <?php echo appEscape($categoria['nomeCategoria']); ?>"
                                                        title="Excluir categoria"
                                                        class="inline-flex h-9 w-9 items-center justify-center rounded border border-red-200 text-red-600 transition-colors hover:bg-red-50">
                                                    <i class="ph ph-trash text-lg" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!$categorias): ?>
                        <p class="p-6 text-center text-sm text-gray-500">Nenhuma categoria cadastrada.</p>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
    <?php $toastSuccess = $resultado; $toastError = $erro; include __DIR__ . '/../../../components/toast.php'; ?>
    <script src="<?php echo appEscape(appBaseUrl() . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
</body>
</html>
