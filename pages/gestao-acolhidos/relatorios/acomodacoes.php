<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('gestao-acolhidos.relatorios/acomodacoes');
$situacaoSql = "CASE WHEN acomodacao.fimEm IS NULL THEN 'Atual' ELSE 'Encerrada' END";
$reportConfig = [
    'file' => 'acomodacoes.php',
    'title' => 'Relatório de Acomodações',
    'pageTitle' => 'Relatório de Acomodações',
    'singular' => 'acomodação',
    'plural' => 'acomodações',
    'downloadName' => 'relatorio-de-acomodacoes.pdf',
    'select' => 'SELECT acomodacao.idAcomodacao, admissao.idAcolhimento, pessoa.nomeCompleto,
                         acomodacao.blocoSnapshot, acomodacao.quartoSnapshot, acomodacao.leitoSnapshot,
                         acomodacao.inicioEm, acomodacao.fimEm, ' . $situacaoSql . ' AS situacao,
                         acomodacao.observacoes, acomodacao.criadoEm, acomodacao.atualizadoEm,
                         criador.idUsuario AS criadoPorId, criador.nomeCompleto AS criadoPor,
                         alterador.idUsuario AS alteradoPorId, alterador.nomeCompleto AS alteradoPor',
    'from' => 'FROM tbAcomodacaoAcolhido acomodacao
               INNER JOIN tbAcolhimento admissao ON admissao.idAcolhimento = acomodacao.idAcolhimento
               INNER JOIN tbAcolhido pessoa ON pessoa.idAcolhido = admissao.idAcolhido
               INNER JOIN tbUsuario criador ON criador.idUsuario = acomodacao.idUsuarioCriacao
               LEFT JOIN tbUsuario alterador ON alterador.idUsuario = acomodacao.idUsuarioAlteracao',
    'filters' => [
        'acolhido' => ['label' => 'Nome do acolhido', 'type' => 'text', 'sql' => 'pessoa.nomeCompleto'],
        'bloco' => ['label' => 'Bloco', 'type' => 'text', 'sql' => 'acomodacao.blocoSnapshot'],
        'inicio' => ['label' => 'Período de início', 'type' => 'date', 'sql' => 'acomodacao.inicioEm'],
        'situacao' => ['label' => 'Situação', 'type' => 'option', 'sql' => $situacaoSql, 'options' => [
            'Atual' => 'Atual', 'Encerrada' => 'Encerrada',
        ]],
    ],
    'columns' => [
        'id' => ['label' => 'ID da acomodação', 'sql' => 'acomodacao.idAcomodacao', 'value' => 'idAcomodacao'],
        'acolhimento' => ['label' => 'ID do acolhimento', 'sql' => 'admissao.idAcolhimento', 'value' => 'idAcolhimento'],
        'acolhido' => ['label' => 'Acolhido', 'sql' => 'pessoa.nomeCompleto', 'value' => 'nomeCompleto'],
        'bloco' => ['label' => 'Bloco', 'sql' => 'acomodacao.blocoSnapshot', 'value' => 'blocoSnapshot'],
        'quarto' => ['label' => 'Quarto', 'sql' => 'acomodacao.quartoSnapshot', 'value' => 'quartoSnapshot'],
        'leito' => ['label' => 'Leito', 'sql' => 'acomodacao.leitoSnapshot', 'value' => 'leitoSnapshot'],
        'inicio' => ['label' => 'Início', 'sql' => 'acomodacao.inicioEm', 'value' => 'inicioEm', 'format' => 'datetime'],
        'fim' => ['label' => 'Fim', 'sql' => 'acomodacao.fimEm', 'value' => 'fimEm', 'format' => 'datetime'],
        'situacao' => ['label' => 'Situação', 'sql' => $situacaoSql, 'value' => 'situacao'],
        'observacoes' => ['label' => 'Observações', 'sql' => 'acomodacao.observacoes', 'value' => 'observacoes'],
        'criadoEm' => ['label' => 'Criado em', 'sql' => 'acomodacao.criadoEm', 'value' => 'criadoEm', 'format' => 'datetime'],
        'criadoPor' => ['label' => 'Criado por', 'sql' => 'criador.idUsuario', 'value' => 'criadoPor', 'format' => 'id-name', 'idValue' => 'criadoPorId'],
        'atualizadoEm' => ['label' => 'Última alteração', 'sql' => 'acomodacao.atualizadoEm', 'value' => 'atualizadoEm', 'format' => 'datetime'],
        'alteradoPor' => ['label' => 'Alterado por', 'sql' => 'alterador.idUsuario', 'value' => 'alteradoPor', 'format' => 'id-name', 'idValue' => 'alteradoPorId'],
    ],
];
require __DIR__ . '/../../../components/gestao-acolhidos/relatorio-engine.php';
