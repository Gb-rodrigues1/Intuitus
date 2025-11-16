<?php
require_once 'config.php';

// Verifica login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Busca informações do usuário para mostrar na sidebar
$stmtUsuario = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
$stmtUsuario->execute([$usuario_id]);
$usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

// Gera iniciais do usuário
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
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Sobre Nós - Intuitus Manager</title>
    <link href="assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/fontawsome/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="stile.css">
    <!-- Adicionar viewport para mobile -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body class="container2 mt-4">

  <!-- Botão Menu Mobile -->
  <button class="mobile-menu-btn mobile-only" id="mobileMenuBtn">
      <i class="fa fa-bars"></i>
  </button>

  <!-- Overlay para fechar menu -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- Sidebar -->
  <div class="sidebar" id="sidebar">
    <h4 class="text-center">Menu</h4>
    <a href="index.php"><i class="fa fa-home"></i> Dashboard</a>
    <a href="projetos.php"><i class="fa fa-folder"></i> Projetos</a>
    <a href="notas.php"><i class="fa fa-sticky-note"></i> Notas</a>
    <a href="usuarios.php"><i class="fa fa-users"></i> Usuários</a>
    
    <!-- Menu do Usuário -->
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

  <div class="content">
    <div class="sobre-container">
      <!-- Cabeçalho -->
      <div class="card mb-4">
          <div class="card-body sobre-card-body">
              
              <!-- Introdução -->
              <div class="sobre-header">
                  <div class="row">
                      <div class="col-md-12">
                          <h1 class="prelude-logo">Preludium</h1>
                          <h2 class="tagline">Intuitus Manager</h2>
                          </div>
                          <p class="intro-text">
                              Combinamos expertise técnica com visão educacional para desenvolver 
                              soluções que transformam a gestão de projetos acadêmicos. Nossa equipe 
                              multidisciplinar une conhecimento em desenvolvimento full-stack, 
                              arquitetura de dados e experiência do usuário.
                          </p>
                      </div>
                  </div>
              </div>

              <!-- Missão -->
              <div class="mission-section">
                  <div class="row">
                      <div class="col-md-12">
                          <h3 class="section-title">Nossa Missão</h3>
                          <p class="mission-text">
                              Desenvolver soluções tecnológicas que otimizem a colaboração acadêmica 
                              através de interfaces intuitivas e arquiteturas escaláveis. O Intuitus 
                              Manager representa nossa visão de como a tecnologia pode potencializar
                              o trabalho em equipe no ambiente educacional.
                          </p>
                          <blockquote class="inspiration-quote">
                              A tecnologia mais poderosa é aquela que se torna invisível, 
                              permitindo que as pessoas foquem no que realmente importa: criar e colaborar.
                              <span class="quote-author">— Equipe Preludium</span>
                          </blockquote>
                      </div>
                  </div>
              </div>

              <!-- Equipe -->
              <div class="team-section">
                  <h3 class="section-title">Nossa Equipe</h3>
                  <div class="row g-4">
                      <!-- Roger -->
                      <div class="col-md-4">
                          <div class="team-member-card">
                              <div class="member-icon icon-documentacao">
                                  <i class="fas fa-file-alt"></i>
                              </div>
                              <h5 class="team-member-name">Roger Carvalho</h5>
                              <p class="member-role">Especialista em Documentação</p>
                              <p class="member-bio">
                                  Responsável pela documentação técnica e arquitetura da informação do sistema.
                              </p>
                          </div>
                      </div>

                      <!-- Guilherme -->
                      <div class="col-md-4">
                          <div class="team-member-card">
                              <div class="member-icon icon-banco-dados">
                                  <i class="fas fa-database"></i>
                              </div>
                              <h5 class="team-member-name">Guilherme Dias</h5>
                              <p class="member-role">Arquiteto de Banco de Dados</p>
                              <p class="member-bio">
                                  Projeta e otimiza a estrutura de dados para performance e escalabilidade.
                              </p>
                          </div>
                      </div>

                      <!-- Miguel -->
                      <div class="col-md-4">
                          <div class="team-member-card">
                              <div class="member-icon icon-analise">
                                  <i class="fas fa-chart-line"></i>
                              </div>
                              <h5 class="team-member-name">Miguel Felipe</h5>
                              <p class="member-role">Analista de Documentação</p>
                              <p class="member-bio">
                                  Desenvolve a documentação de usuário e mantém a consistência técnica.
                              </p>
                          </div>
                      </div>

                      <!-- Gabriel -->
                      <div class="col-md-6">
                          <div class="team-member-card">
                              <div class="member-icon icon-fullstack">
                                  <i class="fas fa-code"></i>
                              </div>
                              <h5 class="team-member-name">Gabriel Rodrigues</h5>
                              <p class="member-role">Desenvolvedor Full-Stack</p>
                              <p class="member-bio">
                                  Implementa funcionalidades tanto no backend quanto no frontend do sistema.
                              </p>
                          </div>
                      </div>

                      <!-- Vinicius -->
                      <div class="col-md-6">
                          <div class="team-member-card">
                              <div class="member-icon icon-design">
                                  <i class="fas fa-paint-brush"></i>
                              </div>
                              <h5 class="team-member-name">Vinicius Santos</h5>
                              <p class="member-role">Designer de Interface</p>
                              <p class="member-bio">
                                  Cria experiências visuais intuitivas e interfaces responsivas para os usuários.
                              </p>
                          </div>
                      </div>
                  </div>
          </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="assets/bootstrap/js/bootstrap.min.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const mobileMenuBtn = document.getElementById('mobileMenuBtn');
      const sidebar = document.getElementById('sidebar');
      const sidebarOverlay = document.getElementById('sidebarOverlay');
      const userMenuToggle = document.getElementById('userMenuToggle');
      const userDropdown = document.getElementById('userDropdown');
      const userMenuChevron = document.getElementById('userMenuChevron');

      // Toggle menu mobile
      mobileMenuBtn.addEventListener('click', function() {
        sidebar.classList.toggle('mobile-open');
        sidebarOverlay.classList.toggle('active');
        document.body.classList.toggle('menu-open');
      });

      // Fecha menu ao clicar no overlay
      sidebarOverlay.addEventListener('click', function() {
        sidebar.classList.remove('mobile-open');
        sidebarOverlay.classList.remove('active');
        document.body.classList.remove('menu-open');
      });

      // Toggle menu do usuário
      if (userMenuToggle && userDropdown && userMenuChevron) {
        userMenuToggle.addEventListener('click', function() {
          userDropdown.classList.toggle('show');
          userMenuChevron.classList.toggle('fa-chevron-up');
          userMenuChevron.classList.toggle('fa-chevron-down');
        });
        
        // Fecha menu ao clicar fora
        document.addEventListener('click', function(event) {
          if (!userMenuToggle.contains(event.target) && !userDropdown.contains(event.target)) {
            userDropdown.classList.remove('show');
            userMenuChevron.classList.add('fa-chevron-up');
            userMenuChevron.classList.remove('fa-chevron-down');
          }
        });
      }

      // Fecha dropdowns ao redimensionar a janela
      window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
          sidebar.classList.remove('mobile-open');
          sidebarOverlay.classList.remove('active');
          document.body.classList.remove('menu-open');
          if (userDropdown) {
            userDropdown.classList.remove('show');
          }
          if (userMenuChevron) {
            userMenuChevron.classList.add('fa-chevron-up');
            userMenuChevron.classList.remove('fa-chevron-down');
          }
        }
      });
    });
  </script>
</body>
</html>
