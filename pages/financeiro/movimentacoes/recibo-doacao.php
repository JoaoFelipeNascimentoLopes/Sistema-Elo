<?php
require_once __DIR__ . '/../../../auth/access.php';
appRequirePage('financeiro.movimentacoes/lancamentos-financeiros');
$pdo = appPdo();

$idInput = $_GET['id'] ?? null;
$idLancamento = is_scalar($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;
if ($idLancamento === false || $idLancamento < 1) {
    http_response_code(404);
    exit('Recibo de doação não encontrado.');
}

$statement = $pdo->prepare(
    'SELECT l.idLancamento, l.dataLancamento, l.descricaoLancamento, l.valorLancamento,
            l.statusLancamento, l.idDoadorContribuinte, c.naturezaCategoria,
            dc.tipoPessoa, dc.documento AS doadorDocumento, dc.nomeRazaoSocial AS doadorNome,
            dc.logradouro AS doadorLogradouro, dc.numero AS doadorNumero,
            dc.complemento AS doadorComplemento, dc.bairro AS doadorBairro,
            dc.cidade AS doadorCidade, dc.uf AS doadorUf, dc.cep AS doadorCep,
            p.nomeFormaPagamento,
            i.razaoSocial AS instituicaoRazaoSocial, i.nomeFantasia AS instituicaoNomeFantasia,
            i.cnpj AS instituicaoCnpj, i.email AS instituicaoEmail, i.telefone AS instituicaoTelefone,
            i.cep AS instituicaoCep, i.logradouro AS instituicaoLogradouro,
            i.numero AS instituicaoNumero, i.complemento AS instituicaoComplemento,
            i.bairro AS instituicaoBairro, i.cidade AS instituicaoCidade, i.uf AS instituicaoUf
     FROM tbLancamentoFinanceiro l
     INNER JOIN tbCategoriaLancamentos c ON c.idCategoria = l.idCategoria
     INNER JOIN tbDoadorContribuinte dc ON dc.idDoadorContribuinte = l.idDoadorContribuinte
     LEFT JOIN tbFormaPagamento p ON p.idFormaPagamento = l.idFormaPagamento
     LEFT JOIN tbInformacoesInstituicao i ON i.idInformacoes = 1
     WHERE l.idLancamento = :id'
);
$statement->execute(['id' => $idLancamento]);
$recibo = $statement->fetch(PDO::FETCH_ASSOC);
if (!$recibo
    || $recibo['naturezaCategoria'] !== 'Entrada'
    || $recibo['statusLancamento'] !== 'Efetivado'
    || empty($recibo['idDoadorContribuinte'])) {
    http_response_code(404);
    exit('Este lançamento não é uma doação efetivada com doador identificado.');
}

$meses = [
    1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
    5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
    9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
];
$formatarDocumento = static function (string $documento): string {
    $digits = preg_replace('/\D/', '', $documento) ?? '';
    if (strlen($digits) === 11) {
        return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9);
    }
    if (strlen($digits) === 14) {
        return substr($digits, 0, 2) . '.' . substr($digits, 2, 3) . '.' . substr($digits, 5, 3) . '/' . substr($digits, 8, 4) . '-' . substr($digits, 12);
    }
    return $documento;
};
$formatarCep = static function (?string $cep): string {
    $digits = preg_replace('/\D/', '', (string) $cep) ?? '';
    return strlen($digits) === 8 ? substr($digits, 0, 5) . '-' . substr($digits, 5) : (string) $cep;
};
$formatarTelefone = static function (?string $telefone): string {
    $digits = preg_replace('/\D/', '', (string) $telefone) ?? '';
    if (strlen($digits) === 11) {
        return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 5) . '-' . substr($digits, 7);
    }
    if (strlen($digits) === 10) {
        return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 4) . '-' . substr($digits, 6);
    }
    return (string) $telefone;
};
$montarEndereco = static function (array $data, string $prefix, callable $formatarCep): string {
    $logradouro = trim((string) ($data[$prefix . 'Logradouro'] ?? ''));
    $numero = trim((string) ($data[$prefix . 'Numero'] ?? ''));
    $complemento = trim((string) ($data[$prefix . 'Complemento'] ?? ''));
    $bairro = trim((string) ($data[$prefix . 'Bairro'] ?? ''));
    $cidade = trim((string) ($data[$prefix . 'Cidade'] ?? ''));
    $uf = trim((string) ($data[$prefix . 'Uf'] ?? ''));
    $cep = trim((string) ($data[$prefix . 'Cep'] ?? ''));
    $logradouroCompleto = trim($logradouro . ($numero !== '' ? ', ' . $numero : '') . ($complemento !== '' ? ' - ' . $complemento : ''));
    $cidadeUf = trim($cidade . ($cidade !== '' && $uf !== '' ? ' - ' : '') . $uf);
    return implode(' · ', array_filter([
        $logradouroCompleto,
        $bairro,
        $cidadeUf,
        $cep !== '' ? 'CEP ' . $formatarCep($cep) : '',
    ], static fn (string $part): bool => $part !== ''));
};

$data = new DateTimeImmutable($recibo['dataLancamento']);
$dataPorExtenso = $data->format('d') . ' de ' . $meses[(int) $data->format('n')] . ' de ' . $data->format('Y');
$valor = 'R$ ' . number_format((float) $recibo['valorLancamento'], 2, ',', '.');
$tipoDocumentoDoador = $recibo['tipoPessoa'] === 'fisica' ? 'CPF' : 'CNPJ';
$doadorDocumento = $formatarDocumento((string) $recibo['doadorDocumento']);
$enderecoDoador = $montarEndereco($recibo, 'doador', $formatarCep);
$enderecoInstituicao = $montarEndereco($recibo, 'instituicao', $formatarCep);
$nomeInstituicao = trim((string) ($recibo['instituicaoRazaoSocial'] ?? ''))
    ?: trim((string) ($recibo['instituicaoNomeFantasia'] ?? ''));
$nomeInstituicao = $nomeInstituicao !== '' ? $nomeInstituicao : null;
$cnpjInstituicao = trim((string) ($recibo['instituicaoCnpj'] ?? ''));
$identificacaoInstituicao = $cnpjInstituicao !== ''
    ? 'CNPJ: ' . $formatarDocumento($cnpjInstituicao)
    : 'CNPJ não informado';
$cidadeInstituicao = trim((string) ($recibo['instituicaoCidade'] ?? ''));
$ufInstituicao = trim((string) ($recibo['instituicaoUf'] ?? ''));
$localInstituicao = trim($cidadeInstituicao . ($cidadeInstituicao !== '' && $ufInstituicao !== '' ? ' - ' : '') . $ufInstituicao);
$numeroRecibo = 'REC-' . $data->format('Y') . '-' . str_pad((string) $recibo['idLancamento'], 6, '0', STR_PAD_LEFT);
if ($nomeInstituicao === null) {
    http_response_code(409);
    header('Content-Type: text/html; charset=UTF-8');
    $voltarUrl = appTabUrl('lancamentos-financeiros.php');
    echo '<!DOCTYPE html><html lang="pt-br"><meta charset="UTF-8"><title>Recibo não emitido</title>'
        . '<body style="margin:0;padding:40px;background:#f3f4f6;font:16px Arial,sans-serif;color:#1f2937">'
        . '<main style="max-width:640px;margin:auto;padding:28px;background:#fff;border:1px solid #d1d5db;border-radius:8px">'
        . '<h1 style="margin-top:0">Recibo não emitido</h1>'
        . '<p>A doação foi salva, mas falta cadastrar a razão social ou o nome fantasia da instituição.</p>'
        . '<p>Preencha os dados em Informações da Instituição e depois emita o recibo pela lista de lançamentos.</p>'
        . '<a href="' . appEscape($voltarUrl) . '" style="display:inline-block;margin-top:12px;padding:10px 16px;background:#007c89;color:#fff;text-decoration:none;border-radius:4px">Voltar aos lançamentos</a>'
        . '</main></body></html>';
    exit;
}

require_once __DIR__ . '/../../../vendor/autoload.php';
$options = new \Dompdf\Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', false);
$pdf = new \Dompdf\Dompdf($options);
$pdf->loadHtml(
    '<!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <style>
            @page { margin: 10mm 12mm; }
            body { color: #17212b; font-family: "DejaVu Sans", sans-serif; font-size: 10pt; line-height: 1.35; }
            .receipt { border: 1px solid #cbd5e1; padding: 8mm; }
            .eyebrow { color: #52616b; font-size: 7.5pt; font-weight: bold; letter-spacing: 1pt; text-transform: uppercase; }
            h1 { margin: 2mm 0 1mm; color: #111827; font-size: 18pt; letter-spacing: 1pt; text-align: center; }
            .number { margin: 0 0 5mm; color: #52616b; font-size: 8.5pt; text-align: center; }
            .institution { padding-bottom: 3mm; border-bottom: 2px solid #1f2937; text-align: center; }
            .institution-name { font-size: 12pt; font-weight: bold; }
            .muted { color: #52616b; font-size: 8pt; }
            .body-copy { margin: 5mm 0; font-size: 10.5pt; text-align: justify; }
            .highlight { margin: 4mm 0; padding: 3mm; border: 1px solid #cbd5e1; background: #f8fafc; }
            .detail { margin: 1mm 0; }
            .label { color: #52616b; font-size: 8pt; font-weight: bold; text-transform: uppercase; }
            .signature { margin-top: 10mm; text-align: center; }
            .signature-line { width: 75%; margin: 0 auto 1mm; border-top: 1px solid #475569; }
            .footer { margin-top: 5mm; padding-top: 2mm; border-top: 1px solid #cbd5e1; color: #64748b; font-size: 7.5pt; text-align: center; }
        </style>
    </head>
    <body>
        <main class="receipt">
            <header class="institution">
                <div class="eyebrow">Sistema Elo · Comprovante de recebimento</div>
                <div class="institution-name">' . appEscape($nomeInstituicao) . '</div>
                <div class="muted">' . appEscape($identificacaoInstituicao) . '</div>
                ' . ($enderecoInstituicao !== '' ? '<div class="muted">' . appEscape($enderecoInstituicao) . '</div>' : '') . '
                ' . (!empty($recibo['instituicaoTelefone']) ? '<div class="muted">Telefone: ' . appEscape($formatarTelefone((string) $recibo['instituicaoTelefone'])) . '</div>' : '') . '
                ' . (!empty($recibo['instituicaoEmail']) ? '<div class="muted">' . appEscape((string) $recibo['instituicaoEmail']) . '</div>' : '') . '
            </header>
            <h1>Recibo de Doação</h1>
            <p class="number">' . appEscape($numeroRecibo) . '</p>
            <p class="body-copy">
                Declaramos, para os devidos fins, que recebemos de <strong>' . appEscape((string) $recibo['doadorNome']) . '</strong>,
                identificado(a) pelo ' . appEscape($tipoDocumentoDoador) . ' nº <strong>' . appEscape($doadorDocumento) . '</strong>,
                a importância de <strong>' . appEscape($valor) . '</strong>, referente à doação descrita neste recibo.
            </p>
            <section class="highlight">
                <div class="detail"><span class="label">Descrição / finalidade</span><br>' . appEscape((string) $recibo['descricaoLancamento']) . '</div>
                <div class="detail"><span class="label">Data do recebimento</span><br>' . appEscape($dataPorExtenso) . '</div>
                ' . (!empty($recibo['nomeFormaPagamento']) ? '<div class="detail"><span class="label">Forma de recebimento</span><br>' . appEscape((string) $recibo['nomeFormaPagamento']) . '</div>' : '') . '
                ' . ($enderecoDoador !== '' ? '<div class="detail"><span class="label">Endereço do doador</span><br>' . appEscape($enderecoDoador) . '</div>' : '') . '
            </section>
            <p>Por ser verdade, firmamos o presente recibo para que produza os efeitos cabíveis.</p>
            <p style="margin-top: 4mm; text-align: right;">' . appEscape($localInstituicao !== '' ? $localInstituicao . ', ' : '') . appEscape($dataPorExtenso) . '.</p>
            <div class="signature">
                <div class="signature-line"></div>
                <strong>' . appEscape($nomeInstituicao) . '</strong><br>
                <span class="muted">Responsável pelo recebimento</span>
            </div>
            <footer class="footer">Documento emitido pelo Sistema Elo · Lançamento nº ' . (int) $recibo['idLancamento'] . '</footer>
        </main>
    </body>
    </html>',
    'UTF-8'
);
$pdf->setPaper('A4', 'portrait');
$pdf->render();
$pdf->stream('recibo-doacao-' . (int) $recibo['idLancamento'] . '.pdf', ['Attachment' => true]);
exit;
