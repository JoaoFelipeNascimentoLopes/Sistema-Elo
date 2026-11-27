<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$inputPath = $argv[1] ?? null;
if (!is_string($inputPath) || trim($inputPath) === '') {
    fwrite(STDERR, "Uso: php docs/gerar-pdf.php <caminho-do-arquivo.md> [caminho-de-saida.pdf]\n");
    exit(1);
}

$resolvedInput = realpath($inputPath);
if ($resolvedInput === false || !is_file($resolvedInput)) {
    fwrite(STDERR, "Arquivo Markdown não encontrado: $inputPath\n");
    exit(1);
}

$outputPath = $argv[2] ?? preg_replace('/\.md$/i', '.pdf', $resolvedInput);
if (!is_string($outputPath) || $outputPath === $resolvedInput) {
    $outputPath = $resolvedInput . '.pdf';
}

$raw = file_get_contents($resolvedInput);
if ($raw === false) {
    fwrite(STDERR, "Não foi possível ler o arquivo: $resolvedInput\n");
    exit(1);
}

$raw = str_replace(["\r\n", "\r"], "\n", $raw);

$meta = [
    'title' => 'Documentação do Sistema',
    'subtitle' => '',
    'module' => '',
    'system' => 'Sistema Elo — Gerenciamento de Casas de Acolhimento',
    'author' => 'Equipe Sistema Elo',
    'version' => '1.0',
    'date' => date('d/m/Y'),
];

$bodyMarkdown = $raw;
if (preg_match('/\A---\n(.*?)\n---\n?(.*)\z/s', $raw, $matches)) {
    foreach (explode("\n", $matches[1]) as $metaLine) {
        if (preg_match('/^\s*([a-zA-Z0-9_-]+)\s*:\s*(.+?)\s*$/', $metaLine, $kv)) {
            $meta[strtolower($kv[1])] = trim($kv[2], "\"'");
        }
    }
    $bodyMarkdown = $matches[2];
}

$escape = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$renderInline = static function (string $text) use ($escape): string {
    $placeholders = [];
    $text = preg_replace_callback('/`([^`]+)`/', static function (array $m) use (&$placeholders, $escape): string {
        $key = '%%CODE' . count($placeholders) . '%%';
        $placeholders[$key] = '<code>' . $escape($m[1]) . '</code>';
        return $key;
    }, $text) ?? $text;

    $escaped = $escape($text);
    $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
    $escaped = preg_replace('/(?<!\*)\*([^*]+?)\*(?!\*)/s', '<em>$1</em>', $escaped) ?? $escaped;

    return strtr($escaped, $placeholders);
};

$lines = explode("\n", $bodyMarkdown);
$toc = [];
$htmlParts = [];
$totalLines = count($lines);
$i = 0;
$chapterCount = 0;

while ($i < $totalLines) {
    $line = $lines[$i];
    $trimmed = trim($line);

    if ($trimmed === '') {
        $i++;
        continue;
    }

    if (preg_match('/^---+$/', $trimmed)) {
        $i++;
        continue;
    }

    if (str_starts_with($trimmed, '```')) {
        $codeLines = [];
        $i++;
        while ($i < $totalLines && !str_starts_with(trim($lines[$i]), '```')) {
            $codeLines[] = $lines[$i];
            $i++;
        }
        $i++;
        $htmlParts[] = '<pre class="code-block"><code>' . $escape(implode("\n", $codeLines)) . '</code></pre>';
        continue;
    }

    if (preg_match('/^(#{1,3})\s+(.+)$/', $trimmed, $hMatch)) {
        $level = strlen($hMatch[1]);
        $titleText = trim($hMatch[2]);
        $anchor = 'sec-' . (count($toc) + 1);
        if ($level <= 2) {
            $toc[] = ['level' => $level, 'title' => $titleText, 'anchor' => $anchor];
        }
        if ($level === 1) {
            $chapterCount++;
            $breakClass = $chapterCount > 1 ? ' chapter-break' : '';
            $htmlParts[] = '<h1 id="' . $anchor . '" class="chapter-title' . $breakClass . '">' . $renderInline($titleText) . '</h1>';
        } elseif ($level === 2) {
            $htmlParts[] = '<h2 id="' . $anchor . '">' . $renderInline($titleText) . '</h2>';
        } else {
            $htmlParts[] = '<h3>' . $renderInline($titleText) . '</h3>';
        }
        $i++;
        continue;
    }

    if (str_starts_with($trimmed, '>')) {
        $quoteLines = [];
        while ($i < $totalLines && str_starts_with(trim($lines[$i]), '>')) {
            $quoteLines[] = ltrim(substr(trim($lines[$i]), 1));
            $i++;
        }
        $htmlParts[] = '<blockquote class="callout">' . $renderInline(implode(' ', $quoteLines)) . '</blockquote>';
        continue;
    }

    if (str_contains($line, '|') && ($i + 1 < $totalLines) && preg_match('/^\s*\|?[\s:\-|]+\|[\s:\-|]*$/', $lines[$i + 1])) {
        $parseRow = static function (string $row): array {
            $row = trim($row);
            $row = trim($row, '|');
            return array_map('trim', explode('|', $row));
        };
        $headers = $parseRow($line);
        $i += 2;
        $rows = [];
        while ($i < $totalLines && trim($lines[$i]) !== '' && str_contains($lines[$i], '|')) {
            $rows[] = $parseRow($lines[$i]);
            $i++;
        }
        $tableHtml = '<table class="doc-table"><thead><tr>';
        foreach ($headers as $cell) {
            $tableHtml .= '<th>' . $renderInline($cell) . '</th>';
        }
        $tableHtml .= '</tr></thead><tbody>';
        foreach ($rows as $rowIndex => $row) {
            $zebra = $rowIndex % 2 === 1 ? ' class="zebra"' : '';
            $tableHtml .= '<tr' . $zebra . '>';
            foreach ($headers as $colIdx => $_) {
                $tableHtml .= '<td>' . $renderInline($row[$colIdx] ?? '') . '</td>';
            }
            $tableHtml .= '</tr>';
        }
        $tableHtml .= '</tbody></table>';
        $htmlParts[] = $tableHtml;
        continue;
    }

    if (preg_match('/^[-*]\s+(.+)$/', $trimmed)) {
        $items = [];
        while ($i < $totalLines && preg_match('/^[-*]\s+(.+)$/', trim($lines[$i]), $m)) {
            $items[] = '<li>' . $renderInline($m[1]) . '</li>';
            $i++;
        }
        $htmlParts[] = '<ul>' . implode('', $items) . '</ul>';
        continue;
    }

    if (preg_match('/^\d+\.\s+(.+)$/', $trimmed)) {
        $items = [];
        while ($i < $totalLines) {
            $curLine = $lines[$i];
            $curTrim = trim($curLine);
            if (preg_match('/^\d+\.\s+(.+)$/', $curTrim, $m)) {
                $itemHtml = $renderInline($m[1]);
                $i++;
                $subItems = [];
                while ($i < $totalLines && preg_match('/^\s+[-*]\s+(.+)$/', $lines[$i], $sm)) {
                    $subItems[] = '<li>' . $renderInline($sm[1]) . '</li>';
                    $i++;
                }
                if ($subItems) {
                    $itemHtml .= '<ul class="nested-list">' . implode('', $subItems) . '</ul>';
                }
                $items[] = '<li>' . $itemHtml . '</li>';
                continue;
            }
            break;
        }
        $htmlParts[] = '<ol>' . implode('', $items) . '</ol>';
        continue;
    }

    $paraLines = [];
    while ($i < $totalLines) {
        $cur = trim($lines[$i]);
        if ($cur === ''
            || preg_match('/^(#{1,3}\s|---|```|>|[-*]\s|\d+\.\s)/', $cur)
            || (str_contains($lines[$i], '|') && ($i + 1 < $totalLines) && preg_match('/^\s*\|?[\s:\-|]+\|[\s:\-|]*$/', $lines[$i + 1]))) {
            break;
        }
        $paraLines[] = $cur;
        $i++;
    }
    $htmlParts[] = '<p>' . $renderInline(implode(' ', $paraLines)) . '</p>';
}

$projectRoot = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
$tempDir = __DIR__ . DIRECTORY_SEPARATOR . '.pdf-tmp';
if (!is_dir($tempDir)) {
    @mkdir($tempDir, 0775, true);
}

$logoCanvasPath = null;
$logoAspect = 1.0;
$logoMarkup = '';
$logoPath = realpath(__DIR__ . '/../assets/imgs/logo-elo.png');
if (extension_loaded('gd') && $logoPath !== false && is_file($logoPath) && is_dir($tempDir)) {
    $src = @imagecreatefrompng($logoPath);
    if ($src !== false) {
        $w = imagesx($src);
        $h = imagesy($src);
        $logoAspect = $w > 0 ? ($h / $w) : 1.0;
        $dst = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $w, $h, $white);
        imagealphablending($dst, true);
        imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
        $logoJpgPath = $tempDir . DIRECTORY_SEPARATOR . 'logo-elo.jpg';
        $saved = imagejpeg($dst, $logoJpgPath, 92);
        imagedestroy($src);
        imagedestroy($dst);
        if ($saved && is_file($logoJpgPath)) {
            $logoCanvasPath = $logoJpgPath;
            $logoMarkup = '<div class="cover-logo-spacer"></div>';
        }
    }
}
if ($logoMarkup === '') {
    $logoMarkup = '<div class="cover-monogram">ELO</div>';
}

$tocHtml = '<div class="toc-page"><div class="toc-eyebrow">SUMÁRIO</div><h1 class="toc-heading">Conteúdo do Manual</h1><ul class="toc-list">';
foreach ($toc as $entry) {
    $cls = $entry['level'] === 1 ? 'toc-l1' : 'toc-l2';
    $tocHtml .= '<li class="' . $cls . '"><a href="#' . $escape($entry['anchor']) . '">' . $escape($entry['title']) . '</a></li>';
}
$tocHtml .= '</ul></div>';

$coverHtml = '<div class="cover-page">'
    . '<div class="cover-accent"></div>'
    . '<div class="cover-top">'
    . $logoMarkup
    . '<div class="cover-system">' . $escape($meta['system']) . '</div>'
    . '</div>'
    . '<div class="cover-main">'
    . ($meta['module'] !== '' ? '<div class="cover-badge">' . $escape($meta['module']) . '</div>' : '')
    . '<h1 class="cover-title">' . $escape($meta['title']) . '</h1>'
    . ($meta['subtitle'] !== '' ? '<p class="cover-subtitle">' . $escape($meta['subtitle']) . '</p>' : '')
    . '</div>'
    . '<div class="cover-meta">'
    . '<table>'
    . '<tr><td class="meta-label">Autor</td><td class="meta-value">' . $escape($meta['author']) . '</td></tr>'
    . '<tr><td class="meta-label">Módulo</td><td class="meta-value">' . $escape($meta['module'] !== '' ? $meta['module'] : 'Geral') . '</td></tr>'
    . '<tr><td class="meta-label">Versão</td><td class="meta-value">' . $escape($meta['version']) . '</td></tr>'
    . '<tr><td class="meta-label">Data de emissão</td><td class="meta-value">' . $escape($meta['date']) . '</td></tr>'
    . '</table>'
    . '</div>'
    . '</div>';

$fullHtml = '<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<style>
    @page {
        size: A4 portrait;
        margin: 22mm 16mm 20mm 16mm;
    }
    @page :first {
        margin: 0;
    }
    body {
        font-family: "DejaVu Sans", sans-serif;
        font-size: 9.2pt;
        line-height: 1.5;
        color: #1f2937;
        margin: 0;
    }
    .running-header {
        position: fixed;
        top: -14mm;
        left: 0;
        right: 0;
        height: 8mm;
        border-bottom: 1px solid #cbd5e1;
        font-size: 7.5pt;
        color: #64748b;
    }
    .running-header .rh-left { float: left; font-weight: bold; color: #007c89; }
    .running-header .rh-right { float: right; }

    .cover-page {
        position: relative;
        width: 210mm;
        height: 297mm;
        background: #ffffff;
        page-break-after: always;
    }
    .cover-accent {
        position: absolute;
        top: 0;
        left: 0;
        bottom: 0;
        width: 14mm;
        background: #007c89;
    }
    .cover-top {
        padding: 24mm 20mm 0 28mm;
    }
    .cover-logo-spacer {
        height: 29mm;
        margin-bottom: 4mm;
    }
    .cover-monogram {
        display: inline-block;
        padding: 3mm 5mm;
        margin-bottom: 5mm;
        border-radius: 3mm;
        background: #007c89;
        color: #ffffff;
        font-size: 16pt;
        font-weight: bold;
        letter-spacing: 2pt;
    }
    .cover-system {
        font-size: 9pt;
        font-weight: bold;
        letter-spacing: 1pt;
        text-transform: uppercase;
        color: #475569;
    }
    .cover-main {
        padding: 40mm 22mm 0 28mm;
    }
    .cover-badge {
        display: inline-block;
        padding: 2mm 4.5mm;
        margin-bottom: 6mm;
        border-radius: 3mm;
        background: #e6f4f5;
        color: #007c89;
        font-size: 8.5pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.8pt;
    }
    .cover-title {
        margin: 0 0 5mm;
        font-size: 27pt;
        line-height: 1.15;
        color: #0f172a;
        border: none;
        padding: 0;
        text-align: left;
    }
    .cover-subtitle {
        margin: 0;
        font-size: 12pt;
        line-height: 1.45;
        color: #475569;
        max-width: 145mm;
        text-align: left;
    }
    .cover-meta {
        position: absolute;
        left: 28mm;
        right: 20mm;
        bottom: 22mm;
        border-top: 2px solid #007c89;
        padding-top: 6mm;
    }
    .cover-meta table {
        width: 100%;
        border-collapse: collapse;
    }
    .cover-meta td {
        padding: 1.6mm 0;
        font-size: 9pt;
    }
    .cover-meta .meta-label {
        width: 36mm;
        font-weight: bold;
        color: #64748b;
        text-transform: uppercase;
        font-size: 7.8pt;
        letter-spacing: 0.5pt;
    }
    .cover-meta .meta-value {
        color: #0f172a;
        font-weight: bold;
    }

    .toc-page {
        page-break-after: always;
    }
    .toc-eyebrow {
        font-size: 8pt;
        font-weight: bold;
        letter-spacing: 1.4pt;
        color: #007c89;
        margin-bottom: 2mm;
    }
    .toc-heading {
        margin: 0 0 6mm;
        padding-bottom: 3mm;
        border-bottom: 2px solid #0f172a;
        font-size: 18pt;
        color: #0f172a;
    }
    .toc-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .toc-list li {
        margin: 0;
    }
    .toc-list a {
        text-decoration: none;
        color: #1e293b;
    }
    .toc-l1 {
        padding: 2.2mm 0 1mm;
        font-size: 10.5pt;
        font-weight: bold;
        border-bottom: 1px dotted #cbd5e1;
    }
    .toc-l1 a {
        color: #007c89;
    }
    .toc-l2 {
        padding: 1.1mm 0 1.1mm 6mm;
        font-size: 9pt;
        color: #334155;
    }

    .chapter-title {
        margin: 0 0 4mm;
        padding-bottom: 2.5mm;
        border-bottom: 2px solid #007c89;
        font-size: 15.5pt;
        color: #0f172a;
    }
    .chapter-break {
        page-break-before: always;
    }
    h2 {
        margin: 6mm 0 2.5mm;
        font-size: 11.8pt;
        color: #007c89;
        page-break-after: avoid;
    }
    h3 {
        margin: 4mm 0 2mm;
        font-size: 9.8pt;
        color: #1e293b;
        page-break-after: avoid;
    }
    p {
        margin: 0 0 3mm;
        text-align: justify;
    }
    ul, ol {
        margin: 0 0 3.5mm;
        padding-left: 6mm;
    }
    .nested-list {
        margin: 1.5mm 0 1mm;
        padding-left: 5mm;
    }
    li {
        margin-bottom: 1.2mm;
    }
    code {
        font-family: "DejaVu Sans Mono", monospace;
        font-size: 8pt;
        background: #f1f5f9;
        color: #0f766e;
        padding: 0.4mm 1.2mm;
        border-radius: 1mm;
    }
    .code-block {
        margin: 3mm 0 4mm;
        padding: 3.5mm 4mm;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-left: 3px solid #007c89;
        border-radius: 1.5mm;
        font-family: "DejaVu Sans Mono", monospace;
        font-size: 7.4pt;
        line-height: 1.4;
        color: #0f172a;
        white-space: pre-wrap;
        page-break-inside: avoid;
    }
    .code-block code {
        background: transparent;
        color: inherit;
        padding: 0;
        font-size: 7.4pt;
    }
    .callout {
        margin: 3.5mm 0 4mm;
        padding: 3mm 4mm;
        border-left: 3.5px solid #007c89;
        background: #f0fdfa;
        color: #134e4a;
        font-size: 8.6pt;
        page-break-inside: avoid;
    }
    .doc-table {
        width: 100%;
        border-collapse: collapse;
        margin: 2.5mm 0 4.5mm;
        font-size: 8pt;
        table-layout: fixed;
    }
    .doc-table thead {
        display: table-header-group;
    }
    .doc-table th {
        background: #1e293b;
        color: #ffffff;
        font-weight: bold;
        text-align: left;
        padding: 2mm 2.2mm;
        border: 1px solid #334155;
    }
    .doc-table td {
        padding: 1.8mm 2.2mm;
        border: 1px solid #cbd5e1;
        vertical-align: top;
        overflow-wrap: anywhere;
    }
    .doc-table tr {
        page-break-inside: avoid;
    }
    .doc-table tr.zebra td {
        background: #f8fafc;
    }
</style>
</head>
<body>
' . $coverHtml . '
<div class="running-header">
    <span class="rh-left">' . $escape($meta['title']) . '</span>
    <span class="rh-right">' . $escape($meta['system']) . '</span>
</div>
' . $tocHtml . '
<main class="content">' . implode("\n", $htmlParts) . '</main>
</body>
</html>';

require_once __DIR__ . '/../vendor/autoload.php';

$options = new \Dompdf\Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', false);
$options->set('isPhpEnabled', false);
$options->set('chroot', $projectRoot);
if (is_dir($tempDir) && is_writable($tempDir)) {
    $options->set('tempDir', $tempDir);
}

$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($fullHtml, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$canvas = $dompdf->getCanvas();
$fontMetrics = $dompdf->getFontMetrics();
$font = $fontMetrics->getFont('DejaVu Sans', 'normal');
$pageWidth = $canvas->get_width();
$pageHeight = $canvas->get_height();

$canvas->page_script(static function (int $pageNumber, int $pageCount, $canvas, $fontMetrics) use ($font, $pageWidth, $pageHeight, $meta, $logoCanvasPath, $logoAspect): void {
    if ($pageNumber === 1) {
        if ($logoCanvasPath !== null && is_file($logoCanvasPath)) {
            $mmToPt = 72 / 25.4;
            $logoW = 24 * $mmToPt;
            $logoH = $logoW * $logoAspect;
            $canvas->image($logoCanvasPath, 28 * $mmToPt, 22 * $mmToPt, $logoW, $logoH);
        }
        return;
    }
    $footerLeft = $meta['module'] !== '' ? $meta['module'] . ' · v' . $meta['version'] : 'v' . $meta['version'];
    $footerRight = 'Página ' . $pageNumber . ' de ' . $pageCount;
    $canvas->line(45, $pageHeight - 38, $pageWidth - 45, $pageHeight - 38, [0.8, 0.84, 0.88], 0.6);
    $canvas->text(45, $pageHeight - 32, $footerLeft, $font, 7.5, [0.39, 0.45, 0.54]);
    $textWidth = $fontMetrics->getTextWidth($footerRight, $font, 7.5);
    $canvas->text($pageWidth - 45 - $textWidth, $pageHeight - 32, $footerRight, $font, 7.5, [0.39, 0.45, 0.54]);
});

$pdfOutput = $dompdf->output();
if (is_dir($tempDir)) {
    foreach (glob($tempDir . DIRECTORY_SEPARATOR . '*') ?: [] as $tmpFile) {
        if (is_file($tmpFile)) {
            @unlink($tmpFile);
        }
    }
    @rmdir($tempDir);
}
if (file_put_contents($outputPath, $pdfOutput) === false) {
    fwrite(STDERR, "Não foi possível gravar o PDF em: $outputPath\n");
    exit(1);
}

fwrite(STDOUT, "PDF gerado com sucesso: $outputPath (" . strlen($pdfOutput) . " bytes, " . $dompdf->getCanvas()->get_page_count() . " páginas)\n");
