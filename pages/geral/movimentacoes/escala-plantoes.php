<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('geral.movimentacoes/escala-plantoes');
$pdo = appPdo();
$baseUrl = appBaseUrl();
$urlAtual = $baseUrl . '/pages/geral/movimentacoes/escala-plantoes.php';
$urlColaboradores = $baseUrl . '/pages/geral/cadastros/colaboradores.php';

$turnos = ['Manhã', 'Tarde', 'Noite', 'Integral'];
$statusPermitidos = ['Confirmado', 'Troca', 'Folga', 'Falta', 'Férias'];
$turnosMeta = [
    'Manhã' => ['icone' => 'ph-sun', 'horario' => '07:00 – 13:00', 'bg' => 'bg-amber-50/70', 'badge' => 'bg-amber-100 text-amber-800 border-amber-200', 'accent' => 'border-l-amber-400'],
    'Tarde' => ['icone' => 'ph-sun-horizon', 'horario' => '13:00 – 19:00', 'bg' => 'bg-orange-50/60', 'badge' => 'bg-orange-100 text-orange-800 border-orange-200', 'accent' => 'border-l-orange-400'],
    'Noite' => ['icone' => 'ph-moon-stars', 'horario' => '19:00 – 07:00', 'bg' => 'bg-indigo-50/60', 'badge' => 'bg-indigo-100 text-indigo-800 border-indigo-200', 'accent' => 'border-l-indigo-500'],
    'Integral' => ['icone' => 'ph-clock', 'horario' => 'Plantão 12h / Integral', 'bg' => 'bg-teal-50/60', 'badge' => 'bg-teal-100 text-teal-800 border-teal-200', 'accent' => 'border-l-elo-teal'],
];
$diasSemanaCurtos = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];
$mesesNomes = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
];

$textoPost = static function (string $campo): string {
    $valor = $_POST[$campo] ?? '';
    return is_scalar($valor) ? trim((string) $valor) : '';
};
$validarData = static function (string $data): bool {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
    return $d !== false && $d->format('Y-m-d') === $data;
};
$validarHora = static function (string $hora): ?string {
    if ($hora === '') {
        return null;
    }
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $hora)) {
        throw new InvalidArgumentException('Informe um horário válido no formato HH:MM.');
    }
    return strlen($hora) === 5 ? $hora . ':00' : $hora;
};
$mapearTurnoPadrao = static function (string $turnoColab): string {
    return match ($turnoColab) {
        'Manhã' => 'Manhã',
        'Tarde' => 'Tarde',
        'Noite' => 'Noite',
        default => 'Integral',
    };
};
$urlFotoColaborador = static function (?string $caminhoFoto, int $idColaborador) use ($baseUrl): ?string {
    return !empty($caminhoFoto)
        ? appTabUrl($baseUrl . '/auth/colaborador-foto.php?id=' . $idColaborador)
        : null;
};
$iniciaisNome = static function (string $nome): string {
    $partes = preg_split('/\s+/', trim($nome)) ?: [];
    if (!$partes) {
        return 'EL';
    }
    $a = mb_substr($partes[0], 0, 1, 'UTF-8');
    $b = count($partes) > 1 ? mb_substr($partes[count($partes) - 1], 0, 1, 'UTF-8') : mb_substr($partes[0], 1, 1, 'UTF-8');
    return mb_strtoupper($a . $b, 'UTF-8');
};
$nomeCurto = static function (string $nome): string {
    $partes = preg_split('/\s+/', trim($nome)) ?: [];
    if (count($partes) <= 2) {
        return trim($nome);
    }
    return $partes[0] . ' ' . $partes[count($partes) - 1];
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    header('Content-Type: application/json; charset=UTF-8');
    $acao = $textoPost('acao');

    try {
        if ($acao === 'alocar') {
            $idColaborador = filter_var($_POST['idColaborador'] ?? 0, FILTER_VALIDATE_INT);
            $dataPlantao = $textoPost('dataPlantao');
            $turno = $textoPost('turno');
            $statusEscala = $textoPost('statusEscala') ?: 'Confirmado';
            $horaInicio = $validarHora($textoPost('horaInicio'));
            $horaFim = $validarHora($textoPost('horaFim'));
            $observacoes = $textoPost('observacoes');

            if (!$idColaborador || $idColaborador < 1) {
                throw new InvalidArgumentException('Selecione um colaborador válido.');
            }
            if (!$validarData($dataPlantao)) {
                throw new InvalidArgumentException('Data de plantão inválida.');
            }
            if (!in_array($turno, $turnos, true)) {
                throw new InvalidArgumentException('Turno inválido.');
            }
            if (!in_array($statusEscala, $statusPermitidos, true)) {
                throw new InvalidArgumentException('Status de escala inválido.');
            }
            if (mb_strlen($observacoes, 'UTF-8') > 255) {
                throw new InvalidArgumentException('A observação deve ter até 255 caracteres.');
            }

            $colabStmt = $pdo->prepare(
                'SELECT idColaborador, nomeCompleto, cargoFuncao, tipoVinculo, caminhoFoto, ativo
                 FROM tbColaborador WHERE idColaborador = :id LIMIT 1'
            );
            $colabStmt->execute(['id' => $idColaborador]);
            $colab = $colabStmt->fetch(PDO::FETCH_ASSOC);
            if (!$colab || (int) $colab['ativo'] !== 1) {
                throw new InvalidArgumentException('Apenas colaboradores ativos podem ser escalados.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO tbEscalaPlantao
                    (idColaborador, dataPlantao, turno, statusEscala, horaInicio, horaFim, observacoes, idUsuarioCriacao)
                 VALUES
                    (:idColaborador, :dataPlantao, :turno, :statusEscala, :horaInicio, :horaFim, :observacoes, :idUsuarioCriacao)'
            );
            $stmt->execute([
                'idColaborador' => (int) $idColaborador,
                'dataPlantao' => $dataPlantao,
                'turno' => $turno,
                'statusEscala' => $statusEscala,
                'horaInicio' => $horaInicio,
                'horaFim' => $horaFim,
                'observacoes' => $observacoes !== '' ? $observacoes : null,
                'idUsuarioCriacao' => (int) $usuarioAtual['idUsuario'],
            ]);
            $novoId = (int) $pdo->lastInsertId();

            appRegistrarAuditoria(
                'geral',
                'criar',
                'Plantão escalado: ' . $colab['nomeCompleto'] . ' em ' . date('d/m/Y', strtotime($dataPlantao)) . ' (' . $turno . ').',
                'tbEscalaPlantao',
                $novoId,
                ['idColaborador' => (int) $idColaborador, 'dataPlantao' => $dataPlantao, 'turno' => $turno, 'statusEscala' => $statusEscala],
                $usuarioAtual
            );

            echo json_encode([
                'ok' => true,
                'item' => [
                    'idEscala' => $novoId,
                    'idColaborador' => (int) $colab['idColaborador'],
                    'nomeCompleto' => $colab['nomeCompleto'],
                    'nomeCurto' => $nomeCurto((string) $colab['nomeCompleto']),
                    'iniciais' => $iniciaisNome((string) $colab['nomeCompleto']),
                    'cargoFuncao' => $colab['cargoFuncao'],
                    'tipoVinculo' => $colab['tipoVinculo'],
                    'fotoUrl' => $urlFotoColaborador($colab['caminhoFoto'] ?? null, (int) $colab['idColaborador']),
                    'dataPlantao' => $dataPlantao,
                    'turno' => $turno,
                    'statusEscala' => $statusEscala,
                    'horaInicio' => $horaInicio !== null ? substr($horaInicio, 0, 5) : '',
                    'horaFim' => $horaFim !== null ? substr($horaFim, 0, 5) : '',
                    'observacoes' => $observacoes,
                ],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'mover') {
            $idEscala = filter_var($_POST['idEscala'] ?? 0, FILTER_VALIDATE_INT);
            $dataPlantao = $textoPost('dataPlantao');
            $turno = $textoPost('turno');

            if (!$idEscala || $idEscala < 1 || !$validarData($dataPlantao) || !in_array($turno, $turnos, true)) {
                throw new InvalidArgumentException('Dados inválidos para mover o plantão.');
            }

            $origStmt = $pdo->prepare(
                'SELECT e.idEscala, e.idColaborador, e.dataPlantao, e.turno, c.nomeCompleto
                 FROM tbEscalaPlantao e
                 INNER JOIN tbColaborador c ON c.idColaborador = e.idColaborador
                 WHERE e.idEscala = :id LIMIT 1'
            );
            $origStmt->execute(['id' => $idEscala]);
            $orig = $origStmt->fetch(PDO::FETCH_ASSOC);
            if (!$orig) {
                throw new InvalidArgumentException('Plantão não encontrado.');
            }
            if ($orig['dataPlantao'] === $dataPlantao && $orig['turno'] === $turno) {
                echo json_encode(['ok' => true, 'unchanged' => true], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $upd = $pdo->prepare(
                'UPDATE tbEscalaPlantao
                 SET dataPlantao = :dataPlantao, turno = :turno, idUsuarioAlteracao = :uid
                 WHERE idEscala = :id'
            );
            $upd->execute([
                'dataPlantao' => $dataPlantao,
                'turno' => $turno,
                'uid' => (int) $usuarioAtual['idUsuario'],
                'id' => (int) $idEscala,
            ]);

            appRegistrarAuditoria(
                'geral',
                'editar',
                'Plantão movido: ' . $orig['nomeCompleto'] . ' para ' . date('d/m/Y', strtotime($dataPlantao)) . ' (' . $turno . ').',
                'tbEscalaPlantao',
                (int) $idEscala,
                ['de' => $orig['dataPlantao'] . ' ' . $orig['turno'], 'para' => $dataPlantao . ' ' . $turno],
                $usuarioAtual
            );

            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'atualizar') {
            $idEscala = filter_var($_POST['idEscala'] ?? 0, FILTER_VALIDATE_INT);
            $dataPlantao = $textoPost('dataPlantao');
            $turno = $textoPost('turno');
            $statusEscala = $textoPost('statusEscala');
            $horaInicio = $validarHora($textoPost('horaInicio'));
            $horaFim = $validarHora($textoPost('horaFim'));
            $observacoes = $textoPost('observacoes');

            if (!$idEscala || $idEscala < 1 || !$validarData($dataPlantao)
                || !in_array($turno, $turnos, true) || !in_array($statusEscala, $statusPermitidos, true)) {
                throw new InvalidArgumentException('Confira os dados do plantão.');
            }
            if (mb_strlen($observacoes, 'UTF-8') > 255) {
                throw new InvalidArgumentException('A observação deve ter até 255 caracteres.');
            }

            $origStmt = $pdo->prepare(
                'SELECT e.idEscala, c.nomeCompleto
                 FROM tbEscalaPlantao e
                 INNER JOIN tbColaborador c ON c.idColaborador = e.idColaborador
                 WHERE e.idEscala = :id LIMIT 1'
            );
            $origStmt->execute(['id' => $idEscala]);
            $orig = $origStmt->fetch(PDO::FETCH_ASSOC);
            if (!$orig) {
                throw new InvalidArgumentException('Plantão não encontrado.');
            }

            $upd = $pdo->prepare(
                'UPDATE tbEscalaPlantao
                 SET dataPlantao = :dataPlantao, turno = :turno, statusEscala = :statusEscala,
                     horaInicio = :horaInicio, horaFim = :horaFim, observacoes = :observacoes,
                     idUsuarioAlteracao = :uid
                 WHERE idEscala = :id'
            );
            $upd->execute([
                'dataPlantao' => $dataPlantao,
                'turno' => $turno,
                'statusEscala' => $statusEscala,
                'horaInicio' => $horaInicio,
                'horaFim' => $horaFim,
                'observacoes' => $observacoes !== '' ? $observacoes : null,
                'uid' => (int) $usuarioAtual['idUsuario'],
                'id' => (int) $idEscala,
            ]);

            appRegistrarAuditoria(
                'geral',
                'editar',
                'Plantão atualizado: ' . $orig['nomeCompleto'] . ' em ' . date('d/m/Y', strtotime($dataPlantao)) . ' (' . $turno . ' · ' . $statusEscala . ').',
                'tbEscalaPlantao',
                (int) $idEscala,
                ['dataPlantao' => $dataPlantao, 'turno' => $turno, 'statusEscala' => $statusEscala],
                $usuarioAtual
            );

            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'remover') {
            $idEscala = filter_var($_POST['idEscala'] ?? 0, FILTER_VALIDATE_INT);
            if (!$idEscala || $idEscala < 1) {
                throw new InvalidArgumentException('Registro de escala inválido.');
            }
            $origStmt = $pdo->prepare(
                'SELECT e.idEscala, e.dataPlantao, e.turno, c.nomeCompleto
                 FROM tbEscalaPlantao e
                 INNER JOIN tbColaborador c ON c.idColaborador = e.idColaborador
                 WHERE e.idEscala = :id LIMIT 1'
            );
            $origStmt->execute(['id' => $idEscala]);
            $orig = $origStmt->fetch(PDO::FETCH_ASSOC);
            if (!$orig) {
                throw new InvalidArgumentException('Plantão não encontrado.');
            }
            $del = $pdo->prepare('DELETE FROM tbEscalaPlantao WHERE idEscala = :id');
            $del->execute(['id' => $idEscala]);

            appRegistrarAuditoria(
                'geral',
                'excluir',
                'Plantão removido: ' . $orig['nomeCompleto'] . ' em ' . date('d/m/Y', strtotime((string) $orig['dataPlantao'])) . ' (' . $orig['turno'] . ').',
                'tbEscalaPlantao',
                (int) $idEscala,
                ['dataPlantao' => $orig['dataPlantao'], 'turno' => $orig['turno']],
                $usuarioAtual
            );

            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'copiarSemanaAnterior') {
            $inicioSemana = $textoPost('inicioSemana');
            if (!$validarData($inicioSemana)) {
                throw new InvalidArgumentException('Data de início da semana inválida.');
            }
            $destInicio = new DateTimeImmutable($inicioSemana);
            $origInicio = $destInicio->modify('-7 days');
            $origFim = $origInicio->modify('+6 days');

            $srcStmt = $pdo->prepare(
                'SELECT e.idColaborador, e.dataPlantao, e.turno, e.horaInicio, e.horaFim, e.observacoes
                 FROM tbEscalaPlantao e
                 INNER JOIN tbColaborador c ON c.idColaborador = e.idColaborador
                 WHERE c.ativo = 1
                   AND e.dataPlantao BETWEEN :ini AND :fim
                   AND e.statusEscala IN (\'Confirmado\', \'Troca\')'
            );
            $srcStmt->execute(['ini' => $origInicio->format('Y-m-d'), 'fim' => $origFim->format('Y-m-d')]);
            $rows = $srcStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                throw new InvalidArgumentException('Não há plantões ativos na semana anterior para copiar.');
            }

            $ins = $pdo->prepare(
                'INSERT IGNORE INTO tbEscalaPlantao
                    (idColaborador, dataPlantao, turno, statusEscala, horaInicio, horaFim, observacoes, idUsuarioCriacao)
                 VALUES
                    (:idColaborador, :dataPlantao, :turno, \'Confirmado\', :horaInicio, :horaFim, :observacoes, :uid)'
            );
            $copiados = 0;
            foreach ($rows as $row) {
                $novaData = (new DateTimeImmutable((string) $row['dataPlantao']))->modify('+7 days')->format('Y-m-d');
                $ins->execute([
                    'idColaborador' => (int) $row['idColaborador'],
                    'dataPlantao' => $novaData,
                    'turno' => $row['turno'],
                    'horaInicio' => $row['horaInicio'],
                    'horaFim' => $row['horaFim'],
                    'observacoes' => $row['observacoes'],
                    'uid' => (int) $usuarioAtual['idUsuario'],
                ]);
                $copiados += $ins->rowCount();
            }

            appRegistrarAuditoria(
                'geral',
                'criar',
                'Semana anterior copiada para a semana de ' . $destInicio->format('d/m/Y') . ' (' . $copiados . ' plantão(ões) criado(s)).',
                'tbEscalaPlantao',
                null,
                ['inicioSemana' => $inicioSemana, 'copiados' => $copiados],
                $usuarioAtual
            );

            echo json_encode(['ok' => true, 'copiados' => $copiados], JSON_UNESCAPED_UNICODE);
            exit;
        }

        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (PDOException $e) {
        $code = (int) ($e->errorInfo[1] ?? 0);
        if ($code === 1062) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'message' => 'Este colaborador já está escalado nesse mesmo dia e turno.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        error_log('Erro na escala de plantões: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Não foi possível salvar a alteração na escala.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$visao = is_scalar($_GET['visao'] ?? null) && $_GET['visao'] === 'mensal' ? 'mensal' : 'semanal';
$hoje = new DateTimeImmutable('today');
$hojeIso = $hoje->format('Y-m-d');
$refInput = is_scalar($_GET['data'] ?? null) ? trim((string) $_GET['data']) : '';
$dataRef = $validarData($refInput) ? new DateTimeImmutable($refInput) : $hoje;

if ($visao === 'semanal') {
    $diaSemana = (int) $dataRef->format('N'); // 1=Seg .. 7=Dom
    $periodoInicio = $dataRef->modify('-' . ($diaSemana - 1) . ' days');
    $periodoFim = $periodoInicio->modify('+6 days');
    $anteriorRef = $periodoInicio->modify('-7 days')->format('Y-m-d');
    $proximoRef = $periodoInicio->modify('+7 days')->format('Y-m-d');
    $tituloPeriodo = $periodoInicio->format('d/m') . ' a ' . $periodoFim->format('d/m/Y');
    $diasGrade = [];
    for ($d = 0; $d < 7; $d++) {
        $diasGrade[] = $periodoInicio->modify('+' . $d . ' days');
    }
} else {
    $periodoInicio = $dataRef->modify('first day of this month');
    $periodoFim = $dataRef->modify('last day of this month');
    $anteriorRef = $periodoInicio->modify('-1 month')->format('Y-m-d');
    $proximoRef = $periodoInicio->modify('+1 month')->format('Y-m-d');
    $tituloPeriodo = $mesesNomes[(int) $periodoInicio->format('n')] . ' de ' . $periodoInicio->format('Y');
    $primeiroDiaSem = (int) $periodoInicio->format('N');
    $gradeInicio = $periodoInicio->modify('-' . ($primeiroDiaSem - 1) . ' days');
    $ultimoDiaSem = (int) $periodoFim->format('N');
    $gradeFim = $periodoFim->modify('+' . (7 - $ultimoDiaSem) . ' days');
    $diasGrade = [];
    $cursor = $gradeInicio;
    while ($cursor <= $gradeFim) {
        $diasGrade[] = $cursor;
        $cursor = $cursor->modify('+1 day');
    }
}

$colaboradoresAtivos = $pdo->query(
    'SELECT idColaborador, nomeCompleto, tipoVinculo, cargoFuncao, turno, caminhoFoto
     FROM tbColaborador
     WHERE ativo = 1
     ORDER BY nomeCompleto'
)->fetchAll(PDO::FETCH_ASSOC);

$escalaStmt = $pdo->prepare(
    'SELECT e.idEscala, e.idColaborador, e.dataPlantao, e.turno, e.statusEscala,
            e.horaInicio, e.horaFim, e.observacoes,
            c.nomeCompleto, c.cargoFuncao, c.tipoVinculo, c.caminhoFoto, c.ativo AS colaboradorAtivo
     FROM tbEscalaPlantao e
     INNER JOIN tbColaborador c ON c.idColaborador = e.idColaborador
     WHERE e.dataPlantao BETWEEN :ini AND :fim
     ORDER BY e.dataPlantao, FIELD(e.turno, \'Manhã\', \'Tarde\', \'Noite\', \'Integral\'), c.nomeCompleto'
);
$escalaStmt->execute([
    'ini' => $periodoInicio->format('Y-m-d'),
    'fim' => $periodoFim->format('Y-m-d'),
]);
$escalaRows = $escalaStmt->fetchAll(PDO::FETCH_ASSOC);

$escalaPayload = array_map(static function (array $row) use ($urlFotoColaborador, $iniciaisNome, $nomeCurto): array {
    return [
        'idEscala' => (int) $row['idEscala'],
        'idColaborador' => (int) $row['idColaborador'],
        'nomeCompleto' => (string) $row['nomeCompleto'],
        'nomeCurto' => $nomeCurto((string) $row['nomeCompleto']),
        'iniciais' => $iniciaisNome((string) $row['nomeCompleto']),
        'cargoFuncao' => (string) $row['cargoFuncao'],
        'tipoVinculo' => (string) $row['tipoVinculo'],
        'fotoUrl' => $urlFotoColaborador($row['caminhoFoto'] ?? null, (int) $row['idColaborador']),
        'dataPlantao' => (string) $row['dataPlantao'],
        'turno' => (string) $row['turno'],
        'statusEscala' => (string) $row['statusEscala'],
        'horaInicio' => !empty($row['horaInicio']) ? substr((string) $row['horaInicio'], 0, 5) : '',
        'horaFim' => !empty($row['horaFim']) ? substr((string) $row['horaFim'], 0, 5) : '',
        'observacoes' => (string) ($row['observacoes'] ?? ''),
    ];
}, $escalaRows);

$colaboradoresPayload = array_map(static function (array $c) use ($urlFotoColaborador, $iniciaisNome, $nomeCurto, $mapearTurnoPadrao): array {
    return [
        'idColaborador' => (int) $c['idColaborador'],
        'nomeCompleto' => (string) $c['nomeCompleto'],
        'nomeCurto' => $nomeCurto((string) $c['nomeCompleto']),
        'iniciais' => $iniciaisNome((string) $c['nomeCompleto']),
        'tipoVinculo' => (string) $c['tipoVinculo'],
        'cargoFuncao' => (string) $c['cargoFuncao'],
        'turnoOriginal' => (string) $c['turno'],
        'turnoPadrao' => $mapearTurnoPadrao((string) $c['turno']),
        'fotoUrl' => $urlFotoColaborador($c['caminhoFoto'] ?? null, (int) $c['idColaborador']),
    ];
}, $colaboradoresAtivos);

$instituicao = $pdo->query('SELECT nomeFantasia, razaoSocial FROM tbInformacoesInstituicao WHERE idInformacoes = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
$nomeInstituicao = trim((string) ($instituicao['nomeFantasia'] ?? '')) ?: trim((string) ($instituicao['razaoSocial'] ?? '')) ?: 'Sistema Elo';
$podeEmitirCracha = appCanAccess('geral.cadastros/colaboradores', $usuarioAtual);
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
    <style>
        .dropzone-active {
            outline: 2px dashed #007c89 !important;
            outline-offset: -2px;
            background-color: rgba(0, 124, 137, 0.08) !important;
        }
        .dragging-card {
            opacity: 0.45;
            transform: scale(0.98);
        }
        dialog::backdrop {
            background: rgba(15, 23, 42, 0.48);
        }
        @page {
            size: A4 landscape;
            margin: 8mm;
        }
        @media print {
            html, body {
                width: 100%;
                height: auto !important;
                overflow: visible !important;
                background: #ffffff !important;
                color: #111827 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            body > nav, body > div > aside, .no-print, .fixed {
                display: none !important;
            }
            body > div, main {
                display: block !important;
                width: 100% !important;
                height: auto !important;
                overflow: visible !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .print-only {
                display: block !important;
            }
            .schedule-layout {
                display: block !important;
            }
            .schedule-table {
                width: 100% !important;
                min-width: 0 !important;
                table-layout: fixed;
                font-size: 7.5pt !important;
            }
            .schedule-table th, .schedule-table td {
                padding: 1.5mm !important;
                border-color: #cbd5e1 !important;
            }
        }
    </style>
</head>
<body class="flex h-screen flex-col overflow-hidden bg-slate-50 font-sans text-slate-800">
    <?php include __DIR__ . '/../../../components/geral/navbar-geral.php'; ?>
    <div class="flex min-h-0 flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../../components/geral/sidebar-geral.php'; ?>
        <main class="min-w-0 flex-1 overflow-y-auto bg-gray-50 p-4 md:p-6">
            <div class="space-y-5">
                <!-- Cabeçalho de tela -->
                <header class="no-print flex flex-wrap items-center justify-between gap-3">
                    <h1 class="text-2xl font-bold text-gray-800">Escala de Plantões</h1>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="inline-flex rounded-lg border border-gray-300 bg-white p-0.5 text-xs font-medium shadow-sm">
                            <a href="<?php echo appEscape(appTabUrl($urlAtual . '?visao=semanal&data=' . $dataRef->format('Y-m-d'))); ?>"
                               class="rounded-md px-3 py-1.5 transition <?php echo $visao === 'semanal' ? 'bg-elo-teal text-white' : 'text-gray-600 hover:text-gray-900'; ?>">
                                Mapa Semanal
                            </a>
                            <a href="<?php echo appEscape(appTabUrl($urlAtual . '?visao=mensal&data=' . $dataRef->format('Y-m-d'))); ?>"
                               class="rounded-md px-3 py-1.5 transition <?php echo $visao === 'mensal' ? 'bg-elo-teal text-white' : 'text-gray-600 hover:text-gray-900'; ?>">
                                Mapa Mensal
                            </a>
                        </div>
                        <?php if ($visao === 'semanal'): ?>
                            <button type="button" id="btnCopiarSemana"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-elo-teal/40 bg-white px-3 py-2 text-xs font-medium text-elo-teal shadow-sm hover:bg-elo-teal/5">
                                <i class="ph ph-copy text-base" aria-hidden="true"></i> Copiar semana anterior
                            </button>
                        <?php endif; ?>
                        <button type="button" onclick="window.print()"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-elo-teal px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-elo-green">
                            <i class="ph ph-printer text-base" aria-hidden="true"></i> Imprimir mapa
                        </button>
                    </div>
                </header>

                <!-- Cabeçalho de impressão -->
                <div class="print-only hidden border-b-2 border-slate-800 pb-2 mb-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wider text-slate-500"><?php echo appEscape($nomeInstituicao); ?> · Sistema Elo</div>
                            <h2 class="text-lg font-bold text-slate-900">Mapa de Escala de Plantões — <?php echo appEscape($tituloPeriodo); ?></h2>
                        </div>
                        <div class="text-right text-xs text-slate-600">
                            Emitido em <?php echo date('d/m/Y H:i'); ?>
                        </div>
                    </div>
                </div>

                <!-- Barra de navegação do período + KPIs -->
                <section class="no-print grid gap-3 xl:grid-cols-12">
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm xl:col-span-5">
                        <div class="flex items-center gap-1.5">
                            <a href="<?php echo appEscape(appTabUrl($urlAtual . '?visao=' . $visao . '&data=' . $anteriorRef)); ?>"
                               aria-label="Período anterior"
                               class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50">
                                <i class="ph ph-caret-left" aria-hidden="true"></i>
                            </a>
                            <a href="<?php echo appEscape(appTabUrl($urlAtual . '?visao=' . $visao . '&data=' . $hojeIso)); ?>"
                               class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                Hoje
                            </a>
                            <a href="<?php echo appEscape(appTabUrl($urlAtual . '?visao=' . $visao . '&data=' . $proximoRef)); ?>"
                               aria-label="Próximo período"
                               class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50">
                                <i class="ph ph-caret-right" aria-hidden="true"></i>
                            </a>
                        </div>
                        <div class="text-sm font-bold text-slate-800" data-preserve-case><?php echo appEscape($tituloPeriodo); ?></div>
                        <form method="get" class="flex items-center">
                            <input type="hidden" name="tabToken" value="<?php echo appEscape((string) appCurrentTabToken()); ?>">
                            <input type="hidden" name="visao" value="<?php echo appEscape($visao); ?>">
                            <input type="date" name="data" value="<?php echo appEscape($dataRef->format('Y-m-d')); ?>" onchange="this.form.submit()"
                                   aria-label="Ir para data"
                                   class="rounded-lg border border-gray-200 px-2 py-1 text-xs text-gray-700">
                        </form>
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:col-span-7">
                        <div class="rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 shadow-sm">
                            <p class="text-[11px] font-medium text-gray-500">Plantões no mapa</p>
                            <p id="kpiTotalPlantoes" class="mt-0.5 text-xl font-bold tabular-nums text-slate-900">0</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 shadow-sm">
                            <p class="text-[11px] font-medium text-gray-500">Equipe escalada</p>
                            <p id="kpiEquipeEscalada" class="mt-0.5 text-xl font-bold tabular-nums text-elo-teal">0</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 shadow-sm">
                            <p class="text-[11px] font-medium text-gray-500"><?php echo $visao === 'semanal' ? 'Turnos descobertos' : 'Dias descobertos'; ?></p>
                            <p id="kpiDescobertos" class="mt-0.5 text-xl font-bold tabular-nums text-amber-600">0</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 shadow-sm">
                            <p class="text-[11px] font-medium text-gray-500">Trocas / Ausências</p>
                            <p id="kpiOcorrencias" class="mt-0.5 text-xl font-bold tabular-nums text-rose-600">0</p>
                        </div>
                    </div>
                </section>

                <!-- Área principal: Dock lateral + Mapa -->
                <div class="schedule-layout grid gap-4 lg:grid-cols-[270px_minmax(0,1fr)]">
                    <!-- Dock da Equipe -->
                    <aside class="no-print flex flex-col rounded-2xl border border-gray-200 bg-white shadow-sm lg:max-h-[calc(100vh-245px)]">
                        <div class="border-b border-gray-100 p-3.5">
                            <div class="flex items-center justify-between gap-2">
                                <h2 class="flex items-center gap-1.5 text-sm font-bold text-slate-800">
                                    <i class="ph ph-users-three text-lg text-elo-teal" aria-hidden="true"></i>
                                    Equipe Ativa
                                </h2>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600"><?php echo count($colaboradoresAtivos); ?></span>
                            </div>
                            <input type="search" id="filtroDockEquipe" placeholder="Buscar por nome, cargo ou turno..."
                                   class="mt-2.5 w-full rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs focus:border-elo-teal focus:outline-none">
                            <div class="mt-2 flex flex-wrap gap-1">
                                <button type="button" data-dock-filter="Todos" class="dock-filter-btn rounded-full bg-elo-teal px-2.5 py-0.5 text-[11px] font-medium text-white">Todos</button>
                                <button type="button" data-dock-filter="Colaborador" class="dock-filter-btn rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-medium text-slate-600 hover:bg-slate-200">Equipe</button>
                                <button type="button" data-dock-filter="Voluntário" class="dock-filter-btn rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-medium text-slate-600 hover:bg-slate-200">Voluntários</button>
                            </div>
                        </div>

                        <div id="dockListaColaboradores" class="flex-1 space-y-2 overflow-y-auto p-3">
                            <?php if (!$colaboradoresPayload): ?>
                                <p class="rounded-lg bg-slate-50 p-4 text-center text-xs text-slate-500">
                                    Nenhum colaborador ativo cadastrado ainda.
                                </p>
                            <?php else: ?>
                                <?php foreach ($colaboradoresPayload as $c): ?>
                                    <div draggable="true"
                                         data-dock-card
                                         data-colab-id="<?php echo $c['idColaborador']; ?>"
                                         data-colab-vinculo="<?php echo appEscape($c['tipoVinculo']); ?>"
                                         data-colab-search="<?php echo appEscape(mb_strtolower($c['nomeCompleto'] . ' ' . $c['cargoFuncao'] . ' ' . $c['turnoOriginal'] . ' ' . $c['tipoVinculo'], 'UTF-8')); ?>"
                                         class="group flex cursor-grab items-center gap-2.5 rounded-xl border border-slate-200 bg-white p-2 shadow-xs transition hover:border-elo-teal/60 hover:bg-teal-50/30 active:cursor-grabbing">
                                        <?php if ($c['fotoUrl'] !== null): ?>
                                            <img src="<?php echo appEscape($c['fotoUrl']); ?>" alt="" class="h-9 w-9 shrink-0 rounded-full border border-slate-200 object-cover pointer-events-none">
                                        <?php else: ?>
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-elo-teal/10 text-xs font-bold text-elo-teal pointer-events-none">
                                                <?php echo appEscape($c['iniciais']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <div class="min-w-0 flex-1 pointer-events-none">
                                            <div class="truncate text-xs font-semibold text-slate-800" data-preserve-case><?php echo appEscape($c['nomeCurto']); ?></div>
                                            <div class="truncate text-[11px] text-slate-500" data-preserve-case><?php echo appEscape($c['cargoFuncao']); ?></div>
                                            <div class="mt-0.5 flex items-center gap-1.5 text-[10px] text-slate-400">
                                                <span><?php echo appEscape($c['turnoOriginal']); ?></span>
                                                <span>·</span>
                                                <span data-colab-count="<?php echo $c['idColaborador']; ?>" class="font-semibold text-elo-teal">0x</span>
                                            </div>
                                        </div>
                                        <i class="ph ph-dots-six-vertical text-slate-300 group-hover:text-elo-teal pointer-events-none" aria-hidden="true"></i>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="border-t border-gray-100 bg-slate-50/80 px-3.5 py-2.5 text-[11px] text-slate-500">
                            <i class="ph ph-hand-grabbing mr-1 text-elo-teal" aria-hidden="true"></i>
                            Arraste um cartão para o mapa ou clique em <strong>+</strong> em qualquer célula.
                        </div>
                    </aside>

                    <!-- Mapa de Escala -->
                    <section class="min-w-0 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                        <?php if ($visao === 'semanal'): ?>
                            <div class="overflow-x-auto">
                                <table class="schedule-table w-full min-w-[920px] border-collapse">
                                    <thead>
                                        <tr class="border-b border-slate-200 bg-slate-50">
                                            <th class="w-32 border-r border-slate-200 p-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Turno</th>
                                            <?php foreach ($diasGrade as $diaObj): ?>
                                                <?php
                                                $iso = $diaObj->format('Y-m-d');
                                                $ehHoje = $iso === $hojeIso;
                                                $ehFds = (int) $diaObj->format('N') >= 6;
                                                ?>
                                                <th class="border-r border-slate-200 p-2.5 text-center last:border-r-0 <?php echo $ehHoje ? 'bg-elo-teal/10' : ($ehFds ? 'bg-slate-100/70' : ''); ?>">
                                                    <div class="text-[11px] font-semibold uppercase tracking-wide <?php echo $ehHoje ? 'text-elo-teal' : 'text-slate-500'; ?>">
                                                        <?php echo $diasSemanaCurtos[(int) $diaObj->format('N')]; ?>
                                                    </div>
                                                    <div class="mt-0.5 inline-flex items-center gap-1 text-sm font-bold <?php echo $ehHoje ? 'rounded-full bg-elo-teal px-2 py-0.5 text-white' : 'text-slate-800'; ?>">
                                                        <?php echo $diaObj->format('d/m'); ?>
                                                    </div>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        <?php foreach ($turnos as $turno): ?>
                                            <?php $meta = $turnosMeta[$turno]; ?>
                                            <tr>
                                                <th scope="row" class="border-r border-slate-200 p-3 text-left align-top <?php echo $meta['bg']; ?>">
                                                    <div class="flex items-center gap-1.5 text-xs font-bold text-slate-800">
                                                        <i class="ph <?php echo $meta['icone']; ?> text-base text-elo-teal" aria-hidden="true"></i>
                                                        <?php echo appEscape($turno); ?>
                                                    </div>
                                                    <div class="mt-1 text-[10px] font-medium text-slate-500"><?php echo appEscape($meta['horario']); ?></div>
                                                </th>
                                                <?php foreach ($diasGrade as $diaObj): ?>
                                                    <?php
                                                    $iso = $diaObj->format('Y-m-d');
                                                    $ehHoje = $iso === $hojeIso;
                                                    $ehFds = (int) $diaObj->format('N') >= 6;
                                                    ?>
                                                    <td data-drop-cell
                                                        data-date="<?php echo appEscape($iso); ?>"
                                                        data-turno="<?php echo appEscape($turno); ?>"
                                                        class="group relative h-32 border-r border-slate-200 p-1.5 align-top transition-colors last:border-r-0 <?php echo $ehHoje ? 'bg-teal-50/25' : ($ehFds ? 'bg-slate-50/70' : 'bg-white'); ?>">
                                                        <div class="flex items-center justify-between gap-1 pb-1">
                                                            <span data-cell-badge class="text-[10px] font-medium text-slate-400"></span>
                                                            <button type="button" data-quick-add data-date="<?php echo appEscape($iso); ?>" data-turno="<?php echo appEscape($turno); ?>"
                                                                    title="Adicionar plantonista em <?php echo appEscape($diaObj->format('d/m') . ' · ' . $turno); ?>"
                                                                    class="no-print inline-flex h-5 w-5 items-center justify-center rounded border border-slate-200 bg-white text-slate-400 opacity-70 transition hover:border-elo-teal hover:bg-elo-teal hover:text-white group-hover:opacity-100">
                                                                <i class="ph ph-plus text-xs" aria-hidden="true"></i>
                                                            </button>
                                                        </div>
                                                        <div data-cell-items class="space-y-1.5"></div>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="schedule-table w-full min-w-[880px] border-collapse">
                                    <thead>
                                        <tr class="border-b border-slate-200 bg-slate-50">
                                            <?php foreach ($diasSemanaCurtos as $diaSigla): ?>
                                                <th class="w-[14.28%] border-r border-slate-200 p-2 text-center text-xs font-bold uppercase tracking-wider text-slate-500 last:border-r-0">
                                                    <?php echo $diaSigla; ?>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        <?php foreach (array_chunk($diasGrade, 7) as $semanaDias): ?>
                                            <tr>
                                                <?php foreach ($semanaDias as $diaObj): ?>
                                                    <?php
                                                    $iso = $diaObj->format('Y-m-d');
                                                    $mesmoMes = $diaObj->format('Y-m') === $periodoInicio->format('Y-m');
                                                    $ehHoje = $iso === $hojeIso;
                                                    ?>
                                                    <td <?php echo $mesmoMes ? 'data-drop-day data-date="' . appEscape($iso) . '"' : ''; ?>
                                                        class="group h-28 border-r border-slate-200 p-1.5 align-top last:border-r-0 <?php echo !$mesmoMes ? 'bg-slate-100/70 opacity-50' : ($ehHoje ? 'bg-teal-50/35' : 'bg-white'); ?>">
                                                        <div class="mb-1 flex items-center justify-between">
                                                            <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-xs font-bold <?php echo $ehHoje ? 'bg-elo-teal text-white' : 'text-slate-700'; ?>">
                                                                <?php echo $diaObj->format('d'); ?>
                                                            </span>
                                                            <?php if ($mesmoMes): ?>
                                                                <button type="button" data-quick-add data-date="<?php echo appEscape($iso); ?>" data-turno="Manhã"
                                                                        title="Adicionar plantonista em <?php echo appEscape($diaObj->format('d/m/Y')); ?>"
                                                                        class="no-print inline-flex h-5 w-5 items-center justify-center rounded border border-slate-200 bg-white text-slate-400 opacity-70 hover:border-elo-teal hover:bg-elo-teal hover:text-white group-hover:opacity-100">
                                                                    <i class="ph ph-plus text-xs" aria-hidden="true"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if ($mesmoMes): ?>
                                                            <div data-day-items="<?php echo appEscape($iso); ?>" class="space-y-1"></div>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <!-- Legenda -->
                        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/70 px-4 py-2.5 text-[11px] text-slate-600">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Confirmado</span>
                                <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-sky-500"></span> Troca</span>
                                <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-slate-400"></span> Folga</span>
                                <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span> Falta</span>
                                <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-purple-500"></span> Férias</span>
                            </div>
                            <span class="text-slate-400">Clique em qualquer cartão escalado para editar status, horário ou observações.</span>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal de Alocação Rápida -->
    <dialog id="modalAlocar" class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-0 shadow-xl">
        <form id="formAlocar" class="p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h2 class="text-base font-bold text-slate-800">Escalar colaborador</h2>
                <button type="button" data-close-dialog class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <i class="ph ph-x text-lg" aria-hidden="true"></i>
                </button>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <label class="text-xs font-medium text-slate-700">Data *
                    <input type="date" name="dataPlantao" id="alocarData" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>
                <label class="text-xs font-medium text-slate-700">Turno *
                    <select name="turno" id="alocarTurno" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <?php foreach ($turnos as $t): ?>
                            <option value="<?php echo appEscape($t); ?>"><?php echo appEscape($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label class="block text-xs font-medium text-slate-700">Colaborador / Voluntário *
                <select name="idColaborador" id="alocarColaborador" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">Selecione...</option>
                    <?php foreach ($colaboradoresPayload as $c): ?>
                        <option value="<?php echo $c['idColaborador']; ?>">
                            <?php echo appEscape($c['nomeCompleto'] . ' — ' . $c['cargoFuncao'] . ' (' . $c['turnoOriginal'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="grid grid-cols-3 gap-3">
                <label class="text-xs font-medium text-slate-700">Status
                    <select name="statusEscala" id="alocarStatus" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm">
                        <?php foreach ($statusPermitidos as $st): ?>
                            <option value="<?php echo appEscape($st); ?>"><?php echo appEscape($st); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-700">Início (opc.)
                    <input type="time" name="horaInicio" id="alocarHoraInicio" class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-2 text-sm">
                </label>
                <label class="text-xs font-medium text-slate-700">Fim (opc.)
                    <input type="time" name="horaFim" id="alocarHoraFim" class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-2 text-sm">
                </label>
            </div>
            <label class="block text-xs font-medium text-slate-700">Observação (opcional)
                <input type="text" name="observacoes" id="alocarObs" maxlength="255" placeholder="Ex.: Cobertura 12x36, troca com fulano..."
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </label>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" data-close-dialog class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">Cancelar</button>
                <button type="submit" class="rounded-lg bg-elo-teal px-4 py-2 text-xs font-semibold text-white hover:bg-elo-green">Salvar na escala</button>
            </div>
        </form>
    </dialog>

    <!-- Modal de Edição / Detalhes do Plantão -->
    <dialog id="modalEditar" class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-0 shadow-xl">
        <form id="formEditar" class="p-5 space-y-4">
            <input type="hidden" name="idEscala" id="editarIdEscala">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div id="editarAvatar" class="h-10 w-10 shrink-0 overflow-hidden rounded-full bg-elo-teal/10 flex items-center justify-center text-xs font-bold text-elo-teal"></div>
                    <div class="min-w-0">
                        <h2 id="editarNome" class="truncate text-sm font-bold text-slate-800" data-preserve-case></h2>
                        <p id="editarCargo" class="truncate text-xs text-slate-500" data-preserve-case></p>
                    </div>
                </div>
                <button type="button" data-close-dialog class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <i class="ph ph-x text-lg" aria-hidden="true"></i>
                </button>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <label class="text-xs font-medium text-slate-700">Data *
                    <input type="date" name="dataPlantao" id="editarData" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>
                <label class="text-xs font-medium text-slate-700">Turno *
                    <select name="turno" id="editarTurno" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <?php foreach ($turnos as $t): ?>
                            <option value="<?php echo appEscape($t); ?>"><?php echo appEscape($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <label class="text-xs font-medium text-slate-700">Status *
                    <select name="statusEscala" id="editarStatus" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm">
                        <?php foreach ($statusPermitidos as $st): ?>
                            <option value="<?php echo appEscape($st); ?>"><?php echo appEscape($st); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-700">Início
                    <input type="time" name="horaInicio" id="editarHoraInicio" class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-2 text-sm">
                </label>
                <label class="text-xs font-medium text-slate-700">Fim
                    <input type="time" name="horaFim" id="editarHoraFim" class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-2 text-sm">
                </label>
            </div>
            <label class="block text-xs font-medium text-slate-700">Observação
                <input type="text" name="observacoes" id="editarObs" maxlength="255" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </label>
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
                <div class="flex items-center gap-2">
                    <button type="button" id="btnRemoverEscala" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700 hover:bg-rose-100">
                        <i class="ph ph-trash" aria-hidden="true"></i> Remover
                    </button>
                    <?php if ($podeEmitirCracha): ?>
                        <a id="linkCrachaModal" href="#" class="inline-flex items-center gap-1 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-medium text-sky-700 hover:bg-sky-100">
                            <i class="ph ph-identification-card" aria-hidden="true"></i> Crachá
                        </a>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" data-close-dialog class="rounded-lg border border-slate-300 px-3.5 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">Fechar</button>
                    <button type="submit" class="rounded-lg bg-elo-teal px-4 py-2 text-xs font-semibold text-white hover:bg-elo-green">Salvar</button>
                </div>
            </div>
        </form>
    </dialog>

    <!-- Toast flutuante AJAX -->
    <div id="escalaToast" role="status" aria-live="polite"
         class="no-print pointer-events-none fixed bottom-5 right-5 z-50 hidden max-w-sm rounded-xl px-4 py-3 text-xs font-medium text-white shadow-lg"></div>

    <script src="<?php echo appEscape($baseUrl . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
    <script>
        (() => {
            const csrfToken = <?php echo json_encode(appCsrfToken(), JSON_THROW_ON_ERROR); ?>;
            const visao = <?php echo json_encode($visao, JSON_THROW_ON_ERROR); ?>;
            const inicioSemana = <?php echo json_encode($periodoInicio->format('Y-m-d'), JSON_THROW_ON_ERROR); ?>;
            const urlCrachaBase = <?php echo json_encode($urlColaboradores, JSON_THROW_ON_ERROR); ?>;
            const colaboradores = <?php echo json_encode($colaboradoresPayload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); ?>;
            let escalaItens = <?php echo json_encode($escalaPayload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); ?>;

            const colabMap = new Map(colaboradores.map((c) => [c.idColaborador, c]));
            const getTabToken = () => new URLSearchParams(window.location.search).get('tabToken')
                || window.sessionStorage.getItem('sistemaEloTabToken') || '';

            const toastEl = document.getElementById('escalaToast');
            let toastTimer = null;
            const showToast = (msg, isError = false) => {
                if (!toastEl) return;
                toastEl.textContent = msg;
                toastEl.className = `no-print pointer-events-none fixed bottom-5 right-5 z-50 max-w-sm rounded-xl px-4 py-3 text-xs font-medium text-white shadow-lg ${isError ? 'bg-rose-600' : 'bg-elo-teal'}`;
                toastEl.classList.remove('hidden');
                window.clearTimeout(toastTimer);
                toastTimer = window.setTimeout(() => toastEl.classList.add('hidden'), 3200);
            };

            const postAjax = async (payload) => {
                const body = new FormData();
                body.set('csrfToken', csrfToken);
                body.set('tabToken', getTabToken());
                Object.entries(payload).forEach(([k, v]) => body.set(k, v ?? ''));
                const res = await fetch(window.location.pathname + window.location.search, {
                    method: 'POST',
                    body,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (!res.ok || !data.ok) {
                    throw new Error(data.message || 'Não foi possível concluir a operação.');
                }
                return data;
            };

            const statusStyles = {
                'Confirmado': { border: 'border-emerald-200 bg-emerald-50/70', dot: 'bg-emerald-500', text: 'text-emerald-800' },
                'Troca': { border: 'border-sky-200 bg-sky-50/80', dot: 'bg-sky-500', text: 'text-sky-800' },
                'Folga': { border: 'border-slate-200 bg-slate-100/80', dot: 'bg-slate-400', text: 'text-slate-600' },
                'Falta': { border: 'border-rose-200 bg-rose-50/80', dot: 'bg-rose-500', text: 'text-rose-800' },
                'Férias': { border: 'border-purple-200 bg-purple-50/80', dot: 'bg-purple-500', text: 'text-purple-800' },
            };
            const turnoBadgeMensal = {
                'Manhã': 'bg-amber-100 text-amber-800',
                'Tarde': 'bg-orange-100 text-orange-800',
                'Noite': 'bg-indigo-100 text-indigo-800',
                'Integral': 'bg-teal-100 text-teal-800',
            };

            const createAvatar = (item, sizeClass = 'h-6 w-6 text-[10px]') => {
                if (item.fotoUrl) {
                    const img = document.createElement('img');
                    img.src = item.fotoUrl;
                    img.alt = '';
                    img.className = `${sizeClass} shrink-0 rounded-full border border-white object-cover`;
                    return img;
                }
                const span = document.createElement('span');
                span.className = `${sizeClass} flex shrink-0 items-center justify-center rounded-full bg-elo-teal/15 font-bold text-elo-teal`;
                span.textContent = item.iniciais;
                return span;
            };

            let dragPayload = null;

            const buildShiftCard = (item, conflitoDia, compacto = false) => {
                const st = statusStyles[item.statusEscala] || statusStyles['Confirmado'];
                const card = document.createElement('div');
                card.draggable = true;
                card.className = `group/card relative flex items-center gap-1.5 rounded-lg border px-1.5 py-1 text-left transition hover:shadow-sm cursor-pointer ${st.border}`;
                card.title = `${item.nomeCompleto} (${item.cargoFuncao}) · ${item.statusEscala}${item.observacoes ? ' · ' + item.observacoes : ''}`;

                card.addEventListener('dragstart', (e) => {
                    dragPayload = { type: 'move', idEscala: item.idEscala };
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', JSON.stringify(dragPayload));
                    card.classList.add('dragging-card');
                });
                card.addEventListener('dragend', () => {
                    dragPayload = null;
                    card.classList.remove('dragging-card');
                });
                card.addEventListener('click', (e) => {
                    if (e.target.closest('[data-card-remove]')) return;
                    openEditModal(item);
                });

                card.append(createAvatar(item, compacto ? 'h-5 w-5 text-[9px]' : 'h-6 w-6 text-[10px]'));

                const info = document.createElement('div');
                info.className = 'min-w-0 flex-1';

                const line1 = document.createElement('div');
                line1.className = 'flex items-center gap-1';
                const dot = document.createElement('span');
                dot.className = `h-1.5 w-1.5 shrink-0 rounded-full ${st.dot}`;
                const name = document.createElement('span');
                name.className = `truncate font-semibold text-slate-800 ${compacto ? 'text-[10px]' : 'text-[11px]'}`;
                name.textContent = item.nomeCurto;
                line1.append(dot, name);
                info.append(line1);

                const line2 = document.createElement('div');
                line2.className = 'flex flex-wrap items-center gap-1 text-[9.5px] text-slate-500';
                if (compacto) {
                    const tBadge = document.createElement('span');
                    tBadge.className = `rounded px-1 font-semibold ${turnoBadgeMensal[item.turno] || 'bg-slate-200 text-slate-700'}`;
                    tBadge.textContent = item.turno.slice(0, 1);
                    line2.append(tBadge);
                }
                const sub = document.createElement('span');
                sub.className = 'truncate';
                const horarioTxt = item.horaInicio && item.horaFim ? `${item.horaInicio}-${item.horaFim}` : item.cargoFuncao;
                sub.textContent = horarioTxt;
                line2.append(sub);
                if (conflitoDia) {
                    const warn = document.createElement('span');
                    warn.className = 'rounded bg-amber-200 px-1 font-bold text-amber-900';
                    warn.textContent = '2+';
                    warn.title = 'Este colaborador está em mais de um turno neste mesmo dia';
                    line2.append(warn);
                }
                info.append(line2);
                card.append(info);

                const delBtn = document.createElement('button');
                delBtn.type = 'button';
                delBtn.dataset.cardRemove = '1';
                delBtn.ariaLabel = `Remover ${item.nomeCompleto}`;
                delBtn.className = 'no-print hidden h-4 w-4 shrink-0 items-center justify-center rounded text-slate-400 hover:bg-rose-100 hover:text-rose-700 group-hover/card:inline-flex';
                delBtn.innerHTML = '<i class="ph ph-x text-[11px]" aria-hidden="true"></i>';
                delBtn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    await removerItem(item.idEscala);
                });
                card.append(delBtn);

                return card;
            };

            const renderBoard = () => {
                const countsPerColab = new Map();
                const dayColabCount = new Map();
                let ocorrencias = 0;

                escalaItens.forEach((it) => {
                    countsPerColab.set(it.idColaborador, (countsPerColab.get(it.idColaborador) || 0) + 1);
                    if (it.statusEscala === 'Confirmado' || it.statusEscala === 'Troca') {
                        const dk = `${it.dataPlantao}:${it.idColaborador}`;
                        dayColabCount.set(dk, (dayColabCount.get(dk) || 0) + 1);
                    }
                    if (it.statusEscala !== 'Confirmado') ocorrencias++;
                });

                document.querySelectorAll('[data-colab-count]').forEach((el) => {
                    const cid = Number(el.dataset.colabCount);
                    const n = countsPerColab.get(cid) || 0;
                    el.textContent = `${n}x`;
                    el.className = n > 0 ? 'font-semibold text-elo-teal' : 'text-slate-400';
                });

                let descobertos = 0;

                if (visao === 'semanal') {
                    document.querySelectorAll('[data-drop-cell]').forEach((cell) => {
                        const date = cell.dataset.date;
                        const turno = cell.dataset.turno;
                        const itemsBox = cell.querySelector('[data-cell-items]');
                        const badgeEl = cell.querySelector('[data-cell-badge]');
                        itemsBox.replaceChildren();

                        const cellItems = escalaItens.filter((it) => it.dataPlantao === date && it.turno === turno);
                        const ativosNaCelula = cellItems.filter((it) => it.statusEscala === 'Confirmado' || it.statusEscala === 'Troca').length;

                        if (ativosNaCelula === 0) {
                            descobertos++;
                            badgeEl.textContent = 'Descoberto';
                            badgeEl.className = 'rounded bg-amber-100/80 px-1.5 py-0.5 text-[9.5px] font-semibold text-amber-800';
                        } else {
                            badgeEl.textContent = `${ativosNaCelula} plantonista${ativosNaCelula > 1 ? 's' : ''}`;
                            badgeEl.className = 'text-[9.5px] font-medium text-slate-400';
                        }

                        cellItems.forEach((it) => {
                            const conflito = (dayColabCount.get(`${it.dataPlantao}:${it.idColaborador}`) || 0) > 1;
                            itemsBox.append(buildShiftCard(it, conflito, false));
                        });
                    });
                } else {
                    document.querySelectorAll('[data-day-items]').forEach((box) => {
                        const date = box.dataset.dayItems;
                        box.replaceChildren();
                        const dayItems = escalaItens.filter((it) => it.dataPlantao === date);
                        const ativosNoDia = dayItems.filter((it) => it.statusEscala === 'Confirmado' || it.statusEscala === 'Troca').length;
                        if (ativosNoDia === 0) descobertos++;
                        dayItems.forEach((it) => {
                            const conflito = (dayColabCount.get(`${it.dataPlantao}:${it.idColaborador}`) || 0) > 1;
                            box.append(buildShiftCard(it, conflito, true));
                        });
                    });
                }

                document.getElementById('kpiTotalPlantoes').textContent = String(escalaItens.length);
                document.getElementById('kpiEquipeEscalada').textContent = String(countsPerColab.size);
                const kpiDesc = document.getElementById('kpiDescobertos');
                kpiDesc.textContent = String(descobertos);
                kpiDesc.className = `mt-0.5 text-xl font-bold tabular-nums ${descobertos > 0 ? 'text-amber-600' : 'text-emerald-600'}`;
                document.getElementById('kpiOcorrencias').textContent = String(ocorrencias);
            };

            const alocarItem = async (idColaborador, dataPlantao, turno, extras = {}) => {
                try {
                    const data = await postAjax({
                        acao: 'alocar',
                        idColaborador,
                        dataPlantao,
                        turno,
                        statusEscala: extras.statusEscala || 'Confirmado',
                        horaInicio: extras.horaInicio || '',
                        horaFim: extras.horaFim || '',
                        observacoes: extras.observacoes || '',
                    });
                    escalaItens.push(data.item);
                    renderBoard();
                    showToast(`${data.item.nomeCurto} escalado(a) em ${turno}.`);
                } catch (err) {
                    showToast(err.message, true);
                }
            };

            const moverItem = async (idEscala, dataPlantao, turno) => {
                const idx = escalaItens.findIndex((x) => x.idEscala === idEscala);
                if (idx < 0) return;
                if (escalaItens[idx].dataPlantao === dataPlantao && escalaItens[idx].turno === turno) return;
                try {
                    await postAjax({ acao: 'mover', idEscala, dataPlantao, turno });
                    escalaItens[idx].dataPlantao = dataPlantao;
                    escalaItens[idx].turno = turno;
                    renderBoard();
                    showToast(`Plantão de ${escalaItens[idx].nomeCurto} movido para ${turno}.`);
                } catch (err) {
                    showToast(err.message, true);
                }
            };

            const removerItem = async (idEscala) => {
                try {
                    await postAjax({ acao: 'remover', idEscala });
                    escalaItens = escalaItens.filter((x) => x.idEscala !== idEscala);
                    renderBoard();
                    showToast('Plantão removido da escala.');
                } catch (err) {
                    showToast(err.message, true);
                }
            };

            // Drag do Dock
            document.querySelectorAll('[data-dock-card]').forEach((card) => {
                card.addEventListener('dragstart', (e) => {
                    dragPayload = { type: 'new', idColaborador: Number(card.dataset.colabId) };
                    e.dataTransfer.effectAllowed = 'copy';
                    e.dataTransfer.setData('text/plain', JSON.stringify(dragPayload));
                    card.classList.add('dragging-card');
                });
                card.addEventListener('dragend', () => {
                    dragPayload = null;
                    card.classList.remove('dragging-card');
                });
            });

            // Dropzones (Semanal)
            document.querySelectorAll('[data-drop-cell]').forEach((cell) => {
                cell.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    cell.classList.add('dropzone-active');
                });
                cell.addEventListener('dragleave', () => cell.classList.remove('dropzone-active'));
                cell.addEventListener('drop', async (e) => {
                    e.preventDefault();
                    cell.classList.remove('dropzone-active');
                    let payload = dragPayload;
                    if (!payload) {
                        try { payload = JSON.parse(e.dataTransfer.getData('text/plain')); } catch (_) { return; }
                    }
                    if (!payload) return;
                    if (payload.type === 'new') {
                        await alocarItem(payload.idColaborador, cell.dataset.date, cell.dataset.turno);
                    } else if (payload.type === 'move') {
                        await moverItem(payload.idEscala, cell.dataset.date, cell.dataset.turno);
                    }
                });
            });

            // Dropzones (Mensal)
            document.querySelectorAll('[data-drop-day]').forEach((cell) => {
                cell.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    cell.classList.add('dropzone-active');
                });
                cell.addEventListener('dragleave', () => cell.classList.remove('dropzone-active'));
                cell.addEventListener('drop', async (e) => {
                    e.preventDefault();
                    cell.classList.remove('dropzone-active');
                    let payload = dragPayload;
                    if (!payload) {
                        try { payload = JSON.parse(e.dataTransfer.getData('text/plain')); } catch (_) { return; }
                    }
                    if (!payload) return;
                    if (payload.type === 'new') {
                        const colab = colabMap.get(payload.idColaborador);
                        const turnoPadrao = colab?.turnoPadrao || 'Manhã';
                        await alocarItem(payload.idColaborador, cell.dataset.date, turnoPadrao);
                    } else if (payload.type === 'move') {
                        const orig = escalaItens.find((x) => x.idEscala === payload.idEscala);
                        if (orig) await moverItem(payload.idEscala, cell.dataset.date, orig.turno);
                    }
                });
            });

            // Filtros do Dock
            const filtroInput = document.getElementById('filtroDockEquipe');
            let vinculoAtivo = 'Todos';
            const applyDockFilter = () => {
                const q = (filtroInput?.value || '').trim().toLowerCase();
                document.querySelectorAll('[data-dock-card]').forEach((card) => {
                    const matchQ = q === '' || (card.dataset.colabSearch || '').includes(q);
                    const v = card.dataset.colabVinculo || '';
                    const matchV = vinculoAtivo === 'Todos'
                        || (vinculoAtivo === 'Voluntário' ? v === 'Voluntário' : v !== 'Voluntário');
                    card.classList.toggle('hidden', !(matchQ && matchV));
                });
            };
            filtroInput?.addEventListener('input', applyDockFilter);
            document.querySelectorAll('.dock-filter-btn').forEach((btn) => {
                btn.addEventListener('click', () => {
                    vinculoAtivo = btn.dataset.dockFilter;
                    document.querySelectorAll('.dock-filter-btn').forEach((b) => {
                        const active = b === btn;
                        b.className = `dock-filter-btn rounded-full px-2.5 py-0.5 text-[11px] font-medium ${active ? 'bg-elo-teal text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}`;
                    });
                    applyDockFilter();
                });
            });

            // Modais
            const modalAlocar = document.getElementById('modalAlocar');
            const modalEditar = document.getElementById('modalEditar');
            document.querySelectorAll('[data-close-dialog]').forEach((btn) => {
                btn.addEventListener('click', () => btn.closest('dialog')?.close());
            });

            document.querySelectorAll('[data-quick-add]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    document.getElementById('alocarData').value = btn.dataset.date;
                    document.getElementById('alocarTurno').value = btn.dataset.turno;
                    document.getElementById('alocarColaborador').value = '';
                    document.getElementById('alocarStatus').value = 'Confirmado';
                    document.getElementById('alocarHoraInicio').value = '';
                    document.getElementById('alocarHoraFim').value = '';
                    document.getElementById('alocarObs').value = '';
                    modalAlocar.showModal();
                });
            });

            document.getElementById('formAlocar')?.addEventListener('submit', async (e) => {
                e.preventDefault();
                const idColab = Number(document.getElementById('alocarColaborador').value);
                if (!idColab) return;
                await alocarItem(
                    idColab,
                    document.getElementById('alocarData').value,
                    document.getElementById('alocarTurno').value,
                    {
                        statusEscala: document.getElementById('alocarStatus').value,
                        horaInicio: document.getElementById('alocarHoraInicio').value,
                        horaFim: document.getElementById('alocarHoraFim').value,
                        observacoes: document.getElementById('alocarObs').value.trim(),
                    }
                );
                modalAlocar.close();
            });

            let editingItem = null;
            const openEditModal = (item) => {
                editingItem = item;
                document.getElementById('editarIdEscala').value = String(item.idEscala);
                document.getElementById('editarNome').textContent = item.nomeCompleto;
                document.getElementById('editarCargo').textContent = `${item.cargoFuncao} · ${item.tipoVinculo}`;
                const avatarWrap = document.getElementById('editarAvatar');
                avatarWrap.replaceChildren(createAvatar(item, 'h-10 w-10 text-xs'));
                document.getElementById('editarData').value = item.dataPlantao;
                document.getElementById('editarTurno').value = item.turno;
                document.getElementById('editarStatus').value = item.statusEscala;
                document.getElementById('editarHoraInicio').value = item.horaInicio || '';
                document.getElementById('editarHoraFim').value = item.horaFim || '';
                document.getElementById('editarObs').value = item.observacoes || '';
                const crachaLink = document.getElementById('linkCrachaModal');
                if (crachaLink) {
                    const tok = getTabToken();
                    crachaLink.href = `${urlCrachaBase}?cracha=${item.idColaborador}${tok ? '&tabToken=' + encodeURIComponent(tok) : ''}`;
                }
                modalEditar.showModal();
            };

            document.getElementById('formEditar')?.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!editingItem) return;
                const payload = {
                    acao: 'atualizar',
                    idEscala: editingItem.idEscala,
                    dataPlantao: document.getElementById('editarData').value,
                    turno: document.getElementById('editarTurno').value,
                    statusEscala: document.getElementById('editarStatus').value,
                    horaInicio: document.getElementById('editarHoraInicio').value,
                    horaFim: document.getElementById('editarHoraFim').value,
                    observacoes: document.getElementById('editarObs').value.trim(),
                };
                try {
                    await postAjax(payload);
                    Object.assign(editingItem, {
                        dataPlantao: payload.dataPlantao,
                        turno: payload.turno,
                        statusEscala: payload.statusEscala,
                        horaInicio: payload.horaInicio,
                        horaFim: payload.horaFim,
                        observacoes: payload.observacoes,
                    });
                    renderBoard();
                    modalEditar.close();
                    showToast('Plantão atualizado com sucesso.');
                } catch (err) {
                    showToast(err.message, true);
                }
            });

            document.getElementById('btnRemoverEscala')?.addEventListener('click', async () => {
                if (!editingItem) return;
                const id = editingItem.idEscala;
                modalEditar.close();
                await removerItem(id);
            });

            document.getElementById('btnCopiarSemana')?.addEventListener('click', async () => {
                if (!window.confirm('Copiar todos os plantões confirmados da semana anterior para esta semana?')) return;
                try {
                    const data = await postAjax({ acao: 'copiarSemanaAnterior', inicioSemana });
                    showToast(`${data.copiados} plantão(ões) copiado(s). Recarregando...`);
                    window.setTimeout(() => window.location.reload(), 650);
                } catch (err) {
                    showToast(err.message, true);
                }
            });

            renderBoard();
        })();
    </script>
</body>
</html>
