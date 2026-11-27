<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('geral.relatorios/matriz-permissoes');
$pdo = appPdo();
$baseUrl = appBaseUrl();
$pageUrl = $baseUrl . '/pages/geral/relatorios/matriz-permissoes.php';
$catalogo = appPageCatalog();

$categoryNames = [
    'dashboards' => 'Dashboards',
    'cadastros' => 'Cadastros',
    'movimentacoes' => 'Movimentações',
    'relatorios' => 'Relatórios',
    'configuracoes' => 'Configurações',
];

$allModules = [];
foreach ($catalogo as $moduleKey => $modulo) {
    $allModules[$moduleKey] = $modulo['nome'];
}
$allModules['instituicao'] = 'Instituição';

$profilesStatement = $pdo->query(
    'SELECT p.idPerfil, p.nomePerfil, p.permissoes, p.administradorGlobal,
            COUNT(u.idUsuario) AS totalUsuarios,
            SUM(CASE WHEN u.ativo = 1 AND u.bloqueado = 0 THEN 1 ELSE 0 END) AS usuariosAtivos
     FROM tbPerfilAcesso p
     LEFT JOIN tbUsuario u ON u.idPerfil = p.idPerfil
     GROUP BY p.idPerfil, p.nomePerfil, p.permissoes, p.administradorGlobal
     ORDER BY p.administradorGlobal DESC, p.nomePerfil'
);
$allProfiles = [];
foreach ($profilesStatement->fetchAll() as $row) {
    $decodedPermissions = json_decode((string) $row['permissoes'], true);
    $row['permissoesDecoded'] = is_array($decodedPermissions) ? $decodedPermissions : [];
    $row['totalUsuarios'] = (int) $row['totalUsuarios'];
    $row['usuariosAtivos'] = (int) ($row['usuariosAtivos'] ?? 0);
    $allProfiles[(int) $row['idPerfil']] = $row;
}

$profileHasAccess = static function (array $profile, string $moduleKey, string $pageKey): bool {
    $permissions = $profile['permissoesDecoded'];
    $isGlobal = (int) $profile['administradorGlobal'] === 1;

    if ($moduleKey === 'instituicao' && $pageKey === 'informacoes') {
        return $isGlobal
            || in_array('*', $permissions['*'] ?? [], true)
            || in_array('informacoes', $permissions['instituicao'] ?? [], true);
    }

    if ($isGlobal && $moduleKey === 'geral' && $pageKey === 'perfis-acesso') {
        return true;
    }

    return in_array('*', $permissions['*'] ?? [], true)
        || in_array('*', $permissions[$moduleKey] ?? [], true)
        || in_array($pageKey, $permissions[$moduleKey] ?? [], true);
};

$actionInput = $_POST['reportAction'] ?? '';
$action = is_scalar($actionInput) ? (string) $actionInput : '';
$reportGenerated = $_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['', 'generate', 'download'], true);
$restoreConfiguration = $_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'configure';

$viewMode = 'matriz';
$selectedProfileIds = [];
$selectedModules = [];
$selectedCategories = [];
$showUserCount = true;
$onlyGranted = false;
$paper = 'A4';
$orientation = 'landscape';
$paperWidth = 210;
$paperHeight = 297;
$marginTop = 7;
$marginBottom = 15;
$marginLeft = 7;
$marginRight = 7;
$zebra = true;
$zebraColor = '#f2f2f2';
$errors = [];
$toastError = '';

if ($reportGenerated || $restoreConfiguration) {
    appValidateCsrf();

    $requestedMode = is_scalar($_POST['viewMode'] ?? null) ? (string) $_POST['viewMode'] : 'matriz';
    $viewMode = in_array($requestedMode, ['matriz', 'detalhado'], true) ? $requestedMode : 'matriz';

    $rawProfiles = $_POST['profiles'] ?? [];
    if (is_array($rawProfiles)) {
        foreach ($rawProfiles as $id) {
            $parsedId = is_scalar($id) ? filter_var($id, FILTER_VALIDATE_INT) : false;
            if ($parsedId !== false && isset($allProfiles[(int) $parsedId]) && !in_array((int) $parsedId, $selectedProfileIds, true)) {
                $selectedProfileIds[] = (int) $parsedId;
            }
        }
    }

    $rawModules = $_POST['modules'] ?? [];
    if (is_array($rawModules)) {
        foreach ($rawModules as $mod) {
            if (is_scalar($mod) && isset($allModules[(string) $mod]) && !in_array((string) $mod, $selectedModules, true)) {
                $selectedModules[] = (string) $mod;
            }
        }
    }

    $rawCategories = $_POST['categories'] ?? [];
    if (is_array($rawCategories)) {
        foreach ($rawCategories as $cat) {
            if (is_scalar($cat) && isset($categoryNames[(string) $cat]) && !in_array((string) $cat, $selectedCategories, true)) {
                $selectedCategories[] = (string) $cat;
            }
        }
    }

    $showUserCount = isset($_POST['showUserCount']);
    $onlyGranted = isset($_POST['onlyGranted']);

    $paperInput = is_scalar($_POST['paper'] ?? null) ? (string) $_POST['paper'] : 'A4';
    $paper = in_array($paperInput, ['A4', 'A3', 'Letter', 'custom'], true) ? $paperInput : 'A4';

    $orientationInput = is_scalar($_POST['orientation'] ?? null) ? (string) $_POST['orientation'] : 'landscape';
    $orientation = in_array($orientationInput, ['portrait', 'landscape'], true) ? $orientationInput : 'landscape';

    $paperWidth = filter_var($_POST['paperWidth'] ?? 210, FILTER_VALIDATE_INT);
    $paperHeight = filter_var($_POST['paperHeight'] ?? 297, FILTER_VALIDATE_INT);
    $paperWidth = ($paperWidth !== false && $paperWidth >= 50 && $paperWidth <= 1000) ? (int) $paperWidth : 210;
    $paperHeight = ($paperHeight !== false && $paperHeight >= 50 && $paperHeight <= 1000) ? (int) $paperHeight : 297;

    foreach (['marginTop' => 7, 'marginBottom' => 15, 'marginLeft' => 7, 'marginRight' => 7] as $marginKey => $defaultMargin) {
        $parsedMargin = filter_var($_POST[$marginKey] ?? $defaultMargin, FILTER_VALIDATE_INT);
        $$marginKey = ($parsedMargin !== false && $parsedMargin >= 0 && $parsedMargin <= 100) ? (int) $parsedMargin : $defaultMargin;
    }

    $zebra = isset($_POST['zebra']);
    $colorInput = is_scalar($_POST['zebraColor'] ?? null) ? trim((string) $_POST['zebraColor']) : '#f2f2f2';
    $zebraColor = preg_match('/^#[0-9a-fA-F]{6}$/', $colorInput) === 1 ? $colorInput : '#f2f2f2';
}

$activeProfiles = $selectedProfileIds
    ? array_values(array_intersect_key($allProfiles, array_flip($selectedProfileIds)))
    : array_values($allProfiles);

$activeModules = $selectedModules ?: array_keys($allModules);
$activeCategories = $selectedCategories ?: array_keys($categoryNames);

$permissionRows = [];
foreach ($activeModules as $moduleKey) {
    if ($moduleKey === 'instituicao') {
        if (!in_array('configuracoes', $activeCategories, true)) {
            continue;
        }
        $grants = [];
        $hasAnyGrant = false;
        foreach ($activeProfiles as $profile) {
            $allowed = $profileHasAccess($profile, 'instituicao', 'informacoes');
            $grants[(int) $profile['idPerfil']] = $allowed;
            if ($allowed) {
                $hasAnyGrant = true;
            }
        }
        if ($onlyGranted && !$hasAnyGrant) {
            continue;
        }
        $permissionRows[] = [
            'moduleKey' => 'instituicao',
            'moduleName' => 'Instituição',
            'categoryKey' => 'configuracoes',
            'categoryName' => $categoryNames['configuracoes'],
            'pageKey' => 'informacoes',
            'pageName' => 'Informações da Instituição',
            'grants' => $grants,
        ];
        continue;
    }

    $modulo = $catalogo[$moduleKey] ?? null;
    if (!$modulo) {
        continue;
    }

    $pagesByCategory = [
        'dashboards' => [],
        'cadastros' => [],
        'movimentacoes' => [],
        'relatorios' => [],
    ];
    foreach ($modulo['paginas'] as $pageKey => $page) {
        $catKey = appPageCategory($page['arquivo']);
        $pagesByCategory[$catKey][$pageKey] = $page;
    }

    foreach ($pagesByCategory as $catKey => $pages) {
        if (!in_array($catKey, $activeCategories, true)) {
            continue;
        }
        foreach ($pages as $pageKey => $page) {
            $grants = [];
            $hasAnyGrant = false;
            foreach ($activeProfiles as $profile) {
                $allowed = $profileHasAccess($profile, $moduleKey, $pageKey);
                $grants[(int) $profile['idPerfil']] = $allowed;
                if ($allowed) {
                    $hasAnyGrant = true;
                }
            }
            if ($onlyGranted && !$hasAnyGrant) {
                continue;
            }
            $permissionRows[] = [
                'moduleKey' => $moduleKey,
                'moduleName' => $modulo['nome'],
                'categoryKey' => $catKey,
                'categoryName' => $categoryNames[$catKey] ?? ucfirst($catKey),
                'pageKey' => $pageKey,
                'pageName' => $page['nome'],
                'grants' => $grants,
            ];
        }
    }
}

if ($reportGenerated && !$activeProfiles) {
    $errors[] = 'Nenhum perfil de acesso cadastrado para gerar o relatório.';
}
if ($errors) {
    $toastError = implode(' ', $errors);
}

$renderHiddenInputs = static function (array $values, string $prefix = '') use (&$renderHiddenInputs): void {
    foreach ($values as $key => $value) {
        if ($prefix === '' && $key === 'reportAction') {
            continue;
        }
        $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';
        if (is_array($value)) {
            $renderHiddenInputs($value, $name);
        } elseif (is_scalar($value)) {
            echo '<input type="hidden" name="' . appEscape($name) . '" value="' . appEscape((string) $value) . '">' . PHP_EOL;
        }
    }
};

$renderReportBody = static function () use (
    $viewMode,
    $activeProfiles,
    $permissionRows,
    $showUserCount,
    $zebra
): void {
    if (!$permissionRows) {
        echo '<p class="report-empty">Nenhuma tela corresponde aos filtros selecionados.</p>';
        return;
    }

    if ($viewMode === 'detalhado') {
        $totalTelas = count($permissionRows);
        foreach ($activeProfiles as $index => $profile) {
            $idPerfil = (int) $profile['idPerfil'];
            $telasPorModulo = [];
            $qtdLiberadas = 0;
            foreach ($permissionRows as $row) {
                if (!empty($row['grants'][$idPerfil])) {
                    $telasPorModulo[$row['moduleName']][] = $row;
                    $qtdLiberadas++;
                }
            }

            $tituloPerfil = ($index + 1) . ' - ' . $profile['nomePerfil'];
            if ((int) $profile['administradorGlobal'] === 1) {
                $tituloPerfil .= ' [Administração Global]';
            }
            $subtitulo = $qtdLiberadas . ' de ' . $totalTelas . ' tela(s) liberada(s)';
            if ($showUserCount) {
                $subtitulo .= ' · ' . $profile['totalUsuarios'] . ' usuário(s) (' . $profile['usuariosAtivos'] . ' ativo(s))';
            }

            echo '<section class="matrix-profile-block">';
            echo '<div class="matrix-profile-header"><strong>' . appEscape($tituloPerfil) . '</strong><span>' . appEscape($subtitulo) . '</span></div>';
            if (!$telasPorModulo) {
                echo '<p class="matrix-profile-empty">Nenhuma tela liberada para este perfil no escopo filtrado.</p>';
            } else {
                echo '<table class="report-table"><thead><tr><th style="width:26%">Módulo</th><th style="width:22%">Categoria</th><th>Tela Liberada</th></tr></thead><tbody>';
                $rowIndex = 0;
                foreach ($telasPorModulo as $moduleName => $rows) {
                    foreach ($rows as $row) {
                        $zebraClass = $zebra && $rowIndex % 2 === 1 ? ' report-zebra-row' : '';
                        echo '<tr class="report-data-row' . $zebraClass . '">';
                        echo '<td>' . appEscape($moduleName) . '</td>';
                        echo '<td>' . appEscape($row['categoryName']) . '</td>';
                        echo '<td>' . appEscape($row['pageName']) . '</td>';
                        echo '</tr>';
                        $rowIndex++;
                    }
                }
                echo '</tbody></table>';
            }
            echo '</section>';
        }
        return;
    }

    $rowsByModule = [];
    foreach ($permissionRows as $row) {
        $rowsByModule[$row['moduleName']][] = $row;
    }

    echo '<table class="report-table matrix-table"><thead><tr>';
    echo '<th style="width:14%">Categoria</th>';
    echo '<th style="width:24%">Tela</th>';
    foreach ($activeProfiles as $profile) {
        echo '<th class="matrix-center">';
        echo appEscape((string) $profile['nomePerfil']);
        if ((int) $profile['administradorGlobal'] === 1) {
            echo '<div class="matrix-sub">Global</div>';
        }
        if ($showUserCount) {
            echo '<div class="matrix-sub">' . (int) $profile['totalUsuarios'] . ' usu.</div>';
        }
        echo '</th>';
    }
    echo '</tr></thead><tbody>';

    $colSpan = 2 + count($activeProfiles);
    $moduleCounter = 0;
    $rowIndex = 0;
    foreach ($rowsByModule as $moduleName => $rows) {
        $moduleCounter++;
        echo '<tr class="report-group-row"><th colspan="' . $colSpan . '">';
        echo appEscape($moduleCounter . ' - ' . $moduleName . ' (' . count($rows) . ' tela' . (count($rows) === 1 ? '' : 's') . ')');
        echo '</th></tr>';
        foreach ($rows as $row) {
            $zebraClass = $zebra && $rowIndex % 2 === 1 ? ' report-zebra-row' : '';
            echo '<tr class="report-data-row' . $zebraClass . '">';
            echo '<td>' . appEscape($row['categoryName']) . '</td>';
            echo '<td>' . appEscape($row['pageName']) . '</td>';
            foreach ($activeProfiles as $profile) {
                $allowed = !empty($row['grants'][(int) $profile['idPerfil']]);
                if ($allowed) {
                    echo '<td class="matrix-center matrix-yes">SIM</td>';
                } else {
                    echo '<td class="matrix-center matrix-no">—</td>';
                }
            }
            echo '</tr>';
            $rowIndex++;
        }
    }

    echo '<tr class="matrix-summary-row"><th colspan="2">Total de telas liberadas</th>';
    foreach ($activeProfiles as $profile) {
        $idPerfil = (int) $profile['idPerfil'];
        $count = 0;
        foreach ($permissionRows as $row) {
            if (!empty($row['grants'][$idPerfil])) {
                $count++;
            }
        }
        echo '<th class="matrix-center">' . $count . ' / ' . count($permissionRows) . '</th>';
    }
    echo '</tr></tbody></table>';
};

$paperDimensions = match ($paper) {
    'A3' => ['width' => 297, 'height' => 420],
    'Letter' => ['width' => 216, 'height' => 279],
    default => ['width' => 210, 'height' => 297],
};
if ($paper !== 'custom') {
    $paperWidth = $paperDimensions['width'];
    $paperHeight = $paperDimensions['height'];
}
$pageWidth = $orientation === 'landscape' ? $paperHeight : $paperWidth;
$pageHeight = $orientation === 'landscape' ? $paperWidth : $paperHeight;

if ($reportGenerated && !$errors && $action === 'download') {
    require_once __DIR__ . '/../../../vendor/autoload.php';
    $pdfOptions = new \Dompdf\Options();
    $pdfOptions->set('defaultFont', 'DejaVu Sans');
    $pdfOptions->set('isRemoteEnabled', false);
    $dompdf = new \Dompdf\Dompdf($pdfOptions);

    $subtitle = 'Gerado em ' . date('d/m/Y H:i') . ' · ' . count($activeProfiles) . ' perfil(is) · ' . count($permissionRows) . ' tela(s)';
    $reportContent = '<header class="report-heading"><h1>Matriz de Permissões por Perfil</h1><p>' . appEscape($subtitle) . '</p></header>';
    ob_start();
    $renderReportBody();
    $reportContent .= (string) ob_get_clean();

    $pdfHtml = '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><style>'
        . '@page{size:' . (float) $pageWidth . 'mm ' . (float) $pageHeight . 'mm;margin:'
        . (float) $marginTop . 'mm ' . (float) $marginRight . 'mm ' . (float) $marginBottom . 'mm ' . (float) $marginLeft . 'mm;}'
        . 'body{font-family:"DejaVu Sans",sans-serif;font-size:8.5pt;color:#111;margin:0;}'
        . '.report-heading{margin-bottom:5mm;padding-bottom:3mm;border-bottom:2px solid #1f2937;}'
        . '.report-heading:before{display:block;margin-bottom:1.5mm;color:#4b5563;content:"SISTEMA ELO · RELATÓRIO";font-size:7pt;font-weight:bold;letter-spacing:1.4pt;}'
        . '.report-heading h1{margin:0 0 1mm;font-size:14pt;font-weight:bold;letter-spacing:.4pt;text-transform:uppercase;}'
        . '.report-heading p{margin:0;color:#374151;font-size:8pt;}'
        . '.report-table{width:100%;table-layout:fixed;border-collapse:collapse;text-align:left;font-size:7.5pt;margin-bottom:4mm;}'
        . '.report-table th,.report-table td{border:1px solid #9ca3af;padding:1.3mm;vertical-align:middle;overflow-wrap:anywhere;}'
        . '.report-table thead th{background:#1f2937;color:#fff;font-weight:bold;}'
        . '.report-table .report-group-row th{background:#e8f4f5;color:#075b63;text-align:left;font-weight:bold;}'
        . '.report-data-row.report-zebra-row td{background:' . appEscape($zebraColor) . ';}'
        . '.matrix-center{text-align:center;}'
        . '.matrix-sub{font-size:6.5pt;font-weight:normal;color:#d1d5db;margin-top:0.5mm;}'
        . '.matrix-yes{color:#065f46;font-weight:bold;}'
        . '.matrix-no{color:#9ca3af;}'
        . '.matrix-summary-row th{background:#f3f4f6;color:#111827;font-weight:bold;}'
        . '.matrix-profile-block{margin-bottom:5mm;page-break-inside:avoid;}'
        . '.matrix-profile-header{background:#e8f4f5;color:#075b63;padding:2mm 2.5mm;border:1px solid #9ca3af;border-bottom:0;font-size:8.5pt;}'
        . '.matrix-profile-header span{display:block;font-size:7.5pt;color:#374151;margin-top:0.5mm;}'
        . '.matrix-profile-empty{border:1px solid #9ca3af;padding:2.5mm;margin:0;color:#6b7280;font-size:8pt;}'
        . '.report-empty{padding:5mm;color:#6b7280;text-align:center;}'
        . '</style></head><body>' . $reportContent . '</body></html>';

    $dompdf->loadHtml($pdfHtml, 'UTF-8');
    $dompdf->setPaper([
        0,
        0,
        $pageWidth * 72 / 25.4,
        $pageHeight * 72 / 25.4,
    ]);
    $dompdf->render();
    $dompdf->stream('relatorio-matriz-permissoes.pdf', ['Attachment' => true]);
    exit;
}
?>
<?php if ($reportGenerated && !$errors): ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Elo</title>
    <link rel="icon" type="image/png" href="<?php echo appEscape($baseUrl . '/assets/imgs/logo-elo.png'); ?>">
    <link rel="stylesheet" href="<?php echo appEscape($baseUrl . '/assets/css/usuarios-por-perfis.css?v=' . filemtime(__DIR__ . '/../../../assets/css/usuarios-por-perfis.css')); ?>">
    <style>
        @page {
            size: <?php echo appEscape((string) $pageWidth); ?>mm <?php echo appEscape((string) $pageHeight); ?>mm;
            margin: <?php echo appEscape((string) $marginTop); ?>mm <?php echo appEscape((string) $marginRight); ?>mm <?php echo appEscape((string) $marginBottom); ?>mm <?php echo appEscape((string) $marginLeft); ?>mm;
        }
        .report-paper {
            --zebra-color: <?php echo appEscape($zebraColor); ?>;
            --print-orientation: <?php echo appEscape($orientation); ?>;
        }
        .matrix-center { text-align: center; }
        .matrix-sub { font-size: 0.7rem; font-weight: 400; color: #d1d5db; margin-top: 2px; }
        .matrix-yes { color: #047857; font-weight: 700; }
        .matrix-no { color: #9ca3af; }
        .matrix-summary-row th { background: #f3f4f6; color: #111827; font-weight: 700; }
        .matrix-profile-block { margin-bottom: 1.25rem; }
        .matrix-profile-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
            background: #e8f4f5;
            color: #075b63;
            padding: 0.6rem 0.75rem;
            border: 1px solid #d1d5db;
            border-bottom: 0;
            font-size: 0.9rem;
        }
        .matrix-profile-header span { font-size: 0.78rem; color: #374151; }
        .matrix-profile-empty {
            margin: 0;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            color: #6b7280;
            font-size: 0.85rem;
        }
    </style>
</head>
<body class="report-stream">
    <div class="report-toolbar">
        <form method="post" action="<?php echo appEscape(appTabUrl($pageUrl)); ?>">
            <?php $renderHiddenInputs($_POST); ?>
            <input type="hidden" name="reportAction" value="configure">
            <button type="submit" class="report-back-button">Voltar</button>
        </form>
        <button type="button" id="matrix-print-button">Imprimir</button>
        <form method="post" action="<?php echo appEscape(appTabUrl($pageUrl)); ?>">
            <?php $renderHiddenInputs($_POST); ?>
            <input type="hidden" name="reportAction" value="download">
            <button type="submit">Baixar PDF</button>
        </form>
    </div>
    <main id="report-output" class="report-paper" data-zebra="<?php echo $zebra ? 'true' : 'false'; ?>">
        <header class="report-heading">
            <h1>Matriz de Permissões por Perfil</h1>
            <p>Gerado em <?php echo appEscape(date('d/m/Y H:i')); ?> · <?php echo count($activeProfiles); ?> perfil(is) · <?php echo count($permissionRows); ?> tela(s)</p>
        </header>
        <?php $renderReportBody(); ?>
    </main>
    <script>
        document.getElementById('matrix-print-button')?.addEventListener('click', () => window.print());
    </script>
</body>
</html>
<?php exit; endif; ?>
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
    <link rel="stylesheet" href="<?php echo appEscape($baseUrl . '/assets/css/usuarios-por-perfis.css?v=' . filemtime(__DIR__ . '/../../../assets/css/usuarios-por-perfis.css')); ?>">
</head>
<body class="flex h-screen flex-col overflow-hidden bg-gray-100 font-sans">
    <?php include __DIR__ . '/../../../components/geral/navbar-geral.php'; ?>
    <div class="flex min-h-0 flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../../components/geral/sidebar-geral.php'; ?>
        <main class="min-w-0 flex-1 overflow-y-auto bg-gray-50 p-4 md:p-6">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Matriz de Permissões por Perfil</h1>
                </div>
            </div>

            <form method="post" id="matrix-report-form" class="space-y-4">
                <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                <input type="hidden" name="reportAction" value="generate">
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-wrap border-b border-gray-200" role="tablist" aria-label="Configurações do relatório">
                        <?php foreach (['filtros' => 'Filtros e Escopo', 'configuracoes' => 'Configurações'] as $tabKey => $tabLabel): ?>
                            <button type="button" id="tab-<?php echo appEscape($tabKey); ?>" role="tab"
                                    aria-controls="painel-<?php echo appEscape($tabKey); ?>"
                                    data-matrix-tab="<?php echo appEscape($tabKey); ?>"
                                    aria-selected="<?php echo $tabKey === 'filtros' ? 'true' : 'false'; ?>"
                                    class="report-tab border-b-2 px-5 py-3 text-sm font-medium">
                                <?php echo appEscape($tabLabel); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <section id="painel-filtros" role="tabpanel" aria-labelledby="tab-filtros" data-matrix-panel="filtros" class="p-4 md:p-5">
                        <div class="grid gap-6 lg:grid-cols-12">
                            <div class="space-y-5 lg:col-span-5">
                                <div>
                                    <h2 class="font-semibold text-gray-800">Formato de visualização</h2>
                                    <p class="mb-2 text-xs text-gray-500">Escolha entre tabela comparativa lado a lado ou blocos detalhados por perfil.</p>
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm transition hover:border-elo-teal">
                                            <input type="radio" name="viewMode" value="matriz" <?php echo $viewMode === 'matriz' ? 'checked' : ''; ?> class="mt-0.5 text-elo-teal focus:ring-elo-teal">
                                            <span>
                                                <strong class="block text-gray-800">Matriz comparativa</strong>
                                                <span class="text-xs text-gray-500">Telas nas linhas × Perfis nas colunas</span>
                                            </span>
                                        </label>
                                        <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm transition hover:border-elo-teal">
                                            <input type="radio" name="viewMode" value="detalhado" <?php echo $viewMode === 'detalhado' ? 'checked' : ''; ?> class="mt-0.5 text-elo-teal focus:ring-elo-teal">
                                            <span>
                                                <strong class="block text-gray-800">Detalhado por perfil</strong>
                                                <span class="text-xs text-gray-500">Lista de telas liberadas em cada perfil</span>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <div>
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <div>
                                            <h2 class="font-semibold text-gray-800">Perfis de Acesso</h2>
                                            <p class="text-xs text-gray-500">Sem seleção, todos os perfis são incluídos.</p>
                                        </div>
                                        <div class="flex gap-2 text-xs">
                                            <button type="button" data-check-group="profiles" data-check-value="1" class="font-medium text-elo-teal hover:underline">Todos</button>
                                            <button type="button" data-check-group="profiles" data-check-value="0" class="font-medium text-gray-500 hover:underline">Limpar</button>
                                        </div>
                                    </div>
                                    <div class="max-h-56 space-y-1.5 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-3">
                                        <?php foreach ($allProfiles as $profile): ?>
                                            <label class="flex cursor-pointer items-center justify-between gap-2 rounded px-2 py-1.5 text-sm text-gray-700 hover:bg-white">
                                                <span class="flex items-center gap-2">
                                                    <input type="checkbox" name="profiles[]" data-group="profiles"
                                                           value="<?php echo (int) $profile['idPerfil']; ?>"
                                                           <?php echo in_array((int) $profile['idPerfil'], $selectedProfileIds, true) ? 'checked' : ''; ?>
                                                           class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                                                    <span><?php echo appEscape((string) $profile['nomePerfil']); ?></span>
                                                </span>
                                                <span class="text-xs text-gray-400"><?php echo (int) $profile['totalUsuarios']; ?> usu.</span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-5 border-t border-gray-200 pt-5 lg:col-span-7 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div>
                                        <div class="mb-2 flex items-center justify-between gap-2">
                                            <div>
                                                <h2 class="font-semibold text-gray-800">Módulos</h2>
                                                <p class="text-xs text-gray-500">Sem seleção, inclui todos.</p>
                                            </div>
                                            <div class="flex gap-2 text-xs">
                                                <button type="button" data-check-group="modules" data-check-value="1" class="font-medium text-elo-teal hover:underline">Todos</button>
                                                <button type="button" data-check-group="modules" data-check-value="0" class="font-medium text-gray-500 hover:underline">Limpar</button>
                                            </div>
                                        </div>
                                        <div class="space-y-1.5 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                            <?php foreach ($allModules as $moduleKey => $moduleLabel): ?>
                                                <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm text-gray-700 hover:bg-white">
                                                    <input type="checkbox" name="modules[]" data-group="modules"
                                                           value="<?php echo appEscape($moduleKey); ?>"
                                                           <?php echo in_array($moduleKey, $selectedModules, true) ? 'checked' : ''; ?>
                                                           class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                                                    <span><?php echo appEscape($moduleLabel); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="mb-2 flex items-center justify-between gap-2">
                                            <div>
                                                <h2 class="font-semibold text-gray-800">Categorias</h2>
                                                <p class="text-xs text-gray-500">Sem seleção, inclui todas.</p>
                                            </div>
                                            <div class="flex gap-2 text-xs">
                                                <button type="button" data-check-group="categories" data-check-value="1" class="font-medium text-elo-teal hover:underline">Todas</button>
                                                <button type="button" data-check-group="categories" data-check-value="0" class="font-medium text-gray-500 hover:underline">Limpar</button>
                                            </div>
                                        </div>
                                        <div class="space-y-1.5 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                            <?php foreach ($categoryNames as $catKey => $catLabel): ?>
                                                <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm text-gray-700 hover:bg-white">
                                                    <input type="checkbox" name="categories[]" data-group="categories"
                                                           value="<?php echo appEscape($catKey); ?>"
                                                           <?php echo in_array($catKey, $selectedCategories, true) ? 'checked' : ''; ?>
                                                           class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                                                    <span><?php echo appEscape($catLabel); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-2 rounded-lg border border-gray-200 bg-gray-50 p-4">
                                    <h2 class="font-semibold text-gray-800">Opções de exibição</h2>
                                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="showUserCount" value="1" <?php echo $showUserCount ? 'checked' : ''; ?> class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                                        Mostrar quantidade de usuários vinculados a cada perfil
                                    </label>
                                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="onlyGranted" value="1" <?php echo $onlyGranted ? 'checked' : ''; ?> class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                                        Exibir apenas telas com pelo menos um perfil liberado
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 flex justify-end">
                            <button type="submit" class="inline-flex items-center gap-2 rounded bg-elo-teal px-5 py-2.5 font-medium text-white transition hover:bg-elo-green">
                                <i class="ph ph-file-magnifying-glass" aria-hidden="true"></i> Gerar relatório
                            </button>
                        </div>
                    </section>

                    <section id="painel-configuracoes" role="tabpanel" aria-labelledby="tab-configuracoes" data-matrix-panel="configuracoes" class="p-4 md:p-5" hidden>
                        <div class="grid gap-6 lg:grid-cols-2">
                            <div class="space-y-4">
                                <h2 class="font-semibold text-gray-800">Papel</h2>
                                <label class="block text-sm text-gray-700">Tamanho do papel
                                    <select name="paper" id="matrix-paper-size" class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                                        <option value="A4" <?php echo $paper === 'A4' ? 'selected' : ''; ?>>A4 (210 × 297 mm)</option>
                                        <option value="A3" <?php echo $paper === 'A3' ? 'selected' : ''; ?>>A3 (297 × 420 mm)</option>
                                        <option value="Letter" <?php echo $paper === 'Letter' ? 'selected' : ''; ?>>Carta (216 × 279 mm)</option>
                                        <option value="custom" <?php echo $paper === 'custom' ? 'selected' : ''; ?>>Personalizado</option>
                                    </select>
                                </label>
                                <label class="block text-sm text-gray-700">Orientação
                                    <select name="orientation" class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                                        <option value="landscape" <?php echo $orientation === 'landscape' ? 'selected' : ''; ?>>Paisagem (recomendado para Matriz)</option>
                                        <option value="portrait" <?php echo $orientation === 'portrait' ? 'selected' : ''; ?>>Retrato</option>
                                    </select>
                                </label>
                                <div id="matrix-custom-paper" class="grid grid-cols-2 gap-3 <?php echo $paper === 'custom' ? '' : 'hidden'; ?>">
                                    <label class="text-sm text-gray-700">Largura (mm)
                                        <input type="number" min="50" max="1000" step="1" name="paperWidth" value="<?php echo appEscape((string) $paperWidth); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                    </label>
                                    <label class="text-sm text-gray-700">Altura (mm)
                                        <input type="number" min="50" max="1000" step="1" name="paperHeight" value="<?php echo appEscape((string) $paperHeight); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                    </label>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <?php foreach (['marginTop' => 'Superior', 'marginBottom' => 'Inferior', 'marginLeft' => 'Esquerda', 'marginRight' => 'Direita'] as $marginKey => $marginLabel): ?>
                                        <label class="text-sm text-gray-700"><?php echo appEscape($marginLabel); ?> (mm)
                                            <input type="number" min="0" max="100" step="1" name="<?php echo appEscape($marginKey); ?>" value="<?php echo appEscape((string) $$marginKey); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <h2 class="font-semibold text-gray-800">Zebrado</h2>
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="zebra" value="1" <?php echo $zebra ? 'checked' : ''; ?> class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                                    Imprimir linhas zebradas
                                </label>
                                <label class="block max-w-xs text-sm text-gray-700">Cor das linhas alternadas
                                    <input type="color" name="zebraColor" value="<?php echo appEscape($zebraColor); ?>" class="mt-1 h-10 w-full cursor-pointer rounded border border-gray-300 bg-white p-1">
                                </label>
                                <p class="text-xs text-gray-500">As configurações de papel, orientação e margens são aplicadas tanto na impressão quanto no PDF baixado.</p>
                            </div>
                        </div>
                        <div class="mt-5 flex justify-end">
                            <button type="submit" class="inline-flex items-center gap-2 rounded bg-elo-teal px-5 py-2.5 font-medium text-white transition hover:bg-elo-green">
                                <i class="ph ph-file-magnifying-glass" aria-hidden="true"></i> Gerar relatório
                            </button>
                        </div>
                    </section>
                </div>
            </form>
        </main>
    </div>

    <script>
        (function () {
            const tabs = document.querySelectorAll('[data-matrix-tab]');
            const panels = document.querySelectorAll('[data-matrix-panel]');
            tabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    const target = tab.getAttribute('data-matrix-tab');
                    tabs.forEach((t) => t.setAttribute('aria-selected', t === tab ? 'true' : 'false'));
                    panels.forEach((p) => {
                        p.hidden = p.getAttribute('data-matrix-panel') !== target;
                    });
                });
            });

            document.querySelectorAll('[data-check-group]').forEach((button) => {
                button.addEventListener('click', () => {
                    const group = button.getAttribute('data-check-group');
                    const checked = button.getAttribute('data-check-value') === '1';
                    document.querySelectorAll('input[type="checkbox"][data-group="' + group + '"]').forEach((input) => {
                        input.checked = checked;
                    });
                });
            });

            const paperSelect = document.getElementById('matrix-paper-size');
            const customPaper = document.getElementById('matrix-custom-paper');
            if (paperSelect && customPaper) {
                paperSelect.addEventListener('change', () => {
                    customPaper.classList.toggle('hidden', paperSelect.value !== 'custom');
                });
            }
        })();
    </script>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
    <?php include __DIR__ . '/../../../components/toast.php'; ?>
</body>
</html>
