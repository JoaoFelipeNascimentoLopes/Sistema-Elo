<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('gestao-acolhidos.relatorios/acolhimentos');
$statusSql = "CASE WHEN admissao.dataSaida IS NULL THEN 'Em andamento' ELSE 'Encerrado' END";
$reportConfig = [
    'file' => 'acolhimentos.php',
    'title' => 'Relatório de Acolhimentos',
    'pageTitle' => 'Relatório de Acolhimentos',
    'singular' => 'acolhimento',
    'plural' => 'acolhimentos',
    'downloadName' => 'relatorio-de-acolhimentos.pdf',
    'select' => 'SELECT admissao.idAcolhimento, pessoa.idAcolhido, pessoa.nomeCompleto,
                         admissao.dataEntrada, admissao.dataSaida, ' . $statusSql . ' AS situacao,
                         admissao.motivoEntrada, admissao.motivoSaida, admissao.inventarioPertences,
                         admissao.responsavelJudicial, admissao.parentescoResponsavel,
                         admissao.telefoneResponsavel, admissao.processoJudicial, admissao.observacoesGerais,
                         admissao.criadoEm, admissao.atualizadoEm,
                         criador.idUsuario AS criadoPorId, criador.nomeCompleto AS criadoPor,
                         alterador.idUsuario AS alteradoPorId, alterador.nomeCompleto AS alteradoPor',
    'from' => 'FROM tbAcolhimento admissao
               INNER JOIN tbAcolhido pessoa ON pessoa.idAcolhido = admissao.idAcolhido
               INNER JOIN tbUsuario criador ON criador.idUsuario = admissao.idUsuarioCriacao
               LEFT JOIN tbUsuario alterador ON alterador.idUsuario = admissao.idUsuarioAlteracao',
    'filters' => [
        'acolhido' => ['label' => 'Nome do acolhido', 'type' => 'text', 'sql' => 'pessoa.nomeCompleto'],
        'entrada' => ['label' => 'Período de entrada', 'type' => 'date', 'sql' => 'admissao.dataEntrada'],
        'saida' => ['label' => 'Período de saída', 'type' => 'date', 'sql' => 'admissao.dataSaida'],
        'situacao' => ['label' => 'Situação', 'type' => 'option', 'sql' => $statusSql, 'options' => [
            'Em andamento' => 'Em andamento', 'Encerrado' => 'Encerrado',
        ]],
    ],
    'columns' => [
        'id' => ['label' => 'ID do acolhimento', 'sql' => 'admissao.idAcolhimento', 'value' => 'idAcolhimento'],
        'acolhido' => ['label' => 'Acolhido', 'sql' => 'pessoa.nomeCompleto', 'value' => 'nomeCompleto'],
        'idAcolhido' => ['label' => 'ID do acolhido', 'sql' => 'pessoa.idAcolhido', 'value' => 'idAcolhido'],
        'entrada' => ['label' => 'Data de entrada', 'sql' => 'admissao.dataEntrada', 'value' => 'dataEntrada', 'format' => 'date'],
        'saida' => ['label' => 'Data de saída', 'sql' => 'admissao.dataSaida', 'value' => 'dataSaida', 'format' => 'date'],
        'situacao' => ['label' => 'Situação', 'sql' => $statusSql, 'value' => 'situacao'],
        'motivoEntrada' => ['label' => 'Motivo da entrada', 'sql' => 'admissao.motivoEntrada', 'value' => 'motivoEntrada'],
        'motivoSaida' => ['label' => 'Motivo da saída', 'sql' => 'admissao.motivoSaida', 'value' => 'motivoSaida'],
        'inventario' => ['label' => 'Inventário de pertences', 'sql' => 'admissao.inventarioPertences', 'value' => 'inventarioPertences'],
        'responsavel' => ['label' => 'Responsável judicial', 'sql' => 'admissao.responsavelJudicial', 'value' => 'responsavelJudicial'],
        'parentesco' => ['label' => 'Parentesco do responsável', 'sql' => 'admissao.parentescoResponsavel', 'value' => 'parentescoResponsavel'],
        'telefone' => ['label' => 'Telefone do responsável', 'sql' => 'admissao.telefoneResponsavel', 'value' => 'telefoneResponsavel'],
        'processo' => ['label' => 'Processo judicial', 'sql' => 'admissao.processoJudicial', 'value' => 'processoJudicial'],
        'observacoes' => ['label' => 'Observações gerais', 'sql' => 'admissao.observacoesGerais', 'value' => 'observacoesGerais'],
        'criadoEm' => ['label' => 'Criado em', 'sql' => 'admissao.criadoEm', 'value' => 'criadoEm', 'format' => 'datetime'],
        'criadoPor' => ['label' => 'Criado por', 'sql' => 'criador.idUsuario', 'value' => 'criadoPor', 'format' => 'id-name', 'idValue' => 'criadoPorId'],
        'atualizadoEm' => ['label' => 'Última alteração', 'sql' => 'admissao.atualizadoEm', 'value' => 'atualizadoEm', 'format' => 'datetime'],
        'alteradoPor' => ['label' => 'Alterado por', 'sql' => 'alterador.idUsuario', 'value' => 'alteradoPor', 'format' => 'id-name', 'idValue' => 'alteradoPorId'],
    ],
];
require __DIR__ . '/../../../components/gestao-acolhidos/relatorio-engine.php';
