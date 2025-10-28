<?php
// login.php - Página de login do sistema
require_once 'config.php';

$erro = '';

// Se já está logado, redireciona para dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

// Processa o login
if ($_POST) {
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];

    if ($email && $senha) {
        // Busca o usuário no banco
        $sql = "SELECT id, nome, email, senha FROM usuarios WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        // Verifica se usuário existe e senha está correta
        if ($usuario && password_verify($senha, $usuario['senha'])) {
            // Login bem-sucedido
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_email'] = $usuario['email'];

            header('Location: index.php');
            exit;
        } else {
            $erro = 'Email ou senha incorretos!';
        }
    } else {
        $erro = 'Preencha todos os campos!';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Projetos</title>
    <link rel="stylesheet" href="stile.css">
</head>

<body>
    <div class="login-container">
        <div class="login-box">
            <div class="cabecalho-login">
                <div class="logo-login">Mudança de conteudo do site</div>
            </div>
            <h1 class="cabecalho-login">Acesso ao Sistema</h1>

            <?php if ($erro): ?>
                <div class="alert alert-error"><?php echo $erro; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Email:</label>
                    <input type="email" name="email" class="form-input" placeholder="Digite seu email" required
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Senha:</label>
                    <input type="password" name="senha" class="form-input" placeholder="Digite sua senha" required>
                </div>

                <button type="submit" class="btn-login">
                    Entrar
                </button>
            </form>
            <div class="label-cadastro">
                Não tem conta? <a href="registro.php" class="texto-cadastro">Cadastre-se aqui</a>
            </div>
            <div class="rodape-login">
                <p>Sistema de Gestão de Tarefas para Equipes de Trabalho</p>
                <p>© 2025 - TCC Ensino Médio</p>
            </div>
        </div>
    </div>
</body>

</html>
