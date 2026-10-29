<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('financeiro.fonte-recurso');
$pdo = appPdo();
$erro = '';
$fonteEdicao = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    $acao = is_scalar($_POST['acao'] ?? null) ? (string) $_POST['acao'] : '';

    try {
        if ($acao === 'salvar') {
            $idFonte = filter_var($_POST['idFonte'] ?? 0, FILTER_VALIDATE_INT);
            $nomeInput = $_POST['nomeFonte'] ?? null;
            $descricaoInput = $_POST['descricaoFonte'] ?? null;
            $nome = is_scalar($nomeInput) ? trim((string) $nomeInput) : '';
            $descricao = is_scalar($descricaoInput) ? trim((string) $descricaoInput) : '';
            $status = isset($_POST['statusFonte']) ? 1 : 0;

            if ($idFonte === false || $idFonte < 0
                || $nome === '' || mb_strlen($nome) > 100
                || mb_strlen($descricao) > 255) {
                throw new InvalidArgumentException('Confira o nome e a descrição da fonte de recurso.');
            }

            if ($idFonte > 0) {
                $existingSource = $pdo->prepare(
                    'SELECT idFonte FROM tbFonteRecurso WHERE idFonte = :idFonte'
                );
                $existingSource->execute(['idFonte' => $idFonte]);
                if (!$existingSource->fetchColumn()) {
                    throw new InvalidArgumentException('Fonte de recurso não encontrada.');
                }

                $statement = $pdo->prepare(
                    'UPDATE tbFonteRecurso
                     SET nomeFonte = :nome, descricaoFonte = :descricao,
                         statusFonte = :status, idUsuarioAlteracao = :idUsuarioAlteracao
                     WHERE idFonte = :idFonte'
                );
                $statement->execute([
                    'nome' => $nome,
                    'descricao' => $descricao !== '' ? $descricao : null,
                    'status' => $status,
                    'idUsuarioAlteracao' => (int) $usuarioAtual['idUsuario'],
                    'idFonte' => $idFonte,
                ]);
                header('Location: ' . appTabUrl('fonte-recurso.php?resultado=atualizado'));
                exit;
            }

            $statement = $pdo->prepare(
                'INSERT INTO tbFonteRecurso
                    (nomeFonte, descricaoFonte, statusFonte, idUsuarioCriacao)
                 VALUES (:nome, :descricao, :status, :idUsuarioCriacao)'
            );
            $statement->execute([
                'nome' => $nome,
                'descricao' => $descricao !== '' ? $descricao : null,
                'status' => $status,
                'idUsuarioCriacao' => (int) $usuarioAtual['idUsuario'],
            ]);
            header('Location: ' . appTabUrl('fonte-recurso.php?resultado=criado'));
            exit;
        }

        if ($acao === 'excluir') {
            $idFonte = filter_var($_POST['idFonte'] ?? 0, FILTER_VALIDATE_INT);
            if (!$idFonte || $idFonte < 1) {
                throw new InvalidArgumentException('Fonte de recurso inválida.');
            }

            $statement = $pdo->prepare('DELETE FROM tbFonteRecurso WHERE idFonte = :idFonte');
            $statement->execute(['idFonte' => $idFonte]);
            if ($statement->rowCount() === 0) {
                throw new InvalidArgumentException('Fonte de recurso não encontrada.');
            }

            header('Location: ' . appTabUrl('fonte-recurso.php?resultado=excluido'));
            exit;
        }

        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException $exception) {
        $erro = $exception->getMessage();
        if ($acao === 'salvar') {
            $submittedId = filter_var($_POST['idFonte'] ?? 0, FILTER_VALIDATE_INT);
            $fonteEdicao = [
                'idFonte' => $submittedId !== false ? $submittedId : 0,
                'nomeFonte' => is_scalar($_POST['nomeFonte'] ?? null) ? (string) $_POST['nomeFonte'] : '',
                'descricaoFonte' => is_scalar($_POST['descricaoFonte'] ?? null) ? (string) $_POST['descricaoFonte'] : '',
                'statusFonte' => isset($_POST['statusFonte']) ? 1 : 0,
            ];
        }
    } catch (PDOException $exception) {
        error_log('Falha ao salvar fonte de recurso: ' . $exception->getMessage());
        $erro = (int) ($exception->errorInfo[1] ?? 0) === 1062
            ? 'Já existe uma fonte de recurso com esse nome.'
            : 'Não foi possível salvar a fonte de recurso. Verifique se ela está sendo utilizada.';
        if ($acao === 'salvar') {
            $submittedId = filter_var($_POST['idFonte'] ?? 0, FILTER_VALIDATE_INT);
            $fonteEdicao = [
                'idFonte' => $submittedId !== false ? $submittedId : 0,
                'nomeFonte' => is_scalar($_POST['nomeFonte'] ?? null) ? (string) $_POST['nomeFonte'] : '',
                'descricaoFonte' => is_scalar($_POST['descricaoFonte'] ?? null) ? (string) $_POST['descricaoFonte'] : '',
                'statusFonte' => isset($_POST['statusFonte']) ? 1 : 0,
            ];
        }
    }
}

$idEdicao = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
if ($fonteEdicao === null && $idEdicao) {
    $statement = $pdo->prepare(
        'SELECT f.idFonte, f.nomeFonte, f.descricaoFonte, f.statusFonte,
                criador.idUsuario AS criadoPorId, criador.nomeCompleto AS criadoPor,
                alterador.idUsuario AS alteradoPorId, alterador.nomeCompleto AS alteradoPor
         FROM tbFonteRecurso f
         INNER JOIN tbUsuario criador ON criador.idUsuario = f.idUsuarioCriacao
         LEFT JOIN tbUsuario alterador ON alterador.idUsuario = f.idUsuarioAlteracao
         WHERE f.idFonte = :idFonte'
    );
    $statement->execute(['idFonte' => $idEdicao]);
    $fonteEdicao = $statement->fetch() ?: null;
    if (!$fonteEdicao) {
        $erro = 'Fonte de recurso não encontrada.';
    }
}

if ($fonteEdicao !== null && !isset($fonteEdicao['criadoPor'])) {
    if ((int) ($fonteEdicao['idFonte'] ?? 0) > 0) {
        $auditStatement = $pdo->prepare(
            'SELECT criador.idUsuario AS criadoPorId, criador.nomeCompleto AS criadoPor,
                    alterador.idUsuario AS alteradoPorId, alterador.nomeCompleto AS alteradoPor
             FROM tbFonteRecurso f
             INNER JOIN tbUsuario criador ON criador.idUsuario = f.idUsuarioCriacao
             LEFT JOIN tbUsuario alterador ON alterador.idUsuario = f.idUsuarioAlteracao
             WHERE f.idFonte = :idFonte'
        );
        $auditStatement->execute(['idFonte' => (int) $fonteEdicao['idFonte']]);
        $auditData = $auditStatement->fetch();
        $fonteEdicao['criadoPorId'] = $auditData['criadoPorId'] ?? null;
        $fonteEdicao['criadoPor'] = $auditData['criadoPor'] ?? '';
        $fonteEdicao['alteradoPorId'] = $auditData['alteradoPorId'] ?? null;
        $fonteEdicao['alteradoPor'] = $auditData['alteradoPor'] ?? '';
    } else {
        $fonteEdicao['criadoPorId'] = $usuarioAtual['idUsuario'];
        $fonteEdicao['criadoPor'] = $usuarioAtual['nomeCompleto'];
        $fonteEdicao['alteradoPorId'] = null;
        $fonteEdicao['alteradoPor'] = '';
    }
}

$criadoPorId = $fonteEdicao['criadoPorId'] ?? $usuarioAtual['idUsuario'];
$criadoPorNome = (string) ($fonteEdicao['criadoPor'] ?? $usuarioAtual['nomeCompleto']);
$criadoPor = $criadoPorId !== null ? $criadoPorId . ' - ' . $criadoPorNome : $criadoPorNome;
$alteradoPorId = $fonteEdicao['alteradoPorId'] ?? null;
$alteradoPorNome = (string) ($fonteEdicao['alteradoPor'] ?? '');
$alteradoPor = $alteradoPorId !== null
    ? $alteradoPorId . ' - ' . $alteradoPorNome
    : $alteradoPorNome;

$fontes = $pdo->query(
    'SELECT idFonte, nomeFonte, descricaoFonte, statusFonte
     FROM tbFonteRecurso
     ORDER BY nomeFonte'
)->fetchAll();

$resultado = [
    'criado' => 'Fonte de recurso cadastrada.',
    'atualizado' => 'Fonte de recurso atualizada.',
    'excluido' => 'Fonte de recurso excluída.',
][(string) ($_GET['resultado'] ?? '')] ?? '';
$abaInicial = ($fonteEdicao || ($erro !== ''
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
            <h1 class="mb-6 text-2xl font-bold text-gray-800">Fontes de Recursos</h1>
            <?php include __DIR__ . '/../../../components/tabs-pesquisa-manutencao.php'; ?>

            <section id="painel-manutencao" role="tabpanel" aria-labelledby="tab-manutencao"
                     class="mb-8 rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'manutencao' ? '' : 'hidden'; ?>>
                <h2 class="mb-4 text-lg font-semibold"><?php echo !empty($fonteEdicao['idFonte']) ? 'Alterar Fonte de Recurso' : 'Nova Fonte de Recurso'; ?></h2>
                <form method="post" data-confirm="Confirma gravar estes dados?" class="grid gap-4 md:grid-cols-2">
                    <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="idFonte" value="<?php echo (int) ($fonteEdicao['idFonte'] ?? 0); ?>">
                    <label class="text-sm font-medium text-gray-700">Nome da fonte
                        <input name="nomeFonte" required maxlength="100" value="<?php echo appEscape((string) ($fonteEdicao['nomeFonte'] ?? '')); ?>"
                               class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    </label>
                    <label class="flex items-center gap-2 self-end pb-2 text-sm text-gray-700">
                        <input type="checkbox" name="statusFonte" value="1" <?php echo !isset($fonteEdicao['statusFonte']) || (int) $fonteEdicao['statusFonte'] === 1 ? 'checked' : ''; ?>
                               class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                        Fonte ativa
                    </label>
                    <label class="text-sm font-medium text-gray-700 md:col-span-2">Descrição
                        <textarea name="descricaoFonte" maxlength="255" rows="3" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"><?php echo appEscape((string) ($fonteEdicao['descricaoFonte'] ?? '')); ?></textarea>
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
                            <?php echo !empty($fonteEdicao['idFonte']) ? 'Salvar alterações' : 'Cadastrar fonte'; ?>
                        </button>
                        <?php if (!empty($fonteEdicao['idFonte'])): ?>
                            <a href="fonte-recurso.php#pesquisa" class="inline-flex items-center gap-2 rounded border px-4 py-2 text-gray-700 hover:bg-gray-50">
                                <i class="ph ph-x-circle" aria-hidden="true"></i> Cancelar
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section id="painel-pesquisa" role="tabpanel" aria-labelledby="tab-pesquisa"
                     class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'pesquisa' ? '' : 'hidden'; ?>>
                <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <h2 class="text-lg font-semibold">Fontes Cadastradas</h2>
                    <div class="flex flex-wrap gap-2">
                        <input data-filter-input="listaFontes" type="search" placeholder="Pesquisar fontes..."
                               aria-label="Pesquisar fontes" class="rounded border border-gray-300 px-3 py-2 text-sm">
                        <button type="button" data-tab-open="manutencao" class="rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">
                            + Nova Fonte
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[700px] text-left text-sm">
                        <thead class="border-b bg-gray-50 text-gray-600">
                            <tr>
                                <th class="p-3">Fonte</th>
                                <th class="p-3">Descrição</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="listaFontes">
                            <?php foreach ($fontes as $fonte): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-3 font-medium text-gray-800"><?php echo appEscape($fonte['nomeFonte']); ?></td>
                                    <td class="max-w-sm whitespace-normal p-3 text-gray-600"><?php echo appEscape((string) ($fonte['descricaoFonte'] ?? '')); ?></td>
                                    <td class="p-3">
                                        <span class="<?php echo (int) $fonte['statusFonte'] === 1 ? 'text-green-700' : 'text-gray-500'; ?>">
                                            <?php echo (int) $fonte['statusFonte'] === 1 ? 'Ativa' : 'Inativa'; ?>
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap p-3">
                                        <div class="flex items-center gap-3">
                                            <a href="?editar=<?php echo (int) $fonte['idFonte']; ?>#manutencao"
                                               aria-label="Alterar fonte <?php echo appEscape($fonte['nomeFonte']); ?>"
                                               title="Alterar fonte"
                                               class="inline-flex h-9 w-9 items-center justify-center rounded border border-elo-teal/20 text-elo-teal transition-colors hover:bg-elo-teal/10">
                                                <i class="ph ph-pencil-simple text-lg" aria-hidden="true"></i>
                                            </a>
                                            <form method="post" data-confirm="Confirma excluir esta fonte de recurso?">
                                                <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                                                <input type="hidden" name="acao" value="excluir">
                                                <input type="hidden" name="idFonte" value="<?php echo (int) $fonte['idFonte']; ?>">
                                                <button type="submit" aria-label="Excluir fonte <?php echo appEscape($fonte['nomeFonte']); ?>"
                                                        title="Excluir fonte"
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
                    <?php if (!$fontes): ?>
                        <p class="p-6 text-center text-sm text-gray-500">Nenhuma fonte de recurso cadastrada.</p>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
    <?php $toastSuccess = $resultado; $toastError = $erro; include __DIR__ . '/../../../components/toast.php'; ?>
    <script src="<?php echo appEscape(appBaseUrl() . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
</body>
</html>