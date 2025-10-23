<?php
// config.php - Configuração do banco de dados para Railway

// Usar variáveis de ambiente do Railway com seus valores específicos
$host = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
$port = getenv('MYSQLPORT') ?: '3306';
$banco = getenv('MYSQLDATABASE') ?: 'railway';
$usuario = getenv('MYSQLUSER') ?: 'root';
$senha = getenv('MYSQLPASSWORD') ?: 'VmGWhSgrwp3YYUnECPKjL1etPTqRBzxP';

// Valores de fallback baseados na sua imagem
if (empty(getenv('MYSQLHOST'))) {
    $host = 'mysql.railway.internal';
    $banco = 'railway';
    $usuario = 'root';
    $senha = 'VmGWhSgrwp3YYUnECPKjL1etPTqRBzxP';
    $port = '3306';
}

try {
    // Conecta ao banco de dados
    $dsn = "mysql:host=$host;port=$port;dbname=$banco;charset=utf8";
    $pdo = new PDO($dsn, $usuario, $senha);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Debug (remover em produção)
    error_log("Conectado ao banco: $banco em $host:$port");
    
} catch (PDOException $e) {
    error_log("Erro na conexão: " . $e->getMessage());
    die("Erro na conexão com o banco de dados. Tente novamente mais tarde.");
}

// Inicia a sessão para controlar login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
