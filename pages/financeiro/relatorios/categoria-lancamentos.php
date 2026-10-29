<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('financeiro.relatorios/categoria-lancamentos');
$reportConfig = [
    'file' => 'categoria-lancamentos.php',
    'title' => 'Relatório de Categorias de Lançamentos',
    'pageTitle' => 'Categorias de Lançamentos',
    'singular' => 'categoria',
    'plural' => 'categorias',
    'downloadName' => 'relatorio-categorias-de-lancamentos.pdf',
    'select' => 'SELECT c.idCategoria, c.nomeCategoria, c.naturezaCategoria, c.descricaoCategoria,
                         c.statusCategoria, c.criadoEm, c.atualizadoEm,
                         criador.idUsuario AS criadoPorId, criador.nomeCompleto AS criadoPor,
                         alterador.idUsuario AS alteradoPorId, alterador.nomeCompleto AS alteradoPor',
    'from' => 'FROM tbCategoriaLancamentos c
               INNER JOIN tbUsuario criador ON criador.idUsuario = c.idUsuarioCriacao
               LEFT JOIN tbUsuario alterador ON alterador.idUsuario = c.idUsuarioAlteracao',
    'filters' => [
        'nome' => ['label' => 'Categoria', 'type' => 'text', 'sql' => 'c.nomeCategoria'],
        'natureza' => [
            'label' => 'Natureza',
            'type' => 'option',
            'sql' => 'c.naturezaCategoria',
            'options' => ['Entrada' => 'Entrada', 'Saída' => 'Saída'],
        ],
        'descricao' => ['label' => 'Descrição', 'type' => 'text', 'sql' => 'c.descricaoCategoria'],
        'status' => ['label' => 'Status', 'type' => 'status', 'sql' => 'c.statusCategoria'],
        'criadoEm' => ['label' => 'Data de Criação', 'type' => 'date', 'sql' => 'c.criadoEm'],
        'atualizadoEm' => ['label' => 'Data da Última Alteração', 'type' => 'date', 'sql' => 'c.atualizadoEm'],
        'criadoPor' => ['label' => 'Criado Por', 'type' => 'text', 'sql' => 'criador.nomeCompleto'],
        'alteradoPor' => ['label' => 'Alterado Por', 'type' => 'text', 'sql' => 'alterador.nomeCompleto'],
    ],
    'groups' => [
        'nome' => ['label' => 'Categoria', 'value' => 'nomeCategoria', 'id' => 'idCategoria'],
        'natureza' => ['label' => 'Natureza', 'value' => 'naturezaCategoria'],
        'descricao' => ['label' => 'Descrição', 'value' => 'descricaoCategoria'],
        'status' => ['label' => 'Status', 'value' => 'statusCategoria'],
        'criadoEm' => ['label' => 'Criado Em', 'value' => 'criadoEm'],
        'atualizadoEm' => ['label' => 'Última Alteração', 'value' => 'atualizadoEm'],
        'criadoPor' => ['label' => 'Criado Por', 'value' => 'criadoPor', 'id' => 'criadoPorId'],
        'alteradoPor' => ['label' => 'Alterado Por', 'value' => 'alteradoPor', 'id' => 'alteradoPorId'],
    ],
    'columns' => [
        'nome' => ['label' => 'Categoria', 'sql' => 'c.idCategoria', 'value' => 'nomeCategoria', 'format' => 'id-name', 'idValue' => 'idCategoria'],
        'natureza' => ['label' => 'Natureza', 'sql' => 'c.naturezaCategoria', 'value' => 'naturezaCategoria'],
        'descricao' => ['label' => 'Descrição', 'sql' => 'c.descricaoCategoria', 'value' => 'descricaoCategoria'],
        'status' => ['label' => 'Status', 'sql' => 'c.statusCategoria', 'value' => 'statusCategoria', 'format' => 'status'],
        'criadoEm' => ['label' => 'Criado Em', 'sql' => 'c.criadoEm', 'value' => 'criadoEm', 'format' => 'datetime'],
        'atualizadoEm' => ['label' => 'Última Alteração', 'sql' => 'c.atualizadoEm', 'value' => 'atualizadoEm', 'format' => 'datetime'],
        'criadoPor' => ['label' => 'Criado Por', 'sql' => 'criador.idUsuario', 'value' => 'criadoPor', 'format' => 'id-name', 'idValue' => 'criadoPorId'],
        'alteradoPor' => ['label' => 'Alterado Por', 'sql' => 'alterador.idUsuario', 'value' => 'alteradoPor', 'format' => 'id-name', 'idValue' => 'alteradoPorId'],
    ],
];
$pdo = appPdo();
$baseUrl = appBaseUrl();
$filtersAvailable = $reportConfig['filters'];
$groupsAvailable = $reportConfig['groups'];
$columnsAvailable = $reportConfig['columns'];
$errors = [];
$reportRows = [];
$toastError = '';
$restoreConfiguration = $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['reportAction'] ?? '') === 'configure';
$reportGenerated = $_SERVER['REQUEST_METHOD'] === 'POST' && !$restoreConfiguration;
$selectedGroups = [];
$selectedColumns = [];
$groupHeader = null;
$filters = [];
$sortDirection = 'ASC';
$paper = 'A4';
$orientation = 'portrait';
$paperWidth = 210;
$paperHeight = 297;
$marginTop = 7;
$marginBottom = 15;
$marginLeft = 7;
$marginRight = 7;
$zebra = true;
$zebraColor = '#f2f2f2';
$showGroupCount = false;

if ($reportGenerated || $restoreConfiguration) {
    appValidateCsrf();
    $requestedGroups = $_POST['groupings'] ?? [];
    $requestedFilters = $_POST['filters'] ?? [];
    if (!is_array($requestedGroups) || !is_array($requestedFilters)
        || array_filter($requestedGroups, static fn(mixed $value): bool => !is_scalar($value))) {
        $errors[] = 'As opções do relatório são inválidas.';
    } else {
        foreach (array_map('strval', $requestedGroups) as $groupKey) {
            if (isset($groupsAvailable[$groupKey]) && !in_array($groupKey, $selectedGroups, true)) {
                $selectedGroups[] = $groupKey;
            }
        }
        if (!$selectedGroups) {
            $errors[] = 'Selecione ao menos um atributo para o relatório.';
        }
        $groupHeader = count($selectedGroups) > 1 ? $selectedGroups[0] : null;
        $selectedColumns = $groupHeader === null
            ? $selectedGroups
            : array_slice($selectedGroups, 1);

        foreach ($requestedFilters as $filter) {
            if (!is_array($filter)
                || !is_scalar($filter['field'] ?? null)
                || !is_scalar($filter['operator'] ?? null)
                || (!is_scalar($filter['value'] ?? '') && !is_array($filter['value'] ?? ''))) {
                $errors[] = 'Um dos filtros informados é inválido.';
                break;
            }
            $fieldKey = (string) $filter['field'];
            $operator = (string) $filter['operator'];
            $value = $filter['value'] ?? '';
            $field = $filtersAvailable[$fieldKey] ?? null;
            if ($field === null) {
                $errors[] = 'Um dos atributos de filtro não é válido.';
                break;
            }
            if ($field['type'] === 'date') {
                if (!is_array($value) || !is_scalar($value['from'] ?? null) || !is_scalar($value['to'] ?? null)) {
                    $errors[] = 'Um dos intervalos de datas informados é inválido.';
                    break;
                }
                $value = ['from' => (string) $value['from'], 'to' => (string) $value['to']];
                if ($value['from'] === '' && $value['to'] === '') {
                    continue;
                }
            } else {
                if (!is_scalar($value)) {
                    $errors[] = 'Um dos valores de filtro é inválido.';
                    break;
                }
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }
            }

            $allowedOperators = match ($field['type']) {
                'text' => ['contains', 'equals', 'starts'],
                'status', 'option' => ['equals'],
                'date' => ['between'],
            };
            if (!in_array($operator, $allowedOperators, true)) {
                $errors[] = 'Um dos operadores de filtro não é válido.';
                break;
            }
            if ($field['type'] === 'date') {
                $startDate = $value['from'];
                $endDate = $value['to'];
                $validStartDate = DateTime::createFromFormat('!Y-m-d', $startDate);
                $validEndDate = DateTime::createFromFormat('!Y-m-d', $endDate);
                if (!$validStartDate || !$validEndDate
                    || $validStartDate->format('Y-m-d') !== $startDate
                    || $validEndDate->format('Y-m-d') !== $endDate
                    || $startDate > $endDate) {
                    $errors[] = 'Informe um intervalo de datas válido, com a data inicial anterior à final.';
                    break;
                }
                $value = ['from' => $startDate, 'to' => $endDate];
            } elseif ($field['type'] === 'status' && !in_array($value, ['0', '1'], true)) {
                $errors[] = 'Selecione um status válido.';
                break;
            } elseif ($field['type'] === 'option' && !array_key_exists((string) $value, $field['options'])) {
                $errors[] = 'Selecione uma opção válida para o filtro.';
                break;
            }
            $filters[] = ['field' => $fieldKey, 'operator' => $operator, 'value' => $value];
        }
    }

    $sortDirectionInput = $_POST['sortDirection'] ?? 'ASC';
    $sortDirection = is_scalar($sortDirectionInput) ? strtoupper((string) $sortDirectionInput) : '';
    if (!in_array($sortDirection, ['ASC', 'DESC'], true)) {
        $errors[] = 'A direção de ordenação selecionada é inválida.';
        $sortDirection = 'ASC';
    }

    $paperInput = $_POST['paper'] ?? 'A4';
    $paper = is_scalar($paperInput) ? (string) $paperInput : '';
    if (!in_array($paper, ['A4', 'A3', 'Letter', 'custom'], true)) {
        $errors[] = 'O tamanho do papel selecionado é inválido.';
        $paper = 'A4';
    }
    $orientationInput = $_POST['orientation'] ?? 'portrait';
    $orientation = is_scalar($orientationInput) ? (string) $orientationInput : '';
    if (!in_array($orientation, ['portrait', 'landscape'], true)) {
        $errors[] = 'A orientação de impressão selecionada é inválida.';
        $orientation = 'portrait';
    }
    foreach ([
        'paperWidth' => ['property' => 'paperWidth', 'default' => 210, 'min' => 50, 'max' => 1000],
        'paperHeight' => ['property' => 'paperHeight', 'default' => 297, 'min' => 50, 'max' => 1000],
        'marginTop' => ['property' => 'marginTop', 'default' => 7, 'min' => 0, 'max' => 100],
        'marginBottom' => ['property' => 'marginBottom', 'default' => 15, 'min' => 0, 'max' => 100],
        'marginLeft' => ['property' => 'marginLeft', 'default' => 7, 'min' => 0, 'max' => 100],
        'marginRight' => ['property' => 'marginRight', 'default' => 7, 'min' => 0, 'max' => 100],
    ] as $inputName => $setting) {
        $rawNumber = $_POST[$inputName] ?? $setting['default'];
        $number = is_scalar($rawNumber) ? filter_var($rawNumber, FILTER_VALIDATE_FLOAT) : false;
        if ($number === false || $number < $setting['min'] || $number > $setting['max']) {
            $errors[] = 'Confira as dimensões do papel e as margens informadas.';
            break;
        }
        ${$setting['property']} = (float) $number;
    }
    $showGroupCount = isset($_POST['showGroupCount']);
    $zebra = isset($_POST['zebra']);
    $requestedZebraColor = $_POST['zebraColor'] ?? '#f2f2f2';
    $requestedZebraColor = is_scalar($requestedZebraColor) ? (string) $requestedZebraColor : '';
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $requestedZebraColor)) {
        $zebraColor = $requestedZebraColor;
    }

    if ($reportGenerated && !$errors) {
        $where = [];
        $parameters = [];
        foreach ($filters as $index => $filter) {
            $field = $filtersAvailable[$filter['field']];
            $parameter = ':filter' . $index;
            if ($field['type'] === 'text') {
                $value = match ($filter['operator']) {
                    'contains' => '%' . $filter['value'] . '%',
                    'starts' => $filter['value'] . '%',
                    default => $filter['value'],
                };
                $operator = $filter['operator'] === 'equals' ? '=' : 'LIKE';
                $where[] = $field['sql'] . " $operator $parameter";
                $parameters[$parameter] = $value;
            } elseif (in_array($field['type'], ['status', 'option'], true)) {
                $where[] = $field['sql'] . " = $parameter";
                $parameters[$parameter] = $filter['value'];
            } else {
                $endParameter = $parameter . '_end';
                $where[] = $field['sql'] . " >= $parameter AND " . $field['sql'] . " < $endParameter";
                $parameters[$parameter] = $filter['value']['from'];
                $parameters[$endParameter] = (new DateTimeImmutable($filter['value']['to']))
                    ->modify('+1 day')
                    ->format('Y-m-d');
            }
        }

        $sql = $reportConfig['select'] . ' ' . $reportConfig['from'];
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $orderExpressions = [];
        if ($groupHeader !== null) {
            $orderExpressions[] = $columnsAvailable[$groupHeader]['sql'] . ' ' . $sortDirection;
        }
        foreach ($selectedColumns as $columnKey) {
            $orderExpressions[] = $columnsAvailable[$columnKey]['sql'] . ' ' . $sortDirection;
        }
        if (!$orderExpressions) {
            $orderExpressions[] = $columnsAvailable[array_key_first($columnsAvailable)]['sql'] . ' ' . $sortDirection;
        }
        $sql .= ' ORDER BY ' . implode(', ', $orderExpressions);
        $statement = $pdo->prepare($sql);
        $statement->execute($parameters);
        $reportRows = $statement->fetchAll();
    }

    if ($errors) {
        $toastError = implode(' ', array_unique($errors));
    }
}

$renderHiddenInputs = static function (array $values, string $prefix = '') use (&$renderHiddenInputs): void {
    foreach ($values as $key => $value) {
        $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';
        if (is_array($value)) {
            $renderHiddenInputs($value, $name);
        } elseif (is_scalar($value)) {
            echo '<input type="hidden" name="' . appEscape($name) . '" value="' . appEscape((string) $value) . '">' . PHP_EOL;
        }
    }
};

$columnValue = static function (array $row, string $key) use ($columnsAvailable): string {
    $column = $columnsAvailable[$key];
    if (($column['format'] ?? '') === 'status') {
        return (int) $row[$column['value']] === 1 ? 'Ativo' : 'Inativo';
    }
    if (($column['format'] ?? '') === 'datetime') {
        return empty($row[$column['value']]) ? 'Não informado' : date('d/m/Y H:i', strtotime((string) $row[$column['value']]));
    }
    if (($column['format'] ?? '') === 'id-name') {
        $id = $row[$column['idValue']] ?? null;
        $name = trim((string) ($row[$column['value']] ?? ''));
        return $id === null || $name === '' ? 'Não informado' : $id . ' - ' . $name;
    }

    return (string) ($row[$column['value']] ?? '');
};

$detailRowIndex = 0;
$renderGroupedRows = static function (array $rows, ?string $groupKey, int $columnCount) use (
    $groupsAvailable,
    $columnsAvailable,
    $selectedColumns,
    $columnValue,
    $zebra,
    $showGroupCount,
    $reportConfig,
    &$detailRowIndex
): void {
    if ($groupKey === null) {
        foreach ($rows as $row) {
            $zebraClass = $zebra && $detailRowIndex % 2 === 1 ? ' report-zebra-row' : '';
            echo '<tr class="report-data-row' . $zebraClass . '">';
            foreach ($selectedColumns as $columnKey) {
                echo '<td>' . appEscape($columnValue($row, $columnKey)) . '</td>';
            }
            $detailRowIndex++;
            echo '</tr>';
        }
        return;
    }

    $groups = [];
    foreach ($rows as $row) {
        $group = $groupsAvailable[$groupKey];
        $groupValue = (string) ($row[$group['value']] ?? '');
        $groupId = isset($group['id']) ? ($row[$group['id']] ?? null) : null;
        $groupIdentity = $groupId === null ? 'value:' . $groupValue : 'id:' . $groupId;
        $groupLabel = $groupId === null ? $groupValue : $groupId . ' - ' . $groupValue;
        if (!isset($groups[$groupIdentity])) {
            $groups[$groupIdentity] = ['label' => $groupLabel, 'rows' => []];
        }
        $groups[$groupIdentity]['rows'][] = $row;
    }
    $groupCounter = 0;
    foreach ($groups as $groupData) {
        $value = $groupData['label'];
        $groupRows = $groupData['rows'];
        if ($groupKey === 'status') {
            $value = (int) $value === 1 ? 'Ativo' : 'Inativo';
        }
        $groupCounter++;
        echo '<tr class="report-group-row"><th colspan="' . $columnCount . '">';
        $groupLabel = $groupCounter . ' - ' . ($value !== '' ? $value : 'Não informado');
        if ($showGroupCount) {
            $groupLabel .= ' (' . count($groupRows) . ' ' . (count($groupRows) === 1
                ? $reportConfig['singular'] . ')'
                : $reportConfig['plural'] . ')');
        }
        echo appEscape($groupLabel);
        echo '</th></tr>';
        foreach ($groupRows as $row) {
            $zebraClass = $zebra && $detailRowIndex % 2 === 1 ? ' report-zebra-row' : '';
            echo '<tr class="report-data-row' . $zebraClass . '">';
            foreach ($selectedColumns as $columnKey) {
                echo '<td>' . appEscape($columnValue($row, $columnKey)) . '</td>';
            }
            $detailRowIndex++;
            echo '</tr>';
        }
    }
};

$renderReportTable = static function () use (
    $reportRows,
    $selectedColumns,
    $columnsAvailable,
    $renderGroupedRows,
    $groupHeader,
    $reportConfig
): void {
    if (!$reportRows) {
        echo '<p class="report-empty">Nenhum registro corresponde aos filtros informados.</p>';
        return;
    }
    echo '<table class="report-table"><thead><tr>';
    foreach ($selectedColumns as $columnKey) {
        echo '<th>' . appEscape($columnsAvailable[$columnKey]['label']) . '</th>';
    }
    echo '</tr></thead><tbody>';
    $renderGroupedRows($reportRows, $groupHeader, count($selectedColumns));
    echo '</tbody></table>';
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
$pageUrl = $baseUrl . '/pages/financeiro/relatorios/' . $reportConfig['file'];

if ($reportGenerated && !$errors && ($_POST['reportAction'] ?? '') === 'download') {
    require_once __DIR__ . '/../../../vendor/autoload.php';
    $pdfOptions = new \Dompdf\Options();
    $pdfOptions->set('defaultFont', 'DejaVu Sans');
    $pdfOptions->set('isRemoteEnabled', false);
    $dompdf = new \Dompdf\Dompdf($pdfOptions);
    $reportContent = '<header class="report-heading"><h1>' . appEscape($reportConfig['title']) . '</h1><p>Gerado em '
        . appEscape(date('d/m/Y H:i')) . ' · ' . count($reportRows) . ' ' . appEscape($reportConfig['plural']) . '</p></header>';
    ob_start();
    $renderReportTable();
    $reportContent .= (string) ob_get_clean();
    $pdfHtml = '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><style>'
        . '@page{size:' . (float) $pageWidth . 'mm ' . (float) $pageHeight . 'mm;margin:'
        . (float) $marginTop . 'mm ' . (float) $marginRight . 'mm ' . (float) $marginBottom . 'mm ' . (float) $marginLeft . 'mm;}'
        . 'body{font-family:"DejaVu Sans",sans-serif;font-size:9pt;color:#111;margin:0;}'
        . '.report-heading{margin-bottom:5mm;padding-bottom:3mm;border-bottom:1px solid #d1d5db;}'
        . '.report-heading h1{margin:0 0 1mm;font-size:14pt;}'
        . '.report-heading p{margin:0;color:#6b7280;font-size:8pt;}'
        . '.report-table{width:100%;border-collapse:collapse;text-align:left;font-size:8.5pt;}'
        . '.report-table th,.report-table td{border:1px solid #d1d5db;padding:2mm;vertical-align:top;}'
        . '.report-table thead th{background:#e5e7eb;font-weight:bold;}'
        . '.report-table .report-group-row th{background:#e8f4f5;color:#075b63;text-align:left;}'
        . '.report-data-row.report-zebra-row td{background:' . appEscape($zebraColor) . ';}'
        . '.report-empty{padding:5mm;color:#6b7280;text-align:center;}'
        . '</style></head><body>' . $reportContent . '</body></html>';
    $dompdf->loadHtml($pdfHtml, 'UTF-8');
    $dompdf->setPaper([0, 0, $pageWidth * 72 / 25.4, $pageHeight * 72 / 25.4]);
    $dompdf->render();
    $dompdf->stream($reportConfig['downloadName'], ['Attachment' => true]);
    exit;
}
?>
<?php if ($reportGenerated && !$errors): ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo appEscape($reportConfig['title']); ?></title>
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
    </style>
</head>
<body class="report-stream">
    <div class="report-toolbar">
        <form method="post" action="<?php echo appEscape($pageUrl); ?>">
            <?php $renderHiddenInputs($_POST); ?>
            <input type="hidden" name="tabToken" value="<?php echo appEscape((string) appCurrentTabToken()); ?>">
            <input type="hidden" name="reportAction" value="configure">
            <button type="submit" class="report-back-button">Voltar</button>
        </form>
        <button type="button" data-report-print data-report-stream-print>Imprimir</button>
        <form method="post" action="<?php echo appEscape($pageUrl); ?>">
            <?php $renderHiddenInputs($_POST); ?>
            <input type="hidden" name="tabToken" value="<?php echo appEscape((string) appCurrentTabToken()); ?>">
            <input type="hidden" name="reportAction" value="download">
            <button type="submit">Baixar PDF</button>
        </form>
    </div>
    <main id="report-output" class="report-paper" data-zebra="<?php echo $zebra ? 'true' : 'false'; ?>">
        <header class="report-heading">
            <h1><?php echo appEscape($reportConfig['title']); ?></h1>
            <p>Gerado em <?php echo appEscape(date('d/m/Y H:i')); ?> · <?php echo count($reportRows); ?> <?php echo appEscape($reportConfig['plural']); ?></p>
        </header>
        <?php $renderReportTable(); ?>
    </main>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/usuarios-por-perfis.js?v=' . filemtime(__DIR__ . '/../../../assets/js/usuarios-por-perfis.js')); ?>"></script>
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
    <?php include __DIR__ . '/../../../components/financeiro/navbar-financeiro.php'; ?>
    <div class="flex flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../../components/financeiro/sidebar-financeiro.php'; ?>
        <main class="flex-1 overflow-y-auto bg-gray-50 p-4 md:p-6">
            <div data-report-page-header class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800"><?php echo appEscape($reportConfig['pageTitle']); ?></h1>
                    <p class="mt-1 text-sm text-gray-500">Configure os filtros, atributos e opções de impressão do relatório.</p>
                </div>
            </div>
            <form method="post" action="<?php echo appEscape($pageUrl); ?>" id="report-form" class="space-y-4">
                <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                <input type="hidden" name="tabToken" value="<?php echo appEscape((string) appCurrentTabToken()); ?>">
                <input type="hidden" name="reportAction" value="generate">
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-wrap border-b border-gray-200" role="tablist" aria-label="Configurações do relatório">
                        <?php foreach (['filtros' => 'Filtros', 'ordenacao' => 'Ordenação', 'configuracoes' => 'Configurações'] as $tabKey => $tabLabel): ?>
                            <button type="button" id="tab-<?php echo appEscape($tabKey); ?>" role="tab"
                                    aria-controls="painel-<?php echo appEscape($tabKey); ?>" aria-selected="<?php echo $tabKey === 'filtros' ? 'true' : 'false'; ?>"
                                    data-report-tab="<?php echo appEscape($tabKey); ?>" class="report-tab border-b-2 px-4 py-3 text-sm font-medium">
                                <?php echo appEscape($tabLabel); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <section id="painel-filtros" role="tabpanel" aria-labelledby="tab-filtros" data-report-panel="filtros" class="p-4 md:p-5">
                        <div class="grid gap-6 lg:grid-cols-2">
                            <div>
                                <div class="mb-3 flex items-center justify-between gap-2">
                                    <div>
                                        <h2 class="font-semibold text-gray-800">Filtros</h2>
                                        <p class="text-xs text-gray-500">Adicione condições para limitar os registros exibidos.</p>
                                    </div>
                                    <button type="button" data-add-filter class="inline-flex items-center gap-1 rounded bg-elo-teal px-3 py-2 text-sm font-medium text-white hover:bg-elo-green">
                                        <i class="ph ph-plus" aria-hidden="true"></i> Adicionar filtro
                                    </button>
                                </div>
                                <div id="filter-list" data-initial-filters="<?php echo appEscape(json_encode($filters, JSON_THROW_ON_ERROR)); ?>" class="space-y-2"></div>
                                <p id="filter-empty" class="rounded border border-dashed border-gray-300 p-4 text-center text-sm text-gray-500">
                                    Nenhum filtro adicionado. Sem filtros, o relatório inclui todos os registros.
                                </p>
                            </div>
                            <div class="border-t border-gray-200 pt-5 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                                <div class="mb-3">
                                    <h2 class="font-semibold text-gray-800">Agrupamentos</h2>
                                    <p class="text-xs text-gray-500">Adicione os atributos e ordene como deseja agrupar.</p>
                                </div>
                                <div id="group-list" data-initial-groups="<?php echo appEscape(json_encode($selectedGroups, JSON_THROW_ON_ERROR)); ?>" class="space-y-2"></div>
                                <button type="button" data-add-group class="mt-3 inline-flex items-center gap-1 rounded border border-elo-teal px-3 py-2 text-sm font-medium text-elo-teal hover:bg-elo-teal/5">
                                    <i class="ph ph-plus" aria-hidden="true"></i> Adicionar agrupamento
                                </button>
                            </div>
                        </div>
                        <div class="mt-5 flex justify-end">
                            <button type="submit" class="inline-flex items-center gap-2 rounded bg-elo-teal px-5 py-2.5 font-medium text-white hover:bg-elo-green">
                                <i class="ph ph-file-magnifying-glass" aria-hidden="true"></i> Gerar relatório
                            </button>
                        </div>
                    </section>
                    <section id="painel-ordenacao" role="tabpanel" aria-labelledby="tab-ordenacao" data-report-panel="ordenacao" class="p-4 md:p-5" hidden>
                        <div class="max-w-md">
                            <h2 class="font-semibold text-gray-800">Direção da ordenação</h2>
                            <p class="mb-3 text-xs text-gray-500">Os atributos selecionados em Agrupamentos definem o conteúdo e a ordem do relatório.</p>
                            <label class="block text-sm text-gray-700">Direção
                                <select name="sortDirection" class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                                    <option value="ASC" <?php echo $sortDirection === 'ASC' ? 'selected' : ''; ?>>Crescente (A–Z)</option>
                                    <option value="DESC" <?php echo $sortDirection === 'DESC' ? 'selected' : ''; ?>>Decrescente (Z–A)</option>
                                </select>
                            </label>
                        </div>
                    </section>
                    <section id="painel-configuracoes" role="tabpanel" aria-labelledby="tab-configuracoes" data-report-panel="configuracoes" class="p-4 md:p-5" hidden>
                        <div class="grid gap-6 lg:grid-cols-2">
                            <div class="space-y-4">
                                <h2 class="font-semibold text-gray-800">Papel</h2>
                                <label class="block text-sm text-gray-700">Tamanho do papel
                                    <select name="paper" id="paper-size" class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                                        <option value="A4" <?php echo $paper === 'A4' ? 'selected' : ''; ?>>A4 (210 × 297 mm)</option>
                                        <option value="A3" <?php echo $paper === 'A3' ? 'selected' : ''; ?>>A3 (297 × 420 mm)</option>
                                        <option value="Letter" <?php echo $paper === 'Letter' ? 'selected' : ''; ?>>Carta (216 × 279 mm)</option>
                                        <option value="custom" <?php echo $paper === 'custom' ? 'selected' : ''; ?>>Personalizado</option>
                                    </select>
                                </label>
                                <label class="block text-sm text-gray-700">Orientação
                                    <select name="orientation" class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                                        <option value="portrait" <?php echo $orientation === 'portrait' ? 'selected' : ''; ?>>Retrato</option>
                                        <option value="landscape" <?php echo $orientation === 'landscape' ? 'selected' : ''; ?>>Paisagem</option>
                                    </select>
                                </label>
                                <div id="custom-paper-dimensions" class="grid grid-cols-2 gap-3 <?php echo $paper === 'custom' ? '' : 'hidden'; ?>">
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
                                <h2 class="font-semibold text-gray-800">Opções de exibição</h2>
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="showGroupCount" value="1" <?php echo $showGroupCount ? 'checked' : ''; ?> class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                                    Mostrar quantidade de registros por grupo
                                </label>
                                <h3 class="pt-2 font-semibold text-gray-800">Zebrado</h3>
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="zebra" value="1" <?php echo $zebra ? 'checked' : ''; ?> class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                                    Imprimir linhas zebradas
                                </label>
                                <label class="block max-w-xs text-sm text-gray-700">Cor das linhas alternadas
                                    <input type="color" name="zebraColor" value="<?php echo appEscape($zebraColor); ?>" class="mt-1 h-10 w-full cursor-pointer rounded border border-gray-300 bg-white p-1">
                                </label>
                                <p class="text-xs text-gray-500">As configurações de papel e margens são aplicadas ao imprimir ou salvar como PDF pelo navegador.</p>
                            </div>
                        </div>
                    </section>
                </div>
            </form>
        </main>
    </div>
    <template id="filter-row-template">
        <div class="report-filter-row grid gap-2 rounded border border-gray-200 bg-gray-50 p-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.2fr)_auto]">
            <select data-filter-field class="min-w-0 rounded border border-gray-300 bg-white px-2 py-2 text-sm" aria-label="Atributo do filtro">
                <?php foreach ($filtersAvailable as $fieldKey => $field): ?>
                    <option value="<?php echo appEscape($fieldKey); ?>" data-type="<?php echo appEscape($field['type']); ?>"
                        <?php if ($field['type'] === 'option'): ?>data-options="<?php echo appEscape(json_encode($field['options'], JSON_THROW_ON_ERROR)); ?>"<?php endif; ?>>
                        <?php echo appEscape($field['label']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select data-filter-operator class="min-w-0 rounded border border-gray-300 bg-white px-2 py-2 text-sm" aria-label="Condição do filtro"></select>
            <div data-filter-value></div>
            <button type="button" data-remove-filter aria-label="Remover filtro" class="inline-flex items-center justify-center rounded border border-red-200 px-3 py-2 text-red-600 hover:bg-red-50">
                <i class="ph ph-trash" aria-hidden="true"></i>
            </button>
        </div>
    </template>
    <template id="group-row-template">
        <div class="report-grouping-row flex items-center gap-1 rounded border border-gray-200 bg-gray-50 p-1.5">
            <select data-group-field name="groupings[]" class="min-w-0 flex-1 rounded border border-gray-300 bg-white px-2 py-2 text-sm" aria-label="Atributo do agrupamento">
                <?php foreach ($groupsAvailable as $groupKey => $group): ?>
                    <option value="<?php echo appEscape($groupKey); ?>"><?php echo appEscape($group['label']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" data-order-up aria-label="Mover agrupamento para cima" class="rounded p-1 text-gray-500 hover:bg-gray-100"><i class="ph ph-caret-up" aria-hidden="true"></i></button>
            <button type="button" data-order-down aria-label="Mover agrupamento para baixo" class="rounded p-1 text-gray-500 hover:bg-gray-100"><i class="ph ph-caret-down" aria-hidden="true"></i></button>
            <button type="button" data-remove-group aria-label="Remover agrupamento" class="rounded p-1 text-red-600 hover:bg-red-50"><i class="ph ph-x" aria-hidden="true"></i></button>
        </div>
    </template>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/usuarios-por-perfis.js?v=' . filemtime(__DIR__ . '/../../../assets/js/usuarios-por-perfis.js')); ?>"></script>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
    <?php include __DIR__ . '/../../../components/toast.php'; ?>
</body>
</html>
