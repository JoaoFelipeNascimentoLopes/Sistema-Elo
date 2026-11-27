<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('geral.movimentacoes/livro-plantao');
$pdo = appPdo();
$baseUrl = appBaseUrl();
$urlAtual = $baseUrl . '/pages/geral/movimentacoes/livro-plantao.php';

$tiposRegistro = ['Passagem de Turno', 'Ocorrência', 'Aviso Geral'];
$prioridades = ['Normal', 'Atenção', 'Urgente'];
$statusList = ['Aberto', 'Em acompanhamento', 'Resolvido'];
$turnos = ['Manhã', 'Tarde', 'Noite', 'Integral'];

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

$erro = '';
$formSubmitted = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    $acao = $textoPost('acao');

    if ($acao === 'buscarColaboradores') {
        header('Content-Type: application/json; charset=UTF-8');
        $busca = $textoPost('busca');
        if (mb_strlen($busca, 'UTF-8') > 100) {
            http_response_code(400);
            echo json_encode(['message' => 'A pesquisa deve ter até 100 caracteres.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        try {
            $buscarPorId = ctype_digit($busca);
            $termo = '%' . $busca . '%';
            if ($busca === '') {
                $stmt = $pdo->query(
                    'SELECT idColaborador, nomeCompleto, tipoVinculo, cargoFuncao, ativo
                     FROM tbColaborador
                     ORDER BY ativo DESC, nomeCompleto
                     LIMIT 50'
                );
            } elseif ($buscarPorId) {
                $stmt = $pdo->prepare(
                    'SELECT idColaborador, nomeCompleto, tipoVinculo, cargoFuncao, ativo
                     FROM tbColaborador
                     WHERE idColaborador LIKE ? OR nomeCompleto LIKE ? OR cargoFuncao LIKE ?
                     ORDER BY idColaborador LIKE ? DESC, ativo DESC, nomeCompleto
                     LIMIT 50'
                );
                $stmt->execute([$busca . '%', $termo, $termo, $busca . '%']);
            } else {
                $stmt = $pdo->prepare(
                    'SELECT idColaborador, nomeCompleto, tipoVinculo, cargoFuncao, ativo
                     FROM tbColaborador
                     WHERE nomeCompleto LIKE ? OR cargoFuncao LIKE ?
                     ORDER BY ativo DESC, nomeCompleto
                     LIMIT 50'
                );
                $stmt->execute([$termo, $termo]);
            }
            $items = array_map(static fn(array $r): array => [
                'id' => (int) $r['idColaborador'],
                'label' => (int) $r['idColaborador'] . ' — ' . $r['nomeCompleto']
                    . ' (' . $r['cargoFuncao'] . ((int) $r['ativo'] !== 1 ? ' · Inativo' : '') . ')',
            ], $stmt->fetchAll(PDO::FETCH_ASSOC));
            echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (PDOException $exception) {
            error_log('Falha ao buscar colaboradores no livro de plantão: ' . $exception->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Não foi possível pesquisar os colaboradores agora.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    if ($acao === 'buscarAcolhidos') {
        header('Content-Type: application/json; charset=UTF-8');
        $busca = $textoPost('busca');
        if (mb_strlen($busca, 'UTF-8') > 100) {
            http_response_code(400);
            echo json_encode(['message' => 'A pesquisa deve ter até 100 caracteres.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        try {
            $buscarPorId = ctype_digit($busca);
            $termo = '%' . $busca . '%';
            if ($busca === '') {
                $stmt = $pdo->query(
                    'SELECT idAcolhido, nomeCompleto, dataNascimento
                     FROM tbAcolhido
                     ORDER BY nomeCompleto
                     LIMIT 50'
                );
            } elseif ($buscarPorId) {
                $stmt = $pdo->prepare(
                    'SELECT idAcolhido, nomeCompleto, dataNascimento
                     FROM tbAcolhido
                     WHERE idAcolhido LIKE ? OR nomeCompleto LIKE ?
                     ORDER BY idAcolhido LIKE ? DESC, nomeCompleto
                     LIMIT 50'
                );
                $stmt->execute([$busca . '%', $termo, $busca . '%']);
            } else {
                $stmt = $pdo->prepare(
                    'SELECT idAcolhido, nomeCompleto, dataNascimento
                     FROM tbAcolhido
                     WHERE nomeCompleto LIKE ?
                     ORDER BY nomeCompleto
                     LIMIT 50'
                );
                $stmt->execute([$termo]);
            }
            $items = array_map(static fn(array $r): array => [
                'id' => (int) $r['idAcolhido'],
                'label' => (int) $r['idAcolhido'] . ' — ' . $r['nomeCompleto']
                    . ' (Nasc.: ' . date('d/m/Y', strtotime((string) $r['dataNascimento'])) . ')',
            ], $stmt->fetchAll(PDO::FETCH_ASSOC));
            echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (PDOException $exception) {
            error_log('Falha ao buscar acolhidos no livro de plantão: ' . $exception->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Não foi possível pesquisar os acolhidos agora.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    try {
        if ($acao === 'salvar') {
            $idRegistro = filter_var($_POST['idRegistroPlantao'] ?? 0, FILTER_VALIDATE_INT);
            $idRegistro = ($idRegistro !== false && $idRegistro > 0) ? (int) $idRegistro : 0;

            $tipoRegistro = $textoPost('tipoRegistro');
            $prioridade = $textoPost('prioridade');
            $statusRegistro = $textoPost('statusRegistro');
            $fixado = isset($_POST['fixado']) ? 1 : 0;
            $dataOcorrencia = $textoPost('dataOcorrencia');
            $turno = $textoPost('turno');
            $titulo = $textoPost('titulo');
            $descricao = $textoPost('descricao');
            $idColaboradorInput = $textoPost('idColaborador');
            $idColaborador = $idColaboradorInput !== '' ? filter_var($idColaboradorInput, FILTER_VALIDATE_INT) : null;
            $idAcolhidoInput = $textoPost('idAcolhido');
            $idAcolhido = $idAcolhidoInput !== '' ? filter_var($idAcolhidoInput, FILTER_VALIDATE_INT) : null;
            $observacaoResolucao = $textoPost('observacaoResolucao');

            $formSubmitted = [
                'idRegistroPlantao' => $idRegistro,
                'tipoRegistro' => $tipoRegistro,
                'prioridade' => $prioridade,
                'statusRegistro' => $statusRegistro,
                'fixado' => $fixado,
                'dataOcorrencia' => $dataOcorrencia,
                'turno' => $turno,
                'titulo' => $titulo,
                'descricao' => $descricao,
                'idColaborador' => $idColaborador !== false && $idColaborador !== null ? (int) $idColaborador : null,
                'idAcolhido' => $idAcolhido !== false && $idAcolhido !== null ? (int) $idAcolhido : null,
                'observacaoResolucao' => $observacaoResolucao,
            ];

            if (!in_array($tipoRegistro, $tiposRegistro, true)) {
                throw new InvalidArgumentException('Selecione um tipo de registro válido.');
            }
            if (!in_array($prioridade, $prioridades, true)) {
                throw new InvalidArgumentException('Selecione uma prioridade válida.');
            }
            if (!in_array($statusRegistro, $statusList, true)) {
                throw new InvalidArgumentException('Selecione um status válido.');
            }
            if ($dataOcorrencia === '' || !$validarData($dataOcorrencia)) {
                throw new InvalidArgumentException('Informe uma data válida para o registro.');
            }
            if (!in_array($turno, $turnos, true)) {
                throw new InvalidArgumentException('Selecione um turno válido.');
            }
            if ($titulo === '' || mb_strlen($titulo, 'UTF-8') > 150) {
                throw new InvalidArgumentException('Informe um título (até 150 caracteres).');
            }
            if ($descricao === '' || mb_strlen($descricao, 'UTF-8') > 5000) {
                throw new InvalidArgumentException('Descreva o registro do plantão (até 5000 caracteres).');
            }
            if (mb_strlen($observacaoResolucao, 'UTF-8') > 500) {
                throw new InvalidArgumentException('A observação de resolução deve ter até 500 caracteres.');
            }
            if ($idColaborador === false || ($idColaborador !== null && $idColaborador < 1)) {
                throw new InvalidArgumentException('O colaborador selecionado é inválido.');
            }
            if ($idAcolhido === false || ($idAcolhido !== null && $idAcolhido < 1)) {
                throw new InvalidArgumentException('O acolhido selecionado é inválido.');
            }

            if ($idColaborador !== null) {
                $cCheck = $pdo->prepare('SELECT idColaborador FROM tbColaborador WHERE idColaborador = :id');
                $cCheck->execute(['id' => $idColaborador]);
                if ($cCheck->fetchColumn() === false) {
                    throw new InvalidArgumentException('O colaborador selecionado não foi encontrado.');
                }
            }
            if ($idAcolhido !== null) {
                $aCheck = $pdo->prepare('SELECT idAcolhido FROM tbAcolhido WHERE idAcolhido = :id');
                $aCheck->execute(['id' => $idAcolhido]);
                if ($aCheck->fetchColumn() === false) {
                    throw new InvalidArgumentException('O acolhido selecionado não foi encontrado.');
                }
            }

            if ($idRegistro > 0) {
                $existingStmt = $pdo->prepare(
                    'SELECT idRegistroPlantao, statusRegistro, resolvidoPor, resolvidoEm
                     FROM tbRegistroPlantao WHERE idRegistroPlantao = :id'
                );
                $existingStmt->execute(['id' => $idRegistro]);
                $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
                if (!$existing) {
                    throw new InvalidArgumentException('Registro não encontrado.');
                }

                $resolvidoPor = $existing['resolvidoPor'];
                $resolvidoEm = $existing['resolvidoEm'];
                if ($statusRegistro === 'Resolvido' && $existing['statusRegistro'] !== 'Resolvido') {
                    $resolvidoPor = (int) $usuarioAtual['idUsuario'];
                    $resolvidoEm = date('Y-m-d H:i:s');
                } elseif ($statusRegistro !== 'Resolvido') {
                    $resolvidoPor = null;
                    $resolvidoEm = null;
                }

                $stmt = $pdo->prepare(
                    'UPDATE tbRegistroPlantao
                     SET tipoRegistro = :tipoRegistro, prioridade = :prioridade, statusRegistro = :statusRegistro,
                         fixado = :fixado, dataOcorrencia = :dataOcorrencia, turno = :turno,
                         titulo = :titulo, descricao = :descricao, idColaborador = :idColaborador,
                         idAcolhido = :idAcolhido, observacaoResolucao = :observacaoResolucao,
                         resolvidoPor = :resolvidoPor, resolvidoEm = :resolvidoEm,
                         idUsuarioAlteracao = :idUsuarioAlteracao
                     WHERE idRegistroPlantao = :idRegistroPlantao'
                );
                $stmt->execute([
                    'tipoRegistro' => $tipoRegistro,
                    'prioridade' => $prioridade,
                    'statusRegistro' => $statusRegistro,
                    'fixado' => $fixado,
                    'dataOcorrencia' => $dataOcorrencia,
                    'turno' => $turno,
                    'titulo' => $titulo,
                    'descricao' => $descricao,
                    'idColaborador' => $idColaborador,
                    'idAcolhido' => $idAcolhido,
                    'observacaoResolucao' => $observacaoResolucao !== '' ? $observacaoResolucao : null,
                    'resolvidoPor' => $resolvidoPor,
                    'resolvidoEm' => $resolvidoEm,
                    'idUsuarioAlteracao' => (int) $usuarioAtual['idUsuario'],
                    'idRegistroPlantao' => $idRegistro,
                ]);

                appRegistrarAuditoria(
                    'geral',
                    'editar',
                    'Registro de plantão atualizado: ' . $titulo . '.',
                    'tbRegistroPlantao',
                    $idRegistro,
                    ['tipoRegistro' => $tipoRegistro, 'prioridade' => $prioridade, 'statusRegistro' => $statusRegistro, 'fixado' => (bool) $fixado],
                    $usuarioAtual
                );
                header('Location: ' . appTabUrl('livro-plantao.php?resultado=atualizado'));
                exit;
            }

            $resolvidoPor = $statusRegistro === 'Resolvido' ? (int) $usuarioAtual['idUsuario'] : null;
            $resolvidoEm = $statusRegistro === 'Resolvido' ? date('Y-m-d H:i:s') : null;

            $stmt = $pdo->prepare(
                'INSERT INTO tbRegistroPlantao
                    (tipoRegistro, prioridade, statusRegistro, fixado, dataOcorrencia, turno,
                     titulo, descricao, idColaborador, idAcolhido, observacaoResolucao,
                     resolvidoPor, resolvidoEm, idUsuarioCriacao)
                 VALUES
                    (:tipoRegistro, :prioridade, :statusRegistro, :fixado, :dataOcorrencia, :turno,
                     :titulo, :descricao, :idColaborador, :idAcolhido, :observacaoResolucao,
                     :resolvidoPor, :resolvidoEm, :idUsuarioCriacao)'
            );
            $stmt->execute([
                'tipoRegistro' => $tipoRegistro,
                'prioridade' => $prioridade,
                'statusRegistro' => $statusRegistro,
                'fixado' => $fixado,
                'dataOcorrencia' => $dataOcorrencia,
                'turno' => $turno,
                'titulo' => $titulo,
                'descricao' => $descricao,
                'idColaborador' => $idColaborador,
                'idAcolhido' => $idAcolhido,
                'observacaoResolucao' => $observacaoResolucao !== '' ? $observacaoResolucao : null,
                'resolvidoPor' => $resolvidoPor,
                'resolvidoEm' => $resolvidoEm,
                'idUsuarioCriacao' => (int) $usuarioAtual['idUsuario'],
            ]);
            $novoId = (int) $pdo->lastInsertId();

            appRegistrarAuditoria(
                'geral',
                'criar',
                'Registro de plantão criado (' . $tipoRegistro . '): ' . $titulo . '.',
                'tbRegistroPlantao',
                $novoId,
                ['tipoRegistro' => $tipoRegistro, 'prioridade' => $prioridade, 'statusRegistro' => $statusRegistro, 'fixado' => (bool) $fixado],
                $usuarioAtual
            );
            header('Location: ' . appTabUrl('livro-plantao.php?resultado=criado'));
            exit;
        }

        if ($acao === 'alternarFixado') {
            $idRegistro = filter_var($_POST['idRegistroPlantao'] ?? 0, FILTER_VALIDATE_INT);
            if (!$idRegistro || $idRegistro < 1) {
                throw new InvalidArgumentException('Registro inválido.');
            }
            $check = $pdo->prepare('SELECT titulo, fixado FROM tbRegistroPlantao WHERE idRegistroPlantao = :id');
            $check->execute(['id' => $idRegistro]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new InvalidArgumentException('Registro não encontrado.');
            }
            $novoFixado = (int) $row['fixado'] === 1 ? 0 : 1;
            $upd = $pdo->prepare(
                'UPDATE tbRegistroPlantao SET fixado = :fixado, idUsuarioAlteracao = :uid WHERE idRegistroPlantao = :id'
            );
            $upd->execute(['fixado' => $novoFixado, 'uid' => (int) $usuarioAtual['idUsuario'], 'id' => $idRegistro]);

            appRegistrarAuditoria(
                'geral',
                'editar',
                ($novoFixado === 1 ? 'Aviso fixado no mural: ' : 'Aviso desafixado do mural: ') . $row['titulo'] . '.',
                'tbRegistroPlantao',
                $idRegistro,
                ['fixado' => (bool) $novoFixado],
                $usuarioAtual
            );
            header('Location: ' . appTabUrl('livro-plantao.php?resultado=' . ($novoFixado === 1 ? 'fixado' : 'desafixado')));
            exit;
        }

        if ($acao === 'marcarResolvido') {
            $idRegistro = filter_var($_POST['idRegistroPlantao'] ?? 0, FILTER_VALIDATE_INT);
            if (!$idRegistro || $idRegistro < 1) {
                throw new InvalidArgumentException('Registro inválido.');
            }
            $check = $pdo->prepare('SELECT titulo FROM tbRegistroPlantao WHERE idRegistroPlantao = :id');
            $check->execute(['id' => $idRegistro]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new InvalidArgumentException('Registro não encontrado.');
            }
            $upd = $pdo->prepare(
                "UPDATE tbRegistroPlantao
                 SET statusRegistro = 'Resolvido', resolvidoPor = :uid, resolvidoEm = NOW(), idUsuarioAlteracao = :uid2
                 WHERE idRegistroPlantao = :id"
            );
            $upd->execute([
                'uid' => (int) $usuarioAtual['idUsuario'],
                'uid2' => (int) $usuarioAtual['idUsuario'],
                'id' => $idRegistro,
            ]);
            appRegistrarAuditoria(
                'geral',
                'editar',
                'Registro de plantão marcado como resolvido: ' . $row['titulo'] . '.',
                'tbRegistroPlantao',
                $idRegistro,
                ['statusRegistro' => 'Resolvido'],
                $usuarioAtual
            );
            header('Location: ' . appTabUrl('livro-plantao.php?resultado=resolvido'));
            exit;
        }

        if ($acao === 'excluir') {
            $idRegistro = filter_var($_POST['idRegistroPlantao'] ?? 0, FILTER_VALIDATE_INT);
            if (!$idRegistro || $idRegistro < 1) {
                throw new InvalidArgumentException('Registro inválido.');
            }
            $check = $pdo->prepare('SELECT titulo, tipoRegistro FROM tbRegistroPlantao WHERE idRegistroPlantao = :id');
            $check->execute(['id' => $idRegistro]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new InvalidArgumentException('Registro não encontrado.');
            }
            $del = $pdo->prepare('DELETE FROM tbRegistroPlantao WHERE idRegistroPlantao = :id');
            $del->execute(['id' => $idRegistro]);
            appRegistrarAuditoria(
                'geral',
                'excluir',
                'Registro de plantão excluído (' . $row['tipoRegistro'] . '): ' . $row['titulo'] . '.',
                'tbRegistroPlantao',
                $idRegistro,
                ['titulo' => $row['titulo'], 'tipoRegistro' => $row['tipoRegistro']],
                $usuarioAtual
            );
            header('Location: ' . appTabUrl('livro-plantao.php?resultado=excluido'));
            exit;
        }

        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException $exception) {
        $erro = $exception->getMessage();
    } catch (PDOException $exception) {
        error_log('Falha no livro de plantão: ' . $exception->getMessage());
        $erro = 'Não foi possível salvar o registro do plantão.';
    }
}

$filtroDataInicio = is_scalar($_GET['dataInicio'] ?? null) ? trim((string) $_GET['dataInicio']) : '';
$filtroDataFim = is_scalar($_GET['dataFim'] ?? null) ? trim((string) $_GET['dataFim']) : '';
$filtroTipo = is_scalar($_GET['tipo'] ?? null) ? trim((string) $_GET['tipo']) : '';
$filtroPrioridade = is_scalar($_GET['prioridade'] ?? null) ? trim((string) $_GET['prioridade']) : '';
$filtroStatus = is_scalar($_GET['status'] ?? null) ? trim((string) $_GET['status']) : '';

if ($filtroDataInicio !== '' && !$validarData($filtroDataInicio)) {
    $filtroDataInicio = '';
}
if ($filtroDataFim !== '' && !$validarData($filtroDataFim)) {
    $filtroDataFim = '';
}
if ($filtroTipo !== '' && !in_array($filtroTipo, $tiposRegistro, true)) {
    $filtroTipo = '';
}
if ($filtroPrioridade !== '' && !in_array($filtroPrioridade, $prioridades, true)) {
    $filtroPrioridade = '';
}
if ($filtroStatus !== '' && !in_array($filtroStatus, $statusList, true)) {
    $filtroStatus = '';
}

$where = [];
$queryParams = [];
if ($filtroDataInicio !== '') {
    $where[] = 'r.dataOcorrencia >= :dataInicio';
    $queryParams['dataInicio'] = $filtroDataInicio;
}
if ($filtroDataFim !== '') {
    $where[] = 'r.dataOcorrencia <= :dataFim';
    $queryParams['dataFim'] = $filtroDataFim;
}
if ($filtroTipo !== '') {
    $where[] = 'r.tipoRegistro = :tipo';
    $queryParams['tipo'] = $filtroTipo;
}
if ($filtroPrioridade !== '') {
    $where[] = 'r.prioridade = :prioridade';
    $queryParams['prioridade'] = $filtroPrioridade;
}
if ($filtroStatus !== '') {
    $where[] = 'r.statusRegistro = :status';
    $queryParams['status'] = $filtroStatus;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$registrosStmt = $pdo->prepare(
    "SELECT r.*,
            c.nomeCompleto AS nomeColaborador, c.cargoFuncao AS cargoColaborador,
            a.nomeCompleto AS nomeAcolhido,
            uc.nomeCompleto AS nomeUsuarioCriacao,
            ua.nomeCompleto AS nomeUsuarioAlteracao,
            ur.nomeCompleto AS nomeResolvidoPor
     FROM tbRegistroPlantao r
     LEFT JOIN tbColaborador c ON c.idColaborador = r.idColaborador
     LEFT JOIN tbAcolhido a ON a.idAcolhido = r.idAcolhido
     INNER JOIN tbUsuario uc ON uc.idUsuario = r.idUsuarioCriacao
     LEFT JOIN tbUsuario ua ON ua.idUsuario = r.idUsuarioAlteracao
     LEFT JOIN tbUsuario ur ON ur.idUsuario = r.resolvidoPor
     $whereSql
     ORDER BY r.dataOcorrencia DESC, r.idRegistroPlantao DESC"
);
$registrosStmt->execute($queryParams);
$registros = $registrosStmt->fetchAll(PDO::FETCH_ASSOC);

$montarUrlFiltro = static function (string $modo = '') use (
    $urlAtual,
    $filtroDataInicio,
    $filtroDataFim,
    $filtroTipo,
    $filtroPrioridade,
    $filtroStatus
): string {
    $q = array_filter([
        'dataInicio' => $filtroDataInicio,
        'dataFim' => $filtroDataFim,
        'tipo' => $filtroTipo,
        'prioridade' => $filtroPrioridade,
        'status' => $filtroStatus,
        'modo' => $modo,
    ], static fn(string $v): bool => $v !== '');
    return appTabUrl($urlAtual . ($q ? '?' . http_build_query($q) : ''));
};

$modoImpressao = is_scalar($_GET['modo'] ?? null) ? trim((string) $_GET['modo']) : '';
if (in_array($modoImpressao, ['imprimir', 'pdf'], true)) {
    $filtrosResumo = [];
    if ($filtroDataInicio !== '' || $filtroDataFim !== '') {
        $iFmt = $filtroDataInicio !== '' ? date('d/m/Y', strtotime($filtroDataInicio)) : 'Início';
        $fFmt = $filtroDataFim !== '' ? date('d/m/Y', strtotime($filtroDataFim)) : 'Hoje';
        $filtrosResumo[] = 'Período: ' . $iFmt . ' a ' . $fFmt;
    }
    if ($filtroTipo !== '') $filtrosResumo[] = 'Tipo: ' . $filtroTipo;
    if ($filtroPrioridade !== '') $filtrosResumo[] = 'Prioridade: ' . $filtroPrioridade;
    if ($filtroStatus !== '') $filtrosResumo[] = 'Status: ' . $filtroStatus;
    $textoFiltros = $filtrosResumo ? implode(' · ', $filtrosResumo) : 'Sem filtros (todos os registros)';
    $subtituloImpressao = 'Gerado em ' . date('d/m/Y H:i') . ' · ' . count($registros) . ' registro(s)';

    $renderTabelaImpressao = static function () use ($registros): void {
        if (!$registros) {
            echo '<p class="report-empty">Nenhum registro de plantão corresponde aos filtros informados.</p>';
            return;
        }
        echo '<table class="report-table"><thead><tr>'
            . '<th style="width:11%">Data / Turno</th>'
            . '<th style="width:14%">Tipo / Prioridade</th>'
            . '<th style="width:17%">Título</th>'
            . '<th>Descrição e Vínculos</th>'
            . '<th style="width:11%">Status</th>'
            . '<th style="width:14%">Registrado por</th>'
            . '</tr></thead><tbody>';
        foreach ($registros as $idx => $r) {
            $zebraClass = $idx % 2 === 1 ? ' report-zebra-row' : '';
            $vinculos = [];
            if (!empty($r['nomeColaborador'])) $vinculos[] = 'Resp.: ' . $r['nomeColaborador'];
            if (!empty($r['nomeAcolhido'])) $vinculos[] = 'Acolhido: ' . $r['nomeAcolhido'];
            if (!empty($r['observacaoResolucao'])) $vinculos[] = 'Resolução: ' . $r['observacaoResolucao'];
            $descHtml = nl2br(appEscape((string) $r['descricao']));
            if ($vinculos) {
                $descHtml .= '<div style="margin-top:1.5mm;color:#4b5563;font-size:7pt;">' . appEscape(implode(' · ', $vinculos)) . '</div>';
            }
            echo '<tr class="report-data-row' . $zebraClass . '">'
                . '<td>' . appEscape(date('d/m/Y', strtotime((string) $r['dataOcorrencia'])) . ' · ' . $r['turno']) . '</td>'
                . '<td>' . appEscape($r['tipoRegistro'] . ' (' . $r['prioridade'] . ')') . '</td>'
                . '<td><strong>' . appEscape((string) $r['titulo']) . '</strong>' . ((int) $r['fixado'] === 1 ? ' [Fixado]' : '') . '</td>'
                . '<td>' . $descHtml . '</td>'
                . '<td>' . appEscape((string) $r['statusRegistro']) . '</td>'
                . '<td>' . appEscape((string) $r['nomeUsuarioCriacao']) . '</td>'
                . '</tr>';
        }
        echo '</tbody></table>';
    };

    if ($modoImpressao === 'pdf') {
        require_once __DIR__ . '/../../../vendor/autoload.php';
        $pdfOptions = new \Dompdf\Options();
        $pdfOptions->set('defaultFont', 'DejaVu Sans');
        $pdfOptions->set('isRemoteEnabled', false);
        $dompdf = new \Dompdf\Dompdf($pdfOptions);

        ob_start();
        $renderTabelaImpressao();
        $tabelaHtml = (string) ob_get_clean();

        $pdfHtml = '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><style>'
            . '@page{size:297mm 210mm;margin:10mm 10mm 12mm 10mm;}'
            . 'body{font-family:"DejaVu Sans",sans-serif;font-size:8pt;color:#111;margin:0;}'
            . '.report-heading{margin-bottom:4mm;padding-bottom:3mm;border-bottom:2px solid #1f2937;}'
            . '.report-heading:before{display:block;margin-bottom:1.5mm;color:#4b5563;content:"SISTEMA ELO · LIVRO DE PLANTÃO";font-size:7pt;font-weight:bold;letter-spacing:1.4pt;}'
            . '.report-heading h1{margin:0 0 1mm;font-size:14pt;font-weight:bold;letter-spacing:.4pt;text-transform:uppercase;}'
            . '.report-heading p{margin:0;color:#374151;font-size:8pt;}'
            . '.audit-filters{margin:0 0 4mm;padding:2mm 2.5mm;border:1px solid #d1d5db;background:#f8fafc;color:#374151;font-size:7.5pt;}'
            . '.report-table{width:100%;table-layout:fixed;border-collapse:collapse;text-align:left;font-size:7.5pt;}'
            . '.report-table th,.report-table td{border:1px solid #9ca3af;padding:1.3mm;vertical-align:top;overflow-wrap:anywhere;}'
            . '.report-table thead th{background:#1f2937;color:#fff;font-weight:bold;}'
            . '.report-data-row.report-zebra-row td{background:#f2f2f2;}'
            . '.report-empty{padding:5mm;color:#6b7280;text-align:center;}'
            . '</style></head><body>'
            . '<header class="report-heading"><h1>Livro de Plantão e Avisos</h1><p>' . appEscape($subtituloImpressao) . '</p></header>'
            . '<p class="audit-filters"><strong>Filtros aplicados:</strong> ' . appEscape($textoFiltros) . '</p>'
            . $tabelaHtml
            . '</body></html>';

        $dompdf->loadHtml($pdfHtml, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream('livro-plantao-' . date('Ymd-Hi') . '.pdf', ['Attachment' => true]);
        exit;
    }
    ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Elo - Livro de Plantão e Avisos</title>
    <link rel="icon" type="image/png" href="<?php echo appEscape($baseUrl . '/assets/imgs/logo-elo.png'); ?>">
    <link rel="stylesheet" href="<?php echo appEscape($baseUrl . '/assets/css/usuarios-por-perfis.css?v=' . filemtime(__DIR__ . '/../../../assets/css/usuarios-por-perfis.css')); ?>">
    <style>
        @page { size: 297mm 210mm; margin: 10mm 10mm 12mm 10mm; }
        .report-paper { --zebra-color: #f2f2f2; }
        .audit-filters { margin: 0 0 1rem; padding: 0.55rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.25rem; background: #f8fafc; color: #374151; font-size: 0.8rem; }
    </style>
</head>
<body class="report-stream">
    <div class="report-toolbar">
        <a href="<?php echo appEscape($montarUrlFiltro()); ?>" class="report-back-button">Voltar</a>
        <button type="button" id="plantao-print-button">Imprimir</button>
        <a href="<?php echo appEscape($montarUrlFiltro('pdf')); ?>">Baixar PDF</a>
    </div>
    <main id="report-output" class="report-paper" data-zebra="true">
        <header class="report-heading">
            <h1>Livro de Plantão e Avisos</h1>
            <p><?php echo appEscape($subtituloImpressao); ?></p>
        </header>
        <p class="audit-filters"><strong>Filtros aplicados:</strong> <?php echo appEscape($textoFiltros); ?></p>
        <?php $renderTabelaImpressao(); ?>
    </main>
    <script>document.getElementById('plantao-print-button')?.addEventListener('click', () => window.print());</script>
</body>
</html>
    <?php
    exit;
}

$avisosFixados = $pdo->query(
    "SELECT r.idRegistroPlantao, r.tipoRegistro, r.prioridade, r.statusRegistro,
            r.dataOcorrencia, r.turno, r.titulo, r.descricao,
            c.nomeCompleto AS nomeColaborador, a.nomeCompleto AS nomeAcolhido
     FROM tbRegistroPlantao r
     LEFT JOIN tbColaborador c ON c.idColaborador = r.idColaborador
     LEFT JOIN tbAcolhido a ON a.idAcolhido = r.idAcolhido
     WHERE r.fixado = 1
     ORDER BY FIELD(r.prioridade, 'Urgente', 'Atenção', 'Normal'), r.dataOcorrencia DESC, r.idRegistroPlantao DESC
     LIMIT 6"
)->fetchAll(PDO::FETCH_ASSOC);

$registroEdicao = null;
$idEdicao = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
if ($formSubmitted !== null && (int) $formSubmitted['idRegistroPlantao'] > 0) {
    $idEdicao = (int) $formSubmitted['idRegistroPlantao'];
}
if ($idEdicao) {
    $stmt = $pdo->prepare(
        'SELECT r.*,
                uc.nomeCompleto AS nomeUsuarioCriacao,
                ua.nomeCompleto AS nomeUsuarioAlteracao,
                ur.nomeCompleto AS nomeResolvidoPor
         FROM tbRegistroPlantao r
         INNER JOIN tbUsuario uc ON uc.idUsuario = r.idUsuarioCriacao
         LEFT JOIN tbUsuario ua ON ua.idUsuario = r.idUsuarioAlteracao
         LEFT JOIN tbUsuario ur ON ur.idUsuario = r.resolvidoPor
         WHERE r.idRegistroPlantao = :id'
    );
    $stmt->execute(['id' => $idEdicao]);
    $registroEdicao = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
if ($formSubmitted !== null) {
    $registroEdicao = array_merge($registroEdicao ?? [], $formSubmitted);
}

$colaboradorLabel = '';
$idColaboradorForm = (int) ($registroEdicao['idColaborador'] ?? 0);
if ($idColaboradorForm > 0) {
    $cs = $pdo->prepare('SELECT idColaborador, nomeCompleto, cargoFuncao, ativo FROM tbColaborador WHERE idColaborador = :id');
    $cs->execute(['id' => $idColaboradorForm]);
    if ($cr = $cs->fetch(PDO::FETCH_ASSOC)) {
        $colaboradorLabel = (int) $cr['idColaborador'] . ' — ' . $cr['nomeCompleto'] . ' (' . $cr['cargoFuncao'] . ((int) $cr['ativo'] !== 1 ? ' · Inativo' : '') . ')';
    }
}

$acolhidoLabel = '';
$idAcolhidoForm = (int) ($registroEdicao['idAcolhido'] ?? 0);
if ($idAcolhidoForm > 0) {
    $as = $pdo->prepare('SELECT idAcolhido, nomeCompleto, dataNascimento FROM tbAcolhido WHERE idAcolhido = :id');
    $as->execute(['id' => $idAcolhidoForm]);
    if ($ar = $as->fetch(PDO::FETCH_ASSOC)) {
        $acolhidoLabel = (int) $ar['idAcolhido'] . ' — ' . $ar['nomeCompleto'] . ' (Nasc.: ' . date('d/m/Y', strtotime((string) $ar['dataNascimento'])) . ')';
    }
}

$criadoPor = $registroEdicao
    ? $formatarAuditoria($registroEdicao['idUsuarioCriacao'] ?? null, $registroEdicao['nomeUsuarioCriacao'] ?? null, $registroEdicao['criadoEm'] ?? null)
    : '—';
$alteradoPor = $registroEdicao
    ? $formatarAuditoria($registroEdicao['idUsuarioAlteracao'] ?? null, $registroEdicao['nomeUsuarioAlteracao'] ?? null, $registroEdicao['atualizadoEm'] ?? null)
    : '—';
$resolvidoPorTexto = $registroEdicao
    ? $formatarAuditoria($registroEdicao['resolvidoPor'] ?? null, $registroEdicao['nomeResolvidoPor'] ?? null, $registroEdicao['resolvidoEm'] ?? null)
    : '—';

$temFiltros = $filtroDataInicio !== '' || $filtroDataFim !== '' || $filtroTipo !== '' || $filtroPrioridade !== '' || $filtroStatus !== '';

$resultado = [
    'criado' => 'Registro lançado no livro de plantão.',
    'atualizado' => 'Registro atualizado com sucesso.',
    'excluido' => 'Registro excluído.',
    'fixado' => 'Aviso fixado no mural.',
    'desafixado' => 'Aviso removido do mural.',
    'resolvido' => 'Registro marcado como resolvido.',
][(string) ($_GET['resultado'] ?? '')] ?? '';
$abaInicial = ($registroEdicao || $erro !== '' || ($_GET['aba'] ?? '') === 'manutencao') ? 'manutencao' : 'pesquisa';

$badgePrioridade = static fn(string $p): string => match ($p) {
    'Urgente' => 'bg-red-100 text-red-800 border-red-200',
    'Atenção' => 'bg-amber-100 text-amber-800 border-amber-200',
    default => 'bg-gray-100 text-gray-700 border-gray-200',
};
$badgeStatus = static fn(string $s): string => match ($s) {
    'Resolvido' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
    'Em acompanhamento' => 'bg-sky-100 text-sky-800 border-sky-200',
    default => 'bg-amber-100 text-amber-800 border-amber-200',
};
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/tailwind-config.js'); ?>"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="flex h-screen flex-col overflow-hidden bg-gray-100 font-sans text-gray-800">
    <?php include __DIR__ . '/../../../components/geral/navbar-geral.php'; ?>
    <div class="flex min-h-0 flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../../components/geral/sidebar-geral.php'; ?>
        <main class="min-w-0 flex-1 overflow-y-auto bg-gray-50 p-4 md:p-6">
            <h1 class="mb-6 text-2xl font-bold text-gray-800">Livro de Plantão e Avisos</h1>

            <?php if ($avisosFixados): ?>
                <section class="mb-6 rounded-xl border border-elo-teal/25 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="flex items-center gap-2 text-sm font-semibold text-elo-teal">
                            <i class="ph-fill ph-push-pin text-lg" aria-hidden="true"></i>
                            Mural de Avisos Fixados
                        </h2>
                        <span class="text-xs text-gray-500"><?php echo count($avisosFixados); ?> fixado(s)</span>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <?php foreach ($avisosFixados as $aviso): ?>
                            <article class="flex flex-col justify-between rounded-lg border <?php echo $aviso['prioridade'] === 'Urgente' ? 'border-red-200 bg-red-50/40' : ($aviso['prioridade'] === 'Atenção' ? 'border-amber-200 bg-amber-50/40' : 'border-gray-200 bg-gray-50/60'); ?> p-3.5">
                                <div>
                                    <div class="mb-1.5 flex flex-wrap items-center justify-between gap-1.5">
                                        <span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold <?php echo $badgePrioridade($aviso['prioridade']); ?>">
                                            <?php echo appEscape($aviso['prioridade']); ?>
                                        </span>
                                        <span class="text-[11px] text-gray-500" data-preserve-case>
                                            <?php echo appEscape(date('d/m/Y', strtotime((string) $aviso['dataOcorrencia'])) . ' · ' . $aviso['turno']); ?>
                                        </span>
                                    </div>
                                    <h3 class="text-sm font-semibold text-gray-800" data-preserve-case><?php echo appEscape($aviso['titulo']); ?></h3>
                                    <p class="mt-1 line-clamp-2 text-xs text-gray-600" data-preserve-case><?php echo appEscape($aviso['descricao']); ?></p>
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-2 border-t border-gray-200/70 pt-2 text-xs">
                                    <span class="truncate text-gray-500" data-preserve-case>
                                        <?php echo appEscape($aviso['tipoRegistro']); ?>
                                        <?php if (!empty($aviso['nomeAcolhido'])): ?> · <?php echo appEscape($aviso['nomeAcolhido']); ?><?php endif; ?>
                                    </span>
                                    <div class="flex shrink-0 items-center gap-1.5">
                                        <a href="<?php echo appEscape(appTabUrl($urlAtual . '?editar=' . (int) $aviso['idRegistroPlantao'] . '#manutencao')); ?>"
                                           class="rounded border border-elo-teal/30 bg-white px-2 py-1 text-[11px] font-medium text-elo-teal hover:bg-elo-teal/10">
                                            Abrir
                                        </a>
                                        <form method="post">
                                            <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                                            <input type="hidden" name="acao" value="alternarFixado">
                                            <input type="hidden" name="idRegistroPlantao" value="<?php echo (int) $aviso['idRegistroPlantao']; ?>">
                                            <button type="submit" title="Desafixar do mural"
                                                    class="rounded border border-gray-300 bg-white px-2 py-1 text-[11px] font-medium text-gray-600 hover:bg-gray-100">
                                                Desafixar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php include __DIR__ . '/../../../components/tabs-pesquisa-manutencao.php'; ?>

            <section id="painel-manutencao" role="tabpanel" aria-labelledby="tab-manutencao"
                     class="mb-8 rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'manutencao' ? '' : 'hidden'; ?>>
                <h2 class="mb-4 text-lg font-semibold"><?php echo !empty($registroEdicao['idRegistroPlantao']) ? 'Alterar registro do plantão' : 'Novo registro de plantão / aviso'; ?></h2>
                <form method="post" class="space-y-5" data-confirm="Confirma gravar este registro no livro de plantão?">
                    <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="idRegistroPlantao" value="<?php echo (int) ($registroEdicao['idRegistroPlantao'] ?? 0); ?>">

                    <fieldset class="grid gap-4 rounded-lg border border-gray-200 p-4 md:grid-cols-3">
                        <legend class="px-2 text-sm font-semibold text-gray-700">1. Classificação e turno</legend>
                        <label class="text-sm font-medium text-gray-700">Tipo de registro *
                            <select name="tipoRegistro" required class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                                <?php $tipoAtual = (string) ($registroEdicao['tipoRegistro'] ?? 'Passagem de Turno'); ?>
                                <?php foreach ($tiposRegistro as $tipo): ?>
                                    <option value="<?php echo appEscape($tipo); ?>" <?php echo $tipoAtual === $tipo ? 'selected' : ''; ?>>
                                        <?php echo appEscape($tipo); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="text-sm font-medium text-gray-700">Prioridade *
                            <select name="prioridade" required class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                                <?php $prioAtual = (string) ($registroEdicao['prioridade'] ?? 'Normal'); ?>
                                <?php foreach ($prioridades as $prio): ?>
                                    <option value="<?php echo appEscape($prio); ?>" <?php echo $prioAtual === $prio ? 'selected' : ''; ?>>
                                        <?php echo appEscape($prio); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="text-sm font-medium text-gray-700">Status *
                            <select name="statusRegistro" required class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                                <?php $statusAtual = (string) ($registroEdicao['statusRegistro'] ?? 'Aberto'); ?>
                                <?php foreach ($statusList as $st): ?>
                                    <option value="<?php echo appEscape($st); ?>" <?php echo $statusAtual === $st ? 'selected' : ''; ?>>
                                        <?php echo appEscape($st); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="text-sm font-medium text-gray-700">Data *
                            <input type="date" name="dataOcorrencia" required
                                   value="<?php echo appEscape((string) ($registroEdicao['dataOcorrencia'] ?? date('Y-m-d'))); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Turno *
                            <select name="turno" required class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                                <?php $turnoAtual = (string) ($registroEdicao['turno'] ?? 'Manhã'); ?>
                                <?php foreach ($turnos as $t): ?>
                                    <option value="<?php echo appEscape($t); ?>" <?php echo $turnoAtual === $t ? 'selected' : ''; ?>>
                                        <?php echo appEscape($t); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="flex items-center gap-2 pt-6 text-sm font-medium text-gray-700">
                            <input type="checkbox" name="fixado" value="1" <?php echo (int) ($registroEdicao['fixado'] ?? 0) === 1 ? 'checked' : ''; ?>
                                   class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                            Fixar no Mural de Avisos
                        </label>
                    </fieldset>

                    <fieldset class="grid gap-4 rounded-lg border border-gray-200 p-4 md:grid-cols-2">
                        <legend class="px-2 text-sm font-semibold text-gray-700">2. Relato e vínculos</legend>
                        <label class="text-sm font-medium text-gray-700 md:col-span-2">Título *
                            <input name="titulo" required maxlength="150" placeholder="Resumo da passagem de turno, ocorrência ou aviso..."
                                   value="<?php echo appEscape((string) ($registroEdicao['titulo'] ?? '')); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700 md:col-span-2">Descrição detalhada *
                            <textarea name="descricao" required maxlength="5000" rows="4" placeholder="Relate os fatos, orientações para o próximo plantão ou comunicado..."
                                      class="mt-1 w-full rounded border border-gray-300 px-3 py-2"><?php echo appEscape((string) ($registroEdicao['descricao'] ?? '')); ?></textarea>
                        </label>
                        <div class="relative" data-ajax-combo="buscarColaboradores">
                            <label for="colabPlantaoPesquisa" class="block text-sm font-medium text-gray-700">Colaborador / Voluntário responsável (opcional)</label>
                            <span class="mt-1 flex">
                                <input id="colabPlantaoPesquisa" type="search" data-combo-input autocomplete="off"
                                       role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="sugestoes-colab-plantao"
                                       value="<?php echo appEscape($colaboradorLabel); ?>" placeholder="ID, nome ou cargo..."
                                       class="min-w-0 flex-1 rounded-l border border-r-0 border-gray-300 px-3 py-2 text-sm">
                                <button type="button" data-combo-toggle aria-label="Mostrar colaboradores"
                                        class="inline-flex h-[42px] w-10 shrink-0 items-center justify-center rounded-r border border-gray-300 bg-gray-50 text-gray-600 hover:bg-gray-100">
                                    <i class="ph ph-caret-down" aria-hidden="true"></i>
                                </button>
                            </span>
                            <input type="hidden" name="idColaborador" data-combo-id value="<?php echo $idColaboradorForm ?: ''; ?>">
                            <div id="sugestoes-colab-plantao" data-combo-results data-preserve-case role="listbox" class="absolute z-20 mt-1 hidden max-h-56 w-full overflow-y-auto rounded border border-gray-200 bg-white shadow-lg"></div>
                            <p data-combo-message role="status" aria-live="polite" class="mt-1 text-xs text-gray-500">Abra a lista ou digite ID/nome.</p>
                        </div>
                        <div class="relative" data-ajax-combo="buscarAcolhidos">
                            <label for="acolhidoPlantaoPesquisa" class="block text-sm font-medium text-gray-700">Acolhido relacionado (opcional)</label>
                            <span class="mt-1 flex">
                                <input id="acolhidoPlantaoPesquisa" type="search" data-combo-input autocomplete="off"
                                       role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="sugestoes-acolhido-plantao"
                                       value="<?php echo appEscape($acolhidoLabel); ?>" placeholder="ID ou nome do acolhido..."
                                       class="min-w-0 flex-1 rounded-l border border-r-0 border-gray-300 px-3 py-2 text-sm">
                                <button type="button" data-combo-toggle aria-label="Mostrar acolhidos"
                                        class="inline-flex h-[42px] w-10 shrink-0 items-center justify-center rounded-r border border-gray-300 bg-gray-50 text-gray-600 hover:bg-gray-100">
                                    <i class="ph ph-caret-down" aria-hidden="true"></i>
                                </button>
                            </span>
                            <input type="hidden" name="idAcolhido" data-combo-id value="<?php echo $idAcolhidoForm ?: ''; ?>">
                            <div id="sugestoes-acolhido-plantao" data-combo-results data-preserve-case role="listbox" class="absolute z-20 mt-1 hidden max-h-56 w-full overflow-y-auto rounded border border-gray-200 bg-white shadow-lg"></div>
                            <p data-combo-message role="status" aria-live="polite" class="mt-1 text-xs text-gray-500">Abra a lista ou digite ID/nome.</p>
                        </div>
                    </fieldset>

                    <fieldset class="grid gap-4 rounded-lg border border-gray-200 p-4 md:grid-cols-3">
                        <legend class="px-2 text-sm font-semibold text-gray-700">3. Encaminhamento / Resolução e controle</legend>
                        <label class="text-sm font-medium text-gray-700 md:col-span-3">Observação de encaminhamento / resolução
                            <textarea name="observacaoResolucao" maxlength="500" rows="2" placeholder="Registre a providência tomada ou o desfecho..."
                                      class="mt-1 w-full rounded border border-gray-300 px-3 py-2"><?php echo appEscape((string) ($registroEdicao['observacaoResolucao'] ?? '')); ?></textarea>
                        </label>
                        <label class="text-sm font-medium text-gray-700">Criado por
                            <input value="<?php echo appEscape($criadoPor); ?>" disabled class="mt-1 w-full cursor-not-allowed rounded border border-gray-200 bg-gray-100 px-3 py-2 text-gray-600">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Última alteração por
                            <input value="<?php echo appEscape($alteradoPor); ?>" disabled class="mt-1 w-full cursor-not-allowed rounded border border-gray-200 bg-gray-100 px-3 py-2 text-gray-600">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Resolvido por
                            <input value="<?php echo appEscape($resolvidoPorTexto); ?>" disabled class="mt-1 w-full cursor-not-allowed rounded border border-gray-200 bg-gray-100 px-3 py-2 text-gray-600">
                        </label>
                    </fieldset>

                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" class="rounded bg-elo-teal px-5 py-2.5 text-sm font-medium text-white hover:bg-elo-green">
                            <?php echo !empty($registroEdicao['idRegistroPlantao']) ? 'Salvar alterações' : 'Registrar no livro'; ?>
                        </button>
                        <?php if (!empty($registroEdicao['idRegistroPlantao'])): ?>
                            <a href="<?php echo appEscape(appTabUrl($urlAtual)); ?>" class="rounded border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancelar edição
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section id="painel-pesquisa" role="tabpanel" aria-labelledby="tab-pesquisa"
                     class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'pesquisa' ? '' : 'hidden'; ?>>
                <form method="get" action="<?php echo appEscape($urlAtual); ?>" class="mb-5 space-y-3 rounded-lg border border-gray-200 bg-gray-50/70 p-4">
                    <?php if (appCurrentTabToken() !== null): ?>
                        <input type="hidden" name="tabToken" value="<?php echo appEscape((string) appCurrentTabToken()); ?>">
                    <?php endif; ?>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <label class="text-xs font-medium text-gray-700">Data inicial
                            <input type="date" name="dataInicio" value="<?php echo appEscape($filtroDataInicio); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 bg-white px-2.5 py-2 text-sm">
                        </label>
                        <label class="text-xs font-medium text-gray-700">Data final
                            <input type="date" name="dataFim" value="<?php echo appEscape($filtroDataFim); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 bg-white px-2.5 py-2 text-sm">
                        </label>
                        <label class="text-xs font-medium text-gray-700">Tipo
                            <select name="tipo" class="mt-1 w-full rounded border border-gray-300 bg-white px-2.5 py-2 text-sm">
                                <option value="">Todos</option>
                                <?php foreach ($tiposRegistro as $tipo): ?>
                                    <option value="<?php echo appEscape($tipo); ?>" <?php echo $filtroTipo === $tipo ? 'selected' : ''; ?>><?php echo appEscape($tipo); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="text-xs font-medium text-gray-700">Prioridade
                            <select name="prioridade" class="mt-1 w-full rounded border border-gray-300 bg-white px-2.5 py-2 text-sm">
                                <option value="">Todas</option>
                                <?php foreach ($prioridades as $prio): ?>
                                    <option value="<?php echo appEscape($prio); ?>" <?php echo $filtroPrioridade === $prio ? 'selected' : ''; ?>><?php echo appEscape($prio); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="text-xs font-medium text-gray-700">Status
                            <select name="status" class="mt-1 w-full rounded border border-gray-300 bg-white px-2.5 py-2 text-sm">
                                <option value="">Todos</option>
                                <?php foreach ($statusList as $st): ?>
                                    <option value="<?php echo appEscape($st); ?>" <?php echo $filtroStatus === $st ? 'selected' : ''; ?>><?php echo appEscape($st); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200/80 pt-3">
                        <input data-filter-input="listaPlantao" type="search" placeholder="Pesquisar na lista..." aria-label="Pesquisar registros do plantão"
                               class="w-full max-w-xs rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <?php if ($temFiltros): ?>
                                <a href="<?php echo appEscape(appTabUrl($urlAtual)); ?>"
                                   class="inline-flex items-center gap-1.5 rounded border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    <i class="ph ph-eraser" aria-hidden="true"></i> Limpar
                                </a>
                            <?php endif; ?>
                            <button type="submit" name="modo" value="imprimir"
                                    class="inline-flex items-center gap-1.5 rounded border border-elo-teal bg-white px-3.5 py-2 text-sm font-medium text-elo-teal hover:bg-elo-teal/5">
                                <i class="ph ph-printer" aria-hidden="true"></i> Imprimir
                            </button>
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 rounded border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                                <i class="ph ph-funnel" aria-hidden="true"></i> Filtrar
                            </button>
                            <button type="button" data-tab-open="manutencao"
                                    class="rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">
                                + Novo registro
                            </button>
                        </div>
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1100px] text-left text-sm">
                        <thead class="border-b bg-gray-50 text-gray-600">
                            <tr>
                                <th class="p-3">Data / Turno</th>
                                <th class="p-3">Tipo / Prioridade</th>
                                <th class="p-3">Registro e Vínculos</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Auditoria</th>
                                <th class="p-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="listaPlantao">
                            <?php foreach ($registros as $item): ?>
                                <?php
                                $payload = json_encode([
                                    'id' => (int) $item['idRegistroPlantao'],
                                    'dataTurno' => date('d/m/Y', strtotime((string) $item['dataOcorrencia'])) . ' · ' . $item['turno'],
                                    'tipo' => $item['tipoRegistro'],
                                    'prioridade' => $item['prioridade'],
                                    'status' => $item['statusRegistro'],
                                    'titulo' => $item['titulo'],
                                    'descricao' => $item['descricao'],
                                    'colaborador' => !empty($item['nomeColaborador']) ? $item['nomeColaborador'] . ' (' . $item['cargoColaborador'] . ')' : '—',
                                    'acolhido' => !empty($item['nomeAcolhido']) ? $item['nomeAcolhido'] : '—',
                                    'resolucao' => !empty($item['observacaoResolucao']) ? $item['observacaoResolucao'] : '—',
                                    'criado' => $formatarAuditoria($item['idUsuarioCriacao'], $item['nomeUsuarioCriacao'], $item['criadoEm']),
                                    'resolvido' => $formatarAuditoria($item['resolvidoPor'], $item['nomeResolvidoPor'], $item['resolvidoEm']),
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                                ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="whitespace-nowrap p-3">
                                        <div class="font-medium text-gray-800" data-preserve-case><?php echo appEscape(date('d/m/Y', strtotime((string) $item['dataOcorrencia']))); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo appEscape($item['turno']); ?></div>
                                    </td>
                                    <td class="whitespace-nowrap p-3">
                                        <div class="text-xs font-medium text-gray-700"><?php echo appEscape($item['tipoRegistro']); ?></div>
                                        <span class="mt-1 inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold <?php echo $badgePrioridade($item['prioridade']); ?>">
                                            <?php echo appEscape($item['prioridade']); ?>
                                        </span>
                                    </td>
                                    <td class="p-3" data-preserve-case>
                                        <div class="flex items-center gap-1.5 font-medium text-gray-800">
                                            <?php if ((int) $item['fixado'] === 1): ?>
                                                <i class="ph-fill ph-push-pin text-elo-teal" title="Fixado no mural" aria-hidden="true"></i>
                                            <?php endif; ?>
                                            <span><?php echo appEscape($item['titulo']); ?></span>
                                        </div>
                                        <p class="mt-0.5 line-clamp-2 text-xs text-gray-600"><?php echo appEscape($item['descricao']); ?></p>
                                        <?php if (!empty($item['nomeColaborador']) || !empty($item['nomeAcolhido'])): ?>
                                            <div class="mt-1 flex flex-wrap gap-2 text-[11px] text-gray-500">
                                                <?php if (!empty($item['nomeColaborador'])): ?>
                                                    <span><i class="ph ph-identification-badge align-middle" aria-hidden="true"></i> <?php echo appEscape($item['nomeColaborador']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($item['nomeAcolhido'])): ?>
                                                    <span><i class="ph ph-user align-middle" aria-hidden="true"></i> <?php echo appEscape($item['nomeAcolhido']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="whitespace-nowrap p-3">
                                        <span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium <?php echo $badgeStatus($item['statusRegistro']); ?>">
                                            <?php echo appEscape($item['statusRegistro']); ?>
                                        </span>
                                    </td>
                                    <td class="p-3 text-xs text-gray-500" data-preserve-case>
                                        <div>Criado: <?php echo appEscape($formatarAuditoria($item['idUsuarioCriacao'], $item['nomeUsuarioCriacao'], $item['criadoEm'])); ?></div>
                                        <?php if ($item['resolvidoPor'] !== null): ?>
                                            <div class="text-emerald-700">Resolv.: <?php echo appEscape($formatarAuditoria($item['resolvidoPor'], $item['nomeResolvidoPor'], $item['resolvidoEm'])); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="whitespace-nowrap p-3">
                                        <div class="flex items-center gap-1.5">
                                            <button type="button" data-plantao-open data-plantao-payload="<?php echo appEscape((string) $payload); ?>"
                                                    title="Ver detalhes" aria-label="Ver detalhes"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded border border-gray-200 text-gray-600 hover:bg-gray-100">
                                                <i class="ph ph-eye" aria-hidden="true"></i>
                                            </button>
                                            <form method="post">
                                                <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                                                <input type="hidden" name="acao" value="alternarFixado">
                                                <input type="hidden" name="idRegistroPlantao" value="<?php echo (int) $item['idRegistroPlantao']; ?>">
                                                <button type="submit" title="<?php echo (int) $item['fixado'] === 1 ? 'Desafixar do mural' : 'Fixar no mural'; ?>"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded border <?php echo (int) $item['fixado'] === 1 ? 'border-elo-teal bg-elo-teal/10 text-elo-teal' : 'border-gray-200 text-gray-500 hover:bg-gray-100'; ?>">
                                                    <i class="<?php echo (int) $item['fixado'] === 1 ? 'ph-fill' : 'ph'; ?> ph-push-pin" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                            <?php if ($item['statusRegistro'] !== 'Resolvido'): ?>
                                                <form method="post" data-confirm="Marcar este registro como resolvido?">
                                                    <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                                                    <input type="hidden" name="acao" value="marcarResolvido">
                                                    <input type="hidden" name="idRegistroPlantao" value="<?php echo (int) $item['idRegistroPlantao']; ?>">
                                                    <button type="submit" title="Marcar como resolvido"
                                                            class="inline-flex h-8 w-8 items-center justify-center rounded border border-emerald-200 text-emerald-700 hover:bg-emerald-50">
                                                        <i class="ph ph-check-circle" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <a href="<?php echo appEscape(appTabUrl($urlAtual . '?editar=' . (int) $item['idRegistroPlantao'] . '#manutencao')); ?>"
                                               title="Editar" aria-label="Editar"
                                               class="inline-flex h-8 w-8 items-center justify-center rounded border border-elo-teal/20 text-elo-teal hover:bg-elo-teal/10">
                                                <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                                            </a>
                                            <form method="post" data-confirm="Confirma excluir este registro do livro de plantão?">
                                                <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                                                <input type="hidden" name="acao" value="excluir">
                                                <input type="hidden" name="idRegistroPlantao" value="<?php echo (int) $item['idRegistroPlantao']; ?>">
                                                <button type="submit" title="Excluir" aria-label="Excluir"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded border border-red-200 text-red-600 hover:bg-red-50">
                                                    <i class="ph ph-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$registros): ?>
                                <tr><td colspan="6" class="px-3 py-10 text-center text-gray-500">Nenhum registro encontrado no livro de plantão.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <div id="plantaoDetailModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="plantaoModalTitle">
        <div class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-2xl">
            <button type="button" data-plantao-close class="absolute right-4 top-4 text-gray-400 hover:text-gray-700" aria-label="Fechar">
                <i class="ph ph-x text-2xl" aria-hidden="true"></i>
            </button>
            <h2 id="plantaoModalTitle" class="mb-4 pr-8 text-lg font-semibold text-gray-800" data-preserve-case>Detalhes do Registro</h2>
            <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2" data-preserve-case>
                <div class="rounded-lg bg-gray-50 p-3"><dt class="text-xs font-semibold uppercase text-gray-500">Data / Turno</dt><dd id="pdDataTurno" class="mt-0.5 font-medium text-gray-800"></dd></div>
                <div class="rounded-lg bg-gray-50 p-3"><dt class="text-xs font-semibold uppercase text-gray-500">Classificação</dt><dd id="pdClassificacao" class="mt-0.5 font-medium text-gray-800"></dd></div>
                <div class="rounded-lg bg-gray-50 p-3"><dt class="text-xs font-semibold uppercase text-gray-500">Responsável</dt><dd id="pdColaborador" class="mt-0.5 text-gray-800"></dd></div>
                <div class="rounded-lg bg-gray-50 p-3"><dt class="text-xs font-semibold uppercase text-gray-500">Acolhido</dt><dd id="pdAcolhido" class="mt-0.5 text-gray-800"></dd></div>
                <div class="rounded-lg bg-gray-50 p-3 sm:col-span-2"><dt class="text-xs font-semibold uppercase text-gray-500">Descrição</dt><dd id="pdDescricao" class="mt-1 whitespace-pre-wrap text-gray-800"></dd></div>
                <div class="rounded-lg bg-gray-50 p-3 sm:col-span-2"><dt class="text-xs font-semibold uppercase text-gray-500">Encaminhamento / Resolução</dt><dd id="pdResolucao" class="mt-1 whitespace-pre-wrap text-gray-800"></dd></div>
                <div class="rounded-lg bg-gray-50 p-3"><dt class="text-xs font-semibold uppercase text-gray-500">Registrado por</dt><dd id="pdCriado" class="mt-0.5 text-xs text-gray-700"></dd></div>
                <div class="rounded-lg bg-gray-50 p-3"><dt class="text-xs font-semibold uppercase text-gray-500">Resolvido por</dt><dd id="pdResolvido" class="mt-0.5 text-xs text-gray-700"></dd></div>
            </dl>
            <div class="mt-5 flex justify-end">
                <button type="button" data-plantao-close class="rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">Fechar</button>
            </div>
        </div>
    </div>

    <?php $toastSuccess = $resultado; $toastError = $erro; include __DIR__ . '/../../../components/toast.php'; ?>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
    <script>
        (() => {
            const csrfToken = <?php echo json_encode(appCsrfToken(), JSON_THROW_ON_ERROR); ?>;
            const getTabToken = () => new URLSearchParams(window.location.search).get('tabToken')
                || window.sessionStorage.getItem('sistemaEloTabToken') || '';

            document.querySelectorAll('[data-ajax-combo]').forEach((box) => {
                const action = box.getAttribute('data-ajax-combo');
                const form = box.closest('form');
                const input = box.querySelector('[data-combo-input]');
                const hidden = box.querySelector('[data-combo-id]');
                const toggle = box.querySelector('[data-combo-toggle]');
                const results = box.querySelector('[data-combo-results]');
                const message = box.querySelector('[data-combo-message]');
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
                    message.textContent = 'Selecionado.';
                    close();
                };
                const search = async (browseAll = false) => {
                    ctrl?.abort();
                    ctrl = new AbortController();
                    const body = new FormData();
                    body.set('csrfToken', csrfToken);
                    body.set('tabToken', getTabToken());
                    body.set('acao', action);
                    body.set('busca', browseAll ? '' : input.value.trim());
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
                            p.textContent = 'Nenhum resultado encontrado.';
                            results.append(p);
                            message.textContent = 'Nenhum resultado.';
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
                        message.textContent = `${items.length} opção(ões).`;
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
                    input.setCustomValidity(input.value.trim() === '' ? '' : 'Selecione uma opção na lista ou apague o texto.');
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
                        input.setCustomValidity('Selecione uma opção na lista ou apague o texto.');
                        input.reportValidity();
                        e.preventDefault();
                    }
                });
            });

            const modal = document.getElementById('plantaoDetailModal');
            if (modal) {
                const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val || '—'; };
                const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
                document.querySelectorAll('[data-plantao-open]').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const d = JSON.parse(btn.getAttribute('data-plantao-payload') || '{}');
                        setText('plantaoModalTitle', '#' + (d.id || '') + ' — ' + (d.titulo || ''));
                        setText('pdDataTurno', d.dataTurno);
                        setText('pdClassificacao', `${d.tipo || ''} · ${d.prioridade || ''} · ${d.status || ''}`);
                        setText('pdColaborador', d.colaborador);
                        setText('pdAcolhido', d.acolhido);
                        setText('pdDescricao', d.descricao);
                        setText('pdResolucao', d.resolucao);
                        setText('pdCriado', d.criado);
                        setText('pdResolvido', d.resolvido);
                        modal.classList.remove('hidden');
                        modal.classList.add('flex');
                    });
                });
                modal.querySelectorAll('[data-plantao-close]').forEach((b) => b.addEventListener('click', close));
                modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) close(); });
            }
        })();
    </script>
</body>
</html>
