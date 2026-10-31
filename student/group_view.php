<?php
require_once '../includes/functions.php';
requireStudent();

$error = '';
$success = '';

if (!isset($_GET['id'])) {
    redirect('groups.php');
}
$group_id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM group_members WHERE group_id = ? AND user_id = ?");
$stmt->execute([$group_id, $_SESSION['user_id']]);
$is_member = $stmt->fetchColumn() > 0;

if (!$is_member) {
    redirect('groups.php');
}

try {
    $pdo->query("SELECT join_code FROM study_groups LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE study_groups ADD COLUMN join_code VARCHAR(32) NULL UNIQUE");
    } catch (Exception $ex) {}
}

try {
    $null_groups = $pdo->query("SELECT id FROM study_groups WHERE join_code IS NULL OR join_code = ''")->fetchAll();
    if (!empty($null_groups)) {
        $upd_stmt = $pdo->prepare("UPDATE study_groups SET join_code = ? WHERE id = ?");
        foreach ($null_groups as $ng) {
            $code = 'GRP-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));
            $upd_stmt->execute([$code, $ng['id']]);
        }
    }
} catch (Exception $ex) {}

try {
    $pdo->query("SELECT status FROM group_sessions LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE group_sessions ADD COLUMN status ENUM('scheduled', 'live', 'ended') DEFAULT 'scheduled'");
    } catch (Exception $ex) {}
}

try {
    $pdo->query("SELECT id FROM group_materials LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `group_materials` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `group_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `title` varchar(255) NOT NULL,
            `description` text,
            `file_path` varchar(255) NOT NULL,
            `file_type` varchar(50) DEFAULT 'document',
            `file_size` varchar(50) DEFAULT '',
            `is_pinned` tinyint(1) DEFAULT 0,
            `pinned_at` timestamp NULL DEFAULT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`group_id`) REFERENCES `study_groups`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        )");
    } catch (Exception $ex) {}
}

$stmt = $pdo->prepare("SELECT g.*, u.name as creator_name, u.batch as creator_batch, u.faculty as creator_faculty
                     FROM study_groups g
                     LEFT JOIN users u ON g.created_by = u.id
                     WHERE g.id = ?");
$stmt->execute([$group_id]);
$group = $stmt->fetch();

if (!$group) {
    die("Group not found.");
}

$is_creator = ($group['created_by'] == $_SESSION['user_id']);

if (isset($_GET['api_status'])) {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("SELECT id, status, title FROM group_sessions WHERE group_id = ?");
    $stmt->execute([$group_id]);
    echo json_encode(['sessions' => $stmt->fetchAll()]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['start_meeting', 'end_meeting'])) {
    header('Content-Type: application/json');
    $session_id = (int)($_POST['session_id'] ?? 0);

    if (!$is_creator) {
        echo json_encode(['success' => false, 'message' => 'Only the group creator can start or end meetings.']);
        exit();
    }

    $new_status = ($_POST['action'] === 'start_meeting') ? 'live' : 'ended';
    $stmt = $pdo->prepare("UPDATE group_sessions SET status = ? WHERE id = ? AND group_id = ?");
    $stmt->execute([$new_status, $session_id, $group_id]);

    echo json_encode(['success' => true, 'status' => $new_status, 'session_id' => $session_id]);
    exit();
}

if (isset($_SESSION['success_msg'])) {
    $success = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
if (isset($_SESSION['error_msg'])) {
    $error = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

if (!function_exists('formatFileBytes')) {
    function formatFileBytes($bytes, $precision = 1) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_material') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($title)) {
        $_SESSION['error_msg'] = "Please provide a title for the study material.";
        redirect("group_view.php?id=" . $group_id);
    }

    if (!isset($_FILES['material_file']) || $_FILES['material_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error_msg'] = "Please choose a valid file to upload.";
        redirect("group_view.php?id=" . $group_id);
    }

    $file_tmp = $_FILES['material_file']['tmp_name'];
    $file_name = $_FILES['material_file']['name'];
    $file_bytes = $_FILES['material_file']['size'];
    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if ($file_bytes > 50 * 1024 * 1024) {
        $_SESSION['error_msg'] = "File size must be under 50MB.";
        redirect("group_view.php?id=" . $group_id);
    }

    $file_type = 'document';
    if (in_array($ext, ['pdf'])) $file_type = 'pdf';
    elseif (in_array($ext, ['doc', 'docx'])) $file_type = 'word';
    elseif (in_array($ext, ['ppt', 'pptx'])) $file_type = 'powerpoint';
    elseif (in_array($ext, ['xls', 'xlsx'])) $file_type = 'excel';
    elseif (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) $file_type = 'archive';
    elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) $file_type = 'image';
    elseif (in_array($ext, ['java', 'py', 'c', 'cpp', 'js', 'php', 'html', 'css', 'sql'])) $file_type = 'code';

    $upload_dir = __DIR__ . '/../uploads/group_materials/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $unique_name = 'mat_' . uniqid() . '.' . $ext;
    $target_dest = $upload_dir . $unique_name;

    if (move_uploaded_file($file_tmp, $target_dest)) {
        $db_path = 'uploads/group_materials/' . $unique_name;
        $size_str = formatFileBytes($file_bytes);

        $stmt = $pdo->prepare("INSERT INTO group_materials (group_id, user_id, title, description, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$group_id, $_SESSION['user_id'], $title, $description, $db_path, $file_type, $size_str])) {
            $_SESSION['success_msg'] = "Study material shared successfully with the group! 📚";
        } else {
            $_SESSION['error_msg'] = "Failed to save material to database.";
        }
    } else {
        $_SESSION['error_msg'] = "Failed to upload file to server.";
    }
    redirect("group_view.php?id=" . $group_id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_pin') {
    $material_id = (int)($_POST['material_id'] ?? 0);

    if (!$is_creator) {
        $_SESSION['error_msg'] = "Only the group admin can pin materials.";
        redirect("group_view.php?id=" . $group_id);
    }

    $chk = $pdo->prepare("SELECT is_pinned FROM group_materials WHERE id = ? AND group_id = ?");
    $chk->execute([$material_id, $group_id]);
    $mat = $chk->fetch();

    if ($mat) {
        if ($mat['is_pinned'] == 1) {
            $upd = $pdo->prepare("UPDATE group_materials SET is_pinned = 0, pinned_at = NULL WHERE id = ?");
            $upd->execute([$material_id]);
            $_SESSION['success_msg'] = "Study material unpinned.";
        } else {
            $upd = $pdo->prepare("UPDATE group_materials SET is_pinned = 1, pinned_at = NOW() WHERE id = ?");
            $upd->execute([$material_id]);
            $_SESSION['success_msg'] = "Study material pinned to top! 📌";
        }
    }
    redirect("group_view.php?id=" . $group_id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_material') {
    $material_id = (int)($_POST['material_id'] ?? 0);

    $chk = $pdo->prepare("SELECT * FROM group_materials WHERE id = ? AND group_id = ?");
    $chk->execute([$material_id, $group_id]);
    $mat = $chk->fetch();

    if ($mat && ($is_creator || $mat['user_id'] == $_SESSION['user_id'])) {
        $full_file = __DIR__ . '/../' . $mat['file_path'];
        if (file_exists($full_file)) {
            @unlink($full_file);
        }
        $del = $pdo->prepare("DELETE FROM group_materials WHERE id = ?");
        $del->execute([$material_id]);
        $_SESSION['success_msg'] = "Study material deleted.";
    }
    redirect("group_view.php?id=" . $group_id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_session') {
    $title = $_POST['title'] ?? '';
    $schedule_time = $_POST['schedule_time'] ?? '';
    $meeting_link = $_POST['meeting_link'] ?? '';

    if (empty($title) || empty($schedule_time)) {
        $_SESSION['error_msg'] = "Title and schedule time are required.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO group_sessions (group_id, title, schedule_time, meeting_link, status) VALUES (?, ?, ?, ?, 'scheduled')");
        if ($stmt->execute([$group_id, $title, $schedule_time, $meeting_link])) {
            $_SESSION['success_msg'] = "Session scheduled successfully!";
        } else {
            $_SESSION['error_msg'] = "Failed to schedule session.";
        }
    }
    redirect("group_view.php?id=" . $group_id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_session') {
    $del_session_id = (int)($_POST['session_id'] ?? 0);
    if ($is_creator) {
        $stmt = $pdo->prepare("DELETE FROM group_sessions WHERE id = ? AND group_id = ?");
        $stmt->execute([$del_session_id, $group_id]);
        $_SESSION['success_msg'] = "Session deleted successfully!";
    }
    redirect("group_view.php?id=" . $group_id);
}

$mat_stmt = $pdo->prepare("SELECT gm.*, u.name as uploader_name, u.batch as uploader_batch, u.faculty as uploader_faculty
                           FROM group_materials gm
                           LEFT JOIN users u ON gm.user_id = u.id
                           WHERE gm.group_id = ?
                           ORDER BY gm.is_pinned DESC, gm.pinned_at DESC, gm.created_at DESC");
$mat_stmt->execute([$group_id]);
$materials = $mat_stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM group_sessions WHERE group_id = ? ORDER BY schedule_time DESC");
$stmt->execute([$group_id]);
$sessions = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT u.id, u.name, u.batch, u.faculty FROM group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = ?");
$stmt->execute([$group_id]);
$members = $stmt->fetchAll();

$pinned_materials = array_filter($materials, function($m) { return $m['is_pinned'] == 1; });
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($group['name']) ?> - Study Group</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/groups.css">
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
                <a href="../logout.php" style="margin-left: 0.5rem;" title="Logout">🚪</a>
            </div>
        </div>
    </nav>

    <div class="app-container">

        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="index.php" class="sidebar-link"><span class="sidebar-icon">🏠</span><span class="sidebar-text">Home</span></a></li>
                <li><a href="forum.php" class="sidebar-link"><span class="sidebar-icon">💬</span><span class="sidebar-text">Forum</span></a></li>
                <li><a href="groups.php" class="sidebar-link active"><span class="sidebar-icon">👥</span><span class="sidebar-text">Study Groups</span></a></li>
            </ul>
        </aside>

        <main class="main-content" id="groupApp" data-group-id="<?= (int)$group_id ?>" data-is-creator="<?= $is_creator ? '1' : '0' ?>" data-user-name="<?= htmlspecialchars($_SESSION['name'] ?? 'Student') ?>" style="display: flex; flex-direction: column; align-items: center; width: 100%; padding: 2rem 2.5rem;">
            <div style="width: 100%; max-width: 1320px;">
            <a href="groups.php" style="display: inline-block; margin-bottom: 1.25rem; color: var(--text-secondary); font-weight: 700;">&larr; Back to Groups</a>

            <?php if($error): ?><div style="padding: 1rem; background: #FEE2E2; color: #991B1B; border-radius: 8px; margin-bottom: 1.25rem; font-weight: 700;"><?= $error ?></div><?php endif; ?>
            <?php if($success): ?><div style="padding: 1rem; background: #D1FAE5; color: #065F46; border-radius: 8px; margin-bottom: 1.25rem; font-weight: 700;"><?= $success ?></div><?php endif; ?>

            <?php if (isset($_SESSION['flash_created_code'])): ?>
            <div style="background: #ECFDF5; border: 2px solid #10B981; border-radius: var(--border-radius-md); padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.12);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 2.2rem;">🎉</span>
                    <div>
                        <h4 style="margin: 0 0 4px 0; color: #065F46; font-size: 1.15rem; font-weight: 800;">Group Created Successfully!</h4>
                        <p style="margin: 0; color: #047857; font-size: 0.9rem; font-weight: 600;">Share this secret Join Code with your classmates so they can join your study group:</p>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="background: white; border: 2px dashed #059669; padding: 0.55rem 1.25rem; border-radius: 8px; font-family: monospace; font-size: 1.3rem; font-weight: 900; color: #065F46; letter-spacing: 1px;">
                        <?= htmlspecialchars($_SESSION['flash_created_code']) ?>
                    </div>
                    <button type="button" onclick="copyJoinCode('<?= htmlspecialchars($_SESSION['flash_created_code'], ENT_QUOTES) ?>')" class="btn" style="background: #059669; color: white; font-weight: 800; padding: 0.7rem 1.25rem; border-radius: 8px; border: none; cursor: pointer; box-shadow: 0 4px 10px rgba(5, 150, 105, 0.25);">
                        📋 Copy Code
                    </button>
                </div>
            </div>
            <?php
                unset($_SESSION['flash_created_code']);
                unset($_SESSION['flash_created_group']);
            endif; ?>

            <div class="group-header">
                <div class="header-top-flex">
                    <div>
                        <h1 style="color: #064E3B; font-size: 2.5rem; margin-bottom: 0.5rem; line-height: 1.1; font-weight: 900;">
                            <?= htmlspecialchars($group['name']) ?>
                        </h1>
                        <p style="color: #047857; font-size: 1.1rem; max-width: 750px; line-height: 1.5; margin-bottom: 1.25rem;">
                            <?= htmlspecialchars($group['description'] ?: 'Study together, share lecture notes, and host live sessions for this module.') ?>
                        </p>
                    </div>

                    <div class="header-action-buttons">
                        <button onclick="document.getElementById('materialModal').classList.add('active')" class="btn" style="background: #047857; color: white; border: none; font-weight: 800; padding: 0.85rem 1.4rem; box-shadow: 0 4px 14px rgba(4, 120, 87, 0.35);">
                            + Share Study Material 📚
                        </button>
                        <button onclick="document.getElementById('sessionModal').classList.add('active')" class="btn" style="background: white; color: #047857; border: 1px solid #A7F3D0; font-weight: 800; padding: 0.85rem 1.4rem;">
                            + Schedule Session 📹
                        </button>
                    </div>
                </div>

                <div style="margin-top: 1rem; display: flex; gap: 0.85rem; align-items: center; flex-wrap: wrap;">
                    <span style="background: white; color: #059669; padding: 0.5rem 1rem; border-radius: 50px; font-weight: 800; font-size: 0.9rem; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
                        👑 Admin: <?= htmlspecialchars($group['creator_name']) ?> <?= $is_creator ? '(You)' : '' ?>
                    </span>
                    <span style="background: white; color: #059669; padding: 0.5rem 1rem; border-radius: 50px; font-weight: 800; font-size: 0.9rem; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
                        👥 <?= count($members) ?> Members
                    </span>
                    <span style="background: white; color: #059669; padding: 0.5rem 1rem; border-radius: 50px; font-weight: 800; font-size: 0.9rem; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
                        📚 <?= count($materials) ?> Materials <?= count($pinned_materials) ? '('.count($pinned_materials).' Pinned)' : '' ?>
                    </span>

                    <span style="background: #1E293B; color: #F8FAFC; padding: 0.5rem 1.1rem; border-radius: 50px; font-weight: 700; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.08);">
                        <span>🔑 Join Code:</span>
                        <code id="groupJoinCodeText" style="color: #34D399; font-weight: 800; font-size: 0.95rem; font-family: monospace; letter-spacing: 0.5px;"><?= htmlspecialchars($group['join_code'] ?? 'N/A') ?></code>
                        <button type="button" id="btnCopyCode" onclick="copyJoinCode('<?= htmlspecialchars($group['join_code'] ?? '', ENT_QUOTES) ?>')" style="background: rgba(255,255,255,0.18); color: white; border: none; border-radius: 20px; padding: 3px 10px; font-size: 0.78rem; font-weight: 700; cursor: pointer; transition: all 0.2s;" title="Copy Join Code to Clipboard">📋 Copy</button>
                    </span>

                    <?php if($is_creator): ?>
                    <span style="background: #10B981; color: white; padding: 0.5rem 1rem; border-radius: 50px; font-weight: 700; font-size: 0.85rem;">
                        ✨ You have Admin Pin & Host control
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="group-side-by-side-grid">

                <div class="grid-col-left">

                    <div style="margin-bottom: 2rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                            <div>
                                <h2 style="font-size: 1.35rem; color: #0F172A; display: flex; align-items: center; gap: 8px;">
                                    <span>📹</span> Live Sessions & Schedule
                                </h2>
                                <p style="color: #64748B; font-size: 0.85rem; font-weight: 600; margin-top: 2px;">
                                    Live study meetings and group schedule.
                                </p>
                            </div>
                            <button onclick="document.getElementById('sessionModal').classList.add('active')" class="btn btn-primary" style="background: var(--accent-green); padding: 0.55rem 0.95rem; font-size: 0.85rem; font-weight: 800;">
                                + Schedule Session
                            </button>
                        </div>

                        <?php foreach($sessions as $session):
                            $status = $session['status'] ?? 'scheduled';
                        ?>
                        <div class="session-card <?= $status === 'live' ? 'is-live' : '' ?>" id="session-card-<?= $session['id'] ?>" data-status="<?= $status ?>">
                            <div style="min-width: 0; flex: 1;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.35rem; flex-wrap: wrap;">
                                    <h3 style="margin: 0; font-size: 1.05rem; color: var(--text-primary); font-weight: 700;"><?= htmlspecialchars($session['title']) ?></h3>
                                    <?php if($status === 'live'): ?>
                                        <span class="live-badge"><span class="pulse-dot"></span> LIVE NOW</span>
                                    <?php elseif($status === 'ended'): ?>
                                        <span class="ended-badge">Ended</span>
                                    <?php else: ?>
                                        <span class="scheduled-badge">⏳ Scheduled</span>
                                    <?php endif; ?>
                                </div>
                                <div style="color: var(--text-secondary); font-size: 0.82rem; font-weight: 600;">
                                    📅 <?= date('M d, Y', strtotime($session['schedule_time'])) ?> &nbsp;•&nbsp; 🕒 <?= date('g:i A', strtotime($session['schedule_time'])) ?>
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
                            <?php if(!empty($session['meeting_link'])): ?>
                                <a href="<?= htmlspecialchars($session['meeting_link']) ?>" target="_blank" class="btn" style="background: #EFF6FF; color: var(--accent-blue); padding: 0.5rem 0.85rem; font-size: 0.82rem; font-weight: 700;">Join Link</a>
                            <?php else: ?>
                                <?php if($is_creator): ?>
                                    <?php if($status === 'live'): ?>
                                        <button onclick="startJitsi('<?= $session['id'] ?>', '<?= htmlspecialchars($session['title'], ENT_QUOTES) ?>', true)" class="btn" style="background: #10B981; color: white; border: none; font-weight: 700; cursor: pointer; padding: 0.5rem 0.85rem; font-size: 0.82rem;">🟢 Re-join</button>
                                        <button onclick="hostEndMeeting('<?= $session['id'] ?>')" class="btn" style="background: #EF4444; color: white; border: none; font-weight: 700; cursor: pointer; padding: 0.5rem 0.75rem; font-size: 0.82rem;">⏹ End</button>
                                    <?php elseif($status === 'ended'): ?>
                                        <button onclick="hostStartMeeting('<?= $session['id'] ?>', '<?= htmlspecialchars($session['title'], ENT_QUOTES) ?>')" class="btn" style="background: #F1F5F9; color: var(--text-primary); border: 1px solid #CBD5E1; font-weight: 600; cursor: pointer; padding: 0.5rem 0.85rem; font-size: 0.82rem;">🔄 Restart</button>
                                    <?php else: ?>
                                        <button onclick="hostStartMeeting('<?= $session['id'] ?>', '<?= htmlspecialchars($session['title'], ENT_QUOTES) ?>')" class="btn" style="background: #10B981; color: white; border: none; font-weight: 700; cursor: pointer; padding: 0.5rem 0.85rem; font-size: 0.82rem; box-shadow: 0 4px 10px rgba(16,185,129,0.3);">🟢 Start</button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if($status === 'live'): ?>
                                        <button onclick="startJitsi('<?= $session['id'] ?>', '<?= htmlspecialchars($session['title'], ENT_QUOTES) ?>', false)" class="btn btn-live-pulse" style="background: #10B981; color: white; border: none; font-weight: 800; cursor: pointer; padding: 0.5rem 0.85rem; font-size: 0.82rem;">🟢 Join Live</button>
                                    <?php elseif($status === 'ended'): ?>
                                        <button disabled class="btn" style="background: #F8FAFC; color: #94A3B8; border: 1px solid #E2E8F0; cursor: not-allowed; font-size: 0.8rem; padding: 0.5rem 0.75rem;">Ended</button>
                                    <?php else: ?>
                                        <button disabled class="btn" style="background: #F1F5F9; color: #64748B; border: 1px solid #E2E8F0; cursor: not-allowed; font-weight: 600; font-size: 0.8rem; padding: 0.5rem 0.75rem;" title="Waiting for host to start..."><span style="margin-right: 4px;">🔒</span> Waiting</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if($is_creator): ?>
                                <form method="POST" style="margin: 0; display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this session?');">
                                    <input type="hidden" name="action" value="delete_session">
                                    <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                    <button type="submit" class="btn" style="background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; padding: 0.5rem 0.65rem; border-radius: 8px; cursor: pointer; font-size: 0.82rem;" title="Delete this session">🗑</button>
                                </form>
                            <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <?php if(empty($sessions)): ?>
                        <div style="text-align: center; padding: 2.5rem 1.5rem; background: white; border-radius: var(--border-radius-md); border: 2px dashed var(--border-color);">
                            <div style="font-size: 2.2rem; margin-bottom: 0.5rem;">📅</div>
                            <h3 style="font-size: 1.05rem; color: #1E293B; margin-bottom: 0.35rem;">No Sessions Scheduled Yet</h3>
                            <p style="color: var(--text-secondary); margin-bottom: 1rem; font-size: 0.85rem;">Schedule a live group meeting or video study circle.</p>
                            <button onclick="document.getElementById('sessionModal').classList.add('active')" class="btn" style="background: #F1F5F9; color: var(--text-primary); font-size: 0.85rem; font-weight: 700;">+ Schedule Now</button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div style="background: white; border-radius: var(--border-radius-md); padding: 1.35rem; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid #F1F5F9; padding-bottom: 0.75rem;">
                            <h3 style="margin: 0; font-size: 1.05rem; color: #0F172A; display: flex; align-items: center; gap: 6px;">
                                <span>👥</span> Group Members
                            </h3>
                            <span style="font-size: 0.8rem; background: #ECFDF5; color: #047857; padding: 2px 10px; border-radius: 50px; font-weight: 800; border: 1px solid #A7F3D0;">
                                <?= count($members) ?> <?= count($members) === 1 ? 'Member' : 'Members' ?>
                            </span>
                        </div>
                        <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php foreach($members as $member):
                                $is_mem_creator = ($member['id'] == $group['created_by']);
                            ?>
                            <li style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem; min-width: 0;">
                                    <div class="avatar-small" style="width: 32px; height: 32px; font-size: 0.85rem; background: var(--accent-blue); flex-shrink: 0; font-weight: 800;">
                                        <?= strtoupper(substr($member['name'], 0, 1)) ?>
                                    </div>
                                    <div style="min-width: 0;">
                                        <div style="line-height: 1.2; font-weight: 700; color: #1E293B; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?= htmlspecialchars($member['name']) ?>
                                        </div>
                                        <?php if(!empty($member['batch'])): ?>
                                            <div style="font-size: 0.72rem; color: #64748B; font-weight: 600;">
                                                <?= htmlspecialchars($member['batch']) ?> • <?= htmlspecialchars($member['faculty'] ?? 'Computing') ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if($is_mem_creator): ?>
                                    <span style="background: #FEF3C7; color: #B45309; padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 800; border: 1px solid #FCD34D; flex-shrink: 0;">👑 Admin</span>
                                <?php else: ?>
                                    <span style="background: #F1F5F9; color: #64748B; padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; flex-shrink: 0;">Member</span>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                </div>

                <div class="grid-col-right">

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                        <div>
                            <h2 style="font-size: 1.35rem; color: #0F172A; display: flex; align-items: center; gap: 8px;">
                                <span>📚</span> Study Materials & Notes
                            </h2>
                            <p style="color: #64748B; font-size: 0.85rem; font-weight: 600; margin-top: 2px;">
                                Shared notes, slides, and past papers.
                            </p>
                        </div>
                        <button onclick="document.getElementById('materialModal').classList.add('active')" class="btn btn-primary" style="background: var(--accent-green); padding: 0.55rem 0.95rem; font-size: 0.85rem; font-weight: 800;">
                            + Share Note / File
                        </button>
                    </div>

                    <?php if(!empty($pinned_materials)): ?>
                    <div style="background: #FEF3C7; border: 1px solid #FCD34D; color: #92400E; padding: 0.6rem 0.9rem; border-radius: 8px; font-size: 0.82rem; font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between;">
                        <span style="display: flex; align-items: center; gap: 6px;">
                            <span>📌</span> <strong><?= count($pinned_materials) ?> Material<?= count($pinned_materials) > 1 ? 's' : '' ?> Pinned by Admin</strong>
                        </span>
                        <span style="font-size: 0.75rem; color: #B45309;">Featured on top</span>
                    </div>
                    <?php endif; ?>

                    <div class="materials-grid">
                        <?php foreach($materials as $mat):
                            $is_pinned = ($mat['is_pinned'] == 1);
                            $type_icon = '📄';
                            if ($mat['file_type'] === 'pdf') $type_icon = '📕';
                            elseif ($mat['file_type'] === 'word') $type_icon = '📘';
                            elseif ($mat['file_type'] === 'powerpoint') $type_icon = '📙';
                            elseif ($mat['file_type'] === 'excel') $type_icon = '📗';
                            elseif ($mat['file_type'] === 'archive') $type_icon = '🗂️';
                            elseif ($mat['file_type'] === 'image') $type_icon = '🖼️';
                            elseif ($mat['file_type'] === 'code') $type_icon = '💻';
                        ?>
                        <div class="material-card <?= $is_pinned ? 'is-pinned' : '' ?>" id="mat-card-<?= $mat['id'] ?>">

                            <div style="display: flex; align-items: flex-start; gap: 1rem;">
                                <div class="material-file-icon">
                                    <?= $type_icon ?>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 3px; flex-wrap: wrap;">
                                        <h3 style="margin: 0; font-size: 1.05rem; color: #0F172A; font-weight: 700; word-break: break-word;">
                                            <a href="../<?= htmlspecialchars($mat['file_path']) ?>" target="_blank" style="color: inherit; text-decoration: none;">
                                                <?= htmlspecialchars($mat['title']) ?>
                                            </a>
                                        </h3>
                                        <?php if($is_pinned): ?>
                                            <span class="pinned-tag">📌 PINNED BY ADMIN</span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if(!empty($mat['description'])): ?>
                                        <p style="color: #475569; font-size: 0.88rem; margin: 0 0 6px 0; line-height: 1.4; word-break: break-word;">
                                            <?= htmlspecialchars($mat['description']) ?>
                                        </p>
                                    <?php endif; ?>

                                    <div style="font-size: 0.78rem; color: #64748B; font-weight: 600; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                        <span>👤 <strong><?= htmlspecialchars($mat['uploader_name']) ?></strong></span>
                                        <?php if(!empty($mat['uploader_batch'])): ?>
                                            <span style="background: #ECFDF5; color: #047857; padding: 1px 5px; border-radius: 4px; font-weight: 700; font-size: 0.7rem;"><?= htmlspecialchars($mat['uploader_batch']) ?></span>
                                        <?php endif; ?>
                                        <span>•</span>
                                        <span>📦 <?= htmlspecialchars($mat['file_size']) ?></span>
                                        <span>•</span>
                                        <span>📅 <?= date('M d, Y', strtotime($mat['created_at'])) ?></span>
                                    </div>
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid #F1F5F9; padding-top: 0.75rem; margin-top: 0.25rem;">
                                <div style="font-size: 0.72rem; color: #94A3B8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <?= strtoupper(htmlspecialchars($mat['file_type'])) ?> FILE
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">

                                    <a href="../<?= htmlspecialchars($mat['file_path']) ?>" download class="btn" style="background: #EFF6FF; color: var(--accent-blue); padding: 0.45rem 0.85rem; font-size: 0.82rem; border-radius: 6px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                        ⬇️ Download
                                    </a>

                                    <?php if($is_creator): ?>
                                    <form method="POST" style="margin: 0; display: inline-block;">
                                        <input type="hidden" name="action" value="toggle_pin">
                                        <input type="hidden" name="material_id" value="<?= $mat['id'] ?>">
                                        <?php if($is_pinned): ?>
                                            <button type="submit" class="btn" style="background: #FEF3C7; color: #B45309; border: 1px solid #FCD34D; padding: 0.45rem 0.75rem; font-size: 0.82rem; border-radius: 6px; font-weight: 700; cursor: pointer;" title="Unpin this material">
                                                📍 Unpin
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn" style="background: #F1F5F9; color: #475569; border: 1px solid #CBD5E1; padding: 0.45rem 0.75rem; font-size: 0.82rem; border-radius: 6px; font-weight: 700; cursor: pointer;" title="Pin this material to top">
                                                📌 Pin
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                    <?php endif; ?>

                                    <?php if($is_creator || $mat['user_id'] == $_SESSION['user_id']): ?>
                                    <form method="POST" style="margin: 0; display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this study material?');">
                                        <input type="hidden" name="action" value="delete_material">
                                        <input type="hidden" name="material_id" value="<?= $mat['id'] ?>">
                                        <button type="submit" class="btn" style="background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; padding: 0.45rem 0.65rem; border-radius: 6px; cursor: pointer; font-size: 0.82rem;" title="Delete material">
                                            🗑
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <?php if(empty($materials)): ?>
                        <div style="text-align: center; padding: 2.5rem 1.5rem; background: white; border-radius: var(--border-radius-md); border: 2px dashed var(--border-color);">
                            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📁</div>
                            <h3 style="margin-bottom: 0.35rem; font-size: 1.05rem; color: #1E293B;">No Study Materials Shared Yet</h3>
                            <p style="color: var(--text-secondary); margin-bottom: 1.2rem; font-size: 0.85rem; font-weight: 600;">Share past paper solutions, lecture notes, or slides with your group.</p>
                            <button onclick="document.getElementById('materialModal').classList.add('active')" class="btn btn-primary" style="background: var(--accent-green); font-size: 0.85rem; font-weight: 700;">
                                + Share First Material
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>

                </div>

            </div>

        </main>
    </div>

    <?php include '../includes/footer.php'; ?>

    <div id="materialModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="margin: 0; font-size: 1.35rem; display: flex; align-items: center; gap: 8px;">
                    <span>📚</span> Share Study Material
                </h2>
                <button onclick="document.getElementById('materialModal').classList.remove('active')" style="background: none; border: none; font-size: 1.75rem; cursor: pointer; color: var(--text-secondary);">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_material">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="font-weight: 700;">Material Title</label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. Chapter 4 Lecture Slides / Past Paper 2024">
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="font-weight: 700;">Description (Optional)</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Add helpful notes, topic coverage, or instructions for your peers..."></textarea>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label" style="font-weight: 700;">Choose File</label>
                    <input type="file" name="material_file" class="form-control" required style="padding: 0.6rem 1rem;">
                    <small style="color: #64748B; font-weight: 600; display: block; margin-top: 4px;">Supports PDF, Word, PowerPoint, Excel, Images, Code & ZIP files (Max 50MB)</small>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; background: var(--accent-green); padding: 0.9rem; font-size: 1rem; font-weight: 800;">
                    Upload & Share with Group 🚀
                </button>
            </form>
        </div>
    </div>

    <div id="sessionModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="margin: 0; font-size: 1.35rem;">Schedule a Live Session 📹</h2>
                <button onclick="document.getElementById('sessionModal').classList.remove('active')" style="background: none; border: none; font-size: 1.75rem; cursor: pointer; color: var(--text-secondary);">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="new_session">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="font-weight: 700;">Session Topic</label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. Past Paper Discussion 2023">
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="font-weight: 700;">Date & Time</label>
                    <input type="datetime-local" name="schedule_time" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label" style="font-weight: 700;">Meeting Link (Optional)</label>
                    <input type="url" name="meeting_link" class="form-control" placeholder="Leave empty for In-App Jitsi Meeting">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; background: var(--accent-green); padding: 0.9rem; font-size: 1rem; font-weight: 800;">
                    Schedule Meeting 🗓️
                </button>
            </form>
        </div>
    </div>

    <div id="jitsiModal" class="modal" style="z-index: 2000;">
        <div class="modal-content" style="width: 95%; max-width: 1200px; height: 90vh; padding: 0; display: flex; flex-direction: column; overflow: hidden;">
            <div style="background: #1E293B; color: white; padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                <h3 id="jitsiModalTitle" style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <span style="display: inline-block; width: 10px; height: 10px; background: #10B981; border-radius: 50%; box-shadow: 0 0 8px #10B981;"></span>
                    Live Group Session
                </h3>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <?php if($is_creator): ?>
                    <button onclick="hostEndMeetingFromModal()" id="btnModalEnd" class="btn" style="background: #EF4444; color: white; border: none; padding: 0.45rem 1rem; font-size: 0.85rem; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                        ⏹ End Meeting for All
                    </button>
                    <?php endif; ?>
                    <button onclick="closeJitsi()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
                </div>
            </div>
            <div id="jitsi-container" style="flex: 1; width: 100%; position: relative; background: #0F172A;">

                <div style="position: absolute; top: 10px; left: 10px; height: 45px; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); z-index: 10; pointer-events: none; display: flex; align-items: center; padding: 0 15px 0 10px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.05); box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <img src="../assets/img/nsbm-logo.png" alt="NSBM" style="height: 26px; filter: brightness(1.2);">
                    <div style="margin-left: 8px; display: flex; flex-direction: column; line-height: 1;">
                        <span style="color: white; font-weight: 800; font-size: 0.8rem;">NSBM</span>
                        <span style="color: #10B981; font-weight: 700; font-size: 0.55rem; text-transform: uppercase;">Study Circle</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://meet.jit.si/external_api.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/groups.js"></script>
</body>
</html>
