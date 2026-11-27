<?php
require_once __DIR__ . '/../../../auth/access.php';
require_once __DIR__ . '/../../../auth/colaborador-fotos.php';
$usuarioAtual = appRequirePage('geral.cadastros/colaboradores');
$pdo = appPdo();
$baseUrl = appBaseUrl();
$urlColaboradores = $baseUrl . '/pages/geral/cadastros/colaboradores.php';
$urlEscala = $baseUrl . '/pages/geral/movimentacoes/escala-plantoes.php';
$urlAtual = $baseUrl . '/pages/geral/cadastros/cracha-colaborador.php';
$origem = ($_GET['origem'] ?? '') === 'escala' ? 'escala' : 'colaboradores';
$urlVoltar = $origem === 'escala' ? $urlEscala : $urlColaboradores;

$idInput = $_GET['id'] ?? null;
$idColaborador = is_scalar($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;

$todosColaboradores = $pdo->query(
    'SELECT idColaborador, nomeCompleto, tipoVinculo, cargoFuncao, ativo
     FROM tbColaborador
     ORDER BY ativo DESC, nomeCompleto'
)->fetchAll(PDO::FETCH_ASSOC);

if (($idColaborador === false || $idColaborador < 1) && !empty($todosColaboradores)) {
    $idColaborador = (int) $todosColaboradores[0]['idColaborador'];
}

$colaborador = null;
if ($idColaborador !== false && $idColaborador > 0) {
    $stmt = $pdo->prepare(
        'SELECT idColaborador, nomeCompleto, cpf, rg, dataNascimento, caminhoFoto,
                tipoVinculo, cargoFuncao, registroProfissional, turno, dataInicio,
                telefone, email, ativo
         FROM tbColaborador
         WHERE idColaborador = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $idColaborador]);
    $colaborador = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($colaborador === null) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="pt-br"><meta charset="UTF-8"><title>Sistema Elo</title>'
        . '<body style="margin:0;padding:40px;background:#f3f4f6;font:15px Arial,sans-serif;color:#1f2937">'
        . '<main style="max-width:540px;margin:auto;padding:28px;background:#fff;border:1px solid #d1d5db;border-radius:10px">'
        . '<h1 style="margin-top:0">Colaborador não encontrado</h1>'
        . '<p>Cadastre um colaborador ou voluntário antes de emitir o crachá de identificação.</p>'
        . '<a href="' . appEscape(appTabUrl($urlColaboradores)) . '" style="display:inline-block;margin-top:12px;padding:10px 16px;background:#007c89;color:#fff;text-decoration:none;border-radius:6px">Voltar aos colaboradores</a>'
        . '</main></body></html>';
    exit;
}

$instituicao = $pdo->query(
    'SELECT razaoSocial, nomeFantasia, cnpj, email, telefone, cep, logradouro, numero, complemento, bairro, cidade, uf
     FROM tbInformacoesInstituicao
     WHERE idInformacoes = 1
     LIMIT 1'
)->fetch(PDO::FETCH_ASSOC) ?: [];

$nomeInstituicao = trim((string) ($instituicao['nomeFantasia'] ?? ''))
    ?: trim((string) ($instituicao['razaoSocial'] ?? ''))
    ?: 'Casa de Acolhimento';
$razaoInstituicao = trim((string) ($instituicao['razaoSocial'] ?? '')) ?: $nomeInstituicao;

$formatarCpf = static function (?string $cpf): string {
    $d = preg_replace('/\D+/', '', (string) $cpf) ?? '';
    return strlen($d) === 11
        ? substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9)
        : (string) $cpf;
};
$formatarCnpj = static function (?string $cnpj): string {
    $d = preg_replace('/\D+/', '', (string) $cnpj) ?? '';
    return strlen($d) === 14
        ? substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3) . '/' . substr($d, 8, 4) . '-' . substr($d, 12)
        : (string) $cnpj;
};
$formatarTelefone = static function (?string $tel): string {
    $d = preg_replace('/\D+/', '', (string) $tel) ?? '';
    if (strlen($d) === 11) {
        return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7);
    }
    if (strlen($d) === 10) {
        return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6);
    }
    return (string) $tel;
};
$iniciaisNome = static function (string $nome): string {
    $partes = preg_split('/\s+/', trim($nome)) ?: [];
    if (!$partes) {
        return 'EL';
    }
    $primeira = mb_substr($partes[0], 0, 1, 'UTF-8');
    $ultima = count($partes) > 1 ? mb_substr($partes[count($partes) - 1], 0, 1, 'UTF-8') : mb_substr($partes[0], 1, 1, 'UTF-8');
    return mb_strtoupper($primeira . $ultima, 'UTF-8');
};

$enderecoPartes = [];
$logr = trim((string) ($instituicao['logradouro'] ?? ''));
$num = trim((string) ($instituicao['numero'] ?? ''));
if ($logr !== '') {
    $enderecoPartes[] = $logr . ($num !== '' ? ', ' . $num : '');
}
if (!empty($instituicao['bairro'])) {
    $enderecoPartes[] = trim((string) $instituicao['bairro']);
}
$cidUf = trim((string) ($instituicao['cidade'] ?? ''));
if (!empty($instituicao['uf'])) {
    $cidUf .= ($cidUf !== '' ? '/' : '') . strtoupper(trim((string) $instituicao['uf']));
}
if ($cidUf !== '') {
    $enderecoPartes[] = $cidUf;
}
$enderecoInstituicao = implode(' · ', $enderecoPartes);

$modo = is_scalar($_GET['modo'] ?? null) ? (string) $_GET['modo'] : 'caseiro_dobravel';
if (!in_array($modo, ['caseiro_dobravel', 'caseiro_adesivo', 'grafica_pvc'], true)) {
    $modo = 'caseiro_dobravel';
}
$mostrarCpf = ($_GET['cpf'] ?? '1') !== '0';
$mostrarRegistro = ($_GET['reg'] ?? '1') !== '0';
$mostrarFuro = ($_GET['furo'] ?? '1') !== '0';
$temaCor = is_scalar($_GET['cor'] ?? null) ? (string) $_GET['cor'] : 'auto';

$coresVinculo = [
    'Colaborador' => ['primary' => '#007c89', 'dark' => '#005b66', 'soft' => '#e6f4f5', 'text' => '#005b66'],
    'Voluntário' => ['primary' => '#6d28d9', 'dark' => '#4c1d95', 'soft' => '#f3e8ff', 'text' => '#5b21b6'],
    'Estagiário' => ['primary' => '#0369a1', 'dark' => '#075985', 'soft' => '#e0f2fe', 'text' => '#075985'],
    'Terceirizado' => ['primary' => '#b45309', 'dark' => '#78350f', 'soft' => '#fef3c7', 'text' => '#92400e'],
];
$paletaManual = [
    'teal' => ['primary' => '#007c89', 'dark' => '#005b66', 'soft' => '#e6f4f5', 'text' => '#005b66'],
    'navy' => ['primary' => '#1e3a8a', 'dark' => '#172554', 'soft' => '#dbeafe', 'text' => '#1e3a8a'],
    'emerald' => ['primary' => '#047857', 'dark' => '#064e3b', 'soft' => '#d1fae5', 'text' => '#065f46'],
    'purple' => ['primary' => '#6d28d9', 'dark' => '#4c1d95', 'soft' => '#f3e8ff', 'text' => '#5b21b6'],
    'amber' => ['primary' => '#b45309', 'dark' => '#78350f', 'soft' => '#fef3c7', 'text' => '#92400e'],
    'slate' => ['primary' => '#334155', 'dark' => '#0f172a', 'soft' => '#f1f5f9', 'text' => '#1e293b'],
];
$paleta = ($temaCor !== 'auto' && isset($paletaManual[$temaCor]))
    ? $paletaManual[$temaCor]
    : ($coresVinculo[$colaborador['tipoVinculo']] ?? $coresVinculo['Colaborador']);

$matricula = 'COL-' . str_pad((string) $colaborador['idColaborador'], 4, '0', STR_PAD_LEFT);
$iniciais = $iniciaisNome((string) $colaborador['nomeCompleto']);
$dataInicioFmt = !empty($colaborador['dataInicio']) ? date('d/m/Y', strtotime((string) $colaborador['dataInicio'])) : '—';
$emissaoFmt = date('d/m/Y');
$fotoUrlWeb = !empty($colaborador['caminhoFoto'])
    ? appTabUrl($baseUrl . '/auth/colaborador-foto.php?id=' . (int) $colaborador['idColaborador'])
    : null;

$carregarImagemDataUri = static function (?string $absPath, int $targetW = 276, int $targetH = 312): ?string {
    if ($absPath === null || !is_file($absPath)) {
        return null;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($absPath);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return null;
    }

    // Center-crop proporcional via GD para o Dompdf nunca distorcer fotos (ele ignora object-fit: cover)
    if (extension_loaded('gd')) {
        $src = match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($absPath) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($absPath) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absPath) : false,
            default => false,
        };
        if ($src !== false) {
            $sw = imagesx($src);
            $sh = imagesy($src);
            if ($sw > 0 && $sh > 0) {
                $targetRatio = $targetW / $targetH;
                $srcRatio = $sw / $sh;
                if ($srcRatio > $targetRatio) {
                    $cropH = $sh;
                    $cropW = (int) round($sh * $targetRatio);
                    $srcX = (int) round(($sw - $cropW) / 2);
                    $srcY = 0;
                } else {
                    $cropW = $sw;
                    $cropH = (int) round($sw / $targetRatio);
                    $srcX = 0;
                    // Desloca levemente para cima para preservar o rosto em fotos verticais
                    $srcY = (int) round(($sh - $cropH) * 0.25);
                }
                $dst = imagecreatetruecolor($targetW, $targetH);
                $white = imagecolorallocate($dst, 255, 255, 255);
                imagefilledrectangle($dst, 0, 0, $targetW, $targetH, $white);
                imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $targetW, $targetH, $cropW, $cropH);
                ob_start();
                imagejpeg($dst, null, 92);
                $bytes = ob_get_clean();
                imagedestroy($src);
                imagedestroy($dst);
                if (is_string($bytes) && $bytes !== '') {
                    return 'data:image/jpeg;base64,' . base64_encode($bytes);
                }
            } else {
                imagedestroy($src);
            }
        }
    }

    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
        return null;
    }
    $bytes = @file_get_contents($absPath);
    return is_string($bytes) && $bytes !== '' ? 'data:' . $mime . ';base64,' . base64_encode($bytes) : null;
};

if (($_GET['formato'] ?? '') === 'pdf') {
    $pdfTipo = ($_GET['pdfTipo'] ?? 'grafica') === 'a4' ? 'a4' : 'grafica';
    require_once __DIR__ . '/../../../vendor/autoload.php';

    $fotoAbs = !empty($colaborador['caminhoFoto']) ? appColaboradorFotoPath((string) $colaborador['caminhoFoto']) : null;
    $fotoDataUri = $carregarImagemDataUri($fotoAbs, 276, 312);

    $pPrimary = $paleta['primary'];
    $pDark = $paleta['dark'];
    $pSoft = $paleta['soft'];
    $pText = $paleta['text'];

    $fotoHtmlPdf = $fotoDataUri !== null
        ? '<img src="' . $fotoDataUri . '" style="width:23mm;height:26mm;border-radius:2.5mm;border:0.7mm solid #ffffff;display:block;margin:0 auto;">'
        : '<table style="width:23mm;height:26mm;background:#e2e8f0;border-radius:2.5mm;border:0.7mm solid #ffffff;margin:0 auto;border-collapse:collapse;"><tr><td style="text-align:center;vertical-align:middle;color:' . $pDark . ';font-size:15pt;font-weight:bold;">' . appEscape($iniciais) . '</td></tr></table>';

    $furoHtmlPdf = $mostrarFuro
        ? '<div style="width:11mm;height:2.1mm;border:0.35mm dashed rgba(255,255,255,0.85);border-radius:1.1mm;margin:0 auto 1.8mm;"></div>'
        : '<div style="height:1.8mm;"></div>';

    $frenteInner = '
        <div style="width:54mm;height:86mm;background:#ffffff;position:relative;overflow:hidden;font-family:\'DejaVu Sans\',sans-serif;box-sizing:border-box;">
            <div style="background:' . $pPrimary . ';color:#ffffff;padding:2.2mm 3mm 7mm;text-align:center;">
                ' . $furoHtmlPdf . '
                <div style="font-size:6.4pt;font-weight:bold;line-height:1.18;text-transform:uppercase;letter-spacing:0.2pt;">' . appEscape($nomeInstituicao) . '</div>
                <div style="font-size:5pt;opacity:0.9;margin-top:0.6mm;letter-spacing:0.6pt;text-transform:uppercase;">Identificação Funcional</div>
            </div>
            <div style="margin-top:-5.5mm;text-align:center;">
                ' . $fotoHtmlPdf . '
            </div>
            <div style="padding:2mm 3mm 0;text-align:center;">
                <div style="font-size:8.2pt;font-weight:bold;color:#0f172a;line-height:1.18;">' . appEscape((string) $colaborador['nomeCompleto']) . '</div>
                <div style="font-size:6.5pt;color:#475569;margin-top:0.8mm;line-height:1.18;">' . appEscape((string) $colaborador['cargoFuncao']) . '</div>
                ' . ($mostrarRegistro && !empty($colaborador['registroProfissional'])
                    ? '<div style="font-size:5.6pt;color:#64748b;margin-top:0.4mm;">' . appEscape((string) $colaborador['registroProfissional']) . '</div>'
                    : '') . '
                <div style="margin-top:1.8mm;">
                    <span style="display:inline-block;padding:0.7mm 2.8mm;border-radius:3mm;background:' . $pSoft . ';color:' . $pText . ';font-size:5.8pt;font-weight:bold;text-transform:uppercase;letter-spacing:0.4pt;">' . appEscape((string) $colaborador['tipoVinculo']) . '</span>
                </div>
            </div>
            <div style="position:absolute;left:0;right:0;bottom:0;border-top:0.3mm solid #e2e8f0;background:#f8fafc;padding:1.6mm 3.2mm 1.8mm;">
                <table style="width:100%;border-collapse:collapse;font-size:5.8pt;color:#475569;">
                    <tr>
                        <td style="text-align:left;"><strong style="color:#0f172a;">' . appEscape($matricula) . '</strong></td>
                        <td style="text-align:right;">Turno: <strong style="color:#0f172a;">' . appEscape((string) $colaborador['turno']) . '</strong></td>
                    </tr>
                </table>
                <div style="height:1.3mm;background:' . $pPrimary . ';margin-top:1.2mm;border-radius:1mm;"></div>
            </div>
        </div>';

    $versoInner = '
        <div style="width:54mm;height:86mm;background:#ffffff;position:relative;overflow:hidden;font-family:\'DejaVu Sans\',sans-serif;box-sizing:border-box;">
            <div style="background:' . $pDark . ';color:#ffffff;padding:2.2mm 3mm;text-align:center;">
                ' . $furoHtmlPdf . '
                <div style="font-size:6.2pt;font-weight:bold;letter-spacing:0.5pt;text-transform:uppercase;">Dados Institucionais</div>
            </div>
            <div style="padding:2.2mm 3.2mm;font-size:5.7pt;color:#334155;line-height:1.26;">
                <table style="width:100%;border-collapse:collapse;margin-bottom:1.8mm;">
                    <tr><td style="color:#64748b;padding:0.4mm 0;">Matrícula:</td><td style="text-align:right;font-weight:bold;color:#0f172a;">' . appEscape($matricula) . '</td></tr>
                    <tr><td style="color:#64748b;padding:0.4mm 0;">Vínculo:</td><td style="text-align:right;font-weight:bold;color:#0f172a;">' . appEscape((string) $colaborador['tipoVinculo']) . '</td></tr>
                    ' . ($mostrarCpf ? '<tr><td style="color:#64748b;padding:0.4mm 0;">CPF:</td><td style="text-align:right;font-weight:bold;color:#0f172a;">' . appEscape($formatarCpf((string) $colaborador['cpf'])) . '</td></tr>' : '') . '
                    ' . ($mostrarRegistro && !empty($colaborador['registroProfissional']) ? '<tr><td style="color:#64748b;padding:0.4mm 0;">Registro:</td><td style="text-align:right;font-weight:bold;color:#0f172a;">' . appEscape((string) $colaborador['registroProfissional']) . '</td></tr>' : '') . '
                    <tr><td style="color:#64748b;padding:0.4mm 0;">Início:</td><td style="text-align:right;font-weight:bold;color:#0f172a;">' . appEscape($dataInicioFmt) . '</td></tr>
                </table>
                <div style="border-top:0.3mm solid #cbd5e1;padding-top:1.6mm;margin-bottom:1.8mm;">
                    <div style="font-size:6pt;font-weight:bold;color:#0f172a;line-height:1.2;">' . appEscape($razaoInstituicao) . '</div>
                    ' . (!empty($instituicao['cnpj']) ? '<div style="font-size:5.3pt;color:#475569;margin-top:0.4mm;">CNPJ: ' . appEscape($formatarCnpj((string) $instituicao['cnpj'])) . '</div>' : '') . '
                    ' . ($enderecoInstituicao !== '' ? '<div style="font-size:5.1pt;color:#475569;margin-top:0.4mm;line-height:1.22;">' . appEscape($enderecoInstituicao) . '</div>' : '') . '
                    ' . (!empty($instituicao['telefone']) ? '<div style="font-size:5.3pt;color:#475569;margin-top:0.4mm;">Tel.: ' . appEscape($formatarTelefone((string) $instituicao['telefone'])) . '</div>' : '') . '
                </div>
                <div style="background:#f8fafc;border:0.3mm solid #e2e8f0;border-radius:1.4mm;padding:1.5mm 1.8mm;font-size:4.9pt;color:#475569;text-align:justify;line-height:1.25;">
                    Crachá de uso pessoal e intransferível. Em caso de perda, extravio ou desligamento, devolver imediatamente à administração.
                </div>
            </div>
            <div style="position:absolute;left:3.2mm;right:3.2mm;bottom:2.2mm;text-align:center;">
                <div style="border-top:0.3mm solid #64748b;width:78%;margin:0 auto 0.8mm;"></div>
                <div style="font-size:5.4pt;font-weight:bold;color:#334155;">Coordenação / Administração</div>
                <div style="font-size:4.8pt;color:#94a3b8;margin-top:0.4mm;">Emitido em ' . appEscape($emissaoFmt) . ' · Sistema Elo</div>
            </div>
        </div>';

    $options = new \Dompdf\Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isRemoteEnabled', false);
    $dompdf = new \Dompdf\Dompdf($options);

    if ($pdfTipo === 'grafica') {
        $htmlPdf = '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><style>
            @page { margin: 0; }
            body { margin: 0; padding: 0; background: #ffffff; }
            .bleed-page { width: 60mm; height: 92mm; position: relative; background: ' . $pPrimary . '; page-break-after: always; }
            .bleed-page.verso { background: #ffffff; page-break-after: auto; }
            .card-wrap { position: absolute; top: 3mm; left: 3mm; width: 54mm; height: 86mm; }
            .crop-tl { position: absolute; top: 0; left: 3mm; width: 0; height: 2.2mm; border-left: 0.2mm solid #0f172a; }
            .crop-tl2 { position: absolute; top: 3mm; left: 0; width: 2.2mm; height: 0; border-top: 0.2mm solid #0f172a; }
            .crop-tr { position: absolute; top: 0; right: 3mm; width: 0; height: 2.2mm; border-right: 0.2mm solid #0f172a; }
            .crop-tr2 { position: absolute; top: 3mm; right: 0; width: 2.2mm; height: 0; border-top: 0.2mm solid #0f172a; }
            .crop-bl { position: absolute; bottom: 0; left: 3mm; width: 0; height: 2.2mm; border-left: 0.2mm solid #0f172a; }
            .crop-bl2 { position: absolute; bottom: 3mm; left: 0; width: 2.2mm; height: 0; border-top: 0.2mm solid #0f172a; }
            .crop-br { position: absolute; bottom: 0; right: 3mm; width: 0; height: 2.2mm; border-right: 0.2mm solid #0f172a; }
            .crop-br2 { position: absolute; bottom: 3mm; right: 0; width: 2.2mm; height: 0; border-top: 0.2mm solid #0f172a; }
        </style></head><body>
            <div class="bleed-page">
                <div class="card-wrap">' . $frenteInner . '</div>
                <div class="crop-tl"></div><div class="crop-tl2"></div>
                <div class="crop-tr"></div><div class="crop-tr2"></div>
                <div class="crop-bl"></div><div class="crop-bl2"></div>
                <div class="crop-br"></div><div class="crop-br2"></div>
            </div>
            <div class="bleed-page verso">
                <div class="card-wrap">' . $versoInner . '</div>
                <div class="crop-tl"></div><div class="crop-tl2"></div>
                <div class="crop-tr"></div><div class="crop-tr2"></div>
                <div class="crop-bl"></div><div class="crop-bl2"></div>
                <div class="crop-br"></div><div class="crop-br2"></div>
            </div>
        </body></html>';
        // 60mm x 92mm em pontos (1mm = 2.83464567pt)
        $dompdf->setPaper([0, 0, 60 * 2.83464567, 92 * 2.83464567], 'portrait');
        $downloadFilename = 'cracha-grafica-' . strtolower($matricula) . '.pdf';
    } else {
        $apenasFrente = $modo === 'caseiro_adesivo';
        $cardsTable = $apenasFrente
            ? '<table style="margin:15mm auto 0;border-collapse:collapse;"><tr>
                    <td style="border:0.35mm dashed #64748b;padding:0;">' . $frenteInner . '</td>
                    <td style="width:8mm;"></td>
                    <td style="border:0.35mm dashed #64748b;padding:0;">' . $frenteInner . '</td>
               </tr></table>
               <p style="text-align:center;font-size:8pt;color:#64748b;margin-top:6mm;">Modo Papel Adesivo (2 cópias da frente em tamanho CR80: 54 × 86 mm). Recorte na linha pontilhada.</p>'
            : '<table style="margin:15mm auto 0;border-collapse:collapse;"><tr>
                    <td style="border:0.35mm dashed #64748b;border-right:0.4mm dotted #0f172a;padding:0;">' . $frenteInner . '</td>
                    <td style="border:0.35mm dashed #64748b;border-left:none;padding:0;">' . $versoInner . '</td>
               </tr></table>
               <p style="text-align:center;font-size:8pt;color:#64748b;margin-top:6mm;">Modo Impressão Caseira Dobrável (CR80: 54 × 86 mm). Recorte a borda externa, dobre ao meio na linha central e plastifique ou cole.</p>';
        $htmlPdf = '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><style>
            @page { size: A4 portrait; margin: 12mm; }
            body { margin: 0; font-family: "DejaVu Sans", sans-serif; color: #1f2937; }
            .hdr { border-bottom: 0.4mm solid #cbd5e1; padding-bottom: 3mm; font-size: 9pt; color: #475569; }
        </style></head><body>
            <div class="hdr"><strong>Sistema Elo</strong> · Crachá de Identificação Funcional · ' . appEscape((string) $colaborador['nomeCompleto']) . ' (' . appEscape($matricula) . ')</div>
            ' . $cardsTable . '
        </body></html>';
        $dompdf->setPaper('A4', 'portrait');
        $downloadFilename = 'cracha-a4-' . strtolower($matricula) . '.pdf';
    }

    appRegistrarAuditoria(
        'geral',
        'exportar',
        'Crachá em PDF (' . ($pdfTipo === 'grafica' ? 'Gráfica CR80' : 'Folha A4') . ') gerado para ' . $colaborador['nomeCompleto'] . '.',
        'tbColaborador',
        (int) $colaborador['idColaborador'],
        ['formato' => $pdfTipo, 'modo' => $modo],
        $usuarioAtual
    );

    $dompdf->loadHtml($htmlPdf, 'UTF-8');
    $dompdf->render();
    $dompdf->stream($downloadFilename, ['Attachment' => true]);
    exit;
}

$queryBasePdf = [
    'id' => (int) $colaborador['idColaborador'],
    'modo' => $modo,
    'cpf' => $mostrarCpf ? '1' : '0',
    'reg' => $mostrarRegistro ? '1' : '0',
    'furo' => $mostrarFuro ? '1' : '0',
    'cor' => $temaCor,
    'formato' => 'pdf',
];
$urlPdfGrafica = appTabUrl($urlAtual . '?' . http_build_query($queryBasePdf + ['pdfTipo' => 'grafica']));
$urlPdfA4 = appTabUrl($urlAtual . '?' . http_build_query($queryBasePdf + ['pdfTipo' => 'a4']));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crachá · <?php echo appEscape((string) $colaborador['nomeCompleto']); ?></title>
    <link rel="icon" type="image/png" href="<?php echo appEscape($baseUrl . '/assets/imgs/logo-elo.png'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/tailwind-config.js'); ?>"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
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
        .badge-cut-border {
            border: 1px dashed #64748b;
        }
        .badge-fold-divider {
            border-right: 1.5px dotted #0f172a;
        }
        .badge-bleed-frame {
            padding: 3mm;
            position: relative;
            display: inline-block;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .crop-mark {
            position: absolute;
            background: #0f172a;
        }
        @page {
            size: A4 portrait;
            margin: 12mm;
        }
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
            .no-print {
                display: none !important;
            }
            .print-stage {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                background: #ffffff !important;
            }
            .badge-shadow {
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800">
    <header class="no-print border-b border-slate-200 bg-white px-4 py-3 shadow-sm">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="<?php echo appEscape(appTabUrl($urlVoltar)); ?>"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <i class="ph ph-arrow-left" aria-hidden="true"></i> Voltar
                </a>
                <h1 class="text-lg font-bold text-slate-800">Crachá de Identificação Funcional</h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="window.print()"
                        class="inline-flex items-center gap-2 rounded-lg bg-elo-teal px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-elo-green">
                    <i class="ph ph-printer text-lg" aria-hidden="true"></i> Imprimir agora
                </button>
                <a href="<?php echo appEscape($urlPdfA4); ?>"
                   class="inline-flex items-center gap-2 rounded-lg border border-elo-teal/40 bg-elo-teal/5 px-3.5 py-2 text-sm font-medium text-elo-teal hover:bg-elo-teal/10">
                    <i class="ph ph-file-pdf text-lg" aria-hidden="true"></i> PDF Folha A4
                </a>
                <a href="<?php echo appEscape($urlPdfGrafica); ?>"
                   class="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-800 px-3.5 py-2 text-sm font-medium text-white hover:bg-slate-900">
                    <i class="ph ph-export text-lg" aria-hidden="true"></i> PDF para Gráfica (CR80 + sangria)
                </a>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl p-4 md:p-6">
        <form method="get" class="no-print mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <input type="hidden" name="tabToken" value="<?php echo appEscape((string) appCurrentTabToken()); ?>">
            <?php if ($origem === 'escala'): ?>
                <input type="hidden" name="origem" value="escala">
            <?php endif; ?>
            <div class="grid gap-4 md:grid-cols-4">
                <label class="text-sm font-medium text-slate-700 md:col-span-2">Colaborador / Voluntário
                    <select name="id" onchange="this.form.submit()" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <?php foreach ($todosColaboradores as $c): ?>
                            <option value="<?php echo (int) $c['idColaborador']; ?>" <?php echo (int) $c['idColaborador'] === (int) $colaborador['idColaborador'] ? 'selected' : ''; ?>>
                                <?php echo appEscape((int) $c['idColaborador'] . ' — ' . $c['nomeCompleto'] . ' (' . $c['tipoVinculo'] . ')' . ((int) $c['ativo'] !== 1 ? ' [Inativo]' : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">Modo de impressão
                    <select name="modo" onchange="this.form.submit()" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="caseiro_dobravel" <?php echo $modo === 'caseiro_dobravel' ? 'selected' : ''; ?>>Caseira A4 — Frente e Verso dobrável</option>
                        <option value="caseiro_adesivo" <?php echo $modo === 'caseiro_adesivo' ? 'selected' : ''; ?>>Papel Adesivo A4 — 2x Somente Frente</option>
                        <option value="grafica_pvc" <?php echo $modo === 'grafica_pvc' ? 'selected' : ''; ?>>Gráfica / PVC — Frente e Verso com sangria</option>
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">Cor da faixa
                    <select name="cor" onchange="this.form.submit()" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="auto" <?php echo $temaCor === 'auto' ? 'selected' : ''; ?>>Automática por vínculo</option>
                        <option value="teal" <?php echo $temaCor === 'teal' ? 'selected' : ''; ?>>Verde-petróleo (Elo)</option>
                        <option value="navy" <?php echo $temaCor === 'navy' ? 'selected' : ''; ?>>Azul marinho</option>
                        <option value="emerald" <?php echo $temaCor === 'emerald' ? 'selected' : ''; ?>>Verde esmeralda</option>
                        <option value="purple" <?php echo $temaCor === 'purple' ? 'selected' : ''; ?>>Roxo (Voluntariado)</option>
                        <option value="amber" <?php echo $temaCor === 'amber' ? 'selected' : ''; ?>>Âmbar (Terceirizado)</option>
                        <option value="slate" <?php echo $temaCor === 'slate' ? 'selected' : ''; ?>>Grafite sóbrio</option>
                    </select>
                </label>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-5 border-t border-slate-100 pt-3 text-sm text-slate-700">
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="cpf" value="0">
                    <input type="checkbox" name="cpf" value="1" <?php echo $mostrarCpf ? 'checked' : ''; ?> onchange="this.form.submit()" class="rounded border-slate-300 text-elo-teal focus:ring-elo-teal">
                    Exibir CPF no verso
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="reg" value="0">
                    <input type="checkbox" name="reg" value="1" <?php echo $mostrarRegistro ? 'checked' : ''; ?> onchange="this.form.submit()" class="rounded border-slate-300 text-elo-teal focus:ring-elo-teal">
                    Exibir registro profissional
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="furo" value="0">
                    <input type="checkbox" name="furo" value="1" <?php echo $mostrarFuro ? 'checked' : ''; ?> onchange="this.form.submit()" class="rounded border-slate-300 text-elo-teal focus:ring-elo-teal">
                    Mostrar guia de furo (cordão / jacaré)
                </label>
                <?php if ($fotoUrlWeb === null): ?>
                    <a href="<?php echo appEscape(appTabUrl($urlColaboradores . '?editar=' . (int) $colaborador['idColaborador'] . '#manutencao')); ?>"
                       class="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800 hover:bg-amber-100">
                        <i class="ph ph-camera text-base" aria-hidden="true"></i> Este colaborador ainda está sem foto — adicionar foto
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <section class="print-stage rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:p-10">
            <div class="no-print mb-6 flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3 text-xs text-slate-500">
                <span><strong>Tamanho real CR80:</strong> 54 mm × 86 mm (padrão internacional de crachá PVC)</span>
                <span>Ao imprimir no navegador, marque <strong>"Escala: 100%"</strong> e <strong>"Gráficos de plano de fundo"</strong>.</span>
            </div>

            <?php
            $renderFrente = static function () use ($colaborador, $paleta, $nomeInstituicao, $fotoUrlWeb, $iniciais, $mostrarRegistro, $mostrarFuro, $matricula): string {
                $pPrimary = $paleta['primary'];
                $pDark = $paleta['dark'];
                $pSoft = $paleta['soft'];
                $pText = $paleta['text'];
                $fotoHtml = $fotoUrlWeb !== null
                    ? '<img src="' . appEscape($fotoUrlWeb) . '" alt="Foto de ' . appEscape((string) $colaborador['nomeCompleto']) . '" class="mx-auto h-[31mm] w-[26mm] rounded-[3mm] border-[0.7mm] border-white object-cover shadow-sm">'
                    : '<div class="mx-auto flex h-[31mm] w-[26mm] items-center justify-center rounded-[3mm] border-[0.7mm] border-white bg-slate-200 text-2xl font-bold shadow-sm" style="color:' . $pDark . '">' . appEscape($iniciais) . '</div>';
                $furoHtml = $mostrarFuro
                    ? '<div class="mx-auto mb-[2.2mm] h-[2.4mm] w-[12mm] rounded-full border border-dashed border-white/80"></div>'
                    : '<div class="h-[2.5mm]"></div>';
                $regHtml = ($mostrarRegistro && !empty($colaborador['registroProfissional']))
                    ? '<p class="mt-[0.5mm] text-[6.3pt] text-slate-500" data-preserve-case>' . appEscape((string) $colaborador['registroProfissional']) . '</p>'
                    : '';

                return '<div class="badge-card badge-shadow flex flex-col">'
                    . '<div style="background:' . $pPrimary . ';" class="px-[3.5mm] pb-[8.5mm] pt-[3mm] text-center text-white">'
                    . $furoHtml
                    . '<div class="text-[7.5pt] font-bold uppercase leading-tight tracking-wide" data-preserve-case>' . appEscape($nomeInstituicao) . '</div>'
                    . '<div class="mt-[0.6mm] text-[5.6pt] uppercase tracking-[0.8pt] opacity-90">Identificação Funcional</div>'
                    . '</div>'
                    . '<div class="-mt-[6.5mm] text-center">' . $fotoHtml . '</div>'
                    . '<div class="flex-1 px-[3.5mm] pt-[2.2mm] text-center">'
                    . '<h2 class="text-[9.2pt] font-bold leading-tight text-slate-900" data-preserve-case>' . appEscape((string) $colaborador['nomeCompleto']) . '</h2>'
                    . '<p class="mt-[0.8mm] text-[7pt] font-medium text-slate-600" data-preserve-case>' . appEscape((string) $colaborador['cargoFuncao']) . '</p>'
                    . $regHtml
                    . '<div class="mt-[2.2mm]">'
                    . '<span style="background:' . $pSoft . ';color:' . $pText . ';" class="inline-block rounded-full px-[3mm] py-[0.8mm] text-[6.2pt] font-bold uppercase tracking-wider">' . appEscape((string) $colaborador['tipoVinculo']) . '</span>'
                    . '</div>'
                    . '</div>'
                    . '<div class="border-t border-slate-200 bg-slate-50 px-[3.5mm] pb-[2mm] pt-[1.8mm]">'
                    . '<div class="flex items-center justify-between text-[6pt] text-slate-500">'
                    . '<strong class="text-slate-900">' . appEscape($matricula) . '</strong>'
                    . '<span>Turno: <strong class="text-slate-900">' . appEscape((string) $colaborador['turno']) . '</strong></span>'
                    . '</div>'
                    . '<div style="background:' . $pPrimary . ';" class="mt-[1.4mm] h-[1.4mm] rounded-full"></div>'
                    . '</div>'
                    . '</div>';
            };

            $renderVerso = static function () use ($colaborador, $paleta, $razaoInstituicao, $instituicao, $enderecoInstituicao, $formatarCpf, $formatarCnpj, $formatarTelefone, $mostrarCpf, $mostrarRegistro, $mostrarFuro, $matricula, $dataInicioFmt, $emissaoFmt): string {
                $pDark = $paleta['dark'];
                $furoHtml = $mostrarFuro
                    ? '<div class="mx-auto mb-[2mm] h-[2.4mm] w-[12mm] rounded-full border border-dashed border-white/80"></div>'
                    : '<div class="h-[2mm]"></div>';
                $cpfLinha = $mostrarCpf
                    ? '<div class="flex justify-between py-[0.5mm]"><span class="text-slate-500">CPF:</span><strong class="text-slate-900">' . appEscape($formatarCpf((string) $colaborador['cpf'])) . '</strong></div>'
                    : '';
                $regLinha = ($mostrarRegistro && !empty($colaborador['registroProfissional']))
                    ? '<div class="flex justify-between py-[0.5mm]"><span class="text-slate-500">Registro:</span><strong class="text-slate-900" data-preserve-case>' . appEscape((string) $colaborador['registroProfissional']) . '</strong></div>'
                    : '';

                return '<div class="badge-card badge-shadow flex flex-col">'
                    . '<div style="background:' . $pDark . ';" class="px-[3.5mm] py-[2.8mm] text-center text-white">'
                    . $furoHtml
                    . '<div class="text-[6.6pt] font-bold uppercase tracking-wider">Dados Institucionais</div>'
                    . '</div>'
                    . '<div class="flex-1 space-y-[2.2mm] px-[3.8mm] py-[3mm] text-[6.2pt] text-slate-700">'
                    . '<div class="divide-y divide-slate-100">'
                    . '<div class="flex justify-between py-[0.5mm]"><span class="text-slate-500">Matrícula:</span><strong class="text-slate-900">' . appEscape($matricula) . '</strong></div>'
                    . '<div class="flex justify-between py-[0.5mm]"><span class="text-slate-500">Vínculo:</span><strong class="text-slate-900">' . appEscape((string) $colaborador['tipoVinculo']) . '</strong></div>'
                    . $cpfLinha
                    . $regLinha
                    . '<div class="flex justify-between py-[0.5mm]"><span class="text-slate-500">Início:</span><strong class="text-slate-900">' . appEscape($dataInicioFmt) . '</strong></div>'
                    . '</div>'
                    . '<div class="border-t border-slate-200 pt-[2mm]">'
                    . '<div class="text-[6.5pt] font-bold text-slate-900" data-preserve-case>' . appEscape($razaoInstituicao) . '</div>'
                    . (!empty($instituicao['cnpj']) ? '<div class="mt-[0.4mm] text-[5.8pt] text-slate-600">CNPJ: ' . appEscape($formatarCnpj((string) $instituicao['cnpj'])) . '</div>' : '')
                    . ($enderecoInstituicao !== '' ? '<div class="mt-[0.4mm] text-[5.6pt] leading-snug text-slate-600" data-preserve-case>' . appEscape($enderecoInstituicao) . '</div>' : '')
                    . (!empty($instituicao['telefone']) ? '<div class="mt-[0.4mm] text-[5.8pt] text-slate-600">Tel.: ' . appEscape($formatarTelefone((string) $instituicao['telefone'])) . '</div>' : '')
                    . '</div>'
                    . '<div class="rounded border border-slate-200 bg-slate-50 p-[2mm] text-justify text-[5.4pt] leading-snug text-slate-600">'
                    . 'Este crachá é de uso pessoal e intransferível. Em caso de perda, extravio ou desligamento, favor devolver imediatamente à administração da instituição.'
                    . '</div>'
                    . '</div>'
                    . '<div class="px-[3.8mm] pb-[2.8mm] text-center">'
                    . '<div class="mx-auto mb-[1mm] w-4/5 border-t border-slate-500"></div>'
                    . '<div class="text-[5.6pt] font-bold text-slate-700">Coordenação / Administração</div>'
                    . '<div class="mt-[0.4mm] text-[5pt] text-slate-400">Emitido em ' . appEscape($emissaoFmt) . ' · Sistema Elo</div>'
                    . '</div>'
                    . '</div>';
            };
            ?>

            <?php if ($modo === 'caseiro_dobravel'): ?>
                <div class="flex flex-col items-center">
                    <div class="inline-flex items-stretch badge-cut-border shadow-md">
                        <div class="badge-fold-divider"><?php echo $renderFrente(); ?></div>
                        <div><?php echo $renderVerso(); ?></div>
                    </div>
                    <p class="mt-4 flex items-center gap-2 text-center text-xs text-slate-500">
                        <i class="ph ph-scissors text-base" aria-hidden="true"></i>
                        Recorte na linha tracejada externa, dobre ao meio na linha pontilhada central, cole/plastifique e faça o furo no guia superior para cordão ou jacaré.
                    </p>
                </div>
            <?php elseif ($modo === 'caseiro_adesivo'): ?>
                <div class="flex flex-col items-center">
                    <div class="flex flex-wrap items-center justify-center gap-6">
                        <div class="badge-cut-border shadow-md"><?php echo $renderFrente(); ?></div>
                        <div class="badge-cut-border shadow-md"><?php echo $renderFrente(); ?></div>
                    </div>
                    <p class="mt-4 flex items-center gap-2 text-center text-xs text-slate-500">
                        <i class="ph ph-scissors text-base" aria-hidden="true"></i>
                        Modo Papel Adesivo: 2 frentes em tamanho real CR80 (54 × 86 mm) prontas para recortar e colar sobre cartão PVC ou crachá rígido.
                    </p>
                </div>
            <?php else: ?>
                <div class="flex flex-col items-center">
                    <div class="flex flex-wrap items-center justify-center gap-10">
                        <div class="text-center">
                            <p class="no-print mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Frente (CR80 + 3mm sangria)</p>
                            <div class="badge-bleed-frame shadow-md" style="background:<?php echo appEscape($paleta['primary']); ?>;">
                                <?php echo $renderFrente(); ?>
                                <span class="crop-mark" style="top:0;left:3mm;width:0.3mm;height:2.2mm;"></span>
                                <span class="crop-mark" style="top:3mm;left:0;width:2.2mm;height:0.3mm;"></span>
                                <span class="crop-mark" style="top:0;right:3mm;width:0.3mm;height:2.2mm;"></span>
                                <span class="crop-mark" style="top:3mm;right:0;width:2.2mm;height:0.3mm;"></span>
                                <span class="crop-mark" style="bottom:0;left:3mm;width:0.3mm;height:2.2mm;"></span>
                                <span class="crop-mark" style="bottom:3mm;left:0;width:2.2mm;height:0.3mm;"></span>
                                <span class="crop-mark" style="bottom:0;right:3mm;width:0.3mm;height:2.2mm;"></span>
                                <span class="crop-mark" style="bottom:3mm;right:0;width:2.2mm;height:0.3mm;"></span>
                            </div>
                        </div>
                        <div class="text-center">
                            <p class="no-print mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Verso (CR80 + 3mm sangria)</p>
                            <div class="badge-bleed-frame border border-slate-200 bg-white shadow-md">
                                <?php echo $renderVerso(); ?>
                                <span class="crop-mark" style="top:0;left:3mm;width:0.3mm;height:2.2mm;"></span>
                                <span class="crop-mark" style="top:3mm;left:0;width:2.2mm;height:0.3mm;"></span>
                                <span class="crop-mark" style="top:0;right:3mm;width:0.3mm;height:2.2mm;"></span>
                                <span class="crop-mark" style="top:3mm;right:0;width:2.2mm;height:0.3mm;"></span>
                                <span class="crop-mark" style="bottom:0;left:3mm;width:0.3mm;height:2.2mm;"></span>
                                <span class="crop-mark" style="bottom:3mm;left:0;width:2.2mm;height:0.3mm;"></span>
                                <span class="crop-mark" style="bottom:0;right:3mm;width:0.3mm;height:2.2mm;"></span>
                                <span class="crop-mark" style="bottom:3mm;right:0;width:2.2mm;height:0.3mm;"></span>
                            </div>
                        </div>
                    </div>
                    <p class="no-print mt-5 text-center text-xs text-slate-500">
                        Para enviar à gráfica, clique em <strong>"PDF para Gráfica (CR80 + sangria)"</strong> no topo — o arquivo sai em 2 páginas (frente e verso) com 60 × 92 mm e marcas de corte profissionais.
                    </p>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
</body>
</html>
