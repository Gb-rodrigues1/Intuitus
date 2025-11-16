<?php
// Funções úteis para o sistema

// Verifica se o usuário está logado
function verificarLogin()
{
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Conta total de tarefas do usuário
function contarTarefasUsuario($pdo, $usuario_id)
{
    $sql = "SELECT COUNT(*) FROM tarefas t 
            JOIN projetos p ON t.projeto_id = p.id 
            JOIN membros_projeto mp ON p.id = mp.projeto_id 
            WHERE mp.usuario_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id]);
    return $stmt->fetchColumn();
}

// Conta tarefas por status do usuário
function contarTarefasPorStatus($pdo, $usuario_id, $status)
{
    $sql = "SELECT COUNT(*) FROM tarefas t 
            JOIN projetos p ON t.projeto_id = p.id 
            JOIN membros_projeto mp ON p.id = mp.projeto_id 
            WHERE mp.usuario_id = ? AND t.status = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id, $status]);
    return $stmt->fetchColumn();
}

// Busca projetos do usuário
function buscarProjetosUsuario($pdo, $usuario_id)
{
    $sql = "SELECT DISTINCT p.*, u.nome as dono_nome 
            FROM projetos p 
            JOIN membros_projeto mp ON p.id = mp.projeto_id 
            JOIN usuarios u ON p.dono_id = u.id
            WHERE mp.usuario_id = ? 
            ORDER BY p.criado_em DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id]);
    return $stmt->fetchAll();
}

// Conta tarefas de um projeto por status
function contarTarefasProjeto($pdo, $projeto_id, $status = null)
{
    if ($status) {
        $sql = "SELECT COUNT(*) FROM tarefas WHERE projeto_id = ? AND status = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$projeto_id, $status]);
    } else {
        $sql = "SELECT COUNT(*) FROM tarefas WHERE projeto_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$projeto_id]);
    }
    return $stmt->fetchColumn();
}

// Verifica se usuário tem acesso ao projeto
function verificarAcessoProjeto($pdo, $usuario_id, $projeto_id)
{
    $sql = "SELECT COUNT(*) FROM membros_projeto WHERE usuario_id = ? AND projeto_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id, $projeto_id]);
    return $stmt->fetchColumn() > 0;
}

// Busca dados do usuário
function buscarUsuario($pdo, $usuario_id)
{
    $sql = "SELECT * FROM usuarios WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id]);
    return $stmt->fetch();
}

/* Cria convite de compartilhamento */
function enviarConviteCompartilhamento($projetoId, $remetenteId, $destinatarioId, $conn)
{
    $sql = "INSERT INTO convites_compartilhamento (projeto_id, remetente_id, destinatario_id)
            VALUES (?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $projetoId, $remetenteId, $destinatarioId);

    if ($stmt->execute()) {
        return true;
    } else {
        return false;
    }
}

// Ver convites de compartilhamento
function listarConvitesPendentes($usuarioId, $conn)
{
    $sql = "SELECT c.id, c.criado_em, p.titulo AS projeto, u.nome AS remetente
            FROM convites_compartilhamento c
            JOIN projetos p ON p.id = c.projeto_id
            JOIN usuarios u ON u.id = c.remetente_id
            WHERE c.destinatario_id = ? AND c.status = 'pendente'";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $usuarioId);
    $stmt->execute();
    $result = $stmt->get_result();

    $convites = [];
    while ($row = $result->fetch_assoc()) {
        $convites[] = $row;
    }

    return $convites;
}

// Aceita convite de compartilhamento
function aceitarConvite($conviteId, $usuarioId, $conn)
{
    // 1. Atualiza o status do convite
    $sql = "UPDATE convites_compartilhamento 
            SET status = 'aceito' 
            WHERE id = ? AND destinatario_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $conviteId, $usuarioId);
    $stmt->execute();

    // 2. Pega dados do convite para inserir no compartilhamento
    $sql = "SELECT projeto_id FROM convites_compartilhamento 
            WHERE id = ? AND status = 'aceito'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $conviteId);
    $stmt->execute();
    $result = $stmt->get_result();
    $convite = $result->fetch_assoc();

    if ($convite) {
        $projetoId = $convite['projeto_id'];

        // 3. Insere em usuarios_projetos (compartilhamento efetivo)
        $sql = "INSERT IGNORE INTO usuarios_projetos (usuario_id, projeto_id)
                VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $usuarioId, $projetoId);
        return $stmt->execute();
    }

    return false;
}

// Recusa convite de compartilhamento
function recusarConvite($conviteId, $usuarioId, $conn)
{
    $sql = "UPDATE convites_compartilhamento 
            SET status = 'recusado' 
            WHERE id = ? AND destinatario_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $conviteId, $usuarioId);

    return $stmt->execute();
}

?>
