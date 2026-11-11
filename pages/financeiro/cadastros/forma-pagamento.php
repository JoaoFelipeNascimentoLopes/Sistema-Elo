<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('financeiro.forma-pagamento');
$pdo = appPdo();
$erro = '';
$formaEdicao = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    $acaoInput = $_POST['acao'] ?? '';
    $acao = is_scalar($acaoInput) ? (string) $acaoInput : '';

    try {
        if ($acao === 'salvar') {
            $idInput = $_POST['idFormaPagamento'] ?? 0;
            $nomeInput = $_POST['nomeFormaPagamento'] ?? null;
            $descricaoInput = $_POST['descricaoFormaPagamento'] ?? null;
            $idFormaPagamento = is_scalar($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;
            $nome = is_scalar($nomeInput) ? trim((string) $nomeInput) : '';
            $descricao = is_scalar($descricaoInput) ? trim((string) $descricaoInput) : '';
            $status = isset($_POST['statusFormaPagamento']) ? 1 : 0;

            if ($idFormaPagamento === false || $idFormaPagamento < 0
                || $nome === '' || mb_strlen($nome, 'UTF-8') > 100
                || mb_strlen($descricao, 'UTF-8') > 255) {
                throw new InvalidArgumentException('Confira o nome e a descrição da forma de pagamento.');
            }

            if ($idFormaPagamento > 0) {
                $existing = $pdo->prepare(
                    'SELECT idFormaPagamento FROM tbFormaPagamento
                     WHERE idFormaPagamento = :idFormaPagamento'
                );
                $existing->execute(['idFormaPagamento' => $idFormaPagamento]);
                if (!$existing->fetchColumn()) {
                    throw new InvalidArgumentException('Forma de pagamento não encontrada.');
                }

                $statement = $pdo->prepare(
                    'UPDATE tbFormaPagamento
                     SET nomeFormaPagamento = :nome, descricaoFormaPagamento = :descricao,
                         statusFormaPagamento = :status, idUsuarioAlteracao = :idUsuarioAlteracao
                     WHERE idFormaPagamento = :idFormaPagamento'
                );
                $statement->execute([
                    'nome' => $nome,
                    'descricao' => $descricao !== '' ? $descricao : null,
                    'status' => $status,
                    'idUsuarioAlteracao' => (int) $usuarioAtual['idUsuario'],
                    'idFormaPagamento' => $idFormaPagamento,
                ]);
                header('Location: ' . appTabUrl('forma-pagamento.php?resultado=atualizado'));
                exit;
            }

            $statement = $pdo->prepare(
                'INSERT INTO tbFormaPagamento
                    (nomeFormaPagamento, descricaoFormaPagamento, statusFormaPagamento, idUsuarioCriacao)
                 VALUES (:nome, :descricao, :status, :idUsuarioCriacao)'
            );
            $statement->execute([
                'nome' => $nome,
                'descricao' => $descricao !== '' ? $descricao : null,
                'status' => $status,
                'idUsuarioCriacao' => (int) $usuarioAtual['idUsuario'],
            ]);
            header('Location: ' . appTabUrl('forma-pagamento.php?resultado=criado'));
            exit;
        }

        if ($acao === 'excluir') {
            $idInput = $_POST['idFormaPagamento'] ?? 0;
            $idFormaPagamento = is_scalar($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;
            if (!$idFormaPagamento || $idFormaPagamento < 1) {
                throw new InvalidArgumentException('Forma de pagamento inválida.');
            }

            $statement = $pdo->prepare(
                'DELETE FROM tbFormaPagamento WHERE idFormaPagamento = :idFormaPagamento'
            );
            $statement->execute(['idFormaPagamento' => $idFormaPagamento]);
            if ($statement->rowCount() === 0) {
                throw new InvalidArgumentException('Forma de pagamento não encontrada.');
            }
            header('Location: ' . appTabUrl('forma-pagamento.php?resultado=excluido'));
            exit;
        }

        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException $exception) {
        $erro = $exception->getMessage();
        if ($acao === 'salvar') {
            $submittedIdInput = $_POST['idFormaPagamento'] ?? 0;
            $submittedId = is_scalar($submittedIdInput) ? filter_var($submittedIdInput, FILTER_VALIDATE_INT) : false;
            $formaEdicao = [
                'idFormaPagamento' => $submittedId !== false ? $submittedId : 0,
                'nomeFormaPagamento' => is_scalar($_POST['nomeFormaPagamento'] ?? null) ? (string) $_POST['nomeFormaPagamento'] : '',
                'descricaoFormaPagamento' => is_scalar($_POST['descricaoFormaPagamento'] ?? null) ? (string) $_POST['descricaoFormaPagamento'] : '',
                'statusFormaPagamento' => isset($_POST['statusFormaPagamento']) ? 1 : 0,
            ];
        }
    } catch (PDOException $exception) {
        error_log('Falha ao salvar forma de pagamento: ' . $exception->getMessage());
        $errorCode = (int) ($exception->errorInfo[1] ?? 0);
        $erro = $acao === 'excluir' && $errorCode === 1451
            ? 'Não é possível excluir esta forma de pagamento porque ela está sendo utilizada.'
            : ($errorCode === 1062
                ? 'Já existe uma forma de pagamento com esse nome.'
                : 'Não foi possível salvar a forma de pagamento.');
        if ($acao === 'salvar') {
            $submittedIdInput = $_POST['idFormaPagamento'] ?? 0;
            $submittedId = is_scalar($submittedIdInput) ? filter_var($submittedIdInput, FILTER_VALIDATE_INT) : false;
            $formaEdicao = [
                'idFormaPagamento' => $submittedId !== false ? $submittedId : 0,
                'nomeFormaPagamento' => is_scalar($_POST['nomeFormaPagamento'] ?? null) ? (string) $_POST['nomeFormaPagamento'] : '',
                'descricaoFormaPagamento' => is_scalar($_POST['descricaoFormaPagamento'] ?? null) ? (string) $_POST['descricaoFormaPagamento'] : '',
                'statusFormaPagamento' => isset($_POST['statusFormaPagamento']) ? 1 : 0,
            ];
        }
    }
}

$idEdicao = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
if ($formaEdicao === null && $idEdicao) {
    $statement = $pdo->prepare(
        'SELECT f.idFormaPagamento, f.nomeFormaPagamento, f.descricaoFormaPagamento,
                f.statusFormaPagamento, f.criadoEm, f.atualizadoEm, criador.idUsuario AS criadoPorId,
                criador.nomeCompleto AS criadoPor, alterador.idUsuario AS alteradoPorId,
                alterador.nomeCompleto AS alteradoPor
         FROM tbFormaPagamento f
         INNER JOIN tbUsuario criador ON criador.idUsuario = f.idUsuarioCriacao
         LEFT JOIN tbUsuario alterador ON alterador.idUsuario = f.idUsuarioAlteracao
         WHERE f.idFormaPagamento = :idFormaPagamento'
    );
    $statement->execute(['idFormaPagamento' => $idEdicao]);
    $formaEdicao = $statement->fetch() ?: null;
    if (!$formaEdicao) {
        $erro = 'Forma de pagamento não encontrada.';
    }
}

if ($formaEdicao !== null && !isset($formaEdicao['criadoPor'])) {
    if ((int) ($formaEdicao['idFormaPagamento'] ?? 0) > 0) {
        $auditStatement = $pdo->prepare(
            'SELECT f.criadoEm, f.atualizadoEm, criador.idUsuario AS criadoPorId, criador.nomeCompleto AS criadoPor,
                    alterador.idUsuario AS alteradoPorId, alterador.nomeCompleto AS alteradoPor
             FROM tbFormaPagamento f
             INNER JOIN tbUsuario criador ON criador.idUsuario = f.idUsuarioCriacao
             LEFT JOIN tbUsuario alterador ON alterador.idUsuario = f.idUsuarioAlteracao
             WHERE f.idFormaPagamento = :idFormaPagamento'
        );
        $auditStatement->execute(['idFormaPagamento' => (int) $formaEdicao['idFormaPagamento']]);
        $auditData = $auditStatement->fetch() ?: [];
        $formaEdicao['criadoPorId'] = $auditData['criadoPorId'] ?? null;
        $formaEdicao['criadoPor'] = $auditData['criadoPor'] ?? '';
        $formaEdicao['alteradoPorId'] = $auditData['alteradoPorId'] ?? null;
        $formaEdicao['alteradoPor'] = $auditData['alteradoPor'] ?? '';
    } else {
        $formaEdicao['criadoPorId'] = (int) $usuarioAtual['idUsuario'];
        $formaEdicao['criadoPor'] = (string) $usuarioAtual['nomeCompleto'];
        $formaEdicao['alteradoPorId'] = null;
        $formaEdicao['alteradoPor'] = '';
    }
}

$criadoPorId = $formaEdicao['criadoPorId'] ?? $usuarioAtual['idUsuario'];
$criadoPorNome = (string) ($formaEdicao['criadoPor'] ?? $usuarioAtual['nomeCompleto']);
$criadoEm = (string) ($formaEdicao['criadoEm'] ?? date('Y-m-d H:i:s'));
$criadoPor = $criadoPorId !== null
    ? $criadoPorId . ' - ' . $criadoPorNome . ' - ' . date('d/m/Y H:i', strtotime($criadoEm))
    : '—';
$alteradoPorId = $formaEdicao['alteradoPorId'] ?? null;
$alteradoPorNome = (string) ($formaEdicao['alteradoPor'] ?? '');
$atualizadoEm = $formaEdicao['atualizadoEm'] ?? null;
$alteradoPor = $alteradoPorId !== null
    ? $alteradoPorId . ' - ' . $alteradoPorNome . ' - ' . date('d/m/Y H:i', strtotime((string) $atualizadoEm))
    : '';

$formasPagamento = $pdo->query(
    'SELECT idFormaPagamento, nomeFormaPagamento, descricaoFormaPagamento, statusFormaPagamento
     FROM tbFormaPagamento
     ORDER BY nomeFormaPagamento'
)->fetchAll();

$resultado = [
    'criado' => 'Forma de pagamento cadastrada.',
    'atualizado' => 'Forma de pagamento atualizada.',
    'excluido' => 'Forma de pagamento excluída.',
][(string) ($_GET['resultado'] ?? '')] ?? '';
$abaInicial = ($formaEdicao || ($erro !== ''
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
            <h1 class="mb-6 text-2xl font-bold text-gray-800">Formas de Pagamento</h1>
            <?php include __DIR__ . '/../../../components/tabs-pesquisa-manutencao.php'; ?>

            <section id="painel-manutencao" role="tabpanel" aria-labelledby="tab-manutencao"
                     class="mb-8 rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'manutencao' ? '' : 'hidden'; ?>>
                <h2 class="mb-4 text-lg font-semibold"><?php echo !empty($formaEdicao['idFormaPagamento']) ? 'Alterar forma de pagamento' : 'Nova forma de pagamento'; ?></h2>
                <form method="post" data-confirm="Confirma gravar estes dados?" class="grid gap-4 md:grid-cols-2">
                    <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="idFormaPagamento" value="<?php echo (int) ($formaEdicao['idFormaPagamento'] ?? 0); ?>">
                    <label class="text-sm font-medium text-gray-700">Nome da forma de pagamento
                        <input name="nomeFormaPagamento" required maxlength="100" value="<?php echo appEscape((string) ($formaEdicao['nomeFormaPagamento'] ?? '')); ?>"
                               class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    </label>
                    <label class="flex items-center gap-2 self-end pb-2 text-sm text-gray-700">
                        <input type="checkbox" name="statusFormaPagamento" value="1" <?php echo !isset($formaEdicao['statusFormaPagamento']) || (int) $formaEdicao['statusFormaPagamento'] === 1 ? 'checked' : ''; ?>
                               class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                        Forma de pagamento ativa
                    </label>
                    <label class="text-sm font-medium text-gray-700 md:col-span-2">Descrição
                        <textarea name="descricaoFormaPagamento" maxlength="255" rows="3" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"><?php echo appEscape((string) ($formaEdicao['descricaoFormaPagamento'] ?? '')); ?></textarea>
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
                            <?php echo !empty($formaEdicao['idFormaPagamento']) ? 'Salvar alterações' : 'Cadastrar forma'; ?>
                        </button>
                        <?php if (!empty($formaEdicao['idFormaPagamento'])): ?>
                            <a href="forma-pagamento.php#pesquisa" class="inline-flex items-center gap-2 rounded border px-4 py-2 text-gray-700 hover:bg-gray-50">
                                <i class="ph ph-x-circle" aria-hidden="true"></i> Cancelar
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section id="painel-pesquisa" role="tabpanel" aria-labelledby="tab-pesquisa"
                     class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'pesquisa' ? '' : 'hidden'; ?>>
                <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <h2 class="text-lg font-semibold">Formas cadastradas</h2>
                    <div class="flex flex-wrap gap-2">
                        <input data-filter-input="listaFormasPagamento" type="search" placeholder="Pesquisar formas de pagamento..."
                               aria-label="Pesquisar formas de pagamento" class="rounded border border-gray-300 px-3 py-2 text-sm">
                        <button type="button" data-tab-open="manutencao" class="rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">
                            + Nova forma
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[700px] text-left text-sm">
                        <thead class="border-b bg-gray-50 text-gray-600">
                            <tr>
                                <th class="p-3">Forma de pagamento</th>
                                <th class="p-3">Descrição</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="listaFormasPagamento">
                            <?php foreach ($formasPagamento as $forma): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-3 font-medium text-gray-800"><?php echo appEscape($forma['nomeFormaPagamento']); ?></td>
                                    <td class="max-w-sm whitespace-normal p-3 text-gray-600"><?php echo appEscape((string) ($forma['descricaoFormaPagamento'] ?? '')); ?></td>
                                    <td class="p-3">
                                        <span class="<?php echo (int) $forma['statusFormaPagamento'] === 1 ? 'text-green-700' : 'text-gray-500'; ?>">
                                            <?php echo (int) $forma['statusFormaPagamento'] === 1 ? 'Ativa' : 'Inativa'; ?>
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap p-3">
                                        <div class="flex items-center gap-3">
                                            <a href="?editar=<?php echo (int) $forma['idFormaPagamento']; ?>#manutencao"
                                               aria-label="Alterar forma de pagamento <?php echo appEscape($forma['nomeFormaPagamento']); ?>"
                                               title="Alterar forma de pagamento"
                                               class="inline-flex h-9 w-9 items-center justify-center rounded border border-elo-teal/20 text-elo-teal transition-colors hover:bg-elo-teal/10">
                                                <i class="ph ph-pencil-simple text-lg" aria-hidden="true"></i>
                                            </a>
                                            <form method="post" data-confirm="Confirma excluir esta forma de pagamento?">
                                                <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                                                <input type="hidden" name="acao" value="excluir">
                                                <input type="hidden" name="idFormaPagamento" value="<?php echo (int) $forma['idFormaPagamento']; ?>">
                                                <button type="submit" aria-label="Excluir forma de pagamento <?php echo appEscape($forma['nomeFormaPagamento']); ?>"
                                                        title="Excluir forma de pagamento"
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
                    <?php if (!$formasPagamento): ?>
                        <p class="p-6 text-center text-sm text-gray-500">Nenhuma forma de pagamento cadastrada.</p>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
    <?php $toastSuccess = $resultado; $toastError = $erro; include __DIR__ . '/../../../components/toast.php'; ?>
    <script src="<?php echo appEscape(appBaseUrl() . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
</body>
</html>
