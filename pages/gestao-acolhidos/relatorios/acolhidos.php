<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('gestao-acolhidos.relatorios/acolhidos');
$situacaoSql = "CASE WHEN EXISTS (
    SELECT 1 FROM tbAcolhimento ativo
    WHERE ativo.idAcolhido = pessoa.idAcolhido AND ativo.dataSaida IS NULL
) THEN 'Acolhido' ELSE 'Sem acolhimento ativo' END";
$reportConfig = [
    'file' => 'acolhidos.php',
    'title' => 'Relatório de Acolhidos',
    'pageTitle' => 'Relatório de Acolhidos',
    'singular' => 'acolhido',
    'plural' => 'acolhidos',
    'downloadName' => 'relatorio-de-acolhidos.pdf',
    'select' => 'SELECT pessoa.idAcolhido, pessoa.nomeCompleto, pessoa.dataNascimento, pessoa.sexo,
                         pessoa.nomeMae, pessoa.nomePai, pessoa.moradorRua, pessoa.cidade, pessoa.uf,
                         ' . $situacaoSql . ' AS situacaoAcolhido,
                         pessoa.criadoEm, pessoa.atualizadoEm,
                         criador.idUsuario AS criadoPorId, criador.nomeCompleto AS criadoPor,
                         alterador.idUsuario AS alteradoPorId, alterador.nomeCompleto AS alteradoPor',
    'from' => 'FROM tbAcolhido pessoa
               INNER JOIN tbUsuario criador ON criador.idUsuario = pessoa.idUsuarioCriacao
               LEFT JOIN tbUsuario alterador ON alterador.idUsuario = pessoa.idUsuarioAlteracao',
    'filters' => [
        'nome' => ['label' => 'Nome do acolhido', 'type' => 'text', 'sql' => 'pessoa.nomeCompleto'],
        'mae' => ['label' => 'Nome da mãe', 'type' => 'text', 'sql' => 'pessoa.nomeMae'],
        'nascimento' => ['label' => 'Data de nascimento', 'type' => 'date', 'sql' => 'pessoa.dataNascimento'],
        'sexo' => ['label' => 'Sexo', 'type' => 'option', 'sql' => 'pessoa.sexo', 'options' => [
            'Feminino' => 'Feminino', 'Masculino' => 'Masculino', 'Outro' => 'Outro', 'Não informado' => 'Não informado',
        ]],
        'situacao' => ['label' => 'Situação do acolhimento', 'type' => 'option', 'sql' => $situacaoSql, 'options' => [
            'Acolhido' => 'Com acolhimento ativo', 'Sem acolhimento ativo' => 'Sem acolhimento ativo',
        ]],
    ],
    'columns' => [
        'id' => ['label' => 'ID', 'sql' => 'pessoa.idAcolhido', 'value' => 'idAcolhido'],
        'nome' => ['label' => 'Nome completo', 'sql' => 'pessoa.nomeCompleto', 'value' => 'nomeCompleto'],
        'nascimento' => ['label' => 'Data de nascimento', 'sql' => 'pessoa.dataNascimento', 'value' => 'dataNascimento', 'format' => 'date'],
        'sexo' => ['label' => 'Sexo', 'sql' => 'pessoa.sexo', 'value' => 'sexo'],
        'mae' => ['label' => 'Nome da mãe', 'sql' => 'pessoa.nomeMae', 'value' => 'nomeMae'],
        'pai' => ['label' => 'Nome do pai', 'sql' => 'pessoa.nomePai', 'value' => 'nomePai'],
        'moradorRua' => ['label' => 'Morador de rua', 'sql' => 'pessoa.moradorRua', 'value' => 'moradorRua', 'format' => 'boolean'],
        'cidade' => ['label' => 'Cidade', 'sql' => 'pessoa.cidade', 'value' => 'cidade'],
        'uf' => ['label' => 'UF', 'sql' => 'pessoa.uf', 'value' => 'uf'],
        'situacao' => ['label' => 'Situação', 'sql' => $situacaoSql, 'value' => 'situacaoAcolhido'],
        'criadoEm' => ['label' => 'Data do cadastro', 'sql' => 'pessoa.criadoEm', 'value' => 'criadoEm', 'format' => 'datetime'],
        'criadoPor' => ['label' => 'Criado por', 'sql' => 'criador.idUsuario', 'value' => 'criadoPor', 'format' => 'id-name', 'idValue' => 'criadoPorId'],
        'atualizadoEm' => ['label' => 'Última alteração', 'sql' => 'pessoa.atualizadoEm', 'value' => 'atualizadoEm', 'format' => 'datetime'],
        'alteradoPor' => ['label' => 'Alterado por', 'sql' => 'alterador.idUsuario', 'value' => 'alteradoPor', 'format' => 'id-name', 'idValue' => 'alteradoPorId'],
    ],
];
require __DIR__ . '/../../../components/gestao-acolhidos/relatorio-engine.php';
