<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('gestao-acolhidos.relatorios/blocos');
$reportConfig = [
    'file' => 'blocos.php',
    'title' => 'Relatório de Blocos',
    'pageTitle' => 'Relatório de Blocos',
    'singular' => 'bloco',
    'plural' => 'blocos',
    'downloadName' => 'relatorio-de-blocos.pdf',
    'select' => 'SELECT bloco.idBloco, bloco.nomeBloco, bloco.descricao, bloco.ativo,
                         (SELECT COUNT(*) FROM tbQuarto quarto WHERE quarto.idBloco = bloco.idBloco) AS totalQuartos,
                         bloco.criadoEm, bloco.atualizadoEm,
                         criador.idUsuario AS criadoPorId, criador.nomeCompleto AS criadoPor,
                         alterador.idUsuario AS alteradoPorId, alterador.nomeCompleto AS alteradoPor',
    'from' => 'FROM tbBloco bloco
               INNER JOIN tbUsuario criador ON criador.idUsuario = bloco.idUsuarioCriacao
               LEFT JOIN tbUsuario alterador ON alterador.idUsuario = bloco.idUsuarioAlteracao',
    'filters' => [
        'nome' => ['label' => 'Nome do bloco', 'type' => 'text', 'sql' => 'bloco.nomeBloco'],
        'status' => ['label' => 'Status', 'type' => 'option', 'sql' => 'bloco.ativo', 'options' => ['1' => 'Ativo', '0' => 'Inativo']],
        'criadoEm' => ['label' => 'Data de criação', 'type' => 'date', 'sql' => 'bloco.criadoEm'],
    ],
    'columns' => [
        'id' => ['label' => 'ID do bloco', 'sql' => 'bloco.idBloco', 'value' => 'idBloco'],
        'nome' => ['label' => 'Bloco', 'sql' => 'bloco.nomeBloco', 'value' => 'nomeBloco'],
        'descricao' => ['label' => 'Descrição', 'sql' => 'bloco.descricao', 'value' => 'descricao'],
        'quartos' => ['label' => 'Quantidade de quartos', 'sql' => 'totalQuartos', 'value' => 'totalQuartos'],
        'status' => ['label' => 'Status', 'sql' => 'bloco.ativo', 'value' => 'ativo', 'format' => 'status'],
        'criadoEm' => ['label' => 'Criado em', 'sql' => 'bloco.criadoEm', 'value' => 'criadoEm', 'format' => 'datetime'],
        'criadoPor' => ['label' => 'Criado por', 'sql' => 'criador.idUsuario', 'value' => 'criadoPor', 'format' => 'id-name', 'idValue' => 'criadoPorId'],
        'atualizadoEm' => ['label' => 'Última alteração', 'sql' => 'bloco.atualizadoEm', 'value' => 'atualizadoEm', 'format' => 'datetime'],
        'alteradoPor' => ['label' => 'Alterado por', 'sql' => 'alterador.idUsuario', 'value' => 'alteradoPor', 'format' => 'id-name', 'idValue' => 'alteradoPorId'],
    ],
];
require __DIR__ . '/../../../components/gestao-acolhidos/relatorio-engine.php';
