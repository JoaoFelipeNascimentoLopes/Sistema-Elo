<?php
require_once __DIR__ . '/../../../auth/access.php';
require_once __DIR__ . '/../../../auth/colaborador-fotos.php';
$usuarioAtual = appRequirePage('geral.cadastros/colaboradores');
$pdo = appPdo();
$baseUrl = appBaseUrl();
$urlAtual = $baseUrl . '/pages/geral/cadastros/colaboradores.php';
$urlCracha = $baseUrl . '/pages/geral/cadastros/cracha-colaborador.php';
$fotoNovaRelativa = null;

$urlFoto = static function (array $colab) use ($baseUrl): ?string {
    return !empty($colab['caminhoFoto'])
        ? appTabUrl($baseUrl . '/auth/colaborador-foto.php?id=' . (int) $colab['idColaborador'])
        : null;
};

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
            $removerFoto = isset($_POST['removerFoto']) && (string) $_POST['removerFoto'] === '1';
            $uploadFoto = $_FILES['foto'] ?? null;
            $temUploadFoto = is_array($uploadFoto) && ($uploadFoto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

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
            if ($removerFoto && $temUploadFoto) {
                throw new InvalidArgumentException('Não é possível enviar uma nova foto e marcar remoção ao mesmo tempo.');
            }

            if ($idUsuarioVinculado !== null) {
                $userExists = $pdo->prepare('SELECT idUsuario FROM tbUsuario WHERE idUsuario = :id');
                $userExists->execute(['id' => $idUsuarioVinculado]);
                if ($userExists->fetchColumn() === false) {
                    throw new InvalidArgumentException('O usuário selecionado não foi encontrado.');
                }
            }

            $fotoAnterior = null;
            if ($idColaborador > 0) {
                $exists = $pdo->prepare('SELECT caminhoFoto FROM tbColaborador WHERE idColaborador = :id');
                $exists->execute(['id' => $idColaborador]);
                $fotoAnterior = $exists->fetchColumn();
                if ($fotoAnterior === false) {
                    throw new InvalidArgumentException('Colaborador não encontrado.');
                }
            }

            if ($temUploadFoto) {
                $fotoNovaRelativa = appValidarUploadFotoColaborador($uploadFoto);
            }
            $caminhoFoto = $removerFoto
                ? null
                : ($fotoNovaRelativa ?? (is_string($fotoAnterior) ? $fotoAnterior : null));

            $params = [
                'nomeCompleto' => $nomeCompleto,
                'cpf' => $cpf,
                'rg' => $rg !== '' ? $rg : null,
                'dataNascimento' => $dataNascimento !== '' ? $dataNascimento : null,
                'caminhoFoto' => $caminhoFoto,
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
                $statement = $pdo->prepare(
                    'UPDATE tbColaborador
                     SET nomeCompleto = :nomeCompleto, cpf = :cpf, rg = :rg, dataNascimento = :dataNascimento,
                         caminhoFoto = :caminhoFoto,
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
                $fotoSubstituida = ($removerFoto || $fotoNovaRelativa !== null) && is_string($fotoAnterior) && $fotoAnterior !== '';
                $fotoNovaRelativa = null;
                if ($fotoSubstituida) {
                    try {
                        appRemoverFotoColaborador($fotoAnterior);
                    } catch (RuntimeException $photoErr) {
                        error_log($photoErr->getMessage());
                    }
                }

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
                    (nomeCompleto, cpf, rg, dataNascimento, caminhoFoto, tipoVinculo, cargoFuncao,
                     registroProfissional, turno, dataInicio, dataDesligamento,
                     email, telefone, cep, logradouro, numero, complemento, bairro,
                     cidade, uf, idUsuario, observacoes, ativo, idUsuarioCriacao)
                 VALUES
                    (:nomeCompleto, :cpf, :rg, :dataNascimento, :caminhoFoto, :tipoVinculo, :cargoFuncao,
                     :registroProfissional, :turno, :dataInicio, :dataDesligamento,
                     :email, :telefone, :cep, :logradouro, :numero, :complemento, :bairro,
                     :cidade, :uf, :idUsuario, :observacoes, :ativo, :idUsuarioCriacao)'
            );
            $params['idUsuarioCriacao'] = (int) $usuarioAtual['idUsuario'];
            $statement->execute($params);
            $novoId = (int) $pdo->lastInsertId();
            $fotoNovaRelativa = null;

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
            $check = $pdo->prepare('SELECT nomeCompleto, tipoVinculo, caminhoFoto FROM tbColaborador WHERE idColaborador = :id');
            $check->execute(['id' => $idColaborador]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new InvalidArgumentException('Cadastro não encontrado.');
            }
            $delete = $pdo->prepare('DELETE FROM tbColaborador WHERE idColaborador = :id');
            $delete->execute(['id' => $idColaborador]);
            if (!empty($row['caminhoFoto'])) {
                try {
                    appRemoverFotoColaborador((string) $row['caminhoFoto']);
                } catch (RuntimeException $photoErr) {
                    error_log($photoErr->getMessage());
                }
            }

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
    } catch (InvalidArgumentException | RuntimeException $exception) {
        $erro = $exception->getMessage();
    } catch (PDOException $exception) {
        error_log('Falha ao salvar colaborador: ' . $exception->getMessage());
        $erro = ((int) ($exception->errorInfo[1] ?? 0)) === 1451
            ? 'Este colaborador possui registros na Escala de Plantões ou na Agenda Institucional e não pode ser excluído. Inative o cadastro em vez de excluir.'
            : 'Não foi possível salvar o cadastro. Verifique se o CPF ou o usuário vinculado já estão em uso.';
    } finally {
        if ($fotoNovaRelativa !== null) {
            try {
                appRemoverFotoColaborador($fotoNovaRelativa);
            } catch (RuntimeException) {
            }
            $fotoNovaRelativa = null;
        }
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
    'SELECT c.idColaborador, c.nomeCompleto, c.cpf, c.caminhoFoto, c.tipoVinculo, c.cargoFuncao,
            c.registroProfissional, c.turno, c.dataInicio, c.telefone, c.email, c.ativo,
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

$instituicaoRow = $pdo->query(
    'SELECT razaoSocial, nomeFantasia, cnpj, telefone, logradouro, numero, bairro, cidade, uf
     FROM tbInformacoesInstituicao
     WHERE idInformacoes = 1
     LIMIT 1'
)->fetch(PDO::FETCH_ASSOC) ?: [];
$formatarCnpj = static function (?string $cnpj): string {
    $d = preg_replace('/\D+/', '', (string) $cnpj) ?? '';
    return strlen($d) === 14
        ? substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3) . '/' . substr($d, 8, 4) . '-' . substr($d, 12)
        : (string) $cnpj;
};
$endInstPartes = [];
$logrInst = trim((string) ($instituicaoRow['logradouro'] ?? ''));
$numInst = trim((string) ($instituicaoRow['numero'] ?? ''));
if ($logrInst !== '') {
    $endInstPartes[] = $logrInst . ($numInst !== '' ? ', ' . $numInst : '');
}
if (!empty($instituicaoRow['bairro'])) {
    $endInstPartes[] = trim((string) $instituicaoRow['bairro']);
}
$cidUfInst = trim((string) ($instituicaoRow['cidade'] ?? ''));
if (!empty($instituicaoRow['uf'])) {
    $cidUfInst .= ($cidUfInst !== '' ? '/' : '') . strtoupper(trim((string) $instituicaoRow['uf']));
}
if ($cidUfInst !== '') {
    $endInstPartes[] = $cidUfInst;
}
$instituicaoCracha = [
    'nome' => trim((string) ($instituicaoRow['nomeFantasia'] ?? '')) ?: trim((string) ($instituicaoRow['razaoSocial'] ?? '')) ?: 'Casa de Acolhimento',
    'razao' => trim((string) ($instituicaoRow['razaoSocial'] ?? '')) ?: trim((string) ($instituicaoRow['nomeFantasia'] ?? '')) ?: 'Casa de Acolhimento',
    'cnpj' => !empty($instituicaoRow['cnpj']) ? $formatarCnpj((string) $instituicaoRow['cnpj']) : '',
    'telefone' => !empty($instituicaoRow['telefone']) ? $formatarTelefone((string) $instituicaoRow['telefone']) : '',
    'endereco' => implode(' · ', $endInstPartes),
    'emissao' => date('d/m/Y'),
];
$colaboradoresCracha = [];
foreach ($colaboradores as $item) {
    $partesNome = preg_split('/\s+/', trim((string) $item['nomeCompleto'])) ?: [];
    $inic = !$partesNome
        ? 'EL'
        : mb_strtoupper(
            mb_substr($partesNome[0], 0, 1, 'UTF-8')
            . (count($partesNome) > 1 ? mb_substr($partesNome[count($partesNome) - 1], 0, 1, 'UTF-8') : mb_substr($partesNome[0], 1, 1, 'UTF-8')),
            'UTF-8'
        );
    $colaboradoresCracha[(int) $item['idColaborador']] = [
        'id' => (int) $item['idColaborador'],
        'matricula' => 'COL-' . str_pad((string) $item['idColaborador'], 4, '0', STR_PAD_LEFT),
        'nomeCompleto' => (string) $item['nomeCompleto'],
        'iniciais' => $inic,
        'cpf' => $formatarCpf((string) $item['cpf']),
        'tipoVinculo' => (string) $item['tipoVinculo'],
        'cargoFuncao' => (string) $item['cargoFuncao'],
        'registroProfissional' => (string) ($item['registroProfissional'] ?? ''),
        'turno' => (string) $item['turno'],
        'dataInicio' => !empty($item['dataInicio']) ? date('d/m/Y', strtotime((string) $item['dataInicio'])) : '—',
        'fotoUrl' => $urlFoto($item),
    ];
}
$abrirCrachaInicial = filter_input(INPUT_GET, 'cracha', FILTER_VALIDATE_INT) ?: 0;

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
    <style>
        [data-photo-video][hidden], [data-camera-panel][hidden] { display: none; }
        .badge-card {
            width: 54mm;
            height: 86mm;
            box-sizing: border-box;
            position: relative;
            overflow: hidden;
            background: #ffffff;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .badge-cut-border { border: 1px dashed #64748b; }
        .badge-fold-divider { border-right: 1.5px dotted #0f172a; }
        .badge-bleed-frame {
            padding: 3mm;
            position: relative;
            display: inline-block;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .crop-mark { position: absolute; background: #0f172a; }
        @page { size: A4 portrait; margin: 12mm; }
        @media print {
            html, body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
                height: auto !important;
                overflow: visible !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            body > :not(#crachaPrintRoot) { display: none !important; }
            #crachaPrintRoot { display: block !important; width: 100% !important; }
            .no-print { display: none !important; }
            .badge-shadow { box-shadow: none !important; }
        }
    </style>
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
                <form method="post" enctype="multipart/form-data" class="space-y-5" data-confirm="Confirma gravar este cadastro?">
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

                    <fieldset class="rounded-lg border border-gray-200 p-4">
                        <legend class="px-2 text-sm font-semibold text-gray-700">2. Foto de identificação (crachá e escala)</legend>
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                            <div class="flex h-32 w-32 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-gray-200 bg-gray-50" data-photo-preview>
                                <?php $fotoUrlAtual = $colaboradorEdicao ? $urlFoto($colaboradorEdicao) : null; ?>
                                <?php if ($fotoUrlAtual !== null): ?>
                                    <img src="<?php echo appEscape($fotoUrlAtual); ?>" alt="Foto de <?php echo appEscape((string) ($colaboradorEdicao['nomeCompleto'] ?? '')); ?>" class="h-full w-full object-cover" data-photo-image>
                                <?php else: ?>
                                    <i class="ph ph-user text-5xl text-gray-300" data-photo-placeholder aria-hidden="true"></i>
                                <?php endif; ?>
                            </div>
                            <div class="space-y-3">
                                <p class="text-sm text-gray-600">Tire uma foto com a câmera ou selecione uma imagem JPEG, PNG ou WebP de até 5 MB.</p>
                                <div class="flex flex-wrap gap-2">
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                                        <i class="ph ph-upload-simple text-lg" aria-hidden="true"></i> Escolher foto
                                        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" capture="user" data-photo-input class="sr-only">
                                    </label>
                                    <button type="button" data-camera-open class="inline-flex items-center gap-2 rounded border border-elo-teal/30 px-3 py-2 text-sm font-medium text-elo-teal hover:bg-elo-teal/5">
                                        <i class="ph ph-camera text-lg" aria-hidden="true"></i> Abrir câmera
                                    </button>
                                    <?php if (!empty($colaboradorEdicao['caminhoFoto'])): ?>
                                        <label class="inline-flex items-center gap-2 rounded border border-red-200 px-3 py-2 text-sm text-red-700">
                                            <input type="checkbox" name="removerFoto" value="1" data-photo-remove class="rounded border-red-300 text-red-600">
                                            Remover foto atual
                                        </label>
                                    <?php endif; ?>
                                </div>
                                <p role="status" data-photo-message class="text-xs text-gray-500"></p>
                            </div>
                        </div>
                        <div data-camera-panel hidden class="mt-4 max-w-xl rounded-lg border border-gray-200 bg-gray-50 p-3">
                            <video data-photo-video autoplay playsinline class="max-h-80 w-full rounded bg-black"></video>
                            <div class="mt-3 flex gap-2">
                                <button type="button" data-camera-capture class="rounded bg-elo-teal px-3 py-2 text-sm font-medium text-white hover:bg-elo-green">Capturar foto</button>
                                <button type="button" data-camera-close class="rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Fechar câmera</button>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="grid gap-4 rounded-lg border border-gray-200 p-4 md:grid-cols-3">
                        <legend class="px-2 text-sm font-semibold text-gray-700">3. Contato e endereço</legend>
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
                        <legend class="px-2 text-sm font-semibold text-gray-700">4. Observações e controle</legend>
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
                            <button type="button" data-open-cracha="<?php echo (int) $colaboradorEdicao['idColaborador']; ?>"
                                    class="inline-flex items-center gap-2 rounded border border-elo-teal/40 bg-elo-teal/5 px-4 py-2.5 text-sm font-medium text-elo-teal hover:bg-elo-teal/10">
                                <i class="ph ph-identification-card text-lg" aria-hidden="true"></i>
                                Emitir crachá
                            </button>
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
                                        <div class="flex items-center gap-3">
                                            <?php $thumbFoto = $urlFoto($item); ?>
                                            <?php if ($thumbFoto !== null): ?>
                                                <img src="<?php echo appEscape($thumbFoto); ?>" alt="Foto de <?php echo appEscape($item['nomeCompleto']); ?>"
                                                     class="h-10 w-10 shrink-0 rounded-full border border-gray-200 object-cover">
                                            <?php else: ?>
                                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-gray-100 text-xs font-semibold text-gray-500">
                                                    <i class="ph ph-user text-lg" aria-hidden="true"></i>
                                                </span>
                                            <?php endif; ?>
                                            <div>
                                                <div class="font-medium text-gray-800" data-preserve-case><?php echo (int) $item['idColaborador']; ?> — <?php echo appEscape($item['nomeCompleto']); ?></div>
                                                <div class="text-xs text-gray-500" data-preserve-case><?php echo appEscape($formatarCpf($item['cpf'])); ?></div>
                                            </div>
                                        </div>
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
                                            <button type="button" data-open-cracha="<?php echo (int) $item['idColaborador']; ?>"
                                                    aria-label="Emitir crachá de <?php echo appEscape($item['nomeCompleto']); ?>"
                                                    title="Emitir crachá de identificação"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded border border-sky-200 text-sky-700 hover:bg-sky-50">
                                                <i class="ph ph-identification-card text-lg" aria-hidden="true"></i>
                                            </button>
                                            <a href="<?php echo appEscape(appTabUrl($urlAtual . '?editar=' . (int) $item['idColaborador'] . '#manutencao')); ?>"
                                               aria-label="Editar <?php echo appEscape($item['nomeCompleto']); ?>"
                                               title="Editar cadastro"
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

    <!-- Modal Grande de Emissão e Impressão de Crachá -->
    <div id="modalCrachaColaborador" role="dialog" aria-modal="true" aria-labelledby="tituloModalCracha"
         class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-3 md:p-6">
        <div class="flex max-h-[94vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-elo-teal/10 text-elo-teal">
                        <i class="ph ph-identification-card text-xl" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h2 id="tituloModalCracha" class="text-base font-bold text-slate-800">Crachá de Identificação Funcional</h2>
                        <p id="subtituloModalCracha" class="text-xs text-slate-500" data-preserve-case></p>
                    </div>
                </div>
                <button type="button" id="btnFecharModalCracha" aria-label="Fechar modal de crachá"
                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <i class="ph ph-x text-xl" aria-hidden="true"></i>
                </button>
            </header>

            <div class="flex-1 overflow-y-auto bg-slate-50 p-5 space-y-5">
                <!-- Controles ao vivo -->
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
                    <div class="grid gap-4 md:grid-cols-3">
                        <label class="text-xs font-medium text-slate-700">Modo de impressão
                            <select id="crachaModo" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                                <option value="caseiro_dobravel">Caseira A4 — Frente e Verso dobrável</option>
                                <option value="caseiro_adesivo">Papel Adesivo A4 — 2× Somente Frente</option>
                                <option value="grafica_pvc">Gráfica / PVC — Frente e Verso com sangria</option>
                            </select>
                        </label>
                        <label class="text-xs font-medium text-slate-700">Cor da faixa
                            <select id="crachaCor" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                                <option value="auto">Automática por vínculo</option>
                                <option value="teal">Verde-petróleo (Elo)</option>
                                <option value="navy">Azul marinho</option>
                                <option value="emerald">Verde esmeralda</option>
                                <option value="purple">Roxo (Voluntariado)</option>
                                <option value="amber">Âmbar (Terceirizado)</option>
                                <option value="slate">Grafite sóbrio</option>
                            </select>
                        </label>
                        <div class="flex flex-col justify-center gap-2 pt-2 text-xs text-slate-700">
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" id="crachaMostrarCpf" checked class="rounded border-slate-300 text-elo-teal focus:ring-elo-teal">
                                Exibir CPF no verso
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" id="crachaMostrarReg" checked class="rounded border-slate-300 text-elo-teal focus:ring-elo-teal">
                                Exibir registro profissional
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" id="crachaMostrarFuro" checked class="rounded border-slate-300 text-elo-teal focus:ring-elo-teal">
                                Guia de furo (cordão / jacaré)
                            </label>
                        </div>
                    </div>
                    <div id="crachaAvisoSemFoto" class="mt-3 hidden items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        <span><i class="ph ph-camera mr-1" aria-hidden="true"></i> Este colaborador ainda não possui foto cadastrada (serão exibidas as iniciais).</span>
                        <a id="crachaLinkAdicionarFoto" href="#" class="font-semibold underline">Adicionar foto</a>
                    </div>
                </div>

                <!-- Palco de prévia CR80 em tamanho real -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2.5 text-[11px] text-slate-500">
                        <span><strong>Tamanho real CR80:</strong> 54 mm × 86 mm</span>
                        <span>Na impressão do navegador, marque <strong>"Escala: 100%"</strong> e <strong>"Gráficos de plano de fundo"</strong>.</span>
                    </div>
                    <div id="crachaPreviewStage" class="flex flex-col items-center justify-center py-2"></div>
                </div>
            </div>

            <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-white px-5 py-3.5">
                <button type="button" id="btnFecharModalCrachaFooter"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Fechar
                </button>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" id="btnImprimirCrachaModal"
                            class="inline-flex items-center gap-2 rounded-lg bg-elo-teal px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-elo-green">
                        <i class="ph ph-printer text-lg" aria-hidden="true"></i> Imprimir agora
                    </button>
                    <a id="btnPdfA4Cracha" href="#"
                       class="inline-flex items-center gap-2 rounded-lg border border-elo-teal/40 bg-elo-teal/5 px-3.5 py-2 text-sm font-medium text-elo-teal hover:bg-elo-teal/10">
                        <i class="ph ph-file-pdf text-lg" aria-hidden="true"></i> PDF Folha A4
                    </a>
                    <a id="btnPdfGraficaCracha" href="#"
                       class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-3.5 py-2 text-sm font-medium text-white hover:bg-slate-900">
                        <i class="ph ph-export text-lg" aria-hidden="true"></i> PDF para Gráfica (CR80 + sangria)
                    </a>
                </div>
            </footer>
        </div>
    </div>

    <!-- Container exclusivo para impressão limpa via window.print() -->
    <div id="crachaPrintRoot" class="hidden"></div>

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

            const photoInput = document.querySelector('[data-photo-input]');
            const photoPreview = document.querySelector('[data-photo-preview]');
            const photoMessage = document.querySelector('[data-photo-message]');
            const removePhoto = document.querySelector('[data-photo-remove]');
            const setPhotoPreview = (source) => {
                if (!photoPreview) return;
                let image = photoPreview.querySelector('[data-photo-image]');
                if (!image) {
                    photoPreview.replaceChildren();
                    image = document.createElement('img');
                    image.dataset.photoImage = '';
                    image.alt = 'Prévia da foto selecionada';
                    image.className = 'h-full w-full object-cover';
                    photoPreview.append(image);
                }
                image.src = source;
            };
            photoInput?.addEventListener('change', () => {
                const file = photoInput.files?.[0];
                if (!file) return;
                if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
                    photoInput.value = '';
                    if (photoMessage) photoMessage.textContent = 'Escolha uma foto JPEG, PNG ou WebP de até 5 MB.';
                    return;
                }
                if (removePhoto) removePhoto.checked = false;
                if (photoMessage) photoMessage.textContent = file.name;
                setPhotoPreview(URL.createObjectURL(file));
            });
            removePhoto?.addEventListener('change', () => {
                if (removePhoto.checked && photoInput) photoInput.value = '';
            });

            const cameraPanel = document.querySelector('[data-camera-panel]');
            const video = document.querySelector('[data-photo-video]');
            const openCamera = document.querySelector('[data-camera-open]');
            let cameraStream = null;
            const stopCamera = () => {
                cameraStream?.getTracks().forEach((track) => track.stop());
                cameraStream = null;
                if (video) video.srcObject = null;
                if (cameraPanel) {
                    cameraPanel.hidden = true;
                    cameraPanel.classList.add('hidden');
                }
            };
            openCamera?.addEventListener('click', async () => {
                if (!navigator.mediaDevices?.getUserMedia) {
                    if (photoMessage) photoMessage.textContent = 'A câmera não está disponível neste navegador ou a página não está em HTTPS. Use "Escolher foto".';
                    return;
                }
                try {
                    cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                    video.srcObject = cameraStream;
                    cameraPanel.hidden = false;
                    cameraPanel.classList.remove('hidden');
                    if (photoMessage) photoMessage.textContent = '';
                } catch (error) {
                    console.error('Falha ao acessar a câmera:', error);
                    if (photoMessage) photoMessage.textContent = 'Não foi possível acessar a câmera. Verifique a permissão do navegador ou escolha uma foto.';
                }
            });
            document.querySelector('[data-camera-close]')?.addEventListener('click', stopCamera);
            document.querySelector('[data-camera-capture]')?.addEventListener('click', () => {
                if (!video?.videoWidth || !video?.videoHeight || !cameraStream) {
                    if (photoMessage) photoMessage.textContent = 'A câmera ainda não está pronta para capturar.';
                    return;
                }
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0);
                canvas.toBlob((blob) => {
                    if (!blob) {
                        if (photoMessage) photoMessage.textContent = 'Não foi possível capturar a foto. Tente novamente.';
                        return;
                    }
                    const file = new File([blob], 'foto-colaborador.jpg', { type: 'image/jpeg' });
                    const transfer = new DataTransfer();
                    transfer.items.add(file);
                    photoInput.files = transfer.files;
                    photoInput.dispatchEvent(new Event('change', { bubbles: true }));
                    stopCamera();
                }, 'image/jpeg', 0.9);
            });
            window.addEventListener('pagehide', stopCamera, { once: true });

            // Modal grande de Crachá na mesma tela (sem abrir nova aba)
            const crachaUrlBase = <?php echo json_encode($urlCracha, JSON_THROW_ON_ERROR); ?>;
            const colabAtualUrl = <?php echo json_encode($urlAtual, JSON_THROW_ON_ERROR); ?>;
            const instituicaoCracha = <?php echo json_encode($instituicaoCracha, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); ?>;
            const colaboradoresCracha = <?php echo json_encode($colaboradoresCracha, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); ?>;
            const abrirCrachaInicial = <?php echo (int) $abrirCrachaInicial; ?>;

            const modalCracha = document.getElementById('modalCrachaColaborador');
            const stageCracha = document.getElementById('crachaPreviewStage');
            const printRoot = document.getElementById('crachaPrintRoot');
            const selModo = document.getElementById('crachaModo');
            const selCor = document.getElementById('crachaCor');
            const chkCpf = document.getElementById('crachaMostrarCpf');
            const chkReg = document.getElementById('crachaMostrarReg');
            const chkFuro = document.getElementById('crachaMostrarFuro');
            const btnPdfA4 = document.getElementById('btnPdfA4Cracha');
            const btnPdfGrafica = document.getElementById('btnPdfGraficaCracha');
            const avisoSemFoto = document.getElementById('crachaAvisoSemFoto');
            const linkAddFoto = document.getElementById('crachaLinkAdicionarFoto');
            let crachaColabAtual = null;

            const escHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (ch) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[ch]));

            const paletas = {
                teal: { primary: '#007c89', dark: '#005b66', soft: '#e6f4f5', text: '#005b66' },
                navy: { primary: '#1e3a8a', dark: '#172554', soft: '#dbeafe', text: '#1e3a8a' },
                emerald: { primary: '#047857', dark: '#064e3b', soft: '#d1fae5', text: '#065f46' },
                purple: { primary: '#6d28d9', dark: '#4c1d95', soft: '#f3e8ff', text: '#5b21b6' },
                amber: { primary: '#b45309', dark: '#78350f', soft: '#fef3c7', text: '#92400e' },
                slate: { primary: '#334155', dark: '#0f172a', soft: '#f1f5f9', text: '#1e293b' },
            };
            const vinculoCor = {
                'Colaborador': paletas.teal,
                'Voluntário': paletas.purple,
                'Estagiário': { primary: '#0369a1', dark: '#075985', soft: '#e0f2fe', text: '#075985' },
                'Terceirizado': paletas.amber,
            };

            const renderCrachaPreview = () => {
                if (!crachaColabAtual || !stageCracha) return;
                const c = crachaColabAtual;
                const modo = selModo.value;
                const corKey = selCor.value;
                const pal = corKey !== 'auto' && paletas[corKey] ? paletas[corKey] : (vinculoCor[c.tipoVinculo] || paletas.teal);
                const showCpf = chkCpf.checked;
                const showReg = chkReg.checked;
                const showFuro = chkFuro.checked;

                const furoHtml = showFuro
                    ? '<div class="mx-auto mb-[1.8mm] h-[2.1mm] w-[11mm] rounded-full border border-dashed border-white/80"></div>'
                    : '<div class="h-[1.8mm]"></div>';
                const fotoHtml = c.fotoUrl
                    ? `<img src="${escHtml(c.fotoUrl)}" alt="" class="mx-auto h-[26mm] w-[23mm] rounded-[2.5mm] border-[0.7mm] border-white object-cover shadow-sm">`
                    : `<div class="mx-auto flex h-[26mm] w-[23mm] items-center justify-center rounded-[2.5mm] border-[0.7mm] border-white bg-slate-200 text-xl font-bold shadow-sm" style="color:${pal.dark}">${escHtml(c.iniciais)}</div>`;
                const regFrente = showReg && c.registroProfissional
                    ? `<p class="mt-[0.4mm] text-[5.6pt] text-slate-500">${escHtml(c.registroProfissional)}</p>` : '';

                const frente = `<div class="badge-card badge-shadow flex flex-col">
                    <div style="background:${pal.primary};" class="px-[3mm] pb-[7mm] pt-[2.2mm] text-center text-white">
                        ${furoHtml}
                        <div class="text-[6.4pt] font-bold uppercase leading-[1.18] tracking-wide">${escHtml(instituicaoCracha.nome)}</div>
                        <div class="mt-[0.5mm] text-[5pt] uppercase tracking-[0.6pt] opacity-90">Identificação Funcional</div>
                    </div>
                    <div class="-mt-[5.5mm] text-center">${fotoHtml}</div>
                    <div class="flex-1 px-[3mm] pt-[2mm] text-center">
                        <h3 class="text-[8.2pt] font-bold leading-[1.18] text-slate-900">${escHtml(c.nomeCompleto)}</h3>
                        <p class="mt-[0.7mm] text-[6.5pt] font-medium leading-[1.18] text-slate-600">${escHtml(c.cargoFuncao)}</p>
                        ${regFrente}
                        <div class="mt-[1.8mm]">
                            <span style="background:${pal.soft};color:${pal.text};" class="inline-block rounded-full px-[2.8mm] py-[0.6mm] text-[5.8pt] font-bold uppercase tracking-wider">${escHtml(c.tipoVinculo)}</span>
                        </div>
                    </div>
                    <div class="border-t border-slate-200 bg-slate-50 px-[3.2mm] pb-[1.8mm] pt-[1.5mm]">
                        <div class="flex items-center justify-between text-[5.8pt] text-slate-500">
                            <strong class="text-slate-900">${escHtml(c.matricula)}</strong>
                            <span>Turno: <strong class="text-slate-900">${escHtml(c.turno)}</strong></span>
                        </div>
                        <div style="background:${pal.primary};" class="mt-[1.2mm] h-[1.3mm] rounded-full"></div>
                    </div>
                </div>`;

                const cpfLinha = showCpf ? `<div class="flex justify-between py-[0.4mm]"><span class="text-slate-500">CPF:</span><strong class="text-slate-900">${escHtml(c.cpf)}</strong></div>` : '';
                const regLinha = showReg && c.registroProfissional ? `<div class="flex justify-between py-[0.4mm]"><span class="text-slate-500">Registro:</span><strong class="text-slate-900">${escHtml(c.registroProfissional)}</strong></div>` : '';
                const verso = `<div class="badge-card badge-shadow flex flex-col">
                    <div style="background:${pal.dark};" class="px-[3mm] py-[2.2mm] text-center text-white">
                        ${furoHtml}
                        <div class="text-[6.2pt] font-bold uppercase tracking-wider">Dados Institucionais</div>
                    </div>
                    <div class="flex-1 space-y-[1.6mm] px-[3.2mm] py-[2.2mm] text-[5.7pt] leading-[1.25] text-slate-700">
                        <div class="divide-y divide-slate-100">
                            <div class="flex justify-between py-[0.4mm]"><span class="text-slate-500">Matrícula:</span><strong class="text-slate-900">${escHtml(c.matricula)}</strong></div>
                            <div class="flex justify-between py-[0.4mm]"><span class="text-slate-500">Vínculo:</span><strong class="text-slate-900">${escHtml(c.tipoVinculo)}</strong></div>
                            ${cpfLinha}${regLinha}
                            <div class="flex justify-between py-[0.4mm]"><span class="text-slate-500">Início:</span><strong class="text-slate-900">${escHtml(c.dataInicio)}</strong></div>
                        </div>
                        <div class="border-t border-slate-200 pt-[1.5mm]">
                            <div class="text-[6pt] font-bold leading-[1.2] text-slate-900">${escHtml(instituicaoCracha.razao)}</div>
                            ${instituicaoCracha.cnpj ? `<div class="mt-[0.3mm] text-[5.3pt] text-slate-600">CNPJ: ${escHtml(instituicaoCracha.cnpj)}</div>` : ''}
                            ${instituicaoCracha.endereco ? `<div class="mt-[0.3mm] text-[5.1pt] leading-[1.22] text-slate-600">${escHtml(instituicaoCracha.endereco)}</div>` : ''}
                            ${instituicaoCracha.telefone ? `<div class="mt-[0.3mm] text-[5.3pt] text-slate-600">Tel.: ${escHtml(instituicaoCracha.telefone)}</div>` : ''}
                        </div>
                        <div class="rounded border border-slate-200 bg-slate-50 px-[1.8mm] py-[1.4mm] text-justify text-[4.9pt] leading-[1.24] text-slate-600">
                            Crachá de uso pessoal e intransferível. Em caso de perda, extravio ou desligamento, devolver imediatamente à administração.
                        </div>
                    </div>
                    <div class="px-[3.2mm] pb-[2.2mm] text-center">
                        <div class="mx-auto mb-[0.8mm] w-4/5 border-t border-slate-500"></div>
                        <div class="text-[5.4pt] font-bold text-slate-700">Coordenação / Administração</div>
                        <div class="mt-[0.3mm] text-[4.8pt] text-slate-400">Emitido em ${escHtml(instituicaoCracha.emissao)} · Sistema Elo</div>
                    </div>
                </div>`;

                const cropMarks = `
                    <span class="crop-mark" style="top:0;left:3mm;width:0.3mm;height:2.2mm;"></span>
                    <span class="crop-mark" style="top:3mm;left:0;width:2.2mm;height:0.3mm;"></span>
                    <span class="crop-mark" style="top:0;right:3mm;width:0.3mm;height:2.2mm;"></span>
                    <span class="crop-mark" style="top:3mm;right:0;width:2.2mm;height:0.3mm;"></span>
                    <span class="crop-mark" style="bottom:0;left:3mm;width:0.3mm;height:2.2mm;"></span>
                    <span class="crop-mark" style="bottom:3mm;left:0;width:2.2mm;height:0.3mm;"></span>
                    <span class="crop-mark" style="bottom:0;right:3mm;width:0.3mm;height:2.2mm;"></span>
                    <span class="crop-mark" style="bottom:3mm;right:0;width:2.2mm;height:0.3mm;"></span>`;

                let markup = '';
                if (modo === 'caseiro_dobravel') {
                    markup = `<div class="inline-flex items-stretch badge-cut-border shadow-md">
                        <div class="badge-fold-divider">${frente}</div>
                        <div>${verso}</div>
                    </div>
                    <p class="mt-4 text-center text-xs text-slate-500">Recorte na linha tracejada externa, dobre ao meio na linha pontilhada central, cole/plastifique e fure no guia superior.</p>`;
                } else if (modo === 'caseiro_adesivo') {
                    markup = `<div class="flex flex-wrap items-center justify-center gap-6">
                        <div class="badge-cut-border shadow-md">${frente}</div>
                        <div class="badge-cut-border shadow-md">${frente}</div>
                    </div>
                    <p class="mt-4 text-center text-xs text-slate-500">Modo Papel Adesivo: 2 frentes em tamanho CR80 (54 × 86 mm) prontas para recortar e colar sobre cartão PVC ou crachá rígido.</p>`;
                } else {
                    markup = `<div class="flex flex-wrap items-center justify-center gap-8">
                        <div class="text-center">
                            <p class="no-print mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Frente (CR80 + 3mm sangria)</p>
                            <div class="badge-bleed-frame shadow-md" style="background:${pal.primary};">${frente}${cropMarks}</div>
                        </div>
                        <div class="text-center">
                            <p class="no-print mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Verso (CR80 + 3mm sangria)</p>
                            <div class="badge-bleed-frame border border-slate-200 bg-white shadow-md">${verso}${cropMarks}</div>
                        </div>
                    </div>
                    <p class="no-print mt-4 text-center text-xs text-slate-500">Use <strong>"PDF para Gráfica (CR80 + sangria)"</strong> abaixo para baixar o arquivo pronto em 2 páginas (60 × 92 mm) com marcas de corte.</p>`;
                }

                stageCracha.innerHTML = markup;
                const tok = getTabToken();
                const params = new URLSearchParams({
                    id: String(c.id),
                    modo,
                    cor: corKey,
                    cpf: showCpf ? '1' : '0',
                    reg: showReg ? '1' : '0',
                    furo: showFuro ? '1' : '0',
                    formato: 'pdf',
                });
                if (tok) params.set('tabToken', tok);
                btnPdfA4.href = `${crachaUrlBase}?${params.toString()}&pdfTipo=a4`;
                btnPdfGrafica.href = `${crachaUrlBase}?${params.toString()}&pdfTipo=grafica`;
            };

            const abrirModalCracha = (id) => {
                const colab = colaboradoresCracha[id];
                if (!colab || !modalCracha) return;
                crachaColabAtual = colab;
                document.getElementById('subtituloModalCracha').textContent = `${colab.matricula} · ${colab.nomeCompleto} (${colab.tipoVinculo})`;
                if (!colab.fotoUrl) {
                    avisoSemFoto.classList.remove('hidden');
                    avisoSemFoto.classList.add('flex');
                    const tok = getTabToken();
                    linkAddFoto.href = `${colabAtualUrl}?editar=${colab.id}${tok ? '&tabToken=' + encodeURIComponent(tok) : ''}#manutencao`;
                } else {
                    avisoSemFoto.classList.add('hidden');
                    avisoSemFoto.classList.remove('flex');
                }
                renderCrachaPreview();
                modalCracha.classList.remove('hidden');
                modalCracha.classList.add('flex');
            };

            const fecharModalCracha = () => {
                if (!modalCracha) return;
                modalCracha.classList.add('hidden');
                modalCracha.classList.remove('flex');
            };

            document.querySelectorAll('[data-open-cracha]').forEach((btn) => {
                btn.addEventListener('click', () => abrirModalCracha(Number(btn.dataset.openCracha)));
            });
            [selModo, selCor, chkCpf, chkReg, chkFuro].forEach((el) => el?.addEventListener('change', renderCrachaPreview));
            document.getElementById('btnFecharModalCracha')?.addEventListener('click', fecharModalCracha);
            document.getElementById('btnFecharModalCrachaFooter')?.addEventListener('click', fecharModalCracha);
            modalCracha?.addEventListener('click', (e) => { if (e.target === modalCracha) fecharModalCracha(); });
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modalCracha && !modalCracha.classList.contains('hidden')) fecharModalCracha(); });

            document.getElementById('btnImprimirCrachaModal')?.addEventListener('click', () => {
                if (!printRoot || !stageCracha) return;
                printRoot.innerHTML = `<div style="display:flex;flex-direction:column;align-items:center;padding-top:10mm;">${stageCracha.innerHTML}</div>`;
                window.print();
            });

            if (abrirCrachaInicial > 0 && colaboradoresCracha[abrirCrachaInicial]) {
                abrirModalCracha(abrirCrachaInicial);
            }
        })();
    </script>
</body>
</html>
