<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('gestao-acolhidos.movimentacoes/acolhimento');
$pdo = appPdo();
$baseUrl = appBaseUrl();
$urlAtual = $baseUrl . '/pages/gestao-acolhidos/movimentacoes/acolhimento.php';
$erro = '';
$resultado = is_scalar($_GET['resultado'] ?? null) ? (string) $_GET['resultado'] : '';
$idEdicaoInput = $_GET['editar'] ?? null;
$idEdicao = is_scalar($idEdicaoInput) ? filter_var($idEdicaoInput, FILTER_VALIDATE_INT) : false;
$formEnviado = false;
$dados = [
    'idAcolhimento' => 0, 'idAcolhido' => '', 'dataEntrada' => date('Y-m-d'),
    'dataSaida' => '', 'motivoEntrada' => '', 'motivoSaida' => '', 'inventario' => [],
    'responsavelJudicial' => '', 'parentescoResponsavel' => '', 'documentoResponsavel' => '',
    'telefoneResponsavel' => '', 'processoJudicial' => '', 'observacoesGerais' => '',
];
$auditoria = [
    'criadoPorId' => null,
    'criadoPor' => null,
    'criadoEm' => null,
    'alteradoPorId' => null, 'alteradoPor' => null, 'atualizadoEm' => null,
];

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
    return $timestamp === false
        ? '—'
        : (int) $idUsuario . ' - ' . (string) $nomeUsuario . ' - ' . date('d/m/Y H:i', $timestamp);
};
$lerInventario = static function ($valor): array {
    if (!is_string($valor) || $valor === '') {
        return [];
    }
    try {
        $itens = json_decode($valor, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        error_log('Inventário inválido no acolhimento: ' . $exception->getMessage());
        return [];
    }
    return is_array($itens) ? $itens : [];
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    $acaoInput = $_POST['acao'] ?? '';
    $acao = is_scalar($acaoInput) ? (string) $acaoInput : '';

    if ($acao === 'buscarAcolhidos') {
        header('Content-Type: application/json; charset=UTF-8');
        $busca = is_scalar($_POST['busca'] ?? null) ? trim((string) $_POST['busca']) : '';
        if (mb_strlen($busca, 'UTF-8') > 100) {
            http_response_code(400);
            echo json_encode(['message' => 'A pesquisa deve ter até 100 caracteres.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $buscarPorId = ctype_digit($busca);
            $termo = '%' . $busca . '%';
            $prefixoId = $buscarPorId ? $busca . '%' : null;
            $condicaoId = $buscarPorId ? ' OR idAcolhido LIKE ?' : '';
            $ordemId = $buscarPorId ? 'idAcolhido LIKE ? DESC, ' : '';
            $statement = $pdo->prepare(
                'SELECT idAcolhido, nomeCompleto, dataNascimento
                 FROM tbAcolhido
                 WHERE nomeCompleto LIKE ?' . $condicaoId . '
                 ORDER BY ' . $ordemId . 'nomeCompleto
                 LIMIT 50'
            );
            $parametros = [$termo];
            if ($prefixoId !== null) {
                $parametros[] = $prefixoId;
                $parametros[] = $prefixoId;
            }
            $statement->execute($parametros);
            $itens = [];
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $acolhido) {
                $itens[] = [
                    'id' => (int) $acolhido['idAcolhido'],
                    'label' => (int) $acolhido['idAcolhido'] . ' — ' . $acolhido['nomeCompleto']
                        . ' — ' . date('d/m/Y', strtotime((string) $acolhido['dataNascimento'])),
                ];
            }
            echo json_encode(['items' => $itens], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (PDOException $exception) {
            http_response_code(500);
            error_log('Falha ao pesquisar acolhidos para o formulário: ' . $exception->getMessage());
            echo json_encode(['message' => 'Não foi possível pesquisar os acolhidos.'], JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            http_response_code(500);
            error_log('Falha ao preparar opções de acolhidos: ' . $exception->getMessage());
            echo json_encode(['message' => 'Não foi possível exibir os acolhidos encontrados.'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    try {
        if ($acao === 'salvar') {
            $formEnviado = true;
            $idInput = $textoPost('idAcolhimento');
            $idAcolhimento = $idInput === '' ? 0 : filter_var($idInput, FILTER_VALIDATE_INT);
            $idAcolhidoInput = filter_var($textoPost('idAcolhido'), FILTER_VALIDATE_INT);
            $dataEntrada = $textoPost('dataEntrada');
            $dataSaidaInput = $textoPost('dataSaida');
            $dataSaida = $dataSaidaInput === '' ? null : $dataSaidaInput;
            $motivoEntrada = $textoPost('motivoEntrada');
            $motivoSaidaInput = $textoPost('motivoSaida');
            $motivoSaida = $dataSaida === null ? null : $motivoSaidaInput;
            $inventarioPostado = $_POST['inventario'] ?? [];
            $inventario = [];

            if (is_array($inventarioPostado)) {
                if (count($inventarioPostado) > 100) {
                    throw new InvalidArgumentException('O inventário pode conter até 100 itens.');
                }
                foreach ($inventarioPostado as $itemPostado) {
                    if (!is_array($itemPostado)) {
                        throw new InvalidArgumentException('Confira os itens informados no inventário.');
                    }
                    $item = is_scalar($itemPostado['item'] ?? null) ? trim((string) $itemPostado['item']) : '';
                    $quantidadeInput = is_scalar($itemPostado['quantidade'] ?? null)
                        ? trim((string) $itemPostado['quantidade']) : '';
                    $observacaoItem = is_scalar($itemPostado['observacao'] ?? null)
                        ? trim((string) $itemPostado['observacao']) : '';
                    if ($item === '' && $quantidadeInput === '' && $observacaoItem === '') {
                        continue;
                    }
                    $quantidade = $quantidadeInput === '' ? 1 : filter_var($quantidadeInput, FILTER_VALIDATE_INT);
                    if ($item === '' || mb_strlen($item, 'UTF-8') > 150
                        || $quantidade === false || $quantidade < 1 || $quantidade > 9999
                        || mb_strlen($observacaoItem, 'UTF-8') > 255) {
                        throw new InvalidArgumentException('Confira a descrição, a quantidade e a observação de cada item do inventário.');
                    }
                    $inventario[] = ['item' => $item, 'quantidade' => $quantidade, 'observacao' => $observacaoItem];
                }
            } elseif ($inventarioPostado !== []) {
                throw new InvalidArgumentException('Confira os itens informados no inventário.');
            }

            $dados = [
                'idAcolhimento' => $idAcolhimento !== false ? (int) $idAcolhimento : 0,
                'idAcolhido' => $idAcolhidoInput !== false ? (int) $idAcolhidoInput : '',
                'dataEntrada' => $dataEntrada, 'dataSaida' => $dataSaida ?? '',
                'motivoEntrada' => $motivoEntrada, 'motivoSaida' => $motivoSaida ?? '',
                'inventario' => $inventario,
                'responsavelJudicial' => $textoPost('responsavelJudicial'),
                'parentescoResponsavel' => $textoPost('parentescoResponsavel'),
                'documentoResponsavel' => $textoPost('documentoResponsavel'),
                'telefoneResponsavel' => $textoPost('telefoneResponsavel'),
                'processoJudicial' => $textoPost('processoJudicial'),
                'observacoesGerais' => $textoPost('observacoesGerais'),
            ];
            if ($idAcolhimento === false || $idAcolhimento < 0
                || $idAcolhidoInput === false || $idAcolhidoInput < 1
                || ($idEdicao !== false && $idEdicao > 0 && $idAcolhimento !== $idEdicao)
                || ($idEdicao !== false && $idEdicao > 0 && $idAcolhimento === 0)
                || !$validarData($dataEntrada)
                || ($dataSaida !== null && (!$validarData($dataSaida) || $dataSaida < $dataEntrada))
                || $motivoEntrada === '' || mb_strlen($motivoEntrada, 'UTF-8') > 500
                || ($dataSaida !== null && ($motivoSaida === '' || mb_strlen($motivoSaida, 'UTF-8') > 500))
                || ($dataSaida === null && $motivoSaidaInput !== '')
                || mb_strlen($dados['responsavelJudicial'], 'UTF-8') > 150
                || mb_strlen($dados['parentescoResponsavel'], 'UTF-8') > 80
                || mb_strlen($dados['documentoResponsavel'], 'UTF-8') > 30
                || mb_strlen($dados['telefoneResponsavel'], 'UTF-8') > 30
                || mb_strlen($dados['processoJudicial'], 'UTF-8') > 100
                || mb_strlen($dados['observacoesGerais'], 'UTF-8') > 10000) {
                throw new InvalidArgumentException('Confira o acolhido, as datas e os campos obrigatórios do acolhimento.');
            }

            $inventarioJson = json_encode($inventario, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $pdo->beginTransaction();
            $registroAtual = null;
            if ($idAcolhimento > 0) {
                $registroStatement = $pdo->prepare(
                    'SELECT idAcolhimento, idAcolhido FROM tbAcolhimento
                     WHERE idAcolhimento = :id FOR UPDATE'
                );
                $registroStatement->execute(['id' => $idAcolhimento]);
                $registroAtual = $registroStatement->fetch(PDO::FETCH_ASSOC);
                if (!$registroAtual) {
                    throw new InvalidArgumentException('Acolhimento não encontrado.');
                }
                $historicoAcomodacaoStatement = $pdo->prepare(
                    'SELECT COUNT(*) AS total,
                            MIN(DATE(inicioEm)) AS primeiraData,
                            MAX(DATE(inicioEm)) AS ultimaData
                     FROM tbAcomodacaoAcolhido WHERE idAcolhimento = :id'
                );
                $historicoAcomodacaoStatement->execute(['id' => $idAcolhimento]);
                $historicoAcomodacao = $historicoAcomodacaoStatement->fetch(PDO::FETCH_ASSOC);
                if ((int) $historicoAcomodacao['total'] > 0
                    && ((int) $registroAtual['idAcolhido'] !== (int) $idAcolhidoInput
                        || $dataEntrada > $historicoAcomodacao['primeiraData']
                        || ($dataSaida !== null && $dataSaida < $historicoAcomodacao['ultimaData']))) {
                    throw new InvalidArgumentException('Não é possível alterar o acolhido ou as datas de modo que contradigam o histórico de acomodações.');
                }
            }

            $idsAcolhidos = [(int) $idAcolhidoInput];
            if ($registroAtual) {
                $idsAcolhidos[] = (int) $registroAtual['idAcolhido'];
            }
            $idsAcolhidos = array_values(array_unique($idsAcolhidos));
            sort($idsAcolhidos, SORT_NUMERIC);
            $placeholders = implode(', ', array_fill(0, count($idsAcolhidos), '?'));
            $acolhidosLock = $pdo->prepare(
                'SELECT idAcolhido FROM tbAcolhido WHERE idAcolhido IN (' . $placeholders . ')
                 ORDER BY idAcolhido FOR UPDATE'
            );
            $acolhidosLock->execute($idsAcolhidos);
            $acolhidosEncontrados = array_map('intval', $acolhidosLock->fetchAll(PDO::FETCH_COLUMN));
            if ($acolhidosEncontrados !== $idsAcolhidos) {
                throw new InvalidArgumentException('O acolhido selecionado não foi encontrado.');
            }

            $acolhimentoAberto = $pdo->prepare(
                'SELECT idAcolhimento FROM tbAcolhimento
                 WHERE idAcolhido = :idAcolhido AND dataSaida IS NULL
                   AND idAcolhimento <> :idAcolhimento LIMIT 1'
            );
            $acolhimentoAberto->execute([
                'idAcolhido' => (int) $idAcolhidoInput,
                'idAcolhimento' => $idAcolhimento > 0 ? $idAcolhimento : 0,
            ]);
            if ($acolhimentoAberto->fetchColumn()) {
                throw new InvalidArgumentException('Este acolhido já possui um acolhimento em aberto. Registre a saída antes de criar outro.');
            }

            $valores = [
                'idAcolhido' => (int) $idAcolhidoInput, 'dataEntrada' => $dataEntrada,
                'dataSaida' => $dataSaida, 'motivoEntrada' => $motivoEntrada, 'motivoSaida' => $motivoSaida,
                'inventarioPertences' => $inventarioJson,
                'responsavelJudicial' => $dados['responsavelJudicial'] !== '' ? $dados['responsavelJudicial'] : null,
                'parentescoResponsavel' => $dados['parentescoResponsavel'] !== '' ? $dados['parentescoResponsavel'] : null,
                'documentoResponsavel' => $dados['documentoResponsavel'] !== '' ? $dados['documentoResponsavel'] : null,
                'telefoneResponsavel' => $dados['telefoneResponsavel'] !== '' ? $dados['telefoneResponsavel'] : null,
                'processoJudicial' => $dados['processoJudicial'] !== '' ? $dados['processoJudicial'] : null,
                'observacoesGerais' => $dados['observacoesGerais'] !== '' ? $dados['observacoesGerais'] : null,
            ];
            if ($idAcolhimento > 0) {
                $statement = $pdo->prepare(
                    'UPDATE tbAcolhimento SET idAcolhido = :idAcolhido, dataEntrada = :dataEntrada,
                     dataSaida = :dataSaida, motivoEntrada = :motivoEntrada, motivoSaida = :motivoSaida,
                     inventarioPertences = :inventarioPertences, responsavelJudicial = :responsavelJudicial,
                     parentescoResponsavel = :parentescoResponsavel, documentoResponsavel = :documentoResponsavel,
                     telefoneResponsavel = :telefoneResponsavel, processoJudicial = :processoJudicial,
                     observacoesGerais = :observacoesGerais, idUsuarioAlteracao = :idUsuarioAlteracao
                     WHERE idAcolhimento = :idAcolhimento'
                );
                $statement->execute($valores + [
                    'idUsuarioAlteracao' => (int) $usuarioAtual['idUsuario'],
                    'idAcolhimento' => $idAcolhimento,
                ]);
                if ($dataSaida !== null) {
                    $encerraAcomodacao = $pdo->prepare(
                        'UPDATE tbAcomodacaoAcolhido
                         SET fimEm = CURRENT_TIMESTAMP, idUsuarioAlteracao = :usuario
                         WHERE idAcolhimento = :id AND fimEm IS NULL'
                    );
                    $encerraAcomodacao->execute([
                        'usuario' => (int) $usuarioAtual['idUsuario'],
                        'id' => $idAcolhimento,
                    ]);
                }
                $resultadoSalvo = 'atualizado';
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO tbAcolhimento
                     (idAcolhido, dataEntrada, dataSaida, motivoEntrada, motivoSaida, inventarioPertences,
                      responsavelJudicial, parentescoResponsavel, documentoResponsavel, telefoneResponsavel,
                      processoJudicial, observacoesGerais, idUsuarioCriacao)
                     VALUES (:idAcolhido, :dataEntrada, :dataSaida, :motivoEntrada, :motivoSaida,
                      :inventarioPertences, :responsavelJudicial, :parentescoResponsavel, :documentoResponsavel,
                      :telefoneResponsavel, :processoJudicial, :observacoesGerais, :idUsuarioCriacao)'
                );
                $statement->execute($valores + ['idUsuarioCriacao' => (int) $usuarioAtual['idUsuario']]);
                $resultadoSalvo = 'criado';
            }
            $pdo->commit();
            header('Location: ' . appTabUrl('acolhimento.php?resultado=' . $resultadoSalvo . '#pesquisa'));
            exit;
        }

        if ($acao === 'excluir') {
            $id = filter_var($textoPost('idAcolhimento'), FILTER_VALIDATE_INT);
            if (!$id || $id < 1) {
                throw new InvalidArgumentException('Acolhimento inválido.');
            }
            $statement = $pdo->prepare('DELETE FROM tbAcolhimento WHERE idAcolhimento = :id');
            $statement->execute(['id' => $id]);
            if ($statement->rowCount() === 0) {
                throw new InvalidArgumentException('Acolhimento não encontrado.');
            }
            header('Location: ' . appTabUrl('acolhimento.php?resultado=excluido#pesquisa'));
            exit;
        }
        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erro = $exception->getMessage();
    } catch (JsonException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Falha ao preparar inventário do acolhimento: ' . $exception->getMessage());
        $erro = 'Não foi possível preparar o inventário informado.';
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Falha ao salvar acolhimento: ' . $exception->getMessage());
        $erro = $acao === 'excluir' && (int) ($exception->errorInfo[1] ?? 0) === 1451
            ? 'Este acolhimento possui histórico de acomodações e não pode ser excluído.'
            : 'Não foi possível concluir o acolhimento. Verifique os dados e tente novamente.';
    }
}

if ($idEdicao !== false && $idEdicao > 0) {
    $statement = $pdo->prepare(
        'SELECT a.*, acolhido.nomeCompleto AS nomeAcolhido,
                criador.idUsuario AS criadoPorId, criador.nomeCompleto AS criadoPor,
                alterador.idUsuario AS alteradoPorId, alterador.nomeCompleto AS alteradoPor
         FROM tbAcolhimento a
         INNER JOIN tbAcolhido acolhido ON acolhido.idAcolhido = a.idAcolhido
         INNER JOIN tbUsuario criador ON criador.idUsuario = a.idUsuarioCriacao
         LEFT JOIN tbUsuario alterador ON alterador.idUsuario = a.idUsuarioAlteracao
         WHERE a.idAcolhimento = :id'
    );
    $statement->execute(['id' => $idEdicao]);
    $registro = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$registro) {
        $erro = $erro !== '' ? $erro : 'Acolhimento não encontrado.';
    } else {
        $auditoria = [
            'criadoPorId' => $registro['criadoPorId'], 'criadoPor' => $registro['criadoPor'],
            'criadoEm' => $registro['criadoEm'], 'alteradoPorId' => $registro['alteradoPorId'],
            'alteradoPor' => $registro['alteradoPor'], 'atualizadoEm' => $registro['atualizadoEm'],
        ];
        if (!$formEnviado) {
            $dados = [
                'idAcolhimento' => (int) $registro['idAcolhimento'], 'idAcolhido' => (int) $registro['idAcolhido'],
                'dataEntrada' => (string) $registro['dataEntrada'], 'dataSaida' => (string) ($registro['dataSaida'] ?? ''),
                'motivoEntrada' => (string) $registro['motivoEntrada'], 'motivoSaida' => (string) ($registro['motivoSaida'] ?? ''),
                'inventario' => $lerInventario($registro['inventarioPertences']),
                'responsavelJudicial' => (string) ($registro['responsavelJudicial'] ?? ''),
                'parentescoResponsavel' => (string) ($registro['parentescoResponsavel'] ?? ''),
                'documentoResponsavel' => (string) ($registro['documentoResponsavel'] ?? ''),
                'telefoneResponsavel' => (string) ($registro['telefoneResponsavel'] ?? ''),
                'processoJudicial' => (string) ($registro['processoJudicial'] ?? ''),
                'observacoesGerais' => (string) ($registro['observacoesGerais'] ?? ''),
            ];
        }
    }
}

$totalAcolhidos = (int) $pdo->query('SELECT COUNT(*) FROM tbAcolhido')->fetchColumn();
$acolhidoSelecionado = null;
$idAcolhidoSelecionado = filter_var($dados['idAcolhido'], FILTER_VALIDATE_INT);
if ($idAcolhidoSelecionado !== false && $idAcolhidoSelecionado > 0) {
    $acolhidoStatement = $pdo->prepare(
        'SELECT idAcolhido, nomeCompleto, dataNascimento FROM tbAcolhido WHERE idAcolhido = :id'
    );
    $acolhidoStatement->execute(['id' => $idAcolhidoSelecionado]);
    $acolhidoSelecionado = $acolhidoStatement->fetch(PDO::FETCH_ASSOC) ?: null;
}
$acolhimentos = $pdo->query(
    'SELECT a.idAcolhimento, a.idAcolhido, a.dataEntrada, a.dataSaida, a.motivoEntrada,
            a.inventarioPertences, acolhido.nomeCompleto, criador.idUsuario AS criadoPorId,
            criador.nomeCompleto AS criadoPor, a.criadoEm, alterador.idUsuario AS alteradoPorId,
            alterador.nomeCompleto AS alteradoPor, a.atualizadoEm
     FROM tbAcolhimento a
     INNER JOIN tbAcolhido acolhido ON acolhido.idAcolhido = a.idAcolhido
     INNER JOIN tbUsuario criador ON criador.idUsuario = a.idUsuarioCriacao
     LEFT JOIN tbUsuario alterador ON alterador.idUsuario = a.idUsuarioAlteracao
     ORDER BY a.dataEntrada DESC, a.idAcolhimento DESC'
)->fetchAll(PDO::FETCH_ASSOC);
$inventarioFormulario = $dados['inventario'];
if ($inventarioFormulario === []) {
    $inventarioFormulario = [['item' => '', 'quantidade' => 1, 'observacao' => '']];
}
$abaInicial = (($idEdicao !== false && $idEdicao > 0) || ($formEnviado && $erro !== '')
    || in_array($_GET['aba'] ?? null, ['pesquisa', 'manutencao'], true))
    ? (($_GET['aba'] ?? '') === 'pesquisa' ? 'pesquisa' : 'manutencao') : 'pesquisa';
$mensagensResultado = [
    'criado' => 'Acolhimento registrado.',
    'atualizado' => 'Acolhimento atualizado.',
    'excluido' => 'Acolhimento excluído.',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acolhimentos | Sistema Elo</title>
    <link rel="icon" type="image/png" href="<?php echo appEscape($baseUrl . '/assets/imgs/logo-elo.png'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/tailwind-config.js'); ?>"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="flex h-screen flex-col overflow-hidden bg-gray-100 font-sans text-gray-800">
    <?php include __DIR__ . '/../../../components/gestao-acolhidos/navbar-gestaoacolhidos.php'; ?>
    <div class="flex min-h-0 flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../../components/gestao-acolhidos/sidebar-gestaoacolhidos.php'; ?>
        <main class="min-h-0 min-w-0 flex-1 overflow-y-auto bg-gray-50 p-4 md:p-6">
            <h1 class="mb-6 text-2xl font-bold text-gray-800">Acolhimentos</h1>
            <?php if ($erro !== ''): ?>
                <p role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"><?php echo appEscape($erro); ?></p>
            <?php elseif (isset($mensagensResultado[$resultado])): ?>
                <p role="status" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800"><?php echo appEscape($mensagensResultado[$resultado]); ?></p>
            <?php endif; ?>

            <?php include __DIR__ . '/../../../components/tabs-pesquisa-manutencao.php'; ?>

            <section id="painel-manutencao" role="tabpanel" aria-labelledby="tab-manutencao" <?php echo $abaInicial === 'manutencao' ? '' : 'hidden'; ?>>
                <section class="mb-5 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:p-6">
                    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900"><?php echo (int) $dados['idAcolhimento'] > 0 ? 'Editar acolhimento' : 'Novo acolhimento'; ?></h2>
                        </div>
                        <?php if ((int) $dados['idAcolhimento'] > 0): ?>
                            <a href="<?php echo appEscape(appTabUrl($urlAtual . '?aba=manutencao#manutencao')); ?>" class="text-sm font-medium text-elo-teal hover:underline">Novo acolhimento</a>
                        <?php endif; ?>
                    </div>

                    <?php if ($totalAcolhidos === 0): ?>
                        <p class="mb-4 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                            Cadastre primeiro a pessoa na seção Acolhidos para iniciar um acolhimento.
                            <a href="<?php echo appEscape(appTabUrl($baseUrl . '/pages/gestao-acolhidos/cadastros/acolhidos.php?subaba=acolhidos#manutencao')); ?>" class="font-semibold underline">Ir para cadastro de acolhidos</a>
                        </p>
                    <?php endif; ?>

                    <form method="post" class="space-y-5" data-admission-form>
                        <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                        <input type="hidden" name="acao" value="salvar">
                        <input type="hidden" name="idAcolhimento" value="<?php echo (int) $dados['idAcolhimento']; ?>">

                        <fieldset class="rounded-lg border border-gray-200 p-4">
                            <legend class="px-2 text-sm font-semibold text-gray-700">Entrada e saída</legend>
                            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                <div class="relative md:col-span-2" data-admission-resident-search>
                                    <label for="idAcolhidoPesquisa" class="text-sm font-medium text-gray-700">Acolhido *</label>
                                    <span class="mt-1 flex">
                                        <input id="idAcolhidoPesquisa" type="search" data-admission-resident-input required autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="sugestoes-acolhidos" value="<?php
                                            echo $acolhidoSelecionado
                                                ? appEscape('#' . (int) $acolhidoSelecionado['idAcolhido'] . ' — ' . $acolhidoSelecionado['nomeCompleto'] . ' — Nascimento: ' . date('d/m/Y', strtotime((string) $acolhidoSelecionado['dataNascimento'])))
                                                : '';
                                        ?>" placeholder="Pesquise ou selecione um acolhido" <?php echo $totalAcolhidos === 0 ? 'disabled' : ''; ?> class="min-w-0 flex-1 rounded-l border border-r-0 border-gray-300 px-3 py-2 disabled:cursor-not-allowed disabled:bg-gray-100">
                                        <button type="button" data-admission-resident-toggle aria-label="Mostrar acolhidos disponíveis" title="Mostrar acolhidos disponíveis" <?php echo $totalAcolhidos === 0 ? 'disabled' : ''; ?> class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-r border border-gray-300 bg-gray-50 text-gray-600 hover:bg-gray-100 focus:z-10 focus:outline-none focus:ring-2 focus:ring-elo-teal disabled:cursor-not-allowed disabled:opacity-50">
                                            <i class="ph ph-caret-down" aria-hidden="true"></i>
                                        </button>
                                    </span>
                                    <input type="hidden" name="idAcolhido" data-admission-resident-id value="<?php echo $acolhidoSelecionado ? (int) $acolhidoSelecionado['idAcolhido'] : ''; ?>">
                                    <div id="sugestoes-acolhidos" data-admission-resident-results role="listbox" class="absolute z-20 mt-1 hidden max-h-60 w-full overflow-y-auto rounded border border-gray-200 bg-white shadow-lg"></div>
                                    <p data-admission-resident-message role="status" aria-live="polite" class="mt-1 text-xs text-gray-500">Abra a lista ou digite para filtrar.</p>
                                </div>
                                <label class="text-sm font-medium text-gray-700">Data de entrada *
                                    <input type="date" name="dataEntrada" required value="<?php echo appEscape((string) $dados['dataEntrada']); ?>" data-admission-entry class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                </label>
                                <label class="text-sm font-medium text-gray-700">Data de saída
                                    <input type="date" name="dataSaida" value="<?php echo appEscape((string) $dados['dataSaida']); ?>" data-admission-exit class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                    <span class="mt-1 block text-xs font-normal text-gray-500">Deixe em branco enquanto o acolhimento estiver em andamento.</span>
                                </label>
                                <label class="text-sm font-medium text-gray-700 md:col-span-2">Motivo da entrada *
                                    <textarea name="motivoEntrada" required maxlength="500" rows="3" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"><?php echo appEscape((string) $dados['motivoEntrada']); ?></textarea>
                                </label>
                                <label class="text-sm font-medium text-gray-700 md:col-span-2">Motivo da saída
                                    <textarea name="motivoSaida" maxlength="500" rows="3" data-admission-exit-reason class="mt-1 w-full rounded border border-gray-300 px-3 py-2" <?php echo $dados['dataSaida'] === '' ? 'disabled' : ''; ?>><?php echo appEscape((string) $dados['motivoSaida']); ?></textarea>
                                    <span class="mt-1 block text-xs font-normal text-gray-500">Obrigatório quando a data de saída for informada.</span>
                                </label>
                            </div>
                        </fieldset>

                        <fieldset class="rounded-lg border border-gray-200 p-4">
                            <legend class="px-2 text-sm font-semibold text-gray-700">Inventário de pertences</legend>
                            <div class="space-y-3" data-inventory-list>
                                <?php foreach ($inventarioFormulario as $indice => $item): ?>
                                    <?php
                                    $itemNome = is_array($item) && is_scalar($item['item'] ?? null) ? (string) $item['item'] : '';
                                    $itemQuantidade = is_array($item) && is_scalar($item['quantidade'] ?? null) ? (string) $item['quantidade'] : '1';
                                    $itemObservacao = is_array($item) && is_scalar($item['observacao'] ?? null) ? (string) $item['observacao'] : '';
                                    ?>
                                    <div class="grid gap-3 rounded-lg border border-gray-100 bg-gray-50 p-3 md:grid-cols-[minmax(0,2fr)_120px_minmax(0,2fr)_40px]" data-inventory-item>
                                        <label class="text-xs font-medium text-gray-600">Item
                                            <input name="inventario[<?php echo (int) $indice; ?>][item]" maxlength="150" value="<?php echo appEscape($itemNome); ?>" placeholder="Ex.: mochila" class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                                        </label>
                                        <label class="text-xs font-medium text-gray-600">Quantidade
                                            <input type="number" name="inventario[<?php echo (int) $indice; ?>][quantidade]" min="1" max="9999" value="<?php echo appEscape($itemQuantidade !== '' ? $itemQuantidade : '1'); ?>" class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                                        </label>
                                        <label class="text-xs font-medium text-gray-600">Observação do item
                                            <input name="inventario[<?php echo (int) $indice; ?>][observacao]" maxlength="255" value="<?php echo appEscape($itemObservacao); ?>" placeholder="Estado, cor ou identificação" class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                                        </label>
                                        <button type="button" data-inventory-remove class="mt-5 inline-flex h-10 w-10 items-center justify-center rounded border border-red-200 text-red-600 hover:bg-red-50" aria-label="Remover item do inventário" title="Remover item">
                                            <i class="ph ph-trash text-lg" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" data-inventory-add class="mt-3 inline-flex items-center gap-2 rounded border border-elo-teal px-3 py-2 text-sm font-medium text-elo-teal hover:bg-elo-teal/5">
                                <i class="ph ph-plus" aria-hidden="true"></i> Adicionar item
                            </button>
                            <template data-inventory-template>
                                <div class="grid gap-3 rounded-lg border border-gray-100 bg-gray-50 p-3 md:grid-cols-[minmax(0,2fr)_120px_minmax(0,2fr)_40px]" data-inventory-item>
                                    <label class="text-xs font-medium text-gray-600">Item
                                        <input data-inventory-field="item" maxlength="150" placeholder="Ex.: mochila" class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                                    </label>
                                    <label class="text-xs font-medium text-gray-600">Quantidade
                                        <input type="number" data-inventory-field="quantidade" min="1" max="9999" value="1" class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                                    </label>
                                    <label class="text-xs font-medium text-gray-600">Observação do item
                                        <input data-inventory-field="observacao" maxlength="255" placeholder="Estado, cor ou identificação" class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                                    </label>
                                    <button type="button" data-inventory-remove class="mt-5 inline-flex h-10 w-10 items-center justify-center rounded border border-red-200 text-red-600 hover:bg-red-50" aria-label="Remover item do inventário" title="Remover item">
                                        <i class="ph ph-trash text-lg" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </template>
                        </fieldset>

                        <fieldset class="rounded-lg border border-gray-200 p-4">
                            <legend class="px-2 text-sm font-semibold text-gray-700">Responsável judicial (opcional)</legend>
                            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                <label class="text-sm font-medium text-gray-700">Nome do responsável
                                    <input name="responsavelJudicial" maxlength="150" value="<?php echo appEscape((string) $dados['responsavelJudicial']); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                </label>
                                <label class="text-sm font-medium text-gray-700">Vínculo / parentesco
                                    <input name="parentescoResponsavel" maxlength="80" value="<?php echo appEscape((string) $dados['parentescoResponsavel']); ?>" placeholder="Ex.: responsável legal" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                </label>
                                <label class="text-sm font-medium text-gray-700">CPF ou documento
                                    <input name="documentoResponsavel" maxlength="30" value="<?php echo appEscape((string) $dados['documentoResponsavel']); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                </label>
                                <label class="text-sm font-medium text-gray-700">Telefone
                                    <input type="tel" name="telefoneResponsavel" maxlength="30" value="<?php echo appEscape((string) $dados['telefoneResponsavel']); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                </label>
                                <label class="text-sm font-medium text-gray-700 md:col-span-2">Processo / referência judicial
                                    <input name="processoJudicial" maxlength="100" value="<?php echo appEscape((string) $dados['processoJudicial']); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                </label>
                            </div>
                        </fieldset>

                        <fieldset class="rounded-lg border border-gray-200 p-4">
                            <legend class="px-2 text-sm font-semibold text-gray-700">Observações gerais</legend>
                            <textarea name="observacoesGerais" maxlength="10000" rows="4" class="w-full rounded border border-gray-300 px-3 py-2"><?php echo appEscape((string) $dados['observacoesGerais']); ?></textarea>
                        </fieldset>

                        <fieldset class="grid gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm md:grid-cols-2">
                            <legend class="px-2 text-sm font-semibold text-gray-700">Auditoria</legend>
                            <label class="font-medium text-gray-700">Criado por
                                <input type="text" value="<?php echo appEscape($formatarAuditoria($auditoria['criadoPorId'], $auditoria['criadoPor'], $auditoria['criadoEm'])); ?>" disabled class="mt-1 w-full cursor-not-allowed rounded border border-gray-200 bg-white px-3 py-2 text-gray-600">
                            </label>
                            <label class="font-medium text-gray-700">Alterado por
                                <input type="text" value="<?php echo appEscape($formatarAuditoria($auditoria['alteradoPorId'], $auditoria['alteradoPor'], $auditoria['atualizadoEm'])); ?>" disabled class="mt-1 w-full cursor-not-allowed rounded border border-gray-200 bg-white px-3 py-2 text-gray-600">
                            </label>
                        </fieldset>

                        <div class="flex flex-wrap justify-end gap-2">
                            <a href="<?php echo appEscape(appTabUrl($urlAtual . '#pesquisa')); ?>" class="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</a>
                            <button type="submit" class="inline-flex items-center gap-2 rounded bg-elo-teal px-5 py-2 text-sm font-semibold text-white hover:bg-elo-green" <?php echo $totalAcolhidos === 0 ? 'disabled' : ''; ?>>
                                <i class="ph ph-floppy-disk text-lg" aria-hidden="true"></i>
                                <?php echo (int) $dados['idAcolhimento'] > 0 ? 'Salvar alterações' : 'Registrar acolhimento'; ?>
                            </button>
                        </div>
                    </form>
                </section>
            </section>

            <section id="painel-pesquisa" role="tabpanel" aria-labelledby="tab-pesquisa" <?php echo $abaInicial === 'pesquisa' ? '' : 'hidden'; ?>>
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div>
                            <h2 class="text-lg font-semibold">Histórico de acolhimentos</h2>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <input data-filter-input="listaAcolhimentos" type="search" placeholder="Pesquisar acolhimentos..." aria-label="Pesquisar acolhimentos" class="rounded border border-gray-300 px-3 py-2 text-sm">
                            <a href="<?php echo appEscape(appTabUrl($urlAtual . '?aba=manutencao#manutencao')); ?>" class="rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">+ Novo acolhimento</a>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1050px] text-left text-sm">
                            <thead><tr class="border-b bg-gray-50 text-gray-600">
                                <th class="p-3">Acolhido</th><th class="p-3">Entrada</th><th class="p-3">Saída</th>
                                <th class="p-3">Motivo da entrada</th><th class="p-3">Pertences</th>
                                <th class="p-3">Auditoria</th><th class="p-3">Ações</th>
                            </tr></thead>
                            <tbody id="listaAcolhimentos">
                                <?php foreach ($acolhimentos as $acolhimento): ?>
                                    <?php $itensInventario = $lerInventario((string) $acolhimento['inventarioPertences']); ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="p-3 font-medium text-gray-800">
                                            <?php echo appEscape((string) $acolhimento['nomeCompleto']); ?>
                                            <span class="block text-xs font-normal text-gray-500">Acolhimento #<?php echo (int) $acolhimento['idAcolhimento']; ?> · Cadastro #<?php echo (int) $acolhimento['idAcolhido']; ?></span>
                                        </td>
                                        <td class="whitespace-nowrap p-3"><?php echo appEscape(date('d/m/Y', strtotime((string) $acolhimento['dataEntrada']))); ?></td>
                                        <td class="whitespace-nowrap p-3">
                                            <?php if ($acolhimento['dataSaida'] === null): ?>
                                                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800">Em andamento</span>
                                            <?php else: ?>
                                                <?php echo appEscape(date('d/m/Y', strtotime((string) $acolhimento['dataSaida']))); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="max-w-sm whitespace-normal p-3"><?php echo appEscape((string) $acolhimento['motivoEntrada']); ?></td>
                                        <td class="p-3"><?php echo count($itensInventario); ?> item(ns)</td>
                                        <td class="min-w-72 p-3 text-xs text-gray-600">
                                            <p><span class="font-medium text-gray-700">Criado:</span> <?php echo appEscape($formatarAuditoria($acolhimento['criadoPorId'], $acolhimento['criadoPor'], $acolhimento['criadoEm'])); ?></p>
                                            <p class="mt-1"><span class="font-medium text-gray-700">Alterado:</span> <?php echo appEscape($formatarAuditoria($acolhimento['alteradoPorId'], $acolhimento['alteradoPor'], $acolhimento['atualizadoEm'])); ?></p>
                                        </td>
                                        <td class="whitespace-nowrap p-3">
                                            <div class="flex items-center gap-2">
                                                <a href="<?php echo appEscape(appTabUrl($urlAtual . '?editar=' . (int) $acolhimento['idAcolhimento'] . '#manutencao')); ?>" class="inline-flex h-9 w-9 items-center justify-center rounded border border-gray-200 text-elo-teal hover:bg-elo-teal/5" aria-label="Editar acolhimento">
                                                    <i class="ph ph-pencil-simple text-lg" aria-hidden="true"></i>
                                                </a>
                                                <form method="post" data-confirm="Excluir este registro de acolhimento do histórico?">
                                                    <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                                                    <input type="hidden" name="acao" value="excluir">
                                                    <input type="hidden" name="idAcolhimento" value="<?php echo (int) $acolhimento['idAcolhimento']; ?>">
                                                    <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded border border-red-200 text-red-600 hover:bg-red-50" aria-label="Excluir acolhimento">
                                                        <i class="ph ph-trash text-lg" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$acolhimentos): ?>
                                    <tr><td colspan="7" class="px-3 py-10 text-center text-gray-500">Ainda não há acolhimentos registrados.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>
        </main>
    </div>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
    <script>
        (() => {
            const form = document.querySelector('[data-admission-form]');
            if (!form) return;
            const entryDate = form.querySelector('[data-admission-entry]');
            const exitDate = form.querySelector('[data-admission-exit]');
            const exitReason = form.querySelector('[data-admission-exit-reason]');
            const inventoryList = form.querySelector('[data-inventory-list]');
            const inventoryTemplate = form.querySelector('[data-inventory-template]');
            let nextInventoryIndex = inventoryList.querySelectorAll('[data-inventory-item]').length;
            const residentSearch = form.querySelector('[data-admission-resident-search]');
            const residentInput = residentSearch.querySelector('[data-admission-resident-input]');
            const residentId = residentSearch.querySelector('[data-admission-resident-id]');
            const residentToggle = residentSearch.querySelector('[data-admission-resident-toggle]');
            const residentResults = residentSearch.querySelector('[data-admission-resident-results]');
            const residentMessage = residentSearch.querySelector('[data-admission-resident-message]');
            const csrfToken = form.elements.namedItem('csrfToken').value;
            const tabToken = <?php echo json_encode(appCurrentTabToken(), JSON_THROW_ON_ERROR); ?>;
            let residentSearchTimer;
            let residentRequestController;
            let activeResidentIndex = -1;

            const showResidentResults = (message) => {
                residentResults.classList.remove('hidden');
                residentInput.setAttribute('aria-expanded', 'true');
                residentMessage.textContent = message;
            };
            const closeResidentResults = () => {
                residentResults.classList.add('hidden');
                residentInput.setAttribute('aria-expanded', 'false');
                residentInput.removeAttribute('aria-activedescendant');
            };
            const selectResident = (resident) => {
                residentInput.value = resident.label;
                residentId.value = String(resident.id);
                residentInput.setCustomValidity('');
                closeResidentResults();
                activeResidentIndex = -1;
            };
            const renderResidents = (residents) => {
                residentResults.replaceChildren();
                activeResidentIndex = -1;
                if (!residents.length) {
                    showResidentResults('Nenhum acolhido encontrado.');
                    return;
                }
                residents.forEach((resident, index) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.id = `${residentResults.id}-opcao-${index}`;
                    option.className = 'block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-elo-teal/10 focus:bg-elo-teal/10 focus:outline-none';
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', 'false');
                    option.textContent = resident.label;
                    option.addEventListener('pointerdown', (event) => event.preventDefault());
                    option.addEventListener('click', () => selectResident(resident));
                    residentResults.append(option);
                });
                showResidentResults(residents.length === 50
                    ? 'Mostrando até 50 opções. Digite para filtrar.'
                    : `${residents.length} opção(ões). Use as setas e Enter para selecionar.`);
            };
            const searchResidents = async (browseAll = false) => {
                const busca = browseAll ? '' : residentInput.value.trim();
                residentRequestController?.abort();
                const controller = new AbortController();
                residentRequestController = controller;
                const body = new FormData();
                body.set('csrfToken', csrfToken);
                body.set('tabToken', tabToken || '');
                body.set('acao', 'buscarAcolhidos');
                body.set('busca', busca);
                residentResults.replaceChildren();
                showResidentResults('Pesquisando acolhidos...');

                try {
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body,
                        headers: {'X-Requested-With': 'XMLHttpRequest'},
                        credentials: 'same-origin',
                        signal: controller.signal
                    });
                    if (!(response.headers.get('content-type') || '').includes('application/json')) {
                        throw new Error('Não foi possível validar sua sessão. Atualize a página e tente novamente.');
                    }
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message || 'Não foi possível pesquisar os acolhidos.');
                    if (residentRequestController !== controller) return;
                    renderResidents(result.items);
                } catch (error) {
                    if (error.name === 'AbortError' || residentRequestController !== controller) return;
                    residentResults.replaceChildren();
                    showResidentResults(error.message || 'Falha ao pesquisar os acolhidos.');
                }
            };

            residentToggle.addEventListener('pointerdown', (event) => event.preventDefault());
            residentToggle.addEventListener('click', () => {
                if (residentInput.getAttribute('aria-expanded') === 'true') {
                    residentRequestController?.abort();
                    closeResidentResults();
                    return;
                }
                searchResidents(true);
            });
            residentInput.addEventListener('focus', () => searchResidents(true));
            residentInput.addEventListener('input', () => {
                window.clearTimeout(residentSearchTimer);
                residentRequestController?.abort();
                residentId.value = '';
                residentInput.setCustomValidity('Selecione um acolhido nas sugestões.');
                residentSearchTimer = window.setTimeout(() => searchResidents(), 250);
            });
            residentInput.addEventListener('keydown', (event) => {
                const options = Array.from(residentResults.querySelectorAll('[role="option"]'));
                if (event.key === 'Escape') {
                    closeResidentResults();
                    return;
                }
                if (event.key === 'Enter' && residentInput.getAttribute('aria-expanded') === 'true' && !options.length) {
                    event.preventDefault();
                    return;
                }
                if (!options.length || !['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) return;
                event.preventDefault();
                if (event.key === 'Enter') {
                    if (activeResidentIndex >= 0) options[activeResidentIndex].click();
                    return;
                }
                activeResidentIndex = event.key === 'ArrowDown'
                    ? (activeResidentIndex + 1) % options.length
                    : (activeResidentIndex - 1 + options.length) % options.length;
                options.forEach((option, index) => {
                    const active = index === activeResidentIndex;
                    option.setAttribute('aria-selected', String(active));
                    option.classList.toggle('bg-elo-teal/10', active);
                });
                residentInput.setAttribute('aria-activedescendant', options[activeResidentIndex].id);
            });
            residentInput.addEventListener('blur', () => {
                window.setTimeout(() => {
                    if (!residentSearch.contains(document.activeElement)) closeResidentResults();
                }, 100);
            });
            form.addEventListener('submit', (event) => {
                if (residentId.value !== '') return;
                residentInput.setCustomValidity('Selecione um acolhido nas sugestões.');
                residentInput.reportValidity();
                event.preventDefault();
                residentInput.focus();
            });

            const updateExitFields = () => {
                exitDate.min = entryDate.value;
                exitReason.disabled = exitDate.value === '';
                exitReason.required = exitDate.value !== '';
                if (exitDate.value === '') exitReason.value = '';
            };
            entryDate.addEventListener('change', updateExitFields);
            exitDate.addEventListener('change', updateExitFields);
            updateExitFields();
            form.querySelector('[data-inventory-add]').addEventListener('click', () => {
                const row = inventoryTemplate.content.firstElementChild.cloneNode(true);
                row.querySelectorAll('[data-inventory-field]').forEach((input) => {
                    input.name = `inventario[${nextInventoryIndex}][${input.dataset.inventoryField}]`;
                });
                nextInventoryIndex++;
                inventoryList.append(row);
                row.querySelector('[data-inventory-field="item"]').focus();
            });
            inventoryList.addEventListener('click', (event) => {
                const removeButton = event.target.closest('[data-inventory-remove]');
                if (!removeButton) return;
                const row = removeButton.closest('[data-inventory-item]');
                const rows = inventoryList.querySelectorAll('[data-inventory-item]');
                if (rows.length === 1) {
                    row.querySelectorAll('input').forEach((input) => {
                        input.value = input.type === 'number' ? '1' : '';
                    });
                    return;
                }
                row.remove();
            });
        })();
    </script>
</body>
</html>
