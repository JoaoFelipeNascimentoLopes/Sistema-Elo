<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/conexao.php';

$schema = file_get_contents(__DIR__ . '/schema.sql');
if ($schema === false) {
    throw new RuntimeException('Não foi possível ler o schema do banco.');
}

foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
    $pdo->exec($statement);
}

$existingProfile = $pdo->query(
    "SELECT idPerfil FROM tbPerfilAcesso WHERE administradorGlobal = 1 LIMIT 1"
)->fetchColumn();

if ($existingProfile !== false) {
    fwrite(STDOUT, "O perfil de Administração Global já está configurado. Nenhuma senha foi alterada.\n");
    exit(0);
}

$login = 'admin-global';
$existingLogin = $pdo->prepare('SELECT idUsuario FROM tbUsuario WHERE login = :login');
$existingLogin->execute(['login' => $login]);
if ($existingLogin->fetchColumn() !== false) {
    throw new RuntimeException('O login admin-global já existe sem um perfil global. Resolva o conflito antes de continuar.');
}

$senhaInicial = bin2hex(random_bytes(18));
$pdo->beginTransaction();

try {
    $insertProfile = $pdo->prepare(
        'INSERT INTO tbPerfilAcesso (nomePerfil, permissoes, administradorGlobal)
         VALUES (:nome, :permissoes, 1)'
    );
    $insertProfile->execute([
        'nome' => 'Administração Global',
        'permissoes' => json_encode(['*' => ['*']], JSON_THROW_ON_ERROR),
    ]);
    $idPerfil = (int) $pdo->lastInsertId();

    $insertUser = $pdo->prepare(
        'INSERT INTO tbUsuario (nomeCompleto, email, login, senhaHash, idPerfil, ativo)
         VALUES (:nome, :email, :login, :senhaHash, :idPerfil, 1)'
    );
    $insertUser->execute([
        'nome' => 'Administrador Global',
        'email' => 'admin-global@localhost',
        'login' => $login,
        'senhaHash' => password_hash($senhaInicial, PASSWORD_DEFAULT),
        'idPerfil' => $idPerfil,
    ]);

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

fwrite(STDOUT, "Setup concluído. Guarde as credenciais abaixo; a senha não será exibida novamente.\n");
fwrite(STDOUT, "Login: $login\nSenha: $senhaInicial\n");
