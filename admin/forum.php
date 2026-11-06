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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_forum_post') {
    $post_id_to_delete = (int)($_POST['post_id'] ?? 0);
    $chk = $pdo->prepare("SELECT media_url FROM forum_posts WHERE id = ?");
    $chk->execute([$post_id_to_delete]);
    $post = $chk->fetch();
    if ($post) {
        if (!empty($post['media_url'])) {
            $media = __DIR__ . '/../' . $post['media_url'];
            if (file_exists($media) && is_file($media)) {
                @unlink($media);
            }
        }
        $stmt = $pdo->prepare("DELETE FROM forum_posts WHERE id = ?");
        $stmt->execute([$post_id_to_delete]);
        $_SESSION['success_msg'] = "Forum discussion topic and its replies deleted successfully.";
    }
    redirect('forum.php');
}

$forum_posts = $pdo->query("
    SELECT p.*, u.name as author_name, u.batch as author_batch, s.name as subject_name,
           (SELECT COUNT(*) FROM forum_replies WHERE post_id = p.id) as reply_count
    FROM forum_posts p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN subjects s ON p.subject_id = s.id
    ORDER BY p.created_at DESC
")->fetchAll();

$total_forum = count($forum_posts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Forum - NSBM StudyCircle Admin</title>
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
                <li><a href="notes.php"><span class="icon">📚</span> Manage Notes</a></li>
                <li><a href="forum.php" class="active"><span class="icon">💬</span> Manage Forum</a></li>
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
                    <h1>Manage Forum Discussions</h1>
                    <p>Monitor community questions and delete spam or inappropriate topics.</p>
                </div>
                <div>
                    <span style="background: #FAF5FF; color: #7C3AED; padding: 6px 14px; border-radius: 50px; font-weight: 800; font-size: 0.88rem; border: 1px solid #E9D5FF;">
                        💬 <?= $total_forum ?> Total Discussions
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
                        <span>💬</span>
                        <h2>Community Forum Topics Directory</h2>
                        <span class="section-counter"><?= $total_forum ?> Topics</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Discussion Topic</th>
                                <th>Category / Subject</th>
                                <th>Author</th>
                                <th>Engagement</th>
                                <th>Posted Date</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($forum_posts)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2.5rem; color: #94A3B8;">No forum discussion topics posted yet.</td>
                            </tr>
                            <?php endif; ?>
                            <?php foreach ($forum_posts as $post): ?>
                            <tr>
                                <td>
                                    <strong style="color: #0F172A; font-size: 0.95rem;"><?= htmlspecialchars($post['title']) ?></strong>
                                    <div style="color: #64748B; font-size: 0.8rem; margin-top: 3px; max-width: 420px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?= htmlspecialchars(strip_tags($post['content'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="background: #F1F5F9; color: #334155; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 0.8rem;">
                                        <?= htmlspecialchars($post['subject_name'] ?: 'General') ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 700;"><?= htmlspecialchars($post['author_name'] ?: 'Student') ?></div>
                                    <?php if (!empty($post['author_batch'])): ?>
                                        <div style="font-size: 0.75rem; color: #64748B;"><?= htmlspecialchars($post['author_batch']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="background: #FAF5FF; color: #7C3AED; border: 1px solid #E9D5FF; padding: 2px 8px; border-radius: 50px; font-weight: 700; font-size: 0.78rem;">
                                        💬 <?= (int)$post['reply_count'] ?> Replies
                                    </span>
                                </td>
                                <td style="color: #64748B; font-size: 0.85rem;"><?= date('M d, Y', strtotime($post['created_at'])) ?></td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="../student/forum_view.php?id=<?= $post['id'] ?>" target="_blank" class="btn-action-view" style="margin-right: 6px;">
                                        👁 View ↗
                                    </a>
                                    <form method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Are you sure you want to delete this forum topic and all its replies?');">
                                        <input type="hidden" name="action" value="delete_forum_post">
                                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                        <button type="submit" class="btn-action-delete" title="Delete forum discussion topic">
                                            🗑 Delete
                                        </button>
                                    </form>
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

