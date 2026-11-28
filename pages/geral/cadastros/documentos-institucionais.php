<?php
require_once __DIR__ . '/../../../auth/access.php';
require_once __DIR__ . '/../../../auth/documento-institucional-arquivos.php';
$usuarioAtual = appRequirePage('geral.cadastros/documentos-institucionais');
$pdo = appPdo();
$baseUrl = appBaseUrl();
$urlAtual = $baseUrl . '/pages/geral/cadastros/documentos-institucionais.php';
$urlArquivoBase = $baseUrl . '/auth/documento-institucional-arquivo.php';

$categorias = [
    'Alvará Sanitário',
    'AVCB / Bombeiros',
    'CMAS / CMDCA',
    'Certidão Negativa (CND)',
    'Estatuto / Regimento',
    'Ata da Diretoria',
    'Convênio / Parceria',
    'Licença / Outros',
];

$textoPost = static function (string $campo): string {
    $valor = $_POST[$campo] ?? '';
    return is_scalar($valor) ? trim((string) $valor) : '';
};
$validarData = static function (string $data): bool {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
    return $d !== false && $d->format('Y-m-d') === $data;
};
$formatarAuditoria = static function ($idUsuario, $nomeUsuario, $dataHora): string {
    if ($idUsuario === null || $nomeUsuario === null || $dataHora === null) {
        return '—';
    }
    $ts = strtotime((string) $dataHora);
    return $ts === false ? '—' : ((int) $idUsuario . ' - ' . (string) $nomeUsuario . ' - ' . date('d/m/Y H:i', $ts));
};

$hoje = new DateTimeImmutable('today');
$classificarSituacao = static function (array $doc) use ($hoje): array {
    if ((int) ($doc['ativo'] ?? 1) !== 1) {
        return ['chave' => 'desatualizado', 'label' => 'Desatualizado', 'detalhe' => 'Substituído / fora dos alertas', 'classe' => 'bg-slate-200 text-slate-700 border-slate-300'];
    }
    if ((int) ($doc['semValidade'] ?? 0) === 1 || empty($doc['dataValidade'])) {
        return ['chave' => 'valido', 'label' => 'Válido (Permanente)', 'detalhe' => 'Sem vencimento', 'classe' => 'bg-emerald-100 text-emerald-800 border-emerald-200'];
    }
    $val = new DateTimeImmutable((string) $doc['dataValidade']);
    $diff = (int) $hoje->diff($val)->format('%r%a');
    $alertaDias = max(0, (int) ($doc['diasAlertaAntes'] ?? 30));

    if ($diff < 0) {
        $atraso = abs($diff);
        return [
            'chave' => 'vencido',
            'label' => 'Vencido',
            'detalhe' => 'Vencido há ' . $atraso . ' dia' . ($atraso === 1 ? '' : 's'),
            'classe' => 'bg-rose-100 text-rose-800 border-rose-200',
        ];
    }
    if ($diff <= $alertaDias) {
        return [
            'chave' => 'vencendo',
            'label' => $diff === 0 ? 'Vence hoje' : 'Vencendo em breve',
            'detalhe' => $diff === 0 ? 'Vence hoje' : ('Vence em ' . $diff . ' dia' . ($diff === 1 ? '' : 's')),
            'classe' => 'bg-amber-100 text-amber-800 border-amber-200',
        ];
    }
    return [
        'chave' => 'valido',
        'label' => 'Válido',
        'detalhe' => 'Faltam ' . $diff . ' dias',
        'classe' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
    ];
};

$erro = '';
$formSubmitted = null;
$arquivoNovoInfo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    $acao = $textoPost('acao');

    try {
        if ($acao === 'salvar') {
            $idDocumento = filter_var($_POST['idDocumento'] ?? 0, FILTER_VALIDATE_INT);
            $idDocumento = ($idDocumento !== false && $idDocumento > 0) ? (int) $idDocumento : 0;

            $titulo = $textoPost('titulo');
            $categoria = $textoPost('categoria');
            $numeroDocumento = $textoPost('numeroDocumento');
            $orgaoEmissor = $textoPost('orgaoEmissor');
            $dataEmissao = $textoPost('dataEmissao');
            $semValidade = isset($_POST['semValidade']) ? 1 : 0;
            $dataValidade = $semValidade === 1 ? '' : $textoPost('dataValidade');
            $diasAlertaInput = filter_var($_POST['diasAlertaAntes'] ?? 30, FILTER_VALIDATE_INT);
            $diasAlertaAntes = $diasAlertaInput !== false ? (int) $diasAlertaInput : 30;
            $observacoes = $textoPost('observacoes');
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $substituirIdInput = filter_var($_POST['substituirDocumentoId'] ?? 0, FILTER_VALIDATE_INT);
            $substituirDocumentoId = ($idDocumento === 0 && $substituirIdInput !== false && $substituirIdInput > 0) ? (int) $substituirIdInput : 0;
            $removerArquivo = isset($_POST['removerArquivo']) && (string) $_POST['removerArquivo'] === '1';
            $upload = $_FILES['arquivo'] ?? null;
            $temUpload = is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

            $formSubmitted = [
                'idDocumento' => $idDocumento,
                'titulo' => $titulo,
                'categoria' => $categoria,
                'numeroDocumento' => $numeroDocumento,
                'orgaoEmissor' => $orgaoEmissor,
                'dataEmissao' => $dataEmissao,
                'semValidade' => $semValidade,
                'dataValidade' => $dataValidade,
                'diasAlertaAntes' => $diasAlertaAntes,
                'observacoes' => $observacoes,
                'ativo' => $ativo,
                'substituirDocumentoId' => $substituirDocumentoId,
            ];

            if ($titulo === '' || mb_strlen($titulo, 'UTF-8') > 150) {
                throw new InvalidArgumentException('Informe o título do documento (até 150 caracteres).');
            }
            if (!in_array($categoria, $categorias, true)) {
                throw new InvalidArgumentException('Selecione uma categoria válida.');
            }
            if (mb_strlen($numeroDocumento, 'UTF-8') > 80 || mb_strlen($orgaoEmissor, 'UTF-8') > 120) {
                throw new InvalidArgumentException('Número do documento ou órgão emissor excede o tamanho permitido.');
            }
            if ($dataEmissao !== '' && !$validarData($dataEmissao)) {
                throw new InvalidArgumentException('Informe uma data de emissão válida.');
            }
            if ($semValidade === 0) {
                if ($dataValidade === '' || !$validarData($dataValidade)) {
                    throw new InvalidArgumentException('Informe a data de validade ou marque "Documento permanente / sem vencimento".');
                }
                if ($dataEmissao !== '' && $dataValidade < $dataEmissao) {
                    throw new InvalidArgumentException('A data de validade não pode ser anterior à data de emissão.');
                }
            }
            if ($diasAlertaAntes < 0 || $diasAlertaAntes > 365) {
                throw new InvalidArgumentException('O prazo de alerta deve estar entre 0 e 365 dias.');
            }
            if (mb_strlen($observacoes, 'UTF-8') > 500) {
                throw new InvalidArgumentException('As observações devem ter até 500 caracteres.');
            }
            if ($removerArquivo && $temUpload) {
                throw new InvalidArgumentException('Não é possível enviar um novo anexo e marcar remoção ao mesmo tempo.');
            }

            $anterior = null;
            if ($idDocumento > 0) {
                $chk = $pdo->prepare('SELECT caminhoArquivo, nomeOriginalArquivo FROM tbDocumentoInstitucional WHERE idDocumento = :id LIMIT 1');
                $chk->execute(['id' => $idDocumento]);
                $anterior = $chk->fetch(PDO::FETCH_ASSOC);
                if (!$anterior) {
                    throw new InvalidArgumentException('Documento não encontrado.');
                }
            }

            if ($temUpload) {
                $arquivoNovoInfo = appValidarUploadDocumentoInstitucional($upload);
            }
            $caminhoArquivo = $removerArquivo
                ? null
                : ($arquivoNovoInfo['caminho'] ?? (is_string($anterior['caminhoArquivo'] ?? null) ? $anterior['caminhoArquivo'] : null));
            $nomeOriginal = $removerArquivo
                ? null
                : ($arquivoNovoInfo['nomeOriginal'] ?? (is_string($anterior['nomeOriginalArquivo'] ?? null) ? $anterior['nomeOriginalArquivo'] : null));

            $params = [
                'titulo' => $titulo,
                'categoria' => $categoria,
                'numeroDocumento' => $numeroDocumento !== '' ? $numeroDocumento : null,
                'orgaoEmissor' => $orgaoEmissor !== '' ? $orgaoEmissor : null,
                'dataEmissao' => $dataEmissao !== '' ? $dataEmissao : null,
                'dataValidade' => $dataValidade !== '' ? $dataValidade : null,
                'semValidade' => $semValidade,
                'diasAlertaAntes' => $diasAlertaAntes,
                'caminhoArquivo' => $caminhoArquivo,
                'nomeOriginalArquivo' => $nomeOriginal,
                'observacoes' => $observacoes !== '' ? $observacoes : null,
                'ativo' => $ativo,
            ];

            if ($idDocumento > 0) {
                $upd = $pdo->prepare(
                    'UPDATE tbDocumentoInstitucional
                     SET titulo = :titulo, categoria = :categoria, numeroDocumento = :numeroDocumento,
                         orgaoEmissor = :orgaoEmissor, dataEmissao = :dataEmissao, dataValidade = :dataValidade,
                         semValidade = :semValidade, diasAlertaAntes = :diasAlertaAntes,
                         caminhoArquivo = :caminhoArquivo, nomeOriginalArquivo = :nomeOriginalArquivo,
                         observacoes = :observacoes, ativo = :ativo,
                         idUsuarioAlteracao = :uid
                     WHERE idDocumento = :id'
                );
                $params['uid'] = (int) $usuarioAtual['idUsuario'];
                $params['id'] = $idDocumento;
                $upd->execute($params);

                $substituiu = ($removerArquivo || $arquivoNovoInfo !== null) && !empty($anterior['caminhoArquivo']);
                $arquivoNovoInfo = null;
                if ($substituiu) {
                    try {
                        appRemoverDocumentoInstitucional((string) $anterior['caminhoArquivo']);
                    } catch (RuntimeException $e) {
                        error_log($e->getMessage());
                    }
                }

                appRegistrarAuditoria(
                    'geral',
                    'editar',
                    'Documento institucional atualizado: ' . $titulo . ' (' . $categoria . ').',
                    'tbDocumentoInstitucional',
                    $idDocumento,
                    ['categoria' => $categoria, 'dataValidade' => $dataValidade ?: null, 'semValidade' => (bool) $semValidade, 'ativo' => (bool) $ativo],
                    $usuarioAtual
                );
                header('Location: ' . appTabUrl('documentos-institucionais.php?resultado=atualizado'));
                exit;
            }

            $docSubstituido = null;
            if ($substituirDocumentoId > 0) {
                $subChk = $pdo->prepare('SELECT idDocumento, titulo, observacoes FROM tbDocumentoInstitucional WHERE idDocumento = :id AND ativo = 1 LIMIT 1');
                $subChk->execute(['id' => $substituirDocumentoId]);
                $docSubstituido = $subChk->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($docSubstituido === null) {
                    throw new InvalidArgumentException('O documento anterior selecionado para marcar como desatualizado não foi encontrado ou já está desatualizado.');
                }
            }

            $pdo->beginTransaction();
            try {
                $ins = $pdo->prepare(
                    'INSERT INTO tbDocumentoInstitucional
                        (titulo, categoria, numeroDocumento, orgaoEmissor, dataEmissao, dataValidade,
                         semValidade, diasAlertaAntes, caminhoArquivo, nomeOriginalArquivo,
                         observacoes, ativo, idUsuarioCriacao)
                     VALUES
                        (:titulo, :categoria, :numeroDocumento, :orgaoEmissor, :dataEmissao, :dataValidade,
                         :semValidade, :diasAlertaAntes, :caminhoArquivo, :nomeOriginalArquivo,
                         :observacoes, :ativo, :uid)'
                );
                $params['uid'] = (int) $usuarioAtual['idUsuario'];
                $ins->execute($params);
                $novoId = (int) $pdo->lastInsertId();

                if ($docSubstituido !== null) {
                    $notaSubst = 'Substituído por "' . $titulo . '" (#' . $novoId . ') em ' . date('d/m/Y') . '.';
                    $obsAntiga = trim((string) ($docSubstituido['observacoes'] ?? ''));
                    $novaObsAntiga = $obsAntiga !== '' ? ($obsAntiga . ' | ' . $notaSubst) : $notaSubst;
                    $novaObsAntiga = mb_substr($novaObsAntiga, 0, 500, 'UTF-8');

                    $updAntigo = $pdo->prepare(
                        'UPDATE tbDocumentoInstitucional
                         SET ativo = 0, observacoes = :obs, idUsuarioAlteracao = :uid
                         WHERE idDocumento = :id'
                    );
                    $updAntigo->execute([
                        'obs' => $novaObsAntiga,
                        'uid' => (int) $usuarioAtual['idUsuario'],
                        'id' => (int) $docSubstituido['idDocumento'],
                    ]);
                }
                $pdo->commit();
            } catch (Throwable $txErr) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $txErr;
            }
            $arquivoNovoInfo = null;

            appRegistrarAuditoria(
                'geral',
                'criar',
                'Documento institucional cadastrado: ' . $titulo . ' (' . $categoria . ')'
                . ($docSubstituido !== null ? ' — substituindo "' . $docSubstituido['titulo'] . '".' : '.'),
                'tbDocumentoInstitucional',
                $novoId,
                [
                    'categoria' => $categoria,
                    'dataValidade' => $dataValidade ?: null,
                    'semValidade' => (bool) $semValidade,
                    'documentoSubstituidoId' => $docSubstituido !== null ? (int) $docSubstituido['idDocumento'] : null,
                ],
                $usuarioAtual
            );
            header('Location: ' . appTabUrl('documentos-institucionais.php?resultado=' . ($docSubstituido !== null ? 'renovado' : 'criado')));
            exit;
        }

        if ($acao === 'alterarStatus') {
            $idDocumento = filter_var($_POST['idDocumento'] ?? 0, FILTER_VALIDATE_INT);
            $novoAtivo = ($_POST['novoAtivo'] ?? '0') === '1' ? 1 : 0;
            if (!$idDocumento || $idDocumento < 1) {
                throw new InvalidArgumentException('Documento inválido.');
            }
            $chk = $pdo->prepare('SELECT titulo, categoria FROM tbDocumentoInstitucional WHERE idDocumento = :id LIMIT 1');
            $chk->execute(['id' => $idDocumento]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new InvalidArgumentException('Documento não encontrado.');
            }
            $upd = $pdo->prepare(
                'UPDATE tbDocumentoInstitucional SET ativo = :ativo, idUsuarioAlteracao = :uid WHERE idDocumento = :id'
            );
            $upd->execute([
                'ativo' => $novoAtivo,
                'uid' => (int) $usuarioAtual['idUsuario'],
                'id' => (int) $idDocumento,
            ]);

            appRegistrarAuditoria(
                'geral',
                'editar',
                'Documento "' . $row['titulo'] . '" marcado como ' . ($novoAtivo === 1 ? 'ativo (monitorado).' : 'desatualizado (fora dos alertas).'),
                'tbDocumentoInstitucional',
                (int) $idDocumento,
                ['ativo' => (bool) $novoAtivo],
                $usuarioAtual
            );
            header('Location: ' . appTabUrl('documentos-institucionais.php?resultado=' . ($novoAtivo === 1 ? 'reativado' : 'desatualizado')));
            exit;
        }

        if ($acao === 'excluir') {
            $idDocumento = filter_var($_POST['idDocumento'] ?? 0, FILTER_VALIDATE_INT);
            if (!$idDocumento || $idDocumento < 1) {
                throw new InvalidArgumentException('Documento inválido.');
            }
            $chk = $pdo->prepare('SELECT titulo, categoria, caminhoArquivo FROM tbDocumentoInstitucional WHERE idDocumento = :id LIMIT 1');
            $chk->execute(['id' => $idDocumento]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new InvalidArgumentException('Documento não encontrado.');
            }
            $pdo->prepare('DELETE FROM tbDocumentoInstitucional WHERE idDocumento = :id')->execute(['id' => $idDocumento]);
            if (!empty($row['caminhoArquivo'])) {
                try {
                    appRemoverDocumentoInstitucional((string) $row['caminhoArquivo']);
                } catch (RuntimeException $e) {
                    error_log($e->getMessage());
                }
            }

            appRegistrarAuditoria(
                'geral',
                'excluir',
                'Documento institucional excluído: ' . $row['titulo'] . ' (' . $row['categoria'] . ').',
                'tbDocumentoInstitucional',
                (int) $idDocumento,
                ['titulo' => $row['titulo'], 'categoria' => $row['categoria']],
                $usuarioAtual
            );
            header('Location: ' . appTabUrl('documentos-institucionais.php?resultado=excluido'));
            exit;
        }

        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException | RuntimeException $e) {
        $erro = $e->getMessage();
    } catch (PDOException $e) {
        error_log('Erro em documentos institucionais: ' . $e->getMessage());
        $erro = 'Não foi possível salvar o documento agora.';
    } finally {
        if ($arquivoNovoInfo !== null) {
            try {
                appRemoverDocumentoInstitucional($arquivoNovoInfo['caminho']);
            } catch (RuntimeException) {
            }
            $arquivoNovoInfo = null;
        }
    }
}

$docEdicao = null;
$idEdicao = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
$idRenovar = filter_input(INPUT_GET, 'renovar', FILTER_VALIDATE_INT);
if ($formSubmitted !== null && (int) $formSubmitted['idDocumento'] > 0) {
    $idEdicao = (int) $formSubmitted['idDocumento'];
}
if ($idEdicao) {
    $st = $pdo->prepare(
        'SELECT d.*, uc.nomeCompleto AS nomeUsuarioCriacao, ua.nomeCompleto AS nomeUsuarioAlteracao
         FROM tbDocumentoInstitucional d
         INNER JOIN tbUsuario uc ON uc.idUsuario = d.idUsuarioCriacao
         LEFT JOIN tbUsuario ua ON ua.idUsuario = d.idUsuarioAlteracao
         WHERE d.idDocumento = :id LIMIT 1'
    );
    $st->execute(['id' => $idEdicao]);
    $docEdicao = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} elseif ($idRenovar && $formSubmitted === null) {
    $stRen = $pdo->prepare(
        'SELECT idDocumento, titulo, categoria, orgaoEmissor, diasAlertaAntes
         FROM tbDocumentoInstitucional
         WHERE idDocumento = :id AND ativo = 1 LIMIT 1'
    );
    $stRen->execute(['id' => $idRenovar]);
    if ($renRow = $stRen->fetch(PDO::FETCH_ASSOC)) {
        $docEdicao = [
            'idDocumento' => 0,
            'titulo' => (string) $renRow['titulo'],
            'categoria' => (string) $renRow['categoria'],
            'orgaoEmissor' => (string) ($renRow['orgaoEmissor'] ?? ''),
            'diasAlertaAntes' => (int) ($renRow['diasAlertaAntes'] ?? 30),
            'ativo' => 1,
            'semValidade' => 0,
            'substituirDocumentoId' => (int) $renRow['idDocumento'],
        ];
    }
}
if ($formSubmitted !== null) {
    $docEdicao = array_merge($docEdicao ?? [], $formSubmitted);
}

$documentos = $pdo->query(
    'SELECT d.*, uc.nomeCompleto AS nomeUsuarioCriacao, ua.nomeCompleto AS nomeUsuarioAlteracao
     FROM tbDocumentoInstitucional d
     INNER JOIN tbUsuario uc ON uc.idUsuario = d.idUsuarioCriacao
     LEFT JOIN tbUsuario ua ON ua.idUsuario = d.idUsuarioAlteracao
     ORDER BY d.ativo DESC, d.semValidade ASC, COALESCE(d.dataValidade, \'9999-12-31\') ASC, d.titulo ASC'
)->fetchAll(PDO::FETCH_ASSOC);

$contadores = ['ativos' => 0, 'validos' => 0, 'vencendo' => 0, 'vencidos' => 0, 'desatualizados' => 0];
$candidatosSubstituicao = [];
foreach ($documentos as &$docItem) {
    $sit = $classificarSituacao($docItem);
    $docItem['_situacao'] = $sit;
    if ((int) $docItem['ativo'] === 1) {
        $contadores['ativos']++;
        if ($sit['chave'] === 'vencido') {
            $contadores['vencidos']++;
        } elseif ($sit['chave'] === 'vencendo') {
            $contadores['vencendo']++;
        } else {
            $contadores['validos']++;
        }
        $candidatosSubstituicao[] = $docItem;
    } else {
        $contadores['desatualizados']++;
    }
}
unset($docItem);

usort($candidatosSubstituicao, static function (array $a, array $b): int {
    $peso = ['vencido' => 0, 'vencendo' => 1, 'valido' => 2];
    $pa = $peso[$a['_situacao']['chave']] ?? 3;
    $pb = $peso[$b['_situacao']['chave']] ?? 3;
    return $pa !== $pb ? ($pa <=> $pb) : strcmp((string) $a['titulo'], (string) $b['titulo']);
});

$criadoPor = $docEdicao
    ? $formatarAuditoria($docEdicao['idUsuarioCriacao'] ?? null, $docEdicao['nomeUsuarioCriacao'] ?? null, $docEdicao['criadoEm'] ?? null)
    : '—';
$alteradoPor = $docEdicao
    ? $formatarAuditoria($docEdicao['idUsuarioAlteracao'] ?? null, $docEdicao['nomeUsuarioAlteracao'] ?? null, $docEdicao['atualizadoEm'] ?? null)
    : '—';

$resultado = [
    'criado' => 'Documento cadastrado com sucesso.',
    'renovado' => 'Novo documento cadastrado e o documento anterior foi marcado como desatualizado.',
    'atualizado' => 'Documento atualizado com sucesso.',
    'desatualizado' => 'Documento marcado como desatualizado e removido dos alertas de vencimento.',
    'reativado' => 'Documento reativado e recolocado no monitoramento de validade.',
    'excluido' => 'Documento excluído com sucesso.',
][(string) ($_GET['resultado'] ?? '')] ?? '';
$abaInicial = ($docEdicao || $erro !== '' || ($_GET['aba'] ?? '') === 'manutencao') ? 'manutencao' : 'pesquisa';
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
            <h1 class="mb-5 text-2xl font-bold text-gray-800">Documentos e Certidões Institucionais</h1>

            <section class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-elo-teal/10 text-elo-teal">
                        <i class="ph ph-files text-2xl" aria-hidden="true"></i>
                    </span>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Ativos</p>
                        <p class="mt-0.5 text-2xl font-bold text-gray-800"><?php echo $contadores['ativos']; ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 shadow-sm">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-emerald-200/80 text-emerald-700">
                        <i class="ph ph-seal-check text-2xl" aria-hidden="true"></i>
                    </span>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-emerald-700">Válidos</p>
                        <p class="mt-0.5 text-2xl font-bold text-emerald-900"><?php echo $contadores['validos']; ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50/70 p-4 shadow-sm">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-amber-200/80 text-amber-700">
                        <i class="ph ph-clock-countdown text-2xl" aria-hidden="true"></i>
                    </span>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-amber-700">Vencendo em breve</p>
                        <p class="mt-0.5 text-2xl font-bold text-amber-900"><?php echo $contadores['vencendo']; ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50/70 p-4 shadow-sm">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-rose-200/80 text-rose-700">
                        <i class="ph ph-warning-circle text-2xl" aria-hidden="true"></i>
                    </span>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-rose-700">Vencidos</p>
                        <p class="mt-0.5 text-2xl font-bold text-rose-900"><?php echo $contadores['vencidos']; ?></p>
                    </div>
                </div>
            </section>

            <?php include __DIR__ . '/../../../components/tabs-pesquisa-manutencao.php'; ?>

            <section id="painel-manutencao" role="tabpanel" aria-labelledby="tab-manutencao"
                     class="mb-8 rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'manutencao' ? '' : 'hidden'; ?>>
                <h2 class="mb-4 text-lg font-semibold"><?php echo !empty($docEdicao['idDocumento']) ? 'Alterar documento / certidão' : 'Novo documento / certidão'; ?></h2>
                <form method="post" enctype="multipart/form-data" class="space-y-5" data-confirm="Confirma gravar este documento institucional?">
                    <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="idDocumento" value="<?php echo (int) ($docEdicao['idDocumento'] ?? 0); ?>">

                    <?php if (empty($docEdicao['idDocumento']) && !empty($candidatosSubstituicao)): ?>
                        <?php $subAtual = (int) ($docEdicao['substituirDocumentoId'] ?? 0); ?>
                        <fieldset class="rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                            <legend class="px-2 text-sm font-semibold text-amber-900">Renovação / Substituição (opcional)</legend>
                            <label class="block text-sm font-medium text-amber-950">Marcar documento anterior como <strong>Desatualizado</strong> ao salvar este novo
                                <select name="substituirDocumentoId" class="mt-1.5 w-full rounded border border-amber-300 bg-white px-3 py-2 text-sm text-gray-800">
                                    <option value="">Não substituir nenhum documento anterior</option>
                                    <?php foreach ($candidatosSubstituicao as $cand): ?>
                                        <?php
                                        $stCand = $cand['_situacao'];
                                        $valTxt = !empty($cand['dataValidade']) && (int) $cand['semValidade'] !== 1
                                            ? ' · Val.: ' . date('d/m/Y', strtotime((string) $cand['dataValidade']))
                                            : '';
                                        ?>
                                        <option value="<?php echo (int) $cand['idDocumento']; ?>" <?php echo $subAtual === (int) $cand['idDocumento'] ? 'selected' : ''; ?>>
                                            <?php echo appEscape('[' . strtoupper($stCand['label']) . '] ' . $cand['titulo'] . ' (' . $cand['categoria'] . ')' . $valTxt); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <p class="mt-1.5 text-xs text-amber-800">O documento selecionado sairá imediatamente dos alertas de vencidos/vencendo, mas continuará salvo no histórico com o anexo original.</p>
                        </fieldset>
                    <?php endif; ?>

                    <fieldset class="grid gap-4 rounded-lg border border-gray-200 p-4 md:grid-cols-3">
                        <legend class="px-2 text-sm font-semibold text-gray-700">1. Identificação do documento</legend>
                        <label class="text-sm font-medium text-gray-700 md:col-span-2">Título do documento *
                            <input name="titulo" required maxlength="150" placeholder="Ex.: Alvará Sanitário 2026, AVCB Bombeiros, CND Federal..."
                                   value="<?php echo appEscape((string) ($docEdicao['titulo'] ?? '')); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Categoria *
                            <select name="categoria" required class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                                <?php $catAtual = (string) ($docEdicao['categoria'] ?? 'Certidão Negativa (CND)'); ?>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo appEscape($cat); ?>" <?php echo $catAtual === $cat ? 'selected' : ''; ?>>
                                        <?php echo appEscape($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="text-sm font-medium text-gray-700">Número / Protocolo
                            <input name="numeroDocumento" maxlength="80" placeholder="Ex.: 2026/00451"
                                   value="<?php echo appEscape((string) ($docEdicao['numeroDocumento'] ?? '')); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700 md:col-span-2">Órgão emissor
                            <input name="orgaoEmissor" maxlength="120" placeholder="Ex.: Prefeitura Municipal, Corpo de Bombeiros, Receita Federal..."
                                   value="<?php echo appEscape((string) ($docEdicao['orgaoEmissor'] ?? '')); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                    </fieldset>

                    <fieldset class="grid gap-4 rounded-lg border border-gray-200 p-4 md:grid-cols-3">
                        <legend class="px-2 text-sm font-semibold text-gray-700">2. Validade e alertas</legend>
                        <label class="text-sm font-medium text-gray-700">Data de emissão
                            <input type="date" name="dataEmissao"
                                   value="<?php echo appEscape((string) ($docEdicao['dataEmissao'] ?? '')); ?>"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Data de validade *
                            <input type="date" id="docDataValidade" name="dataValidade"
                                   value="<?php echo appEscape((string) ($docEdicao['dataValidade'] ?? '')); ?>"
                                   <?php echo (int) ($docEdicao['semValidade'] ?? 0) === 1 ? 'disabled' : 'required'; ?>
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2 disabled:bg-gray-100">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Alertar quantos dias antes
                            <input type="number" id="docDiasAlerta" name="diasAlertaAntes" min="0" max="365"
                                   value="<?php echo (int) ($docEdicao['diasAlertaAntes'] ?? 30); ?>"
                                   <?php echo (int) ($docEdicao['semValidade'] ?? 0) === 1 ? 'disabled' : ''; ?>
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2 disabled:bg-gray-100">
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 md:col-span-2">
                            <input type="checkbox" id="docSemValidade" name="semValidade" value="1"
                                   <?php echo (int) ($docEdicao['semValidade'] ?? 0) === 1 ? 'checked' : ''; ?>
                                   class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                            Documento permanente / sem data de vencimento (ex.: Estatuto, CNPJ)
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input type="checkbox" name="ativo" value="1"
                                   <?php echo (int) ($docEdicao['ativo'] ?? 1) === 1 ? 'checked' : ''; ?>
                                   class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                            Documento vigente / monitorar validade (desmarque para marcar como desatualizado)
                        </label>
                    </fieldset>

                    <fieldset class="space-y-3 rounded-lg border border-gray-200 p-4">
                        <legend class="px-2 text-sm font-semibold text-gray-700">3. Anexo digital (PDF ou Imagem até 10 MB)</legend>
                        <?php if (!empty($docEdicao['caminhoArquivo']) && !empty($docEdicao['idDocumento'])): ?>
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-elo-teal/30 bg-elo-teal/5 px-3.5 py-2.5 text-sm">
                                <span class="flex items-center gap-2 font-medium text-gray-800" data-preserve-case>
                                    <i class="ph ph-file-pdf text-xl text-elo-teal" aria-hidden="true"></i>
                                    <?php echo appEscape((string) ($docEdicao['nomeOriginalArquivo'] ?: 'Anexo atual')); ?>
                                </span>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button"
                                            data-doc-preview="<?php echo appEscape(appTabUrl($urlArquivoBase . '?id=' . (int) $docEdicao['idDocumento'])); ?>"
                                            data-doc-title="<?php echo appEscape((string) $docEdicao['titulo']); ?>"
                                            data-doc-ext="<?php echo appEscape(strtolower(pathinfo((string) $docEdicao['caminhoArquivo'], PATHINFO_EXTENSION))); ?>"
                                            class="rounded border border-elo-teal/40 bg-white px-3 py-1 text-xs font-medium text-elo-teal hover:bg-elo-teal/10">
                                        Visualizar
                                    </button>
                                    <a href="<?php echo appEscape(appTabUrl($urlArquivoBase . '?id=' . (int) $docEdicao['idDocumento'] . '&download=1')); ?>"
                                       class="rounded border border-gray-300 bg-white px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                        Baixar
                                    </a>
                                    <label class="inline-flex items-center gap-1.5 rounded border border-rose-200 bg-white px-2.5 py-1 text-xs text-rose-700">
                                        <input type="checkbox" name="removerArquivo" value="1" class="rounded border-rose-300 text-rose-600">
                                        Remover anexo
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="arquivo" accept="application/pdf,image/jpeg,image/png,image/webp"
                               class="block w-full text-sm text-gray-700 file:mr-3 file:rounded file:border-0 file:bg-elo-teal/10 file:px-4 file:py-2 file:text-sm file:font-medium file:text-elo-teal hover:file:bg-elo-teal/20">
                        <p class="text-xs text-gray-500">Formatos aceitos: PDF, JPG, PNG ou WebP (máx. 10 MB). Armazenado em diretório privado com acesso autenticado.</p>
                    </fieldset>

                    <fieldset class="grid gap-4 rounded-lg border border-gray-200 p-4 md:grid-cols-2">
                        <legend class="px-2 text-sm font-semibold text-gray-700">4. Observações e auditoria</legend>
                        <label class="text-sm font-medium text-gray-700 md:col-span-2">Observações / Providências para renovação
                            <textarea name="observacoes" maxlength="500" rows="2" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"><?php echo appEscape((string) ($docEdicao['observacoes'] ?? '')); ?></textarea>
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
                            <?php echo !empty($docEdicao['idDocumento']) ? 'Salvar alterações' : 'Cadastrar documento'; ?>
                        </button>
                        <?php if (!empty($docEdicao['idDocumento'])): ?>
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
                    <h2 class="text-lg font-semibold">Acervo de documentos e certidões</h2>
                    <div class="flex flex-wrap gap-2">
                        <input data-filter-input="listaDocumentos" type="search" placeholder="Pesquisar documento, órgão, nº..." aria-label="Pesquisar documentos institucionais"
                               class="rounded border border-gray-300 px-3 py-2 text-sm">
                        <button type="button" data-tab-open="manutencao" class="rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">
                            + Novo documento
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1020px] text-left text-sm">
                        <thead class="border-b bg-gray-50 text-gray-600">
                            <tr>
                                <th class="p-3">Documento / Nº</th>
                                <th class="p-3">Categoria / Órgão</th>
                                <th class="p-3">Validade</th>
                                <th class="p-3">Situação</th>
                                <th class="p-3">Anexo</th>
                                <th class="p-3">Auditoria</th>
                                <th class="p-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="listaDocumentos">
                            <?php foreach ($documentos as $item): ?>
                                <?php $sit = $item['_situacao']; ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-3">
                                        <div class="font-medium text-gray-800" data-preserve-case><?php echo appEscape((string) $item['titulo']); ?></div>
                                        <?php if (!empty($item['numeroDocumento'])): ?>
                                            <div class="text-xs text-gray-500" data-preserve-case>Nº <?php echo appEscape((string) $item['numeroDocumento']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3">
                                        <span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">
                                            <?php echo appEscape((string) $item['categoria']); ?>
                                        </span>
                                        <?php if (!empty($item['orgaoEmissor'])): ?>
                                            <div class="mt-1 text-xs text-gray-500" data-preserve-case><?php echo appEscape((string) $item['orgaoEmissor']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="whitespace-nowrap p-3 text-xs text-gray-700" data-preserve-case>
                                        <?php if ((int) $item['semValidade'] === 1 || empty($item['dataValidade'])): ?>
                                            <span class="font-medium text-gray-600">Permanente</span>
                                        <?php else: ?>
                                            <div class="font-semibold text-gray-800"><?php echo date('d/m/Y', strtotime((string) $item['dataValidade'])); ?></div>
                                            <?php if (!empty($item['dataEmissao'])): ?>
                                                <div class="text-gray-400">Emissão: <?php echo date('d/m/Y', strtotime((string) $item['dataEmissao'])); ?></div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="whitespace-nowrap p-3">
                                        <span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold <?php echo appEscape($sit['classe']); ?>">
                                            <?php echo appEscape($sit['label']); ?>
                                        </span>
                                        <div class="mt-0.5 text-[11px] text-gray-500" data-preserve-case><?php echo appEscape($sit['detalhe']); ?></div>
                                    </td>
                                    <td class="whitespace-nowrap p-3">
                                        <?php if (!empty($item['caminhoArquivo'])): ?>
                                            <div class="flex items-center gap-1.5">
                                                <button type="button"
                                                        data-doc-preview="<?php echo appEscape(appTabUrl($urlArquivoBase . '?id=' . (int) $item['idDocumento'])); ?>"
                                                        data-doc-title="<?php echo appEscape((string) $item['titulo']); ?>"
                                                        data-doc-ext="<?php echo appEscape(strtolower(pathinfo((string) $item['caminhoArquivo'], PATHINFO_EXTENSION))); ?>"
                                                        class="inline-flex items-center gap-1 rounded border border-elo-teal/30 bg-elo-teal/5 px-2.5 py-1 text-xs font-medium text-elo-teal hover:bg-elo-teal/10">
                                                    <i class="ph ph-eye" aria-hidden="true"></i> Ver
                                                </button>
                                                <a href="<?php echo appEscape(appTabUrl($urlArquivoBase . '?id=' . (int) $item['idDocumento'] . '&download=1')); ?>"
                                                   aria-label="Baixar anexo de <?php echo appEscape((string) $item['titulo']); ?>"
                                                   title="Baixar anexo"
                                                   class="inline-flex h-7 w-7 items-center justify-center rounded border border-gray-200 text-gray-600 hover:bg-gray-100">
                                                    <i class="ph ph-download-simple text-sm" aria-hidden="true"></i>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400">Sem anexo</span>
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
                                            <?php if (in_array($sit['chave'], ['vencido', 'vencendo'], true)): ?>
                                                <a href="<?php echo appEscape(appTabUrl($urlAtual . '?renovar=' . (int) $item['idDocumento'] . '#manutencao')); ?>"
                                                   aria-label="Renovar <?php echo appEscape((string) $item['titulo']); ?>"
                                                   title="Subir nova versão e marcar este como desatualizado"
                                                   class="inline-flex items-center gap-1 rounded border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100">
                                                    <i class="ph ph-arrows-clockwise text-sm" aria-hidden="true"></i> Renovar
                                                </a>
                                            <?php endif; ?>
                                            <?php if ((int) $item['ativo'] === 1): ?>
                                                <form method="post" data-confirm="Marcar '<?php echo appEscape((string) $item['titulo']); ?>' como desatualizado? Ele sairá dos alertas de vencimento mas permanecerá no histórico.">
                                                    <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                                                    <input type="hidden" name="acao" value="alterarStatus">
                                                    <input type="hidden" name="idDocumento" value="<?php echo (int) $item['idDocumento']; ?>">
                                                    <input type="hidden" name="novoAtivo" value="0">
                                                    <button type="submit" aria-label="Marcar <?php echo appEscape((string) $item['titulo']); ?> como desatualizado"
                                                            title="Marcar como desatualizado (remover dos alertas)"
                                                            class="inline-flex h-9 w-9 items-center justify-center rounded border border-slate-300 text-slate-600 hover:bg-slate-100">
                                                        <i class="ph ph-archive text-lg" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" data-confirm="Reativar '<?php echo appEscape((string) $item['titulo']); ?>' para voltar a monitorar a validade?">
                                                    <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                                                    <input type="hidden" name="acao" value="alterarStatus">
                                                    <input type="hidden" name="idDocumento" value="<?php echo (int) $item['idDocumento']; ?>">
                                                    <input type="hidden" name="novoAtivo" value="1">
                                                    <button type="submit" aria-label="Reativar <?php echo appEscape((string) $item['titulo']); ?>"
                                                            title="Reativar monitoramento de validade"
                                                            class="inline-flex h-9 w-9 items-center justify-center rounded border border-emerald-200 text-emerald-700 hover:bg-emerald-50">
                                                        <i class="ph ph-arrow-u-up-left text-lg" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <a href="<?php echo appEscape(appTabUrl($urlAtual . '?editar=' . (int) $item['idDocumento'] . '#manutencao')); ?>"
                                               aria-label="Editar <?php echo appEscape((string) $item['titulo']); ?>"
                                               title="Editar documento"
                                               class="inline-flex h-9 w-9 items-center justify-center rounded border border-elo-teal/20 text-elo-teal hover:bg-elo-teal/10">
                                                <i class="ph ph-pencil-simple text-lg" aria-hidden="true"></i>
                                            </a>
                                            <form method="post" data-confirm="Confirma excluir o documento <?php echo appEscape((string) $item['titulo']); ?>?">
                                                <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                                                <input type="hidden" name="acao" value="excluir">
                                                <input type="hidden" name="idDocumento" value="<?php echo (int) $item['idDocumento']; ?>">
                                                <button type="submit" aria-label="Excluir <?php echo appEscape((string) $item['titulo']); ?>"
                                                        class="inline-flex h-9 w-9 items-center justify-center rounded border border-red-200 text-red-600 hover:bg-red-50">
                                                    <i class="ph ph-trash text-lg" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$documentos): ?>
                                <tr><td colspan="7" class="px-3 py-10 text-center text-gray-500">Nenhum documento ou certidão cadastrado ainda.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <!-- Modal de Visualização de Anexo (na mesma aba) -->
    <div id="modalVisualizarDocumento" role="dialog" aria-modal="true" aria-labelledby="tituloModalDocPreview"
         class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/65 p-3 md:p-6">
        <div class="flex h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <header class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-3.5">
                <h2 id="tituloModalDocPreview" class="truncate text-base font-bold text-slate-800" data-preserve-case>Visualizar documento</h2>
                <button type="button" id="btnFecharDocPreview" aria-label="Fechar visualização"
                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <i class="ph ph-x text-xl" aria-hidden="true"></i>
                </button>
            </header>
            <div id="corpoDocPreview" class="flex flex-1 items-center justify-center overflow-auto bg-slate-100 p-3"></div>
        </div>
    </div>

    <?php $toastSuccess = $resultado; $toastError = $erro; include __DIR__ . '/../../../components/toast.php'; ?>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
    <script>
        (() => {
            const semVal = document.getElementById('docSemValidade');
            const dtVal = document.getElementById('docDataValidade');
            const diasAlerta = document.getElementById('docDiasAlerta');
            const syncValidade = () => {
                if (!semVal || !dtVal) return;
                const perm = semVal.checked;
                dtVal.disabled = perm;
                dtVal.required = !perm;
                if (perm) dtVal.value = '';
                if (diasAlerta) diasAlerta.disabled = perm;
            };
            semVal?.addEventListener('change', syncValidade);
            syncValidade();

            const modal = document.getElementById('modalVisualizarDocumento');
            const corpo = document.getElementById('corpoDocPreview');
            const titulo = document.getElementById('tituloModalDocPreview');
            const fechar = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                corpo.replaceChildren();
            };
            document.querySelectorAll('[data-doc-preview]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const url = btn.dataset.docPreview;
                    const ext = btn.dataset.docExt || '';
                    titulo.textContent = btn.dataset.docTitle || 'Documento';
                    corpo.replaceChildren();
                    if (ext === 'pdf') {
                        const iframe = document.createElement('iframe');
                        iframe.src = url;
                        iframe.className = 'h-full w-full rounded border border-slate-200 bg-white';
                        corpo.append(iframe);
                    } else {
                        const img = document.createElement('img');
                        img.src = url;
                        img.alt = titulo.textContent;
                        img.className = 'max-h-full max-w-full rounded shadow';
                        corpo.append(img);
                    }
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                });
            });
            document.getElementById('btnFecharDocPreview')?.addEventListener('click', fechar);
            modal?.addEventListener('click', (e) => { if (e.target === modal) fechar(); });
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) fechar(); });
        })();
    </script>
</body>
</html>
