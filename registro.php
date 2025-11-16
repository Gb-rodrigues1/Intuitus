<?php
// Página de cadastro de novos usuários
require_once 'config.php';

$erro = '';
$sucesso = '';

// Se já está logado, redireciona para dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

// Processa o cadastro
if ($_POST) {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    $confirmar_senha = $_POST['confirmar_senha'];

    // Validações básicas
    if (!$nome || !$email || !$senha || !$confirmar_senha) {
        $erro = 'Preencha todos os campos!';
    } elseif ($senha !== $confirmar_senha) {
        $erro = 'As senhas não coincidem!';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter pelo menos 6 caracteres!';
    } elseif (!preg_match('/[A-Z]/', $senha)) {
        $erro = 'A senha deve ter pelo menos 1 caracter maiúsculo!';
    } elseif (!preg_match('/[^a-zA-Z0-9]/', $senha)) {
        $erro = 'A senha deve conter pelo menos 1 caracter especial';
    } else {
        // Verifica se email já existe
        $sql = "SELECT id FROM usuarios WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $erro = 'Este email já está cadastrado!';
        } else {
            // Cadastra o usuário
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $sql = "INSERT INTO usuarios (nome, email, senha, criado_em) VALUES (?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);

            if ($stmt->execute([$nome, $email, $senha_hash])) {
                $sucesso = 'Cadastro realizado com sucesso! Você já pode fazer login.';
            } else {
                $erro = 'Erro ao cadastrar. Tente novamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - Sistema de Projetos</title>
    <link rel="stylesheet" href="stile.css">
</head>

<body>
    <div class="login-container">
        <div class="login-box">
            <div class="cabecalho-login">
                <div class="logo-login">Intuitus</div>
            </div>
            <h1 class="cabecalho-login">Cadastro de Usuário</h1>

            <?php if ($erro): ?>
                <div class="alert alert-error"><?php echo $erro; ?></div>
            <?php endif; ?>

            <?php if ($sucesso): ?>
                <div class="alert alert-success">
                    <meta http-equiv="refresh" content="2; login.php"><?php echo $sucesso; ?>
                </div>
            <?php endif; ?>

            <!-- Input nome -->
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Nome Completo:</label>
                    <input type="text" name="nome" class="form-input" placeholder="Digite seu Nome" required
                        value="<?php echo isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''; ?>">
                </div>

                <!-- Input email -->
                <div class="form-group">
                    <label class="form-label">Email:</label>
                    <input type="email" name="email" class="form-input" placeholder="Digite seu email" required
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <!-- Input senha -->
                <div class="form-group">
                    <label class="form-label">Senha:</label>
                    <input type="password" name="senha" class="form-input" placeholder="Digite sua senha" required
                        onfocus="document.getElementById('senha-info').style.display='block'"
                        onblur="document.getElementById('senha-info').style.display='none'">
                    <div id="senha-info" class="senha-info" style="display: none;">
                        (A senha deve conter pelo menos 6 caracteres, 1 letra maiúscula e 1 caractere especial)
                    </div>
                </div>

                <!-- Input confirmar senha -->
                <div class="form-group">
                    <label class="form-label">Confirmar Senha:</label>
                    <input type="password" name="confirmar_senha" class="form-input" placeholder="Confirme sua senha"
                        required>
                </div>

                <!-- Botão de cadastro -->
                <button type="submit" class="btn-login">
                    Cadastrar
                </button>
            </form>

            <!-- Link para login.php -->
            <div class="label-cadastro">
                Já tem uma conta? <a href="login.php" class="texto-cadastro">Faça Login Aqui</a>
            </div>

            <div class="rodape-login">
                <p>Sistema de Gestão de Tarefas para Equipes de Trabalho</p>
                <p>© 2025 - TCC Ensino Médio</p>
            </div>
        </div>
    </div>
</body>

</html>
