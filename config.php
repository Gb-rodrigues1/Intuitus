<?php
// config.php - Configuração do banco de dados para Railway

// Usar variáveis de ambiente do Railway
$host = getenv('MYSQLHOST') ?: 'localhost';
$port = getenv('MYSQLPORT') ?: '3306';
$banco = getenv('MYSQLDATABASE') ?: 'sistema_projetos';
$usuario = getenv('MYSQLUSER') ?: 'root';
$senha = getenv('MYSQLPASSWORD') ?: 'prof@3t3c';

try {
    // Conecta ao banco de dados - IMPORTANTE: incluir a porta
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$banco;charset=utf8", $usuario, $senha);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// Inicia a sessão para controlar login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>