<?php
require_once __DIR__ . '/../auth/access.php';
$usuarioAtual = appRequirePage('instituicao.informacoes');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $returnUrl = appFirstAllowedUrl($usuarioAtual);
    header('Location: ' . appTabUrl($returnUrl ?? appBaseUrl() . '/auth/login.php'));
    exit;
}

appValidateCsrf();
header('Content-Type: application/json; charset=UTF-8');

$respond = static function (int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};

$lookupCep = static function (string $cep): array {
    if (!preg_match('/^\d{8}$/', $cep) || $cep === '00000000') {
        throw new InvalidArgumentException('Informe um CEP válido com 8 dígitos.');
    }

    $curl = curl_init('https://viacep.com.br/ws/' . $cep . '/json/');
    if ($curl === false) {
        error_log('Não foi possível iniciar a consulta do CEP.');
        throw new RuntimeException('Não foi possível consultar o CEP agora. Tente novamente.');
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($response === false) {
        $error = curl_error($curl);
        curl_close($curl);
        error_log('Falha na consulta ao ViaCEP: ' . $error);
        throw new RuntimeException('Não foi possível consultar o CEP agora. Tente novamente.');
    }
    curl_close($curl);

    if ($status !== 200) {
        error_log('ViaCEP respondeu com status HTTP ' . $status . '.');
        throw new RuntimeException('O serviço de CEP está indisponível. Tente novamente.');
    }

    try {
        $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        error_log('Resposta JSON inválida do ViaCEP: ' . $exception->getMessage());
        throw new RuntimeException('O serviço de CEP retornou uma resposta inválida. Tente novamente.');
    }

    if (!is_array($result) || ($result['erro'] ?? false) === true) {
        throw new InvalidArgumentException('CEP não encontrado. Confira o número e tente novamente.');
    }
    if (!is_string($result['cep'] ?? null)
        || preg_replace('/\D+/', '', $result['cep']) !== $cep
        || !is_string($result['localidade'] ?? null)
        || !is_string($result['uf'] ?? null)) {
        error_log('ViaCEP retornou uma resposta incompleta para o CEP ' . $cep . '.');
        throw new RuntimeException('O serviço de CEP retornou uma resposta inválida. Tente novamente.');
    }
    foreach (['logradouro', 'bairro'] as $optionalField) {
        if (isset($result[$optionalField]) && !is_string($result[$optionalField])) {
            error_log('ViaCEP retornou um campo de endereço inválido para o CEP ' . $cep . '.');
            throw new RuntimeException('O serviço de CEP retornou uma resposta inválida. Tente novamente.');
        }
    }

    // ViaCEP's "complemento" can describe postal-side ranges, so it remains manual.
    $address = [
        'logradouro' => trim($result['logradouro'] ?? ''),
        'bairro' => trim((string) ($result['bairro'] ?? '')),
        'cidade' => trim((string) ($result['localidade'] ?? '')),
        'uf' => strtoupper(trim((string) ($result['uf'] ?? ''))),
    ];
    $address['lockedFields'] = array_values(array_keys(array_filter(
        $address,
        static fn (string $value, string $field): bool => $field !== 'uf' ? $value !== '' : preg_match('/^[A-Z]{2}$/', $value) === 1,
        ARRAY_FILTER_USE_BOTH
    )));

    if (isset($address['uf']) && $address['uf'] !== '' && !preg_match('/^[A-Z]{2}$/', $address['uf'])) {
        error_log('ViaCEP retornou uma UF inválida para o CEP ' . $cep . '.');
        throw new RuntimeException('O serviço de CEP retornou um endereço inválido. Tente novamente.');
    }

    return $address;
};

$action = $_POST['acao'] ?? '';
if (!is_string($action)) {
    $respond(400, ['message' => 'A solicitação enviada é inválida.']);
}
if ($action === 'consultarCep') {
    $submittedCep = $_POST['cep'] ?? '';
    if (!is_scalar($submittedCep)) {
        $respond(400, ['message' => 'Informe um CEP válido.']);
    }

    try {
        $address = $lookupCep(preg_replace('/\D+/', '', (string) $submittedCep));
        $respond(200, $address);
    } catch (InvalidArgumentException $exception) {
        $respond(422, ['message' => $exception->getMessage()]);
    } catch (RuntimeException $exception) {
        $respond(502, ['message' => $exception->getMessage()]);
    }
}
if ($action !== '') {
    $respond(400, ['message' => 'A ação solicitada é inválida.']);
}

$fields = [
    'cnpj',
    'razaoSocial',
    'nomeFantasia',
    'email',
    'telefone',
    'cep',
    'logradouro',
    'numero',
    'complemento',
    'bairro',
    'cidade',
    'uf',
    'site',
];
$submitted = [];
foreach ($fields as $field) {
    $value = $_POST[$field] ?? '';
    if (!is_scalar($value)) {
        $respond(400, ['message' => 'Confira os dados informados.']);
    }
    $submitted[$field] = trim((string) $value);
}

$lookupCompleted = $_POST['cepConsultado'] ?? '0';
if (!is_string($lookupCompleted) || !in_array($lookupCompleted, ['0', '1'], true)) {
    $respond(400, ['message' => 'A solicitação de consulta do CEP é inválida.']);
}

$cnpj = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $submitted['cnpj']));
$cep = preg_replace('/\D+/', '', $submitted['cep']);
$phone = preg_replace('/\D+/', '', $submitted['telefone']);
$submitted['uf'] = strtoupper($submitted['uf']);
$validCnpj = static function (string $value): bool {
    if (!preg_match('/^[A-Z0-9]{12}\d{2}$/', $value) || preg_match('/^([A-Z0-9])\1{13}$/', $value)) {
        return false;
    }

    $calculateDigit = static function (string $base, array $weights): int {
        $sum = 0;
        foreach (str_split($base) as $index => $character) {
            $sum += (ord($character) - 48) * $weights[$index];
        }
        $remainder = $sum % 11;
        return $remainder < 2 ? 0 : 11 - $remainder;
    };

    $firstDigit = $calculateDigit(substr($value, 0, 12), [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
    $secondDigit = $calculateDigit(substr($value, 0, 12) . $firstDigit, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

    return substr($value, -2) === $firstDigit . $secondDigit;
};
$validPhone = static function (string $value): bool {
    $validAreaCodes = [
        '11', '12', '13', '14', '15', '16', '17', '18', '19', '21', '22', '24',
        '27', '28', '31', '32', '33', '34', '35', '37', '38', '41', '42', '43',
        '44', '45', '46', '47', '48', '49', '51', '53', '54', '55', '61', '62',
        '63', '64', '65', '66', '67', '68', '69', '71', '73', '74', '75', '77',
        '79', '81', '82', '83', '84', '85', '86', '87', '88', '89', '91', '92',
        '93', '94', '95', '96', '97', '98', '99',
    ];
    if (preg_match('/^(\d)\1+$/', $value)) {
        return false;
    }
    $areaCode = substr($value, 0, 2);
    $subscriber = substr($value, 2);
    if (!in_array($areaCode, $validAreaCodes, true)) {
        return false;
    }

    return (strlen($value) === 10 && preg_match('/^[2-5]\d{7}$/', $subscriber) === 1)
        || (strlen($value) === 11 && preg_match('/^9\d{8}$/', $subscriber) === 1);
};

$validationError = null;
if ($submitted['cnpj'] !== '' && !$validCnpj($cnpj)) {
    $validationError = 'Informe um CNPJ válido, com 14 caracteres e dígitos verificadores corretos.';
} elseif ($submitted['telefone'] !== '' && !$validPhone($phone)) {
    $validationError = 'Informe um telefone brasileiro válido com DDD.';
} elseif ($submitted['cep'] !== '' && (!preg_match('/^\d{8}$/', $cep) || $cep === '00000000')) {
    $validationError = 'Informe um CEP válido com 8 dígitos.';
} elseif ($lookupCompleted === '1' && $cep === '') {
    $validationError = 'Informe o CEP antes de consultar o endereço.';
} elseif ($submitted['uf'] !== '' && !preg_match('/^[A-Z]{2}$/', $submitted['uf'])) {
    $validationError = 'Informe uma UF válida com duas letras.';
} elseif ($submitted['email'] !== '' && filter_var($submitted['email'], FILTER_VALIDATE_EMAIL) === false) {
    $validationError = 'Informe um e-mail institucional válido.';
} elseif ($submitted['site'] !== ''
    && (filter_var($submitted['site'], FILTER_VALIDATE_URL) === false
        || !in_array(strtolower((string) parse_url($submitted['site'], PHP_URL_SCHEME)), ['http', 'https'], true))) {
    $validationError = 'Informe um endereço de site válido, começando com http:// ou https://.';
}

$lengthLimits = [
    'cnpj' => 18,
    'razaoSocial' => 150,
    'nomeFantasia' => 150,
    'email' => 190,
    'telefone' => 15,
    'cep' => 9,
    'logradouro' => 150,
    'numero' => 20,
    'complemento' => 100,
    'bairro' => 100,
    'cidade' => 100,
    'site' => 255,
];
foreach ($lengthLimits as $field => $limit) {
    if (mb_strlen($submitted[$field], 'UTF-8') > $limit) {
        $validationError = 'Um dos campos excede o limite permitido.';
        break;
    }
}
if ($validationError !== null) {
    $respond(422, ['message' => $validationError]);
}

$lookupAddress = null;
if ($lookupCompleted === '1') {
    try {
        $lookupAddress = $lookupCep($cep);
    } catch (InvalidArgumentException $exception) {
        $respond(422, ['message' => $exception->getMessage()]);
    } catch (RuntimeException $exception) {
        $respond(502, ['message' => $exception->getMessage()]);
    }
}

try {
    $pdo = appPdo();
    $pdo->beginTransaction();
    $existingQuery = $pdo->query(
        'SELECT cep, logradouro, complemento, bairro, cidade, uf, camposEnderecoViaCep
         FROM tbInformacoesInstituicao
         WHERE idInformacoes = 1
         FOR UPDATE'
    );
    $existing = $existingQuery->fetch(PDO::FETCH_ASSOC) ?: null;
    $existingCep = preg_replace('/\D+/', '', (string) ($existing['cep'] ?? ''));
    $previousLockedFields = json_decode((string) ($existing['camposEnderecoViaCep'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
    $previousLockedFields = is_array($previousLockedFields)
        ? array_values(array_filter(
            $previousLockedFields,
            static fn ($field): bool => is_string($field)
                && in_array($field, ['logradouro', 'bairro', 'cidade', 'uf'], true)
        ))
        : [];
    $lockedFields = [];

    if ($lookupAddress !== null) {
        foreach (['logradouro', 'bairro', 'cidade', 'uf'] as $field) {
            $submitted[$field] = $lookupAddress[$field];
        }
        $lockedFields = $lookupAddress['lockedFields'];
    } elseif ($existing !== null && $existingCep === $cep && $cep !== '') {
        foreach ($previousLockedFields as $field) {
            $submitted[$field] = (string) ($existing[$field] ?? '');
            $lockedFields[] = $field;
        }
    }

    $values = [
        'cnpj' => $cnpj !== '' ? $cnpj : null,
        'razaoSocial' => $submitted['razaoSocial'] !== '' ? $submitted['razaoSocial'] : null,
        'nomeFantasia' => $submitted['nomeFantasia'] !== '' ? $submitted['nomeFantasia'] : null,
        'email' => $submitted['email'] !== '' ? $submitted['email'] : null,
        'telefone' => $phone !== '' ? $phone : null,
        'cep' => $cep !== '' ? $cep : null,
        'logradouro' => $submitted['logradouro'] !== '' ? $submitted['logradouro'] : null,
        'numero' => $submitted['numero'] !== '' ? $submitted['numero'] : null,
        'complemento' => $submitted['complemento'] !== '' ? $submitted['complemento'] : null,
        'bairro' => $submitted['bairro'] !== '' ? $submitted['bairro'] : null,
        'cidade' => $submitted['cidade'] !== '' ? $submitted['cidade'] : null,
        'uf' => $submitted['uf'] !== '' ? $submitted['uf'] : null,
        'camposEnderecoViaCep' => $lockedFields !== [] ? json_encode($lockedFields, JSON_THROW_ON_ERROR) : null,
        'site' => $submitted['site'] !== '' ? $submitted['site'] : null,
    ];

    $save = $pdo->prepare(
        'INSERT INTO tbInformacoesInstituicao
            (idInformacoes, cnpj, razaoSocial, nomeFantasia, email, telefone, cep,
             logradouro, numero, complemento, bairro, cidade, uf, camposEnderecoViaCep, site)
         VALUES
            (1, :cnpj, :razaoSocial, :nomeFantasia, :email, :telefone, :cep,
             :logradouro, :numero, :complemento, :bairro, :cidade, :uf, :camposEnderecoViaCep, :site)
         ON DUPLICATE KEY UPDATE
            cnpj = VALUES(cnpj), razaoSocial = VALUES(razaoSocial),
            nomeFantasia = VALUES(nomeFantasia), email = VALUES(email),
            telefone = VALUES(telefone), cep = VALUES(cep), logradouro = VALUES(logradouro),
            numero = VALUES(numero), complemento = VALUES(complemento), bairro = VALUES(bairro),
            cidade = VALUES(cidade), uf = VALUES(uf),
            camposEnderecoViaCep = VALUES(camposEnderecoViaCep), site = VALUES(site)'
    );
    $save->execute($values);
    $pdo->commit();
    $respond(200, ['message' => 'Informações da instituição salvas.']);
} catch (JsonException $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Estado de endereço via CEP inválido no banco: ' . $exception->getMessage());
    $respond(500, ['message' => 'Não foi possível carregar os dados atuais do endereço.']);
} catch (PDOException $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Falha ao salvar informações da instituição: ' . $exception->getMessage());
    $respond(500, ['message' => 'Não foi possível salvar as informações da instituição.']);
}
