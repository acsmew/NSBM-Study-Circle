<?php
require_once '../includes/functions.php';
requireStudent();

$error = '';
$success = '';

if (!isset($_GET['id'])) {
    redirect('forum.php');
}
$post_id = (int)$_GET['id'];

if (isset($_SESSION['forum_success'])) {
    $success = $_SESSION['forum_success'];
    unset($_SESSION['forum_success']);
}
if (isset($_SESSION['forum_error'])) {
    $error = $_SESSION['forum_error'];
    unset($_SESSION['forum_error']);
}

$user_stmt = $pdo->prepare("SELECT id, name, email, batch, faculty FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$current_user = $user_stmt->fetch();
$current_batch = $current_user['batch'] ?? '26.1';
$current_faculty = $current_user['faculty'] ?? 'Computing';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'react') {
    header('Content-Type: application/json');
    $reaction_type = $_POST['reaction_type'] ?? 'like';
    $valid_reactions = ['like', 'love', 'haha', 'wow', 'sad'];

    if (!in_array($reaction_type, $valid_reactions)) {
        echo json_encode(['success' => false, 'message' => 'Invalid reaction']);
        exit();
    }

    $stmt = $pdo->prepare("SELECT id, reaction_type FROM forum_reactions WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$post_id, $_SESSION['user_id']]);
    $existing = $stmt->fetch();

    $user_action = '';
    if ($existing) {
        if ($existing['reaction_type'] === $reaction_type) {
            $del = $pdo->prepare("DELETE FROM forum_reactions WHERE id = ?");
            $del->execute([$existing['id']]);
            $user_action = 'removed';
            $current_reaction = null;
        } else {
            $upd = $pdo->prepare("UPDATE forum_reactions SET reaction_type = ? WHERE id = ?");
            $upd->execute([$reaction_type, $existing['id']]);
            $user_action = 'updated';
            $current_reaction = $reaction_type;
        }
    } else {
        $ins = $pdo->prepare("INSERT INTO forum_reactions (post_id, user_id, reaction_type) VALUES (?, ?, ?)");
        $ins->execute([$post_id, $_SESSION['user_id'], $reaction_type]);
        $user_action = 'added';
        $current_reaction = $reaction_type;
    }

    $counts_stmt = $pdo->prepare("SELECT reaction_type, COUNT(*) as cnt FROM forum_reactions WHERE post_id = ? GROUP BY reaction_type");
    $counts_stmt->execute([$post_id]);
    $counts_raw = $counts_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $total_reactions = array_sum($counts_raw);

    echo json_encode([
        'success' => true,
        'action' => $user_action,
        'current_reaction' => $current_reaction,
        'total' => $total_reactions
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_reply') {
    $content = trim($_POST['content'] ?? '');

    if (empty($content)) {
        $_SESSION['forum_error'] = "Reply cannot be empty.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO forum_replies (post_id, user_id, content) VALUES (?, ?, ?)");
        if ($stmt->execute([$post_id, $_SESSION['user_id'], $content])) {
            $_SESSION['forum_success'] = "Reply posted successfully!";
        } else {
            $_SESSION['forum_error'] = "Failed to post reply.";
        }
    }
    redirect("forum_view.php?id=" . $post_id);
}

$stmt = $pdo->prepare("SELECT f.*,
                             u.name as author_name,
                             u.batch as author_batch,
                             u.faculty as author_faculty,
                             s.name as subject_name,
                             (SELECT COUNT(*) FROM forum_reactions WHERE post_id = f.id) as reaction_count,
                             (SELECT reaction_type FROM forum_reactions WHERE post_id = f.id AND user_id = ?) as user_reaction
                      FROM forum_posts f
                      LEFT JOIN users u ON f.user_id = u.id
                      LEFT JOIN subjects s ON f.subject_id = s.id
                      WHERE f.id = ?");
$stmt->execute([$_SESSION['user_id'], $post_id]);
$post = $stmt->fetch();

if (!$post) {
    die("Post not found.");
}

$stmt = $pdo->prepare("SELECT r.*,
                             u.name as author_name,
                             u.batch as author_batch,
                             u.faculty as author_faculty
                      FROM forum_replies r
                      LEFT JOIN users u ON r.user_id = u.id
                      WHERE r.post_id = ?
                      ORDER BY r.created_at ASC");
$stmt->execute([$post_id]);
$replies = $stmt->fetchAll();

$user_react = $post['user_reaction'] ?? null;
$react_label = 'Like';
$react_icon = '👍';
$react_class = '';
if ($user_react === 'like') { $react_label = 'Liked'; $react_icon = '👍'; $react_class = 'reacted-like'; }
elseif ($user_react === 'love') { $react_label = 'Love'; $react_icon = '❤️'; $react_class = 'reacted-love'; }
elseif ($user_react === 'haha') { $react_label = 'Haha'; $react_icon = '😆'; $react_class = 'reacted-haha'; }
elseif ($user_react === 'wow')  { $react_label = 'Wow';  $react_icon = '😮'; $react_class = 'reacted-wow'; }
elseif ($user_react === 'sad')  { $react_label = 'Sad';  $react_icon = '😢'; $react_class = 'reacted-sad'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['title']) ?> - Forum</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/forum.css">
</head>
<body>

    <nav class="navbar">
        <div class="nav-left">
            <button class="btn-toggle-sidebar" id="sidebarToggle">☰</button>
            <div class="nav-brand">
                <img src="../assets/img/nsbm-logo.png" alt="NSBM" style="height: 40px; margin-right: 8px;">
                <div style="display: flex; flex-direction: column; line-height: 1.1;">
                    <span style="font-weight: 800; color: #1E293B; font-size: 1.2rem; letter-spacing: 0.5px;">NSBM</span>
                    <span style="font-weight: 700; color: var(--accent-green); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px;">Study Circle</span>
                </div>
            </div>
        </div>
        <div class="nav-right">
            <a href="materials.php" class="btn-upload">Upload Note</a>
            <div class="user-profile">
                <div class="avatar-small"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
                <a href="../logout.php" style="margin-left: 0.5rem;">🚪</a>
            </div>
        </div>
    </nav>

    <div class="app-container">

        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="index.php" class="sidebar-link"><span class="sidebar-icon">🏠</span><span class="sidebar-text">Home</span></a></li>
                <li><a href="forum.php" class="sidebar-link active"><span class="sidebar-icon">💬</span><span class="sidebar-text">Forum</span></a></li>
                <li><a href="groups.php" class="sidebar-link"><span class="sidebar-icon">👥</span><span class="sidebar-text">Study Groups</span></a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="forum-view-wrapper">
                <a href="forum.php" style="display: inline-flex; align-items: center; gap: 6px; margin-bottom: 1.25rem; color: #64748B; font-weight: 700;">
                    &larr; Back to Forum Feed
                </a>

                <?php if($error): ?><div style="padding: 1rem; background: #FEE2E2; color: #991B1B; border-radius: 8px; margin-bottom: 1.25rem; font-weight: 700;"><?= $error ?></div><?php endif; ?>
                <?php if($success): ?><div style="padding: 1rem; background: #D1FAE5; color: #065F46; border-radius: 8px; margin-bottom: 1.25rem; font-weight: 700;"><?= $success ?></div><?php endif; ?>

                <article class="main-post-card">

                    <div class="author-info-bar">
                        <div class="author-details">
                            <div class="avatar-circle">
                                <?= strtoupper(substr($post['author_name'] ?? 'U', 0, 1)) ?>
                            </div>
                            <div>
                                <div style="font-size: 1.15rem; font-weight: 800; color: #0F172A;">
                                    <?= htmlspecialchars($post['author_name']) ?>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px; margin-top: 2px;">
                                    <span class="badge-batch"><?= htmlspecialchars($post['author_batch'] ?? '26.1') ?></span>
                                    <span class="badge-faculty"><?= htmlspecialchars($post['author_faculty'] ?? 'Computing') ?></span>
                                    <span style="color:#CBD5E1;">•</span>
                                    <span style="font-size: 0.8rem; color: #94A3B8; font-weight: 600;"><?= timeAgo($post['created_at']) ?></span>
                                </div>
                            </div>
                        </div>
                        <span style="background: #F8FAFC; color: #475569; border: 1px solid #E2E8F0; padding: 4px 12px; border-radius: 50px; font-size: 0.8rem; font-weight: 800; text-transform: uppercase;">
                            <?= htmlspecialchars($post['subject_name'] ?? 'General') ?>
                        </span>
                    </div>

                    <h1 class="post-title"><?= htmlspecialchars($post['title']) ?></h1>
                    <div class="post-content"><?= htmlspecialchars($post['content']) ?></div>

                    <?php if(!empty($post['media_url'])): ?>
                        <div class="post-media-box">
                            <?php if($post['media_type'] === 'image'): ?>
                                <img src="../<?= htmlspecialchars($post['media_url']) ?>" alt="Post Attachment">
                            <?php elseif($post['media_type'] === 'video'): ?>
                                <video src="../<?= htmlspecialchars($post['media_url']) ?>" controls playsinline preload="metadata"></video>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="post-stats-row">
                        <div>
                            <span>👍 ❤️ 😆</span>
                            <span id="detail-stat-reactions" style="margin-left: 6px;"><?= $post['reaction_count'] ?> reactions</span>
                        </div>
                        <div>
                            <span>💬 <?= count($replies) ?> replies</span>
                        </div>
                    </div>

                    <div class="post-actions-bar">
                        <div class="reaction-picker-wrapper">
                            <div class="reaction-flyout">
                                <button class="flyout-emoji-btn" onclick="sendReaction('like')" title="Like">👍</button>
                                <button class="flyout-emoji-btn" onclick="sendReaction('love')" title="Love">❤️</button>
                                <button class="flyout-emoji-btn" onclick="sendReaction('haha')" title="Haha">😆</button>
                                <button class="flyout-emoji-btn" onclick="sendReaction('wow')" title="Wow">😮</button>
                                <button class="flyout-emoji-btn" onclick="sendReaction('sad')" title="Sad">😢</button>
                            </div>
                            <button class="action-button-btn <?= $react_class ?>" id="detail-react-btn" onclick="sendReaction('like')">
                                <span id="detail-react-icon"><?= $react_icon ?></span>
                                <span id="detail-react-label"><?= $react_label ?></span>
                            </button>
                        </div>

                        <button class="action-button-btn" onclick="document.getElementById('replyTextArea').focus()">
                            <span>💬</span>
                            <span>Write a Reply</span>
                        </button>
                    </div>
                </article>

                <h3 style="margin-bottom: 1rem; color: #1E293B; font-size: 1.25rem;">
                    Discussion Replies (<?= count($replies) ?>)
                </h3>

                <div class="replies-list-container">
                    <?php foreach($replies as $reply): ?>
                    <div class="reply-item-card">
                        <div class="reply-avatar">
                            <?= strtoupper(substr($reply['author_name'] ?? 'U', 0, 1)) ?>
                        </div>
                        <div class="reply-body">
                            <div class="reply-author-line">
                                <span class="reply-author-name"><?= htmlspecialchars($reply['author_name']) ?></span>
                                <span class="badge-batch"><?= htmlspecialchars($reply['author_batch'] ?? '26.1') ?></span>
                                <span class="badge-faculty"><?= htmlspecialchars($reply['author_faculty'] ?? 'Computing') ?></span>
                                <span style="color: #CBD5E1;">•</span>
                                <span style="font-size: 0.75rem; color: #94A3B8; font-weight: 600;"><?= timeAgo($reply['created_at']) ?></span>
                            </div>
                            <div class="reply-content-text"><?= htmlspecialchars($reply['content']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if(empty($replies)): ?>
                        <div style="background: white; padding: 2rem; border-radius: var(--border-radius-md); text-align: center; color: #64748B; font-weight: 600; margin-bottom: 1.5rem; border: 1px dashed #CBD5E1;">
                            No replies yet. Be the first to share your answer or thought!
                        </div>
                    <?php endif; ?>
                </div>

                <div style="background: white; padding: 1.75rem; border-radius: var(--border-radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); margin-top: 1.5rem;">
                    <h3 style="margin-bottom: 0.5rem; font-size: 1.15rem;">Post Your Reply</h3>
                    <p style="color: #64748B; font-size: 0.85rem; font-weight: 600; margin-bottom: 1rem;">
                        Replying as <strong><?= htmlspecialchars($current_user['name']) ?></strong> (<?= htmlspecialchars($current_batch) ?> • <?= htmlspecialchars($current_faculty) ?>)
                    </p>
                    <form method="POST">
                        <input type="hidden" name="action" value="new_reply">
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <textarea name="content" id="replyTextArea" class="form-control" rows="4" required placeholder="Type your answer or helpful advice here..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" style="background: var(--accent-blue); padding: 0.75rem 1.75rem; font-weight: 700;">
                            Submit Reply 🚀
                        </button>
                    </form>
                </div>

            </div>
        </main>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/forum.js"></script>
</body>
</html>
