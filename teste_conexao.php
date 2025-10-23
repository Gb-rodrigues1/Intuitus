<?php
// teste_conexao.php - Debug completo da conexão
echo "<h3>🔍 Teste de Conexão com o Banco</h3>";

// Valores EXATOS que devem estar sendo usados
$host = 'mysql.railway.internal';
$port = '3306';
$banco = 'railway';
$usuario = 'root';
$senha = 'VmGNnSgrwpJYYUnECPkjLIetPTqRBzxP';

echo "<strong>Configuração usada:</strong><br>";
echo "Host: <code>$host</code><br>";
echo "Port: <code>$port</code><br>";
echo "Banco: <code>$banco</code><br>";
echo "Usuário: <code>$usuario</code><br>";
echo "Senha: <code>********</code> (comprimento: " . strlen($senha) . " caracteres)<br>";
echo "DSN: <code>mysql:host=$host;port=$port;dbname=$banco;charset=utf8</code><br><br>";

try {
    echo "🔄 Tentando conectar...<br>";
    $dsn = "mysql:host=$host;port=$port;dbname=$banco;charset=utf8";
    $pdo = new PDO($dsn, $usuario, $senha);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ <strong>Conexão bem-sucedida!</strong><br><br>";
    
    // Testar acesso às tabelas
    echo "📊 Testando acesso às tabelas:<br>";
    $tables = ['usuarios', 'projetos', 'tarefas', 'notas', 'usuarios_conexoes', 'usuarios_projetos', 'convites_compartith...'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM " . $table);
            $result = $stmt->fetch();
            echo "&nbsp;&nbsp;✅ $table: " . $result['total'] . " registros<br>";
        } catch (Exception $e) {
            echo "&nbsp;&nbsp;❌ $table: Erro - " . $e->getMessage() . "<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ <strong>Erro na conexão:</strong><br>";
    echo "<div style='background: #ffebee; padding: 10px; border-radius: 5px; border: 1px solid #f44336;'>";
    echo "<strong>Mensagem:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>Código:</strong> " . $e->getCode() . "<br>";
    echo "</div>";
    
    // Tentativas alternativas
    echo "<br><strong>🔄 Tentativas alternativas:</strong><br>";
    
    // Tentativa sem porta específica
    try {
        $pdo2 = new PDO("mysql:host=$host;dbname=$banco", $usuario, $senha);
        echo "✅ Conexão sem porta específica: FUNCIONOU<br>";
    } catch (Exception $e2) {
        echo "❌ Conexão sem porta específica: " . $e2->getMessage() . "<br>";
    }
    
    // Tentativa sem database
    try {
        $pdo3 = new PDO("mysql:host=$host;port=$port", $usuario, $senha);
        echo "✅ Conexão sem database: FUNCIONOU<br>";
    } catch (Exception $e3) {
        echo "❌ Conexão sem database: " . $e3->getMessage() . "<br>";
    }
}

echo "<br><hr>";
echo "<strong>Variáveis de ambiente atuais:</strong><br>";
echo "MYSQLHOST: " . (getenv('MYSQLHOST') ?: 'NÃO DEFINIDA') . "<br>";
echo "MYSQLPORT: " . (getenv('MYSQLPORT') ?: 'NÃO DEFINIDA') . "<br>";
echo "MYSQLDATABASE: " . (getenv('MYSQLDATABASE') ?: 'NÃO DEFINIDA') . "<br>";
echo "MYSQLUSER: " . (getenv('MYSQLUSER') ?: 'NÃO DEFINIDA') . "<br>";
echo "MYSQLPASSWORD: " . (getenv('MYSQLPASSWORD') ? 'DEFINIDA (' . strlen(getenv('MYSQLPASSWORD')) . ' chars)' : 'NÃO DEFINIDA') . "<br>";
?>