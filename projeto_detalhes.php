<?php
require_once "config.php";

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

// Exibir mensagens de feedback
if (isset($_SESSION['mensagem'])) {
    $tipo = $_SESSION['tipo_mensagem'] ?? 'info';
    $mensagem = $_SESSION['mensagem'];
    unset($_SESSION['mensagem']);
    unset($_SESSION['tipo_mensagem']);

    echo "<div class='alert alert-$tipo alert-dismissible fade show' role='alert'>
            $mensagem
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
          </div>";
}

$projeto_id = $_GET['id'] ?? null;

if (!$projeto_id) {
    header("Location: projetos.php");
    exit;
}

// Confere se usuário participa do projeto e obtém informações do projeto
$stmt = $pdo->prepare("SELECT p.*, up.usuario_id, u.nome as nome_criador
                      FROM projetos p 
                      JOIN usuarios_projetos up ON p.id = up.projeto_id 
                      JOIN usuarios u ON p.criador_id = u.id
                      WHERE up.usuario_id = ? AND p.id = ?");
$stmt->execute([$_SESSION['usuario_id'], $projeto_id]);
$projeto_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$projeto_info) {
    die("Acesso negado.");
}

// Verifica se o usuário é o criador do projeto
$eh_criador = ($projeto_info['criador_id'] == $_SESSION['usuario_id']);

// Buscar membros do projeto (usuários conectados que participam do projeto) INCLUINDO o próprio usuário
$stmtMembros = $pdo->prepare("SELECT u.id, u.nome, u.email 
                             FROM usuarios u 
                             JOIN usuarios_projetos up ON u.id = up.usuario_id 
                             WHERE up.projeto_id = ?");
$stmtMembros->execute([$projeto_id]);
$membros_projeto = $stmtMembros->fetchAll(PDO::FETCH_ASSOC);

// Criar nova tarefa
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['titulo'])) {
    $titulo = trim($_POST['titulo']);
    $descricao = trim($_POST['descricao']);
    $designado_para = !empty($_POST['designado_para']) ? intval($_POST['designado_para']) : null;
    $prazo_conclusao = !empty($_POST['prazo_conclusao']) ? $_POST['prazo_conclusao'] : null;

    if (!empty($titulo)) {
        $stmt = $pdo->prepare("INSERT INTO tarefas (titulo, descricao, projeto_id, designado_para, prazo_conclusao) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$titulo, $descricao, $projeto_id, $designado_para, $prazo_conclusao]);
        
        $_SESSION['mensagem'] = "Tarefa criada com sucesso!" . ($designado_para ? " Designada para membro específico." : "");
        $_SESSION['tipo_mensagem'] = "success";
        
        header("Location: projeto_detalhes.php?id=$projeto_id");
        exit;
    }
}

// Upload de comprovante E marcar como concluída
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comprovante_tarefa_id'])) {
    $tarefa_id = intval($_POST['comprovante_tarefa_id']);

    // Verificar se o arquivo foi enviado
    if (isset($_FILES['comprovante_imagem']) && $_FILES['comprovante_imagem']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'comprovantes/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = time() . '_' . basename($_FILES['comprovante_imagem']['name']);
        $uploadFile = $uploadDir . $fileName;

        // Mover arquivo para o diretório de comprovantes
        if (move_uploaded_file($_FILES['comprovante_imagem']['tmp_name'], $uploadFile)) {
            // Atualizar banco de dados com o caminho do comprovante E marcar como concluída
            $stmt = $pdo->prepare("UPDATE tarefas SET comprovante = ?, concluida = 1, concluida_por = ?, concluida_em = NOW() WHERE id = ?");
            $stmt->execute([$uploadFile, $_SESSION['usuario_id'], $tarefa_id]);

            $_SESSION['mensagem'] = "Comprovante enviado e tarefa marcada como concluída!";
            $_SESSION['tipo_mensagem'] = "success";
        }
    }

    header("Location: projeto_detalhes.php?id=$projeto_id");
    exit;
}

// Remover comprovante e desmarcar tarefa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remover_comprovante']) && isset($_POST['tarefa_id'])) {
    $tarefa_id = intval($_POST['tarefa_id']);

    // Verificar se o usuário é o criador do projeto OU o usuário que enviou o comprovante
    $stmt = $pdo->prepare("SELECT t.comprovante, t.concluida_por, p.criador_id 
                          FROM tarefas t 
                          JOIN projetos p ON t.projeto_id = p.id 
                          WHERE t.id = ?");
    $stmt->execute([$tarefa_id]);
    $tarefa_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tarefa_info) {
        // Permite remoção se for o criador do projeto OU o usuário que enviou o comprovante
        $pode_remover = ($tarefa_info['criador_id'] == $_SESSION['usuario_id']) ||
            ($tarefa_info['concluida_por'] == $_SESSION['usuario_id']);

        if ($pode_remover) {
            // Buscar o caminho do comprovante para apagar o arquivo
            if ($tarefa_info['comprovante']) {
                // Remove o arquivo do servidor
                if (file_exists($tarefa_info['comprovante'])) {
                    unlink($tarefa_info['comprovante']);
                }
            }

            // Atualizar o banco: remove comprovante e marca como não concluída
            $stmt = $pdo->prepare("UPDATE tarefas SET comprovante = NULL, concluida = 0, concluida_por = NULL, concluida_em = NULL WHERE id = ?");
            $stmt->execute([$tarefa_id]);

            $_SESSION['mensagem'] = "Comprovante removido com sucesso!";
            $_SESSION['tipo_mensagem'] = "success";
        } else {
            $_SESSION['mensagem'] = "Você não tem permissão para remover este comprovante.";
            $_SESSION['tipo_mensagem'] = "error";
        }
    }

    header("Location: projeto_detalhes.php?id=$projeto_id");
    exit;
}

// Remover tarefa (apenas para criador)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remover']) && isset($_POST['remover_id']) && $eh_criador) {
    $rem_id = intval($_POST['remover_id']);

    // Antes de remover, apagar o comprovante se existir
    $stmt = $pdo->prepare("SELECT comprovante FROM tarefas WHERE id = ?");
    $stmt->execute([$rem_id]);
    $tarefa = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($tarefa && $tarefa['comprovante'] && file_exists($tarefa['comprovante'])) {
        unlink($tarefa['comprovante']);
    }

    $stmt = $pdo->prepare("DELETE FROM tarefas WHERE id = ?");
    $stmt->execute([$rem_id]);

    $_SESSION['mensagem'] = "Tarefa removida com sucesso!";
    $_SESSION['tipo_mensagem'] = "success";

    header("Location: projeto_detalhes.php?id=$projeto_id");
    exit;
}

// Listar tarefas pendentes com informações do usuário designado
$stmtPendentes = $pdo->prepare("SELECT t.*, u.nome as nome_designado 
                               FROM tarefas t 
                               LEFT JOIN usuarios u ON t.designado_para = u.id 
                               WHERE t.projeto_id = ? AND t.concluida = 0 
                               ORDER BY t.prazo_conclusao ASC, t.criado_em DESC");
$stmtPendentes->execute([$projeto_id]);
$tarefas_pendentes = $stmtPendentes->fetchAll(PDO::FETCH_ASSOC);

// Listar tarefas concluídas com informações do usuário que concluiu e designado
$stmtConcluidas = $pdo->prepare("SELECT t.*, u.nome as nome_usuario, ud.nome as nome_designado 
                                FROM tarefas t 
                                LEFT JOIN usuarios u ON t.concluida_por = u.id 
                                LEFT JOIN usuarios ud ON t.designado_para = ud.id 
                                WHERE t.projeto_id = ? AND t.concluida = 1 
                                ORDER BY t.concluida_em DESC");
$stmtConcluidas->execute([$projeto_id]);
$tarefas_concluidas = $stmtConcluidas->fetchAll(PDO::FETCH_ASSOC);

// Dados do projeto
$stmt = $pdo->prepare("SELECT p.*, u.nome as nome_criador 
                      FROM projetos p 
                      JOIN usuarios u ON p.criador_id = u.id 
                      WHERE p.id = ?");
$stmt->execute([$projeto_id]);
$projeto = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Projeto: <?= htmlspecialchars($projeto['titulo']) ?></title>
    <!-- Bootstrap 5 -->
    <link href="assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="assets/fontawsome/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="stile.css">
    <!-- Adicionar viewport para mobile -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .comprovante-container {
            max-height: 300px;
            overflow: hidden;
        }

        .comprovante-imagem {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        /* Para garantir que o modal fique bem ajustado */
        .modal-improved .modal-body {
            max-height: 80vh;
            overflow-y: auto;
        }
        
        /* Ajustes específicos para mobile */
        @media (max-width: 768px) {
            .container-fluid .row.g-0 .col-md-6 {
                padding: 1rem !important;
            }
            
            .modal-improved .modal-dialog {
                margin: 10px;
            }
            
            .comprovante-imagem {
                max-height: 200px;
            }
        }
    </style>
</head>

<body class="container2 mt-4">

  <!-- Botão Menu Mobile -->
  <button class="mobile-menu-btn mobile-only" id="mobileMenuBtn">
      <i class="fa fa-bars"></i>
  </button>

  <!-- Overlay para fechar menu -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

 <div class="sidebar" id="sidebar">
        <h4 class="text-center">Menu</h4>
        <a href="index.php"><i class="fa fa-home"></i> Dashboard</a>
        <a href="projetos.php"><i class="fa fa-folder"></i> Projetos</a>
        <a href="notas.php"><i class="fa fa-sticky-note"></i> Notas</a>
        <a href="usuarios.php"><i class="fa fa-users"></i> Usuários</a>

    
    <!-- Menu do usuário -->
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

  <!-- CONTEÚDO PRINCIPAL COM CLASSE CORRETA -->
  <div class="content">
    <div class="container mt-4">
        <h1>Projeto: <?= htmlspecialchars($projeto['titulo']) ?></h1>
        <div class="criador-projeto">
            <i class="fa fa-user"></i> Criado por: <?= htmlspecialchars($projeto['nome_criador']) ?>
        </div>
        <p>Descrição do projeto: <?= htmlspecialchars($projeto['descricao']) ?></p>
        
        <!-- Seção de Membros do Projeto - SEMPRE VISÍVEL -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fa fa-users me-2"></i>Membros do Projeto</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($membros_projeto as $membro): ?>
                        <?php 
                        $badge_class = 'bg-primary';
                        $icon = 'fa-user';
                        
                        // Destacar o criador do projeto
                        if ($membro['id'] == $projeto['criador_id']) {
                            $badge_class = 'bg-success';
                            $icon = 'fa-crown';
                        }
                        // Destacar o usuário atual
                        elseif ($membro['id'] == $_SESSION['usuario_id']) {
                            $badge_class = 'bg-info';
                            $icon = 'fa-user-circle';
                        }
                        ?>
                        <span class="badge <?= $badge_class ?> p-2 d-flex align-items-center">
                            <i class="fa <?= $icon ?> me-1"></i>
                            <?= htmlspecialchars($membro['nome']) ?>
                            <?php if ($membro['id'] == $projeto['criador_id']): ?>
                                <span class="ms-1">(Criador)</span>
                            <?php elseif ($membro['id'] == $_SESSION['usuario_id']): ?>
                                <span class="ms-1">(Você)</span>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3 text-muted small">
                    <i class="fa fa-info-circle me-1"></i>
                    Total de <?= count($membros_projeto) ?> membro(s) participando deste projeto.
                </div>
            </div>
        </div>

        <!-- Seção de Tarefas Pendentes -->
        <h2>
            Tarefas Pendentes
            <span class="badge bg-warning badge-contador"><?= count($tarefas_pendentes) ?></span>
        </h2>

        <?php if (empty($tarefas_pendentes)): ?>
            <div class="alert alert-info">
                Nenhuma tarefa pendente.
            </div>
        <?php else: ?>
            <?php foreach ($tarefas_pendentes as $t): ?>
                <li class="list-group">
                    <div class="linha">
                        <div>
                            <?= htmlspecialchars($t['titulo']) ?>
                            <?php if ($t['prazo_conclusao']): ?>
                                <span class="badge bg-secondary ms-2">
                                    <i class="fa fa-calendar me-1"></i><?= date('d/m/Y', strtotime($t['prazo_conclusao'])) ?>
                                    <?php if (strtotime($t['prazo_conclusao']) < strtotime('today')): ?>
                                        <span class="badge bg-danger ms-1">Atrasada</span>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="acoes-tarefa">
                            <!-- Botão Detalhes -->
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                data-bs-target="#modalDetalhes<?= $t['id'] ?>">
                                Detalhes
                            </button>
                            <?php if ($eh_criador): ?>
                                <!-- Botão Remover só aparece para o criador -->
                                <form method="post" style="margin:0;" onsubmit="return confirm('Deletar tarefa?');">
                                    <input type="hidden" name="remover_id" value="<?= $t['id'] ?>">
                                    <button class="btn btn-sm btn-danger" name="remover" value="1">Remover</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Seção de Tarefas Concluídas -->
        <h2>
            Tarefas Concluídas
            <span class="badge bg-success badge-contador"><?= count($tarefas_concluidas) ?></span>
        </h2>

        <?php if (empty($tarefas_concluidas)): ?>
            <div class="alert alert-info">
                Nenhuma tarefa concluída.
            </div>
        <?php else: ?>
            <?php foreach ($tarefas_concluidas as $t): ?>
                <li class="list-group">
                    <div class="linha">
                        <div>
                            <span class="text-success me-2">✅</span>
                            <?= htmlspecialchars($t['titulo']) ?>
                        </div>
                        <div class="acoes-tarefa">
                            <!-- Botão Detalhes -->
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                data-bs-target="#modalDetalhes<?= $t['id'] ?>">
                                Detalhes
                            </button>
                            <?php if ($eh_criador): ?>
                                <!-- Botão Remover só aparece para o criador -->
                                <form method="post" style="margin:0;" onsubmit="return confirm('Deletar tarefa?');">
                                    <input type="hidden" name="remover_id" value="<?= $t['id'] ?>">
                                    <button class="btn btn-sm btn-danger" name="remover" value="1">Remover</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Modais DEVEM ficar FORA das listas -->
        <?php foreach ($tarefas_pendentes as $t): ?>
            <!-- Modal para Detalhes da Tarefa Pendente -->
            <div class="modal fade modal-improved" id="modalDetalhes<?= $t['id'] ?>" tabindex="-1"
                aria-labelledby="modalLabel<?= $t['id'] ?>" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel<?= $t['id'] ?>">
                                <i class="fa fa-tasks me-2"></i><?= htmlspecialchars($t['titulo']) ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-0">
                            <div class="container-fluid">
                                <div class="row g-0">
                                    <!-- Coluna da Esquerda - Informações da Tarefa -->
                                    <div class="col-md-6 p-4">
                                        <div class="modal-section mb-4">
                                            <h6><i class="fa fa-align-left me-2"></i>Descrição</h6>
                                            <p class="mb-0">
                                                <?= !empty($t['descricao']) ? htmlspecialchars($t['descricao']) : 'Nenhuma descrição fornecida.' ?>
                                            </p>
                                        </div>

                                        <?php if ($t['prazo_conclusao']): ?>
                                            <div class="modal-section mb-4">
                                                <h6><i class="fa fa-calendar me-2"></i>Prazo de Conclusão</h6>
                                                <p class="mb-0">
                                                    <?= date('d/m/Y', strtotime($t['prazo_conclusao'])) ?>
                                                    <?php if (strtotime($t['prazo_conclusao']) < strtotime('today')): ?>
                                                        <span class="badge bg-danger ms-2">Atrasada</span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($t['nome_designado']): ?>
                                            <div class="modal-section mb-4">
                                                <h6><i class="fa fa-user me-2"></i>Designado para</h6>
                                                <p class="mb-0 text-info">
                                                    <i class="fa fa-user-tag me-1"></i>
                                                    <?= htmlspecialchars($t['nome_designado']) ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>

                                        <div class="modal-section">
                                            <h6><i class="fa fa-info-circle me-2"></i>Status</h6>
                                            <span class="status-badge status-pendente">
                                                <i class="fa fa-clock me-1"></i> Pendente
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Coluna da Direita - Comprovante -->
                                    <div class="col-md-6 bg-light p-4">
                                        <div class="modal-section">
                                            <h6><i class="fa fa-paperclip me-2"></i>Comprovante de Conclusão</h6>
                                            <div class="alert alert-info mb-4">
                                                <i class="fa fa-info-circle me-2"></i>
                                                Para marcar esta tarefa como concluída, envie um comprovante abaixo:
                                            </div>
                                            <form method="post" enctype="multipart/form-data">
                                                <input type="hidden" name="comprovante_tarefa_id" value="<?= $t['id'] ?>">
                                                <div class="mb-3">
                                                    <label for="comprovante<?= $t['id'] ?>" class="form-label">
                                                        <i class="fa fa-upload me-1"></i>Enviar comprovante (imagem)
                                                    </label>
                                                    <input type="file" class="form-control form-control-lg" id="comprovante<?= $t['id'] ?>"
                                                        name="comprovante_imagem" accept="image/*" required>
                                                    <div class="form-text mt-2">
                                                        <i class="fa fa-lightbulb me-1"></i>
                                                        A tarefa será automaticamente marcada como concluída após o envio do
                                                        comprovante.
                                                    </div>
                                                </div>
                                                <button type="submit" class="btn btn-success btn-lg w-100 py-2 mt-3">
                                                    <i class="fa fa-check me-1"></i> Enviar Comprovante e Concluir Tarefa
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fa fa-times me-1"></i> Fechar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php foreach ($tarefas_concluidas as $t): ?>
            <!-- Modal para Detalhes da Tarefa Concluída -->
            <div class="modal fade modal-improved" id="modalDetalhes<?= $t['id'] ?>" tabindex="-1"
                aria-labelledby="modalLabel<?= $t['id'] ?>" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel<?= $t['id'] ?>">
                                <i class="fa fa-tasks me-2"></i><?= htmlspecialchars($t['titulo']) ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-0">
                            <div class="container-fluid">
                                <div class="row g-0">
                                    <!-- Coluna da Esquerda - Informações da Tarefa -->
                                    <div class="col-md-6 p-4">
                                        <div class="modal-section mb-4">
                                            <h6><i class="fa fa-align-left me-2"></i>Descrição</h6>
                                            <p class="mb-0">
                                                <?= !empty($t['descricao']) ? htmlspecialchars($t['descricao']) : 'Nenhuma descrição fornecida.' ?>
                                            </p>
                                        </div>

                                        <?php if ($t['prazo_conclusao']): ?>
                                            <div class="modal-section mb-4">
                                                <h6><i class="fa fa-calendar me-2"></i>Prazo de Conclusão</h6>
                                                <p class="mb-0">
                                                    <?= date('d/m/Y', strtotime($t['prazo_conclusao'])) ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($t['nome_designado']): ?>
                                            <div class="modal-section mb-4">
                                                <h6><i class="fa fa-user me-2"></i>Designado para</h6>
                                                <p class="mb-0 text-info">
                                                    <i class="fa fa-user-tag me-1"></i>
                                                    <?= htmlspecialchars($t['nome_designado']) ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>

                                        <div class="modal-section mb-4">
                                            <h6><i class="fa fa-info-circle me-2"></i>Status</h6>
                                            <span class="status-badge status-concluido">
                                                <i class="fa fa-check me-1"></i> Concluída
                                            </span>
                                        </div>

                                        <?php if (!empty($t['nome_usuario'])): ?>
                                            <div class="modal-section mb-4">
                                                <h6><i class="fa fa-user me-2"></i>Concluída por</h6>
                                                <p class="mb-0 text-info">
                                                    <i class="fa fa-user-check me-1"></i>
                                                    <?= htmlspecialchars($t['nome_usuario']) ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($t['concluida_em'])): ?>
                                            <div class="modal-section">
                                                <h6><i class="fa fa-calendar-check me-2"></i>Data de Conclusão</h6>
                                                <p class="mb-0 text-success">
                                                    <i class="fa fa-clock me-1"></i>
                                                    <?= date('d/m/Y H:i', strtotime($t['concluida_em'])) ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Coluna da Direita - Comprovante -->
                                    <div class="col-md-6 bg-light p-4">
                                        <div class="modal-section h-100">
                                            <h6><i class="fa fa-paperclip me-2"></i>Comprovante de Conclusão</h6>
                                            <?php if (!empty($t['comprovante'])): ?>
                                                <div class="comprovante-container h-100 d-flex flex-column">
                                                    <div class="text-center mb-3">
                                                        <p class="mb-2"><i class="fa fa-image me-1"></i>Comprovante enviado:</p>
                                                    </div>
                                                    <div class="flex-grow-1 d-flex align-items-center justify-content-center mb-3 comprovante-container">
                                                        <img src="<?= $t['comprovante'] ?>" alt="Comprovante" class="comprovante-imagem rounded shadow">
                                                    </div>
                                                    <div class="mt-auto">
                                                        <?php
                                                        // Verifica se o usuário atual pode remover o comprovante
                                                        $pode_remover_comprovante = ($eh_criador || $t['concluida_por'] == $_SESSION['usuario_id']);
                                                        ?>

                                                        <?php if ($pode_remover_comprovante): ?>
                                                            <form method="post" class="mt-2">
                                                                <input type="hidden" name="tarefa_id" value="<?= $t['id'] ?>">
                                                                <button type="submit" name="remover_comprovante" value="1"
                                                                    class="btn btn-warning w-100 py-2"
                                                                    onclick="return confirm('Remover comprovante e marcar tarefa como pendente?')">
                                                                    <i class="fa fa-times me-1"></i> Remover Comprovante
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <div class="alert alert-info mt-2">
                                                                <i class="fa fa-info-circle me-2"></i>
                                                                Apenas o criador do projeto ou quem enviou o comprovante pode removê-lo.
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-warning h-100 d-flex align-items-center justify-content-center">
                                                    <div class="text-center">
                                                        <i class="fa fa-exclamation-triangle fa-2x mb-2"></i>
                                                        <p class="mb-0">Esta tarefa está marcada como concluída,<br>mas não possui comprovante.</p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fa fa-times me-1"></i> Fechar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($eh_criador): ?>
            <!-- Formulário de nova tarefa só aparece para o criador -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fa fa-plus-circle me-2"></i>Nova Tarefa</h3>
                </div>
                <div class="card-body">
                    <form method="post" class="form-custom">
                        <div class="mb-3">
                            <label for="titulo" class="form-label">Título da tarefa</label>
                            <input type="text" name="titulo" id="titulo" placeholder="Título da tarefa" required class="form-control">
                        </div>
                        
                        <div class="mb-3">
                            <label for="descricao" class="form-label">Descrição</label>
                            <textarea name="descricao" id="descricao" placeholder="Descrição da tarefa" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="designado_para" class="form-label">Designar para (opcional)</label>
                                    <select name="designado_para" id="designado_para" class="form-select">
                                        <option value="">-- Não designar para ninguém --</option>
                                        <?php foreach ($membros_projeto as $membro): ?>
                                            <option value="<?= $membro['id'] ?>">
                                                <?= htmlspecialchars($membro['nome']) ?>
                                                <?php if ($membro['id'] == $_SESSION['usuario_id']): ?>
                                                    (Você)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="prazo_conclusao" class="form-label">Prazo de conclusão (opcional)</label>
                                    <input type="date" name="prazo_conclusao" id="prazo_conclusao" class="form-control">
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-plus me-1"></i> Adicionar Tarefa
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <a href="projetos.php" class="btn btn-secondary mt-3">
            <i class="fa fa-arrow-left me-1"></i> Voltar para Projetos
        </a>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="assets/bootstrap/js/bootstrap.min.js"></script>

  <script>
    // ADICIONAR SCRIPTS DE CONTROLE DO MENU MOBILE (igual ao do index.php)
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

      // Fechar menu ao clicar no overlay
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
        
        // Fechar menu ao clicar fora
        document.addEventListener('click', function(event) {
          if (!userMenuToggle.contains(event.target) && !userDropdown.contains(event.target)) {
            userDropdown.classList.remove('show');
            userMenuChevron.classList.add('fa-chevron-up');
            userMenuChevron.classList.remove('fa-chevron-down');
          }
        });
      }

      // Fechar dropdowns ao redimensionar a janela
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

    // Configurar data mínima para o campo de prazo (hoje)
    const prazoInput = document.getElementById('prazo_conclusao');
    if (prazoInput) {
        const today = new Date().toISOString().split('T')[0];
        prazoInput.min = today;
    }
  </script>
</body>

</html>
