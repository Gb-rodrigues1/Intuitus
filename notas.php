<?php
require_once "config.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

$usuario_id = $_SESSION['usuario_id'];
$mensagem = "";

// Verificar se há mensagem na URL (após redirecionamento)
if (isset($_GET['mensagem'])) {
    $mensagem = $_GET['mensagem'];
}

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

// Cria nova nota
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['titulo']) && !isset($_POST['editar_nota'])) {
    $titulo = trim($_POST['titulo']);
    $conteudo = trim($_POST['conteudo'] ?? '');
    $projeto_id = !empty($_POST['projeto_id']) ? intval($_POST['projeto_id']) : NULL;
    $categoria = trim($_POST['categoria'] ?? 'Geral');
    $prioridade = $_POST['prioridade'] ?? 'media';
    $cor = '#ffffff'; // Cor fixa
    $tipo = $_POST['tipo'] ?? 'texto';

    if (!empty($titulo)) {
        // Verifica se o usuário tem acesso ao projeto (se estiver vinculando a um projeto)
        if ($projeto_id) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM projetos p 
                LEFT JOIN usuarios_projetos up ON p.id = up.projeto_id 
                WHERE p.id = ? AND (p.criador_id = ? OR up.usuario_id = ?)
            ");
            $stmt->execute([$projeto_id, $usuario_id, $usuario_id]);
            $tem_acesso = $stmt->fetchColumn();
            
            if (!$tem_acesso) {
                $mensagem = "Você não tem acesso a este projeto.";
                $projeto_id = NULL; // Não vincular a projeto sem acesso
            }
        }

        $stmt = $pdo->prepare("INSERT INTO notas (titulo, conteudo, projeto_id, categoria, prioridade, cor, tipo, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$titulo, $conteudo, $projeto_id, $categoria, $prioridade, $cor, $tipo, $usuario_id])) {
            header("Location: notas.php?mensagem=" . urlencode("Nota criada com sucesso!"));
            exit;
        } else {
            $mensagem = "Erro ao criar a nota.";
        }
    }
}

// Editar nota
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['editar_nota'])) {
    $nota_id = intval($_POST['nota_id']);
    $titulo = trim($_POST['titulo']);
    $conteudo = trim($_POST['conteudo'] ?? '');
    $projeto_id = !empty($_POST['projeto_id']) ? intval($_POST['projeto_id']) : NULL;
    $categoria = trim($_POST['categoria'] ?? 'Geral');
    $prioridade = $_POST['prioridade'] ?? 'media';
    $cor = '#ffffff'; // Cor fixa

    // Verifica se a nota pertence ao usuário OU se o usuário é dono do projeto da nota
    $stmt = $pdo->prepare("
        SELECT n.id, n.usuario_id, p.criador_id, p.id as projeto_id
        FROM notas n 
        LEFT JOIN projetos p ON n.projeto_id = p.id 
        WHERE n.id = ? AND (n.usuario_id = ? OR p.criador_id = ?)
    ");
    $stmt->execute([$nota_id, $usuario_id, $usuario_id]);
    $nota_info = $stmt->fetch();
    
    if ($nota_info) {
        // Verifica acesso ao projeto (se estiver vinculando a um projeto diferente)
        if ($projeto_id && $projeto_id != $nota_info['projeto_id']) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM projetos p 
                LEFT JOIN usuarios_projetos up ON p.id = up.projeto_id 
                WHERE p.id = ? AND (p.criador_id = ? OR up.usuario_id = ?)
            ");
            $stmt->execute([$projeto_id, $usuario_id, $usuario_id]);
            $tem_acesso = $stmt->fetchColumn();
            
            if (!$tem_acesso) {
                $mensagem = "Você não tem acesso a este projeto.";
                $projeto_id = $nota_info['projeto_id']; // Manter o projeto original
            }
        }

        // Update no banco de dados
        $stmt = $pdo->prepare("UPDATE notas SET titulo = ?, conteudo = ?, projeto_id = ?, categoria = ?, prioridade = ?, cor = ?, atualizado_em = NOW() WHERE id = ?");
        
        if ($stmt->execute([$titulo, $conteudo, $projeto_id, $categoria, $prioridade, $cor, $nota_id])) {
            header("Location: notas.php?mensagem=" . urlencode("Nota atualizada com sucesso!"));
            exit;
        } else {
            $mensagem = "Erro ao atualizar a nota.";
        }
    } else {
        $mensagem = "Você não tem permissão para editar esta nota.";
    }
}

// Excluir nota
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['excluir_nota'])) {
    $nota_id = intval($_POST['nota_id']);

    // Verifica se a nota pertence ao usuário OU se o usuário é dono do projeto
    $stmt = $pdo->prepare("
        SELECT n.id, n.usuario_id, p.criador_id 
        FROM notas n 
        LEFT JOIN projetos p ON n.projeto_id = p.id 
        WHERE n.id = ? AND (n.usuario_id = ? OR p.criador_id = ?)
    ");
    $stmt->execute([$nota_id, $usuario_id, $usuario_id]);
    $nota_info = $stmt->fetch();
    
    if ($nota_info) {
        $stmt = $pdo->prepare("DELETE FROM notas WHERE id = ?");
        if ($stmt->execute([$nota_id])) {
            header("Location: notas.php?mensagem=" . urlencode("Nota excluída com sucesso!"));
            exit;
        } else {
            $mensagem = "Erro ao excluir a nota.";
        }
    } else {
        $mensagem = "Você não tem permissão para excluir esta nota.";
    }
}

// Marcar/desmarcar como concluída
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['toggle_concluida'])) {
    $nota_id = intval($_POST['nota_id']);

    // Verifica se a nota pertence ao usuário OU se o usuário é dono do projeto
    $stmt = $pdo->prepare("
        SELECT n.id, n.concluida, n.usuario_id, p.criador_id 
        FROM notas n 
        LEFT JOIN projetos p ON n.projeto_id = p.id 
        WHERE n.id = ? AND (n.usuario_id = ? OR p.criador_id = ?)
    ");
    $stmt->execute([$nota_id, $usuario_id, $usuario_id]);
    $nota_info = $stmt->fetch();
    
    if ($nota_info) {
        $nova_concluida = $nota_info['concluida'] ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE notas SET concluida = ?, atualizado_em = NOW() WHERE id = ?");
        if ($stmt->execute([$nova_concluida, $nota_id])) {
            header("Location: notas.php?mensagem=" . urlencode($nova_concluida ? "Nota marcada como concluída!" : "Nota reaberta!"));
            exit;
        } else {
            $mensagem = "Erro ao atualizar a nota.";
        }
    } else {
        $mensagem = "Você não tem permissão para modificar esta nota.";
    }
}

// Busca projetos do usuário para o select (incluindo projetos compartilhados)
$stmt_projetos = $pdo->prepare("
    SELECT p.id, p.titulo 
    FROM projetos p 
    JOIN usuarios_projetos up ON p.id = up.projeto_id 
    WHERE up.usuario_id = ? 
    ORDER BY p.titulo
");
$stmt_projetos->execute([$usuario_id]);
$projetos = $stmt_projetos->fetchAll(PDO::FETCH_ASSOC);

// Busca notas do usuário E notas dos projetos compartilhados
$categoria_filtro = $_GET['categoria'] ?? '';
$projeto_filtro = $_GET['projeto'] ?? '';
$prioridade_filtro = $_GET['prioridade'] ?? '';

$sql = "SELECT n.*, p.titulo as projeto_titulo, p.criador_id as projeto_criador_id,
               u.nome as autor_nome,
               CASE 
                   WHEN n.usuario_id = ? THEN 1 
                   ELSE 0 
               END as eh_minha_nota,
               CASE 
                   WHEN p.criador_id = ? THEN 1 
                   ELSE 0 
               END as eh_dono_projeto
        FROM notas n 
        LEFT JOIN projetos p ON n.projeto_id = p.id 
        LEFT JOIN usuarios u ON n.usuario_id = u.id
        WHERE (
            -- Notas criadas pelo próprio usuário E que estão em projetos que ele tem acesso
            (n.usuario_id = ? AND n.projeto_id IN (
                SELECT up.projeto_id 
                FROM usuarios_projetos up 
                WHERE up.usuario_id = ?
            ))
            -- OU notas sem projeto (do próprio usuário)
            OR (n.usuario_id = ? AND n.projeto_id IS NULL)
            -- OU notas de projetos onde o usuário é o dono (vê todas as notas do seu projeto)
            OR (p.criador_id = ?)
            -- OU notas de projetos que o usuário tem acesso como participante (incluindo notas do dono)
            OR (n.projeto_id IN (
                SELECT up.projeto_id 
                FROM usuarios_projetos up 
                WHERE up.usuario_id = ?
            ))
        )";

$params = [$usuario_id, $usuario_id, $usuario_id, $usuario_id, $usuario_id, $usuario_id, $usuario_id];
$types = "iiiiiii";

if ($categoria_filtro) {
    $sql .= " AND n.categoria = ?";
    $params[] = $categoria_filtro;
    $types .= "s";
}

if ($projeto_filtro) {
    $sql .= " AND n.projeto_id = ?";
    $params[] = $projeto_filtro;
    $types .= "i";
}

if ($prioridade_filtro) {
    $sql .= " AND n.prioridade = ?";
    $params[] = $prioridade_filtro;
    $types .= "s";
}

$sql .= " ORDER BY n.concluida ASC, n.prioridade DESC, n.atualizado_em DESC";

$stmt_notas = $pdo->prepare($sql);
$stmt_notas->execute($params);
$notas = $stmt_notas->fetchAll(PDO::FETCH_ASSOC);

// Busca categorias únicas para o filtro (apenas das notas que o usuário tem acesso)
$stmt_categorias = $pdo->prepare("
    SELECT DISTINCT categoria 
    FROM notas 
    WHERE usuario_id = ? OR projeto_id IN (
        SELECT up.projeto_id 
        FROM usuarios_projetos up 
        WHERE up.usuario_id = ?
    ) OR projeto_id IN (
        SELECT id 
        FROM projetos 
        WHERE criador_id = ?
    )
    ORDER BY categoria
");
$stmt_categorias->execute([$usuario_id, $usuario_id, $usuario_id]);
$categorias = $stmt_categorias->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notas - Sistema de Projetos</title>
    <link href="assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/fontawsome/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="stile.css">
    
    
</head>

<body>

  <button class="mobile-menu-btn mobile-only" id="mobileMenuBtn">
      <i class="fa fa-bars"></i>
  </button>
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

    <div class="main-content">
        <div class="content">
            <?php if ($mensagem): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($mensagem) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <h1 class="mb-4">Minhas Notas</h1>

            <!-- Barra de Ferramentas -->
            <div class="notes-toolbar">
                <div class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaNota">
                            <i class="fa fa-plus"></i> Nova Nota
                        </button>
                    </div>
                    <div class="col-md-8">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-3">

                                <!-- Filtros -->
                                <select class="form-select form-select-sm" onchange="filtrarNotas()" id="filtroCategoria">
                                    <option value="">Todas categorias</option>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>" <?= $categoria_filtro === $cat ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select form-select-sm" onchange="filtrarNotas()" id="filtroProjeto">
                                    <option value="">Todos projetos</option>
                                    <?php foreach ($projetos as $proj): ?>
                                        <option value="<?= $proj['id'] ?>" <?= $projeto_filtro == $proj['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($proj['titulo']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select form-select-sm" onchange="filtrarNotas()" id="filtroPrioridade">
                                    <option value="">Todas prioridades</option>
                                    <option value="alta" <?= $prioridade_filtro === 'alta' ? 'selected' : '' ?>>Alta</option>
                                    <option value="media" <?= $prioridade_filtro === 'media' ? 'selected' : '' ?>>Média</option>
                                    <option value="baixa" <?= $prioridade_filtro === 'baixa' ? 'selected' : '' ?>>Baixa</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="text" class="form-control form-control-sm" placeholder="Buscar..." id="buscarNotas" onkeyup="buscarNotas()">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grid de Notas -->
            <div class="notes-grid" id="notesGrid">
                <?php if (empty($notas)): ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <i class="fa fa-info-circle"></i> Nenhuma nota encontrada. Crie sua primeira nota!
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($notas as $nota): ?>
                        <?php $temProjeto = !empty($nota['projeto_id']); ?>
                        <div class="note-card <?= $nota['concluida'] ? 'concluida' : '' ?> <?= $nota['prioridade'] ?> <?= !$temProjeto ? 'sem-projeto' : '' ?>"
                             data-titulo="<?= htmlspecialchars(strtolower($nota['titulo'])) ?>"
                             data-conteudo="<?= htmlspecialchars(strtolower($nota['conteudo'])) ?>"
                             onclick="<?= $temProjeto ? "abrirProjeto({$nota['projeto_id']})" : "" ?>">
                            <div class="note-header">
                                <div class="note-title"><?= htmlspecialchars($nota['titulo']) ?></div>
                                <span class="note-priority badge bg-<?= 
                                    $nota['prioridade'] === 'alta' ? 'danger' : 
                                    ($nota['prioridade'] === 'media' ? 'warning' : 'success') 
                                ?>">
                                    <?= ucfirst($nota['prioridade']) ?>
                                </span>
                            </div>
                            
                            <?php if ($nota['categoria'] !== 'Geral'): ?>
                                <div class="note-category mb-2"><?= htmlspecialchars($nota['categoria']) ?></div>
                            <?php endif; ?>
                            
                            <div class="note-content">
                                <?= nl2br(htmlspecialchars($nota['conteudo'])) ?>
                            </div>
                            
                            <div class="note-footer">
                                <div class="note-info">
                                <?php if ($temProjeto): ?>
                                    <div>
                                        <small>
                                            <i class="fa fa-folder"></i> Projeto: <?= htmlspecialchars($nota['projeto_titulo']) ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                    <?php if (!$nota['eh_minha_nota']): ?>
                                        <div><small><i class="fa fa-user"></i> Por: <?= htmlspecialchars($nota['autor_nome']) ?></small></div>
                                    <?php endif; ?>
                                    <div><small><?= date('d/m/Y H:i', strtotime($nota['atualizado_em'])) ?></small></div>
                                </div>
                                <div class="note-actions">
                                    <?php if ($nota['eh_minha_nota'] || $nota['eh_dono_projeto']): ?>
                                        <form method="POST" style="display:inline;" onclick="event.stopPropagation()">
                                            <input type="hidden" name="nota_id" value="<?= $nota['id'] ?>">
                                            <input type="hidden" name="toggle_concluida" value="1">
                                            <button type="submit" class="btn btn-sm btn-<?= $nota['concluida'] ? 'warning' : 'success' ?>">
                                                <i class="fa fa-<?= $nota['concluida'] ? 'undo' : 'check' ?>"></i>
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-primary btn-editar" 
                                                onclick="event.stopPropagation(); editarNota(<?= $nota['id'] ?>, '<?= htmlspecialchars(addslashes($nota['titulo'])) ?>', `<?= htmlspecialchars(addslashes(str_replace(["\r", "\n"], '', $nota['conteudo']))) ?>`, <?= $nota['projeto_id'] ?: 'null' ?>, '<?= htmlspecialchars(addslashes($nota['categoria'])) ?>', '<?= htmlspecialchars($nota['prioridade']) ?>')">
                                            <i class="fa fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="event.stopPropagation(); return confirm('Excluir esta nota?')">
                                            <input type="hidden" name="nota_id" value="<?= $nota['id'] ?>">
                                            <input type="hidden" name="excluir_nota" value="1">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php if (!$nota['eh_minha_nota'] && $nota['eh_dono_projeto']): ?>
                                            <span class="badge bg-info" title="Nota de participante - Você é dono do projeto">Dono</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Nota compartilhada</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Nova Nota -->
    <div class="modal fade" id="modalNovaNota" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nova Nota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Título *</label>
                                <input type="text" name="titulo" class="form-control" required maxlength="255">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Projeto</label>
                                <select name="projeto_id" class="form-control">
                                    <option value="">Sem projeto</option>
                                    <?php foreach ($projetos as $proj): ?>
                                        <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['titulo']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Conteúdo</label>
                                <textarea name="conteudo" class="form-control" rows="8" placeholder="Digite o conteúdo da sua nota..."></textarea>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Categoria</label>
                                <input type="text" name="categoria" class="form-control" value="Geral" list="categorias">
                                <datalist id="categorias">
                                    <option value="Geral">
                                    <option value="Ideias">
                                    <option value="Lembretes">
                                    <option value="Tarefas">
                                    <option value="Reuniões">
                                    <option value="Projeto">
                                    <option value="Pessoal">
                                </datalist>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Prioridade</label>
                                <select name="prioridade" class="form-control">
                                    <option value="baixa">Baixa</option>
                                    <option value="media" selected>Média</option>
                                    <option value="alta">Alta</option>
                                </select>
                            </div>
                            
                            <input type="hidden" name="tipo" value="texto">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Criar Nota</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Nota -->
    <div class="modal fade" id="modalEditarNota" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Nota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="editar_nota" value="1">
                    <input type="hidden" name="nota_id" id="editNotaId">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Título *</label>
                                <input type="text" name="titulo" id="editTitulo" class="form-control" required maxlength="255">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Projeto</label>
                                <select name="projeto_id" id="editProjetoId" class="form-control">
                                    <option value="">Sem projeto</option>
                                    <?php foreach ($projetos as $proj): ?>
                                        <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['titulo']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Conteúdo</label>
                                <textarea name="conteudo" id="editConteudo" class="form-control" rows="8"></textarea>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Categoria</label>
                                <input type="text" name="categoria" id="editCategoria" class="form-control" list="categorias">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Prioridade</label>
                                <select name="prioridade" id="editPrioridade" class="form-control">
                                    <option value="baixa">Baixa</option>
                                    <option value="media">Média</option>
                                    <option value="alta">Alta</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="assets/bootstrap/js/bootstrap.min.js"></script>
    
    <script>
        // Controle do menu mobile
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
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
        
        // Fecha menu ao clicar fora
        document.addEventListener('click', function(event) {
            if (!userMenuToggle.contains(event.target) && !userDropdown.contains(event.target)) {
                userDropdown.classList.remove('show');
                userMenuChevron.classList.add('fa-chevron-up');
                userMenuChevron.classList.remove('fa-chevron-down');
            }
        });

        function editarNota(id, titulo, conteudo, projetoId, categoria, prioridade) {
            document.getElementById('editNotaId').value = id;
            document.getElementById('editTitulo').value = titulo;
            document.getElementById('editConteudo').value = conteudo;
            document.getElementById('editCategoria').value = categoria;
            document.getElementById('editPrioridade').value = prioridade;
            
            if (projetoId) {
                document.getElementById('editProjetoId').value = projetoId;
            } else {
                document.getElementById('editProjetoId').value = '';
            }
            
            var modal = new bootstrap.Modal(document.getElementById('modalEditarNota'));
            modal.show();
        }

        function filtrarNotas() {
            const categoria = document.getElementById('filtroCategoria').value;
            const projeto = document.getElementById('filtroProjeto').value;
            const prioridade = document.getElementById('filtroPrioridade').value;
            
            let url = 'notas.php?';
            const params = [];
            
            if (categoria) params.push('categoria=' + encodeURIComponent(categoria));
            if (projeto) params.push('projeto=' + encodeURIComponent(projeto));
            if (prioridade) params.push('prioridade=' + encodeURIComponent(prioridade));
            
            window.location.href = url + params.join('&');
        }

        function buscarNotas() {
            const termo = document.getElementById('buscarNotas').value.toLowerCase();
            const notas = document.querySelectorAll('.note-card');
            
            notas.forEach(nota => {
                const titulo = nota.getAttribute('data-titulo');
                const conteudo = nota.getAttribute('data-conteudo');
                
                if (titulo.includes(termo) || conteudo.includes(termo)) {
                    nota.style.display = 'block';
                } else {
                    nota.style.display = 'none';
                }
            });
        }

        function abrirProjeto(projetoId) {
            window.location.href = 'projeto_detalhes.php?id=' + projetoId;
        }
    </script>
</body>
</html>
