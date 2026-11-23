<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('gestao-acolhidos.relatorios/leitos');
$ocupacaoSql = "COALESCE(
    (SELECT atual.nomeCompleto
     FROM tbAcomodacaoAcolhido acomodacao
     INNER JOIN tbAcolhimento admissao ON admissao.idAcolhimento = acomodacao.idAcolhimento
     INNER JOIN tbAcolhido atual ON atual.idAcolhido = admissao.idAcolhido
     WHERE acomodacao.idLeito = leito.idLeito AND acomodacao.fimEm IS NULL
     LIMIT 1),
    'Disponível'
)";
$reportConfig = [
    'file' => 'leitos.php',
    'title' => 'Relatório de Leitos',
    'pageTitle' => 'Relatório de Leitos',
    'singular' => 'leito',
    'plural' => 'leitos',
    'downloadName' => 'relatorio-de-leitos.pdf',
    'select' => 'SELECT leito.idLeito, bloco.nomeBloco, quarto.identificacaoQuarto,
                         leito.identificacaoLeito, leito.statusLeito, ' . $ocupacaoSql . ' AS ocupacaoAtual,
                         leito.criadoEm, leito.atualizadoEm,
                         criador.idUsuario AS criadoPorId, criador.nomeCompleto AS criadoPor,
                         alterador.idUsuario AS alteradoPorId, alterador.nomeCompleto AS alteradoPor',
    'from' => 'FROM tbLeito leito
               INNER JOIN tbQuarto quarto ON quarto.idQuarto = leito.idQuarto
               INNER JOIN tbBloco bloco ON bloco.idBloco = quarto.idBloco
               INNER JOIN tbUsuario criador ON criador.idUsuario = leito.idUsuarioCriacao
               LEFT JOIN tbUsuario alterador ON alterador.idUsuario = leito.idUsuarioAlteracao',
    'filters' => [
        'bloco' => ['label' => 'Bloco', 'type' => 'text', 'sql' => 'bloco.nomeBloco'],
        'quarto' => ['label' => 'Quarto', 'type' => 'text', 'sql' => 'quarto.identificacaoQuarto'],
        'leito' => ['label' => 'Leito', 'type' => 'text', 'sql' => 'leito.identificacaoLeito'],
        'status' => ['label' => 'Situação do leito', 'type' => 'option', 'sql' => 'leito.statusLeito', 'options' => [
            'Ativo' => 'Ativo', 'Manutenção' => 'Manutenção', 'Inativo' => 'Inativo',
        ]],
    ],
    'columns' => [
        'id' => ['label' => 'ID do leito', 'sql' => 'leito.idLeito', 'value' => 'idLeito'],
        'bloco' => ['label' => 'Bloco', 'sql' => 'bloco.nomeBloco', 'value' => 'nomeBloco'],
        'quarto' => ['label' => 'Quarto', 'sql' => 'quarto.identificacaoQuarto', 'value' => 'identificacaoQuarto'],
        'leito' => ['label' => 'Leito', 'sql' => 'leito.identificacaoLeito', 'value' => 'identificacaoLeito'],
        'ocupacao' => ['label' => 'Ocupação atual', 'sql' => $ocupacaoSql, 'value' => 'ocupacaoAtual'],
        'status' => ['label' => 'Situação', 'sql' => 'leito.statusLeito', 'value' => 'statusLeito'],
        'criadoEm' => ['label' => 'Criado em', 'sql' => 'leito.criadoEm', 'value' => 'criadoEm', 'format' => 'datetime'],
        'criadoPor' => ['label' => 'Criado por', 'sql' => 'criador.idUsuario', 'value' => 'criadoPor', 'format' => 'id-name', 'idValue' => 'criadoPorId'],
        'atualizadoEm' => ['label' => 'Última alteração', 'sql' => 'leito.atualizadoEm', 'value' => 'atualizadoEm', 'format' => 'datetime'],
        'alteradoPor' => ['label' => 'Alterado por', 'sql' => 'alterador.idUsuario', 'value' => 'alteradoPor', 'format' => 'id-name', 'idValue' => 'alteradoPorId'],
    ],
];
require __DIR__ . '/../../../components/gestao-acolhidos/relatorio-engine.php';
