<?php
require_once "config.php";

// Verifica se usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Buscar informações do usuário para mostrar na sidebar
$stmtUsuario = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
$stmtUsuario->execute([$usuario_id]);
$usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

// Gerar iniciais do usuário
$iniciais = '';
if ($usuario && isset($usuario['nome'])) {
    $nomes = explode(' ', $usuario['nome']);
    $iniciais = strtoupper(substr($nomes[0], 0, 1));
    if (count($nomes) > 1) {
        $iniciais .= strtoupper(substr($nomes[count($nomes)-1], 0, 1));
    }
} else {
    $iniciais = 'U';
}

// Criar projeto
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['titulo'], $_POST['descricao'])) {
    $titulo = trim($_POST['titulo']);
    $descricao = trim($_POST['descricao']);
    $usuario_id = $_SESSION['usuario_id'];

    if (!empty($titulo)) {
        $stmt = $pdo->prepare("INSERT INTO projetos (titulo, descricao, criador_id) VALUES (?, ?, ?)");
        $stmt->execute([$titulo, $descricao, $usuario_id]);

        $projeto_id = $pdo->lastInsertId();

        // adiciona criador na tabela usuarios_projetos
        $stmt2 = $pdo->prepare("INSERT INTO usuarios_projetos (usuario_id, projeto_id) VALUES (?, ?)");
        $stmt2->execute([$usuario_id, $projeto_id]);

        header("Location: projetos.php");
        exit;
    }
}

// Deletar Projeto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remover']) && isset($_POST['remover_id'])) {
    $rem_id = intval($_POST['remover_id']);
    $usuario_id = $_SESSION['usuario_id'];

    // Verificar se o usuário é o criador do projeto
    $stmt = $pdo->prepare("SELECT criador_id FROM projetos WHERE id = ?");
    $stmt->execute([$rem_id]);
    $criador_id = $stmt->fetchColumn();

    if ($criador_id == $usuario_id) {
        // remove tarefas relacionadas
        $pdo->prepare("DELETE FROM tarefas WHERE projeto_id = ?")->execute([$rem_id]);

        // remove os vínculos de usuários
        $pdo->prepare("DELETE FROM usuarios_projetos WHERE projeto_id = ?")->execute([$rem_id]);

        //remove o projeto
        $pdo->prepare("DELETE FROM projetos WHERE id = ?")->execute([$rem_id]);
    }

    header('Location: projetos.php');
    exit;
}

// Buscar projetos que o usuário participa com informação se é o criador e se foi compartilhado
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        CASE 
            WHEN p.criador_id = ? THEN 1 
            ELSE 0 
        END as eh_criador,
        CASE 
            WHEN p.criador_id != ? THEN 1 
            ELSE 0 
        END as foi_compartilhado
    FROM projetos p
    JOIN usuarios_projetos up ON p.id = up.projeto_id
    WHERE up.usuario_id = ?
");
$stmt->execute([$_SESSION['usuario_id'], $_SESSION['usuario_id'], $_SESSION['usuario_id']]);
$projetos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Meus Projetos</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="stile.css">
</head>


<body class="container2 mt-4">

  <!-- Botão Menu Mobile -->
  <button class="mobile-menu-btn mobile-only" id="mobileMenuBtn">
      <i class="fa fa-bars"></i>
  </button>

  <!-- Overlay para fechar menu -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- Sidebar -->
  <div class="sidebar">
    <h4 class="text-center">Menu</h4>
    <a href="index.php"><i class="fa fa-home"></i> Dashboard</a>
    <a href="projetos.php"><i class="fa fa-folder"></i> Projetos</a>
    <a href="notas.php"><i class="fa fa-sticky-note"></i> Notas</a>
    <a href="usuarios.php"><i class="fa fa-users"></i> Usuários</a>
    
    <!-- Menu do Usuário Corrigido -->
    <div class="user-menu">
      <div class="user-info" id="userMenuToggle">
        <div class="user-avatar">
          <?php echo $iniciais; ?>
        </div>
        <div class="user-details">
          <div class="user-name"><?php echo htmlspecialchars($usuario['nome'] ?? 'Usuário'); ?></div>
          <div class="user-email"><?php echo htmlspecialchars($usuario['email'] ?? ''); ?></div>
        </div>
        <i class="fa fa-chevron-up text-muted" id="userMenuChevron"></i>
      </div>
      
      <div class="user-dropdown" id="userDropdown">
        <a href="sobre.php">
                  <i class="fa fa-info-circle"></i> Sobre Nós
              </a>
        <a href="logout.php">
          <i class="fa fa-sign-out-alt"></i> Sair
        </a>
      </div>
    </div>
  </div>

    <div class="container mt-4 content">
        <h1>Meus Projetos</h1>

        <form method="post" class="mb-4">
            <input type="text" name="titulo" placeholder="Título do projeto" required class="list-group ">
            <input name="descricao" placeholder="Descrição" class="list-group"></input>
            <button class="btn btn-success">Criar Projeto</button>
        </form>

        <h2>Projetos</h2>
        <?php foreach ($projetos as $proj): ?>
            <li class="list-group">
                <div class="linha">
                    <div>
                        <?= htmlspecialchars($proj['titulo']) ?>
                        <?php if ($proj['foi_compartilhado']): ?>
                            <?php if ($proj['eh_criador']): ?>
                                <span class="badge-criador">Dono</span>
                            <?php else: ?>
                                <span class="badge-participante">Participante</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="acoes-tarefa">
                        <a href="projeto_detalhes.php?id=<?= $proj['id'] ?>">
                            <button class="btn btn-sm btn-primary" name="detalhes" value="1">Detalhes</button>
                        </a>
                        <?php if ($proj['eh_criador']): ?>
                            <!-- Apenas o criador pode deletar o projeto -->
                            <form method="post" style="margin:0;" onsubmit="return confirm('Deletar Projeto?');">
                                <input type="hidden" name="remover_id" value="<?= $proj['id'] ?>">
                                <button class="btn btn-sm btn-danger" name="remover" value="1">Remover</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>

        <script>
            // Controle do menu mobile
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.querySelector('.sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const body = document.body;

            if (mobileMenuBtn && sidebar && sidebarOverlay) {
                mobileMenuBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('mobile-open');
                    sidebarOverlay.classList.toggle('active');
                    body.classList.toggle('menu-open');
                });

                sidebarOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('mobile-open');
                    sidebarOverlay.classList.remove('active');
                    body.classList.remove('menu-open');
                });

                const sidebarLinks = document.querySelectorAll('.sidebar a');
                sidebarLinks.forEach(link => {
                    link.addEventListener('click', () => {
                        if (window.innerWidth <= 768) {
                            sidebar.classList.remove('mobile-open');
                            sidebarOverlay.classList.remove('active');
                            body.classList.remove('menu-open');
                        }
                    });
                });

                window.addEventListener('resize', () => {
                    if (window.innerWidth > 768) {
                        sidebar.classList.remove('mobile-open');
                        sidebarOverlay.classList.remove('active');
                        body.classList.remove('menu-open');
                    }
                });
            }
            
            // Controle do menu do usuário
            const userMenuToggle = document.getElementById('userMenuToggle');
            const userDropdown = document.getElementById('userDropdown');
            const userMenuChevron = document.getElementById('userMenuChevron');
            
            userMenuToggle.addEventListener('click', function() {
                userDropdown.classList.toggle('show');
                userMenuChevron.classList.toggle('fa-chevron-up');
                userMenuChevron.classList.toggle('fa-chevron-down');
            });
            
            // Fechar menu ao clicar fora
            document.addEventListener('click', function(event) {
                if (!userMenuToggle.contains(event.target) && !userDropdown.contains(event.target)) {
                    userDropdown.classList.remove('show');
                    userMenuChevron.classList.add('fa-chevron-up');
                    userMenuChevron.classList.remove('fa-chevron-down');
                }
            });
        </script>
</body>

</html>