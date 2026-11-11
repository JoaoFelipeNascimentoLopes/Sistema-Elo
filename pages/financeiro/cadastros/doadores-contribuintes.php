<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('financeiro.cadastros/doadores-contribuintes');
$pdo = appPdo();
$erro = '';
$doadorEdicao = null;

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
            if ($digits[$position] !== ($remainder === 10 ? 0 : $remainder)) {
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
$consultarCep = static function (string $cep): array {
    if (!preg_match('/^\d{8}$/', $cep) || $cep === '00000000') {
        throw new InvalidArgumentException('Informe um CEP válido com 8 dígitos.');
    }

    $curl = curl_init('https://viacep.com.br/ws/' . $cep . '/json/');
    if ($curl === false) {
        throw new RuntimeException('Não foi possível iniciar a consulta do CEP.');
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($response === false) {
        $error = curl_error($curl);
        curl_close($curl);
        error_log('Falha ao consultar CEP de doador no ViaCEP: ' . $error);
        throw new RuntimeException('Não foi possível consultar o CEP agora. Tente novamente.');
    }
    curl_close($curl);
    if ($status !== 200) {
        error_log('ViaCEP respondeu com status HTTP ' . $status . ' ao consultar endereço de doador.');
        throw new RuntimeException('O serviço de CEP está indisponível. Tente novamente.');
    }

    try {
        $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        error_log('Resposta JSON inválida do ViaCEP para doador: ' . $exception->getMessage());
        throw new RuntimeException('O serviço de CEP retornou uma resposta inválida. Tente novamente.');
    }
    if (!is_array($result) || ($result['erro'] ?? false) === true) {
        throw new InvalidArgumentException('CEP não encontrado. Confira o número e tente novamente.');
    }
    if (!is_string($result['cep'] ?? null)
        || preg_replace('/\D+/', '', $result['cep']) !== $cep
        || !is_string($result['localidade'] ?? null)
        || !is_string($result['uf'] ?? null)
        || (isset($result['logradouro']) && !is_string($result['logradouro']))
        || (isset($result['bairro']) && !is_string($result['bairro']))) {
        error_log('ViaCEP retornou endereço incompleto ou inválido para CEP de doador.');
        throw new RuntimeException('O serviço de CEP retornou uma resposta inválida. Tente novamente.');
    }

    $address = [
        'logradouro' => trim((string) ($result['logradouro'] ?? '')),
        'bairro' => trim((string) ($result['bairro'] ?? '')),
        'cidade' => trim($result['localidade']),
        'uf' => strtoupper(trim($result['uf'])),
    ];
    if ($address['cidade'] === '' || !preg_match('/^[A-Z]{2}$/', $address['uf'])) {
        error_log('ViaCEP retornou cidade ou UF inválida para CEP de doador.');
        throw new RuntimeException('O serviço de CEP retornou um endereço inválido. Tente novamente.');
    }
    return $address;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    $acaoInput = $_POST['acao'] ?? '';
    $acao = is_scalar($acaoInput) ? (string) $acaoInput : '';

    if ($acao === 'consultarCep') {
        header('Content-Type: application/json; charset=UTF-8');
        $cepInput = $_POST['cep'] ?? '';
        if (!is_scalar($cepInput)) {
            http_response_code(400);
            echo json_encode(['message' => 'Informe um CEP válido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        try {
            $address = $consultarCep(preg_replace('/\D+/', '', (string) $cepInput));
            echo json_encode($address, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (InvalidArgumentException $exception) {
            http_response_code(422);
            echo json_encode(['message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
        } catch (RuntimeException $exception) {
            http_response_code(502);
            echo json_encode(['message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    try {
        if ($acao === 'salvar') {
            $idInput = $_POST['idDoadorContribuinte'] ?? 0;
            $id = is_scalar($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;
            $tipoInput = $_POST['tipoPessoa'] ?? '';
            $docInput = $_POST['documento'] ?? '';
            $nameInput = $_POST['nomeRazaoSocial'] ?? '';
            $tradeInput = $_POST['nomeFantasia'] ?? '';
            $emailInput = $_POST['email'] ?? '';
            $phoneInput = $_POST['telefone'] ?? '';
            $cepInput = $_POST['cep'] ?? '';
            $streetInput = $_POST['logradouro'] ?? '';
            $numberInput = $_POST['numero'] ?? '';
            $complementInput = $_POST['complemento'] ?? '';
            $districtInput = $_POST['bairro'] ?? '';
            $cityInput = $_POST['cidade'] ?? '';
            $stateInput = $_POST['uf'] ?? '';
            $notesInput = $_POST['observacoes'] ?? '';
            $tipo = is_scalar($tipoInput) ? (string) $tipoInput : '';
            $documento = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', is_scalar($docInput) ? (string) $docInput : ''));
            $nome = is_scalar($nameInput) ? trim((string) $nameInput) : '';
            $fantasia = $tipo === 'juridica' && is_scalar($tradeInput) ? trim((string) $tradeInput) : '';
            $email = is_scalar($emailInput) ? trim((string) $emailInput) : '';
            $telefone = preg_replace('/\D+/', '', is_scalar($phoneInput) ? (string) $phoneInput : '');
            $cep = preg_replace('/\D+/', '', is_scalar($cepInput) ? (string) $cepInput : '');
            $logradouro = is_scalar($streetInput) ? trim((string) $streetInput) : '';
            $numero = is_scalar($numberInput) ? trim((string) $numberInput) : '';
            $complemento = is_scalar($complementInput) ? trim((string) $complementInput) : '';
            $bairro = is_scalar($districtInput) ? trim((string) $districtInput) : '';
            $cidade = is_scalar($cityInput) ? trim((string) $cityInput) : '';
            $uf = strtoupper(is_scalar($stateInput) ? trim((string) $stateInput) : '');
            $observacoes = is_scalar($notesInput) ? trim((string) $notesInput) : '';
            $status = isset($_POST['statusDoador']) ? 1 : 0;

            if ($id === false || $id < 0 || !in_array($tipo, ['fisica', 'juridica'], true)
                || !$validarDocumento($tipo, $documento) || $nome === ''
                || mb_strlen($nome, 'UTF-8') > 150 || mb_strlen($fantasia, 'UTF-8') > 150
                || mb_strlen($email, 'UTF-8') > 190 || mb_strlen($logradouro, 'UTF-8') > 150
                || mb_strlen($numero, 'UTF-8') > 20 || mb_strlen($complemento, 'UTF-8') > 100
                || mb_strlen($bairro, 'UTF-8') > 100 || mb_strlen($cidade, 'UTF-8') > 100
                || mb_strlen($observacoes, 'UTF-8') > 255) {
                throw new InvalidArgumentException('Confira o tipo de pessoa, CPF/CNPJ, nome e os limites dos campos.');
            }
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('Informe um e-mail válido.');
            }
            if (!$validarTelefone($telefone)) {
                throw new InvalidArgumentException('Informe um telefone brasileiro válido com DDD.');
            }
            if ($cep !== '' && !preg_match('/^\d{8}$/', $cep)) {
                throw new InvalidArgumentException('Informe um CEP com 8 dígitos.');
            }
            if ($uf !== '' && !preg_match('/^[A-Z]{2}$/', $uf)) {
                throw new InvalidArgumentException('Informe uma UF válida com duas letras.');
            }

            $values = [
                'tipoPessoa' => $tipo,
                'documento' => $documento,
                'nomeRazaoSocial' => $nome,
                'nomeFantasia' => $fantasia !== '' ? $fantasia : null,
                'email' => $email !== '' ? $email : null,
                'telefone' => $telefone !== '' ? $telefone : null,
                'cep' => $cep !== '' ? $cep : null,
                'logradouro' => $logradouro !== '' ? $logradouro : null,
                'numero' => $numero !== '' ? $numero : null,
                'complemento' => $complemento !== '' ? $complemento : null,
                'bairro' => $bairro !== '' ? $bairro : null,
                'cidade' => $cidade !== '' ? $cidade : null,
                'uf' => $uf !== '' ? $uf : null,
                'observacoes' => $observacoes !== '' ? $observacoes : null,
                'statusDoador' => $status,
            ];

            if ($id > 0) {
                $statement = $pdo->prepare(
                    'UPDATE tbDoadorContribuinte
                     SET tipoPessoa = :tipoPessoa, documento = :documento,
                         nomeRazaoSocial = :nomeRazaoSocial, nomeFantasia = :nomeFantasia,
                         email = :email, telefone = :telefone, cep = :cep, logradouro = :logradouro,
                         numero = :numero, complemento = :complemento, bairro = :bairro,
                         cidade = :cidade, uf = :uf, observacoes = :observacoes,
                         statusDoador = :statusDoador, idUsuarioAlteracao = :idUsuarioAlteracao
                     WHERE idDoadorContribuinte = :idDoadorContribuinte'
                );
                $statement->execute($values + ['idUsuarioAlteracao' => (int) $usuarioAtual['idUsuario'], 'idDoadorContribuinte' => $id]);
                if ($statement->rowCount() === 0) {
                    $check = $pdo->prepare('SELECT 1 FROM tbDoadorContribuinte WHERE idDoadorContribuinte = :id');
                    $check->execute(['id' => $id]);
                    if (!$check->fetchColumn()) {
                        throw new InvalidArgumentException('Doador/contribuinte não encontrado.');
                    }
                }
                header('Location: ' . appTabUrl('doadores-contribuintes.php?resultado=atualizado'));
                exit;
            }

            $statement = $pdo->prepare(
                'INSERT INTO tbDoadorContribuinte
                    (tipoPessoa, documento, nomeRazaoSocial, nomeFantasia, email, telefone,
                     cep, logradouro, numero, complemento, bairro, cidade, uf, observacoes,
                     statusDoador, idUsuarioCriacao)
                 VALUES
                    (:tipoPessoa, :documento, :nomeRazaoSocial, :nomeFantasia, :email, :telefone,
                     :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :uf, :observacoes,
                     :statusDoador, :idUsuarioCriacao)'
            );
            $statement->execute($values + ['idUsuarioCriacao' => (int) $usuarioAtual['idUsuario']]);
            header('Location: ' . appTabUrl('doadores-contribuintes.php?resultado=criado'));
            exit;
        }

        if ($acao === 'excluir') {
            $idInput = $_POST['idDoadorContribuinte'] ?? 0;
            $id = is_scalar($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;
            if (!$id || $id < 1) {
                throw new InvalidArgumentException('Doador/contribuinte inválido.');
            }
            $statement = $pdo->prepare('DELETE FROM tbDoadorContribuinte WHERE idDoadorContribuinte = :id');
            $statement->execute(['id' => $id]);
            if ($statement->rowCount() === 0) {
                throw new InvalidArgumentException('Doador/contribuinte não encontrado.');
            }
            header('Location: ' . appTabUrl('doadores-contribuintes.php?resultado=excluido'));
            exit;
        }
        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException $exception) {
        $erro = $exception->getMessage();
        if ($acao === 'salvar') {
            $postedId = $_POST['idDoadorContribuinte'] ?? 0;
            $safeId = is_scalar($postedId) ? filter_var($postedId, FILTER_VALIDATE_INT) : false;
            $doadorEdicao = ['idDoadorContribuinte' => $safeId !== false ? $safeId : 0];
            foreach (['tipoPessoa', 'documento', 'nomeRazaoSocial', 'nomeFantasia', 'email', 'telefone', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'observacoes'] as $field) {
                $doadorEdicao[$field] = is_scalar($_POST[$field] ?? null) ? (string) $_POST[$field] : '';
            }
            $doadorEdicao['statusDoador'] = isset($_POST['statusDoador']) ? 1 : 0;
        }
    } catch (PDOException $exception) {
        error_log('Falha ao salvar doador/contribuinte: ' . $exception->getMessage());
        $errorCode = (int) ($exception->errorInfo[1] ?? 0);
        $erro = $errorCode === 1062
            ? 'Já existe um cadastro com este CPF/CNPJ.'
            : ($acao === 'excluir' && $errorCode === 1451
                ? 'Não é possível excluir este cadastro porque ele está sendo utilizado.'
                : 'Não foi possível salvar o cadastro do doador/contribuinte.');
        if ($acao === 'salvar') {
            $postedId = $_POST['idDoadorContribuinte'] ?? 0;
            $safeId = is_scalar($postedId) ? filter_var($postedId, FILTER_VALIDATE_INT) : false;
            $doadorEdicao = ['idDoadorContribuinte' => $safeId !== false ? $safeId : 0];
            foreach (['tipoPessoa', 'documento', 'nomeRazaoSocial', 'nomeFantasia', 'email', 'telefone', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'observacoes'] as $field) {
                $doadorEdicao[$field] = is_scalar($_POST[$field] ?? null) ? (string) $_POST[$field] : '';
            }
            $doadorEdicao['statusDoador'] = isset($_POST['statusDoador']) ? 1 : 0;
        }
    }
}

$idEdicaoInput = $_GET['editar'] ?? null;
$idEdicao = is_scalar($idEdicaoInput) ? filter_var($idEdicaoInput, FILTER_VALIDATE_INT) : false;
if ($doadorEdicao === null && $idEdicao) {
    $statement = $pdo->prepare(
        'SELECT d.idDoadorContribuinte, d.tipoPessoa, d.documento, d.nomeRazaoSocial,
                d.nomeFantasia, d.email, d.telefone, d.cep, d.logradouro, d.numero,
                d.complemento, d.bairro, d.cidade, d.uf, d.observacoes, d.statusDoador,
                criador.idUsuario AS criadoPorId, criador.nomeCompleto AS criadoPor,
                alterador.idUsuario AS alteradoPorId, alterador.nomeCompleto AS alteradoPor
         FROM tbDoadorContribuinte d
         INNER JOIN tbUsuario criador ON criador.idUsuario = d.idUsuarioCriacao
         LEFT JOIN tbUsuario alterador ON alterador.idUsuario = d.idUsuarioAlteracao
         WHERE d.idDoadorContribuinte = :id'
    );
    $statement->execute(['id' => $idEdicao]);
    $doadorEdicao = $statement->fetch() ?: null;
    if (!$doadorEdicao) {
        $erro = 'Doador/contribuinte não encontrado.';
    }
}
if ($doadorEdicao !== null && !isset($doadorEdicao['criadoPor'])) {
    if ((int) ($doadorEdicao['idDoadorContribuinte'] ?? 0) > 0) {
        $audit = $pdo->prepare(
            'SELECT criador.idUsuario AS criadoPorId, criador.nomeCompleto AS criadoPor,
                    alterador.idUsuario AS alteradoPorId, alterador.nomeCompleto AS alteradoPor
             FROM tbDoadorContribuinte d
             INNER JOIN tbUsuario criador ON criador.idUsuario = d.idUsuarioCriacao
             LEFT JOIN tbUsuario alterador ON alterador.idUsuario = d.idUsuarioAlteracao
             WHERE d.idDoadorContribuinte = :id'
        );
        $audit->execute(['id' => (int) $doadorEdicao['idDoadorContribuinte']]);
        $doadorEdicao = array_merge($doadorEdicao, $audit->fetch() ?: []);
    } else {
        $doadorEdicao['criadoPorId'] = (int) $usuarioAtual['idUsuario'];
        $doadorEdicao['criadoPor'] = (string) $usuarioAtual['nomeCompleto'];
        $doadorEdicao['alteradoPorId'] = null;
        $doadorEdicao['alteradoPor'] = '';
    }
}
$criadoPorId = $doadorEdicao['criadoPorId'] ?? $usuarioAtual['idUsuario'];
$criadoPor = $criadoPorId . ' - ' . (string) ($doadorEdicao['criadoPor'] ?? $usuarioAtual['nomeCompleto']);
$alteradoPorId = $doadorEdicao['alteradoPorId'] ?? null;
$alteradoPor = $alteradoPorId !== null ? $alteradoPorId . ' - ' . (string) ($doadorEdicao['alteradoPor'] ?? '') : '';
$doadores = $pdo->query(
    'SELECT idDoadorContribuinte, tipoPessoa, documento, nomeRazaoSocial, email, telefone,
            cidade, uf, statusDoador
     FROM tbDoadorContribuinte
     ORDER BY nomeRazaoSocial'
)->fetchAll();
$resultado = [
    'criado' => 'Doador/contribuinte cadastrado.',
    'atualizado' => 'Doador/contribuinte atualizado.',
    'excluido' => 'Doador/contribuinte excluído.',
][(string) ($_GET['resultado'] ?? '')] ?? '';
$abaInicial = ($doadorEdicao || ($erro !== '' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar'))
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
            <h1 class="mb-6 text-2xl font-bold text-gray-800">Doadores e Contribuintes</h1>
            <?php include __DIR__ . '/../../../components/tabs-pesquisa-manutencao.php'; ?>
            <section id="painel-manutencao" role="tabpanel" aria-labelledby="tab-manutencao" class="mb-8 rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'manutencao' ? '' : 'hidden'; ?>>
                <h2 class="mb-4 text-lg font-semibold"><?php echo !empty($doadorEdicao['idDoadorContribuinte']) ? 'Alterar cadastro' : 'Novo doador/contribuinte'; ?></h2>
                <form method="post" data-finance-contact-form data-confirm="Confirma gravar estes dados?" class="grid gap-4 md:grid-cols-2">
                    <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="idDoadorContribuinte" value="<?php echo (int) ($doadorEdicao['idDoadorContribuinte'] ?? 0); ?>">
                    <fieldset class="grid gap-4 rounded-lg border border-gray-200 p-4 md:col-span-2 md:grid-cols-2">
                        <legend class="px-2 text-sm font-semibold text-gray-700">1. Identificação</legend>
                        <label class="text-sm font-medium text-gray-700">Tipo de pessoa
                            <select name="tipoPessoa" data-finance-document-type required class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                                <option value="">Selecione</option>
                                <option value="fisica" <?php echo ($doadorEdicao['tipoPessoa'] ?? '') === 'fisica' ? 'selected' : ''; ?>>Pessoa Física</option>
                                <option value="juridica" <?php echo ($doadorEdicao['tipoPessoa'] ?? '') === 'juridica' ? 'selected' : ''; ?>>Pessoa Jurídica</option>
                            </select>
                        </label>
                        <label class="text-sm font-medium text-gray-700"><span data-finance-document-label>CPF/CNPJ</span>
                            <input name="documento" data-finance-document required <?php echo in_array(($doadorEdicao['tipoPessoa'] ?? ''), ['fisica', 'juridica'], true) ? '' : 'disabled'; ?> maxlength="18" inputmode="text" value="<?php echo appEscape((string) ($doadorEdicao['documento'] ?? '')); ?>" placeholder="Informe o CPF ou CNPJ" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700 md:col-span-2"><span data-finance-person-name-label>Nome completo / razão social</span>
                            <input name="nomeRazaoSocial" required maxlength="150" value="<?php echo appEscape((string) ($doadorEdicao['nomeRazaoSocial'] ?? '')); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label data-finance-legal-only <?php echo ($doadorEdicao['tipoPessoa'] ?? '') === 'juridica' ? '' : 'hidden'; ?> class="text-sm font-medium text-gray-700 md:col-span-2">Nome Fantasia
                            <input name="nomeFantasia" maxlength="150" value="<?php echo appEscape((string) ($doadorEdicao['nomeFantasia'] ?? '')); ?>" <?php echo ($doadorEdicao['tipoPessoa'] ?? '') === 'juridica' ? '' : 'disabled'; ?> class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                    </fieldset>

                    <fieldset class="grid gap-4 rounded-lg border border-gray-200 p-4 md:col-span-2 md:grid-cols-2">
                        <legend class="px-2 text-sm font-semibold text-gray-700">2. Contato</legend>
                        <label class="text-sm font-medium text-gray-700">E-mail
                            <input type="email" name="email" maxlength="190" value="<?php echo appEscape((string) ($doadorEdicao['email'] ?? '')); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Telefone
                            <input type="tel" name="telefone" data-finance-phone inputmode="tel" maxlength="20" value="<?php echo appEscape((string) ($doadorEdicao['telefone'] ?? '')); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                    </fieldset>

                    <fieldset class="grid gap-4 rounded-lg border border-gray-200 p-4 md:col-span-2">
                        <legend class="px-2 text-sm font-semibold text-gray-700">3. Endereço</legend>
                        <div class="grid gap-4 md:grid-cols-4">
                            <div>
                                <label class="text-sm font-medium text-gray-700" for="cep-doador">CEP
                                    <span class="mt-1 flex gap-2">
                                        <input id="cep-doador" name="cep" data-finance-cep inputmode="numeric" maxlength="9" value="<?php echo appEscape((string) ($doadorEdicao['cep'] ?? '')); ?>" class="min-w-0 flex-1 rounded border border-gray-300 px-3 py-2">
                                        <button type="button" data-finance-cep-lookup class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded border border-elo-teal text-elo-teal transition-colors hover:bg-elo-teal/10 disabled:cursor-not-allowed disabled:opacity-50" aria-label="Buscar endereço pelo CEP" title="Buscar endereço pelo CEP">
                                            <i class="ph ph-magnifying-glass text-lg" aria-hidden="true"></i>
                                        </button>
                                    </span>
                                </label>
                                <p data-finance-cep-message role="status" aria-live="polite" class="mt-1 text-xs text-gray-500"></p>
                            </div>
                            <label class="text-sm font-medium text-gray-700 md:col-span-3">Logradouro
                                <input name="logradouro" data-finance-address="logradouro" maxlength="150" value="<?php echo appEscape((string) ($doadorEdicao['logradouro'] ?? '')); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                            </label>
                            <label class="text-sm font-medium text-gray-700">Número
                                <input name="numero" maxlength="20" value="<?php echo appEscape((string) ($doadorEdicao['numero'] ?? '')); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                            </label>
                            <label class="text-sm font-medium text-gray-700 md:col-span-3">Complemento
                                <input name="complemento" maxlength="100" value="<?php echo appEscape((string) ($doadorEdicao['complemento'] ?? '')); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                            </label>
                            <label class="text-sm font-medium text-gray-700 md:col-span-2">Bairro
                                <input name="bairro" data-finance-address="bairro" maxlength="100" value="<?php echo appEscape((string) ($doadorEdicao['bairro'] ?? '')); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                            </label>
                            <label class="text-sm font-medium text-gray-700">Cidade
                                <input name="cidade" data-finance-address="cidade" maxlength="100" value="<?php echo appEscape((string) ($doadorEdicao['cidade'] ?? '')); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                            </label>
                            <label class="text-sm font-medium text-gray-700">UF
                                <input name="uf" data-finance-address="uf" maxlength="2" value="<?php echo appEscape((string) ($doadorEdicao['uf'] ?? '')); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                            </label>
                        </div>
                    </fieldset>

                    <fieldset class="grid gap-4 rounded-lg border border-gray-200 p-4 md:col-span-2 md:grid-cols-2">
                        <legend class="px-2 text-sm font-semibold text-gray-700">4. Observações e controle</legend>
                        <label class="text-sm font-medium text-gray-700 md:col-span-2">Observações
                            <textarea name="observacoes" maxlength="255" rows="3" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"><?php echo appEscape((string) ($doadorEdicao['observacoes'] ?? '')); ?></textarea>
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="statusDoador" value="1" <?php echo !isset($doadorEdicao['statusDoador']) || (int) $doadorEdicao['statusDoador'] === 1 ? 'checked' : ''; ?> class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
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
                        <button type="submit" class="inline-flex items-center gap-2 rounded bg-elo-teal px-4 py-2 font-medium text-white hover:bg-elo-green"><i class="ph ph-floppy-disk" aria-hidden="true"></i><?php echo !empty($doadorEdicao['idDoadorContribuinte']) ? 'Salvar alterações' : 'Cadastrar'; ?></button>
                        <?php if (!empty($doadorEdicao['idDoadorContribuinte'])): ?><a href="doadores-contribuintes.php#pesquisa" class="inline-flex items-center gap-2 rounded border px-4 py-2 text-gray-700 hover:bg-gray-50"><i class="ph ph-x-circle" aria-hidden="true"></i>Cancelar</a><?php endif; ?>
                    </div>
                </form>
            </section>

            <section id="painel-pesquisa" role="tabpanel" aria-labelledby="tab-pesquisa" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'pesquisa' ? '' : 'hidden'; ?>>
                <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <h2 class="text-lg font-semibold">Doadores e Contribuintes cadastrados</h2>
                    <div class="flex flex-wrap gap-2">
                        <input data-filter-input="listaDoadores" type="search" placeholder="Pesquisar..." aria-label="Pesquisar doadores e contribuintes" class="rounded border border-gray-300 px-3 py-2 text-sm">
                        <button type="button" data-tab-open="manutencao" class="rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">+ Novo cadastro</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1000px] text-left text-sm">
                        <thead class="border-b bg-gray-50 text-gray-600"><tr><th class="p-3">Nome completo / Razão Social</th><th class="p-3">CPF/CNPJ</th><th class="p-3">E-mail</th><th class="p-3">Telefone</th><th class="p-3">Cidade/UF</th><th class="p-3">Status</th><th class="p-3">Ações</th></tr></thead>
                        <tbody id="listaDoadores">
                        <?php foreach ($doadores as $doador): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-3 font-medium text-gray-800"><?php echo appEscape($doador['nomeRazaoSocial']); ?></td>
                                <td class="p-3"><?php echo appEscape($formatarDocumento((string) $doador['documento'])); ?></td>
                                <td class="p-3"><?php echo appEscape((string) ($doador['email'] ?? '')); ?></td>
                                <td class="p-3"><?php echo appEscape($formatarTelefone($doador['telefone'] ?? null)); ?></td>
                                <td class="p-3"><?php echo appEscape(trim((string) ($doador['cidade'] ?? '') . '/' . (string) ($doador['uf'] ?? ''), '/')); ?></td>
                                <td class="p-3"><span class="<?php echo (int) $doador['statusDoador'] === 1 ? 'text-green-700' : 'text-gray-500'; ?>"><?php echo (int) $doador['statusDoador'] === 1 ? 'Ativo' : 'Inativo'; ?></span></td>
                                <td class="whitespace-nowrap p-3"><div class="flex items-center gap-3">
                                    <a href="?editar=<?php echo (int) $doador['idDoadorContribuinte']; ?>#manutencao" aria-label="Alterar cadastro <?php echo appEscape($doador['nomeRazaoSocial']); ?>" class="inline-flex h-9 w-9 items-center justify-center rounded border border-elo-teal/20 text-elo-teal hover:bg-elo-teal/10"><i class="ph ph-pencil-simple text-lg" aria-hidden="true"></i></a>
                                    <form method="post" data-confirm="Confirma excluir este doador/contribuinte?"><input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="idDoadorContribuinte" value="<?php echo (int) $doador['idDoadorContribuinte']; ?>"><button type="submit" aria-label="Excluir cadastro <?php echo appEscape($doador['nomeRazaoSocial']); ?>" class="inline-flex h-9 w-9 items-center justify-center rounded border border-red-200 text-red-600 hover:bg-red-50"><i class="ph ph-trash text-lg" aria-hidden="true"></i></button></form>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!$doadores): ?><p class="p-6 text-center text-sm text-gray-500">Nenhum doador/contribuinte cadastrado.</p><?php endif; ?>
                </div>
            </section>
        </main>
    </div>
    <?php $toastSuccess = $resultado; $toastError = $erro; include __DIR__ . '/../../../components/toast.php'; ?>
    <script src="<?php echo appEscape(appBaseUrl() . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
</body>
</html>
