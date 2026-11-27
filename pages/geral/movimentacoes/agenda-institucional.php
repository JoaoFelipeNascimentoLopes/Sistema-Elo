<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('geral.movimentacoes/agenda-institucional');
$pdo = appPdo();
$baseUrl = appBaseUrl();
$urlAtual = $baseUrl . '/pages/geral/movimentacoes/agenda-institucional.php';

$categorias = [
    'Reunião',
    'Vistoria / Órgão Público',
    'Audiência / Judicial',
    'Treinamento',
    'Evento / Comemorativo',
    'Manutenção',
    'Outro',
];
$statusPermitidos = ['Agendado', 'Confirmado', 'Concluído', 'Cancelado'];
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
    return count($partes) <= 2 ? trim($nome) : ($partes[0] . ' ' . $partes[count($partes) - 1]);
};
$urlFotoColaborador = static function (?string $caminhoFoto, int $idColaborador) use ($baseUrl): ?string {
    return !empty($caminhoFoto)
        ? appTabUrl($baseUrl . '/auth/colaborador-foto.php?id=' . $idColaborador)
        : null;
};

// Descobre se o usuário logado possui vínculo com algum colaborador
$meuColabStmt = $pdo->prepare('SELECT idColaborador, nomeCompleto FROM tbColaborador WHERE idUsuario = :uid LIMIT 1');
$meuColabStmt->execute(['uid' => (int) $usuarioAtual['idUsuario']]);
$meuColaborador = $meuColabStmt->fetch(PDO::FETCH_ASSOC) ?: null;
$meuIdColaborador = $meuColaborador ? (int) $meuColaborador['idColaborador'] : 0;

$carregarEventoCompleto = static function (int $idEvento) use ($pdo, $urlFotoColaborador, $iniciaisNome, $nomeCurto, $meuIdColaborador): ?array {
    $st = $pdo->prepare(
        'SELECT e.idEvento, e.titulo, e.categoria, e.statusEvento, e.dataEvento, e.diaInteiro,
                e.horaInicio, e.horaFim, e.localEvento, e.descricao,
                u.nomeCompleto AS criadoPor
         FROM tbAgendaEvento e
         INNER JOIN tbUsuario u ON u.idUsuario = e.idUsuarioCriacao
         WHERE e.idEvento = :id
         LIMIT 1'
    );
    $st->execute(['id' => $idEvento]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $pSt = $pdo->prepare(
        'SELECT c.idColaborador, c.nomeCompleto, c.tipoVinculo, c.cargoFuncao, c.caminhoFoto
         FROM tbAgendaEventoParticipante p
         INNER JOIN tbColaborador c ON c.idColaborador = p.idColaborador
         WHERE p.idEvento = :id
         ORDER BY c.nomeCompleto'
    );
    $pSt->execute(['id' => $idEvento]);
    $participantes = [];
    $euEnvolvido = false;
    foreach ($pSt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $cid = (int) $p['idColaborador'];
        if ($meuIdColaborador > 0 && $cid === $meuIdColaborador) {
            $euEnvolvido = true;
        }
        $participantes[] = [
            'idColaborador' => $cid,
            'nomeCompleto' => (string) $p['nomeCompleto'],
            'nomeCurto' => $nomeCurto((string) $p['nomeCompleto']),
            'iniciais' => $iniciaisNome((string) $p['nomeCompleto']),
            'tipoVinculo' => (string) $p['tipoVinculo'],
            'cargoFuncao' => (string) $p['cargoFuncao'],
            'fotoUrl' => $urlFotoColaborador($p['caminhoFoto'] ?? null, $cid),
        ];
    }
    return [
        'idEvento' => (int) $row['idEvento'],
        'titulo' => (string) $row['titulo'],
        'categoria' => (string) $row['categoria'],
        'statusEvento' => (string) $row['statusEvento'],
        'dataEvento' => (string) $row['dataEvento'],
        'diaInteiro' => (int) $row['diaInteiro'] === 1,
        'horaInicio' => !empty($row['horaInicio']) ? substr((string) $row['horaInicio'], 0, 5) : '',
        'horaFim' => !empty($row['horaFim']) ? substr((string) $row['horaFim'], 0, 5) : '',
        'localEvento' => (string) ($row['localEvento'] ?? ''),
        'descricao' => (string) ($row['descricao'] ?? ''),
        'criadoPor' => (string) $row['criadoPor'],
        'participantes' => $participantes,
        'participanteIds' => array_column($participantes, 'idColaborador'),
        'euEnvolvido' => $euEnvolvido,
    ];
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    header('Content-Type: application/json; charset=UTF-8');
    $acao = $textoPost('acao');

    try {
        if ($acao === 'salvar') {
            $idEvento = filter_var($_POST['idEvento'] ?? 0, FILTER_VALIDATE_INT);
            $idEvento = ($idEvento !== false && $idEvento > 0) ? (int) $idEvento : 0;
            $titulo = $textoPost('titulo');
            $categoria = $textoPost('categoria');
            $statusEvento = $textoPost('statusEvento') ?: 'Agendado';
            $dataEvento = $textoPost('dataEvento');
            $diaInteiro = isset($_POST['diaInteiro']) && (string) $_POST['diaInteiro'] === '1' ? 1 : 0;
            $horaInicio = $diaInteiro ? null : $validarHora($textoPost('horaInicio'));
            $horaFim = $diaInteiro ? null : $validarHora($textoPost('horaFim'));
            $localEvento = $textoPost('localEvento');
            $descricao = $textoPost('descricao');

            $partRaw = $_POST['participantes'] ?? [];
            if (is_string($partRaw)) {
                $decoded = json_decode($partRaw, true);
                $partRaw = is_array($decoded) ? $decoded : [];
            }
            $participanteIds = [];
            if (is_array($partRaw)) {
                foreach ($partRaw as $pid) {
                    $valid = filter_var($pid, FILTER_VALIDATE_INT);
                    if ($valid !== false && $valid > 0) {
                        $participanteIds[(int) $valid] = (int) $valid;
                    }
                }
            }
            $participanteIds = array_values($participanteIds);

            if ($titulo === '' || mb_strlen($titulo, 'UTF-8') > 150) {
                throw new InvalidArgumentException('Informe o título do evento (até 150 caracteres).');
            }
            if (!in_array($categoria, $categorias, true)) {
                throw new InvalidArgumentException('Selecione uma categoria válida.');
            }
            if (!in_array($statusEvento, $statusPermitidos, true)) {
                throw new InvalidArgumentException('Selecione um status válido.');
            }
            if (!$validarData($dataEvento)) {
                throw new InvalidArgumentException('Informe uma data válida para o evento.');
            }
            if ($horaInicio !== null && $horaFim !== null && $horaFim < $horaInicio) {
                throw new InvalidArgumentException('O horário final não pode ser anterior ao horário inicial.');
            }
            if (mb_strlen($localEvento, 'UTF-8') > 150) {
                throw new InvalidArgumentException('O local deve ter até 150 caracteres.');
            }
            if (mb_strlen($descricao, 'UTF-8') > 3000) {
                throw new InvalidArgumentException('A descrição deve ter até 3000 caracteres.');
            }

            if ($participanteIds) {
                $placeholders = implode(', ', array_fill(0, count($participanteIds), '?'));
                $chk = $pdo->prepare("SELECT idColaborador FROM tbColaborador WHERE idColaborador IN ($placeholders)");
                $chk->execute($participanteIds);
                $validDbIds = array_map('intval', $chk->fetchAll(PDO::FETCH_COLUMN));
                if (count($validDbIds) !== count($participanteIds)) {
                    throw new InvalidArgumentException('Um ou mais participantes selecionados não foram encontrados.');
                }
            }

            $pdo->beginTransaction();
            try {
                if ($idEvento > 0) {
                    $chkEv = $pdo->prepare('SELECT idEvento FROM tbAgendaEvento WHERE idEvento = :id');
                    $chkEv->execute(['id' => $idEvento]);
                    if ($chkEv->fetchColumn() === false) {
                        throw new InvalidArgumentException('Evento não encontrado.');
                    }
                    $upd = $pdo->prepare(
                        'UPDATE tbAgendaEvento
                         SET titulo = :titulo, categoria = :categoria, statusEvento = :statusEvento,
                             dataEvento = :dataEvento, diaInteiro = :diaInteiro,
                             horaInicio = :horaInicio, horaFim = :horaFim,
                             localEvento = :localEvento, descricao = :descricao,
                             idUsuarioAlteracao = :uid
                         WHERE idEvento = :id'
                    );
                    $upd->execute([
                        'titulo' => $titulo,
                        'categoria' => $categoria,
                        'statusEvento' => $statusEvento,
                        'dataEvento' => $dataEvento,
                        'diaInteiro' => $diaInteiro,
                        'horaInicio' => $horaInicio,
                        'horaFim' => $horaFim,
                        'localEvento' => $localEvento !== '' ? $localEvento : null,
                        'descricao' => $descricao !== '' ? $descricao : null,
                        'uid' => (int) $usuarioAtual['idUsuario'],
                        'id' => $idEvento,
                    ]);
                    $pdo->prepare('DELETE FROM tbAgendaEventoParticipante WHERE idEvento = :id')->execute(['id' => $idEvento]);
                    $acaoLog = 'editar';
                    $descLog = 'Evento atualizado na agenda: "' . $titulo . '" (' . date('d/m/Y', strtotime($dataEvento)) . ').';
                } else {
                    $ins = $pdo->prepare(
                        'INSERT INTO tbAgendaEvento
                            (titulo, categoria, statusEvento, dataEvento, diaInteiro, horaInicio, horaFim, localEvento, descricao, idUsuarioCriacao)
                         VALUES
                            (:titulo, :categoria, :statusEvento, :dataEvento, :diaInteiro, :horaInicio, :horaFim, :localEvento, :descricao, :uid)'
                    );
                    $ins->execute([
                        'titulo' => $titulo,
                        'categoria' => $categoria,
                        'statusEvento' => $statusEvento,
                        'dataEvento' => $dataEvento,
                        'diaInteiro' => $diaInteiro,
                        'horaInicio' => $horaInicio,
                        'horaFim' => $horaFim,
                        'localEvento' => $localEvento !== '' ? $localEvento : null,
                        'descricao' => $descricao !== '' ? $descricao : null,
                        'uid' => (int) $usuarioAtual['idUsuario'],
                    ]);
                    $idEvento = (int) $pdo->lastInsertId();
                    $acaoLog = 'criar';
                    $descLog = 'Evento agendado: "' . $titulo . '" em ' . date('d/m/Y', strtotime($dataEvento)) . ' (' . count($participanteIds) . ' envolvido(s)).';
                }

                if ($participanteIds) {
                    $insPart = $pdo->prepare('INSERT INTO tbAgendaEventoParticipante (idEvento, idColaborador) VALUES (:ev, :col)');
                    foreach ($participanteIds as $cid) {
                        $insPart->execute(['ev' => $idEvento, 'col' => $cid]);
                    }
                }
                $pdo->commit();
            } catch (Throwable $txErr) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $txErr;
            }

            appRegistrarAuditoria(
                'geral',
                $acaoLog,
                $descLog,
                'tbAgendaEvento',
                $idEvento,
                ['categoria' => $categoria, 'statusEvento' => $statusEvento, 'dataEvento' => $dataEvento, 'participantes' => count($participanteIds)],
                $usuarioAtual
            );

            echo json_encode(['ok' => true, 'evento' => $carregarEventoCompleto($idEvento)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'mover') {
            $idEvento = filter_var($_POST['idEvento'] ?? 0, FILTER_VALIDATE_INT);
            $novaData = $textoPost('dataEvento');
            if (!$idEvento || $idEvento < 1 || !$validarData($novaData)) {
                throw new InvalidArgumentException('Dados inválidos para reagendar o evento.');
            }
            $orig = $pdo->prepare('SELECT titulo, dataEvento FROM tbAgendaEvento WHERE idEvento = :id LIMIT 1');
            $orig->execute(['id' => $idEvento]);
            $row = $orig->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new InvalidArgumentException('Evento não encontrado.');
            }
            $upd = $pdo->prepare('UPDATE tbAgendaEvento SET dataEvento = :data, idUsuarioAlteracao = :uid WHERE idEvento = :id');
            $upd->execute(['data' => $novaData, 'uid' => (int) $usuarioAtual['idUsuario'], 'id' => $idEvento]);

            appRegistrarAuditoria(
                'geral',
                'editar',
                'Evento reagendado: "' . $row['titulo'] . '" para ' . date('d/m/Y', strtotime($novaData)) . '.',
                'tbAgendaEvento',
                (int) $idEvento,
                ['de' => $row['dataEvento'], 'para' => $novaData],
                $usuarioAtual
            );
            echo json_encode(['ok' => true, 'evento' => $carregarEventoCompleto((int) $idEvento)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'status') {
            $idEvento = filter_var($_POST['idEvento'] ?? 0, FILTER_VALIDATE_INT);
            $novoStatus = $textoPost('statusEvento');
            if (!$idEvento || $idEvento < 1 || !in_array($novoStatus, $statusPermitidos, true)) {
                throw new InvalidArgumentException('Status inválido.');
            }
            $orig = $pdo->prepare('SELECT titulo FROM tbAgendaEvento WHERE idEvento = :id LIMIT 1');
            $orig->execute(['id' => $idEvento]);
            $titulo = $orig->fetchColumn();
            if ($titulo === false) {
                throw new InvalidArgumentException('Evento não encontrado.');
            }
            $upd = $pdo->prepare('UPDATE tbAgendaEvento SET statusEvento = :st, idUsuarioAlteracao = :uid WHERE idEvento = :id');
            $upd->execute(['st' => $novoStatus, 'uid' => (int) $usuarioAtual['idUsuario'], 'id' => $idEvento]);

            appRegistrarAuditoria(
                'geral',
                'editar',
                'Status do evento "' . $titulo . '" alterado para ' . $novoStatus . '.',
                'tbAgendaEvento',
                (int) $idEvento,
                ['statusEvento' => $novoStatus],
                $usuarioAtual
            );
            echo json_encode(['ok' => true, 'evento' => $carregarEventoCompleto((int) $idEvento)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'excluir') {
            $idEvento = filter_var($_POST['idEvento'] ?? 0, FILTER_VALIDATE_INT);
            if (!$idEvento || $idEvento < 1) {
                throw new InvalidArgumentException('Evento inválido.');
            }
            $orig = $pdo->prepare('SELECT titulo, dataEvento FROM tbAgendaEvento WHERE idEvento = :id LIMIT 1');
            $orig->execute(['id' => $idEvento]);
            $row = $orig->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new InvalidArgumentException('Evento não encontrado.');
            }
            $pdo->prepare('DELETE FROM tbAgendaEvento WHERE idEvento = :id')->execute(['id' => $idEvento]);

            appRegistrarAuditoria(
                'geral',
                'excluir',
                'Evento excluído da agenda: "' . $row['titulo'] . '" (' . date('d/m/Y', strtotime((string) $row['dataEvento'])) . ').',
                'tbAgendaEvento',
                (int) $idEvento,
                ['titulo' => $row['titulo'], 'dataEvento' => $row['dataEvento']],
                $usuarioAtual
            );
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (PDOException $e) {
        error_log('Erro na Agenda Institucional: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Não foi possível salvar a alteração na agenda.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$hoje = new DateTimeImmutable('today');
$hojeIso = $hoje->format('Y-m-d');
$eventoAberturaId = filter_input(INPUT_GET, 'evento', FILTER_VALIDATE_INT) ?: 0;
$mesInput = is_scalar($_GET['mes'] ?? null) ? trim((string) $_GET['mes']) : '';

if ($eventoAberturaId > 0 && $mesInput === '') {
    $evDataStmt = $pdo->prepare('SELECT dataEvento FROM tbAgendaEvento WHERE idEvento = :id LIMIT 1');
    $evDataStmt->execute(['id' => $eventoAberturaId]);
    $evDataVal = $evDataStmt->fetchColumn();
    if (is_string($evDataVal) && $validarData($evDataVal)) {
        $mesInput = substr($evDataVal, 0, 7);
    }
}

$mesRef = preg_match('/^\d{4}-\d{2}$/', $mesInput) ? DateTimeImmutable::createFromFormat('!Y-m-d', $mesInput . '-01') : false;
if (!$mesRef) {
    $mesRef = $hoje->modify('first day of this month');
}

$mesInicio = $mesRef->modify('first day of this month');
$mesFim = $mesRef->modify('last day of this month');
$mesAnterior = $mesInicio->modify('-1 month')->format('Y-m');
$mesProximo = $mesInicio->modify('+1 month')->format('Y-m');
$tituloMes = $mesesNomes[(int) $mesInicio->format('n')] . ' de ' . $mesInicio->format('Y');

$primeiroDiaSem = (int) $mesInicio->format('N');
$gradeInicio = $mesInicio->modify('-' . ($primeiroDiaSem - 1) . ' days');
$ultimoDiaSem = (int) $mesFim->format('N');
$gradeFim = $mesFim->modify('+' . (7 - $ultimoDiaSem) . ' days');
$diasGrade = [];
$cursor = $gradeInicio;
while ($cursor <= $gradeFim) {
    $diasGrade[] = $cursor;
    $cursor = $cursor->modify('+1 day');
}

// Busca eventos que caem em toda a grade visível (inclui sobras da semana anterior/seguinte)
$eventosStmt = $pdo->prepare(
    'SELECT e.idEvento, e.titulo, e.categoria, e.statusEvento, e.dataEvento, e.diaInteiro,
            e.horaInicio, e.horaFim, e.localEvento, e.descricao,
            u.nomeCompleto AS criadoPor
     FROM tbAgendaEvento e
     INNER JOIN tbUsuario u ON u.idUsuario = e.idUsuarioCriacao
     WHERE e.dataEvento BETWEEN :ini AND :fim
     ORDER BY e.dataEvento ASC, e.diaInteiro DESC, COALESCE(e.horaInicio, \'00:00:00\') ASC, e.idEvento ASC'
);
$eventosStmt->execute([
    'ini' => $gradeInicio->format('Y-m-d'),
    'fim' => $gradeFim->format('Y-m-d'),
]);
$eventosRows = $eventosStmt->fetchAll(PDO::FETCH_ASSOC);

$participantesPorEvento = [];
if ($eventosRows) {
    $evIds = array_map(static fn(array $r): int => (int) $r['idEvento'], $eventosRows);
    $ph = implode(', ', array_fill(0, count($evIds), '?'));
    $pStmt = $pdo->prepare(
        "SELECT p.idEvento, c.idColaborador, c.nomeCompleto, c.tipoVinculo, c.cargoFuncao, c.caminhoFoto
         FROM tbAgendaEventoParticipante p
         INNER JOIN tbColaborador c ON c.idColaborador = p.idColaborador
         WHERE p.idEvento IN ($ph)
         ORDER BY c.nomeCompleto"
    );
    $pStmt->execute($evIds);
    foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $pr) {
        $eid = (int) $pr['idEvento'];
        $cid = (int) $pr['idColaborador'];
        $participantesPorEvento[$eid][] = [
            'idColaborador' => $cid,
            'nomeCompleto' => (string) $pr['nomeCompleto'],
            'nomeCurto' => $nomeCurto((string) $pr['nomeCompleto']),
            'iniciais' => $iniciaisNome((string) $pr['nomeCompleto']),
            'tipoVinculo' => (string) $pr['tipoVinculo'],
            'cargoFuncao' => (string) $pr['cargoFuncao'],
            'fotoUrl' => $urlFotoColaborador($pr['caminhoFoto'] ?? null, $cid),
        ];
    }
}

$eventosPayload = [];
foreach ($eventosRows as $r) {
    $eid = (int) $r['idEvento'];
    $parts = $participantesPorEvento[$eid] ?? [];
    $partIds = array_column($parts, 'idColaborador');
    $eventosPayload[] = [
        'idEvento' => $eid,
        'titulo' => (string) $r['titulo'],
        'categoria' => (string) $r['categoria'],
        'statusEvento' => (string) $r['statusEvento'],
        'dataEvento' => (string) $r['dataEvento'],
        'diaInteiro' => (int) $r['diaInteiro'] === 1,
        'horaInicio' => !empty($r['horaInicio']) ? substr((string) $r['horaInicio'], 0, 5) : '',
        'horaFim' => !empty($r['horaFim']) ? substr((string) $r['horaFim'], 0, 5) : '',
        'localEvento' => (string) ($r['localEvento'] ?? ''),
        'descricao' => (string) ($r['descricao'] ?? ''),
        'criadoPor' => (string) $r['criadoPor'],
        'participantes' => $parts,
        'participanteIds' => $partIds,
        'euEnvolvido' => $meuIdColaborador > 0 && in_array($meuIdColaborador, $partIds, true),
    ];
}

$colaboradoresAtivos = $pdo->query(
    'SELECT idColaborador, nomeCompleto, tipoVinculo, cargoFuncao, caminhoFoto
     FROM tbColaborador
     WHERE ativo = 1
     ORDER BY nomeCompleto'
)->fetchAll(PDO::FETCH_ASSOC);

$colaboradoresPayload = array_map(static function (array $c) use ($urlFotoColaborador, $iniciaisNome, $nomeCurto): array {
    $cid = (int) $c['idColaborador'];
    return [
        'idColaborador' => $cid,
        'nomeCompleto' => (string) $c['nomeCompleto'],
        'nomeCurto' => $nomeCurto((string) $c['nomeCompleto']),
        'iniciais' => $iniciaisNome((string) $c['nomeCompleto']),
        'tipoVinculo' => (string) $c['tipoVinculo'],
        'cargoFuncao' => (string) $c['cargoFuncao'],
        'fotoUrl' => $urlFotoColaborador($c['caminhoFoto'] ?? null, $cid),
    ];
}, $colaboradoresAtivos);

$instituicao = $pdo->query('SELECT nomeFantasia, razaoSocial FROM tbInformacoesInstituicao WHERE idInformacoes = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
$nomeInstituicao = trim((string) ($instituicao['nomeFantasia'] ?? '')) ?: trim((string) ($instituicao['razaoSocial'] ?? '')) ?: 'Sistema Elo';
$diaInicialSelecionado = ($hojeIso >= $mesInicio->format('Y-m-d') && $hojeIso <= $mesFim->format('Y-m-d'))
    ? $hojeIso
    : $mesInicio->format('Y-m-d');
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
        .cal-drop-active {
            outline: 2px dashed #007c89 !important;
            outline-offset: -2px;
            background-color: rgba(0, 124, 137, 0.08) !important;
        }
        .cal-day-selected {
            box-shadow: inset 0 0 0 2px #007c89;
            background-color: rgba(0, 124, 137, 0.04);
        }
        @page { size: A4 landscape; margin: 8mm; }
        @media print {
            html, body {
                width: 100%; height: auto !important; overflow: visible !important;
                background: #ffffff !important; color: #111827 !important;
                -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
            }
            body > nav, body > div > aside, .no-print, .fixed { display: none !important; }
            body > div, main { display: block !important; width: 100% !important; height: auto !important; overflow: visible !important; padding: 0 !important; margin: 0 !important; }
            .print-only { display: block !important; }
            .agenda-layout { display: block !important; }
            .cal-grid-table { width: 100% !important; min-width: 0 !important; table-layout: fixed; font-size: 7.5pt !important; }
        }
    </style>
</head>
<body class="flex h-screen flex-col overflow-hidden bg-slate-50 font-sans text-slate-800">
    <?php include __DIR__ . '/../../../components/geral/navbar-geral.php'; ?>
    <div class="flex min-h-0 flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../../components/geral/sidebar-geral.php'; ?>
        <main class="min-w-0 flex-1 overflow-y-auto bg-gray-50 p-4 md:p-6">
            <div class="space-y-5">
                <header class="no-print flex flex-wrap items-center justify-between gap-3">
                    <h1 class="text-2xl font-bold text-gray-800">Agenda Institucional</h1>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" id="btnNovoEventoTopo"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-elo-teal px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-elo-green">
                            <i class="ph ph-plus-circle text-base" aria-hidden="true"></i> Novo evento
                        </button>
                        <button type="button" onclick="window.print()"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                            <i class="ph ph-printer text-base" aria-hidden="true"></i> Imprimir calendário
                        </button>
                    </div>
                </header>

                <!-- Cabeçalho de impressão -->
                <div class="print-only hidden mb-3 border-b-2 border-slate-800 pb-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wider text-slate-500"><?php echo appEscape($nomeInstituicao); ?> · Sistema Elo</div>
                            <h2 class="text-lg font-bold text-slate-900">Agenda Institucional — <?php echo appEscape($tituloMes); ?></h2>
                        </div>
                        <div class="text-right text-xs text-slate-600">Emitido em <?php echo date('d/m/Y H:i'); ?></div>
                    </div>
                </div>

                <!-- Navegação do mês + Filtros + KPIs -->
                <section class="no-print grid gap-3 xl:grid-cols-12">
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm xl:col-span-6">
                        <div class="flex items-center gap-1.5">
                            <a href="<?php echo appEscape(appTabUrl($urlAtual . '?mes=' . $mesAnterior)); ?>" aria-label="Mês anterior"
                               class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50">
                                <i class="ph ph-caret-left" aria-hidden="true"></i>
                            </a>
                            <a href="<?php echo appEscape(appTabUrl($urlAtual . '?mes=' . $hoje->format('Y-m'))); ?>"
                               class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                Hoje
                            </a>
                            <a href="<?php echo appEscape(appTabUrl($urlAtual . '?mes=' . $mesProximo)); ?>" aria-label="Próximo mês"
                               class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50">
                                <i class="ph ph-caret-right" aria-hidden="true"></i>
                            </a>
                        </div>
                        <div class="text-base font-bold text-slate-800" data-preserve-case><?php echo appEscape($tituloMes); ?></div>
                        <div class="flex flex-wrap items-center gap-2">
                            <select id="filtroCategoriaAgenda" aria-label="Filtrar categoria" class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs text-gray-700">
                                <option value="">Todas as categorias</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo appEscape($cat); ?>"><?php echo appEscape($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($meuIdColaborador > 0): ?>
                                <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-elo-teal/30 bg-elo-teal/5 px-2.5 py-1.5 text-xs font-medium text-elo-teal">
                                    <input type="checkbox" id="filtroMeusEventos" class="rounded border-elo-teal text-elo-teal focus:ring-elo-teal">
                                    Meus eventos
                                </label>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:col-span-6">
                        <div class="rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 shadow-sm">
                            <p class="text-[11px] font-medium text-gray-500">Eventos no mês</p>
                            <p id="kpiEventosMes" class="mt-0.5 text-xl font-bold tabular-nums text-slate-900">0</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 shadow-sm">
                            <p class="text-[11px] font-medium text-gray-500">Em aberto</p>
                            <p id="kpiEventosAbertos" class="mt-0.5 text-xl font-bold tabular-nums text-elo-teal">0</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 shadow-sm">
                            <p class="text-[11px] font-medium text-gray-500">Meus no mês</p>
                            <p id="kpiMeusEventos" class="mt-0.5 text-xl font-bold tabular-nums text-purple-700">0</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 shadow-sm">
                            <p class="text-[11px] font-medium text-gray-500">Concluídos</p>
                            <p id="kpiConcluidos" class="mt-0.5 text-xl font-bold tabular-nums text-emerald-600">0</p>
                        </div>
                    </div>
                </section>

                <!-- Grade Principal: Calendário + Pauta do Dia -->
                <div class="agenda-layout grid gap-4 xl:grid-cols-[minmax(0,1fr)_350px]">
                    <!-- Calendário Mensal -->
                    <section class="min-w-0 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                        <div class="overflow-x-auto">
                            <table class="cal-grid-table w-full min-w-[760px] border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-200 bg-slate-50">
                                        <?php foreach ($diasSemanaCurtos as $sigla): ?>
                                            <th class="w-[14.28%] border-r border-slate-200 py-2.5 text-center text-xs font-bold uppercase tracking-wider text-slate-500 last:border-r-0">
                                                <?php echo $sigla; ?>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    <?php foreach (array_chunk($diasGrade, 7) as $semana): ?>
                                        <tr>
                                            <?php foreach ($semana as $diaObj): ?>
                                                <?php
                                                $iso = $diaObj->format('Y-m-d');
                                                $mesmoMes = $diaObj->format('Y-m') === $mesInicio->format('Y-m');
                                                $ehHoje = $iso === $hojeIso;
                                                $ehFds = (int) $diaObj->format('N') >= 6;
                                                ?>
                                                <td data-cal-day="<?php echo appEscape($iso); ?>"
                                                    data-same-month="<?php echo $mesmoMes ? '1' : '0'; ?>"
                                                    class="group relative h-28 cursor-pointer border-r border-slate-200 p-1.5 align-top transition-colors last:border-r-0 <?php echo !$mesmoMes ? 'bg-slate-100/60 text-slate-400' : ($ehFds ? 'bg-slate-50/50' : 'bg-white'); ?> hover:bg-teal-50/30">
                                                    <div class="mb-1 flex items-center justify-between">
                                                        <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-xs font-bold <?php echo $ehHoje ? 'bg-elo-teal text-white shadow-xs' : ($mesmoMes ? 'text-slate-700' : 'text-slate-400'); ?>">
                                                            <?php echo $diaObj->format('d'); ?>
                                                        </span>
                                                        <button type="button" data-day-add="<?php echo appEscape($iso); ?>"
                                                                title="Agendar em <?php echo appEscape($diaObj->format('d/m/Y')); ?>"
                                                                class="no-print inline-flex h-5 w-5 items-center justify-center rounded border border-slate-200 bg-white text-slate-400 opacity-0 transition hover:border-elo-teal hover:bg-elo-teal hover:text-white group-hover:opacity-100">
                                                            <i class="ph ph-plus text-xs" aria-hidden="true"></i>
                                                        </button>
                                                    </div>
                                                    <div data-cal-events="<?php echo appEscape($iso); ?>" class="space-y-1"></div>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="no-print flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/70 px-4 py-2.5 text-[11px] text-slate-500">
                            <span>Clique em um dia para ver a pauta · Duplo clique ou <strong>+</strong> para agendar · Arraste um evento para reagendar.</span>
                        </div>
                    </section>

                    <!-- Painel Lateral: Pauta do Dia Selecionado -->
                    <aside class="no-print flex flex-col gap-4">
                        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-2 border-b border-slate-100 pb-3">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-elo-teal">Pauta do dia</p>
                                    <h2 id="tituloDiaSelecionado" class="mt-0.5 text-sm font-bold text-slate-800" data-preserve-case></h2>
                                </div>
                                <button type="button" id="btnAgendarDiaSelecionado"
                                        class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-elo-teal px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-elo-green">
                                    <i class="ph ph-plus" aria-hidden="true"></i> Agendar
                                </button>
                            </div>
                            <div id="listaEventosDia" class="mt-3 space-y-2.5"></div>
                        </section>

                        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                            <h2 class="flex items-center gap-1.5 text-sm font-bold text-slate-800">
                                <i class="ph ph-calendar-dots text-lg text-elo-teal" aria-hidden="true"></i>
                                Próximos eventos do mês
                            </h2>
                            <div id="listaProximosMes" class="mt-3 space-y-2"></div>
                        </section>
                    </aside>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Grande de Evento + Participantes Visuais -->
    <div id="modalEventoAgenda" role="dialog" aria-modal="true" aria-labelledby="tituloModalEvento"
         class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-3 md:p-6">
        <div class="flex max-h-[94vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <header class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-elo-teal/10 text-elo-teal">
                        <i class="ph ph-calendar-plus text-xl" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h2 id="tituloModalEvento" class="text-base font-bold text-slate-800">Novo Evento</h2>
                        <p id="subtituloModalEvento" class="text-xs text-slate-500" data-preserve-case></p>
                    </div>
                </div>
                <button type="button" data-close-evento-modal aria-label="Fechar modal"
                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <i class="ph ph-x text-xl" aria-hidden="true"></i>
                </button>
            </header>

            <form id="formEventoAgenda" class="flex min-h-0 flex-1 flex-col">
                <input type="hidden" id="evIdEvento" name="idEvento" value="0">
                <div class="flex-1 space-y-4 overflow-y-auto p-5">
                    <div class="grid gap-3 md:grid-cols-3">
                        <label class="text-xs font-medium text-slate-700 md:col-span-2">Título do evento *
                            <input type="text" id="evTitulo" name="titulo" required maxlength="150"
                                   placeholder="Ex.: Reunião de Equipe, Vistoria da Vigilância, Audiência..."
                                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        </label>
                        <label class="text-xs font-medium text-slate-700">Status
                            <select id="evStatus" name="statusEvento" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                                <?php foreach ($statusPermitidos as $st): ?>
                                    <option value="<?php echo appEscape($st); ?>"><?php echo appEscape($st); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <div>
                        <span class="block text-xs font-medium text-slate-700">Categoria *</span>
                        <input type="hidden" id="evCategoria" name="categoria" value="Reunião">
                        <div id="evCategoriaChips" class="mt-1.5 flex flex-wrap gap-1.5">
                            <?php foreach ($categorias as $cat): ?>
                                <button type="button" data-cat-chip="<?php echo appEscape($cat); ?>"
                                        class="rounded-full border px-3 py-1 text-xs font-medium transition">
                                    <?php echo appEscape($cat); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 sm:grid-cols-4">
                        <label class="text-xs font-medium text-slate-700 sm:col-span-2">Data selecionada *
                            <input type="date" id="evData" name="dataEvento" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        </label>
                        <label class="text-xs font-medium text-slate-700">Início
                            <input type="time" id="evHoraInicio" name="horaInicio" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm">
                        </label>
                        <label class="text-xs font-medium text-slate-700">Fim
                            <input type="time" id="evHoraFim" name="horaFim" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm">
                        </label>
                        <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700 sm:col-span-4">
                            <input type="checkbox" id="evDiaInteiro" name="diaInteiro" value="1" class="rounded border-slate-300 text-elo-teal focus:ring-elo-teal">
                            Evento de dia inteiro
                        </label>
                    </div>

                    <label class="block text-xs font-medium text-slate-700">Local (opcional)
                        <input type="text" id="evLocal" name="localEvento" maxlength="150" placeholder="Ex.: Sala de Reuniões, Pátio, Fórum, Google Meet..."
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </label>

                    <label class="block text-xs font-medium text-slate-700">Pauta / Observações (opcional)
                        <textarea id="evDescricao" name="descricao" rows="2" maxlength="3000" placeholder="Detalhes importantes, documentos necessários ou pauta..."
                                  class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                    </label>

                    <!-- Seletor Visual de Participantes Envolvidos -->
                    <div class="rounded-xl border border-slate-200 p-3.5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h3 class="text-xs font-bold text-slate-800">Pessoas envolvidas</h3>
                                <p class="text-[11px] text-slate-500">Quem for marcado aqui verá este evento em destaque no próprio Dashboard Geral ao fazer login.</p>
                            </div>
                            <span id="contadorParticipantes" class="rounded-full bg-elo-teal/10 px-2.5 py-0.5 text-[11px] font-bold text-elo-teal">0 selecionado(s)</span>
                        </div>

                        <div id="chipsSelecionados" class="mt-2.5 flex flex-wrap gap-1.5 empty:hidden"></div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <input type="search" id="buscaParticipanteModal" placeholder="Buscar colaborador, voluntário, terceirizado..."
                                   class="min-w-[180px] flex-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs focus:border-elo-teal focus:outline-none">
                            <select id="filtroVinculoModal" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700">
                                <option value="">Todos vínculos</option>
                                <option value="Colaborador">Colaborador</option>
                                <option value="Voluntário">Voluntário</option>
                                <option value="Estagiário">Estagiário</option>
                                <option value="Terceirizado">Terceirizado</option>
                            </select>
                            <button type="button" id="btnLimparParticipantes" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-600 hover:bg-slate-50">
                                Limpar
                            </button>
                        </div>

                        <div id="gradeParticipantesModal" class="mt-2.5 grid max-h-48 gap-2 overflow-y-auto pr-1 sm:grid-cols-2"></div>
                    </div>
                </div>

                <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3.5">
                    <button type="button" id="btnExcluirEventoModal"
                            class="hidden items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700 hover:bg-rose-100">
                        <i class="ph ph-trash" aria-hidden="true"></i> Excluir evento
                    </button>
                    <div class="ml-auto flex items-center gap-2">
                        <button type="button" data-close-evento-modal class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit" class="rounded-lg bg-elo-teal px-4 py-2 text-xs font-semibold text-white hover:bg-elo-green">
                            Salvar evento
                        </button>
                    </div>
                </footer>
            </form>
        </div>
    </div>

    <div id="agendaToast" role="status" aria-live="polite"
         class="no-print pointer-events-none fixed bottom-5 right-5 z-50 hidden max-w-sm rounded-xl px-4 py-3 text-xs font-medium text-white shadow-lg"></div>

    <script src="<?php echo appEscape($baseUrl . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
    <script>
        (() => {
            const csrfToken = <?php echo json_encode(appCsrfToken(), JSON_THROW_ON_ERROR); ?>;
            const mesAtualPrefix = <?php echo json_encode($mesInicio->format('Y-m'), JSON_THROW_ON_ERROR); ?>;
            const hojeIso = <?php echo json_encode($hojeIso, JSON_THROW_ON_ERROR); ?>;
            const eventoAberturaId = <?php echo (int) $eventoAberturaId; ?>;
            const colaboradores = <?php echo json_encode($colaboradoresPayload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); ?>;
            let eventos = <?php echo json_encode($eventosPayload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); ?>;
            let diaSelecionado = <?php echo json_encode($diaInicialSelecionado, JSON_THROW_ON_ERROR); ?>;

            const getTabToken = () => new URLSearchParams(window.location.search).get('tabToken')
                || window.sessionStorage.getItem('sistemaEloTabToken') || '';

            const toastEl = document.getElementById('agendaToast');
            let toastTimer = null;
            const showToast = (msg, isError = false) => {
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
                if (!res.ok || !data.ok) throw new Error(data.message || 'Erro ao processar a solicitação.');
                return data;
            };

            const catMeta = {
                'Reunião': { pill: 'bg-teal-100 text-teal-900 border-teal-200', dot: 'bg-teal-600', chipActive: 'bg-teal-600 text-white border-teal-600' },
                'Vistoria / Órgão Público': { pill: 'bg-amber-100 text-amber-900 border-amber-200', dot: 'bg-amber-500', chipActive: 'bg-amber-600 text-white border-amber-600' },
                'Audiência / Judicial': { pill: 'bg-rose-100 text-rose-900 border-rose-200', dot: 'bg-rose-600', chipActive: 'bg-rose-600 text-white border-rose-600' },
                'Treinamento': { pill: 'bg-sky-100 text-sky-900 border-sky-200', dot: 'bg-sky-600', chipActive: 'bg-sky-600 text-white border-sky-600' },
                'Evento / Comemorativo': { pill: 'bg-purple-100 text-purple-900 border-purple-200', dot: 'bg-purple-600', chipActive: 'bg-purple-600 text-white border-purple-600' },
                'Manutenção': { pill: 'bg-orange-100 text-orange-900 border-orange-200', dot: 'bg-orange-500', chipActive: 'bg-orange-600 text-white border-orange-600' },
                'Outro': { pill: 'bg-slate-100 text-slate-800 border-slate-200', dot: 'bg-slate-500', chipActive: 'bg-slate-700 text-white border-slate-700' },
            };
            const statusBadges = {
                'Agendado': 'bg-slate-100 text-slate-700',
                'Confirmado': 'bg-emerald-100 text-emerald-800',
                'Concluído': 'bg-teal-100 text-teal-800',
                'Cancelado': 'bg-rose-100 text-rose-700 line-through',
            };
            const diasExtenso = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
            const mesesExtenso = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

            const formatarDataExtenso = (iso) => {
                const [y, m, d] = iso.split('-').map(Number);
                const dt = new Date(y, m - 1, d);
                return `${diasExtenso[dt.getDay()]}, ${String(d).padStart(2, '0')} de ${mesesExtenso[m - 1]} de ${y}`;
            };
            const formatarHorario = (ev) => {
                if (ev.diaInteiro) return 'Dia inteiro';
                if (ev.horaInicio && ev.horaFim) return `${ev.horaInicio} – ${ev.horaFim}`;
                if (ev.horaInicio) return ev.horaInicio;
                return 'Sem horário';
            };
            const createAvatarEl = (p, sizeClass = 'h-6 w-6 text-[9px]') => {
                if (p.fotoUrl) {
                    const img = document.createElement('img');
                    img.src = p.fotoUrl;
                    img.alt = p.nomeCompleto;
                    img.title = `${p.nomeCompleto} (${p.tipoVinculo})`;
                    img.className = `${sizeClass} shrink-0 rounded-full border border-white object-cover`;
                    return img;
                }
                const sp = document.createElement('span');
                sp.title = `${p.nomeCompleto} (${p.tipoVinculo})`;
                sp.className = `${sizeClass} inline-flex shrink-0 items-center justify-center rounded-full border border-white bg-elo-teal/15 font-bold text-elo-teal`;
                sp.textContent = p.iniciais;
                return sp;
            };

            const filtroCat = document.getElementById('filtroCategoriaAgenda');
            const filtroMeus = document.getElementById('filtroMeusEventos');
            const getFilteredEvents = () => eventos.filter((ev) => {
                if (filtroCat?.value && ev.categoria !== filtroCat.value) return false;
                if (filtroMeus?.checked && !ev.euEnvolvido) return false;
                return true;
            });

            let draggedEventId = null;

            const renderCalendar = () => {
                const filtrados = getFilteredEvents();
                const evsNoMes = filtrados.filter((e) => e.dataEvento.startsWith(mesAtualPrefix));
                document.getElementById('kpiEventosMes').textContent = String(evsNoMes.length);
                document.getElementById('kpiEventosAbertos').textContent = String(evsNoMes.filter((e) => e.statusEvento === 'Agendado' || e.statusEvento === 'Confirmado').length);
                document.getElementById('kpiMeusEventos').textContent = String(evsNoMes.filter((e) => e.euEnvolvido).length);
                document.getElementById('kpiConcluidos').textContent = String(evsNoMes.filter((e) => e.statusEvento === 'Concluído').length);

                document.querySelectorAll('[data-cal-day]').forEach((td) => {
                    const iso = td.dataset.calDay;
                    td.classList.toggle('cal-day-selected', iso === diaSelecionado);
                    const box = td.querySelector('[data-cal-events]');
                    box.replaceChildren();
                    const dayEvs = filtrados.filter((e) => e.dataEvento === iso);
                    dayEvs.slice(0, 3).forEach((ev) => {
                        const meta = catMeta[ev.categoria] || catMeta['Outro'];
                        const pill = document.createElement('div');
                        pill.draggable = true;
                        pill.className = `flex items-center gap-1 rounded border px-1.5 py-0.5 text-[10.5px] leading-tight transition hover:brightness-95 ${meta.pill} ${ev.statusEvento === 'Cancelado' ? 'opacity-55 line-through' : ''}`;
                        pill.title = `${ev.titulo} · ${formatarHorario(ev)}${ev.participantes.length ? ' · ' + ev.participantes.map((p) => p.nomeCurto).join(', ') : ''}`;
                        pill.addEventListener('dragstart', (e) => {
                            draggedEventId = ev.idEvento;
                            e.dataTransfer.effectAllowed = 'move';
                            e.dataTransfer.setData('text/plain', String(ev.idEvento));
                        });
                        pill.addEventListener('dragend', () => { draggedEventId = null; });
                        pill.addEventListener('click', (e) => {
                            e.stopPropagation();
                            diaSelecionado = iso;
                            renderCalendar();
                            openEventModal(ev);
                        });

                        if (ev.euEnvolvido) {
                            const star = document.createElement('i');
                            star.className = 'ph-fill ph-star shrink-0 text-[10px] text-elo-teal';
                            star.title = 'Você está envolvido neste evento';
                            pill.append(star);
                        }
                        if (!ev.diaInteiro && ev.horaInicio) {
                            const hr = document.createElement('span');
                            hr.className = 'shrink-0 font-bold opacity-80';
                            hr.textContent = ev.horaInicio;
                            pill.append(hr);
                        }
                        const tt = document.createElement('span');
                        tt.className = 'truncate font-medium';
                        tt.textContent = ev.titulo;
                        pill.append(tt);
                        box.append(pill);
                    });
                    if (dayEvs.length > 3) {
                        const more = document.createElement('div');
                        more.className = 'px-1 text-[10px] font-semibold text-elo-teal';
                        more.textContent = `+${dayEvs.length - 3} mais`;
                        box.append(more);
                    }
                });

                renderDaySidebar(filtrados);
            };

            const renderDaySidebar = (filtrados) => {
                document.getElementById('tituloDiaSelecionado').textContent = formatarDataExtenso(diaSelecionado);
                const listEl = document.getElementById('listaEventosDia');
                listEl.replaceChildren();

                const doDia = filtrados.filter((e) => e.dataEvento === diaSelecionado);
                if (!doDia.length) {
                    const empty = document.createElement('div');
                    empty.className = 'rounded-xl bg-slate-50 px-3 py-6 text-center text-xs text-slate-500';
                    empty.innerHTML = '<i class="ph ph-calendar-blank text-2xl text-slate-300" aria-hidden="true"></i><p class="mt-1">Nenhum evento neste dia.</p>';
                    listEl.append(empty);
                } else {
                    doDia.forEach((ev) => {
                        const meta = catMeta[ev.categoria] || catMeta['Outro'];
                        const card = document.createElement('article');
                        card.className = 'rounded-xl border border-slate-200 bg-slate-50/70 p-3 space-y-2';

                        const top = document.createElement('div');
                        top.className = 'flex flex-wrap items-center justify-between gap-1.5';
                        const badges = document.createElement('div');
                        badges.className = 'flex flex-wrap items-center gap-1';
                        const catB = document.createElement('span');
                        catB.className = `rounded-full border px-2 py-0.5 text-[10px] font-semibold ${meta.pill}`;
                        catB.textContent = ev.categoria;
                        const stB = document.createElement('span');
                        stB.className = `rounded-full px-2 py-0.5 text-[10px] font-semibold ${statusBadges[ev.statusEvento] || statusBadges['Agendado']}`;
                        stB.textContent = ev.statusEvento;
                        badges.append(catB, stB);
                        if (ev.euEnvolvido) {
                            const meB = document.createElement('span');
                            meB.className = 'rounded-full bg-elo-teal px-2 py-0.5 text-[10px] font-semibold text-white';
                            meB.textContent = 'Você participa';
                            badges.append(meB);
                        }
                        top.append(badges);
                        card.append(top);

                        const title = document.createElement('h3');
                        title.className = 'text-sm font-bold text-slate-800';
                        title.textContent = ev.titulo;
                        card.append(title);

                        const info = document.createElement('div');
                        info.className = 'flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-600';
                        info.innerHTML = `<span><i class="ph ph-clock mr-1 text-elo-teal" aria-hidden="true"></i>${formatarHorario(ev)}</span>`
                            + (ev.localEvento ? `<span><i class="ph ph-map-pin mr-1 text-elo-teal" aria-hidden="true"></i></span>` : '');
                        if (ev.localEvento) info.lastChild.append(document.createTextNode(ev.localEvento));
                        card.append(info);

                        if (ev.descricao) {
                            const desc = document.createElement('p');
                            desc.className = 'text-xs text-slate-600 line-clamp-2';
                            desc.textContent = ev.descricao;
                            card.append(desc);
                        }

                        if (ev.participantes.length) {
                            const pWrap = document.createElement('div');
                            pWrap.className = 'flex flex-wrap items-center gap-1.5 pt-1';
                            ev.participantes.forEach((p) => {
                                const chip = document.createElement('span');
                                chip.className = 'inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white py-0.5 pl-0.5 pr-2 text-[10.5px] font-medium text-slate-700';
                                chip.append(createAvatarEl(p, 'h-4 w-4 text-[8px]'), document.createTextNode(p.nomeCurto));
                                pWrap.append(chip);
                            });
                            card.append(pWrap);
                        }

                        const actions = document.createElement('div');
                        actions.className = 'flex flex-wrap items-center gap-1.5 border-t border-slate-200/70 pt-2';
                        if (ev.statusEvento !== 'Concluído') {
                            const btnOk = document.createElement('button');
                            btnOk.type = 'button';
                            btnOk.className = 'rounded-md border border-emerald-200 bg-white px-2 py-1 text-[11px] font-medium text-emerald-700 hover:bg-emerald-50';
                            btnOk.textContent = 'Concluir';
                            btnOk.addEventListener('click', () => alterarStatusEvento(ev.idEvento, 'Concluído'));
                            actions.append(btnOk);
                        }
                        const btnEd = document.createElement('button');
                        btnEd.type = 'button';
                        btnEd.className = 'rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-elo-teal hover:bg-elo-teal/5';
                        btnEd.textContent = 'Editar';
                        btnEd.addEventListener('click', () => openEventModal(ev));
                        actions.append(btnEd);
                        card.append(actions);

                        listEl.append(card);
                    });
                }

                // Próximos eventos do mês (a partir de hoje)
                const proxEl = document.getElementById('listaProximosMes');
                proxEl.replaceChildren();
                const proximos = filtrados
                    .filter((e) => e.dataEvento >= hojeIso && (e.statusEvento === 'Agendado' || e.statusEvento === 'Confirmado'))
                    .slice(0, 6);
                if (!proximos.length) {
                    proxEl.innerHTML = '<p class="rounded-lg bg-slate-50 p-3 text-center text-xs text-slate-500">Nenhum evento futuro neste mês.</p>';
                } else {
                    proximos.forEach((ev) => {
                        const meta = catMeta[ev.categoria] || catMeta['Outro'];
                        const row = document.createElement('button');
                        row.type = 'button';
                        row.className = 'flex w-full items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50/70 p-2.5 text-left transition hover:border-elo-teal/40 hover:bg-teal-50/30';
                        row.addEventListener('click', () => {
                            diaSelecionado = ev.dataEvento;
                            renderCalendar();
                        });
                        const [y, m, d] = ev.dataEvento.split('-');
                        const dateBox = document.createElement('div');
                        dateBox.className = 'flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-lg bg-white border border-slate-200';
                        dateBox.innerHTML = `<span class="text-xs font-bold leading-none text-slate-800">${d}</span><span class="mt-0.5 text-[9px] uppercase text-slate-500">${mesesExtenso[Number(m) - 1].slice(0, 3)}</span>`;
                        const txt = document.createElement('div');
                        txt.className = 'min-w-0 flex-1';
                        const t1 = document.createElement('div');
                        t1.className = 'flex items-center gap-1';
                        const dot = document.createElement('span');
                        dot.className = `h-2 w-2 shrink-0 rounded-full ${meta.dot}`;
                        const name = document.createElement('span');
                        name.className = 'truncate text-xs font-semibold text-slate-800';
                        name.textContent = ev.titulo;
                        t1.append(dot, name);
                        const t2 = document.createElement('div');
                        t2.className = 'mt-0.5 text-[10.5px] text-slate-500';
                        t2.textContent = `${formatarHorario(ev)}${ev.participantes.length ? ' · ' + ev.participantes.length + ' envolvido(s)' : ''}`;
                        txt.append(t1, t2);
                        row.append(dateBox, txt);
                        proxEl.append(row);
                    });
                }
            };

            const alterarStatusEvento = async (idEvento, statusEvento) => {
                try {
                    const data = await postAjax({ acao: 'status', idEvento, statusEvento });
                    eventos = eventos.map((e) => e.idEvento === idEvento ? data.evento : e);
                    renderCalendar();
                    showToast(`Evento marcado como ${statusEvento}.`);
                } catch (err) {
                    showToast(err.message, true);
                }
            };

            // Clique, duplo-clique e drag-and-drop nas células do calendário
            document.querySelectorAll('[data-cal-day]').forEach((td) => {
                const iso = td.dataset.calDay;
                td.addEventListener('click', () => {
                    diaSelecionado = iso;
                    renderCalendar();
                });
                td.addEventListener('dblclick', () => {
                    diaSelecionado = iso;
                    renderCalendar();
                    openEventModal(null, iso);
                });
                td.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    td.classList.add('cal-drop-active');
                });
                td.addEventListener('dragleave', () => td.classList.remove('cal-drop-active'));
                td.addEventListener('drop', async (e) => {
                    e.preventDefault();
                    td.classList.remove('cal-drop-active');
                    const evId = draggedEventId || Number(e.dataTransfer.getData('text/plain'));
                    if (!evId) return;
                    const cur = eventos.find((x) => x.idEvento === evId);
                    if (!cur || cur.dataEvento === iso) return;
                    try {
                        const data = await postAjax({ acao: 'mover', idEvento: evId, dataEvento: iso });
                        eventos = eventos.map((x) => x.idEvento === evId ? data.evento : x);
                        diaSelecionado = iso;
                        renderCalendar();
                        showToast(`"${cur.titulo}" reagendado para ${iso.split('-').reverse().join('/')}.`);
                    } catch (err) {
                        showToast(err.message, true);
                    }
                });
            });

            document.querySelectorAll('[data-day-add]').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    diaSelecionado = btn.dataset.dayAdd;
                    renderCalendar();
                    openEventModal(null, btn.dataset.dayAdd);
                });
            });

            // Modal e Seletor Visual de Participantes
            const modal = document.getElementById('modalEventoAgenda');
            const evIdInput = document.getElementById('evIdEvento');
            const evTitulo = document.getElementById('evTitulo');
            const evStatus = document.getElementById('evStatus');
            const evCategoria = document.getElementById('evCategoria');
            const evData = document.getElementById('evData');
            const evHoraIni = document.getElementById('evHoraInicio');
            const evHoraFim = document.getElementById('evHoraFim');
            const evDiaInteiro = document.getElementById('evDiaInteiro');
            const evLocal = document.getElementById('evLocal');
            const evDesc = document.getElementById('evDescricao');
            const btnExcluirModal = document.getElementById('btnExcluirEventoModal');
            const buscaPart = document.getElementById('buscaParticipanteModal');
            const filtroVinc = document.getElementById('filtroVinculoModal');
            const gradePart = document.getElementById('gradeParticipantesModal');
            const chipsSel = document.getElementById('chipsSelecionados');
            const contPart = document.getElementById('contadorParticipantes');
            let selecionadosSet = new Set();

            const syncCatChips = () => {
                const atual = evCategoria.value;
                document.querySelectorAll('[data-cat-chip]').forEach((btn) => {
                    const cat = btn.dataset.catChip;
                    const active = cat === atual;
                    const meta = catMeta[cat] || catMeta['Outro'];
                    btn.className = `rounded-full border px-3 py-1 text-xs font-medium transition ${active ? meta.chipActive : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'}`;
                });
            };
            document.querySelectorAll('[data-cat-chip]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    evCategoria.value = btn.dataset.catChip;
                    syncCatChips();
                });
            });

            const syncDiaInteiro = () => {
                const full = evDiaInteiro.checked;
                evHoraIni.disabled = full;
                evHoraFim.disabled = full;
                if (full) { evHoraIni.value = ''; evHoraFim.value = ''; }
            };
            evDiaInteiro.addEventListener('change', syncDiaInteiro);

            const renderParticipantesPicker = () => {
                const q = (buscaPart.value || '').trim().toLowerCase();
                const vinc = filtroVinc.value;
                contPart.textContent = `${selecionadosSet.size} selecionado(s)`;

                chipsSel.replaceChildren();
                colaboradores.filter((c) => selecionadosSet.has(c.idColaborador)).forEach((c) => {
                    const ch = document.createElement('span');
                    ch.className = 'inline-flex items-center gap-1 rounded-full bg-elo-teal/10 py-0.5 pl-1 pr-2 text-[11px] font-medium text-elo-teal';
                    const rm = document.createElement('button');
                    rm.type = 'button';
                    rm.className = 'ml-0.5 text-elo-teal hover:text-rose-600';
                    rm.textContent = '×';
                    rm.addEventListener('click', () => {
                        selecionadosSet.delete(c.idColaborador);
                        renderParticipantesPicker();
                    });
                    ch.append(createAvatarEl(c, 'h-4 w-4 text-[8px]'), document.createTextNode(c.nomeCurto), rm);
                    chipsSel.append(ch);
                });

                gradePart.replaceChildren();
                const filtrados = colaboradores.filter((c) => {
                    if (vinc && c.tipoVinculo !== vinc) return false;
                    if (q && !`${c.nomeCompleto} ${c.cargoFuncao} ${c.tipoVinculo}`.toLowerCase().includes(q)) return false;
                    return true;
                });
                if (!filtrados.length) {
                    gradePart.innerHTML = '<p class="col-span-2 py-4 text-center text-xs text-slate-400">Nenhuma pessoa encontrada.</p>';
                    return;
                }
                filtrados.forEach((c) => {
                    const checked = selecionadosSet.has(c.idColaborador);
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = `flex items-center gap-2 rounded-xl border p-2 text-left transition ${checked ? 'border-elo-teal bg-elo-teal/10 ring-1 ring-elo-teal' : 'border-slate-200 bg-white hover:border-elo-teal/40'}`;
                    btn.addEventListener('click', () => {
                        if (selecionadosSet.has(c.idColaborador)) selecionadosSet.delete(c.idColaborador);
                        else selecionadosSet.add(c.idColaborador);
                        renderParticipantesPicker();
                    });
                    const info = document.createElement('div');
                    info.className = 'min-w-0 flex-1';
                    const n = document.createElement('div');
                    n.className = 'truncate text-xs font-semibold text-slate-800';
                    n.textContent = c.nomeCurto;
                    const s = document.createElement('div');
                    s.className = 'truncate text-[10.5px] text-slate-500';
                    s.textContent = `${c.tipoVinculo} · ${c.cargoFuncao}`;
                    info.append(n, s);
                    const ic = document.createElement('i');
                    ic.className = checked ? 'ph-fill ph-check-circle text-lg text-elo-teal shrink-0' : 'ph ph-circle text-lg text-slate-300 shrink-0';
                    btn.append(createAvatarEl(c, 'h-8 w-8 text-[10px]'), info, ic);
                    gradePart.append(btn);
                });
            };
            buscaPart.addEventListener('input', renderParticipantesPicker);
            filtroVinc.addEventListener('change', renderParticipantesPicker);
            document.getElementById('btnLimparParticipantes').addEventListener('click', () => {
                selecionadosSet.clear();
                renderParticipantesPicker();
            });

            const openEventModal = (ev = null, presetDate = null) => {
                const isEdit = ev !== null;
                evIdInput.value = isEdit ? String(ev.idEvento) : '0';
                document.getElementById('tituloModalEvento').textContent = isEdit ? 'Editar Evento' : 'Novo Evento na Agenda';
                const dtAlvo = isEdit ? ev.dataEvento : (presetDate || diaSelecionado);
                document.getElementById('subtituloModalEvento').textContent = formatarDataExtenso(dtAlvo);
                evTitulo.value = isEdit ? ev.titulo : '';
                evStatus.value = isEdit ? ev.statusEvento : 'Agendado';
                evCategoria.value = isEdit ? ev.categoria : 'Reunião';
                evData.value = dtAlvo;
                evDiaInteiro.checked = isEdit ? ev.diaInteiro : false;
                evHoraIni.value = isEdit ? ev.horaInicio : '09:00';
                evHoraFim.value = isEdit ? ev.horaFim : '10:00';
                evLocal.value = isEdit ? ev.localEvento : '';
                evDesc.value = isEdit ? ev.descricao : '';
                selecionadosSet = new Set(isEdit ? ev.participanteIds : []);
                buscaPart.value = '';
                filtroVinc.value = '';
                syncCatChips();
                syncDiaInteiro();
                renderParticipantesPicker();

                if (isEdit) {
                    btnExcluirModal.classList.remove('hidden');
                    btnExcluirModal.classList.add('inline-flex');
                } else {
                    btnExcluirModal.classList.add('hidden');
                    btnExcluirModal.classList.remove('inline-flex');
                }
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                evTitulo.focus();
            };

            const closeEventModal = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            };

            document.querySelectorAll('[data-close-evento-modal]').forEach((b) => b.addEventListener('click', closeEventModal));
            modal.addEventListener('click', (e) => { if (e.target === modal) closeEventModal(); });
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeEventModal(); });
            evData.addEventListener('change', () => {
                if (evData.value) document.getElementById('subtituloModalEvento').textContent = formatarDataExtenso(evData.value);
            });

            document.getElementById('btnNovoEventoTopo').addEventListener('click', () => openEventModal(null, diaSelecionado));
            document.getElementById('btnAgendarDiaSelecionado').addEventListener('click', () => openEventModal(null, diaSelecionado));
            filtroCat?.addEventListener('change', renderCalendar);
            filtroMeus?.addEventListener('change', renderCalendar);

            document.getElementById('formEventoAgenda').addEventListener('submit', async (e) => {
                e.preventDefault();
                try {
                    const data = await postAjax({
                        acao: 'salvar',
                        idEvento: evIdInput.value,
                        titulo: evTitulo.value.trim(),
                        categoria: evCategoria.value,
                        statusEvento: evStatus.value,
                        dataEvento: evData.value,
                        diaInteiro: evDiaInteiro.checked ? '1' : '0',
                        horaInicio: evDiaInteiro.checked ? '' : evHoraIni.value,
                        horaFim: evDiaInteiro.checked ? '' : evHoraFim.value,
                        localEvento: evLocal.value.trim(),
                        descricao: evDesc.value.trim(),
                        participantes: JSON.stringify(Array.from(selecionadosSet)),
                    });
                    const saved = data.evento;
                    const idx = eventos.findIndex((x) => x.idEvento === saved.idEvento);
                    if (idx >= 0) eventos[idx] = saved;
                    else eventos.push(saved);
                    diaSelecionado = saved.dataEvento;
                    renderCalendar();
                    closeEventModal();
                    showToast(idx >= 0 ? 'Evento atualizado na agenda.' : 'Evento adicionado à agenda.');
                } catch (err) {
                    showToast(err.message, true);
                }
            });

            btnExcluirModal.addEventListener('click', async () => {
                const id = Number(evIdInput.value);
                if (!id || !window.confirm('Confirma excluir este evento da agenda?')) return;
                try {
                    await postAjax({ acao: 'excluir', idEvento: id });
                    eventos = eventos.filter((x) => x.idEvento !== id);
                    renderCalendar();
                    closeEventModal();
                    showToast('Evento excluído da agenda.');
                } catch (err) {
                    showToast(err.message, true);
                }
            });

            renderCalendar();

            if (eventoAberturaId > 0) {
                const target = eventos.find((e) => e.idEvento === eventoAberturaId);
                if (target) {
                    diaSelecionado = target.dataEvento;
                    renderCalendar();
                    openEventModal(target);
                }
            }
        })();
    </script>
</body>
</html>
