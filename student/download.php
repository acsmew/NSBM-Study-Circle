<?php
require_once '../includes/functions.php';
requireStudent();

if (!isset($_GET['id'])) {
    redirect('index.php');
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM materials WHERE id = ? AND status = 'approved'");
$stmt->execute([$id]);
$material = $stmt->fetch();

if (!$material) {
    die("Material not found or not approved.");
}

$raw_path = $material['file_path'];
if (strpos($raw_path, 'uploads/') === 0) {
    $filepath = "../" . $raw_path;
} else {
    $filepath = "../uploads/" . $raw_path;
}

if (file_exists($filepath)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
} else {
    die("File not found on the server.");
}
?>
