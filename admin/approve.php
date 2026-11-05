<?php
require_once '../includes/functions.php';
requireAdmin();

if (isset($_GET['id']) && isset($_GET['action'])) {
    $id = $_GET['id'];
    $action = $_GET['action'];

    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE materials SET status = 'approved' WHERE id = ?");
        $stmt->execute([$id]);
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE materials SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$id]);
    }
}

redirect('index.php');
?>
