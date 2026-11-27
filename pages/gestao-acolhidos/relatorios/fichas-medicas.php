<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('gestao-acolhidos.relatorios/fichas-medicas');
$pdo = appPdo();
$baseUrl = appBaseUrl();
$urlPesquisaAcolhidos = $baseUrl . '/pages/gestao-acolhidos/cadastros/acolhidos.php';
$erro = '';
$idInput = $_GET['idAcolhido'] ?? null;
$idAcolhido = $idInput === null ? null : (is_scalar($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false);
$acolhido = null;
$fichas = [];

if ($idAcolhido === null) {
    header('Location: ' . appTabUrl($urlPesquisaAcolhidos));
    exit;
}

$formatarData = static function (?string $data): string {
    if ($data === null || $data === '') {
        return 'Não informado';
    }

    $timestamp = strtotime($data);

    return $timestamp === false ? 'Não informado' : date('d/m/Y', $timestamp);
};

if ($idAcolhido !== null) {
    if ($idAcolhido === false || $idAcolhido < 1) {
        $erro = 'O acolhido selecionado é inválido.';
    } else {
        $acolhidoStatement = $pdo->prepare(
            'SELECT idAcolhido, nomeCompleto, cpf, rg, dataNascimento, sexo, caminhoFoto,
                    nomeMae, maeDesconhecida, nomePai, paiDesconhecido,
                    moradorRua, localMoradia, cep, logradouro, numero,
                    complemento, bairro, cidade, uf
             FROM tbAcolhido
             WHERE idAcolhido = :idAcolhido
             LIMIT 1'
        );
        $acolhidoStatement->execute(['idAcolhido' => $idAcolhido]);
        $acolhido = $acolhidoStatement->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($acolhido === null) {
            $erro = 'O acolhido selecionado não foi encontrado.';
        } else {
            $partesEndereco = [];
            if ((int) $acolhido['moradorRua'] === 1) {
                $partesEndereco[] = trim((string) ($acolhido['localMoradia'] ?? ''));
            } else {
                $logradouro = trim((string) ($acolhido['logradouro'] ?? ''));
                $numero = trim((string) ($acolhido['numero'] ?? ''));
                if ($logradouro !== '') {
                    $partesEndereco[] = $logradouro . ($numero !== '' ? ', nº ' . $numero : '');
                }
                foreach (['complemento', 'bairro'] as $campoEndereco) {
                    $valorEndereco = trim((string) ($acolhido[$campoEndereco] ?? ''));
                    if ($valorEndereco !== '') {
                        $partesEndereco[] = $valorEndereco;
                    }
                }
                $cidade = trim((string) ($acolhido['cidade'] ?? ''));
                $uf = trim((string) ($acolhido['uf'] ?? ''));
                if ($cidade !== '' || $uf !== '') {
                    $partesEndereco[] = $cidade . ($cidade !== '' && $uf !== '' ? '/' : '') . $uf;
                }
                $cep = (string) preg_replace('/^(\d{5})(\d{3})$/', '$1-$2', (string) ($acolhido['cep'] ?? ''));
                if ($cep !== '') {
                    $partesEndereco[] = 'CEP ' . $cep;
                }
            }
            $acolhido['enderecoFormatado'] = implode(' · ', array_filter($partesEndereco, static fn(string $parte): bool => $parte !== ''));

            $cpf = (string) preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', (string) ($acolhido['cpf'] ?? ''));
            $acolhido['cpfFormatado'] = $cpf !== '' ? $cpf : 'Não informado';

            $fichasStatement = $pdo->prepare(
                'SELECT idFichaMedica, tipoSanguineo, alergias, dependencias,
                        relevanciasClinicas, dataRegistro, criadoEm, atualizadoEm
                 FROM tbFichaMedica
                 WHERE idAcolhido = :idAcolhido
                 ORDER BY dataRegistro DESC, idFichaMedica DESC'
            );
            $fichasStatement->execute(['idAcolhido' => $idAcolhido]);
            $fichas = $fichasStatement->fetchAll(PDO::FETCH_ASSOC);

            try {
                foreach ($fichas as &$ficha) {
                    foreach (['alergias', 'dependencias', 'relevanciasClinicas'] as $campo) {
                        $valores = json_decode((string) $ficha[$campo], true, 512, JSON_THROW_ON_ERROR);
                        if (!is_array($valores)) {
                            throw new UnexpectedValueException('O conteúdo da ficha não é uma lista.');
                        }
                        foreach ($valores as $valor) {
                            if (!is_string($valor)) {
                                throw new UnexpectedValueException('O conteúdo da ficha contém um valor inválido.');
                            }
                        }
                        $ficha[$campo] = $valores;
                    }
                }
                unset($ficha);
            } catch (JsonException | UnexpectedValueException $exception) {
                unset($ficha);
                error_log('Falha ao preparar a impressão da ficha médica do acolhido ' . $idAcolhido . ': ' . $exception->getMessage());
                $erro = 'Não foi possível preparar a impressão porque uma ficha médica contém dados inválidos.';
                $fichas = [];
            }
        }
    }
}
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/tailwind-config.js'); ?>"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm;
        }
        @media print {
            html,
            body {
                display: block !important;
                width: 100% !important;
                height: auto !important;
                min-height: 0 !important;
                overflow: visible !important;
                background: #fff !important;
            }
            body > nav,
            [id$="Modal"],
            [data-forced-password-modal],
            [data-print-hidden],
            [role="alert"],
            aside {
                display: none !important;
            }
            body > div {
                display: block !important;
                height: auto !important;
                overflow: visible !important;
            }
            main {
                display: block !important;
                height: auto !important;
                overflow: visible !important;
                padding: 0 !important;
                background: #fff !important;
            }
            [data-medical-sheet] {
                width: 100% !important;
                max-width: none !important;
                border: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }
            [data-medical-record] {
                break-inside: avoid;
                page-break-inside: avoid;
            }
            [data-resident-info-grid] {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            [data-handwritten-notes] {
                break-inside: avoid;
                page-break-inside: avoid;
            }
            [data-handwritten-line] {
                height: auto !important;
                min-height: 9mm;
            }
        }
    </style>
</head>
<body class="flex h-screen flex-col overflow-hidden bg-gray-100 font-sans">
    <?php include __DIR__ . '/../../../components/gestao-acolhidos/navbar-gestaoacolhidos.php'; ?>
    <div class="flex flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../../components/gestao-acolhidos/sidebar-gestaoacolhidos.php'; ?>
        <main class="flex-1 overflow-y-auto bg-gray-50 p-4 md:p-6">
            <div data-print-hidden class="mb-5">
                <h1 class="text-2xl font-bold text-gray-800">Ficha do Acolhido</h1>
            </div>
            <?php if ($erro !== ''): ?>
                <p role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"><?php echo appEscape($erro); ?></p>
            <?php endif; ?>

            <?php if ($acolhido !== null && $erro === ''): ?>
                <div data-print-hidden class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <a href="<?php echo appEscape(appTabUrl($urlPesquisaAcolhidos)); ?>" class="inline-flex items-center gap-2 rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i class="ph ph-arrow-left" aria-hidden="true"></i> Voltar à pesquisa de acolhidos
                    </a>
                    <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded bg-elo-teal px-4 py-2 text-sm font-semibold text-white hover:bg-elo-green">
                        <i class="ph ph-printer" aria-hidden="true"></i> Imprimir ficha
                    </button>
                </div>
                <article data-medical-sheet class="mx-auto max-w-5xl rounded-xl border border-gray-200 bg-white p-5 shadow-sm md:p-8">
                    <header class="mb-6 border-b-2 border-elo-teal pb-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-elo-teal">Sistema Elo · Gestão de Acolhidos</p>
                                <h2 class="mt-2 text-2xl font-bold uppercase tracking-wide text-gray-900">Ficha do Acolhido</h2>
                                <p class="mt-1 text-sm text-gray-500">Identificação, dados cadastrais e histórico médico</p>
                            </div>
                            <div class="rounded-lg bg-elo-teal/5 px-4 py-3 text-right">
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Fichas registradas</p>
                                <p class="text-2xl font-bold text-elo-teal"><?php echo count($fichas); ?></p>
                            </div>
                        </div>
                    </header>

                    <section class="mb-6 rounded-lg border border-gray-300 p-4">
                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Informações do acolhido</h3>
                        <div class="mb-3 flex items-start gap-3">
                            <?php if (!empty($acolhido['caminhoFoto'])): ?>
                                <img src="<?php echo appEscape(appTabUrl($baseUrl . '/auth/acolhido-foto.php?id=' . (int) $acolhido['idAcolhido'])); ?>" alt="Foto de <?php echo appEscape($acolhido['nomeCompleto']); ?>" class="h-28 w-20 shrink-0 rounded-md border border-gray-200 object-cover" data-resident-photo>
                            <?php endif; ?>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-gray-500">Acolhido</p>
                                <p class="font-semibold text-gray-900"><?php echo (int) $acolhido['idAcolhido']; ?> - <?php echo appEscape($acolhido['nomeCompleto']); ?></p>
                                <div class="mt-3 grid grid-cols-2 gap-3">
                                    <div>
                                        <p class="text-xs text-gray-500">Data de nascimento</p>
                                        <p class="font-medium text-gray-800"><?php echo appEscape($formatarData($acolhido['dataNascimento'])); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Sexo</p>
                                        <p class="font-medium text-gray-800"><?php echo appEscape($acolhido['sexo']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div data-resident-info-grid class="grid gap-x-5 gap-y-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <p class="text-xs text-gray-500">CPF</p>
                                <p class="font-medium text-gray-800"><?php echo appEscape($acolhido['cpfFormatado']); ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">RG</p>
                                <p class="font-medium text-gray-800"><?php echo appEscape($acolhido['rg'] ?: 'Não informado'); ?></p>
                            </div>
                            <div class="sm:col-span-2">
                                <p class="text-xs text-gray-500">Nome da mãe</p>
                                <p class="font-medium text-gray-800"><?php echo appEscape(!empty($acolhido['maeDesconhecida']) ? 'Desconhecida / não consta nos documentos' : ($acolhido['nomeMae'] ?: 'Não informado')); ?></p>
                            </div>
                            <div class="sm:col-span-2">
                                <p class="text-xs text-gray-500">Nome do pai</p>
                                <p class="font-medium text-gray-800"><?php echo appEscape(!empty($acolhido['paiDesconhecido']) ? 'Desconhecido / não consta nos documentos' : ($acolhido['nomePai'] ?: 'Não informado')); ?></p>
                            </div>
                            <div class="sm:col-span-2 lg:col-span-4">
                                <p class="text-xs text-gray-500"><?php echo (int) $acolhido['moradorRua'] === 1 ? 'Local de referência' : 'Endereço'; ?></p>
                                <p class="font-medium text-gray-800"><?php echo appEscape($acolhido['enderecoFormatado'] !== '' ? $acolhido['enderecoFormatado'] : 'Não informado'); ?></p>
                            </div>
                        </div>
                    </section>

                    <?php if ($fichas): ?>
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Histórico clínico</h3>
                            <p class="text-xs text-gray-500">Ordenado da ficha mais recente para a mais antiga</p>
                        </div>
                        <div class="space-y-4">
                            <?php foreach ($fichas as $indice => $ficha): ?>
                                <section data-medical-record class="rounded-lg border border-gray-200 p-4 md:p-5">
                                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-elo-teal">Registro <?php echo count($fichas) - $indice; ?></p>
                                            <h4 class="text-lg font-semibold text-gray-900">Ficha de <?php echo appEscape($formatarData($ficha['dataRegistro'])); ?></h4>
                                        </div>
                                        <div class="rounded-md border border-elo-teal/20 bg-elo-teal/5 px-3 py-2">
                                            <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Tipo sanguíneo</p>
                                            <p class="text-lg font-bold text-elo-teal"><?php echo appEscape($ficha['tipoSanguineo']); ?></p>
                                        </div>
                                    </div>
                                    <div class="grid gap-4 md:grid-cols-3">
                                        <?php foreach ([
                                            'alergias' => ['label' => 'Alergias', 'icon' => 'ph-warning-circle'],
                                            'dependencias' => ['label' => 'Dependências', 'icon' => 'ph-first-aid-kit'],
                                            'relevanciasClinicas' => ['label' => 'Relevâncias clínicas', 'icon' => 'ph-heartbeat'],
                                        ] as $campo => $configuracao): ?>
                                            <div class="rounded-lg bg-gray-50 p-3">
                                                <h5 class="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-800">
                                                    <i class="ph <?php echo appEscape($configuracao['icon']); ?> text-lg" aria-hidden="true"></i>
                                                    <?php echo appEscape($configuracao['label']); ?>
                                                </h5>
                                                <?php if ($ficha[$campo]): ?>
                                                    <ul class="space-y-1 text-sm text-gray-700">
                                                        <?php foreach ($ficha[$campo] as $valor): ?>
                                                            <li class="flex gap-2"><span class="text-elo-teal" aria-hidden="true">•</span><span><?php echo appEscape($valor); ?></span></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else: ?>
                                                    <p class="text-sm italic text-gray-500">Não informado</p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="mt-3 text-right text-xs text-gray-500">Ficha registrada em <?php echo appEscape(date('d/m/Y H:i', strtotime($ficha['criadoEm']))); ?></p>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500">
                            Nenhuma ficha médica foi registrada para este acolhido.
                        </p>
                    <?php endif; ?>

                    <section data-handwritten-notes class="mt-6">
                        <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-700">Anotações</h3>
                        <div class="space-y-2" aria-label="Linhas para anotações manuscritas">
                            <?php for ($linha = 0; $linha < 7; $linha++): ?>
                                <div data-handwritten-line class="h-7 border-b border-gray-400"></div>
                            <?php endfor; ?>
                        </div>
                    </section>
                </article>
            <?php else: ?>
                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:p-6">
                    <a href="<?php echo appEscape(appTabUrl($urlPesquisaAcolhidos)); ?>" class="inline-flex items-center gap-2 rounded border border-elo-teal px-4 py-2 text-sm font-medium text-elo-teal hover:bg-elo-teal/5">
                        <i class="ph ph-arrow-left" aria-hidden="true"></i> Voltar à pesquisa de acolhidos
                    </a>
                </section>
            <?php endif; ?>
        </main>
    </div>
    <script src="<?php echo appEscape($baseUrl . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
</body>
</html>
