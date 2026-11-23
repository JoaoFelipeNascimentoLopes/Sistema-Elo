<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('gestao-acolhidos.relatorios/quartos');
$reportConfig = [
    'file' => 'quartos.php',
    'title' => 'Relatório de Quartos',
    'pageTitle' => 'Relatório de Quartos',
    'singular' => 'quarto',
    'plural' => 'quartos',
    'downloadName' => 'relatorio-de-quartos.pdf',
    'select' => 'SELECT quarto.idQuarto, bloco.idBloco, bloco.nomeBloco,
                         quarto.identificacaoQuarto, quarto.descricao, quarto.ativo,
                         (SELECT COUNT(*) FROM tbLeito leito WHERE leito.idQuarto = quarto.idQuarto) AS totalLeitos,
                         quarto.criadoEm, quarto.atualizadoEm,
                         criador.idUsuario AS criadoPorId, criador.nomeCompleto AS criadoPor,
                         alterador.idUsuario AS alteradoPorId, alterador.nomeCompleto AS alteradoPor',
    'from' => 'FROM tbQuarto quarto
               INNER JOIN tbBloco bloco ON bloco.idBloco = quarto.idBloco
               INNER JOIN tbUsuario criador ON criador.idUsuario = quarto.idUsuarioCriacao
               LEFT JOIN tbUsuario alterador ON alterador.idUsuario = quarto.idUsuarioAlteracao',
    'filters' => [
        'bloco' => ['label' => 'Bloco', 'type' => 'text', 'sql' => 'bloco.nomeBloco'],
        'quarto' => ['label' => 'Identificação do quarto', 'type' => 'text', 'sql' => 'quarto.identificacaoQuarto'],
        'status' => ['label' => 'Status', 'type' => 'option', 'sql' => 'quarto.ativo', 'options' => ['1' => 'Ativo', '0' => 'Inativo']],
    ],
    'columns' => [
        'id' => ['label' => 'ID do quarto', 'sql' => 'quarto.idQuarto', 'value' => 'idQuarto'],
        'bloco' => ['label' => 'Bloco', 'sql' => 'bloco.nomeBloco', 'value' => 'nomeBloco'],
        'idBloco' => ['label' => 'ID do bloco', 'sql' => 'bloco.idBloco', 'value' => 'idBloco'],
        'quarto' => ['label' => 'Quarto', 'sql' => 'quarto.identificacaoQuarto', 'value' => 'identificacaoQuarto'],
        'descricao' => ['label' => 'Descrição', 'sql' => 'quarto.descricao', 'value' => 'descricao'],
        'leitos' => ['label' => 'Quantidade de leitos', 'sql' => 'totalLeitos', 'value' => 'totalLeitos'],
        'status' => ['label' => 'Status', 'sql' => 'quarto.ativo', 'value' => 'ativo', 'format' => 'status'],
        'criadoEm' => ['label' => 'Criado em', 'sql' => 'quarto.criadoEm', 'value' => 'criadoEm', 'format' => 'datetime'],
        'criadoPor' => ['label' => 'Criado por', 'sql' => 'criador.idUsuario', 'value' => 'criadoPor', 'format' => 'id-name', 'idValue' => 'criadoPorId'],
        'atualizadoEm' => ['label' => 'Última alteração', 'sql' => 'quarto.atualizadoEm', 'value' => 'atualizadoEm', 'format' => 'datetime'],
        'alteradoPor' => ['label' => 'Alterado por', 'sql' => 'alterador.idUsuario', 'value' => 'alteradoPor', 'format' => 'id-name', 'idValue' => 'alteradoPorId'],
    ],
];
require __DIR__ . '/../../../components/gestao-acolhidos/relatorio-engine.php';
