<?php
require_once 'config.php';

// Verifica login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
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

$id_usuario = $_SESSION['usuario_id'];
$mensagem = "";

// Verificar se há mensagem de convite para mostrar no modal
$convite_mensagem = "";
$convite_tipo = "";
if (isset($_SESSION['convite_mensagem'])) {
    $convite_mensagem = $_SESSION['convite_mensagem'];
    $convite_tipo = $_SESSION['convite_tipo'];
    // Limpar a mensagem da sessão após usar
    unset($_SESSION['convite_mensagem']);
    unset($_SESSION['convite_tipo']);
}

// Processar remoção de conexão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remover']) && isset($_POST['remover_id'])) {
    $rem_id = intval($_POST['remover_id']);

    // Primeiro, buscar os projetos que foram compartilhados através desta conexão
    $stmt = $pdo->prepare("
        SELECT DISTINCT up.projeto_id 
        FROM usuarios_projetos up 
        INNER JOIN projetos p ON up.projeto_id = p.id 
        WHERE up.usuario_id = ? AND p.criador_id = ?
    ");
    $stmt->execute([$id_usuario, $rem_id]);
    $projetos_compartilhados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Remover o usuário dos projetos do outro usuário
    foreach ($projetos_compartilhados as $projeto) {
        $stmt = $pdo->prepare("DELETE FROM usuarios_projetos WHERE usuario_id = ? AND projeto_id = ?");
        $stmt->execute([$id_usuario, $projeto['projeto_id']]);
    }

    // Também remover o usuário atual dos projetos do outro usuário (caso ele tenha sido adicionado)
    $stmt = $pdo->prepare("
        SELECT DISTINCT up.projeto_id 
        FROM usuarios_projetos up 
        INNER JOIN projetos p ON up.projeto_id = p.id 
        WHERE up.usuario_id = ? AND p.criador_id = ?
    ");
    $stmt->execute([$rem_id, $id_usuario]);
    $projetos_do_usuario_atual = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($projetos_do_usuario_atual as $projeto) {
        $stmt = $pdo->prepare("DELETE FROM usuarios_projetos WHERE usuario_id = ? AND projeto_id = ?");
        $stmt->execute([$rem_id, $projeto['projeto_id']]);
    }

    // Remover os convites/compartilhamentos entre os usuários
    $stmt = $pdo->prepare("DELETE FROM convites_compartilhamento WHERE (remetente_id = ? AND destinatario_id = ?) OR (remetente_id = ? AND destinatario_id = ?)");
    $stmt->execute([$id_usuario, $rem_id, $rem_id, $id_usuario]);

    // Agora remover a conexão
    $stmt = $pdo->prepare("DELETE FROM usuarios_conexoes WHERE id_usuario = ? AND id_conectado = ?");
    $stmt->execute([$_SESSION['usuario_id'], $rem_id]);

    // Também remover a conexão inversa (se existir)
    $stmt = $pdo->prepare("DELETE FROM usuarios_conexoes WHERE id_usuario = ? AND id_conectado = ?");
    $stmt->execute([$rem_id, $_SESSION['usuario_id']]);

    $mensagem = "Conexão removida com sucesso! Os projetos compartilhados foram retirados.";
    header('Location: usuarios.php');
    exit;
}

// Processar resposta do convite (aceitar/recusar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_convite'])) {
    $convite_id = intval($_POST['convite_id']);
    $acao = $_POST['acao_convite'];

    if ($acao === 'aceitar') {
        // Atualizar status do convite e data de aceitação
        $stmt = $pdo->prepare("UPDATE convites_compartilhamento SET status = 'aceito', aceito_em = NOW() WHERE id = ? AND destinatario_id = ?");
        $stmt->execute([$convite_id, $id_usuario]);

        // Adicionar à lista de conexões
        $stmt = $pdo->prepare("SELECT remetente_id, projeto_id FROM convites_compartilhamento WHERE id = ?");
        $stmt->execute([$convite_id]);
        $convite = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($convite) {
            // Verificar se já não existe a conexão (em ambas as direções)
            $stmt = $pdo->prepare("SELECT * FROM usuarios_conexoes WHERE id_usuario = ? AND id_conectado = ?");
            $stmt->execute([$id_usuario, $convite['remetente_id']]);

            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO usuarios_conexoes (id_usuario, id_conectado) VALUES (?, ?)");
                $stmt->execute([$id_usuario, $convite['remetente_id']]);
            }

            // Também criar a conexão inversa
            $stmt = $pdo->prepare("SELECT * FROM usuarios_conexoes WHERE id_usuario = ? AND id_conectado = ?");
            $stmt->execute([$convite['remetente_id'], $id_usuario]);

            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO usuarios_conexoes (id_usuario, id_conectado) VALUES (?, ?)");
                $stmt->execute([$convite['remetente_id'], $id_usuario]);
            }

            // Adicionar usuário ao projeto (se necessário)
            $stmt = $pdo->prepare("SELECT * FROM usuarios_projetos WHERE usuario_id = ? AND projeto_id = ?");
            $stmt->execute([$id_usuario, $convite['projeto_id']]);

            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO usuarios_projetos (usuario_id, projeto_id) VALUES (?, ?)");
                $stmt->execute([$id_usuario, $convite['projeto_id']]);
            }

            $mensagem = "Convite aceito com sucesso!";
        }
    } elseif ($acao === 'recusar') {
        $stmt = $pdo->prepare("UPDATE convites_compartilhamento SET status = 'recusado' WHERE id = ? AND destinatario_id = ?");
        $stmt->execute([$convite_id, $id_usuario]);
        $mensagem = "Convite recusado.";
    }

    header('Location: usuarios.php');
    exit;
}

// Buscar projetos criados pelo usuário logado
$stmt = $pdo->prepare("SELECT id, titulo FROM projetos WHERE criador_id = ?");
$stmt->execute([$id_usuario]);
$projetos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Processar inclusão de usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);

    if (!empty($email)) {
        // Verifica se existe usuário com esse email
        $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            $id_conectado = $usuario['id'];

            if ($id_conectado == $id_usuario) {
                $mensagem = "Você não pode se adicionar.";
            } else {
                // Verifica se já está na lista
                $stmt = $pdo->prepare("SELECT * FROM usuarios_conexoes WHERE id_usuario = ? AND id_conectado = ?");
                $stmt->execute([$id_usuario, $id_conectado]);

                if ($stmt->fetch()) {
                    $mensagem = "Usuário já está na sua lista de conexões.";
                } else {
                    // Adicionar conexão em ambas as direções
                    $stmt = $pdo->prepare("INSERT INTO usuarios_conexoes (id_usuario, id_conectado) VALUES (?, ?)");
                    $stmt->execute([$id_usuario, $id_conectado]);

                    $stmt = $pdo->prepare("INSERT INTO usuarios_conexoes (id_usuario, id_conectado) VALUES (?, ?)");
                    $stmt->execute([$id_conectado, $id_usuario]);

                    $mensagem = "Conexão adicionada com sucesso!";
                }
            }
        } else {
            $mensagem = "Usuário não encontrado.";
        }
    }
}

// Buscar conexões atuais com informações dos projetos compartilhados
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.nome,
        u.email,
        uc.criado_em as data_conexao,
        (
            SELECT GROUP_CONCAT(DISTINCT p.titulo SEPARATOR ', ')
            FROM convites_compartilhamento cc
            INNER JOIN projetos p ON cc.projeto_id = p.id
            WHERE ((cc.remetente_id = ? AND cc.destinatario_id = u.id) OR 
                   (cc.remetente_id = u.id AND cc.destinatario_id = ?))
            AND cc.status = 'aceito'
        ) as projetos_compartilhados
    FROM usuarios u
    INNER JOIN usuarios_conexoes uc ON u.id = uc.id_conectado
    WHERE uc.id_usuario = ?
");
$stmt->execute([$id_usuario, $id_usuario, $id_usuario]);
$conexoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar convites enviados (pendentes)
$stmt = $pdo->prepare("SELECT c.*, u.nome as destinatario_nome, u.email as destinatario_email, p.titulo as projeto_titulo
    FROM convites_compartilhamento c
    INNER JOIN usuarios u ON c.destinatario_id = u.id
    INNER JOIN projetos p ON c.projeto_id = p.id
    WHERE c.remetente_id = ? AND c.status = 'pendente'");
$stmt->execute([$id_usuario]);
$convites_enviados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar convites recebidos (pendentes)
$stmt = $pdo->prepare("SELECT c.*, u.nome as remetente_nome, u.email as remetente_email, p.titulo as projeto_titulo
    FROM convites_compartilhamento c
    INNER JOIN usuarios u ON c.remetente_id = u.id
    INNER JOIN projetos p ON c.projeto_id = p.id
    WHERE c.destinatario_id = ? AND c.status = 'pendente'");
$stmt->execute([$id_usuario]);
$convites_recebidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Gerenciar Conexões</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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

    <div class="content">
        <?php if ($mensagem): ?>
            <div class="alert alert-info"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>

        <!-- Modal de Resultado do Convite -->
        <div class="modal fade" id="modalConvite" tabindex="-1" aria-labelledby="modalConviteLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalConviteLabel">
                            <?php echo $convite_tipo === 'sucesso' ? 'Convite Enviado' : 'Erro no Envio'; ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex align-items-center">
                            <?php if ($convite_tipo === 'sucesso'): ?>
                                <i class="fas fa-check-circle text-success me-3 fs-4"></i>
                            <?php else: ?>
                                <i class="fas fa-exclamation-circle text-danger me-3 fs-4"></i>
                            <?php endif; ?>
                            <span><?= htmlspecialchars($convite_mensagem) ?></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-<?= $convite_tipo === 'sucesso' ? 'success' : 'danger' ?>"
                            data-bs-dismiss="modal">Fechar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Convites Recebidos (Pendentes) -->
        <?php if (count($convites_recebidos) > 0): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3>Convites Recebidos</h3>
                </div>
                <div class="card-body">
                    <?php foreach ($convites_recebidos as $convite): ?>
                        <div class="list-group mb-2">
                            <div class="linha">
                                <div>
                                    <strong><?= htmlspecialchars($convite['remetente_nome']) ?></strong>
                                    (<?= htmlspecialchars($convite['remetente_email']) ?>)<br>
                                    <small>Projeto: <?= htmlspecialchars($convite['projeto_titulo']) ?></small><br>
                                    <small>Enviado em: <?= date('d/m/Y H:i', strtotime($convite['criado_em'])) ?></small>
                                </div>
                                <div class="acoes-tarefa">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="convite_id" value="<?= $convite['id'] ?>">
                                        <input type="hidden" name="acao_convite" value="aceitar">
                                        <button type="submit" class="btn btn-success btn-sm">Aceitar</button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="convite_id" value="<?= $convite['id'] ?>">
                                        <input type="hidden" name="acao_convite" value="recusar">
                                        <button type="submit" class="btn btn-danger btn-sm">Recusar</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Convites Enviados (Pendentes) -->
        <?php if (count($convites_enviados) > 0): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3>Convites Enviados (Pendentes)</h3>
                </div>
                <div class="card-body">
                    <?php foreach ($convites_enviados as $convite): ?>
                        <div class="list-group mb-2">
                            <div class="linha">
                                <div>
                                    <strong><?= htmlspecialchars($convite['destinatario_nome']) ?></strong>
                                    (<?= htmlspecialchars($convite['destinatario_email']) ?>)<br>
                                    <small>Projeto: <?= htmlspecialchars($convite['projeto_titulo']) ?></small><br>
                                    <small>Enviado em: <?= date('d/m/Y H:i', strtotime($convite['criado_em'])) ?></small>
                                </div>
                                <span class="badge bg-warning">Aguardando resposta</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Minhas Conexões -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>Minhas Conexões</h3>
            </div>
            <div class="card-body">
                <?php if (count($conexoes) > 0): ?>
                    <?php foreach ($conexoes as $c): ?>
                        <li class="list-group">
                            <div class="linha">
                                <div>
                                    <strong><?= htmlspecialchars($c['nome']) ?></strong>
                                    (<?= htmlspecialchars($c['email']) ?>)<br>
                                    <?php if (!empty($c['projetos_compartilhados'])): ?>
                                        <small>Projetos compartilhados:
                                            <?= htmlspecialchars($c['projetos_compartilhados']) ?></small><br>
                                    <?php endif; ?>
                                    <small>Conectado desde: <?= date('d/m/Y H:i', strtotime($c['data_conexao'])) ?></small>
                                </div>
                                <form method="post" style="margin:0;"
                                    onsubmit="return confirm('Remover conexão? Isso também removerá o acesso aos projetos compartilhados.');">
                                    <input type="hidden" name="remover_id" value="<?= $c['id'] ?>">
                                    <button class="btn btn-sm btn-danger" name="remover" value="1">Remover</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Você ainda não possui conexões.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Enviar Convite para Projeto -->
        <div class="card">
            <div class="card-header">
                <h3>Adicionar Conexão</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="enviar_convite.php" id="formConvite">
                    <div class="mb-3">
                        <label for="projeto" class="form-label">Selecione o projeto:</label>
                        <select id="projeto" name="projeto_id" class="form-select" required>
                            <option value="">-- Escolha um projeto --</option>
                            <?php if (!empty($projetos)): ?>
                                <?php foreach ($projetos as $proj): ?>
                                    <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['titulo']) ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option disabled>Você não tem projetos para compartilhar.</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="email_destinatario" class="form-label">Email do destinatário:</label>
                        <input type="email" id="email_destinatario" name="email_destinatario" class="form-control"
                            required placeholder="email@exemplo.com">
                    </div>
                    <button type="submit" class="btn btn-primary">Enviar Convite</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Script para mostrar o modal automaticamente -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            <?php if (!empty($convite_mensagem)): ?>
                var modal = new bootstrap.Modal(document.getElementById('modalConvite'));
                modal.show();
            <?php endif; ?>
        });

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