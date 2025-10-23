<?php
require_once 'config.php';

// Verifica se usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['convite_mensagem'] = "Acesso negado.";
    $_SESSION['convite_tipo'] = "erro";
    header('Location: usuarios.php');
    exit;
}

$remetenteId = $_SESSION['usuario_id'];
$emailDestinatario = trim($_POST['email_destinatario']);
$projetoId = intval($_POST['projeto_id']);

if (empty($emailDestinatario) || empty($projetoId)) {
    $_SESSION['convite_mensagem'] = "Dados incompletos.";
    $_SESSION['convite_tipo'] = "erro";
    header('Location: usuarios.php');
    exit;
}

// Buscar ID do destinatário pelo e-mail
$stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE email = ?");
$stmt->execute([$emailDestinatario]);
$destinatario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$destinatario) {
    $_SESSION['convite_mensagem'] = "Usuário com esse e-mail não encontrado.";
    $_SESSION['convite_tipo'] = "erro";
    header('Location: usuarios.php');
    exit;
}

$destinatarioId = $destinatario['id'];
$destinatarioNome = $destinatario['nome'];

if ($destinatarioId == $remetenteId) {
    $_SESSION['convite_mensagem'] = "Você não pode enviar convite para si mesmo.";
    $_SESSION['convite_tipo'] = "erro";
    header('Location: usuarios.php');
    exit;
}

// Verificar se o remetente é o criador do projeto
$stmt = $pdo->prepare("SELECT titulo FROM projetos WHERE id = ? AND criador_id = ?");
$stmt->execute([$projetoId, $remetenteId]);
$projeto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$projeto) {
    $_SESSION['convite_mensagem'] = "Você não tem permissão para compartilhar este projeto.";
    $_SESSION['convite_tipo'] = "erro";
    header('Location: usuarios.php');
    exit;
}

$projetoTitulo = $projeto['titulo'];

// Verificar se já existe convite pendente
$stmt = $pdo->prepare("SELECT id FROM convites_compartilhamento 
                       WHERE projeto_id = ? AND destinatario_id = ? AND status = 'pendente'");
$stmt->execute([$projetoId, $destinatarioId]);

if ($stmt->rowCount() > 0) {
    $_SESSION['convite_mensagem'] = "Já existe um convite pendente para esse usuário.";
    $_SESSION['convite_tipo'] = "erro";
    header('Location: usuarios.php');
    exit;
}

// Verificar se já tem acesso via usuarios_projetos
$stmt = $pdo->prepare("SELECT 1 FROM usuarios_projetos WHERE projeto_id = ? AND usuario_id = ?");
$stmt->execute([$projetoId, $destinatarioId]);

if ($stmt->rowCount() > 0) {
    $_SESSION['convite_mensagem'] = "O usuário já tem acesso a esse projeto.";
    $_SESSION['convite_tipo'] = "erro";
    header('Location: usuarios.php');
    exit;
}

// Inserir convite
$stmt = $pdo->prepare("INSERT INTO convites_compartilhamento (projeto_id, remetente_id, destinatario_id)
                       VALUES (?, ?, ?)");
$success = $stmt->execute([$projetoId, $remetenteId, $destinatarioId]);

if ($success) {
    $_SESSION['convite_mensagem'] = "Convite enviado com sucesso para $destinatarioNome!";
    $_SESSION['convite_tipo'] = "sucesso";
} else {
    $_SESSION['convite_mensagem'] = "Erro ao enviar o convite.";
    $_SESSION['convite_tipo'] = "erro";
}

header('Location: usuarios.php');
exit;
?>