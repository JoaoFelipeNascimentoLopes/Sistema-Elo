<?php
require_once __DIR__ . '/../../../auth/access.php';
require_once __DIR__ . '/../../../auth/acolhido-fotos.php';
$usuarioAtual = appRequirePage('gestao-acolhidos.cadastros/acolhidos');
$pdo = appPdo();
$baseUrl = appBaseUrl();
$erro = '';
$resultado = is_scalar($_GET['resultado'] ?? null) ? (string) $_GET['resultado'] : '';
$idEdicaoInput = $_GET['editar'] ?? null;
$idEdicao = is_scalar($idEdicaoInput) ? filter_var($idEdicaoInput, FILTER_VALIDATE_INT) : false;
$dadosAcolhido = [
    'idAcolhido' => 0, 'nomeCompleto' => '', 'cpf' => '', 'rg' => '',
    'dataNascimento' => '', 'sexo' => 'Não informado', 'nomeMae' => '',
    'maeDesconhecida' => 0, 'nomePai' => '', 'paiDesconhecido' => 0,
    'caminhoFoto' => null, 'moradorRua' => 0, 'localMoradia' => '',
    'cep' => '', 'logradouro' => '', 'numero' => '', 'complemento' => '',
    'bairro' => '', 'cidade' => '', 'uf' => '',
];
$dadosFicha = [
    'idFichaMedica' => 0, 'idAcolhido' => 0, 'tipoSanguineo' => 'Não informado',
    'alergias' => [], 'dependencias' => [], 'relevanciasClinicas' => [],
    'dataRegistro' => date('Y-m-d'),
];
$formAcolhidoFoiEnviado = false;
$formFichaFoiEnviado = false;
$podeGerenciarFicha = false;
$fotoNovaRelativa = null;

$textoPost = static function (string $campo): string {
    $valor = $_POST[$campo] ?? '';
    return is_scalar($valor) ? trim((string) $valor) : '';
};
$validarData = static function (string $data): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
    return $date !== false && $date->format('Y-m-d') === $data;
};
$formatarAuditoria = static function ($idUsuario, $nomeUsuario, $dataHora): string {
    if ($idUsuario === null || $nomeUsuario === null || $dataHora === null) {
        return '—';
    }

    $timestamp = strtotime((string) $dataHora);
    if ($timestamp === false) {
        return '—';
    }

    return (int) $idUsuario . ' - ' . (string) $nomeUsuario . ' - ' . date('d/m/Y H:i', $timestamp);
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
$decodificarTags = static function (string $json, string $rotulo): array {
    try {
        $tags = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new InvalidArgumentException('Confira as tags do campo ' . $rotulo . '.');
    }
    if (!is_array($tags) || count($tags) > 50) {
        throw new InvalidArgumentException('O campo ' . $rotulo . ' pode conter até 50 tags.');
    }
    $resultadoTags = [];
    foreach ($tags as $tag) {
        if (!is_string($tag)) {
            throw new InvalidArgumentException('Confira as tags do campo ' . $rotulo . '.');
        }
        $tag = trim($tag);
        if ($tag === '') {
            continue;
        }
        if (mb_strlen($tag, 'UTF-8') > 120) {
            throw new InvalidArgumentException('Cada item de ' . $rotulo . ' deve ter até 120 caracteres.');
        }
        $tag = mb_strtoupper($tag, 'UTF-8');
        $normalizada = $tag;
        if (!isset($resultadoTags[$normalizada])) {
            $resultadoTags[$normalizada] = $tag;
        }
    }
    return array_values($resultadoTags);
};
$valorCheckbox = static fn (string $campo): int => isset($_POST[$campo]) ? 1 : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    $acaoInput = $_POST['acao'] ?? '';
    $acao = is_scalar($acaoInput) ? (string) $acaoInput : '';

    try {
        if ($acao === 'salvarAcolhido') {
            $formAcolhidoFoiEnviado = true;
            $idInput = $textoPost('idAcolhido');
            $idAcolhido = $idInput === '' ? 0 : filter_var($idInput, FILTER_VALIDATE_INT);
            $nomeCompleto = $textoPost('nomeCompleto');
            $cpf = preg_replace('/\D+/', '', $textoPost('cpf')) ?? '';
            $rg = $textoPost('rg');
            $dataNascimento = $textoPost('dataNascimento');
            $sexo = $textoPost('sexo');
            $maeDesconhecida = $valorCheckbox('maeDesconhecida');
            $paiDesconhecido = $valorCheckbox('paiDesconhecido');
            $nomeMae = $maeDesconhecida ? '' : $textoPost('nomeMae');
            $nomePai = $paiDesconhecido ? '' : $textoPost('nomePai');
            $moradorRua = $valorCheckbox('moradorRua');
            $localMoradia = $moradorRua ? $textoPost('localMoradia') : '';
            $cep = $moradorRua ? '' : (preg_replace('/\D+/', '', $textoPost('cep')) ?? '');
            $logradouro = $moradorRua ? '' : $textoPost('logradouro');
            $numero = $moradorRua ? '' : $textoPost('numero');
            $complemento = $moradorRua ? '' : $textoPost('complemento');
            $bairro = $moradorRua ? '' : $textoPost('bairro');
            $cidade = $moradorRua ? '' : $textoPost('cidade');
            $uf = $moradorRua ? '' : strtoupper($textoPost('uf'));
            $removerFoto = $valorCheckbox('removerFoto') === 1;
            $uploadFoto = $_FILES['foto'] ?? null;
            $temUploadFoto = is_array($uploadFoto) && ($uploadFoto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            $dadosAcolhido = [
                'idAcolhido' => $idAcolhido !== false ? (int) $idAcolhido : 0,
                'nomeCompleto' => $nomeCompleto, 'cpf' => $cpf, 'rg' => $rg,
                'dataNascimento' => $dataNascimento, 'sexo' => $sexo,
                'nomeMae' => $nomeMae, 'maeDesconhecida' => $maeDesconhecida,
                'nomePai' => $nomePai, 'paiDesconhecido' => $paiDesconhecido,
                'caminhoFoto' => null, 'moradorRua' => $moradorRua,
                'localMoradia' => $localMoradia, 'cep' => $cep,
                'logradouro' => $logradouro, 'numero' => $numero,
                'complemento' => $complemento, 'bairro' => $bairro,
                'cidade' => $cidade, 'uf' => $uf,
            ];

            if ($idAcolhido === false || $idAcolhido < 0
                || $nomeCompleto === '' || mb_strlen($nomeCompleto, 'UTF-8') > 150
                || ($cpf !== '' && !$validarCpf($cpf))
                || mb_strlen($rg, 'UTF-8') > 30
                || !$validarData($dataNascimento) || $dataNascimento > date('Y-m-d')
                || !in_array($sexo, ['Feminino', 'Masculino', 'Outro', 'Não informado'], true)
                || (!$maeDesconhecida && ($nomeMae === '' || mb_strlen($nomeMae, 'UTF-8') > 150))
                || (!$paiDesconhecido && ($nomePai === '' || mb_strlen($nomePai, 'UTF-8') > 150))
                || ($localMoradia !== '' && mb_strlen($localMoradia, 'UTF-8') > 255)
                || ($cep !== '' && !preg_match('/^\d{8}$/', $cep))
                || mb_strlen($logradouro, 'UTF-8') > 150
                || mb_strlen($numero, 'UTF-8') > 20
                || mb_strlen($complemento, 'UTF-8') > 100
                || mb_strlen($bairro, 'UTF-8') > 100
                || mb_strlen($cidade, 'UTF-8') > 100
                || ($uf !== '' && !preg_match('/^[A-Z]{2}$/', $uf))
                || ($removerFoto && $temUploadFoto)) {
                throw new InvalidArgumentException('Confira os dados obrigatórios, os documentos e o endereço informados.');
            }
            if (($idAcolhido > 0 && ($idEdicao === false || $idAcolhido !== $idEdicao))
                || ($idAcolhido === 0 && $idEdicao !== false && $idEdicao > 0)) {
                throw new InvalidArgumentException('Abra um cadastro existente pela opção Editar ou use Novo cadastro para iniciar um registro.');
            }

            $fotoAnterior = null;
            if ($idAcolhido > 0) {
                $fotoStatement = $pdo->prepare('SELECT caminhoFoto FROM tbAcolhido WHERE idAcolhido = :id');
                $fotoStatement->execute(['id' => $idAcolhido]);
                $fotoAnterior = $fotoStatement->fetchColumn();
                if ($fotoAnterior === false) {
                    throw new InvalidArgumentException('Acolhido não encontrado.');
                }
            }

            if ($temUploadFoto) {
                $fotoNovaRelativa = appValidarUploadFotoAcolhido($uploadFoto);
            }
            $caminhoFoto = $removerFoto
                ? null
                : ($fotoNovaRelativa ?? (is_string($fotoAnterior) ? $fotoAnterior : null));
            $dadosAcolhido['caminhoFoto'] = $caminhoFoto;
            $valores = [
                'nomeCompleto' => $nomeCompleto,
                'cpf' => $cpf !== '' ? $cpf : null,
                'rg' => $rg !== '' ? $rg : null,
                'dataNascimento' => $dataNascimento,
                'sexo' => $sexo,
                'nomeMae' => $maeDesconhecida ? null : $nomeMae,
                'maeDesconhecida' => $maeDesconhecida,
                'nomePai' => $paiDesconhecido ? null : $nomePai,
                'paiDesconhecido' => $paiDesconhecido,
                'caminhoFoto' => $caminhoFoto,
                'moradorRua' => $moradorRua,
                'localMoradia' => $localMoradia !== '' ? $localMoradia : null,
                'cep' => $cep !== '' ? $cep : null,
                'logradouro' => $logradouro !== '' ? $logradouro : null,
                'numero' => $numero !== '' ? $numero : null,
                'complemento' => $complemento !== '' ? $complemento : null,
                'bairro' => $bairro !== '' ? $bairro : null,
                'cidade' => $cidade !== '' ? $cidade : null,
                'uf' => $uf !== '' ? $uf : null,
            ];

            if ($idAcolhido > 0) {
                $statement = $pdo->prepare(
                    'UPDATE tbAcolhido
                     SET nomeCompleto = :nomeCompleto, cpf = :cpf, rg = :rg,
                         dataNascimento = :dataNascimento, sexo = :sexo,
                         nomeMae = :nomeMae, maeDesconhecida = :maeDesconhecida,
                         nomePai = :nomePai, paiDesconhecido = :paiDesconhecido,
                         caminhoFoto = :caminhoFoto, moradorRua = :moradorRua,
                         localMoradia = :localMoradia, cep = :cep, logradouro = :logradouro,
                         numero = :numero, complemento = :complemento, bairro = :bairro,
                         cidade = :cidade, uf = :uf, idUsuarioAlteracao = :idUsuarioAlteracao
                     WHERE idAcolhido = :idAcolhido'
                );
                $statement->execute($valores + [
                    'idUsuarioAlteracao' => (int) $usuarioAtual['idUsuario'],
                    'idAcolhido' => $idAcolhido,
                ]);
                if ($statement->rowCount() === 0) {
                    $exists = $pdo->prepare('SELECT 1 FROM tbAcolhido WHERE idAcolhido = :id');
                    $exists->execute(['id' => $idAcolhido]);
                    if (!$exists->fetchColumn()) {
                        throw new InvalidArgumentException('Acolhido não encontrado.');
                    }
                }
                $fotoFoiSubstituida = $fotoNovaRelativa !== null;
                $fotoNovaRelativa = null;
                if (($fotoFoiSubstituida || $removerFoto) && is_string($fotoAnterior)) {
                    appRemoverFotoAcolhido($fotoAnterior);
                }
                header('Location: ' . appTabUrl(
                    'acolhidos.php?resultado=acolhido-atualizado&editar=' . $idAcolhido . '&subaba=acolhidos#manutencao'
                ));
                exit;
            }

            $statement = $pdo->prepare(
                'INSERT INTO tbAcolhido
                    (nomeCompleto, cpf, rg, dataNascimento, sexo, nomeMae, maeDesconhecida,
                     nomePai, paiDesconhecido, caminhoFoto, moradorRua, localMoradia, cep,
                     logradouro, numero, complemento, bairro, cidade, uf, idUsuarioCriacao)
                 VALUES
                    (:nomeCompleto, :cpf, :rg, :dataNascimento, :sexo, :nomeMae, :maeDesconhecida,
                     :nomePai, :paiDesconhecido, :caminhoFoto, :moradorRua, :localMoradia, :cep,
                     :logradouro, :numero, :complemento, :bairro, :cidade, :uf, :idUsuarioCriacao)'
            );
            $statement->execute($valores + ['idUsuarioCriacao' => (int) $usuarioAtual['idUsuario']]);
            $fotoNovaRelativa = null;
            header('Location: ' . appTabUrl('acolhidos.php?resultado=acolhido-criado#pesquisa'));
            exit;
        }

        if ($acao === 'salvarFicha') {
            $formFichaFoiEnviado = true;
            $idInput = $textoPost('idFichaModelo');
            $idFichaModelo = $idInput === '' ? 0 : filter_var($idInput, FILTER_VALIDATE_INT);
            $idAcolhidoInput = filter_var($textoPost('idAcolhido'), FILTER_VALIDATE_INT);
            $tipoSanguineo = $textoPost('tipoSanguineo');
            $dataRegistro = $textoPost('dataRegistro');
            $alergias = $decodificarTags($textoPost('alergiasJson'), 'alergias');
            $dependencias = $decodificarTags($textoPost('dependenciasJson'), 'dependências');
            $relevancias = $decodificarTags($textoPost('relevanciasClinicasJson'), 'relevâncias clínicas');
            $dadosFicha = [
                'idFichaMedica' => $idFichaModelo !== false ? (int) $idFichaModelo : 0,
                'idAcolhido' => $idAcolhidoInput !== false ? (int) $idAcolhidoInput : 0,
                'tipoSanguineo' => $tipoSanguineo, 'alergias' => $alergias,
                'dependencias' => $dependencias, 'relevanciasClinicas' => $relevancias,
                'dataRegistro' => $dataRegistro,
            ];
            if ($idFichaModelo === false || $idFichaModelo < 0 || !$idAcolhidoInput || $idAcolhidoInput < 1
                || !in_array($tipoSanguineo, ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Não informado'], true)
                || !$validarData($dataRegistro) || $dataRegistro > date('Y-m-d')) {
                throw new InvalidArgumentException('Confira o acolhido, o tipo sanguíneo e a data da ficha médica.');
            }
            if ($idEdicao === false || $idEdicao < 1 || (int) $idAcolhidoInput !== $idEdicao) {
                throw new InvalidArgumentException('Para registrar uma ficha médica, abra primeiro o cadastro do acolhido pela opção Editar.');
            }
            $acolhidoEmEdicaoStatement = $pdo->prepare('SELECT 1 FROM tbAcolhido WHERE idAcolhido = :id');
            $acolhidoEmEdicaoStatement->execute(['id' => $idEdicao]);
            if (!$acolhidoEmEdicaoStatement->fetchColumn()) {
                throw new InvalidArgumentException('O cadastro do acolhido precisa existir antes de registrar a ficha médica.');
            }
            if ($idFichaModelo > 0) {
                $modeloStatement = $pdo->prepare('SELECT idAcolhido FROM tbFichaMedica WHERE idFichaMedica = :id');
                $modeloStatement->execute(['id' => $idFichaModelo]);
                $acolhidoModelo = $modeloStatement->fetchColumn();
                if ($acolhidoModelo === false) {
                    throw new InvalidArgumentException('A ficha médica usada como base não foi encontrada.');
                }
                if ((int) $acolhidoModelo !== (int) $idAcolhidoInput) {
                    throw new InvalidArgumentException('A atualização deve permanecer vinculada ao mesmo acolhido da ficha original.');
                }
            }
            $alergiasJson = json_encode($alergias, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $dependenciasJson = json_encode($dependencias, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $relevanciasJson = json_encode($relevancias, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $valoresFicha = [
                'idAcolhido' => $idAcolhidoInput,
                'tipoSanguineo' => $tipoSanguineo,
                'alergias' => $alergiasJson,
                'dependencias' => $dependenciasJson,
                'relevanciasClinicas' => $relevanciasJson,
                'dataRegistro' => $dataRegistro,
            ];
            $pdo->beginTransaction();
            $acolhidoEmEdicaoStatement = $pdo->prepare(
                'SELECT idAcolhido FROM tbAcolhido WHERE idAcolhido = :id FOR UPDATE'
            );
            $acolhidoEmEdicaoStatement->execute(['id' => $idEdicao]);
            if (!$acolhidoEmEdicaoStatement->fetchColumn()) {
                throw new InvalidArgumentException('O cadastro do acolhido precisa existir antes de registrar a ficha médica.');
            }
            $atualizarTipoSanguineo = $pdo->prepare(
                'UPDATE tbFichaMedica
                 SET tipoSanguineo = :tipoSanguineo,
                     idUsuarioAlteracao = :idUsuarioAlteracao
                 WHERE idAcolhido = :idAcolhido
                   AND tipoSanguineo <> :tipoSanguineoComparacao'
            );
            $atualizarTipoSanguineo->execute([
                'tipoSanguineo' => $tipoSanguineo,
                'idUsuarioAlteracao' => (int) $usuarioAtual['idUsuario'],
                'idAcolhido' => $idAcolhidoInput,
                'tipoSanguineoComparacao' => $tipoSanguineo,
            ]);
            $statement = $pdo->prepare(
                'INSERT INTO tbFichaMedica
                    (idAcolhido, tipoSanguineo, alergias, dependencias, relevanciasClinicas,
                     dataRegistro, idUsuarioCriacao)
                 VALUES
                    (:idAcolhido, :tipoSanguineo, :alergias, :dependencias, :relevanciasClinicas,
                     :dataRegistro, :idUsuarioCriacao)'
            );
            $statement->execute($valoresFicha + ['idUsuarioCriacao' => (int) $usuarioAtual['idUsuario']]);
            $pdo->commit();
            header('Location: ' . appTabUrl(
                 'acolhidos.php?resultado=ficha-criada&editar=' . $idEdicao . '&subaba=fichas-medicas#manutencao'
            ));
            exit;
        }

        if ($acao === 'excluirAcolhido') {
            $id = filter_var($textoPost('idAcolhido'), FILTER_VALIDATE_INT);
            if (!$id || $id < 1) {
                throw new InvalidArgumentException('Acolhido inválido.');
            }
            $statement = $pdo->prepare('SELECT caminhoFoto FROM tbAcolhido WHERE idAcolhido = :id');
            $statement->execute(['id' => $id]);
            $foto = $statement->fetchColumn();
            if ($foto === false) {
                throw new InvalidArgumentException('Acolhido não encontrado.');
            }
            $delete = $pdo->prepare('DELETE FROM tbAcolhido WHERE idAcolhido = :id');
            $delete->execute(['id' => $id]);
            appRemoverFotoAcolhido(is_string($foto) ? $foto : null);
            header('Location: ' . appTabUrl('acolhidos.php?resultado=acolhido-excluido#pesquisa'));
            exit;
        }

        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erro = $exception->getMessage();
        if ($acao === 'salvarAcolhido') {
            $formAcolhidoFoiEnviado = true;
            if ((int) ($dadosAcolhido['idAcolhido'] ?? 0) > 0) {
                $fotoStatement = $pdo->prepare('SELECT caminhoFoto FROM tbAcolhido WHERE idAcolhido = :id');
                $fotoStatement->execute(['id' => (int) $dadosAcolhido['idAcolhido']]);
                $fotoExistente = $fotoStatement->fetchColumn();
                $dadosAcolhido['caminhoFoto'] = is_string($fotoExistente) ? $fotoExistente : null;
            }
        } elseif ($acao === 'salvarFicha') {
            $formFichaFoiEnviado = true;
        }
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Falha no cadastro de acolhido/ficha médica: ' . $exception->getMessage());
        $erro = (int) ($exception->errorInfo[1] ?? 0) === 1062
            ? 'Já existe um acolhido cadastrado com este CPF.'
            : ((int) ($exception->errorInfo[1] ?? 0) === 1451
                ? 'Não é possível excluir este acolhido enquanto houver fichas médicas ou outros registros vinculados.'
                : 'Não foi possível concluir a operação. Confira os dados e tente novamente.');
        if ($acao === 'salvarAcolhido') {
            $formAcolhidoFoiEnviado = true;
            if ((int) ($dadosAcolhido['idAcolhido'] ?? 0) > 0) {
                $fotoStatement = $pdo->prepare('SELECT caminhoFoto FROM tbAcolhido WHERE idAcolhido = :id');
                $fotoStatement->execute(['id' => (int) $dadosAcolhido['idAcolhido']]);
                $fotoExistente = $fotoStatement->fetchColumn();
                $dadosAcolhido['caminhoFoto'] = is_string($fotoExistente) ? $fotoExistente : null;
            }
        } elseif ($acao === 'salvarFicha') {
            $formFichaFoiEnviado = true;
        }
    } catch (RuntimeException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erro = $exception->getMessage();
        if ($acao === 'salvarAcolhido') {
            $formAcolhidoFoiEnviado = true;
        }
    } finally {
        if ($fotoNovaRelativa !== null) {
            appRemoverFotoAcolhido($fotoNovaRelativa);
            $fotoNovaRelativa = null;
        }
    }
}

if ($idEdicao !== false && $idEdicao > 0) {
    $statement = $pdo->prepare(
        'SELECT a.*, criador.idUsuario AS criadoPorId, criador.nomeCompleto AS criadoPor,
                alterador.idUsuario AS alteradoPorId, alterador.nomeCompleto AS alteradoPor
         FROM tbAcolhido a
         INNER JOIN tbUsuario criador ON criador.idUsuario = a.idUsuarioCriacao
         LEFT JOIN tbUsuario alterador ON alterador.idUsuario = a.idUsuarioAlteracao
         WHERE a.idAcolhido = :id'
    );
    $statement->execute(['id' => $idEdicao]);
    $registro = $statement->fetch(PDO::FETCH_ASSOC);
    if ($registro) {
        $podeGerenciarFicha = true;
        if (!$formAcolhidoFoiEnviado) {
            $dadosAcolhido = $registro;
        } else {
            foreach (['idUsuarioCriacao', 'idUsuarioAlteracao', 'criadoEm', 'atualizadoEm', 'criadoPorId', 'criadoPor', 'alteradoPorId', 'alteradoPor'] as $campoAuditoria) {
                $dadosAcolhido[$campoAuditoria] = $registro[$campoAuditoria] ?? null;
            }
        }
    } else {
        $erro = 'Acolhido não encontrado.';
    }
}

$idFichaEdicaoInput = $_GET['editarFicha'] ?? null;
$idFichaEdicao = is_scalar($idFichaEdicaoInput) ? filter_var($idFichaEdicaoInput, FILTER_VALIDATE_INT) : false;
if ($idFichaEdicao && $idFichaEdicao > 0 && !$podeGerenciarFicha) {
    $erro = 'Para acessar uma ficha médica, edite primeiro o cadastro do acolhido.';
} elseif ($podeGerenciarFicha && !$formFichaFoiEnviado && $idFichaEdicao && $idFichaEdicao > 0) {
    $statement = $pdo->prepare('SELECT * FROM tbFichaMedica WHERE idFichaMedica = :id');
    $statement->execute(['id' => $idFichaEdicao]);
    $registro = $statement->fetch(PDO::FETCH_ASSOC);
    if ($registro && (int) $registro['idAcolhido'] === $idEdicao) {
        foreach (['alergias', 'dependencias', 'relevanciasClinicas'] as $campo) {
            try {
                $registro[$campo] = json_decode($registro[$campo], true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                error_log('Dados inválidos em ' . $campo . ' da ficha médica ' . $idFichaEdicao . ': ' . $exception->getMessage());
                $registro[$campo] = [];
            }
        }
        $registro['dataRegistro'] = date('Y-m-d');
        $dadosFicha = $registro;
    } else {
        $erro = $registro
            ? 'A ficha médica selecionada pertence a outro acolhido.'
            : 'Ficha médica não encontrada.';
    }
}

$pesquisa = is_scalar($_GET['q'] ?? null) ? trim((string) $_GET['q']) : '';
$whereAcolhidos = '';
$parametrosAcolhidos = [];
if ($pesquisa !== '') {
    $whereAcolhidos = 'WHERE a.nomeCompleto LIKE :pesquisa OR a.cpf LIKE :documento';
    $parametrosAcolhidos = ['pesquisa' => '%' . $pesquisa . '%', 'documento' => '%' . preg_replace('/\D+/', '', $pesquisa) . '%'];
    if (ctype_digit($pesquisa)) {
        $whereAcolhidos .= ' OR a.idAcolhido = :idPesquisa';
        $parametrosAcolhidos['idPesquisa'] = (int) $pesquisa;
    }
}
$acolhidosStatement = $pdo->prepare(
    'SELECT a.idAcolhido, a.nomeCompleto, a.nomeMae, a.dataNascimento,
            a.caminhoFoto, a.cidade, a.uf
     FROM tbAcolhido a ' . $whereAcolhidos . '
     ORDER BY a.nomeCompleto, a.idAcolhido
     LIMIT 100'
);
$acolhidosStatement->execute($parametrosAcolhidos);
$acolhidos = $acolhidosStatement->fetchAll(PDO::FETCH_ASSOC);
$fichas = [];
if ($podeGerenciarFicha) {
    $fichasStatement = $pdo->prepare(
        'SELECT f.idFichaMedica, f.idAcolhido, f.tipoSanguineo, f.alergias,
            f.dependencias, f.relevanciasClinicas, f.dataRegistro, f.idUsuarioCriacao,
            f.idUsuarioAlteracao, f.criadoEm, f.atualizadoEm, a.nomeCompleto,
            criador.idUsuario AS criadoPorId, criador.nomeCompleto AS criadoPor,
            alterador.idUsuario AS alteradoPorId, alterador.nomeCompleto AS alteradoPor
     FROM tbFichaMedica f
     INNER JOIN tbAcolhido a ON a.idAcolhido = f.idAcolhido
     INNER JOIN tbUsuario criador ON criador.idUsuario = f.idUsuarioCriacao
     LEFT JOIN tbUsuario alterador ON alterador.idUsuario = f.idUsuarioAlteracao
     WHERE f.idAcolhido = :idAcolhido
     ORDER BY f.dataRegistro DESC, f.idFichaMedica DESC
     LIMIT 100'
    );
    $fichasStatement->execute(['idAcolhido' => $idEdicao]);
    $fichas = $fichasStatement->fetchAll(PDO::FETCH_ASSOC);
    $tipoSanguineoStatement = $pdo->prepare(
        'SELECT tipoSanguineo FROM tbFichaMedica
         WHERE idAcolhido = :idAcolhido
         ORDER BY criadoEm DESC, idFichaMedica DESC
         LIMIT 1'
    );
    $tipoSanguineoStatement->execute(['idAcolhido' => $idEdicao]);
    $tipoSanguineoAtual = $tipoSanguineoStatement->fetchColumn();
    if (!$formFichaFoiEnviado && is_string($tipoSanguineoAtual)) {
        $dadosFicha['tipoSanguineo'] = $tipoSanguineoAtual;
    }
    if ((int) $dadosFicha['idAcolhido'] === 0) {
        $dadosFicha['idAcolhido'] = $idEdicao;
    }
}
$urlFoto = static function (array $acolhido) use ($baseUrl): ?string {
    return !empty($acolhido['caminhoFoto'])
        ? appTabUrl($baseUrl . '/auth/acolhido-foto.php?id=' . (int) $acolhido['idAcolhido'])
        : null;
};
$edicaoSolicitada = $idEdicao !== false && $idEdicao > 0;
$abaInicial = (
    $formFichaFoiEnviado
    || $formAcolhidoFoiEnviado
    || $resultado === 'ficha-criada'
    || $edicaoSolicitada
    || ($podeGerenciarFicha && $idFichaEdicao && $idFichaEdicao > 0)
    || $podeGerenciarFicha
    || in_array($_GET['subaba'] ?? null, ['acolhidos', 'fichas-medicas'], true)
) ? 'manutencao' : 'pesquisa';
$subabaInicial = (
    $podeGerenciarFicha && (
        ($idFichaEdicao && $idFichaEdicao > 0)
        || $formFichaFoiEnviado
        || $resultado === 'ficha-criada'
        || (isset($_GET['subaba']) && $_GET['subaba'] === 'fichas-medicas')
    )
) ? 'fichas-medicas' : 'acolhidos';
$urlAtual = $baseUrl . '/pages/gestao-acolhidos/cadastros/acolhidos.php';
$mensagensResultado = [
    'acolhido-criado' => 'Cadastro do acolhido criado.',
    'acolhido-atualizado' => 'Cadastro do acolhido atualizado.',
    'acolhido-excluido' => 'Cadastro do acolhido excluído.',
    'ficha-criada' => 'Ficha médica registrada no histórico.',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Acolhidos | Sistema Elo</title>
    <link rel="icon" type="image/png" href="<?php echo appEscape($baseUrl . '/assets/imgs/logo-elo.png'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/tailwind-config.js'); ?>"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        [data-tag-list]:empty { display: none; }
        [data-photo-video][hidden], [data-camera-panel][hidden] { display: none; }
        [data-address-fields][hidden], [data-street-location][hidden] { display: none !important; }
        .medical-tag { overflow-wrap: anywhere; }
    </style>
</head>
<body class="flex h-screen flex-col overflow-hidden bg-gray-100 font-sans text-gray-800">
    <?php include __DIR__ . '/../../../components/gestao-acolhidos/navbar-gestaoacolhidos.php'; ?>
    <div class="flex min-h-0 flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../../components/gestao-acolhidos/sidebar-gestaoacolhidos.php'; ?>
        <main class="min-w-0 flex-1 overflow-y-auto bg-gray-50 p-4 md:p-6">
            <h1 class="mb-6 text-2xl font-bold text-gray-800">Cadastro de Acolhidos</h1>

                <?php if ($erro !== ''): ?>
                    <p role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"><?php echo appEscape($erro); ?></p>
                <?php elseif (isset($mensagensResultado[$resultado])): ?>
                    <p role="status" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800"><?php echo appEscape($mensagensResultado[$resultado]); ?></p>
                <?php endif; ?>

                <?php include __DIR__ . '/../../../components/tabs-pesquisa-manutencao.php'; ?>
                <section id="painel-manutencao" role="tabpanel" aria-labelledby="tab-manutencao" <?php echo $abaInicial === 'manutencao' ? '' : 'hidden'; ?>>
                    <div class="mb-5 flex border-b border-gray-200" role="tablist" aria-label="Manutenção de acolhidos" data-resident-tabs data-initial-tab="<?php echo appEscape($subabaInicial); ?>">
                        <button type="button" id="tab-acolhidos-manutencao" role="tab" aria-controls="painel-acolhidos-manutencao" data-resident-tab-target="acolhidos"
                                aria-selected="<?php echo $subabaInicial === 'acolhidos' ? 'true' : 'false'; ?>" tabindex="<?php echo $subabaInicial === 'acolhidos' ? '0' : '-1'; ?>"
                                class="border-b-2 px-4 py-2 text-sm font-medium transition-colors <?php echo $subabaInicial === 'acolhidos' ? 'border-elo-teal text-elo-teal' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                            Acolhidos
                        </button>
                        <?php if ($podeGerenciarFicha): ?>
                            <button type="button" id="tab-fichas-medicas" role="tab" aria-controls="painel-fichas-medicas" data-resident-tab-target="fichas-medicas"
                                    aria-selected="<?php echo $subabaInicial === 'fichas-medicas' ? 'true' : 'false'; ?>" tabindex="<?php echo $subabaInicial === 'fichas-medicas' ? '0' : '-1'; ?>"
                                    class="border-b-2 px-4 py-2 text-sm font-medium transition-colors <?php echo $subabaInicial === 'fichas-medicas' ? 'border-elo-teal text-elo-teal' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                                Fichas médicas
                            </button>
                        <?php endif; ?>
                    </div>
                    <section id="painel-acolhidos-manutencao" role="tabpanel" aria-labelledby="tab-acolhidos-manutencao" <?php echo $subabaInicial === 'acolhidos' ? '' : 'hidden'; ?>>
                    <div class="mb-5 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:p-6">
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900"><?php echo (int) $dadosAcolhido['idAcolhido'] > 0 ? 'Editar acolhido' : 'Novo acolhido'; ?></h2>
                                <?php if (!$podeGerenciarFicha): ?>
                                    <p class="mt-1 text-sm text-gray-500">Para registrar a ficha médica, salve o cadastro e depois abra o acolhido pela opção Editar na aba Pesquisa.</p>
                                <?php endif; ?>
                            </div>
                            <?php if ((int) $dadosAcolhido['idAcolhido'] > 0): ?>
                                <a href="<?php echo appEscape($urlAtual . '?subaba=acolhidos#manutencao'); ?>" class="text-sm font-medium text-elo-teal hover:underline">Novo cadastro</a>
                            <?php endif; ?>
                        </div>
                        <form method="post" enctype="multipart/form-data" class="space-y-5" data-acolhido-form>
                            <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                            <input type="hidden" name="acao" value="salvarAcolhido">
                            <input type="hidden" name="idAcolhido" value="<?php echo (int) $dadosAcolhido['idAcolhido']; ?>">

                            <fieldset class="rounded-lg border border-gray-200 p-4">
                                <legend class="px-2 text-sm font-semibold text-gray-700">Identificação</legend>
                                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                    <label class="text-sm font-medium text-gray-700 md:col-span-2">Nome completo *
                                        <input name="nomeCompleto" required maxlength="150" value="<?php echo appEscape((string) $dadosAcolhido['nomeCompleto']); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2" autocomplete="name">
                                    </label>
                                    <label class="text-sm font-medium text-gray-700">CPF
                                        <input name="cpf" inputmode="numeric" maxlength="14" value="<?php echo appEscape((string) $dadosAcolhido['cpf']); ?>" placeholder="000.000.000-00" data-acolhido-cpf class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                    </label>
                                    <label class="text-sm font-medium text-gray-700">RG
                                        <input name="rg" maxlength="30" value="<?php echo appEscape((string) $dadosAcolhido['rg']); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                    </label>
                                    <label class="text-sm font-medium text-gray-700">Data de nascimento *
                                        <input type="date" name="dataNascimento" required max="<?php echo date('Y-m-d'); ?>" value="<?php echo appEscape((string) $dadosAcolhido['dataNascimento']); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                    </label>
                                    <label class="text-sm font-medium text-gray-700">Sexo *
                                        <select name="sexo" required class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                                            <?php foreach (['Feminino', 'Masculino', 'Outro', 'Não informado'] as $sexo): ?>
                                                <option value="<?php echo appEscape($sexo); ?>" <?php echo $dadosAcolhido['sexo'] === $sexo ? 'selected' : ''; ?>><?php echo appEscape($sexo); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                            </fieldset>

                            <fieldset class="rounded-lg border border-gray-200 p-4">
                                <legend class="px-2 text-sm font-semibold text-gray-700">Filiação</legend>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700" for="nomeMae">Nome da mãe <?php echo empty($dadosAcolhido['maeDesconhecida']) ? '*' : ''; ?></label>
                                        <input id="nomeMae" name="nomeMae" maxlength="150" value="<?php echo appEscape((string) $dadosAcolhido['nomeMae']); ?>" <?php echo !empty($dadosAcolhido['maeDesconhecida']) ? 'disabled' : 'required'; ?> data-parent-name class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                        <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-600">
                                            <input type="checkbox" name="maeDesconhecida" value="1" <?php echo !empty($dadosAcolhido['maeDesconhecida']) ? 'checked' : ''; ?> data-parent-unknown class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                                            Mãe desconhecida / não consta nos documentos
                                        </label>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700" for="nomePai">Nome do pai <?php echo empty($dadosAcolhido['paiDesconhecido']) ? '*' : ''; ?></label>
                                        <input id="nomePai" name="nomePai" maxlength="150" value="<?php echo appEscape((string) $dadosAcolhido['nomePai']); ?>" <?php echo !empty($dadosAcolhido['paiDesconhecido']) ? 'disabled' : 'required'; ?> data-parent-name class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                        <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-600">
                                            <input type="checkbox" name="paiDesconhecido" value="1" <?php echo !empty($dadosAcolhido['paiDesconhecido']) ? 'checked' : ''; ?> data-parent-unknown class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                                            Pai desconhecido / não consta nos documentos
                                        </label>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="rounded-lg border border-gray-200 p-4">
                                <legend class="px-2 text-sm font-semibold text-gray-700">Endereço</legend>
                                <label class="mb-4 inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                                    <input type="checkbox" name="moradorRua" value="1" <?php echo !empty($dadosAcolhido['moradorRua']) ? 'checked' : ''; ?> data-street-resident class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                                    Pessoa em situação de rua
                                </label>
                                <div data-address-fields <?php echo !empty($dadosAcolhido['moradorRua']) ? 'hidden' : ''; ?> class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <label class="text-sm font-medium text-gray-700">CEP
                                            <span class="mt-1 flex gap-2">
                                                <input name="cep" inputmode="numeric" maxlength="9" value="<?php echo appEscape((string) $dadosAcolhido['cep']); ?>" data-acolhido-cep class="min-w-0 flex-1 rounded border border-gray-300 px-3 py-2">
                                                <button type="button" data-acolhido-cep-lookup class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded border border-elo-teal text-elo-teal transition-colors hover:bg-elo-teal/10 disabled:cursor-not-allowed disabled:opacity-50" aria-label="Buscar endereço pelo CEP" title="Buscar endereço pelo CEP">
                                                    <i class="ph ph-magnifying-glass text-lg" aria-hidden="true"></i>
                                                </button>
                                            </span>
                                        </label>
                                        <p data-acolhido-cep-message role="status" aria-live="polite" class="mt-1 text-xs text-gray-500"></p>
                                    </div>
                                    <label class="text-sm font-medium text-gray-700 lg:col-span-2">Logradouro
                                        <input name="logradouro" maxlength="150" value="<?php echo appEscape((string) $dadosAcolhido['logradouro']); ?>" data-acolhido-address="logradouro" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                    </label>
                                    <label class="text-sm font-medium text-gray-700">Número
                                        <input name="numero" maxlength="20" value="<?php echo appEscape((string) $dadosAcolhido['numero']); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                    </label>
                                    <label class="text-sm font-medium text-gray-700">Complemento
                                        <input name="complemento" maxlength="100" value="<?php echo appEscape((string) $dadosAcolhido['complemento']); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                    </label>
                                    <label class="text-sm font-medium text-gray-700">Bairro
                                        <input name="bairro" maxlength="100" value="<?php echo appEscape((string) $dadosAcolhido['bairro']); ?>" data-acolhido-address="bairro" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                    </label>
                                    <label class="text-sm font-medium text-gray-700">Cidade
                                        <input name="cidade" maxlength="100" value="<?php echo appEscape((string) $dadosAcolhido['cidade']); ?>" data-acolhido-address="cidade" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                    </label>
                                    <label class="text-sm font-medium text-gray-700">UF
                                        <input name="uf" maxlength="2" value="<?php echo appEscape((string) $dadosAcolhido['uf']); ?>" data-acolhido-address="uf" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                    </label>
                                </div>
                                <div data-street-location <?php echo empty($dadosAcolhido['moradorRua']) ? 'hidden' : ''; ?>>
                                    <label class="block text-sm font-medium text-gray-700">Local onde costuma permanecer / referência
                                        <input name="localMoradia" maxlength="255" value="<?php echo appEscape((string) $dadosAcolhido['localMoradia']); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                    </label>
                                </div>
                            </fieldset>

                            <fieldset class="rounded-lg border border-gray-200 p-4">
                                <legend class="px-2 text-sm font-semibold text-gray-700">Foto</legend>
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                                    <div class="flex h-32 w-32 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-gray-200 bg-gray-50" data-photo-preview>
                                        <?php $fotoUrl = $urlFoto($dadosAcolhido); ?>
                                        <?php if ($fotoUrl !== null): ?>
                                            <img src="<?php echo appEscape($fotoUrl); ?>" alt="Foto de <?php echo appEscape((string) $dadosAcolhido['nomeCompleto']); ?>" class="h-full w-full object-cover" data-photo-image>
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
                                            <?php if (!empty($dadosAcolhido['caminhoFoto'])): ?>
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
                                        <button type="button" data-camera-capture class="rounded bg-elo-teal px-3 py-2 text-sm font-medium text-white">Capturar foto</button>
                                        <button type="button" data-camera-close class="rounded border border-gray-300 px-3 py-2 text-sm">Fechar câmera</button>
                                    </div>
                                </div>
                            </fieldset>

                            <?php if ((int) ($dadosAcolhido['idAcolhido'] ?? 0) > 0): ?>
                                <div class="grid gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm md:grid-cols-2">
                                    <label class="font-medium text-gray-700">Criado por
                                        <input type="text" value="<?php echo appEscape($formatarAuditoria($dadosAcolhido['criadoPorId'] ?? null, $dadosAcolhido['criadoPor'] ?? null, $dadosAcolhido['criadoEm'] ?? null)); ?>" disabled class="mt-1 w-full cursor-not-allowed rounded border border-gray-200 bg-white px-3 py-2 text-gray-600">
                                    </label>
                                    <label class="font-medium text-gray-700">Alterado por
                                        <input type="text" value="<?php echo appEscape($formatarAuditoria($dadosAcolhido['alteradoPorId'] ?? null, $dadosAcolhido['alteradoPor'] ?? null, $dadosAcolhido['atualizadoEm'] ?? null)); ?>" disabled class="mt-1 w-full cursor-not-allowed rounded border border-gray-200 bg-white px-3 py-2 text-gray-600">
                                    </label>
                                </div>
                            <?php endif; ?>

                            <div class="flex flex-wrap justify-end gap-2">
                                <a href="<?php echo appEscape($urlAtual . '?subaba=acolhidos#manutencao'); ?>" class="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Limpar</a>
                                <button type="submit" class="inline-flex items-center gap-2 rounded bg-elo-teal px-5 py-2 text-sm font-semibold text-white hover:bg-elo-green">
                                    <i class="ph ph-floppy-disk text-lg" aria-hidden="true"></i> Salvar acolhido
                                </button>
                            </div>
                        </form>
                    </div>

                    </section>

                    <?php if ($podeGerenciarFicha): ?>
                    <section id="painel-fichas-medicas" role="tabpanel" aria-labelledby="tab-fichas-medicas" <?php echo $subabaInicial === 'fichas-medicas' ? '' : 'hidden'; ?>>
                    <div class="mb-5 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:p-6">
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900"><?php echo (int) $dadosFicha['idFichaMedica'] > 0 ? 'Registrar atualização da ficha' : 'Registrar ficha médica'; ?></h2>
                            </div>
                            <?php if ((int) $dadosFicha['idFichaMedica'] > 0): ?>
                                <a href="<?php echo appEscape($urlAtual . '?editar=' . $idEdicao . '&subaba=fichas-medicas#manutencao'); ?>" class="text-sm font-medium text-elo-teal hover:underline">Nova ficha</a>
                            <?php endif; ?>
                        </div>
                        <form method="post" action="<?php echo appEscape(appTabUrl($urlAtual . '?editar=' . $idEdicao . '&subaba=fichas-medicas#manutencao')); ?>" class="space-y-5" data-medical-form>
                            <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                            <input type="hidden" name="acao" value="salvarFicha">
                            <input type="hidden" name="idFichaModelo" value="<?php echo (int) $dadosFicha['idFichaMedica']; ?>">
                            <input type="hidden" name="idAcolhido" value="<?php echo $idEdicao; ?>">
                            <div class="grid gap-4 md:grid-cols-3">
                                <label class="text-sm font-medium text-gray-700 md:col-span-2">Acolhido
                                    <input value="<?php echo appEscape((string) $dadosAcolhido['nomeCompleto']); ?>" readonly class="mt-1 w-full cursor-not-allowed rounded border border-gray-200 bg-gray-100 px-3 py-2 text-gray-600">
                                </label>
                                <label class="text-sm font-medium text-gray-700">Data do registro *
                                    <input type="date" name="dataRegistro" required max="<?php echo date('Y-m-d'); ?>" value="<?php echo appEscape((string) $dadosFicha['dataRegistro']); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                </label>
                                <label class="text-sm font-medium text-gray-700 md:col-span-1">Tipo sanguíneo
                                    <select name="tipoSanguineo" class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                                        <?php foreach (['Não informado', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $tipo): ?>
                                            <option value="<?php echo appEscape($tipo); ?>" <?php echo $dadosFicha['tipoSanguineo'] === $tipo ? 'selected' : ''; ?>><?php echo appEscape($tipo); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="mt-1 block text-xs font-normal text-gray-500">O tipo sanguíneo é compartilhado por todas as fichas deste acolhido.</span>
                                </label>
                            </div>
                            <?php
                            $camposTags = [
                                'alergias' => ['label' => 'Alergias', 'help' => 'Digite um item e pressione Tab, Enter ou vírgula.'],
                                'dependencias' => ['label' => 'Dependências', 'help' => 'Digite um item e pressione Tab, Enter ou vírgula.'],
                                'relevanciasClinicas' => ['label' => 'Relevâncias clínicas', 'help' => 'Digite um item e pressione Tab, Enter ou vírgula.'],
                            ];
                            foreach ($camposTags as $campo => $configuracao):
                                $jsonTags = json_encode($dadosFicha[$campo] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
                            ?>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700"><?php echo appEscape($configuracao['label']); ?></label>
                                    <div data-tag-widget class="mt-1 flex min-h-11 flex-wrap items-center gap-2 rounded border border-gray-300 bg-white p-2 focus-within:border-elo-teal focus-within:ring-1 focus-within:ring-elo-teal">
                                        <div data-tag-list class="contents"></div>
                                        <input type="text" data-tag-entry maxlength="120" placeholder="Adicionar item..." class="min-w-40 flex-1 border-0 p-1 text-sm outline-none focus:ring-0">
                                        <input type="hidden" name="<?php echo appEscape($campo); ?>Json" value="<?php echo appEscape($jsonTags ?: '[]'); ?>" data-tag-value>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500"><?php echo appEscape($configuracao['help']); ?></p>
                                </div>
                            <?php endforeach; ?>
                            <div class="flex justify-end">
                                <button type="submit" class="inline-flex items-center gap-2 rounded bg-elo-teal px-5 py-2 text-sm font-semibold text-white hover:bg-elo-green">
                                    <i class="ph ph-floppy-disk text-lg" aria-hidden="true"></i> Registrar ficha médica
                                </button>
                            </div>
                        </form>
                    </div>

                    <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:p-6">
                        <div class="mb-4">
                            <h2 class="text-lg font-semibold text-gray-900">Histórico de fichas médicas</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1100px] border-collapse text-left text-sm">
                                <thead>
                                    <tr class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                        <th class="border-y border-gray-200 px-3 py-3">Registro</th>
                                        <th class="border-y border-gray-200 px-3 py-3">Acolhido</th>
                                        <th class="border-y border-gray-200 px-3 py-3">Tipo sanguíneo</th>
                                        <th class="border-y border-gray-200 px-3 py-3">Alergias</th>
                                        <th class="border-y border-gray-200 px-3 py-3">Dependências</th>
                                        <th class="border-y border-gray-200 px-3 py-3">Auditoria</th>
                                        <th class="border-y border-gray-200 px-3 py-3 text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($fichas as $ficha): ?>
                                        <?php
                                        $tagsResumo = [];
                                        foreach (['alergias', 'dependencias', 'relevanciasClinicas'] as $campo) {
                                            try {
                                                $lidos = json_decode($ficha[$campo], true, 512, JSON_THROW_ON_ERROR);
                                                if (is_array($lidos)) {
                                                    $tagsResumo[$campo] = implode(', ', $lidos);
                                                } else {
                                                    $tagsResumo[$campo] = '';
                                                }
                                            } catch (JsonException) {
                                                $tagsResumo[$campo] = '';
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td class="whitespace-nowrap px-3 py-3"><span class="font-semibold text-gray-800">#<?php echo (int) $ficha['idFichaMedica']; ?></span><span class="ml-2 text-gray-500"><?php echo appEscape(date('d/m/Y', strtotime($ficha['dataRegistro']))); ?></span></td>
                                            <td class="px-3 py-3"><span class="font-medium text-gray-800"><?php echo appEscape($ficha['nomeCompleto']); ?></span><span class="block text-xs text-gray-500"><?php echo (int) $ficha['idAcolhido']; ?></span></td>
                                            <td class="px-3 py-3 text-gray-600"><?php echo appEscape($ficha['tipoSanguineo']); ?></td>
                                            <td class="max-w-48 px-3 py-3 text-gray-600"><?php echo appEscape($tagsResumo['alergias'] ?: '—'); ?></td>
                                            <td class="max-w-48 px-3 py-3 text-gray-600"><?php echo appEscape($tagsResumo['dependencias'] ?: '—'); ?></td>
                                            <td class="min-w-72 px-3 py-3 text-xs text-gray-600">
                                                <p><span class="font-medium text-gray-700">Criado:</span> <?php echo appEscape($formatarAuditoria($ficha['criadoPorId'] ?? null, $ficha['criadoPor'] ?? null, $ficha['criadoEm'] ?? null)); ?></p>
                                                <p class="mt-1"><span class="font-medium text-gray-700">Alterado:</span> <?php echo appEscape($formatarAuditoria($ficha['alteradoPorId'] ?? null, $ficha['alteradoPor'] ?? null, $ficha['atualizadoEm'] ?? null)); ?></p>
                                            </td>
                                            <td class="px-3 py-3">
                                                <div class="flex justify-end gap-2">
                                                    <button type="button" data-medical-details data-name="<?php echo appEscape($ficha['nomeCompleto']); ?>" data-date="<?php echo appEscape(date('d/m/Y', strtotime($ficha['dataRegistro']))); ?>" data-blood="<?php echo appEscape($ficha['tipoSanguineo']); ?>" data-allergies="<?php echo appEscape($tagsResumo['alergias']); ?>" data-dependencies="<?php echo appEscape($tagsResumo['dependencias']); ?>" data-relevance="<?php echo appEscape($tagsResumo['relevanciasClinicas']); ?>" class="inline-flex h-9 w-9 items-center justify-center rounded border border-gray-200 text-indigo-600 hover:bg-indigo-50" aria-label="Ver ficha médica"><i class="ph ph-eye text-lg" aria-hidden="true"></i></button>
                                                    <a href="<?php echo appEscape($urlAtual . '?editar=' . $idEdicao . '&editarFicha=' . (int) $ficha['idFichaMedica'] . '&subaba=fichas-medicas#manutencao'); ?>" class="inline-flex h-9 w-9 items-center justify-center rounded border border-gray-200 text-elo-teal hover:bg-elo-teal/5" aria-label="Registrar atualização baseada nesta ficha"><i class="ph ph-arrow-clockwise text-lg" aria-hidden="true"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (!$fichas): ?>
                                        <tr><td colspan="7" class="px-3 py-10 text-center text-gray-500">Nenhuma ficha médica registrada.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <?php endif; ?>
                </section>
                </section>
                <section id="painel-pesquisa" role="tabpanel" aria-labelledby="tab-pesquisa" <?php echo $abaInicial === 'pesquisa' ? '' : 'hidden'; ?>>
                    <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                            <h2 class="text-lg font-semibold">Pessoas cadastradas</h2>
                            <div class="flex flex-wrap gap-2">
                                <input data-filter-input="listaAcolhidos" type="search" value="<?php echo appEscape($pesquisa); ?>" placeholder="Pesquisar..." aria-label="Pesquisar acolhidos" class="rounded border border-gray-300 px-3 py-2 text-sm">
                                <a href="<?php echo appEscape(appTabUrl($urlAtual . '?subaba=acolhidos#manutencao')); ?>" class="rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">+ Novo cadastro</a>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[900px] text-left text-sm">
                                <thead>
                                    <tr class="border-b bg-gray-50 text-gray-600">
                                        <th class="p-3">Acolhido</th>
                                        <th class="p-3">Nome da mãe</th>
                                        <th class="p-3">Nascimento</th>
                                        <th class="p-3">Cidade/UF</th>
                                        <th class="p-3">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="listaAcolhidos">
                                    <?php foreach ($acolhidos as $acolhido): ?>
                                        <?php $fotoListaUrl = $urlFoto($acolhido); ?>
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="p-3 font-medium text-gray-800">
                                                <div class="flex items-center gap-3">
                                                    <?php if ($fotoListaUrl !== null): ?>
                                                        <img src="<?php echo appEscape($fotoListaUrl); ?>" alt="" class="h-10 w-10 rounded-full object-cover">
                                                    <?php else: ?>
                                                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-400"><i class="ph ph-user text-xl" aria-hidden="true"></i></span>
                                                    <?php endif; ?>
                                                    <div><p><?php echo (int) $acolhido['idAcolhido']; ?> — <?php echo appEscape($acolhido['nomeCompleto']); ?></p><p class="text-xs font-normal text-gray-500"></p></div>
                                                </div>
                                            </td>
                                            <td class="p-3"><?php echo appEscape((string) ($acolhido['nomeMae'] ?: '—')); ?></td>
                                            <td class="whitespace-nowrap p-3"><?php echo appEscape(date('d/m/Y', strtotime((string) $acolhido['dataNascimento']))); ?></td>
                                            <td class="p-3"><?php echo appEscape(trim((string) ($acolhido['cidade'] ?? '') . (!empty($acolhido['uf']) ? '/' . $acolhido['uf'] : ''), '/') ?: '—'); ?></td>
                                            <td class="whitespace-nowrap p-3"><div class="flex items-center gap-3">
                                                    <a href="<?php echo appEscape(appTabUrl($urlAtual . '?editar=' . (int) $acolhido['idAcolhido'] . '&subaba=acolhidos#manutencao')); ?>" class="inline-flex h-9 w-9 items-center justify-center rounded border border-gray-200 text-elo-teal hover:bg-elo-teal/5" aria-label="Editar acolhido"><i class="ph ph-pencil-simple text-lg" aria-hidden="true"></i></a>
                                                    <form method="post" data-confirm="Excluir este cadastro? A exclusão será bloqueada se houver fichas médicas vinculadas.">
                                                        <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                                                        <input type="hidden" name="acao" value="excluirAcolhido">
                                                        <input type="hidden" name="idAcolhido" value="<?php echo (int) $acolhido['idAcolhido']; ?>">
                                                        <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded border border-red-200 text-red-600 hover:bg-red-50" aria-label="Excluir acolhido"><i class="ph ph-trash text-lg" aria-hidden="true"></i></button>
                                                    </form>
                                            </div></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (!$acolhidos): ?>
                                        <tr><td colspan="5" class="px-3 py-10 text-center text-gray-500"><?php echo $pesquisa !== '' ? 'Nenhum acolhido encontrado.' : 'Ainda não há acolhidos cadastrados.'; ?></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </section>
        </main>
    </div>

    <div data-medical-modal hidden class="fixed inset-0 z-50 items-center justify-center bg-black/50 p-4">
        <section role="dialog" aria-modal="true" aria-labelledby="medical-modal-title" class="w-full max-w-xl rounded-xl bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div><h2 id="medical-modal-title" class="text-lg font-bold text-gray-900">Ficha médica</h2><p data-modal-subtitle class="mt-1 text-sm text-gray-500"></p></div>
                <button type="button" data-medical-modal-close class="rounded p-1 text-gray-500 hover:bg-gray-100" aria-label="Fechar"><i class="ph ph-x text-xl" aria-hidden="true"></i></button>
            </div>
            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs font-semibold uppercase text-gray-500">Tipo sanguíneo</dt><dd data-modal-blood class="mt-1 text-sm text-gray-800"></dd></div>
                <div><dt class="text-xs font-semibold uppercase text-gray-500">Alergias</dt><dd data-modal-allergies class="mt-1 text-sm text-gray-800"></dd></div>
                <div><dt class="text-xs font-semibold uppercase text-gray-500">Dependências</dt><dd data-modal-dependencies class="mt-1 text-sm text-gray-800"></dd></div>
                <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase text-gray-500">Relevâncias clínicas</dt><dd data-modal-relevance class="mt-1 whitespace-pre-wrap text-sm text-gray-800"></dd></div>
            </dl>
        </section>
    </div>

    <script>
        (() => {
            document.querySelectorAll('[data-resident-tabs]').forEach((tabs) => {
                const buttons = Array.from(tabs.querySelectorAll('[data-resident-tab-target]'));
                const panels = buttons
                    .map((button) => document.getElementById(button.getAttribute('aria-controls')))
                    .filter((panel) => panel !== null);
                const activate = (name) => {
                    const selected = buttons.find((button) => button.dataset.residentTabTarget === name)
                        || buttons[0];
                    buttons.forEach((button) => {
                        const active = button === selected;
                        button.setAttribute('aria-selected', String(active));
                        button.tabIndex = active ? 0 : -1;
                        button.classList.toggle('border-elo-teal', active);
                        button.classList.toggle('text-elo-teal', active);
                        button.classList.toggle('border-transparent', !active);
                        button.classList.toggle('text-gray-500', !active);
                        button.classList.toggle('hover:text-gray-700', !active);
                    });
                    panels.forEach((panel) => {
                        panel.hidden = panel.id !== selected.getAttribute('aria-controls');
                    });
                };
                buttons.forEach((button, index) => {
                    button.addEventListener('click', () => activate(button.dataset.residentTabTarget));
                    button.addEventListener('keydown', (event) => {
                        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
                        event.preventDefault();
                        const direction = event.key === 'ArrowRight' ? 1 : -1;
                        const next = buttons[(index + direction + buttons.length) % buttons.length];
                        activate(next.dataset.residentTabTarget);
                        next.focus();
                    });
                });
                activate(tabs.dataset.initialTab);
            });

            document.querySelectorAll('[data-parent-unknown]').forEach((checkbox) => {
                const field = checkbox.closest('div').querySelector('[data-parent-name]');
                const update = () => {
                    field.disabled = checkbox.checked;
                    field.required = !checkbox.checked;
                    if (checkbox.checked) field.value = '';
                };
                checkbox.addEventListener('change', update);
            });

            const cepInput = document.querySelector('[data-acolhido-cep]');
            const cepButton = document.querySelector('[data-acolhido-cep-lookup]');
            const cepMessage = document.querySelector('[data-acolhido-cep-message]');
            const addressInputs = Array.from(document.querySelectorAll('[data-acolhido-address]'));
            if (cepInput && cepButton && cepMessage && addressInputs.length) {
                const digitsFromCep = () => cepInput.value.replace(/\D/g, '');
                const setCepMessage = (message, kind = 'info') => {
                    cepMessage.textContent = message;
                    cepMessage.classList.toggle('text-red-600', kind === 'error');
                    cepMessage.classList.toggle('text-green-700', kind === 'success');
                    cepMessage.classList.toggle('text-gray-500', kind === 'info');
                };
                const unlockAddress = () => {
                    addressInputs.forEach((input) => {
                        if (input.readOnly) input.value = '';
                        input.readOnly = false;
                        input.removeAttribute('aria-readonly');
                        input.classList.remove('bg-gray-100', 'text-gray-600');
                    });
                };
                let previousCep = digitsFromCep();
                let loadedCep = '';
                let requestSequence = 0;
                let requestController = null;
                const formatCep = () => {
                    const digits = digitsFromCep().slice(0, 8);
                    cepInput.value = digits.length > 5 ? `${digits.slice(0, 5)}-${digits.slice(5)}` : digits;
                    cepInput.setCustomValidity(!digits || (/^\d{8}$/.test(digits) && digits !== '00000000')
                        ? ''
                        : 'Informe um CEP válido com 8 dígitos.');
                };
                const lookupCep = async () => {
                    const cep = digitsFromCep();
                    formatCep();
                    if (!/^\d{8}$/.test(cep) || cep === '00000000') {
                        cepInput.reportValidity();
                        return;
                    }
                    if (cep === loadedCep) return;
                    requestController?.abort();
                    requestController = new AbortController();
                    const controller = requestController;
                    const sequence = ++requestSequence;
                    cepButton.disabled = true;
                    setCepMessage('Consultando CEP...');
                    try {
                        const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`, {
                            headers: { Accept: 'application/json' },
                            signal: controller.signal
                        });
                        if (!response.ok) throw new Error('O serviço de CEP está indisponível. Tente novamente.');
                        const address = await response.json();
                        if (sequence !== requestSequence || digitsFromCep() !== cep) return;
                        if (!address || address.erro === true) {
                            throw new Error('CEP não encontrado. Confira o número e tente novamente.');
                        }
                        if (typeof address.cep !== 'string'
                            || address.cep.replace(/\D/g, '') !== cep
                            || typeof address.localidade !== 'string'
                            || typeof address.uf !== 'string'
                            || (address.logradouro !== undefined && typeof address.logradouro !== 'string')
                            || (address.bairro !== undefined && typeof address.bairro !== 'string')) {
                            throw new Error('O serviço de CEP retornou um endereço inválido.');
                        }
                        const values = {
                            logradouro: address.logradouro || '',
                            bairro: address.bairro || '',
                            cidade: address.localidade.trim(),
                            uf: address.uf.trim().toUpperCase()
                        };
                        if (!values.cidade || !/^[A-Z]{2}$/.test(values.uf)) {
                            throw new Error('O serviço de CEP retornou um endereço incompleto.');
                        }
                        addressInputs.forEach((input) => {
                            input.value = values[input.dataset.acolhidoAddress];
                            if (input.value) {
                                input.readOnly = true;
                                input.setAttribute('aria-readonly', 'true');
                                input.classList.add('bg-gray-100', 'text-gray-600');
                            }
                        });
                        loadedCep = cep;
                        setCepMessage('Endereço preenchido. Altere o CEP para liberar os campos.', 'success');
                    } catch (error) {
                        if (error.name !== 'AbortError' && sequence === requestSequence) {
                            setCepMessage(error.message || 'Falha ao consultar o CEP. Tente novamente.', 'error');
                        }
                    } finally {
                        if (sequence === requestSequence) {
                            cepButton.disabled = false;
                            requestController = null;
                        }
                    }
                };
                cepInput.addEventListener('input', () => {
                    formatCep();
                    const cep = digitsFromCep();
                    if (cep !== previousCep) {
                        if (requestController) {
                            requestController.abort();
                            requestController = null;
                            cepButton.disabled = false;
                        }
                        requestSequence++;
                        unlockAddress();
                        loadedCep = '';
                        setCepMessage('CEP alterado. Consulte para preencher o endereço.');
                        previousCep = cep;
                    }
                });
                cepButton.addEventListener('click', lookupCep);
                formatCep();
            }

            const streetResident = document.querySelector('[data-street-resident]');
            const addressFields = document.querySelector('[data-address-fields]');
            const streetLocation = document.querySelector('[data-street-location]');
            streetResident?.addEventListener('change', () => {
                const isStreetResident = streetResident.checked;
                addressFields.hidden = isStreetResident;
                streetLocation.hidden = !isStreetResident;
                if (isStreetResident) {
                    addressFields.querySelectorAll('input').forEach((input) => {
                        input.value = '';
                        if (input.matches('[data-acolhido-cep]')) {
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                        input.readOnly = false;
                        input.removeAttribute('aria-readonly');
                        input.classList.remove('bg-gray-100', 'text-gray-600');
                    });
                } else {
                    streetLocation.querySelector('input').value = '';
                }
            });

            const cpf = document.querySelector('[data-acolhido-cpf]');
            cpf?.addEventListener('input', () => {
                const digits = cpf.value.replace(/\D/g, '').slice(0, 11);
                cpf.value = digits.replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            });

            document.querySelectorAll('[data-tag-widget]').forEach((widget) => {
                const entry = widget.querySelector('[data-tag-entry]');
                const hidden = widget.querySelector('[data-tag-value]');
                const list = widget.querySelector('[data-tag-list]');
                let tags = [];
                try {
                    const parsed = JSON.parse(hidden.value);
                    tags = Array.isArray(parsed)
                        ? parsed.filter((tag) => typeof tag === 'string').map((tag) => tag.toLocaleUpperCase('pt-BR'))
                        : [];
                } catch (error) {
                    console.error('Não foi possível carregar as tags médicas:', error);
                }
                const sync = () => {
                    hidden.value = JSON.stringify(tags);
                    list.replaceChildren(...tags.map((tag, index) => {
                        const chip = document.createElement('span');
                        chip.className = 'medical-tag inline-flex items-center gap-1 rounded-full bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-800';
                        const text = document.createElement('span');
                        text.textContent = tag;
                        const remove = document.createElement('button');
                        remove.type = 'button';
                        remove.className = 'rounded-full text-teal-700 hover:text-red-600';
                        remove.setAttribute('aria-label', `Remover ${tag}`);
                        remove.textContent = '×';
                        remove.addEventListener('click', () => {
                            tags.splice(index, 1);
                            sync();
                        });
                        chip.append(text, remove);
                        return chip;
                    }));
                };
                const add = () => {
                    const value = entry.value.toLocaleUpperCase('pt-BR').trim().replace(/,+$/g, '').trim();
                    if (!value) return false;
                    if (value.length > 120) {
                        entry.setCustomValidity('Cada item deve ter até 120 caracteres.');
                        entry.reportValidity();
                        entry.setCustomValidity('');
                        return false;
                    }
                    if (!tags.includes(value)) {
                        if (tags.length >= 50) {
                            entry.setCustomValidity('Cada campo aceita até 50 tags.');
                            entry.reportValidity();
                            entry.setCustomValidity('');
                            return false;
                        }
                        tags.push(value);
                    }
                    entry.value = '';
                    sync();
                    return true;
                };
                entry.addEventListener('input', () => {
                    const cursor = entry.selectionStart;
                    entry.value = entry.value.toLocaleUpperCase('pt-BR');
                    entry.setSelectionRange(cursor, cursor);
                });
                entry.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ',') {
                        event.preventDefault();
                        add();
                    } else if (event.key === 'Tab' && entry.value.trim() !== '') {
                        if (add()) event.preventDefault();
                    } else if (event.key === 'Backspace' && entry.value === '' && tags.length) {
                        tags.pop();
                        sync();
                    }
                });
                entry.addEventListener('blur', add);
                sync();
            });

            const photoInput = document.querySelector('[data-photo-input]');
            const photoPreview = document.querySelector('[data-photo-preview]');
            const photoMessage = document.querySelector('[data-photo-message]');
            const removePhoto = document.querySelector('[data-photo-remove]');
            const setPhotoPreview = (source) => {
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
                    photoMessage.textContent = 'Escolha uma foto JPEG, PNG ou WebP de até 5 MB.';
                    return;
                }
                removePhoto && (removePhoto.checked = false);
                photoMessage.textContent = file.name;
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
                video.srcObject = null;
                cameraPanel.hidden = true;
                cameraPanel.classList.add('hidden');
            };
            openCamera?.addEventListener('click', async () => {
                if (!navigator.mediaDevices?.getUserMedia) {
                    photoMessage.textContent = 'A câmera não está disponível neste navegador ou a página não está em HTTPS. Use “Escolher foto”.';
                    return;
                }
                try {
                    cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                    video.srcObject = cameraStream;
                    cameraPanel.hidden = false;
                    cameraPanel.classList.remove('hidden');
                    photoMessage.textContent = '';
                } catch (error) {
                    console.error('Falha ao acessar a câmera:', error);
                    photoMessage.textContent = 'Não foi possível acessar a câmera. Verifique a permissão do navegador ou escolha uma foto.';
                }
            });
            document.querySelector('[data-camera-close]')?.addEventListener('click', stopCamera);
            document.querySelector('[data-camera-capture]')?.addEventListener('click', () => {
                if (!video.videoWidth || !video.videoHeight || !cameraStream) {
                    photoMessage.textContent = 'A câmera ainda não está pronta para capturar.';
                    return;
                }
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0);
                canvas.toBlob((blob) => {
                    if (!blob) {
                        photoMessage.textContent = 'Não foi possível capturar a foto. Tente novamente.';
                        return;
                    }
                    const file = new File([blob], 'foto-acolhido.jpg', { type: 'image/jpeg' });
                    const transfer = new DataTransfer();
                    transfer.items.add(file);
                    photoInput.files = transfer.files;
                    photoInput.dispatchEvent(new Event('change', { bubbles: true }));
                    stopCamera();
                }, 'image/jpeg', 0.9);
            });
            window.addEventListener('pagehide', stopCamera, { once: true });

            const detailsModal = document.querySelector('[data-medical-modal]');
            const setDetail = (selector, value) => {
                detailsModal.querySelector(selector).textContent = value || 'Não informado';
            };
            document.querySelectorAll('[data-medical-details]').forEach((button) => {
                button.addEventListener('click', () => {
                    detailsModal.querySelector('[data-modal-subtitle]').textContent = `${button.dataset.name} · ${button.dataset.date}`;
                    setDetail('[data-modal-blood]', button.dataset.blood);
                    setDetail('[data-modal-allergies]', button.dataset.allergies);
                    setDetail('[data-modal-dependencies]', button.dataset.dependencies);
                    setDetail('[data-modal-relevance]', button.dataset.relevance);
                    detailsModal.hidden = false;
                    detailsModal.classList.remove('hidden');
                    detailsModal.classList.add('flex');
                });
            });
            const closeDetails = () => {
                detailsModal.hidden = true;
                detailsModal.classList.add('hidden');
                detailsModal.classList.remove('flex');
            };
            detailsModal.querySelector('[data-medical-modal-close]').addEventListener('click', closeDetails);
            detailsModal.addEventListener('click', (event) => {
                if (event.target === detailsModal) closeDetails();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !detailsModal.hidden) closeDetails();
            });
        })();
    </script>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
</body>
</html>
