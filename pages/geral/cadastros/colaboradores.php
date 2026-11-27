<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('geral.cadastros/colaboradores');
$pdo = appPdo();
$baseUrl = appBaseUrl();
$urlAtual = $baseUrl . '/pages/geral/cadastros/colaboradores.php';

$tiposVinculo = ['Colaborador', 'Voluntário', 'Estagiário', 'Terceirizado'];
$turnos = ['Manhã', 'Tarde', 'Noite', 'Integral', 'Plantão 12x36', 'Flexível'];

$textoPost = static function (string $campo): string {
    $valor = $_POST[$campo] ?? '';
    return is_scalar($valor) ? trim((string) $valor) : '';
};

$validarData = static function (string $data): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
    return $date !== false && $date->format('Y-m-d') === $data;
};

$validarCpf = static function (string $cpf): bool {
    if (!preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    $digits = array_map('intval', str_split($cpf));
    foreach ([9, 10] as $position) {
        $sum = 0;
        for ($index = 0; $index < $position; $index++) {
            $sum += $digits[$index] * ($position + 1 - $index);
        }
        $remainder = ($sum * 10) % 11;
        if ($digits[$position] !== ($remainder === 10 ? 0 : $remainder)) {
            return false;
        }
    }
    return true;
};

$formatarCpf = static function (?string $cpf): string {
    $digits = preg_replace('/\D+/', '', (string) $cpf) ?? '';
    return strlen($digits) === 11
        ? substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9)
        : (string) $cpf;
};

$formatarTelefone = static function (?string $telefone): string {
    $digits = preg_replace('/\D+/', '', (string) $telefone) ?? '';
    if (strlen($digits) === 11) {
        return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 5) . '-' . substr($digits, 7);
    }
    if (strlen($digits) === 10) {
        return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 4) . '-' . substr($digits, 6);
    }
    return (string) $telefone;
};

$formatarCep = static function (?string $cep): string {
    $digits = preg_replace('/\D+/', '', (string) $cep) ?? '';
    return strlen($digits) === 8 ? substr($digits, 0, 5) . '-' . substr($digits, 5) : (string) $cep;
};

$formatarAuditoria = static function ($idUsuario, $nomeUsuario, $dataHora): string {
    if ($idUsuario === null || $nomeUsuario === null || $dataHora === null) {
        return '—';
    }
    $timestamp = strtotime((string) $dataHora);
    return $timestamp === false
        ? '—'
        : (int) $idUsuario . ' - ' . (string) $nomeUsuario . ' - ' . date('d/m/Y H:i', $timestamp);
};

$erro = '';
$formSubmitted = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    $acao = $textoPost('acao');

    if ($acao === 'consultarCep') {
        header('Content-Type: application/json; charset=UTF-8');
        $cep = preg_replace('/\D+/', '', $textoPost('cep')) ?? '';
        if (!preg_match('/^\d{8}$/', $cep) || $cep === '00000000') {
            http_response_code(422);
            echo json_encode(['message' => 'Informe um CEP válido com 8 dígitos.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $curl = curl_init('https://viacep.com.br/ws/' . $cep . '/json/');
        if ($curl === false) {
            http_response_code(502);
            echo json_encode(['message' => 'Não foi possível consultar o CEP agora.'], JSON_UNESCAPED_UNICODE);
            exit;
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
        curl_close($curl);

        if ($response === false || $status !== 200) {
            http_response_code(502);
            echo json_encode(['message' => 'O serviço de CEP está indisponível. Tente novamente.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $result = json_decode($response, true);
        if (!is_array($result) || ($result['erro'] ?? false) === true) {
            http_response_code(422);
            echo json_encode(['message' => 'CEP não encontrado. Confira o número e tente novamente.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode([
            'logradouro' => trim((string) ($result['logradouro'] ?? '')),
            'bairro' => trim((string) ($result['bairro'] ?? '')),
            'cidade' => trim((string) ($result['localidade'] ?? '')),
            'uf' => strtoupper(trim((string) ($result['uf'] ?? ''))),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($acao === 'buscarUsuarios') {
        header('Content-Type: application/json; charset=UTF-8');
        $busca = $textoPost('busca');
        $idEdicaoAtual = filter_var($_POST['idColaborador'] ?? 0, FILTER_VALIDATE_INT);
        $idEdicaoAtual = ($idEdicaoAtual !== false && $idEdicaoAtual > 0) ? (int) $idEdicaoAtual : 0;

        if (mb_strlen($busca, 'UTF-8') > 100) {
            http_response_code(400);
            echo json_encode(['message' => 'A pesquisa deve ter até 100 caracteres.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $filtroDisponivel = 'NOT EXISTS (SELECT 1 FROM tbColaborador c WHERE c.idUsuario = u.idUsuario AND c.idColaborador <> ?)';
            $buscarPorId = ctype_digit($busca);
            $termo = '%' . $busca . '%';

            if ($busca === '') {
                $stmt = $pdo->prepare(
                    "SELECT u.idUsuario, u.nomeCompleto, u.login, u.ativo, u.bloqueado
                     FROM tbUsuario u
                     WHERE $filtroDisponivel
                     ORDER BY u.nomeCompleto
                     LIMIT 50"
                );
                $stmt->execute([$idEdicaoAtual]);
            } elseif ($buscarPorId) {
                $stmt = $pdo->prepare(
                    "SELECT u.idUsuario, u.nomeCompleto, u.login, u.ativo, u.bloqueado
                     FROM tbUsuario u
                     WHERE $filtroDisponivel AND (u.idUsuario LIKE ? OR u.nomeCompleto LIKE ? OR u.login LIKE ?)
                     ORDER BY u.idUsuario LIKE ? DESC, u.nomeCompleto
                     LIMIT 50"
                );
                $stmt->execute([$idEdicaoAtual, $busca . '%', $termo, $termo, $busca . '%']);
            } else {
                $stmt = $pdo->prepare(
                    "SELECT u.idUsuario, u.nomeCompleto, u.login, u.ativo, u.bloqueado
                     FROM tbUsuario u
                     WHERE $filtroDisponivel AND (u.nomeCompleto LIKE ? OR u.login LIKE ?)
                     ORDER BY u.nomeCompleto
                     LIMIT 50"
                );
                $stmt->execute([$idEdicaoAtual, $termo, $termo]);
            }

            $items = array_map(static function (array $row): array {
                $sufixo = (int) $row['bloqueado'] === 1
                    ? ' (Bloqueado)'
                    : ((int) $row['ativo'] !== 1 ? ' (Inativo)' : '');
                return [
                    'id' => (int) $row['idUsuario'],
                    'label' => (int) $row['idUsuario'] . ' — ' . $row['nomeCompleto'] . ' (' . $row['login'] . ')' . $sufixo,
                ];
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));

            echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (PDOException $exception) {
            error_log('Falha ao buscar usuários para colaborador: ' . $exception->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Não foi possível pesquisar os usuários agora.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    try {
        if ($acao === 'salvar') {
            $idColaborador = filter_var($_POST['idColaborador'] ?? 0, FILTER_VALIDATE_INT);
            $idColaborador = ($idColaborador !== false && $idColaborador > 0) ? (int) $idColaborador : 0;

            $nomeCompleto = $textoPost('nomeCompleto');
            $cpf = preg_replace('/\D+/', '', $textoPost('cpf')) ?? '';
            $rg = $textoPost('rg');
            $dataNascimento = $textoPost('dataNascimento');
            $tipoVinculo = $textoPost('tipoVinculo');
            $cargoFuncao = $textoPost('cargoFuncao');
            $registroProfissional = $textoPost('registroProfissional');
            $turno = $textoPost('turno');
            $dataInicio = $textoPost('dataInicio');
            $dataDesligamento = $textoPost('dataDesligamento');
            $email = $textoPost('email');
            $telefone = preg_replace('/\D+/', '', $textoPost('telefone')) ?? '';
            $cep = preg_replace('/\D+/', '', $textoPost('cep')) ?? '';
            $logradouro = $textoPost('logradouro');
            $numero = $textoPost('numero');
            $complemento = $textoPost('complemento');
            $bairro = $textoPost('bairro');
            $cidade = $textoPost('cidade');
            $uf = strtoupper($textoPost('uf'));
            $idUsuarioInput = $textoPost('idUsuario');
            $idUsuarioVinculado = $idUsuarioInput !== '' ? filter_var($idUsuarioInput, FILTER_VALIDATE_INT) : null;
            $observacoes = $textoPost('observacoes');
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            $formSubmitted = [
                'idColaborador' => $idColaborador,
                'nomeCompleto' => $nomeCompleto,
                'cpf' => $cpf,
                'rg' => $rg,
                'dataNascimento' => $dataNascimento,
                'tipoVinculo' => $tipoVinculo,
                'cargoFuncao' => $cargoFuncao,
                'registroProfissional' => $registroProfissional,
                'turno' => $turno,
                'dataInicio' => $dataInicio,
                'dataDesligamento' => $dataDesligamento,
                'email' => $email,
                'telefone' => $telefone,
                'cep' => $cep,
                'logradouro' => $logradouro,
                'numero' => $numero,
                'complemento' => $complemento,
                'bairro' => $bairro,
                'cidade' => $cidade,
                'uf' => $uf,
                'idUsuario' => $idUsuarioVinculado !== false && $idUsuarioVinculado !== null ? (int) $idUsuarioVinculado : null,
                'observacoes' => $observacoes,
                'ativo' => $ativo,
            ];

            if ($nomeCompleto === '' || mb_strlen($nomeCompleto, 'UTF-8') > 150) {
                throw new InvalidArgumentException('Informe o nome completo (até 150 caracteres).');
            }
            if (!$validarCpf($cpf)) {
                throw new InvalidArgumentException('Informe um CPF válido com 11 dígitos.');
            }
            if (mb_strlen($rg, 'UTF-8') > 30) {
                throw new InvalidArgumentException('O RG deve ter até 30 caracteres.');
            }
            if ($dataNascimento !== '' && !$validarData($dataNascimento)) {
                throw new InvalidArgumentException('Informe uma data de nascimento válida.');
            }
            if (!in_array($tipoVinculo, $tiposVinculo, true)) {
                throw new InvalidArgumentException('Selecione um tipo de vínculo válido.');
            }
            if ($cargoFuncao === '' || mb_strlen($cargoFuncao, 'UTF-8') > 100) {
                throw new InvalidArgumentException('Informe o cargo ou função (até 100 caracteres).');
            }
            if (mb_strlen($registroProfissional, 'UTF-8') > 50) {
                throw new InvalidArgumentException('O registro profissional deve ter até 50 caracteres.');
            }
            if (!in_array($turno, $turnos, true)) {
                throw new InvalidArgumentException('Selecione um turno válido.');
            }
            if ($dataInicio === '' || !$validarData($dataInicio)) {
                throw new InvalidArgumentException('Informe uma data de início válida.');
            }
            if ($dataDesligamento !== '') {
                if (!$validarData($dataDesligamento)) {
                    throw new InvalidArgumentException('Informe uma data de desligamento válida.');
                }
                if ($dataDesligamento < $dataInicio) {
                    throw new InvalidArgumentException('A data de desligamento não pode ser anterior à data de início.');
                }
            }
            if ($email !== '' && (mb_strlen($email, 'UTF-8') > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
                throw new InvalidArgumentException('Informe um e-mail válido.');
            }
            if ($telefone !== '' && !preg_match('/^\d{10,11}$/', $telefone)) {
                throw new InvalidArgumentException('Informe um telefone válido com DDD (10 ou 11 dígitos).');
            }
            if ($cep !== '' && (!preg_match('/^\d{8}$/', $cep) || $cep === '00000000')) {
                throw new InvalidArgumentException('Informe um CEP válido com 8 dígitos.');
            }
            if ($uf !== '' && !preg_match('/^[A-Z]{2}$/', $uf)) {
                throw new InvalidArgumentException('Informe uma UF válida com duas letras.');
            }
            if (mb_strlen($logradouro, 'UTF-8') > 150 || mb_strlen($numero, 'UTF-8') > 20
                || mb_strlen($complemento, 'UTF-8') > 100 || mb_strlen($bairro, 'UTF-8') > 100
                || mb_strlen($cidade, 'UTF-8') > 100 || mb_strlen($observacoes, 'UTF-8') > 500) {
                throw new InvalidArgumentException('Um dos campos de endereço ou observações excede o limite permitido.');
            }
            if ($idUsuarioVinculado === false || ($idUsuarioVinculado !== null && $idUsuarioVinculado < 1)) {
                throw new InvalidArgumentException('O usuário selecionado para vínculo é inválido.');
            }

            if ($idUsuarioVinculado !== null) {
                $userExists = $pdo->prepare('SELECT idUsuario FROM tbUsuario WHERE idUsuario = :id');
                $userExists->execute(['id' => $idUsuarioVinculado]);
                if ($userExists->fetchColumn() === false) {
                    throw new InvalidArgumentException('O usuário selecionado não foi encontrado.');
                }
            }

            $params = [
                'nomeCompleto' => $nomeCompleto,
                'cpf' => $cpf,
                'rg' => $rg !== '' ? $rg : null,
                'dataNascimento' => $dataNascimento !== '' ? $dataNascimento : null,
                'tipoVinculo' => $tipoVinculo,
                'cargoFuncao' => $cargoFuncao,
                'registroProfissional' => $registroProfissional !== '' ? $registroProfissional : null,
                'turno' => $turno,
                'dataInicio' => $dataInicio,
                'dataDesligamento' => $dataDesligamento !== '' ? $dataDesligamento : null,
                'email' => $email !== '' ? $email : null,
                'telefone' => $telefone !== '' ? $telefone : null,
                'cep' => $cep !== '' ? $cep : null,
                'logradouro' => $logradouro !== '' ? $logradouro : null,
                'numero' => $numero !== '' ? $numero : null,
                'complemento' => $complemento !== '' ? $complemento : null,
                'bairro' => $bairro !== '' ? $bairro : null,
                'cidade' => $cidade !== '' ? $cidade : null,
                'uf' => $uf !== '' ? $uf : null,
                'idUsuario' => $idUsuarioVinculado,
                'observacoes' => $observacoes !== '' ? $observacoes : null,
                'ativo' => $ativo,
            ];

            if ($idColaborador > 0) {
                $exists = $pdo->prepare('SELECT idColaborador FROM tbColaborador WHERE idColaborador = :id');
                $exists->execute(['id' => $idColaborador]);
                if ($exists->fetchColumn() === false) {
                    throw new InvalidArgumentException('Colaborador não encontrado.');
                }

                $statement = $pdo->prepare(
                    'UPDATE tbColaborador
                     SET nomeCompleto = :nomeCompleto, cpf = :cpf, rg = :rg, dataNascimento = :dataNascimento,
                         tipoVinculo = :tipoVinculo, cargoFuncao = :cargoFuncao,
                         registroProfissional = :registroProfissional, turno = :turno,
                         dataInicio = :dataInicio, dataDesligamento = :dataDesligamento,
                         email = :email, telefone = :telefone, cep = :cep, logradouro = :logradouro,
                         numero = :numero, complemento = :complemento, bairro = :bairro,
                         cidade = :cidade, uf = :uf, idUsuario = :idUsuario,
                         observacoes = :observacoes, ativo = :ativo,
                         idUsuarioAlteracao = :idUsuarioAlteracao
                     WHERE idColaborador = :idColaborador'
                );
                $params['idUsuarioAlteracao'] = (int) $usuarioAtual['idUsuario'];
                $params['idColaborador'] = $idColaborador;
                $statement->execute($params);

                appRegistrarAuditoria(
                    'geral',
                    'editar',
                    'Cadastro atualizado (' . $tipoVinculo . '): ' . $nomeCompleto . '.',
                    'tbColaborador',
                    $idColaborador,
                    ['tipoVinculo' => $tipoVinculo, 'cargoFuncao' => $cargoFuncao, 'turno' => $turno, 'ativo' => (bool) $ativo],
                    $usuarioAtual
                );
                header('Location: ' . appTabUrl('colaboradores.php?resultado=atualizado'));
                exit;
            }

            $statement = $pdo->prepare(
                'INSERT INTO tbColaborador
                    (nomeCompleto, cpf, rg, dataNascimento, tipoVinculo, cargoFuncao,
                     registroProfissional, turno, dataInicio, dataDesligamento,
                     email, telefone, cep, logradouro, numero, complemento, bairro,
                     cidade, uf, idUsuario, observacoes, ativo, idUsuarioCriacao)
                 VALUES
                    (:nomeCompleto, :cpf, :rg, :dataNascimento, :tipoVinculo, :cargoFuncao,
                     :registroProfissional, :turno, :dataInicio, :dataDesligamento,
                     :email, :telefone, :cep, :logradouro, :numero, :complemento, :bairro,
                     :cidade, :uf, :idUsuario, :observacoes, :ativo, :idUsuarioCriacao)'
            );
            $params['idUsuarioCriacao'] = (int) $usuarioAtual['idUsuario'];
            $statement->execute($params);
            $novoId = (int) $pdo->lastInsertId();

            appRegistrarAuditoria(
                'geral',
                'criar',
                'Cadastro criado (' . $tipoVinculo . '): ' . $nomeCompleto . '.',
                'tbColaborador',
                $novoId,
                ['tipoVinculo' => $tipoVinculo, 'cargoFuncao' => $cargoFuncao, 'turno' => $turno, 'ativo' => (bool) $ativo],
                $usuarioAtual
            );
            header('Location: ' . appTabUrl('colaboradores.php?resultado=criado'));
            exit;
        }

        if ($acao === 'excluir') {
            $idColaborador = filter_var($_POST['idColaborador'] ?? 0, FILTER_VALIDATE_INT);
            if (!$idColaborador || $idColaborador < 1) {
                throw new InvalidArgumentException('Cadastro inválido.');
            }
            $check = $pdo->prepare('SELECT nomeCompleto, tipoVinculo FROM tbColaborador WHERE idColaborador = :id');
            $check->execute(['id' => $idColaborador]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new InvalidArgumentException('Cadastro não encontrado.');
            }
            $delete = $pdo->prepare('DELETE FROM tbColaborador WHERE idColaborador = :id');
            $delete->execute(['id' => $idColaborador]);

            appRegistrarAuditoria(
                'geral',
                'excluir',
                'Cadastro excluído (' . $row['tipoVinculo'] . '): ' . $row['nomeCompleto'] . '.',
                'tbColaborador',
                $idColaborador,
                ['nomeCompleto' => $row['nomeCompleto'], 'tipoVinculo' => $row['tipoVinculo']],
                $usuarioAtual
            );
            header('Location: ' . appTabUrl('colaboradores.php?resultado=excluido'));
            exit;
        }

        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException $exception) {
        $erro = $exception->getMessage();
    } catch (PDOException $exception) {
        error_log('Falha ao salvar colaborador: ' . $exception->getMessage());
        $erro = 'Não foi possível salvar o cadastro. Verifique se o CPF ou o usuário vinculado já estão em uso.';
    }
}

$colaboradorEdicao = null;
$idEdicao = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
if ($formSubmitted !== null && (int) $formSubmitted['idColaborador'] > 0) {
    $idEdicao = (int) $formSubmitted['idColaborador'];
}
if ($idEdicao) {
    $stmt = $pdo->prepare(
        'SELECT c.*,
                uc.nomeCompleto AS nomeUsuarioCriacao,
                ua.nomeCompleto AS nomeUsuarioAlteracao
         FROM tbColaborador c
         INNER JOIN tbUsuario uc ON uc.idUsuario = c.idUsuarioCriacao
         LEFT JOIN tbUsuario ua ON ua.idUsuario = c.idUsuarioAlteracao
         WHERE c.idColaborador = :id'
    );
    $stmt->execute(['id' => $idEdicao]);
    $colaboradorEdicao = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
if ($formSubmitted !== null) {
    $colaboradorEdicao = array_merge($colaboradorEdicao ?? [], $formSubmitted);
}

$usuarioVinculadoLabel = '';
$idUsuarioAtualForm = (int) ($colaboradorEdicao['idUsuario'] ?? 0);
if ($idUsuarioAtualForm > 0) {
    $uStmt = $pdo->prepare('SELECT idUsuario, nomeCompleto, login, ativo, bloqueado FROM tbUsuario WHERE idUsuario = :id');
    $uStmt->execute(['id' => $idUsuarioAtualForm]);
    if ($uRow = $uStmt->fetch(PDO::FETCH_ASSOC)) {
        $sufixo = (int) $uRow['bloqueado'] === 1 ? ' (Bloqueado)' : ((int) $uRow['ativo'] !== 1 ? ' (Inativo)' : '');
        $usuarioVinculadoLabel = (int) $uRow['idUsuario'] . ' — ' . $uRow['nomeCompleto'] . ' (' . $uRow['login'] . ')' . $sufixo;
    }
}

$colaboradores = $pdo->query(
    'SELECT c.idColaborador, c.nomeCompleto, c.cpf, c.tipoVinculo, c.cargoFuncao,
            c.registroProfissional, c.turno, c.telefone, c.email, c.ativo,
            c.idUsuarioCriacao, c.criadoEm, c.idUsuarioAlteracao, c.atualizadoEm,
            uv.login AS loginVinculado,
            uc.nomeCompleto AS nomeUsuarioCriacao,
            ua.nomeCompleto AS nomeUsuarioAlteracao
     FROM tbColaborador c
     LEFT JOIN tbUsuario uv ON uv.idUsuario = c.idUsuario
     INNER JOIN tbUsuario uc ON uc.idUsuario = c.idUsuarioCriacao
     LEFT JOIN tbUsuario ua ON ua.idUsuario = c.idUsuarioAlteracao
     ORDER BY c.ativo DESC, c.nomeCompleto'
)->fetchAll(PDO::FETCH_ASSOC);

$criadoPor = $colaboradorEdicao
    ? $formatarAuditoria($colaboradorEdicao['idUsuarioCriacao'] ?? null, $colaboradorEdicao['nomeUsuarioCriacao'] ?? null, $colaboradorEdicao['criadoEm'] ?? null)
    : '—';
$alteradoPor = $colaboradorEdicao
    ? $formatarAuditoria($colaboradorEdicao['idUsuarioAlteracao'] ?? null, $colaboradorEdicao['nomeUsuarioAlteracao'] ?? null, $colaboradorEdicao['atualizadoEm'] ?? null)
    : '—';

$resultado = [
    'criado' => 'Cadastro salvo com sucesso.',
    'atualizado' => 'Cadastro atualizado com sucesso.',
    'excluido' => 'Cadastro excluído com sucesso.',
][(string) ($_GET['resultado'] ?? '')] ?? '';
$abaInicial = ($colaboradorEdicao || $erro !== '' || ($_GET['aba'] ?? '') === 'manutencao') ? 'manutencao' : 'pesquisa';
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/tailwind-config.js'); ?>"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="flex h-screen flex-col overflow-hidden bg-gray-100 font-sans">
    <?php include __DIR__ . '/../../../components/geral/navbar-geral.php'; ?>
    <div class="flex min-h-0 flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../../components/geral/sidebar-geral.php'; ?>
        <main class="min-w-0 flex-1 overflow-y-auto bg-gray-50 p-4 md:p-6">
            <h1 class="mb-6 text-2xl font-bold text-gray-800">Colaboradores e Voluntários</h1>
            <?php include __DIR__ . '/../../../components/tabs-pesquisa-manutencao.php'; ?>

            <section id="painel-manutencao" role="tabpanel" aria-labelledby="tab-manutencao"
                     class="mb-8 rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'manutencao' ? '' : 'hidden'; ?>>
                <h2 class="mb-4 text-lg font-semibold"><?php echo !empty($colaboradorEdicao['idColaborador']) ? 'Alterar colaborador / voluntário' : 'Novo colaborador / voluntário'; ?></h2>
                <form method="post" class="space-y-5" data-confirm="Confirma gravar este cadastro?">
                    <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="idColaborador" id="idColaboradorForm" value="<?php echo (int) ($colaboradorEdicao['idColaborador'] ?? 0); ?>">

                    <fieldset class="grid gap-4 rounded-lg border border-gray-200 p-4 md:grid-cols-3">
                        <legend class="px-2 text-sm font-semibold text-gray-700">1. Identificação e vínculo</legend>
                        <label class="text-sm font-medium text-gray-700 md:col-span-2">Nome completo *
                            <input name="nomeCompleto" required maxlength="150"
                                   value="<?php echo appEscape((string) ($colaboradorEdicao['nomeCompleto'] ?? '')); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700">CPF *
                            <input name="cpf" id="cpfColaborador" required maxlength="14" inputmode="numeric" placeholder="000.000.000-00"
                                   value="<?php echo appEscape($formatarCpf((string) ($colaboradorEdicao['cpf'] ?? ''))); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700">RG (opcional)
                            <input name="rg" maxlength="30"
                                   value="<?php echo appEscape((string) ($colaboradorEdicao['rg'] ?? '')); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Data de nascimento
                            <input type="date" name="dataNascimento"
                                   value="<?php echo appEscape((string) ($colaboradorEdicao['dataNascimento'] ?? '')); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Tipo de vínculo *
                            <select name="tipoVinculo" required class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                                <?php $tipoAtual = (string) ($colaboradorEdicao['tipoVinculo'] ?? 'Colaborador'); ?>
                                <?php foreach ($tiposVinculo as $tipo): ?>
                                    <option value="<?php echo appEscape($tipo); ?>" <?php echo $tipoAtual === $tipo ? 'selected' : ''; ?>>
                                        <?php echo appEscape($tipo); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="text-sm font-medium text-gray-700">Cargo / Função *
                            <input name="cargoFuncao" required maxlength="100" placeholder="Ex.: Educador Social, Voluntário..."
                                   value="<?php echo appEscape((string) ($colaboradorEdicao['cargoFuncao'] ?? '')); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Registro profissional (CRP/CRESS/COREN)
                            <input name="registroProfissional" maxlength="50"
                                   value="<?php echo appEscape((string) ($colaboradorEdicao['registroProfissional'] ?? '')); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Turno *
                            <select name="turno" required class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                                <?php $turnoAtual = (string) ($colaboradorEdicao['turno'] ?? 'Integral'); ?>
                                <?php foreach ($turnos as $t): ?>
                                    <option value="<?php echo appEscape($t); ?>" <?php echo $turnoAtual === $t ? 'selected' : ''; ?>>
                                        <?php echo appEscape($t); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="text-sm font-medium text-gray-700">Data de início *
                            <input type="date" name="dataInicio" required
                                   value="<?php echo appEscape((string) ($colaboradorEdicao['dataInicio'] ?? date('Y-m-d'))); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Data de desligamento
                            <input type="date" name="dataDesligamento"
                                   value="<?php echo appEscape((string) ($colaboradorEdicao['dataDesligamento'] ?? '')); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <div class="relative" data-user-link-search data-csrf="<?php echo appEscape(appCsrfToken()); ?>">
                            <label for="usuarioVinculadoPesquisa" class="block text-sm font-medium text-gray-700">Usuário do sistema (opcional)</label>
                            <span class="mt-1 flex">
                                <input id="usuarioVinculadoPesquisa" type="search" data-search-input autocomplete="off"
                                       role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="sugestoes-usuario-colab"
                                       value="<?php echo appEscape($usuarioVinculadoLabel); ?>"
                                       placeholder="ID, nome ou login..."
                                       class="min-w-0 flex-1 rounded-l border border-r-0 border-gray-300 px-3 py-2 text-sm">
                                <button type="button" data-search-toggle aria-label="Mostrar usuários disponíveis"
                                        class="inline-flex h-[42px] w-10 shrink-0 items-center justify-center rounded-r border border-gray-300 bg-gray-50 text-gray-600 hover:bg-gray-100">
                                    <i class="ph ph-caret-down" aria-hidden="true"></i>
                                </button>
                            </span>
                            <input type="hidden" name="idUsuario" data-search-id value="<?php echo $idUsuarioAtualForm ?: ''; ?>">
                            <div id="sugestoes-usuario-colab" data-search-results data-preserve-case role="listbox" class="absolute z-20 mt-1 hidden max-h-56 w-full overflow-y-auto rounded border border-gray-200 bg-white shadow-lg"></div>
                            <p data-search-message role="status" aria-live="polite" class="mt-1 text-xs text-gray-500">Vincule caso possua conta de acesso.</p>
                        </div>
                        <label class="flex items-center gap-2 pt-2 text-sm font-medium text-gray-700 md:col-span-3">
                            <input type="checkbox" name="ativo" value="1" <?php echo (int) ($colaboradorEdicao['ativo'] ?? 1) === 1 ? 'checked' : ''; ?>
                                   class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                            Cadastro ativo
                        </label>
                    </fieldset>

                    <fieldset class="grid gap-4 rounded-lg border border-gray-200 p-4 md:grid-cols-3">
                        <legend class="px-2 text-sm font-semibold text-gray-700">2. Contato e endereço</legend>
                        <label class="text-sm font-medium text-gray-700">Telefone
                            <input name="telefone" id="telefoneColaborador" maxlength="15" inputmode="numeric" placeholder="(00) 00000-0000"
                                   value="<?php echo appEscape($formatarTelefone((string) ($colaboradorEdicao['telefone'] ?? ''))); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700 md:col-span-2">E-mail
                            <input type="email" name="email" maxlength="190"
                                   value="<?php echo appEscape((string) ($colaboradorEdicao['email'] ?? '')); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <div>
                            <label for="cepColaborador" class="block text-sm font-medium text-gray-700">CEP</label>
                            <div class="mt-1 flex gap-2">
                                <input id="cepColaborador" name="cep" maxlength="9" inputmode="numeric" placeholder="00000-000"
                                       value="<?php echo appEscape($formatarCep((string) ($colaboradorEdicao['cep'] ?? ''))); ?>"
                                       class="min-w-0 flex-1 rounded border border-gray-300 px-3 py-2">
                                <button type="button" id="btnBuscarCepColaborador"
                                        class="rounded border border-elo-teal px-3 py-2 text-xs font-medium text-elo-teal hover:bg-elo-teal/5">
                                    Buscar
                                </button>
                            </div>
                            <p id="msgCepColaborador" role="status" aria-live="polite" class="mt-1 text-xs text-gray-500"></p>
                        </div>
                        <label class="text-sm font-medium text-gray-700 md:col-span-2">Logradouro
                            <input id="logradouroColaborador" name="logradouro" maxlength="150"
                                   value="<?php echo appEscape((string) ($colaboradorEdicao['logradouro'] ?? '')); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Número
                            <input name="numero" maxlength="20"
                                   value="<?php echo appEscape((string) ($colaboradorEdicao['numero'] ?? '')); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Complemento
                            <input name="complemento" maxlength="100"
                                   value="<?php echo appEscape((string) ($colaboradorEdicao['complemento'] ?? '')); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Bairro
                            <input id="bairroColaborador" name="bairro" maxlength="100"
                                   value="<?php echo appEscape((string) ($colaboradorEdicao['bairro'] ?? '')); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700 md:col-span-2">Cidade
                            <input id="cidadeColaborador" name="cidade" maxlength="100"
                                   value="<?php echo appEscape((string) ($colaboradorEdicao['cidade'] ?? '')); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700">UF
                            <input id="ufColaborador" name="uf" maxlength="2" placeholder="SP"
                                   value="<?php echo appEscape((string) ($colaboradorEdicao['uf'] ?? '')); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2 uppercase">
                        </label>
                    </fieldset>

                    <fieldset class="grid gap-4 rounded-lg border border-gray-200 p-4 md:grid-cols-2">
                        <legend class="px-2 text-sm font-semibold text-gray-700">3. Observações e controle</legend>
                        <label class="text-sm font-medium text-gray-700 md:col-span-2">Observações / Disponibilidade
                            <textarea name="observacoes" maxlength="500" rows="2" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"><?php echo appEscape((string) ($colaboradorEdicao['observacoes'] ?? '')); ?></textarea>
                        </label>
                        <label class="text-sm font-medium text-gray-700">Criado por
                            <input value="<?php echo appEscape($criadoPor); ?>" disabled class="mt-1 w-full cursor-not-allowed rounded border border-gray-200 bg-gray-100 px-3 py-2 text-gray-600">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Última alteração por
                            <input value="<?php echo appEscape($alteradoPor); ?>" disabled class="mt-1 w-full cursor-not-allowed rounded border border-gray-200 bg-gray-100 px-3 py-2 text-gray-600">
                        </label>
                    </fieldset>

                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" class="rounded bg-elo-teal px-5 py-2.5 text-sm font-medium text-white hover:bg-elo-green">
                            <?php echo !empty($colaboradorEdicao['idColaborador']) ? 'Salvar alterações' : 'Cadastrar'; ?>
                        </button>
                        <?php if (!empty($colaboradorEdicao['idColaborador'])): ?>
                            <a href="<?php echo appEscape(appTabUrl($urlAtual)); ?>" class="rounded border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancelar edição
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section id="painel-pesquisa" role="tabpanel" aria-labelledby="tab-pesquisa"
                     class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'pesquisa' ? '' : 'hidden'; ?>>
                <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <h2 class="text-lg font-semibold">Equipe e voluntários cadastrados</h2>
                    <div class="flex flex-wrap gap-2">
                        <input data-filter-input="listaColaboradores" type="search" placeholder="Pesquisar equipe..." aria-label="Pesquisar colaboradores e voluntários"
                               class="rounded border border-gray-300 px-3 py-2 text-sm">
                        <button type="button" data-tab-open="manutencao" class="rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">
                            + Novo cadastro
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1050px] text-left text-sm">
                        <thead class="border-b bg-gray-50 text-gray-600">
                            <tr>
                                <th class="p-3">Nome / CPF</th>
                                <th class="p-3">Vínculo / Cargo</th>
                                <th class="p-3">Turno</th>
                                <th class="p-3">Contato</th>
                                <th class="p-3">Usuário</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Auditoria</th>
                                <th class="p-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="listaColaboradores">
                            <?php foreach ($colaboradores as $item): ?>
                                <?php
                                $badgeVinculo = match ($item['tipoVinculo']) {
                                    'Voluntário' => 'bg-purple-100 text-purple-800 border-purple-200',
                                    'Estagiário' => 'bg-sky-100 text-sky-800 border-sky-200',
                                    'Terceirizado' => 'bg-amber-100 text-amber-800 border-amber-200',
                                    default => 'bg-teal-100 text-teal-800 border-teal-200',
                                };
                                ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-3">
                                        <div class="font-medium text-gray-800" data-preserve-case><?php echo (int) $item['idColaborador']; ?> — <?php echo appEscape($item['nomeCompleto']); ?></div>
                                        <div class="text-xs text-gray-500" data-preserve-case><?php echo appEscape($formatarCpf($item['cpf'])); ?></div>
                                    </td>
                                    <td class="p-3">
                                        <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium <?php echo $badgeVinculo; ?>">
                                            <?php echo appEscape($item['tipoVinculo']); ?>
                                        </span>
                                        <div class="mt-1 text-xs text-gray-700" data-preserve-case>
                                            <?php echo appEscape($item['cargoFuncao']); ?>
                                            <?php if (!empty($item['registroProfissional'])): ?>
                                                · <span class="text-gray-500"><?php echo appEscape($item['registroProfissional']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap p-3 text-gray-700"><?php echo appEscape($item['turno']); ?></td>
                                    <td class="p-3 text-xs text-gray-700" data-preserve-case>
                                        <div><?php echo appEscape($formatarTelefone($item['telefone']) ?: '—'); ?></div>
                                        <?php if (!empty($item['email'])): ?>
                                            <div class="text-gray-500"><?php echo appEscape($item['email']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="whitespace-nowrap p-3 text-xs text-gray-700" data-preserve-case>
                                        <?php echo !empty($item['loginVinculado']) ? appEscape($item['loginVinculado']) : '<span class="text-gray-400">Sem vínculo</span>'; ?>
                                    </td>
                                    <td class="whitespace-nowrap p-3">
                                        <?php if ((int) $item['ativo'] === 1): ?>
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">Ativo</span>
                                        <?php else: ?>
                                            <span class="inline-flex rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-medium text-gray-700">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-xs text-gray-500" data-preserve-case>
                                        <div>Criado: <?php echo appEscape($formatarAuditoria($item['idUsuarioCriacao'], $item['nomeUsuarioCriacao'], $item['criadoEm'])); ?></div>
                                        <?php if ($item['idUsuarioAlteracao'] !== null): ?>
                                            <div>Alt.: <?php echo appEscape($formatarAuditoria($item['idUsuarioAlteracao'], $item['nomeUsuarioAlteracao'], $item['atualizadoEm'])); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="whitespace-nowrap p-3">
                                        <div class="flex items-center gap-2">
                                            <a href="<?php echo appEscape(appTabUrl($urlAtual . '?editar=' . (int) $item['idColaborador'] . '#manutencao')); ?>"
                                               aria-label="Editar <?php echo appEscape($item['nomeCompleto']); ?>"
                                               class="inline-flex h-9 w-9 items-center justify-center rounded border border-elo-teal/20 text-elo-teal hover:bg-elo-teal/10">
                                                <i class="ph ph-pencil-simple text-lg" aria-hidden="true"></i>
                                            </a>
                                            <form method="post" data-confirm="Confirma excluir o cadastro de <?php echo appEscape($item['nomeCompleto']); ?>?">
                                                <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                                                <input type="hidden" name="acao" value="excluir">
                                                <input type="hidden" name="idColaborador" value="<?php echo (int) $item['idColaborador']; ?>">
                                                <button type="submit" aria-label="Excluir <?php echo appEscape($item['nomeCompleto']); ?>"
                                                        class="inline-flex h-9 w-9 items-center justify-center rounded border border-red-200 text-red-600 hover:bg-red-50">
                                                    <i class="ph ph-trash text-lg" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$colaboradores): ?>
                                <tr><td colspan="8" class="px-3 py-10 text-center text-gray-500">Nenhum colaborador ou voluntário cadastrado ainda.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <?php $toastSuccess = $resultado; $toastError = $erro; include __DIR__ . '/../../../components/toast.php'; ?>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
    <script>
        (() => {
            const csrfToken = <?php echo json_encode(appCsrfToken(), JSON_THROW_ON_ERROR); ?>;
            const getTabToken = () => new URLSearchParams(window.location.search).get('tabToken')
                || window.sessionStorage.getItem('sistemaEloTabToken') || '';

            const cpfInput = document.getElementById('cpfColaborador');
            cpfInput?.addEventListener('input', () => {
                const d = cpfInput.value.replace(/\D/g, '').slice(0, 11);
                let out = d;
                if (d.length > 9) out = `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6, 9)}-${d.slice(9)}`;
                else if (d.length > 6) out = `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6)}`;
                else if (d.length > 3) out = `${d.slice(0, 3)}.${d.slice(3)}`;
                cpfInput.value = out;
            });

            const telInput = document.getElementById('telefoneColaborador');
            telInput?.addEventListener('input', () => {
                const d = telInput.value.replace(/\D/g, '').slice(0, 11);
                if (d.length <= 2) { telInput.value = d; return; }
                const split = d.length === 11 ? 5 : 4;
                const sub = d.slice(2);
                telInput.value = `(${d.slice(0, 2)}) ${sub.slice(0, split)}${sub.length > split ? '-' + sub.slice(split) : ''}`;
            });

            const cepInput = document.getElementById('cepColaborador');
            const cepBtn = document.getElementById('btnBuscarCepColaborador');
            const cepMsg = document.getElementById('msgCepColaborador');
            const buscarCep = async () => {
                if (!cepInput) return;
                const digits = cepInput.value.replace(/\D/g, '');
                if (!/^\d{8}$/.test(digits) || digits === '00000000') {
                    if (cepMsg) cepMsg.textContent = 'Informe um CEP com 8 dígitos.';
                    return;
                }
                if (cepMsg) cepMsg.textContent = 'Consultando CEP...';
                const body = new FormData();
                body.set('csrfToken', csrfToken);
                body.set('tabToken', getTabToken());
                body.set('acao', 'consultarCep');
                body.set('cep', digits);
                try {
                    const res = await fetch(window.location.pathname + window.location.search, {
                        method: 'POST', body, headers: {'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin'
                    });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.message || 'Falha ao consultar CEP.');
                    const setVal = (id, val) => { const el = document.getElementById(id); if (el && val) el.value = val; };
                    setVal('logradouroColaborador', data.logradouro);
                    setVal('bairroColaborador', data.bairro);
                    setVal('cidadeColaborador', data.cidade);
                    setVal('ufColaborador', data.uf);
                    if (cepMsg) cepMsg.textContent = 'Endereço preenchido via ViaCEP.';
                } catch (err) {
                    if (cepMsg) cepMsg.textContent = err.message || 'Não foi possível consultar o CEP.';
                }
            };
            cepInput?.addEventListener('input', () => {
                const d = cepInput.value.replace(/\D/g, '').slice(0, 8);
                cepInput.value = d.length > 5 ? `${d.slice(0, 5)}-${d.slice(5)}` : d;
            });
            cepBtn?.addEventListener('click', buscarCep);

            const box = document.querySelector('[data-user-link-search]');
            if (box) {
                const form = box.closest('form');
                const input = box.querySelector('[data-search-input]');
                const hidden = box.querySelector('[data-search-id]');
                const toggle = box.querySelector('[data-search-toggle]');
                const results = box.querySelector('[data-search-results]');
                const message = box.querySelector('[data-search-message]');
                let timer = null, ctrl = null, activeIndex = -1;

                const close = () => {
                    results.classList.add('hidden');
                    input.setAttribute('aria-expanded', 'false');
                    activeIndex = -1;
                };
                const selectItem = (item) => {
                    input.value = item.label;
                    hidden.value = String(item.id);
                    input.setCustomValidity('');
                    message.textContent = 'Usuário vinculado.';
                    close();
                };
                const search = async (browseAll = false) => {
                    ctrl?.abort();
                    ctrl = new AbortController();
                    const body = new FormData();
                    body.set('csrfToken', csrfToken);
                    body.set('tabToken', getTabToken());
                    body.set('acao', 'buscarUsuarios');
                    body.set('busca', browseAll ? '' : input.value.trim());
                    body.set('idColaborador', document.getElementById('idColaboradorForm')?.value || '0');
                    results.classList.remove('hidden');
                    input.setAttribute('aria-expanded', 'true');
                    message.textContent = 'Pesquisando...';
                    try {
                        const res = await fetch(window.location.pathname + window.location.search, {
                            method: 'POST', body, headers: {'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin', signal: ctrl.signal
                        });
                        const data = await res.json();
                        if (!res.ok) throw new Error(data.message || 'Falha na pesquisa.');
                        results.replaceChildren();
                        activeIndex = -1;
                        const items = data.items || [];
                        if (!items.length) {
                            const p = document.createElement('p');
                            p.className = 'px-3 py-2 text-xs text-gray-500';
                            p.textContent = 'Nenhum usuário disponível encontrado.';
                            results.append(p);
                            message.textContent = 'Nenhum usuário disponível.';
                            return;
                        }
                        items.forEach((item, idx) => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.id = `${results.id}-opt-${idx}`;
                            btn.setAttribute('role', 'option');
                            btn.className = 'block w-full px-3 py-2 text-left text-xs text-gray-700 hover:bg-elo-teal/10';
                            btn.textContent = item.label;
                            btn.addEventListener('pointerdown', (e) => { e.preventDefault(); selectItem(item); });
                            results.append(btn);
                        });
                        message.textContent = `${items.length} usuário(s) disponível(is).`;
                    } catch (err) {
                        if (err.name !== 'AbortError') message.textContent = err.message || 'Erro ao pesquisar.';
                    }
                };
                toggle.addEventListener('pointerdown', (e) => e.preventDefault());
                toggle.addEventListener('click', () => {
                    if (input.getAttribute('aria-expanded') === 'true') { close(); return; }
                    search(true);
                    input.focus();
                });
                input.addEventListener('focus', () => { if (input.getAttribute('aria-expanded') !== 'true') search(hidden.value !== '' || input.value.trim() === ''); });
                input.addEventListener('input', () => {
                    window.clearTimeout(timer);
                    hidden.value = '';
                    input.setCustomValidity(input.value.trim() === '' ? '' : 'Selecione um usuário na lista ou apague o texto.');
                    timer = window.setTimeout(() => search(false), 250);
                });
                input.addEventListener('keydown', (e) => {
                    const opts = Array.from(results.querySelectorAll('[role="option"]'));
                    if (e.key === 'Escape') { close(); return; }
                    if (!opts.length) return;
                    if (e.key === 'Enter' && activeIndex >= 0) { e.preventDefault(); opts[activeIndex].dispatchEvent(new Event('pointerdown')); return; }
                    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                        e.preventDefault();
                        activeIndex = e.key === 'ArrowDown' ? (activeIndex + 1) % opts.length : (activeIndex - 1 + opts.length) % opts.length;
                        opts.forEach((o, i) => o.classList.toggle('bg-elo-teal/10', i === activeIndex));
                        opts[activeIndex].scrollIntoView({block: 'nearest'});
                    }
                });
                input.addEventListener('blur', () => window.setTimeout(() => { if (!box.contains(document.activeElement)) close(); }, 100));
                form?.addEventListener('submit', (e) => {
                    if (input.value.trim() !== '' && hidden.value === '') {
                        input.setCustomValidity('Selecione um usuário na lista ou apague o texto.');
                        input.reportValidity();
                        e.preventDefault();
                    }
                });
            }
        })();
    </script>
</body>
</html>
