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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $user_id_to_delete = (int)($_POST['user_id'] ?? 0);
    if ($user_id_to_delete !== (int)$_SESSION['user_id']) {

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        if ($stmt->execute([$user_id_to_delete])) {
            $_SESSION['success_msg'] = "Student account deleted successfully.";
        } else {
            $_SESSION['error_msg'] = "Failed to delete student account.";
        }
    } else {
        $_SESSION['error_msg'] = "You cannot delete your own admin account.";
    }
    redirect('students.php');
}

$users = $pdo->query("SELECT * FROM users ORDER BY (role = 'admin') DESC, created_at DESC")->fetchAll();
$total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - NSBM StudyCircle Admin</title>
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
                <li><a href="students.php" class="active"><span class="icon">👥</span> Manage Students</a></li>
                <li><a href="notes.php"><span class="icon">📚</span> Manage Notes</a></li>
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
                    <h1>Manage Students</h1>
                    <p>Review, search, and moderate registered student accounts.</p>
                </div>
                <div>
                    <span style="background: #ECFDF5; color: #047857; padding: 6px 14px; border-radius: 50px; font-weight: 800; font-size: 0.9rem; border: 1px solid #A7F3D0;">
                        👥 <?= $total_students ?> Active Students
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
                        <span>👥</span>
                        <h2>Student Accounts Directory</h2>
                        <span class="section-counter"><?= count($users) ?> Total</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Student Details</th>
                                <th>Email</th>
                                <th>Batch & Faculty</th>
                                <th>Role</th>
                                <th>Joined Date</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td style="font-weight: 700; color: #94A3B8;">#<?= $u['id'] ?></td>
                                <td>
                                    <strong style="color: #0F172A; font-size: 0.95rem;"><?= htmlspecialchars($u['name']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <?php if (!empty($u['batch'])): ?>
                                        <span class="badge-batch"><?= htmlspecialchars($u['batch']) ?></span>
                                    <?php endif; ?>
                                    <span style="color: #64748B; font-size: 0.82rem; font-weight: 600;"><?= htmlspecialchars($u['faculty'] ?? 'Computing') ?></span>
                                </td>
                                <td>
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span class="badge badge-admin">👑 Admin</span>
                                    <?php else: ?>
                                        <span class="badge badge-student">Student</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: #64748B; font-size: 0.85rem;"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                <td style="text-align: right;">
                                    <?php if ($u['role'] !== 'admin'): ?>
                                    <form method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Are you sure you want to delete student <?= htmlspecialchars($u['name'], ENT_QUOTES) ?>? All their notes and posts will also be removed.');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn-action-delete" title="Delete this student account">
                                            🗑 Delete
                                        </button>
                                    </form>
                                    <?php else: ?>
                                        <span style="color: #94A3B8; font-size: 0.78rem; font-weight: 700;">Active Account</span>
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

