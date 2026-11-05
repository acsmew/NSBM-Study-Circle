<?php
require_once '../includes/functions.php';
requireAdmin();

$success = '';
$error = '';
if (isset($_SESSION['success_msg'])) {
    $success = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
if (isset($_SESSION['error_msg'])) {
    $error = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_material') {
    $material_id_to_delete = (int)($_POST['material_id'] ?? 0);
    $chk = $pdo->prepare("SELECT file_path FROM materials WHERE id = ?");
    $chk->execute([$material_id_to_delete]);
    $mat = $chk->fetch();
    if ($mat) {
        $file = __DIR__ . '/../' . $mat['file_path'];
        if (file_exists($file) && is_file($file)) {
            @unlink($file);
        }
        $stmt = $pdo->prepare("DELETE FROM materials WHERE id = ?");
        $stmt->execute([$material_id_to_delete]);
        $_SESSION['success_msg'] = "Study note deleted successfully.";
    }
    redirect('notes.php');
}

$materials = $pdo->query("
    SELECT m.*, u.name as uploader_name, u.batch as uploader_batch, s.name as subject_name
    FROM materials m
    LEFT JOIN users u ON m.user_id = u.id
    LEFT JOIN subjects s ON m.subject_id = s.id
    ORDER BY (m.status = 'pending') DESC, m.created_at DESC
")->fetchAll();

$total_materials = count($materials);
$pending_count = 0;
foreach ($materials as $m) {
    if ($m['status'] === 'pending') $pending_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Study Notes - NSBM StudyCircle Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

    <div class="admin-layout">

        <aside class="admin-sidebar">
            <div class="sidebar-brand">
                <img src="../assets/img/nsbm-logo.png" alt="NSBM">
                <div class="sidebar-brand-text">
                    <h2>NSBM</h2>
                    <span>Admin Control</span>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li><a href="index.php"><span class="icon">📊</span> Dashboard</a></li>
                <li><a href="students.php"><span class="icon">👥</span> Manage Students</a></li>
                <li><a href="notes.php" class="active"><span class="icon">📚</span> Manage Notes</a></li>
                <li><a href="forum.php"><span class="icon">💬</span> Manage Forum</a></li>
                <li><a href="../student/index.php" target="_blank"><span class="icon">🌐</span> Student View ↗</a></li>
            </ul>

            <div class="sidebar-footer">
                <a href="../logout.php" class="logout-btn">
                    <span>🚪</span> Sign Out
                </a>
            </div>
        </aside>

        <main class="admin-main">

            <div class="admin-header">
                <div>
                    <h1>Manage Study Notes</h1>
                    <p>Review, verify, approve, and delete shared study materials and past papers.</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <?php if ($pending_count > 0): ?>
                    <span style="background: #FFFBEB; color: #B45309; padding: 6px 14px; border-radius: 50px; font-weight: 800; font-size: 0.88rem; border: 1px solid #FDE68A;">
                        ⏳ <?= $pending_count ?> Pending Review
                    </span>
                    <?php endif; ?>
                    <span style="background: #ECFDF5; color: #047857; padding: 6px 14px; border-radius: 50px; font-weight: 800; font-size: 0.88rem; border: 1px solid #A7F3D0;">
                        📚 <?= $total_materials ?> Total Notes
                    </span>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="flash-alert flash-success"><span>✅</span> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="flash-alert flash-error"><span>⚠️</span> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="content-section">
                <div class="section-header">
                    <div class="section-header-title">
                        <span>📚</span>
                        <h2>All Uploaded Notes Repository</h2>
                        <span class="section-counter"><?= $total_materials ?> Materials</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Note Title</th>
                                <th>Subject</th>
                                <th>Uploader</th>
                                <th>Status</th>
                                <th>Uploaded Date</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($materials)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2.5rem; color: #94A3B8;">No study notes uploaded yet.</td>
                            </tr>
                            <?php endif; ?>
                            <?php foreach ($materials as $mat): ?>
                            <tr>
                                <td>
                                    <strong style="color: #0F172A; font-size: 0.95rem;"><?= htmlspecialchars($mat['title']) ?></strong>
                                    <?php if (!empty($mat['file_type'])): ?>
                                        <span style="font-size: 0.72rem; color: #94A3B8; font-weight: 700; text-transform: uppercase; margin-left: 4px;">(<?= htmlspecialchars($mat['file_type']) ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="background: #F1F5F9; color: #334155; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 0.8rem;">
                                        <?= htmlspecialchars($mat['subject_name'] ?: 'General') ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 700;"><?= htmlspecialchars($mat['uploader_name'] ?: 'Unknown') ?></div>
                                    <?php if (!empty($mat['uploader_batch'])): ?>
                                        <div style="font-size: 0.75rem; color: #64748B;"><?= htmlspecialchars($mat['uploader_batch']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($mat['status'] === 'approved'): ?>
                                        <span class="badge badge-approved">✓ Approved</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending">⏳ Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: #64748B; font-size: 0.85rem;"><?= date('M d, Y', strtotime($mat['created_at'])) ?></td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <?php if ($mat['status'] === 'pending'): ?>
                                        <a href="approve.php?action=approve&id=<?= $mat['id'] ?>" class="btn-action-approve">✓ Approve</a>
                                        <a href="approve.php?action=reject&id=<?= $mat['id'] ?>" class="btn-action-delete" style="text-decoration: none;">✕ Reject</a>
                                    <?php else: ?>
                                        <a href="../<?= htmlspecialchars($mat['file_path']) ?>" download class="btn-action-view" style="margin-right: 6px;">⬇ View</a>
                                        <form method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Are you sure you want to permanently delete this note? The file will be unlinked from the server.');">
                                            <input type="hidden" name="action" value="delete_material">
                                            <input type="hidden" name="material_id" value="<?= $mat['id'] ?>">
                                            <button type="submit" class="btn-action-delete" title="Permanently delete note">
                                                🗑 Delete
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <script src="../assets/js/admin.js"></script>
</body>
</html>

