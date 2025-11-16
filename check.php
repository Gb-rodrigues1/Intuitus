<?php
require_once "config.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$acao = $_GET['acao'] ?? null;
$id = $_GET['id'] ?? null;
$projeto_id = $_GET['projeto'] ?? null;

// Este arquivo é usado apenas para marcar e desmarcar tarefas 

if ($acao === "desmarcar" && $id && $projeto_id) {
    // verifica se usuário pertence ao projeto
    $stmt = $pdo->prepare("SELECT * FROM usuarios_projetos WHERE usuario_id = ? AND projeto_id = ?");
    $stmt->execute([$_SESSION['usuario_id'], $projeto_id]);
    if (!$stmt->fetch()) {
        die("Acesso negado.");
    }

    // Desmarca a tarefa e remove comprovante
    $stmt = $pdo->prepare("UPDATE tarefas SET concluida = 0, comprovante = NULL WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: projeto_detalhes.php?id=$projeto_id");
exit;
