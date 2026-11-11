<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('financeiro.cadastros/fornecedores-prestadores');
$pdo = appPdo();
$erro = '';
$fornecedorEdicao = null;

$validarDocumento = static function (string $tipoPessoa, string $documento): bool {
    if ($tipoPessoa === 'fisica') {
        if (!preg_match('/^\d{11}$/', $documento) || preg_match('/^(\d)\1{10}$/', $documento)) {
            return false;
        }
        $digits = array_map('intval', str_split($documento));
        foreach ([9, 10] as $position) {
            $sum = 0;
            for ($index = 0; $index < $position; $index++) {
                $sum += $digits[$index] * (($position + 1) - $index);
            }
            $remainder = ($sum * 10) % 11;
            $expected = $remainder === 10 ? 0 : $remainder;
            if ($digits[$position] !== $expected) {
                return false;
            }
        }
        return true;
    }

    if ($tipoPessoa !== 'juridica'
        || !preg_match('/^[A-Z0-9]{12}\d{2}$/', $documento)
        || preg_match('/^([A-Z0-9])\1{13}$/', $documento)) {
        return false;
    }

    $calculateDigit = static function (string $base, array $weights): int {
        $sum = 0;
        foreach (str_split($base) as $index => $character) {
            $sum += (ord($character) - 48) * $weights[$index];
        }
        $remainder = $sum % 11;
        return $remainder < 2 ? 0 : 11 - $remainder;
    };
    $firstDigit = $calculateDigit(substr($documento, 0, 12), [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
    $secondDigit = $calculateDigit(substr($documento, 0, 12) . $firstDigit, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

    return substr($documento, -2) === $firstDigit . $secondDigit;
};
$formatarDocumento = static function (string $documento): string {
    if (strlen($documento) === 11 && ctype_digit($documento)) {
        return substr($documento, 0, 3) . '.' . substr($documento, 3, 3) . '.' . substr($documento, 6, 3) . '-' . substr($documento, 9, 2);
    }
    if (strlen($documento) === 14) {
        return substr($documento, 0, 2) . '.' . substr($documento, 2, 3) . '.' . substr($documento, 5, 3) . '/' . substr($documento, 8, 4) . '-' . substr($documento, 12, 2);
    }
    return $documento;
};
$formatarTelefone = static function (?string $telefone): string {
    $digits = preg_replace('/\D+/', '', (string) $telefone);
    if (strlen($digits) === 10) {
        return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 4) . '-' . substr($digits, 6, 4);
    }
    if (strlen($digits) === 11) {
        return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 5) . '-' . substr($digits, 7, 4);
    }
    return (string) $telefone;
};
$validarTelefone = static function (string $telefone): bool {
    if ($telefone === '') {
        return true;
    }
    $areaCodes = [
        '11', '12', '13', '14', '15', '16', '17', '18', '19', '21', '22', '24',
        '27', '28', '31', '32', '33', '34', '35', '37', '38', '41', '42', '43',
        '44', '45', '46', '47', '48', '49', '51', '53', '54', '55', '61', '62',
        '63', '64', '65', '66', '67', '68', '69', '71', '73', '74', '75', '77',
        '79', '81', '82', '83', '84', '85', '86', '87', '88', '89', '91', '92',
        '93', '94', '95', '96', '97', '98', '99',
    ];
    if (!preg_match('/^\d{10,11}$/', $telefone)
        || !in_array(substr($telefone, 0, 2), $areaCodes, true)
        || preg_match('/^(\d)\1+$/', $telefone)) {
        return false;
    }
    return (strlen($telefone) === 10 && preg_match('/^[2-5]\d{7}$/', substr($telefone, 2)) === 1)
        || (strlen($telefone) === 11 && preg_match('/^9\d{8}$/', substr($telefone, 2)) === 1);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    $acaoInput = $_POST['acao'] ?? '';
    $acao = is_scalar($acaoInput) ? (string) $acaoInput : '';

    try {
        if ($acao === 'salvar') {
            $idInput = $_POST['idFornecedorPrestador'] ?? 0;
            $idFornecedorPrestador = is_scalar($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;
            $tipoPessoaInput = $_POST['tipoPessoa'] ?? '';
            $documentoInput = $_POST['documento'] ?? '';
            $nomeInput = $_POST['nomeRazaoSocial'] ?? '';
            $fantasiaInput = $_POST['nomeFantasia'] ?? '';
            $contatoInput = $_POST['nomeContato'] ?? '';
            $emailInput = $_POST['email'] ?? '';
            $telefoneInput = $_POST['telefone'] ?? '';
            $observacoesInput = $_POST['observacoes'] ?? '';
            $tipoPessoa = is_scalar($tipoPessoaInput) ? (string) $tipoPessoaInput : '';
            $documento = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', is_scalar($documentoInput) ? (string) $documentoInput : ''));
            $nome = is_scalar($nomeInput) ? trim((string) $nomeInput) : '';
            $fantasia = $tipoPessoa === 'juridica' && is_scalar($fantasiaInput) ? trim((string) $fantasiaInput) : '';
            $contato = $tipoPessoa === 'juridica' && is_scalar($contatoInput) ? trim((string) $contatoInput) : '';
            $email = is_scalar($emailInput) ? trim((string) $emailInput) : '';
            $telefone = preg_replace('/\D+/', '', is_scalar($telefoneInput) ? (string) $telefoneInput : '');
            $observacoes = is_scalar($observacoesInput) ? trim((string) $observacoesInput) : '';
            $status = isset($_POST['statusFornecedor']) ? 1 : 0;

            if ($idFornecedorPrestador === false || $idFornecedorPrestador < 0
                || !in_array($tipoPessoa, ['fisica', 'juridica'], true)
                || !$validarDocumento($tipoPessoa, $documento)
                || $nome === '' || mb_strlen($nome, 'UTF-8') > 150
                || mb_strlen($fantasia, 'UTF-8') > 150
                || mb_strlen($contato, 'UTF-8') > 150
                || mb_strlen($email, 'UTF-8') > 190
                || mb_strlen($observacoes, 'UTF-8') > 255) {
                throw new InvalidArgumentException('Confira o tipo de pessoa, CPF/CNPJ, nome e os limites dos campos.');
            }
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('Informe um e-mail válido.');
            }
            if (!$validarTelefone($telefone)) {
                throw new InvalidArgumentException('Informe um telefone brasileiro válido com DDD.');
            }

            $values = [
                'tipoPessoa' => $tipoPessoa,
                'documento' => $documento,
                'nomeRazaoSocial' => $nome,
                'nomeFantasia' => $fantasia !== '' ? $fantasia : null,
                'nomeContato' => $contato !== '' ? $contato : null,
                'email' => $email !== '' ? $email : null,
                'telefone' => $telefone !== '' ? $telefone : null,
                'observacoes' => $observacoes !== '' ? $observacoes : null,
                'statusFornecedor' => $status,
            ];

            if ($idFornecedorPrestador > 0) {
                $statement = $pdo->prepare(
                    'UPDATE tbFornecedorPrestador
                     SET tipoPessoa = :tipoPessoa, documento = :documento,
                         nomeRazaoSocial = :nomeRazaoSocial, nomeFantasia = :nomeFantasia,
                         nomeContato = :nomeContato, email = :email, telefone = :telefone,
                         observacoes = :observacoes, statusFornecedor = :statusFornecedor,
                         idUsuarioAlteracao = :idUsuarioAlteracao
                     WHERE idFornecedorPrestador = :idFornecedorPrestador'
                );
                $statement->execute($values + [
                    'idUsuarioAlteracao' => (int) $usuarioAtual['idUsuario'],
                    'idFornecedorPrestador' => $idFornecedorPrestador,
                ]);
                if ($statement->rowCount() === 0) {
                    $exists = $pdo->prepare('SELECT 1 FROM tbFornecedorPrestador WHERE idFornecedorPrestador = :id');
                    $exists->execute(['id' => $idFornecedorPrestador]);
                    if (!$exists->fetchColumn()) {
                        throw new InvalidArgumentException('Fornecedor/prestador não encontrado.');
                    }
                }
                header('Location: ' . appTabUrl('fornecedores-prestadores.php?resultado=atualizado'));
                exit;
            }

            $statement = $pdo->prepare(
                'INSERT INTO tbFornecedorPrestador
                    (tipoPessoa, documento, nomeRazaoSocial, nomeFantasia, nomeContato,
                     email, telefone, observacoes, statusFornecedor, idUsuarioCriacao)
                 VALUES
                    (:tipoPessoa, :documento, :nomeRazaoSocial, :nomeFantasia, :nomeContato,
                     :email, :telefone, :observacoes, :statusFornecedor, :idUsuarioCriacao)'
            );
            $statement->execute($values + ['idUsuarioCriacao' => (int) $usuarioAtual['idUsuario']]);
            header('Location: ' . appTabUrl('fornecedores-prestadores.php?resultado=criado'));
            exit;
        }

        if ($acao === 'excluir') {
            $idInput = $_POST['idFornecedorPrestador'] ?? 0;
            $id = is_scalar($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;
            if (!$id || $id < 1) {
                throw new InvalidArgumentException('Fornecedor/prestador inválido.');
            }
            $statement = $pdo->prepare('DELETE FROM tbFornecedorPrestador WHERE idFornecedorPrestador = :id');
            $statement->execute(['id' => $id]);
            if ($statement->rowCount() === 0) {
                throw new InvalidArgumentException('Fornecedor/prestador não encontrado.');
            }
            header('Location: ' . appTabUrl('fornecedores-prestadores.php?resultado=excluido'));
            exit;
        }

        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException $exception) {
        $erro = $exception->getMessage();
        if ($acao === 'salvar') {
            $submittedId = $_POST['idFornecedorPrestador'] ?? 0;
            $formaId = is_scalar($submittedId) ? filter_var($submittedId, FILTER_VALIDATE_INT) : false;
            $fornecedorEdicao = [
                'idFornecedorPrestador' => $formaId !== false ? $formaId : 0,
                'tipoPessoa' => is_scalar($_POST['tipoPessoa'] ?? null) ? (string) $_POST['tipoPessoa'] : '',
                'documento' => is_scalar($_POST['documento'] ?? null) ? (string) $_POST['documento'] : '',
                'nomeRazaoSocial' => is_scalar($_POST['nomeRazaoSocial'] ?? null) ? (string) $_POST['nomeRazaoSocial'] : '',
                'nomeFantasia' => is_scalar($_POST['nomeFantasia'] ?? null) ? (string) $_POST['nomeFantasia'] : '',
                'nomeContato' => is_scalar($_POST['nomeContato'] ?? null) ? (string) $_POST['nomeContato'] : '',
                'email' => is_scalar($_POST['email'] ?? null) ? (string) $_POST['email'] : '',
                'telefone' => is_scalar($_POST['telefone'] ?? null) ? (string) $_POST['telefone'] : '',
                'observacoes' => is_scalar($_POST['observacoes'] ?? null) ? (string) $_POST['observacoes'] : '',
                'statusFornecedor' => isset($_POST['statusFornecedor']) ? 1 : 0,
            ];
        }
    } catch (PDOException $exception) {
        error_log('Falha ao salvar fornecedor/prestador: ' . $exception->getMessage());
        $errorCode = (int) ($exception->errorInfo[1] ?? 0);
        $erro = $errorCode === 1062
            ? 'Já existe um cadastro com este CPF/CNPJ.'
            : ($acao === 'excluir' && $errorCode === 1451
                ? 'Não é possível excluir este cadastro porque ele está sendo utilizado.'
                : 'Não foi possível salvar o cadastro do fornecedor/prestador.');
        if ($acao === 'salvar') {
            $submittedId = $_POST['idFornecedorPrestador'] ?? 0;
            $formaId = is_scalar($submittedId) ? filter_var($submittedId, FILTER_VALIDATE_INT) : false;
            $fornecedorEdicao = [
                'idFornecedorPrestador' => $formaId !== false ? $formaId : 0,
                'tipoPessoa' => is_scalar($_POST['tipoPessoa'] ?? null) ? (string) $_POST['tipoPessoa'] : '',
                'documento' => is_scalar($_POST['documento'] ?? null) ? (string) $_POST['documento'] : '',
                'nomeRazaoSocial' => is_scalar($_POST['nomeRazaoSocial'] ?? null) ? (string) $_POST['nomeRazaoSocial'] : '',
                'nomeFantasia' => is_scalar($_POST['nomeFantasia'] ?? null) ? (string) $_POST['nomeFantasia'] : '',
                'nomeContato' => is_scalar($_POST['nomeContato'] ?? null) ? (string) $_POST['nomeContato'] : '',
                'email' => is_scalar($_POST['email'] ?? null) ? (string) $_POST['email'] : '',
                'telefone' => is_scalar($_POST['telefone'] ?? null) ? (string) $_POST['telefone'] : '',
                'observacoes' => is_scalar($_POST['observacoes'] ?? null) ? (string) $_POST['observacoes'] : '',
                'statusFornecedor' => isset($_POST['statusFornecedor']) ? 1 : 0,
            ];
        }
    }
}

$idEdicaoInput = $_GET['editar'] ?? null;
$idEdicao = is_scalar($idEdicaoInput) ? filter_var($idEdicaoInput, FILTER_VALIDATE_INT) : false;
if ($fornecedorEdicao === null && $idEdicao) {
    $statement = $pdo->prepare(
        'SELECT f.idFornecedorPrestador, f.tipoPessoa, f.documento, f.nomeRazaoSocial,
               f.nomeFantasia, f.nomeContato, f.email, f.telefone, f.observacoes,
               f.statusFornecedor, criador.idUsuario AS criadoPorId, criador.nomeCompleto AS criadoPor,
                alterador.idUsuario AS alteradoPorId, alterador.nomeCompleto AS alteradoPor
         FROM tbFornecedorPrestador f
         INNER JOIN tbUsuario criador ON criador.idUsuario = f.idUsuarioCriacao
         LEFT JOIN tbUsuario alterador ON alterador.idUsuario = f.idUsuarioAlteracao
         WHERE f.idFornecedorPrestador = :id'
    );
    $statement->execute(['id' => $idEdicao]);
    $fornecedorEdicao = $statement->fetch() ?: null;
    if (!$fornecedorEdicao) {
        $erro = 'Fornecedor/prestador não encontrado.';
    }
}

if ($fornecedorEdicao !== null && !isset($fornecedorEdicao['criadoPor'])) {
    if ((int) ($fornecedorEdicao['idFornecedorPrestador'] ?? 0) > 0) {
        $audit = $pdo->prepare(
            'SELECT criador.idUsuario AS criadoPorId, criador.nomeCompleto AS criadoPor,
                    alterador.idUsuario AS alteradoPorId, alterador.nomeCompleto AS alteradoPor
             FROM tbFornecedorPrestador f
             INNER JOIN tbUsuario criador ON criador.idUsuario = f.idUsuarioCriacao
             LEFT JOIN tbUsuario alterador ON alterador.idUsuario = f.idUsuarioAlteracao
             WHERE f.idFornecedorPrestador = :id'
        );
        $audit->execute(['id' => (int) $fornecedorEdicao['idFornecedorPrestador']]);
        $fornecedorEdicao = array_merge($fornecedorEdicao, $audit->fetch() ?: []);
    } else {
        $fornecedorEdicao['criadoPorId'] = (int) $usuarioAtual['idUsuario'];
        $fornecedorEdicao['criadoPor'] = (string) $usuarioAtual['nomeCompleto'];
        $fornecedorEdicao['alteradoPorId'] = null;
        $fornecedorEdicao['alteradoPor'] = '';
    }
}
$criadoPorId = $fornecedorEdicao['criadoPorId'] ?? $usuarioAtual['idUsuario'];
$criadoPorNome = (string) ($fornecedorEdicao['criadoPor'] ?? $usuarioAtual['nomeCompleto']);
$criadoPor = $criadoPorId . ' - ' . $criadoPorNome;
$alteradoPorId = $fornecedorEdicao['alteradoPorId'] ?? null;
$alteradoPor = $alteradoPorId !== null
    ? $alteradoPorId . ' - ' . (string) ($fornecedorEdicao['alteradoPor'] ?? '')
    : '';
$fornecedores = $pdo->query(
    'SELECT idFornecedorPrestador, tipoPessoa, documento, nomeRazaoSocial, nomeFantasia,
            nomeContato, email, telefone, statusFornecedor
     FROM tbFornecedorPrestador
     ORDER BY nomeRazaoSocial'
)->fetchAll();
$resultado = [
    'criado' => 'Fornecedor/prestador cadastrado.',
    'atualizado' => 'Fornecedor/prestador atualizado.',
    'excluido' => 'Fornecedor/prestador excluído.',
][(string) ($_GET['resultado'] ?? '')] ?? '';
$abaInicial = ($fornecedorEdicao || ($erro !== '' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar'))
    ? 'manutencao'
    : 'pesquisa';
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
            <h1 class="mb-6 text-2xl font-bold text-gray-800">Fornecedores e Prestadores</h1>
            <?php include __DIR__ . '/../../../components/tabs-pesquisa-manutencao.php'; ?>
            <section id="painel-manutencao" role="tabpanel" aria-labelledby="tab-manutencao"
                     class="mb-8 rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'manutencao' ? '' : 'hidden'; ?>>
                <h2 class="mb-4 text-lg font-semibold"><?php echo !empty($fornecedorEdicao['idFornecedorPrestador']) ? 'Alterar cadastro' : 'Novo fornecedor/prestador'; ?></h2>
                <form method="post" data-finance-contact-form data-confirm="Confirma gravar estes dados?" class="grid gap-4 md:grid-cols-2">
                    <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="idFornecedorPrestador" value="<?php echo (int) ($fornecedorEdicao['idFornecedorPrestador'] ?? 0); ?>">
                    <fieldset class="grid gap-4 rounded-lg border border-gray-200 p-4 md:col-span-2 md:grid-cols-2">
                        <legend class="px-2 text-sm font-semibold text-gray-700">1. Identificação</legend>
                        <label class="text-sm font-medium text-gray-700">Tipo de pessoa
                            <select name="tipoPessoa" data-finance-document-type required class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                                <option value="">Selecione</option>
                                <option value="fisica" <?php echo ($fornecedorEdicao['tipoPessoa'] ?? '') === 'fisica' ? 'selected' : ''; ?>>Pessoa Física</option>
                                <option value="juridica" <?php echo ($fornecedorEdicao['tipoPessoa'] ?? '') === 'juridica' ? 'selected' : ''; ?>>Pessoa Jurídica</option>
                            </select>
                        </label>
                        <label class="text-sm font-medium text-gray-700"><span data-finance-document-label>CPF/CNPJ</span>
                            <input name="documento" data-finance-document required <?php echo in_array(($fornecedorEdicao['tipoPessoa'] ?? ''), ['fisica', 'juridica'], true) ? '' : 'disabled'; ?> maxlength="18" inputmode="text" value="<?php echo appEscape((string) ($fornecedorEdicao['documento'] ?? '')); ?>"
                                   placeholder="Somente números ou CNPJ alfanumérico" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700 md:col-span-2"><span data-finance-person-name-label>Nome completo / razão social</span>
                            <input name="nomeRazaoSocial" required maxlength="150" value="<?php echo appEscape((string) ($fornecedorEdicao['nomeRazaoSocial'] ?? '')); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                    </fieldset>

                    <fieldset data-finance-legal-only <?php echo ($fornecedorEdicao['tipoPessoa'] ?? '') === 'juridica' ? '' : 'hidden'; ?> class="grid gap-4 rounded-lg border border-gray-200 p-4 md:col-span-2 md:grid-cols-2 <?php echo ($fornecedorEdicao['tipoPessoa'] ?? '') === 'juridica' ? '' : 'hidden'; ?>">
                        <legend class="px-2 text-sm font-semibold text-gray-700">2. Dados da empresa</legend>
                        <label class="text-sm font-medium text-gray-700">Nome fantasia
                            <input name="nomeFantasia" maxlength="150" value="<?php echo appEscape((string) ($fornecedorEdicao['nomeFantasia'] ?? '')); ?>" <?php echo ($fornecedorEdicao['tipoPessoa'] ?? '') === 'juridica' ? '' : 'disabled'; ?> class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Pessoa de contato
                            <input name="nomeContato" maxlength="150" value="<?php echo appEscape((string) ($fornecedorEdicao['nomeContato'] ?? '')); ?>" <?php echo ($fornecedorEdicao['tipoPessoa'] ?? '') === 'juridica' ? '' : 'disabled'; ?> class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <p class="text-xs text-gray-500 md:col-span-2">Nome fantasia e pessoa de contato são aplicáveis somente a pessoas jurídicas.</p>
                    </fieldset>

                    <fieldset class="grid gap-4 rounded-lg border border-gray-200 p-4 md:col-span-2 md:grid-cols-2">
                        <legend class="px-2 text-sm font-semibold text-gray-700">3. Contato</legend>
                        <label class="text-sm font-medium text-gray-700">E-mail
                            <input type="email" name="email" maxlength="190" value="<?php echo appEscape((string) ($fornecedorEdicao['email'] ?? '')); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Telefone
                            <input type="tel" name="telefone" data-finance-phone inputmode="tel" maxlength="20" value="<?php echo appEscape((string) ($fornecedorEdicao['telefone'] ?? '')); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                    </fieldset>

                    <fieldset class="grid gap-4 rounded-lg border border-gray-200 p-4 md:col-span-2 md:grid-cols-2">
                        <legend class="px-2 text-sm font-semibold text-gray-700">4. Observações e controle</legend>
                        <label class="text-sm font-medium text-gray-700 md:col-span-2">Observações
                            <textarea name="observacoes" maxlength="255" rows="3" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"><?php echo appEscape((string) ($fornecedorEdicao['observacoes'] ?? '')); ?></textarea>
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="statusFornecedor" value="1" <?php echo !isset($fornecedorEdicao['statusFornecedor']) || (int) $fornecedorEdicao['statusFornecedor'] === 1 ? 'checked' : ''; ?> class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                            Cadastro ativo
                        </label>
                        <label class="text-sm font-medium text-gray-700">Criado por
                            <input value="<?php echo appEscape($criadoPor); ?>" disabled class="mt-1 w-full cursor-not-allowed rounded border border-gray-200 bg-gray-100 px-3 py-2 text-gray-600">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Alterado por
                            <input value="<?php echo appEscape($alteradoPor !== '' ? $alteradoPor : '—'); ?>" disabled class="mt-1 w-full cursor-not-allowed rounded border border-gray-200 bg-gray-100 px-3 py-2 text-gray-600">
                        </label>
                    </fieldset>
                    <div class="flex flex-wrap gap-2 md:col-span-2">
                        <button type="submit" class="inline-flex items-center gap-2 rounded bg-elo-teal px-4 py-2 font-medium text-white hover:bg-elo-green"><i class="ph ph-floppy-disk" aria-hidden="true"></i><?php echo !empty($fornecedorEdicao['idFornecedorPrestador']) ? 'Salvar alterações' : 'Cadastrar'; ?></button>
                        <?php if (!empty($fornecedorEdicao['idFornecedorPrestador'])): ?>
                            <a href="fornecedores-prestadores.php#pesquisa" class="inline-flex items-center gap-2 rounded border px-4 py-2 text-gray-700 hover:bg-gray-50"><i class="ph ph-x-circle" aria-hidden="true"></i>Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section id="painel-pesquisa" role="tabpanel" aria-labelledby="tab-pesquisa" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'pesquisa' ? '' : 'hidden'; ?>>
                <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <h2 class="text-lg font-semibold">Fornecedores e Prestadores cadastrados</h2>
                    <div class="flex flex-wrap gap-2">
                        <input data-filter-input="listaFornecedores" type="search" placeholder="Pesquisar..." aria-label="Pesquisar fornecedores e prestadores" class="rounded border border-gray-300 px-3 py-2 text-sm">
                        <button type="button" data-tab-open="manutencao" class="rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">+ Novo cadastro</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead class="border-b bg-gray-50 text-gray-600"><tr><th class="p-3">Nome completo / Razão Social</th><th class="p-3">CPF/CNPJ</th><th class="p-3">Pessoa de contato</th><th class="p-3">E-mail</th><th class="p-3">Telefone</th><th class="p-3">Status</th><th class="p-3">Ações</th></tr></thead>
                        <tbody id="listaFornecedores">
                        <?php foreach ($fornecedores as $fornecedor): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-3 font-medium text-gray-800"><?php echo appEscape($fornecedor['nomeRazaoSocial']); ?></td>
                                <td class="p-3"><?php echo appEscape($formatarDocumento((string) $fornecedor['documento'])); ?></td>
                                <td class="p-3"><?php echo appEscape((string) ($fornecedor['nomeContato'] ?? '')); ?></td>
                                <td class="p-3"><?php echo appEscape((string) ($fornecedor['email'] ?? '')); ?></td>
                                <td class="p-3"><?php echo appEscape($formatarTelefone($fornecedor['telefone'] ?? null)); ?></td>
                                <td class="p-3"><span class="<?php echo (int) $fornecedor['statusFornecedor'] === 1 ? 'text-green-700' : 'text-gray-500'; ?>"><?php echo (int) $fornecedor['statusFornecedor'] === 1 ? 'Ativo' : 'Inativo'; ?></span></td>
                                <td class="whitespace-nowrap p-3"><div class="flex items-center gap-3">
                                    <a href="?editar=<?php echo (int) $fornecedor['idFornecedorPrestador']; ?>#manutencao" aria-label="Alterar cadastro <?php echo appEscape($fornecedor['nomeRazaoSocial']); ?>" class="inline-flex h-9 w-9 items-center justify-center rounded border border-elo-teal/20 text-elo-teal hover:bg-elo-teal/10"><i class="ph ph-pencil-simple text-lg" aria-hidden="true"></i></a>
                                    <form method="post" data-confirm="Confirma excluir este fornecedor/prestador?"><input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="idFornecedorPrestador" value="<?php echo (int) $fornecedor['idFornecedorPrestador']; ?>"><button type="submit" aria-label="Excluir cadastro <?php echo appEscape($fornecedor['nomeRazaoSocial']); ?>" class="inline-flex h-9 w-9 items-center justify-center rounded border border-red-200 text-red-600 hover:bg-red-50"><i class="ph ph-trash text-lg" aria-hidden="true"></i></button></form>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!$fornecedores): ?><p class="p-6 text-center text-sm text-gray-500">Nenhum fornecedor/prestador cadastrado.</p><?php endif; ?>
                </div>
            </section>
        </main>
    </div>
    <?php $toastSuccess = $resultado; $toastError = $erro; include __DIR__ . '/../../../components/toast.php'; ?>
    <script src="<?php echo appEscape(appBaseUrl() . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
</body>
</html>
