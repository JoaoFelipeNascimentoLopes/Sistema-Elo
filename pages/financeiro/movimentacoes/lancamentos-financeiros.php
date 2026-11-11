<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('financeiro.movimentacoes/lancamentos-financeiros');
$pdo = appPdo();
$erro = '';
$lancamentoEdicao = null;

$validarData = static function (string $data): bool {
    $date = DateTime::createFromFormat('!Y-m-d', $data);
    return $date !== false && $date->format('Y-m-d') === $data;
};
$converterValor = static function (string $valor): ?string {
    $valor = trim($valor);
    if (!preg_match('/^(?:\d{1,12}|\d{1,3}(?:\.\d{3}){1,3})(?:,\d{1,2})?$/', $valor)) {
        return null;
    }
    return str_replace(',', '.', str_replace('.', '', $valor)) === $valor
        ? $valor
        : str_replace(',', '.', str_replace('.', '', $valor));
};
$valorDigitado = static function (mixed $value): string {
    return is_scalar($value) ? trim((string) $value) : '';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    $acao = is_scalar($_POST['acao'] ?? null) ? (string) $_POST['acao'] : '';

    if ($acao === 'buscarReferencias') {
        header('Content-Type: application/json; charset=UTF-8');
        $type = is_scalar($_POST['tipo'] ?? null) ? (string) $_POST['tipo'] : '';
        $search = is_scalar($_POST['busca'] ?? null) ? trim((string) $_POST['busca']) : '';
        $nature = is_scalar($_POST['natureza'] ?? null) ? (string) $_POST['natureza'] : '';

        if (!in_array($type, ['categoria', 'fonte', 'formaPagamento', 'pessoa'], true)
            || mb_strlen($search, 'UTF-8') > 100
            || ($type === 'pessoa' && !in_array($nature, ['Entrada', 'Saída'], true))) {
            http_response_code(400);
            echo json_encode(['message' => 'A pesquisa informada é inválida.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $items = [];
            $isIdSearch = ctype_digit($search);
            if (mb_strlen($search, 'UTF-8') >= 2 || $isIdSearch) {
                $term = '%' . $search . '%';
                $idPrefix = $isIdSearch ? $search . '%' : null;
                if ($type === 'categoria') {
                    $idCondition = $isIdSearch ? ' OR idCategoria LIKE ?' : '';
                    $idOrder = $isIdSearch ? 'idCategoria LIKE ? DESC, ' : '';
                    $statement = $pdo->prepare(
                        'SELECT idCategoria AS id, nomeCategoria AS nome, naturezaCategoria AS natureza
                         FROM tbCategoriaLancamentos
                         WHERE statusCategoria = 1 AND (nomeCategoria LIKE ? OR naturezaCategoria LIKE ?'
                            . $idCondition . ')
                         ORDER BY ' . $idOrder . 'naturezaCategoria, nomeCategoria LIMIT 10'
                    );
                    $parameters = [$term, $term];
                    if ($idPrefix !== null) {
                        $parameters[] = $idPrefix;
                        $parameters[] = $idPrefix;
                    }
                    $statement->execute($parameters);
                    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $items[] = [
                            'id' => (int) $row['id'],
                            'label' => (int) $row['id'] . ' — ' . $row['natureza'] . ' — ' . $row['nome'],
                            'natureza' => $row['natureza'],
                        ];
                    }
                } elseif ($type === 'fonte') {
                    $idCondition = $isIdSearch ? ' OR idFonte LIKE ?' : '';
                    $idOrder = $isIdSearch ? 'idFonte LIKE ? DESC, ' : '';
                    $statement = $pdo->prepare(
                        'SELECT idFonte AS id, nomeFonte AS nome
                         FROM tbFonteRecurso WHERE statusFonte = 1 AND (nomeFonte LIKE ?'
                            . $idCondition . ')
                         ORDER BY ' . $idOrder . 'nomeFonte LIMIT 10'
                    );
                    $parameters = [$term];
                    if ($idPrefix !== null) {
                        $parameters[] = $idPrefix;
                        $parameters[] = $idPrefix;
                    }
                    $statement->execute($parameters);
                    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $items[] = ['id' => (int) $row['id'], 'label' => (int) $row['id'] . ' — ' . $row['nome']];
                    }
                } elseif ($type === 'formaPagamento') {
                    $idCondition = $isIdSearch ? ' OR idFormaPagamento LIKE ?' : '';
                    $idOrder = $isIdSearch ? 'idFormaPagamento LIKE ? DESC, ' : '';
                    $statement = $pdo->prepare(
                        'SELECT idFormaPagamento AS id, nomeFormaPagamento AS nome
                         FROM tbFormaPagamento WHERE statusFormaPagamento = 1 AND (nomeFormaPagamento LIKE ?'
                            . $idCondition . ')
                         ORDER BY ' . $idOrder . 'nomeFormaPagamento LIMIT 10'
                    );
                    $parameters = [$term];
                    if ($idPrefix !== null) {
                        $parameters[] = $idPrefix;
                        $parameters[] = $idPrefix;
                    }
                    $statement->execute($parameters);
                    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $items[] = ['id' => (int) $row['id'], 'label' => (int) $row['id'] . ' — ' . $row['nome']];
                    }
                } elseif ($nature === 'Saída') {
                    $documentTerm = '%' . ($isIdSearch ? $search : preg_replace('/\D/', '', $search)) . '%';
                    $idCondition = $isIdSearch ? ' OR idFornecedorPrestador LIKE ?' : '';
                    $idOrder = $isIdSearch ? 'idFornecedorPrestador LIKE ? DESC, ' : '';
                    $statement = $pdo->prepare(
                        'SELECT idFornecedorPrestador AS id, nomeRazaoSocial AS nome, documento
                         FROM tbFornecedorPrestador
                         WHERE statusFornecedor = 1 AND (nomeRazaoSocial LIKE ? OR documento LIKE ?'
                            . $idCondition . ')
                         ORDER BY ' . $idOrder . 'nomeRazaoSocial LIMIT 10'
                    );
                    $parameters = [$term, $documentTerm];
                    if ($idPrefix !== null) {
                        $parameters[] = $idPrefix;
                        $parameters[] = $idPrefix;
                    }
                    $statement->execute($parameters);
                    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $items[] = [
                            'id' => (int) $row['id'],
                            'value' => 'fornecedor:' . (int) $row['id'],
                            'label' => (int) $row['id'] . ' — ' . $row['nome'] . ' — ' . $row['documento'],
                        ];
                    }
                } else {
                    $documentTerm = '%' . ($isIdSearch ? $search : preg_replace('/\D/', '', $search)) . '%';
                    $idCondition = $isIdSearch ? ' OR idDoadorContribuinte LIKE ?' : '';
                    $idOrder = $isIdSearch ? 'idDoadorContribuinte LIKE ? DESC, ' : '';
                    $statement = $pdo->prepare(
                        'SELECT idDoadorContribuinte AS id, nomeRazaoSocial AS nome, documento
                         FROM tbDoadorContribuinte
                         WHERE statusDoador = 1 AND (nomeRazaoSocial LIKE ? OR documento LIKE ?'
                            . $idCondition . ')
                         ORDER BY ' . $idOrder . 'nomeRazaoSocial LIMIT 10'
                    );
                    $parameters = [$term, $documentTerm];
                    if ($idPrefix !== null) {
                        $parameters[] = $idPrefix;
                        $parameters[] = $idPrefix;
                    }
                    $statement->execute($parameters);
                    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $items[] = [
                            'id' => (int) $row['id'],
                            'value' => 'doador:' . (int) $row['id'],
                            'label' => (int) $row['id'] . ' — ' . $row['nome'] . ' — ' . $row['documento'],
                        ];
                    }
                }
            }

            echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (PDOException $exception) {
            error_log('Falha ao pesquisar referências do lançamento financeiro: ' . $exception->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Não foi possível pesquisar os cadastros relacionados.'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    try {
        if ($acao === 'salvar') {
            $idInput = $_POST['idLancamento'] ?? 0;
            $idLancamento = is_scalar($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;
            $data = $valorDigitado($_POST['dataLancamento'] ?? '');
            $descricao = trim($valorDigitado($_POST['descricaoLancamento'] ?? ''));
            $valorInput = $valorDigitado($_POST['valorLancamento'] ?? '');
            $valor = $converterValor($valorInput);
            $status = is_scalar($_POST['statusLancamento'] ?? null) ? (string) $_POST['statusLancamento'] : '';
            $categoriaInput = $_POST['idCategoria'] ?? 0;
            $idCategoria = is_scalar($categoriaInput) ? filter_var($categoriaInput, FILTER_VALIDATE_INT) : false;
            $fonteInput = $_POST['idFonte'] ?? '';
            $idFonte = $fonteInput === '' ? null : (is_scalar($fonteInput) ? filter_var($fonteInput, FILTER_VALIDATE_INT) : false);
            $formaInput = $_POST['idFormaPagamento'] ?? '';
            $idFormaPagamento = $formaInput === '' ? null : (is_scalar($formaInput) ? filter_var($formaInput, FILTER_VALIDATE_INT) : false);
            $pessoaInput = $valorDigitado($_POST['pessoaRelacionada'] ?? '');
            $observacoes = trim($valorDigitado($_POST['observacoes'] ?? ''));
            $original = null;

            if ($idLancamento === false || $idLancamento < 0
                || !$validarData($data)
                || $descricao === '' || mb_strlen($descricao, 'UTF-8') > 255
                || $valor === null || (float) $valor <= 0
                || !in_array($status, ['Pendente', 'Efetivado'], true)
                || $idCategoria === false || $idCategoria < 1
                || ($idFonte !== null && ($idFonte === false || $idFonte < 1))
                || ($idFormaPagamento !== null && ($idFormaPagamento === false || $idFormaPagamento < 1))
                || mb_strlen($observacoes, 'UTF-8') > 255) {
                throw new InvalidArgumentException('Confira a data, descrição, valor, categoria, situação e os limites dos campos.');
            }

            if ($idLancamento > 0) {
                $existing = $pdo->prepare(
                    'SELECT l.idCategoria, l.idFonte, l.idFormaPagamento,
                            l.idFornecedorPrestador, l.idDoadorContribuinte, l.statusLancamento,
                            c.naturezaCategoria
                     FROM tbLancamentoFinanceiro l
                     INNER JOIN tbCategoriaLancamentos c ON c.idCategoria = l.idCategoria
                     WHERE l.idLancamento = :id'
                );
                $existing->execute(['id' => $idLancamento]);
                $original = $existing->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($original === null) {
                    throw new InvalidArgumentException('Lançamento financeiro não encontrado.');
                }
            }

            $categoryQuery = $pdo->prepare(
                'SELECT naturezaCategoria, statusCategoria
                 FROM tbCategoriaLancamentos WHERE idCategoria = :id'
            );
            $categoryQuery->execute(['id' => $idCategoria]);
            $category = $categoryQuery->fetch(PDO::FETCH_ASSOC);
            if (!$category || ((int) $category['statusCategoria'] !== 1
                && (int) ($original['idCategoria'] ?? 0) !== $idCategoria)) {
                throw new InvalidArgumentException('Selecione uma categoria ativa.');
            }
            $natureza = (string) $category['naturezaCategoria'];

            foreach ([
                ['table' => 'tbFonteRecurso', 'column' => 'idFonte', 'status' => 'statusFonte', 'value' => $idFonte, 'original' => $original['idFonte'] ?? null, 'label' => 'fonte de recurso'],
                ['table' => 'tbFormaPagamento', 'column' => 'idFormaPagamento', 'status' => 'statusFormaPagamento', 'value' => $idFormaPagamento, 'original' => $original['idFormaPagamento'] ?? null, 'label' => 'forma de pagamento'],
            ] as $reference) {
                if ($reference['value'] === null) {
                    continue;
                }
                $query = $pdo->prepare(
                    'SELECT ' . $reference['status'] . ' FROM ' . $reference['table']
                    . ' WHERE ' . $reference['column'] . ' = :id'
                );
                $query->execute(['id' => $reference['value']]);
                $active = $query->fetchColumn();
                if ($active === false || ((int) $active !== 1 && (int) $reference['original'] !== (int) $reference['value'])) {
                    throw new InvalidArgumentException('Selecione uma ' . $reference['label'] . ' ativa.');
                }
            }

            $idFornecedorPrestador = null;
            $idDoadorContribuinte = null;
            if ($pessoaInput !== '') {
                if (!preg_match('/^(fornecedor|doador):([1-9]\d*)$/', $pessoaInput, $personMatch)) {
                    throw new InvalidArgumentException('Selecione uma empresa ou pessoa física válida.');
                }
                $personType = $personMatch[1];
                $personId = (int) $personMatch[2];
                if (($natureza === 'Saída' && $personType !== 'fornecedor')
                    || ($natureza === 'Entrada' && $personType !== 'doador')) {
                    throw new InvalidArgumentException('Para saídas, selecione quem receberá o pagamento; para entradas, quem realizou o pagamento ou contribuição.');
                }
                if ($personType === 'fornecedor') {
                    $query = $pdo->prepare('SELECT statusFornecedor FROM tbFornecedorPrestador WHERE idFornecedorPrestador = :id');
                    $query->execute(['id' => $personId]);
                    $active = $query->fetchColumn();
                    if ($active === false || ((int) $active !== 1 && (int) ($original['idFornecedorPrestador'] ?? 0) !== $personId)) {
                        throw new InvalidArgumentException('Selecione um fornecedor/prestador ativo.');
                    }
                    $idFornecedorPrestador = $personId;
                } else {
                    $query = $pdo->prepare('SELECT statusDoador FROM tbDoadorContribuinte WHERE idDoadorContribuinte = :id');
                    $query->execute(['id' => $personId]);
                    $active = $query->fetchColumn();
                    if ($active === false || ((int) $active !== 1 && (int) ($original['idDoadorContribuinte'] ?? 0) !== $personId)) {
                        throw new InvalidArgumentException('Selecione um doador/contribuinte ativo.');
                    }
                    $idDoadorContribuinte = $personId;
                }
            }

            $values = [
                'dataLancamento' => $data,
                'descricaoLancamento' => $descricao,
                'valorLancamento' => $valor,
                'statusLancamento' => $status,
                'idCategoria' => $idCategoria,
                'idFonte' => $idFonte,
                'idFormaPagamento' => $idFormaPagamento,
                'idFornecedorPrestador' => $idFornecedorPrestador,
                'idDoadorContribuinte' => $idDoadorContribuinte,
                'observacoes' => $observacoes !== '' ? $observacoes : null,
            ];
            if ($idLancamento > 0) {
                $emitirReciboAutomaticamente = $natureza === 'Entrada'
                    && $status === 'Efetivado'
                    && $idDoadorContribuinte !== null
                    && ($original['statusLancamento'] !== 'Efetivado'
                        || $original['naturezaCategoria'] !== 'Entrada'
                        || (int) $original['idDoadorContribuinte'] !== $idDoadorContribuinte);
                $statement = $pdo->prepare(
                    'UPDATE tbLancamentoFinanceiro
                     SET dataLancamento = :dataLancamento, descricaoLancamento = :descricaoLancamento,
                         valorLancamento = :valorLancamento, statusLancamento = :statusLancamento,
                         idCategoria = :idCategoria, idFonte = :idFonte,
                         idFormaPagamento = :idFormaPagamento,
                         idFornecedorPrestador = :idFornecedorPrestador,
                         idDoadorContribuinte = :idDoadorContribuinte,
                         observacoes = :observacoes, idUsuarioAlteracao = :idUsuarioAlteracao
                     WHERE idLancamento = :idLancamento'
                );
                $statement->execute($values + [
                    'idUsuarioAlteracao' => (int) $usuarioAtual['idUsuario'],
                    'idLancamento' => $idLancamento,
                ]);
                if ($emitirReciboAutomaticamente) {
                    header('Location: ' . appTabUrl('recibo-doacao.php?id=' . $idLancamento));
                    exit;
                }
                header('Location: ' . appTabUrl('lancamentos-financeiros.php?resultado=atualizado'));
                exit;
            }

            $statement = $pdo->prepare(
                'INSERT INTO tbLancamentoFinanceiro
                    (dataLancamento, descricaoLancamento, valorLancamento, statusLancamento,
                     idCategoria, idFonte, idFormaPagamento, idFornecedorPrestador,
                     idDoadorContribuinte, observacoes, idUsuarioCriacao)
                 VALUES
                    (:dataLancamento, :descricaoLancamento, :valorLancamento, :statusLancamento,
                     :idCategoria, :idFonte, :idFormaPagamento, :idFornecedorPrestador,
                     :idDoadorContribuinte, :observacoes, :idUsuarioCriacao)'
            );
            $statement->execute($values + ['idUsuarioCriacao' => (int) $usuarioAtual['idUsuario']]);
            $idNovoLancamento = (int) $pdo->lastInsertId();
            if ($natureza === 'Entrada' && $status === 'Efetivado' && $idDoadorContribuinte !== null) {
                header('Location: ' . appTabUrl('recibo-doacao.php?id=' . $idNovoLancamento));
                exit;
            }
            header('Location: ' . appTabUrl('lancamentos-financeiros.php?resultado=criado'));
            exit;
        }

        if ($acao === 'excluir') {
            $idInput = $_POST['idLancamento'] ?? 0;
            $idLancamento = is_scalar($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;
            if (!$idLancamento || $idLancamento < 1) {
                throw new InvalidArgumentException('Lançamento financeiro inválido.');
            }
            $statement = $pdo->prepare('DELETE FROM tbLancamentoFinanceiro WHERE idLancamento = :id');
            $statement->execute(['id' => $idLancamento]);
            if ($statement->rowCount() === 0) {
                throw new InvalidArgumentException('Lançamento financeiro não encontrado.');
            }
            header('Location: ' . appTabUrl('lancamentos-financeiros.php?resultado=excluido'));
            exit;
        }

        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException $exception) {
        $erro = $exception->getMessage();
        if ($acao === 'salvar') {
            $submittedId = $_POST['idLancamento'] ?? 0;
            $safeId = is_scalar($submittedId) ? filter_var($submittedId, FILTER_VALIDATE_INT) : false;
            $lancamentoEdicao = [
                'idLancamento' => $safeId !== false ? $safeId : 0,
                'dataLancamento' => $valorDigitado($_POST['dataLancamento'] ?? ''),
                'descricaoLancamento' => $valorDigitado($_POST['descricaoLancamento'] ?? ''),
                'valorLancamento' => $valorDigitado($_POST['valorLancamento'] ?? ''),
                'statusLancamento' => $valorDigitado($_POST['statusLancamento'] ?? 'Pendente'),
                'idCategoria' => $valorDigitado($_POST['idCategoria'] ?? ''),
                'idFonte' => $valorDigitado($_POST['idFonte'] ?? ''),
                'idFormaPagamento' => $valorDigitado($_POST['idFormaPagamento'] ?? ''),
                'pessoaRelacionada' => $valorDigitado($_POST['pessoaRelacionada'] ?? ''),
                'observacoes' => $valorDigitado($_POST['observacoes'] ?? ''),
            ];
        }
    } catch (PDOException $exception) {
        error_log('Falha ao salvar lançamento financeiro: ' . $exception->getMessage());
        $errorCode = (int) ($exception->errorInfo[1] ?? 0);
        $erro = $acao === 'excluir' && $errorCode === 1451
            ? 'Não é possível excluir este lançamento porque ele está sendo utilizado.'
            : 'Não foi possível salvar o lançamento financeiro.';
        if ($acao === 'salvar') {
            $submittedId = $_POST['idLancamento'] ?? 0;
            $safeId = is_scalar($submittedId) ? filter_var($submittedId, FILTER_VALIDATE_INT) : false;
            $lancamentoEdicao = [
                'idLancamento' => $safeId !== false ? $safeId : 0,
                'dataLancamento' => $valorDigitado($_POST['dataLancamento'] ?? ''),
                'descricaoLancamento' => $valorDigitado($_POST['descricaoLancamento'] ?? ''),
                'valorLancamento' => $valorDigitado($_POST['valorLancamento'] ?? ''),
                'statusLancamento' => $valorDigitado($_POST['statusLancamento'] ?? 'Pendente'),
                'idCategoria' => $valorDigitado($_POST['idCategoria'] ?? ''),
                'idFonte' => $valorDigitado($_POST['idFonte'] ?? ''),
                'idFormaPagamento' => $valorDigitado($_POST['idFormaPagamento'] ?? ''),
                'pessoaRelacionada' => $valorDigitado($_POST['pessoaRelacionada'] ?? ''),
                'observacoes' => $valorDigitado($_POST['observacoes'] ?? ''),
            ];
        }
    }
}

$idEdicaoInput = $_GET['editar'] ?? null;
$idEdicao = is_scalar($idEdicaoInput) ? filter_var($idEdicaoInput, FILTER_VALIDATE_INT) : false;
if ($lancamentoEdicao === null && $idEdicao) {
    $statement = $pdo->prepare(
        'SELECT l.idLancamento, l.dataLancamento, l.descricaoLancamento, l.valorLancamento,
                l.statusLancamento, l.idCategoria, l.idFonte, l.idFormaPagamento,
                l.idFornecedorPrestador, l.idDoadorContribuinte, l.observacoes,
                l.criadoEm, l.atualizadoEm,
                criador.idUsuario AS criadoPorId, criador.nomeCompleto AS criadoPor,
                alterador.idUsuario AS alteradoPorId, alterador.nomeCompleto AS alteradoPor
         FROM tbLancamentoFinanceiro l
         INNER JOIN tbUsuario criador ON criador.idUsuario = l.idUsuarioCriacao
         LEFT JOIN tbUsuario alterador ON alterador.idUsuario = l.idUsuarioAlteracao
         WHERE l.idLancamento = :id'
    );
    $statement->execute(['id' => $idEdicao]);
    $lancamentoEdicao = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$lancamentoEdicao) {
        $erro = 'Lançamento financeiro não encontrado.';
    } elseif (!empty($lancamentoEdicao['idFornecedorPrestador'])) {
        $lancamentoEdicao['pessoaRelacionada'] = 'fornecedor:' . $lancamentoEdicao['idFornecedorPrestador'];
    } elseif (!empty($lancamentoEdicao['idDoadorContribuinte'])) {
        $lancamentoEdicao['pessoaRelacionada'] = 'doador:' . $lancamentoEdicao['idDoadorContribuinte'];
    }
}

$autocompleteValues = [
    'idCategoria' => ['label' => '', 'natureza' => ''],
    'idFonte' => ['label' => ''],
    'idFormaPagamento' => ['label' => ''],
    'pessoaRelacionada' => ['label' => ''],
];
if ((int) ($lancamentoEdicao['idCategoria'] ?? 0) > 0) {
    $statement = $pdo->prepare(
        'SELECT CONCAT(naturezaCategoria, \' — \', nomeCategoria) AS label, naturezaCategoria, statusCategoria
         FROM tbCategoriaLancamentos WHERE idCategoria = ?'
    );
    $statement->execute([(int) $lancamentoEdicao['idCategoria']]);
    if ($reference = $statement->fetch(PDO::FETCH_ASSOC)) {
        $autocompleteValues['idCategoria'] = [
        'label' => (int) $lancamentoEdicao['idCategoria'] . ' — ' . $reference['label']
            . ((int) $reference['statusCategoria'] === 1 ? '' : ' (Inativa)'),
        'natureza' => $reference['naturezaCategoria'],
    ];
    }
}
foreach ([
    'idFonte' => ['table' => 'tbFonteRecurso', 'id' => 'idFonte', 'name' => 'nomeFonte', 'status' => 'statusFonte'],
    'idFormaPagamento' => ['table' => 'tbFormaPagamento', 'id' => 'idFormaPagamento', 'name' => 'nomeFormaPagamento', 'status' => 'statusFormaPagamento'],
] as $field => $reference) {
    if ((int) ($lancamentoEdicao[$field] ?? 0) < 1) {
        continue;
    }
    $statement = $pdo->prepare(
        'SELECT ' . $reference['name'] . ' AS label, ' . $reference['status'] . ' AS ativo
         FROM ' . $reference['table'] . ' WHERE ' . $reference['id'] . ' = ?'
    );
    $statement->execute([(int) $lancamentoEdicao[$field]]);
    if ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $autocompleteValues[$field]['label'] = (int) $lancamentoEdicao[$field] . ' — ' . $row['label']
            . ((int) $row['ativo'] === 1 ? '' : ' (Inativa)');
    }
}
$pessoaRelacionada = (string) ($lancamentoEdicao['pessoaRelacionada'] ?? '');
if (preg_match('/^(fornecedor|doador):([1-9]\d*)$/', $pessoaRelacionada, $personMatch)) {
    if ($personMatch[1] === 'fornecedor') {
        $statement = $pdo->prepare(
            'SELECT nomeRazaoSocial, documento, statusFornecedor FROM tbFornecedorPrestador
             WHERE idFornecedorPrestador = ?'
        );
        $statement->execute([(int) $personMatch[2]]);
        if ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $autocompleteValues['pessoaRelacionada']['label'] = (int) $personMatch[2] . ' — '
                . $row['nomeRazaoSocial'] . ' — ' . $row['documento']
                . ((int) $row['statusFornecedor'] === 1 ? '' : ' (Inativo)');
        }
    } else {
        $statement = $pdo->prepare(
            'SELECT nomeRazaoSocial, documento, statusDoador FROM tbDoadorContribuinte
             WHERE idDoadorContribuinte = ?'
        );
        $statement->execute([(int) $personMatch[2]]);
        if ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $autocompleteValues['pessoaRelacionada']['label'] = (int) $personMatch[2] . ' — '
                . $row['nomeRazaoSocial'] . ' — ' . $row['documento']
                . ((int) $row['statusDoador'] === 1 ? '' : ' (Inativo)');
        }
    }
}
$lancamentos = $pdo->query(
    'SELECT l.idLancamento, l.idDoadorContribuinte, l.dataLancamento, l.descricaoLancamento, l.valorLancamento,
            l.statusLancamento, c.nomeCategoria, c.naturezaCategoria, f.nomeFonte,
            p.nomeFormaPagamento,
            COALESCE(fp.nomeRazaoSocial, dc.nomeRazaoSocial) AS pessoaRelacionada
     FROM tbLancamentoFinanceiro l
     INNER JOIN tbCategoriaLancamentos c ON c.idCategoria = l.idCategoria
     LEFT JOIN tbFonteRecurso f ON f.idFonte = l.idFonte
     LEFT JOIN tbFormaPagamento p ON p.idFormaPagamento = l.idFormaPagamento
     LEFT JOIN tbFornecedorPrestador fp ON fp.idFornecedorPrestador = l.idFornecedorPrestador
     LEFT JOIN tbDoadorContribuinte dc ON dc.idDoadorContribuinte = l.idDoadorContribuinte
     ORDER BY l.dataLancamento DESC, l.idLancamento DESC'
)->fetchAll(PDO::FETCH_ASSOC);
$totalRecibosDisponiveis = count(array_filter(
    $lancamentos,
    static fn (array $lancamento): bool => $lancamento['naturezaCategoria'] === 'Entrada'
        && $lancamento['statusLancamento'] === 'Efetivado'
        && !empty($lancamento['idDoadorContribuinte'])
));
$resultado = [
    'criado' => 'Lançamento financeiro cadastrado.',
    'atualizado' => 'Lançamento financeiro atualizado.',
    'excluido' => 'Lançamento financeiro excluído.',
][(string) ($_GET['resultado'] ?? '')] ?? '';
$abaInicial = ($lancamentoEdicao || ($erro !== '' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar'))
    ? 'manutencao'
    : 'pesquisa';
$criadoPor = isset($lancamentoEdicao['criadoPorId'])
    ? $lancamentoEdicao['criadoPorId'] . ' - ' . $lancamentoEdicao['criadoPor']
        . ' - ' . date('d/m/Y H:i', strtotime((string) $lancamentoEdicao['criadoEm']))
    : (int) $usuarioAtual['idUsuario'] . ' - ' . $usuarioAtual['nomeCompleto']
        . ' - ' . date('d/m/Y H:i');
$alteradoPor = !empty($lancamentoEdicao['alteradoPorId'])
    ? $lancamentoEdicao['alteradoPorId'] . ' - ' . $lancamentoEdicao['alteradoPor']
        . ' - ' . date('d/m/Y H:i', strtotime((string) $lancamentoEdicao['atualizadoEm']))
    : '—';
$formatarMoeda = static fn (mixed $value): string => 'R$ ' . number_format((float) $value, 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Elo</title>
    <link rel="icon" type="image/png" href="<?php echo appEscape(appBaseUrl() . '/assets/imgs/logo-elo.png'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo appEscape(appBaseUrl() . '/assets/js/tailwind-config.js'); ?>"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="flex h-screen flex-col overflow-hidden bg-gray-100 font-sans">
    <?php include __DIR__ . '/../../../components/financeiro/navbar-financeiro.php'; ?>
    <div class="flex flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../../components/financeiro/sidebar-financeiro.php'; ?>
        <main class="flex-1 overflow-y-auto bg-gray-50 p-4 md:p-6">
            <h1 class="mb-2 text-2xl font-bold text-gray-800">Lançamentos Financeiros</h1>
            <p class="mb-6 text-sm text-gray-600">Registre entradas e saídas. Os valores pendentes podem ser incluídos na visão das metas orçamentárias.</p>
            <?php include __DIR__ . '/../../../components/tabs-pesquisa-manutencao.php'; ?>
            <section id="painel-manutencao" role="tabpanel" aria-labelledby="tab-manutencao" class="mb-8 rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'manutencao' ? '' : 'hidden'; ?>>
                <h2 class="mb-4 text-lg font-semibold"><?php echo !empty($lancamentoEdicao['idLancamento']) ? 'Alterar lançamento' : 'Novo lançamento'; ?></h2>
                <form method="post" data-confirm="Confirma gravar este lançamento?" data-finance-launch-form class="grid gap-5">
                    <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="idLancamento" value="<?php echo (int) ($lancamentoEdicao['idLancamento'] ?? 0); ?>">
                    <fieldset class="grid gap-4 rounded-lg border border-gray-200 p-4 md:grid-cols-2">
                        <legend class="px-2 text-sm font-semibold text-gray-700">1. Dados do lançamento</legend>
                        <label class="text-sm font-medium text-gray-700">Data do lançamento
                            <input type="date" name="dataLancamento" required value="<?php echo appEscape((string) ($lancamentoEdicao['dataLancamento'] ?? date('Y-m-d'))); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Situação
                            <select name="statusLancamento" required class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                                <?php foreach (['Pendente' => 'Pendente', 'Efetivado' => 'Efetivado'] as $value => $label): ?>
                                    <option value="<?php echo appEscape($value); ?>" <?php echo ($lancamentoEdicao['statusLancamento'] ?? 'Pendente') === $value ? 'selected' : ''; ?>><?php echo appEscape($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="text-sm font-medium text-gray-700 md:col-span-2">Descrição
                            <input name="descricaoLancamento" required maxlength="255" value="<?php echo appEscape((string) ($lancamentoEdicao['descricaoLancamento'] ?? '')); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Valor
                            <input name="valorLancamento" data-money-input inputmode="decimal" autocomplete="off" required maxlength="18" value="<?php echo appEscape(isset($lancamentoEdicao['valorLancamento']) ? number_format((float) $lancamentoEdicao['valorLancamento'], 2, ',', '.') : ''); ?>" placeholder="0,00" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                    </fieldset>

                    <fieldset class="grid gap-4 rounded-lg border border-gray-200 p-4 md:grid-cols-2">
                        <legend class="px-2 text-sm font-semibold text-gray-700">2. Classificação e vínculos</legend>
                        <div class="relative" data-reference-search data-reference-type="categoria" data-natureza="<?php echo appEscape($autocompleteValues['idCategoria']['natureza']); ?>">
                            <label for="idCategoriaPesquisa" class="text-sm font-medium text-gray-700">Categoria (define entrada ou saída)</label>
                            <input id="idCategoriaPesquisa" type="search" data-reference-input data-required-reference autocomplete="off" required role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="sugestoes-categoria" value="<?php echo appEscape($autocompleteValues['idCategoria']['label']); ?>" placeholder="Digite para pesquisar uma categoria" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                            <input type="hidden" name="idCategoria" data-reference-id value="<?php echo (int) ($lancamentoEdicao['idCategoria'] ?? 0) ?: ''; ?>">
                            <div id="sugestoes-categoria" data-reference-results role="listbox" class="absolute z-20 mt-1 hidden max-h-60 w-full overflow-y-auto rounded border border-gray-200 bg-white shadow-lg"></div>
                            <p data-reference-message role="status" aria-live="polite" class="mt-1 text-xs text-gray-500">Digite o ID ou ao menos 2 caracteres do nome.</p>
                        </div>
                        <div class="relative" data-reference-search data-reference-type="pessoa" data-natureza="<?php echo appEscape($autocompleteValues['idCategoria']['natureza']); ?>">
                            <label for="pessoaRelacionadaPesquisa" data-counterparty-label class="text-sm font-medium text-gray-700"><?php
                                echo $autocompleteValues['idCategoria']['natureza'] === 'Saída'
                                    ? 'Empresa ou pessoa física que receberá o pagamento (opcional)'
                                    : ($autocompleteValues['idCategoria']['natureza'] === 'Entrada'
                                        ? 'Empresa ou pessoa física que realizou o pagamento (opcional)'
                                        : 'Empresa ou pessoa física do pagamento (opcional)');
                            ?></label>
                            <input id="pessoaRelacionadaPesquisa" type="search" data-reference-input autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="sugestoes-pessoa" value="<?php echo appEscape($autocompleteValues['pessoaRelacionada']['label']); ?>" placeholder="<?php echo $autocompleteValues['idCategoria']['natureza'] === 'Saída' ? 'Pesquise um fornecedor ou prestador' : ($autocompleteValues['idCategoria']['natureza'] === 'Entrada' ? 'Pesquise um doador ou contribuinte' : 'Selecione uma categoria primeiro'); ?>" <?php echo $autocompleteValues['idCategoria']['natureza'] === '' ? 'disabled' : ''; ?> class="mt-1 w-full rounded border border-gray-300 px-3 py-2 disabled:cursor-not-allowed disabled:bg-gray-100">
                            <input type="hidden" name="pessoaRelacionada" data-reference-id value="<?php echo appEscape($pessoaRelacionada); ?>">
                            <div id="sugestoes-pessoa" data-reference-results role="listbox" class="absolute z-20 mt-1 hidden max-h-60 w-full overflow-y-auto rounded border border-gray-200 bg-white shadow-lg"></div>
                            <p data-reference-message data-counterparty-help role="status" aria-live="polite" class="mt-1 text-xs text-gray-500"><?php
                                echo $autocompleteValues['idCategoria']['natureza'] === 'Saída'
                                    ? 'Selecione quem receberá o pagamento (fornecedor/prestador).'
                                    : ($autocompleteValues['idCategoria']['natureza'] === 'Entrada'
                                        ? 'Selecione quem pagou ou realizou a contribuição (doador/contribuinte).'
                                        : 'Selecione primeiro uma categoria para definir quem paga ou recebe.');
                            ?></p>
                        </div>
                        <div class="relative" data-reference-search data-reference-type="fonte">
                            <label for="idFontePesquisa" class="text-sm font-medium text-gray-700">Fonte de recurso (opcional)</label>
                            <input id="idFontePesquisa" type="search" data-reference-input autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="sugestoes-fonte" value="<?php echo appEscape($autocompleteValues['idFonte']['label']); ?>" placeholder="Digite para pesquisar uma fonte" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                            <input type="hidden" name="idFonte" data-reference-id value="<?php echo (int) ($lancamentoEdicao['idFonte'] ?? 0) ?: ''; ?>">
                            <div id="sugestoes-fonte" data-reference-results role="listbox" class="absolute z-20 mt-1 hidden max-h-60 w-full overflow-y-auto rounded border border-gray-200 bg-white shadow-lg"></div>
                            <p data-reference-message role="status" aria-live="polite" class="mt-1 text-xs text-gray-500">Digite o ID ou ao menos 2 caracteres do nome.</p>
                        </div>
                        <div class="relative" data-reference-search data-reference-type="formaPagamento">
                            <label for="idFormaPagamentoPesquisa" class="text-sm font-medium text-gray-700">Forma de pagamento (opcional)</label>
                            <input id="idFormaPagamentoPesquisa" type="search" data-reference-input autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="sugestoes-forma-pagamento" value="<?php echo appEscape($autocompleteValues['idFormaPagamento']['label']); ?>" placeholder="Digite para pesquisar uma forma" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                            <input type="hidden" name="idFormaPagamento" data-reference-id value="<?php echo (int) ($lancamentoEdicao['idFormaPagamento'] ?? 0) ?: ''; ?>">
                            <div id="sugestoes-forma-pagamento" data-reference-results role="listbox" class="absolute z-20 mt-1 hidden max-h-60 w-full overflow-y-auto rounded border border-gray-200 bg-white shadow-lg"></div>
                            <p data-reference-message role="status" aria-live="polite" class="mt-1 text-xs text-gray-500">Digite o ID ou ao menos 2 caracteres do nome.</p>
                        </div>
                    </fieldset>

                    <fieldset class="grid gap-4 rounded-lg border border-gray-200 p-4 md:grid-cols-2">
                        <legend class="px-2 text-sm font-semibold text-gray-700">3. Observações e controle</legend>
                        <label class="text-sm font-medium text-gray-700 md:col-span-2">Observações
                            <textarea name="observacoes" maxlength="255" rows="2" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"><?php echo appEscape((string) ($lancamentoEdicao['observacoes'] ?? '')); ?></textarea>
                        </label>
                        <label class="text-sm font-medium text-gray-700">Criado por
                            <input value="<?php echo appEscape($criadoPor); ?>" disabled class="mt-1 w-full cursor-not-allowed rounded border border-gray-200 bg-gray-100 px-3 py-2 text-gray-600">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Alterado por
                            <input value="<?php echo appEscape($alteradoPor); ?>" disabled class="mt-1 w-full cursor-not-allowed rounded border border-gray-200 bg-gray-100 px-3 py-2 text-gray-600">
                        </label>
                    </fieldset>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 rounded bg-elo-teal px-4 py-2 font-medium text-white hover:bg-elo-green"><i class="ph ph-floppy-disk" aria-hidden="true"></i><?php echo !empty($lancamentoEdicao['idLancamento']) ? 'Salvar alterações' : 'Cadastrar lançamento'; ?></button>
                        <?php if (!empty($lancamentoEdicao['idLancamento'])): ?><a href="lancamentos-financeiros.php#pesquisa" class="inline-flex items-center gap-2 rounded border px-4 py-2 text-gray-700 hover:bg-gray-50">Cancelar</a><?php endif; ?>
                    </div>
                </form>
            </section>

            <section id="painel-pesquisa" role="tabpanel" aria-labelledby="tab-pesquisa" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'pesquisa' ? '' : 'hidden'; ?>>
                <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <h2 class="text-lg font-semibold">Lançamentos cadastrados</h2>
                    <div class="flex flex-wrap gap-2">
                        <input data-filter-input="listaLancamentos" type="search" placeholder="Pesquisar lançamentos..." aria-label="Pesquisar lançamentos financeiros" class="rounded border border-gray-300 px-3 py-2 text-sm">
                        <button type="button" data-tab-open="manutencao" class="rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">+ Novo lançamento</button>
                    </div>
                </div>
                <p class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                    <?php if ($totalRecibosDisponiveis > 0): ?>
                        <?php echo $totalRecibosDisponiveis; ?> lançamento(s) elegível(is) para recibo. Use o botão <strong>Emitir recibo</strong> na coluna Ações.
                    <?php else: ?>
                        O botão <strong>Emitir recibo</strong> aparece na coluna Ações para entradas efetivadas com doador vinculado. Nenhum lançamento da lista atende a esses critérios no momento.
                    <?php endif; ?>
                </p>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1050px] text-left text-sm">
                        <thead class="border-b bg-gray-50 text-gray-600"><tr><th class="p-3">Data</th><th class="p-3">Descrição</th><th class="p-3">Categoria</th><th class="p-3">Empresa/Pessoa Física</th><th class="p-3">Fonte</th><th class="p-3">Forma</th><th class="p-3">Situação</th><th class="p-3 text-right">Valor</th><th class="p-3">Ações</th></tr></thead>
                        <tbody id="listaLancamentos">
                        <?php foreach ($lancamentos as $lancamento): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="whitespace-nowrap p-3"><?php echo appEscape(date('d/m/Y', strtotime($lancamento['dataLancamento']))); ?></td>
                                <td class="p-3 font-medium text-gray-800"><?php echo appEscape($lancamento['descricaoLancamento']); ?></td>
                                <td class="p-3"><?php echo appEscape($lancamento['naturezaCategoria'] . ' — ' . $lancamento['nomeCategoria']); ?></td>
                                <td class="p-3"><?php echo appEscape((string) ($lancamento['pessoaRelacionada'] ?? '')); ?></td>
                                <td class="p-3"><?php echo appEscape((string) ($lancamento['nomeFonte'] ?? '')); ?></td>
                                <td class="p-3"><?php echo appEscape((string) ($lancamento['nomeFormaPagamento'] ?? '')); ?></td>
                                <td class="p-3"><span class="<?php echo $lancamento['statusLancamento'] === 'Efetivado' ? 'text-green-700' : 'text-amber-700'; ?>"><?php echo appEscape($lancamento['statusLancamento']); ?></span></td>
                                <td class="whitespace-nowrap p-3 text-right"><?php echo appEscape($formatarMoeda($lancamento['valorLancamento'])); ?></td>
                                <td class="whitespace-nowrap p-3"><div class="flex items-center gap-3">
                                    <?php if ($lancamento['naturezaCategoria'] === 'Entrada' && $lancamento['statusLancamento'] === 'Efetivado' && !empty($lancamento['idDoadorContribuinte'])): ?>
                                        <a href="<?php echo appEscape(appTabUrl('recibo-doacao.php?id=' . (int) $lancamento['idLancamento'])); ?>" aria-label="Emitir recibo de doação do lançamento <?php echo (int) $lancamento['idLancamento']; ?>" class="inline-flex items-center gap-1.5 rounded border border-emerald-200 px-2.5 py-2 text-xs font-medium text-emerald-800 hover:bg-emerald-50"><i class="ph ph-receipt text-base" aria-hidden="true"></i>Emitir recibo</a>
                                    <?php endif; ?>
                                    <a href="?editar=<?php echo (int) $lancamento['idLancamento']; ?>#manutencao" aria-label="Alterar lançamento <?php echo appEscape($lancamento['descricaoLancamento']); ?>" class="inline-flex h-9 w-9 items-center justify-center rounded border border-elo-teal/20 text-elo-teal hover:bg-elo-teal/10"><i class="ph ph-pencil-simple text-lg" aria-hidden="true"></i></a>
                                    <form method="post" data-confirm="Confirma excluir este lançamento financeiro?"><input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="idLancamento" value="<?php echo (int) $lancamento['idLancamento']; ?>"><button type="submit" aria-label="Excluir lançamento <?php echo appEscape($lancamento['descricaoLancamento']); ?>" class="inline-flex h-9 w-9 items-center justify-center rounded border border-red-200 text-red-600 hover:bg-red-50"><i class="ph ph-trash text-lg" aria-hidden="true"></i></button></form>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!$lancamentos): ?><p class="p-6 text-center text-sm text-gray-500">Nenhum lançamento financeiro cadastrado.</p><?php endif; ?>
                </div>
            </section>
        </main>
    </div>
    <?php $toastSuccess = $resultado; $toastError = $erro; include __DIR__ . '/../../../components/toast.php'; ?>
    <script src="<?php echo appEscape(appBaseUrl() . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
    <script>
        (() => {
            const form = document.querySelector('[data-finance-launch-form]');
            if (!form) return;

            const csrfToken = form.elements.namedItem('csrfToken').value;
            const fallbackTabToken = <?php echo json_encode(appCurrentTabToken(), JSON_THROW_ON_ERROR); ?>;
            const categorySearch = form.querySelector('[data-reference-type="categoria"]');
            const personSearch = form.querySelector('[data-reference-type="pessoa"]');
            const personInput = personSearch.querySelector('[data-reference-input]');
            const personLabel = personSearch.querySelector('[data-counterparty-label]');
            const personHelp = personSearch.querySelector('[data-counterparty-help]');

            const showResults = (search, message) => {
                search.querySelector('[data-reference-results]').classList.remove('hidden');
                search.querySelector('[data-reference-input]').setAttribute('aria-expanded', 'true');
                search.querySelector('[data-reference-message]').textContent = message;
            };
            const closeResults = (search) => {
                search.querySelector('[data-reference-results]').classList.add('hidden');
                const input = search.querySelector('[data-reference-input]');
                input.setAttribute('aria-expanded', 'false');
                input.removeAttribute('aria-activedescendant');
            };
            const clearPerson = () => {
                personSearch.querySelector('[data-reference-id]').value = '';
                personInput.value = '';
            };
            const updatePersonAvailability = () => {
                const nature = categorySearch.dataset.natureza || '';
                personSearch.dataset.natureza = nature;
                personInput.disabled = nature === '';
                if (nature === 'Saída') {
                    personLabel.textContent = 'Empresa ou pessoa física que receberá o pagamento (opcional)';
                    personHelp.textContent = 'Selecione quem receberá o pagamento (fornecedor/prestador).';
                } else if (nature === 'Entrada') {
                    personLabel.textContent = 'Empresa ou pessoa física que realizou o pagamento (opcional)';
                    personHelp.textContent = 'Selecione quem pagou ou realizou a contribuição (doador/contribuinte).';
                } else {
                    personLabel.textContent = 'Empresa ou pessoa física do pagamento (opcional)';
                    personHelp.textContent = 'Selecione primeiro uma categoria para definir quem paga ou recebe.';
                }
                personInput.placeholder = nature === 'Saída'
                    ? 'Pesquise um fornecedor ou prestador'
                    : nature === 'Entrada'
                        ? 'Pesquise um doador ou contribuinte'
                        : 'Selecione uma categoria primeiro';
            };

            form.querySelectorAll('[data-reference-search]').forEach((search) => {
                const input = search.querySelector('[data-reference-input]');
                const hidden = search.querySelector('[data-reference-id]');
                const results = search.querySelector('[data-reference-results]');
                const message = search.querySelector('[data-reference-message]');
                const type = search.dataset.referenceType;
                let debounceTimer;
                let requestController;
                let activeIndex = -1;

                const selectItem = (item) => {
                    input.value = item.label;
                    hidden.value = item.value ?? String(item.id);
                    input.setCustomValidity('');
                    closeResults(search);
                    activeIndex = -1;

                    if (type === 'categoria') {
                        categorySearch.dataset.natureza = item.natureza;
                        updatePersonAvailability();
                        clearPerson();
                    }
                };
                const renderItems = (items) => {
                    results.replaceChildren();
                    activeIndex = -1;
                    if (!items.length) {
                        showResults(search, 'Nenhum cadastro encontrado.');
                        return;
                    }
                    items.forEach((item, index) => {
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.id = `${results.id}-opcao-${index}`;
                        option.className = 'block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-elo-teal/10 focus:bg-elo-teal/10 focus:outline-none';
                        option.setAttribute('role', 'option');
                        option.setAttribute('aria-selected', 'false');
                        option.textContent = item.label;
                        option.addEventListener('pointerdown', (event) => {
                            event.preventDefault();
                            selectItem(item);
                        });
                        option.addEventListener('click', () => {
                            if (hidden.value !== (item.value ?? String(item.id))) selectItem(item);
                        });
                        results.append(option);
                    });
                    showResults(search, `${items.length} sugestão(ões). Use as setas e Enter para selecionar.`);
                };
                const searchItems = async () => {
                    const query = input.value.trim();
                    const isIdSearch = /^\d+$/.test(query);
                    if (query.length < 2 && !isIdSearch) {
                        closeResults(search);
                        message.textContent = 'Digite o ID ou ao menos 2 caracteres do nome.';
                        return;
                    }

                    requestController?.abort();
                    requestController = new AbortController();
                    const body = new FormData();
                    body.set('csrfToken', csrfToken);
                    body.set('tabToken', form.elements.namedItem('tabToken')?.value || fallbackTabToken || '');
                    body.set('acao', 'buscarReferencias');
                    body.set('tipo', type);
                    body.set('busca', query);
                    if (type === 'pessoa') body.set('natureza', search.dataset.natureza || '');

                    showResults(search, 'Pesquisando...');
                    results.replaceChildren();
                    try {
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            body,
                            headers: {'X-Requested-With': 'XMLHttpRequest'},
                            credentials: 'same-origin',
                            signal: requestController.signal
                        });
                        if (!(response.headers.get('content-type') || '').includes('application/json')) {
                            throw new Error('Não foi possível validar sua sessão. Atualize a página e tente novamente.');
                        }
                        const result = await response.json();
                        if (!response.ok) throw new Error(result.message || 'Não foi possível pesquisar os cadastros.');
                        renderItems(result.items);
                    } catch (error) {
                        if (error.name === 'AbortError') return;
                        results.replaceChildren();
                        showResults(search, error.message || 'Falha ao pesquisar os cadastros.');
                    }
                };

                input.addEventListener('input', () => {
                    window.clearTimeout(debounceTimer);
                    if (requestController) requestController.abort();
                    if (hidden.value !== '') {
                        hidden.value = '';
                        if (type === 'categoria') {
                            categorySearch.dataset.natureza = '';
                            updatePersonAvailability();
                            clearPerson();
                        }
                    }
                    if (input.hasAttribute('data-required-reference')) {
                        input.setCustomValidity('Selecione uma categoria nas sugestões.');
                    } else {
                        input.setCustomValidity(input.value.trim() === ''
                            ? ''
                            : 'Selecione uma opção nas sugestões ou apague o texto.');
                    }
                    debounceTimer = window.setTimeout(searchItems, 250);
                });
                input.addEventListener('keydown', (event) => {
                    const options = Array.from(results.querySelectorAll('[role="option"]'));
                    if (event.key === 'Escape') {
                        closeResults(search);
                        return;
                    }
                    if (event.key === 'Enter' && input.getAttribute('aria-expanded') === 'true' && !options.length) {
                        event.preventDefault();
                        return;
                    }
                    if (!options.length || !['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) return;
                    event.preventDefault();
                    if (event.key === 'Enter') {
                        if (activeIndex >= 0) options[activeIndex].click();
                        return;
                    }
                    activeIndex = event.key === 'ArrowDown'
                        ? (activeIndex + 1) % options.length
                        : (activeIndex - 1 + options.length) % options.length;
                    options.forEach((option, index) => {
                        option.setAttribute('aria-selected', index === activeIndex ? 'true' : 'false');
                        option.classList.toggle('bg-elo-teal/10', index === activeIndex);
                    });
                    input.setAttribute('aria-activedescendant', options[activeIndex].id);
                });
                input.addEventListener('blur', () => {
                    window.setTimeout(() => {
                        if (!search.contains(document.activeElement)) closeResults(search);
                    }, 100);
                });
                input.addEventListener('focus', () => {
                    const query = input.value.trim();
                    if ((query.length >= 2 || /^\d+$/.test(query)) && hidden.value === '') searchItems();
                });
            });

            updatePersonAvailability();
        })();
    </script>
</body>
</html>