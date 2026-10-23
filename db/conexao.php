<?php

$host = '127.0.0.1';
$port = '3307';
$dbname = 'db_elo';
$usuario = 'root';
$senha = 'Joaofelipe@2312';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $usuario, $senha, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    error_log('Falha ao conectar ao banco de dados: ' . $exception->getMessage());
    throw new RuntimeException('Não foi possível conectar ao banco de dados.', 0, $exception);
}
