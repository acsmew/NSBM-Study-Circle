<?php
require_once '../includes/functions.php';
requireStudent();

$error = '';
$success = '';

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
$_SESSION['batch'] = $current_batch;
$_SESSION['faculty'] = $current_faculty;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'react') {
    header('Content-Type: application/json');
    $post_id = (int)($_POST['post_id'] ?? 0);
    $reaction_type = $_POST['reaction_type'] ?? 'like';
    $valid_reactions = ['like', 'love', 'haha', 'wow', 'sad'];

    if (!$post_id || !in_array($reaction_type, $valid_reactions)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
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
        'total' => $total_reactions,
        'counts' => [
            'like' => $counts_raw['like'] ?? 0,
            'love' => $counts_raw['love'] ?? 0,
            'haha' => $counts_raw['haha'] ?? 0,
            'wow' => $counts_raw['wow'] ?? 0,
            'sad' => $counts_raw['sad'] ?? 0
        ]
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_reply') {
    $post_id = (int)($_POST['post_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    $is_ajax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

    if (empty($content) || !$post_id) {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => 'Comment cannot be empty.']);
            exit();
        }
        $_SESSION['forum_error'] = "Comment cannot be empty.";
        redirect("forum.php#post-" . $post_id);
    }

    $stmt = $pdo->prepare("INSERT INTO forum_replies (post_id, user_id, content) VALUES (?, ?, ?)");
    if ($stmt->execute([$post_id, $_SESSION['user_id'], $content])) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            $reply_id = $pdo->lastInsertId();
            echo json_encode([
                'success' => true,
                'reply' => [
                    'id' => $reply_id,
                    'content' => htmlspecialchars($content),
                    'author_name' => htmlspecialchars($_SESSION['name']),
                    'author_batch' => htmlspecialchars($current_batch),
                    'author_faculty' => htmlspecialchars($current_faculty),
                    'created_at' => 'Just now',
                    'avatar_letter' => strtoupper(substr($_SESSION['name'], 0, 1))
                ]
            ]);
            exit();
        }
        $_SESSION['forum_success'] = "Comment posted successfully!";
        redirect("forum.php#post-" . $post_id);
    } else {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => 'Failed to post comment.']);
            exit();
        }
        $_SESSION['forum_error'] = "Failed to post comment.";
        redirect("forum.php");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $new_batch = trim($_POST['batch'] ?? '');
    $new_faculty = trim($_POST['faculty'] ?? '');

    if (!empty($new_batch) && !empty($new_faculty)) {
        $upd = $pdo->prepare("UPDATE users SET batch = ?, faculty = ? WHERE id = ?");
        $upd->execute([$new_batch, $new_faculty, $_SESSION['user_id']]);
        $_SESSION['batch'] = $new_batch;
        $_SESSION['faculty'] = $new_faculty;
        $_SESSION['forum_success'] = "Profile batch and faculty updated successfully!";
    }
    redirect("forum.php");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_post') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $subject_id = (int)($_POST['subject_id'] ?? 1);

    if (empty($title) || empty($content)) {
        $_SESSION['forum_error'] = "Please provide both a title and description for your post.";
        redirect("forum.php");
    }

    $media_url = null;
    $media_type = 'none';

    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['media']['tmp_name'];
        $file_name = $_FILES['media']['name'];
        $file_size = $_FILES['media']['size'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_images = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowed_videos = ['mp4', 'webm', 'mov', 'm4v'];

        $upload_dir = __DIR__ . '/../uploads/forum/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        if (in_array($ext, $allowed_images)) {
            if ($file_size > 15 * 1024 * 1024) {
                $_SESSION['forum_error'] = "Image size must be less than 15MB.";
                redirect("forum.php");
            }
            $unique_name = 'img_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($file_tmp, $upload_dir . $unique_name)) {
                $media_url = 'uploads/forum/' . $unique_name;
                $media_type = 'image';
            }
        } elseif (in_array($ext, $allowed_videos)) {
            if ($file_size > 60 * 1024 * 1024) {
                $_SESSION['forum_error'] = "Video size must be less than 60MB.";
                redirect("forum.php");
            }
            $unique_name = 'vid_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($file_tmp, $upload_dir . $unique_name)) {
                $media_url = 'uploads/forum/' . $unique_name;
                $media_type = 'video';
            }
        } else {
            $_SESSION['forum_error'] = "Unsupported file type. Please upload an image (JPG, PNG, GIF, WEBP) or a video (MP4, WEBM).";
            redirect("forum.php");
        }
    }

    $stmt = $pdo->prepare("INSERT INTO forum_posts (user_id, subject_id, title, content, media_url, media_type) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$_SESSION['user_id'], $subject_id, $title, $content, $media_url, $media_type])) {
        $_SESSION['forum_success'] = "Your post has been published to the forum! 🎉";
    } else {
        $_SESSION['forum_error'] = "Failed to publish post. Please try again.";
    }
    redirect("forum.php");
}

$sql = "SELECT f.*,
               u.name as author_name,
               u.batch as author_batch,
               u.faculty as author_faculty,
               s.name as subject_name,
               (SELECT COUNT(*) FROM forum_replies WHERE post_id = f.id) as reply_count,
               (SELECT COUNT(*) FROM forum_reactions WHERE post_id = f.id) as reaction_count,
               (SELECT reaction_type FROM forum_reactions WHERE post_id = f.id AND user_id = ?) as user_reaction,
               (SELECT COUNT(*) FROM forum_reactions WHERE post_id = f.id AND reaction_type = 'like') as count_like,
               (SELECT COUNT(*) FROM forum_reactions WHERE post_id = f.id AND reaction_type = 'love') as count_love,
               (SELECT COUNT(*) FROM forum_reactions WHERE post_id = f.id AND reaction_type = 'haha') as count_haha,
               (SELECT COUNT(*) FROM forum_reactions WHERE post_id = f.id AND reaction_type = 'wow') as count_wow,
               (SELECT COUNT(*) FROM forum_reactions WHERE post_id = f.id AND reaction_type = 'sad') as count_sad
        FROM forum_posts f
        LEFT JOIN users u ON f.user_id = u.id
        LEFT JOIN subjects s ON f.subject_id = s.id
        ORDER BY f.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$posts = $stmt->fetchAll();

$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll();
if (empty($subjects)) {
    $pdo->query("INSERT INTO subjects (name, description) VALUES ('General', 'General Discussion')");
    $subjects = $pdo->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discussion Forum - NSBM StudyCircle</title>
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
        <div class="nav-right" style="display: flex; align-items: center; gap: 1rem;">
            <div class="profile-badge-pill">
                <span>🎓 <strong><?= htmlspecialchars($current_user['name']) ?></strong></span>
                <span style="color:#059669; background:#D1FAE5; padding: 2px 6px; border-radius:4px; font-size:0.75rem;"><?= htmlspecialchars($current_batch) ?></span>
                <span style="color:#2563EB; background:#DBEAFE; padding: 2px 6px; border-radius:4px; font-size:0.75rem;"><?= htmlspecialchars($current_faculty) ?></span>
                <button onclick="document.getElementById('profileModal').classList.add('active')" style="background:none; border:none; color:#64748B; cursor:pointer; font-size:0.85rem; margin-left:2px;" title="Edit Batch/Faculty">✏️</button>
            </div>
            <a href="materials.php" class="btn-upload">Upload Note</a>
            <div class="user-profile">
                <div class="avatar-small"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
                <a href="../logout.php" style="margin-left: 0.5rem;" title="Logout">🚪</a>
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
            <div class="forum-wrapper">

                <div class="forum-header-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h1 style="font-size: 1.85rem; color: #0F172A; margin-bottom: 0.25rem;">Discussion Forum 💬</h1>
                        <p style="color: #64748B; font-weight: 600; font-size: 0.95rem;">Ask doubts, discuss past papers, react & help your campus friends.</p>
                    </div>
                    <button onclick="openPostModal()" class="btn btn-primary" style="background: var(--accent-blue); padding: 0.75rem 1.25rem; border-radius: 12px; font-weight: 700; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);">
                        + Ask Question
                    </button>
                </div>

                <?php if($error): ?>
                    <div style="padding: 1rem 1.25rem; background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; border-radius: 12px; margin-bottom: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                        <span>⚠️</span> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                <?php if($success): ?>
                    <div style="padding: 1rem 1.25rem; background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; border-radius: 12px; margin-bottom: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                        <span>✅</span> <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <div class="create-post-card">
                    <div class="create-post-top">
                        <div class="avatar-circle">
                            <?= strtoupper(substr($current_user['name'], 0, 1)) ?>
                        </div>
                        <button class="create-trigger-btn" onclick="openPostModal()">
                            What's on your mind or what do you want to ask, <?= htmlspecialchars(explode(' ', $current_user['name'])[0]) ?>?
                        </button>
                    </div>
                    <div class="create-post-actions">
                        <button class="quick-action-btn" onclick="openPostModal('media')">
                            <span style="font-size: 1.2rem;">📷</span>
                            <span>Photo / Video</span>
                        </button>
                        <button class="quick-action-btn" onclick="openPostModal()">
                            <span style="font-size: 1.2rem;">💡</span>
                            <span>Ask a Question</span>
                        </button>
                        <button class="quick-action-btn" onclick="document.getElementById('profileModal').classList.add('active')">
                            <span style="font-size: 1.2rem;">🏷️</span>
                            <span>Batch & Faculty</span>
                        </button>
                    </div>
                </div>

                <div class="posts-feed-container">
                    <?php foreach($posts as $post):
                        $user_react = $post['user_reaction'] ?? null;

                        $react_label = 'Like';
                        $react_icon = '👍';
                        $react_class = '';
                        if ($user_react === 'like') { $react_label = 'Liked'; $react_icon = '👍'; $react_class = 'reacted-like'; }
                        elseif ($user_react === 'love') { $react_label = 'Love'; $react_icon = '❤️'; $react_class = 'reacted-love'; }
                        elseif ($user_react === 'haha') { $react_label = 'Haha'; $react_icon = '😆'; $react_class = 'reacted-haha'; }
                        elseif ($user_react === 'wow')  { $react_label = 'Wow';  $react_icon = '😮'; $react_class = 'reacted-wow'; }
                        elseif ($user_react === 'sad')  { $react_label = 'Sad';  $react_icon = '😢'; $react_class = 'reacted-sad'; }

                        $rep_stmt = $pdo->prepare("SELECT r.*, u.name as reply_author_name, u.batch as reply_batch, u.faculty as reply_faculty
                                                   FROM forum_replies r
                                                   LEFT JOIN users u ON r.user_id = u.id
                                                   WHERE r.post_id = ?
                                                   ORDER BY r.created_at ASC");
                        $rep_stmt->execute([$post['id']]);
                        $replies = $rep_stmt->fetchAll();
                    ?>
                    <article class="forum-post-card" id="post-<?= $post['id'] ?>" data-post-id="<?= $post['id'] ?>">

                        <div class="post-author-bar">
                            <div class="author-info-wrap">
                                <div class="avatar-circle" style="background: linear-gradient(135deg, #3B82F6, #1D4ED8);">
                                    <?= strtoupper(substr($post['author_name'] ?? 'U', 0, 1)) ?>
                                </div>
                                <div>

                                    <div class="author-name-title">
                                        <?= htmlspecialchars($post['author_name']) ?>
                                    </div>
                                    <div class="author-meta-badges">
                                        <span class="badge-batch"><?= htmlspecialchars($post['author_batch'] ?? '26.1') ?></span>
                                        <span class="badge-faculty"><?= htmlspecialchars($post['author_faculty'] ?? 'Computing') ?></span>
                                        <span style="color:#CBD5E1;">•</span>
                                        <span class="post-timestamp"><?= timeAgo($post['created_at']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <span class="subject-pill">
                                <?= htmlspecialchars($post['subject_name'] ?? 'General') ?>
                            </span>
                        </div>

                        <h2 class="post-heading-title">
                            <a href="forum_view.php?id=<?= $post['id'] ?>" style="color: inherit; text-decoration: none;">
                                <?= htmlspecialchars($post['title']) ?>
                            </a>
                        </h2>
                        <div class="post-body-text"><?= htmlspecialchars($post['content']) ?></div>

                        <?php if(!empty($post['media_url'])): ?>
                            <div class="post-media-container">
                                <?php if($post['media_type'] === 'image'): ?>
                                    <img src="../<?= htmlspecialchars($post['media_url']) ?>" alt="Post Attachment" class="post-media-image" onclick="openLightbox('../<?= htmlspecialchars($post['media_url']) ?>')">
                                <?php elseif($post['media_type'] === 'video'): ?>
                                    <video src="../<?= htmlspecialchars($post['media_url']) ?>" class="post-media-video" controls playsinline preload="metadata"></video>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="post-stats-row">
                            <div class="reaction-icons-summary">
                                <span class="emoji-bubble">👍</span>
                                <span class="emoji-bubble">❤️</span>
                                <span class="emoji-bubble">😆</span>
                                <span id="stat-total-<?= $post['id'] ?>" style="margin-left: 4px; font-weight: 700; color: #334155;">
                                    <?= $post['reaction_count'] ?> reactions
                                </span>
                            </div>
                            <div style="cursor: pointer;" onclick="toggleComments(<?= $post['id'] ?>)">
                                <span id="stat-comments-<?= $post['id'] ?>"><?= count($replies) ?> comments</span>
                            </div>
                        </div>

                        <div class="post-actions-bar">

                            <div class="reaction-picker-wrapper">
                                <div class="reaction-flyout" id="flyout-<?= $post['id'] ?>">
                                    <button class="flyout-emoji-btn" onclick="sendReaction(<?= $post['id'] ?>, 'like')" title="Like">👍</button>
                                    <button class="flyout-emoji-btn" onclick="sendReaction(<?= $post['id'] ?>, 'love')" title="Love">❤️</button>
                                    <button class="flyout-emoji-btn" onclick="sendReaction(<?= $post['id'] ?>, 'haha')" title="Haha">😆</button>
                                    <button class="flyout-emoji-btn" onclick="sendReaction(<?= $post['id'] ?>, 'wow')" title="Wow">😮</button>
                                    <button class="flyout-emoji-btn" onclick="sendReaction(<?= $post['id'] ?>, 'sad')" title="Sad">😢</button>
                                </div>
                                <button class="action-button-btn <?= $react_class ?>" id="react-btn-<?= $post['id'] ?>" onclick="toggleDefaultReaction(<?= $post['id'] ?>)">
                                    <span id="react-icon-<?= $post['id'] ?>"><?= $react_icon ?></span>
                                    <span id="react-label-<?= $post['id'] ?>"><?= $react_label ?></span>
                                </button>
                            </div>

                            <button class="action-button-btn" onclick="toggleComments(<?= $post['id'] ?>)">
                                <span>💬</span>
                                <span>Comment</span>
                            </button>

                            <button class="action-button-btn" onclick="sharePostLink(<?= $post['id'] ?>)">
                                <span>🔗</span>
                                <span>Copy Link</span>
                            </button>
                        </div>

                        <div class="comments-drawer" id="comments-drawer-<?= $post['id'] ?>">
                            <div class="comments-list-box" id="comments-list-<?= $post['id'] ?>">
                                <?php foreach($replies as $rep): ?>
                                    <div class="comment-item">
                                        <div class="comment-avatar">
                                            <?= strtoupper(substr($rep['reply_author_name'] ?? 'U', 0, 1)) ?>
                                        </div>
                                        <div style="flex: 1;">
                                            <div class="comment-bubble">
                                                <div class="comment-author-name">
                                                    <?= htmlspecialchars($rep['reply_author_name']) ?>
                                                    <span class="badge-batch comment-badge"><?= htmlspecialchars($rep['reply_batch'] ?? '26.1') ?></span>
                                                    <span class="badge-faculty comment-badge"><?= htmlspecialchars($rep['reply_faculty'] ?? 'Computing') ?></span>
                                                </div>
                                                <div class="comment-text"><?= htmlspecialchars($rep['content']) ?></div>
                                            </div>
                                            <div class="comment-time"><?= timeAgo($rep['created_at']) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <form class="comment-form-wrap" onsubmit="submitCommentAjax(event, <?= $post['id'] ?>)">
                                <div class="avatar-small" style="width: 32px; height: 32px; font-size: 0.8rem; background: var(--accent-green); flex-shrink: 0;">
                                    <?= strtoupper(substr($current_user['name'], 0, 1)) ?>
                                </div>
                                <input type="text" id="comment-input-<?= $post['id'] ?>" class="comment-input-field" placeholder="Write a comment as <?= htmlspecialchars(explode(' ', $current_user['name'])[0]) ?>..." required autocomplete="off">
                                <button type="submit" class="comment-send-btn" title="Post Comment">➤</button>
                            </form>
                        </div>

                    </article>
                    <?php endforeach; ?>

                    <?php if(empty($posts)): ?>
                        <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: var(--border-radius-lg); border: 2px dashed #CBD5E1;">
                            <div style="font-size: 3.5rem; margin-bottom: 1rem;">🤔</div>
                            <h3 style="margin-bottom: 0.5rem; font-size: 1.35rem;">No Discussions Found</h3>
                            <p style="color: #64748B; margin-bottom: 1.5rem; font-weight: 600;">Be the first student to ask a question, share notes or start a study discussion!</p>
                            <button onclick="openPostModal()" class="btn btn-primary" style="background: var(--accent-blue);">+ Ask the First Question</button>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

    <?php include '../includes/footer.php'; ?>

    <div id="askModal" class="modal">
        <div class="modal-content" style="max-width: 650px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="avatar-circle" style="width: 38px; height: 38px; font-size: 0.95rem;">
                        <?= strtoupper(substr($current_user['name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h2 style="font-size: 1.25rem; margin: 0;">Create Post</h2>
                        <div style="font-size: 0.8rem; color: #64748B; font-weight: 700;">
                            Posting as <strong><?= htmlspecialchars($current_user['name']) ?></strong> (<?= htmlspecialchars($current_batch) ?> • <?= htmlspecialchars($current_faculty) ?>)
                        </div>
                    </div>
                </div>
                <button onclick="closePostModal()" style="background: none; border: none; font-size: 1.75rem; cursor: pointer; color: #94A3B8;">&times;</button>
            </div>

            <form method="POST" enctype="multipart/form-data" id="createPostForm">
                <input type="hidden" name="action" value="new_post">

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="font-weight: 700;">Subject Category</label>
                    <select name="subject_id" class="form-control" style="font-weight: 700;">
                        <?php foreach($subjects as $sub): ?>
                            <option value="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="font-weight: 700;">Title</label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. Can someone explain QuickSort partitioning?">
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label" style="font-weight: 700;">Details / Question</label>
                    <textarea name="content" class="form-control" rows="5" required placeholder="Explain your question in detail, provide context, or share notes..."></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem; background: #F8FAFC; padding: 1.25rem; border-radius: 12px; border: 2px dashed #CBD5E1;">
                    <label class="form-label" style="font-weight: 700; display: flex; justify-content: space-between; align-items: center;">
                        <span>📷 Attach Photo or 🎥 Video (Optional)</span>
                        <span style="font-size: 0.75rem; color: #94A3B8; font-weight: 600;">Max 15MB Photo / 60MB Video</span>
                    </label>
                    <input type="file" name="media" id="mediaFileInput" accept="image/*,video/*" class="form-control" onchange="previewMediaUpload(this)">

                    <div id="mediaPreviewBox" style="display: none; margin-top: 1rem; position: relative;">
                        <button type="button" onclick="clearMediaUpload()" style="position: absolute; top: 8px; right: 8px; background: rgba(0,0,0,0.7); color: white; border: none; border-radius: 50%; width: 28px; height: 28px; cursor: pointer; font-size: 1rem; z-index: 10;">&times;</button>
                        <img id="imgPreview" src="" alt="Preview" style="display: none; width: 100%; max-height: 250px; object-fit: contain; border-radius: 8px; background: #0F172A;">
                        <video id="vidPreview" src="" controls style="display: none; width: 100%; max-height: 250px; border-radius: 8px; background: #0F172A;"></video>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.95rem; font-size: 1.05rem; font-weight: 800; border-radius: 12px; background: var(--accent-blue);">
                    Publish Post 🚀
                </button>
            </form>
        </div>
    </div>

    <div id="profileModal" class="modal">
        <div class="modal-content" style="max-width: 450px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.35rem; margin: 0;">Update Student Profile 🎓</h2>
                <button onclick="document.getElementById('profileModal').classList.remove('active')" style="background: none; border: none; font-size: 1.75rem; cursor: pointer; color: #94A3B8;">&times;</button>
            </div>
            <p style="color: #64748B; font-size: 0.9rem; margin-bottom: 1.25rem; font-weight: 600;">
                Set your Batch and Faculty so it appears prominently on your forum posts and replies!
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="font-weight: 700;">Batch (e.g. 26.1)</label>
                    <input type="text" name="batch" class="form-control" required placeholder="e.g. 26.1" value="<?= htmlspecialchars($current_batch) ?>">
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label" style="font-weight: 700;">Faculty (e.g. IFSL Computing)</label>
                    <input type="text" name="faculty" class="form-control" required placeholder="e.g. IFSL Computing or School of Computing" value="<?= htmlspecialchars($current_faculty) ?>">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; font-weight: 800; border-radius: 12px; background: var(--accent-green);">
                    Save Changes
                </button>
            </form>
        </div>
    </div>

    <div id="mediaLightboxModal" onclick="closeLightbox()">
        <img id="mediaLightboxImg" src="" alt="Full view" onclick="event.stopPropagation()">
    </div>

    <div id="toastNotice"></div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/forum.js"></script>
</body>
</html>
