<?php

require_once "config.php";

// Verifica se usuário está logado
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

// Consultas SQL para obter os totais
try {
  // Projetos: meus projetos + projetos compartilhados comigo
  $stmtProjetos = $pdo->prepare("SELECT COUNT(DISTINCT p.id) as total 
                                FROM projetos p 
                                LEFT JOIN usuarios_projetos up ON p.id = up.projeto_id 
                                WHERE p.criador_id = ? OR up.usuario_id = ?");
  $stmtProjetos->execute([$usuario_id, $usuario_id]);
  $totalProjetos = $stmtProjetos->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

  // Tarefas: apenas dos projetos que o usuário tem acesso (seus projetos + projetos compartilhados)
  $stmtTarefas = $pdo->prepare("SELECT COUNT(DISTINCT t.id) as total 
                               FROM tarefas t 
                               INNER JOIN projetos p ON t.projeto_id = p.id 
                               LEFT JOIN usuarios_projetos up ON p.id = up.projeto_id 
                               WHERE p.criador_id = ? OR up.usuario_id = ?");
  $stmtTarefas->execute([$usuario_id, $usuario_id]);
  $totalTarefas = $stmtTarefas->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

  // Notas: apenas dos projetos que o usuário tem acesso (seus projetos + projetos compartilhados)
  $stmtNotas = $pdo->prepare("SELECT COUNT(DISTINCT n.id) as total 
                           FROM notas n 
                           INNER JOIN projetos p ON n.projeto_id = p.id 
                           LEFT JOIN usuarios_projetos up ON p.id = up.projeto_id 
                           WHERE p.criador_id = ? OR up.usuario_id = ?");
  $stmtNotas->execute([$usuario_id, $usuario_id]);
  $totalNotas = $stmtNotas->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

  // Conexões do usuário logado
  $stmtConexoes = $pdo->prepare("SELECT COUNT(DISTINCT 
                                CASE 
                                    WHEN id_usuario = ? THEN id_conectado 
                                    WHEN id_conectado = ? THEN id_usuario 
                                END) as total 
                                FROM usuarios_conexoes 
                                WHERE id_usuario = ? OR id_conectado = ?");
  $stmtConexoes->execute([$usuario_id, $usuario_id, $usuario_id, $usuario_id]);
  $totalConexoes = $stmtConexoes->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

  // Busca projetos do usuário para o filtro
  $stmtProjetosFiltro = $pdo->prepare("SELECT DISTINCT p.id, p.titulo 
                                      FROM projetos p 
                                      LEFT JOIN usuarios_projetos up ON p.id = up.projeto_id 
                                      WHERE p.criador_id = ? OR up.usuario_id = ? 
                                      ORDER BY p.titulo");
  $stmtProjetosFiltro->execute([$usuario_id, $usuario_id]);
  $projetos = $stmtProjetosFiltro->fetchAll(PDO::FETCH_ASSOC);

  // Verifica se há um filtro de projeto selecionado
  $projeto_filtro = $_GET['projeto'] ?? 'todos';
  
  // Dados para o gráfico de tarefas
  if ($projeto_filtro === 'todos') {
    // Todas as tarefas de todos os projetos
    $stmtTarefasConcluidas = $pdo->prepare("SELECT COUNT(DISTINCT t.id) as total 
                                           FROM tarefas t 
                                           INNER JOIN projetos p ON t.projeto_id = p.id 
                                           LEFT JOIN usuarios_projetos up ON p.id = up.projeto_id 
                                           WHERE (p.criador_id = ? OR up.usuario_id = ?) AND t.concluida = 1");
    $stmtTarefasConcluidas->execute([$usuario_id, $usuario_id]);
    $tarefasConcluidas = $stmtTarefasConcluidas->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmtTarefasPendentes = $pdo->prepare("SELECT COUNT(DISTINCT t.id) as total 
                                          FROM tarefas t 
                                          INNER JOIN projetos p ON t.projeto_id = p.id 
                                          LEFT JOIN usuarios_projetos up ON p.id = up.projeto_id 
                                          WHERE (p.criador_id = ? OR up.usuario_id = ?) AND t.concluida = 0");
    $stmtTarefasPendentes->execute([$usuario_id, $usuario_id]);
    $tarefasPendentes = $stmtTarefasPendentes->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $titulo_grafico = "Status de Todas as Tarefas";
  } else {
    // Tarefas de um projeto específico
    $projeto_id = intval($projeto_filtro);
    
    // Verifica se o usuário tem acesso a este projeto
    $stmtVerificaAcesso = $pdo->prepare("SELECT COUNT(*) as acesso 
                                        FROM projetos p 
                                        LEFT JOIN usuarios_projetos up ON p.id = up.projeto_id 
                                        WHERE p.id = ? AND (p.criador_id = ? OR up.usuario_id = ?)");
    $stmtVerificaAcesso->execute([$projeto_id, $usuario_id, $usuario_id]);
    $tem_acesso = $stmtVerificaAcesso->fetch(PDO::FETCH_ASSOC)['acesso'] ?? 0;
    
    if ($tem_acesso) {
      $stmtTarefasConcluidas = $pdo->prepare("SELECT COUNT(*) as total 
                                             FROM tarefas 
                                             WHERE projeto_id = ? AND concluida = 1");
      $stmtTarefasConcluidas->execute([$projeto_id]);
      $tarefasConcluidas = $stmtTarefasConcluidas->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

      $stmtTarefasPendentes = $pdo->prepare("SELECT COUNT(*) as total 
                                            FROM tarefas 
                                            WHERE projeto_id = ? AND concluida = 0");
      $stmtTarefasPendentes->execute([$projeto_id]);
      $tarefasPendentes = $stmtTarefasPendentes->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
      
      // Busca nome do projeto para o título
      $stmtProjetoNome = $pdo->prepare("SELECT titulo FROM projetos WHERE id = ?");
      $stmtProjetoNome->execute([$projeto_id]);
      $projeto_nome = $stmtProjetoNome->fetch(PDO::FETCH_ASSOC)['titulo'] ?? 'Projeto';
      $titulo_grafico = "Status das Tarefas - " . htmlspecialchars($projeto_nome);
    } else {
      // Se não tem acesso, redireciona para todos os projetos
      $projeto_filtro = 'todos';
      $tarefasConcluidas = 0;
      $tarefasPendentes = 0;
      $titulo_grafico = "Status de Todas as Tarefas";
    }
  }

} catch (Exception $e) {
  // em caso de erro, define zeros
  $totalProjetos = $totalTarefas = $totalNotas = $totalConexoes = 0;
  $tarefasConcluidas = $tarefasPendentes = 0;
  $projetos = [];
  $titulo_grafico = "Status das Tarefas";
  $projeto_filtro = 'todos';
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>
  <!-- Bootstrap 5 -->
  <link href="assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <!-- FontAwesome -->
  <link href="assets/fontawsome/css/all.min.css" rel="stylesheet">
  <!-- Chart.js -->
  <script src="assets/node_modules/chart.js/dist/chart.umd.min.js"></script>
  <link rel="stylesheet" href="stile.css">
  
  
</head>

<body class="container2 mt-4">

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
    <h1 class="mb-4">Dashboard</h1>

    <!-- Filtro de Projeto -->
    <div class="card mb-4">
  <div class="card-body py-3">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-8 col-sm-12">
        <select name="projeto" id="projeto" class="form-select">
          <option value="todos" <?php echo $projeto_filtro === 'todos' ? 'selected' : ''; ?>>Todos os Projetos</option>
          <?php foreach ($projetos as $projeto): ?>
            <option value="<?php echo $projeto['id']; ?>" <?php echo $projeto_filtro == $projeto['id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($projeto['titulo']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 col-sm-12">
        <button type="submit" class="btn btn-primary w-100">Aplicar Filtro</button>
      </div>
    </form>
  </div>
</div>

    <!-- Cards de Estatísticas -->
    <div class="row g-3 mb-4">
  <div class="col-12">
    <div class="row g-3 justify-content-center">
      <div class="col-lg-4 col-md-6 col-sm-6">
        <div class="stat-card stat-total h-100 text-center d-flex flex-column justify-content-center">
          <div class="stat-number display-6 fw-bold"><?php echo $totalProjetos; ?></div>
          <div class="stat-label fs-5">Projetos</div>
        </div>
      </div>
      <div class="col-lg-4 col-md-6 col-sm-6">
        <div class="stat-card stat-progresso h-100 text-center d-flex flex-column justify-content-center">
          <div class="stat-number display-6 fw-bold"><?php echo $totalNotas; ?></div>
          <div class="stat-label fs-5">Notas</div>
        </div>
      </div>
      <div class="col-lg-4 col-md-6 col-sm-6">
        <div class="stat-card stat-concluida h-100 text-center d-flex flex-column justify-content-center">
          <div class="stat-number display-6 fw-bold"><?php echo $totalConexoes; ?></div>
          <div class="stat-label fs-5">Conexões</div>
        </div>
      </div>
    </div>
  </div>
</div>

    <!-- Gráfico de Tarefas Concluídas vs Pendentes -->
    <div class="row g-4">
      <div class="col-lg-8">
        <div class="card h-100">
          <div class="card-body">
            <h5 class="card-title d-flex align-items-center justify-content-center">
              <?php echo $titulo_grafico; ?>
            </h5>
            <div class="chart-container" style="position: relative; height:300px;">
              <canvas id="tarefasChart"></canvas>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card h-100">
          <div class="card-body">
            <h5 class="card-title d-flex align-items-center justify-content-center">
              Resumo
            </h5>
            <ul class="list-group list-group-flush">
              <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                <div class="d-flex align-items-center">
                  <span class="badge bg-success me-2 p-2"></span>
                  Tarefas Concluídas
                </div>
                <span class="badge bg-success rounded-pill fs-6"><?php echo $tarefasConcluidas; ?></span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                <div class="d-flex align-items-center">
                  <span class="badge bg-warning me-2 p-2"></span>
                  Tarefas Pendentes
                </div>
                <span class="badge bg-warning rounded-pill fs-6"><?php echo $tarefasPendentes; ?></span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                <div class="d-flex align-items-center">
                  <span class="badge bg-primary me-2 p-2"></span>
                  Total de Tarefas
                </div>
                <span class="badge bg-primary rounded-pill fs-6"><?php echo $tarefasConcluidas + $tarefasPendentes; ?></span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                <div class="d-flex align-items-center">
                  <span class="badge bg-info me-2 p-2"></span>
                  Taxa de Conclusão
                </div>
                <span class="badge bg-roxo rounded-pill fs-6">
                  <?php 
                    $total = $tarefasConcluidas + $tarefasPendentes;
                    echo $total > 0 ? round(($tarefasConcluidas / $total) * 100, 1) . '%' : '0%'; 
                  ?>
                </span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Gráfico de Tarefas
    const ctx = document.getElementById('tarefasChart').getContext('2d');
    const tarefasChart = new Chart(ctx, {
      type: 'pie',
      data: {
        labels: ['Concluídas', 'Pendentes'],
        datasets: [{
          data: [<?php echo $tarefasConcluidas; ?>, <?php echo $tarefasPendentes; ?>],
          backgroundColor: [
            '#28a745',
            '#ffc107'
          ],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                let label = context.label || '';
                let value = context.raw || 0;
                let total = context.dataset.data.reduce((a, b) => a + b, 0);
                let percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                return `${label}: ${value} (${percentage}%)`;
              }
            }
          }
        }
      }
    });

    // Menu Mobile
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
        userMenuToggle.addEventListener('click', function(e) {
          e.stopPropagation();
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

      // Fecha o dropdown do usuário ao clicar em um link
      const userDropdownLinks = document.querySelectorAll('.user-dropdown a');
      userDropdownLinks.forEach(link => {
          link.addEventListener('click', () => {
              const userDropdown = document.getElementById('userDropdown');
              const userMenuChevron = document.getElementById('userMenuChevron');
              
              if (userDropdown && userMenuChevron) {
                  userDropdown.classList.remove('show');
                  userMenuChevron.classList.add('fa-chevron-up');
                  userMenuChevron.classList.remove('fa-chevron-down');
              }
              
              // Fecha o sidebar no mobile
              if (window.innerWidth <= 768) {
                  const sidebar = document.getElementById('sidebar');
                  const sidebarOverlay = document.getElementById('sidebarOverlay');
                  
                  if (sidebar && sidebarOverlay) {
                      sidebar.classList.remove('mobile-open');
                      sidebarOverlay.classList.remove('active');
                      document.body.classList.remove('menu-open');
                  }
              }
          });
      });
    });
  </script>

  <script src="assets/bootstrap/js/bootstrap.min.js"></script>
</body>

</html>
