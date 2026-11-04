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

$total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$total_materials = $pdo->query("SELECT COUNT(*) FROM materials")->fetchColumn();
$total_forum = $pdo->query("SELECT COUNT(*) FROM forum_posts")->fetchColumn();
$total_groups = $pdo->query("SELECT COUNT(*) FROM study_groups")->fetchColumn();

$recent_students = $pdo->query("SELECT * FROM users WHERE role = 'student' ORDER BY created_at DESC LIMIT 5")->fetchAll();

$recent_notes = $pdo->query("
    SELECT m.*, u.name as uploader_name, s.name as subject_name
    FROM materials m
    LEFT JOIN users u ON m.user_id = u.id
    LEFT JOIN subjects s ON m.subject_id = s.id
    ORDER BY m.created_at DESC LIMIT 5
")->fetchAll();

$recent_forum = $pdo->query("
    SELECT p.*, u.name as author_name, s.name as subject_name,
           (SELECT COUNT(*) FROM forum_replies WHERE post_id = p.id) as reply_count
    FROM forum_posts p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN subjects s ON p.subject_id = s.id
    ORDER BY p.created_at DESC LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - NSBM StudyCircle</title>
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
                <li><a href="index.php" class="active"><span class="icon">📊</span> Dashboard</a></li>
                <li><a href="students.php"><span class="icon">👥</span> Manage Students</a></li>
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
                    <h1>Dashboard Overview</h1>
                    <p>Welcome to NSBM StudyCircle Central Administration Portal.</p>
                </div>
                <div class="admin-user-pill">
                    <div class="avatar-circle">A</div>
                    <div>
                        <div style="font-weight: 800; font-size: 0.9rem; color: #0F172A;"><?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?></div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: #059669;">Administrator</div>
                    </div>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="flash-alert flash-success"><span>✅</span> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="flash-alert flash-error"><span>⚠️</span> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="stats-grid">
                <a href="students.php" class="stat-card" title="Click to Manage Students">
                    <div class="stat-info">
                        <h3>Total Students</h3>
                        <div class="stat-value"><?= number_format($total_students) ?></div>
                    </div>
                    <div class="stat-icon-badge stat-badge-blue">👥</div>
                </a>

                <a href="notes.php" class="stat-card" title="Click to Manage Uploaded Notes">
                    <div class="stat-info">
                        <h3>Uploaded Notes</h3>
                        <div class="stat-value"><?= number_format($total_materials) ?></div>
                    </div>
                    <div class="stat-icon-badge stat-badge-green">📚</div>
                </a>

                <a href="forum.php" class="stat-card" title="Click to Manage Forum Discussions">
                    <div class="stat-info">
                        <h3>Forum Topics</h3>
                        <div class="stat-value"><?= number_format($total_forum) ?></div>
                    </div>
                    <div class="stat-icon-badge stat-badge-purple">💬</div>
                </a>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Study Groups</h3>
                        <div class="stat-value"><?= number_format($total_groups) ?></div>
                    </div>
                    <div class="stat-icon-badge stat-badge-orange">👥</div>
                </div>
            </div>

            <div class="overview-grid">

                <div class="overview-card">
                    <div class="card-header">
                        <h2><span>👥</span> Recent Students</h2>
                        <a href="students.php">Manage All Students →</a>
                    </div>
                    <ul class="item-list">
                        <?php if (empty($recent_students)): ?>
                            <li class="item-row" style="color: #94A3B8; text-align: center; justify-content: center;">No students registered yet.</li>
                        <?php endif; ?>
                        <?php foreach ($recent_students as $s): ?>
                        <li class="item-row">
                            <div>
                                <strong style="color: #0F172A; display: block; font-size: 0.95rem;"><?= htmlspecialchars($s['name']) ?></strong>
                                <span style="color: #64748B; font-size: 0.8rem;"><?= htmlspecialchars($s['email']) ?></span>
                            </div>
                            <div>
                                <span class="badge-batch"><?= htmlspecialchars($s['batch'] ?? '26.1') ?></span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="overview-card">
                    <div class="card-header">
                        <h2><span>📚</span> Recent Study Notes</h2>
                        <a href="notes.php">Manage All Notes →</a>
                    </div>
                    <ul class="item-list">
                        <?php if (empty($recent_notes)): ?>
                            <li class="item-row" style="color: #94A3B8; text-align: center; justify-content: center;">No study notes uploaded yet.</li>
                        <?php endif; ?>
                        <?php foreach ($recent_notes as $n): ?>
                        <li class="item-row">
                            <div>
                                <strong style="color: #0F172A; display: block; font-size: 0.95rem;"><?= htmlspecialchars($n['title']) ?></strong>
                                <span style="color: #64748B; font-size: 0.8rem;">By <?= htmlspecialchars($n['uploader_name'] ?: 'Student') ?> • <?= htmlspecialchars($n['subject_name'] ?: 'General') ?></span>
                            </div>
                            <div>
                                <span class="badge-approved"><?= ucfirst($n['status']) ?></span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="overview-card" style="grid-column: 1 / -1;">
                    <div class="card-header">
                        <h2><span>💬</span> Recent Forum Discussions</h2>
                        <a href="forum.php">Manage All Discussions →</a>
                    </div>
                    <ul class="item-list">
                        <?php if (empty($recent_forum)): ?>
                            <li class="item-row" style="color: #94A3B8; text-align: center; justify-content: center;">No forum discussions posted yet.</li>
                        <?php endif; ?>
                        <?php foreach ($recent_forum as $f): ?>
                        <li class="item-row">
                            <div>
                                <strong style="color: #0F172A; display: block; font-size: 0.95rem;"><?= htmlspecialchars($f['title']) ?></strong>
                                <span style="color: #64748B; font-size: 0.8rem;">By <?= htmlspecialchars($f['author_name'] ?: 'Student') ?> • <?= htmlspecialchars($f['subject_name'] ?: 'General') ?></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="background: #FAF5FF; color: #7C3AED; font-weight: 700; font-size: 0.75rem; padding: 2px 8px; border-radius: 50px;">
                                    💬 <?= $f['reply_count'] ?> Replies
                                </span>
                                <span style="color: #94A3B8; font-size: 0.8rem;"><?= date('M d, Y', strtotime($f['created_at'])) ?></span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

            </div>

        </main>
    </div>

    <script src="../assets/js/admin.js"></script>
</body>
</html>
