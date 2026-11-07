<?php
require_once '../includes/functions.php';
requireStudent();

$error = '';
$success = '';

if (isset($_SESSION['error_msg'])) {
    $error = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}
if (isset($_SESSION['success_msg'])) {
    $success = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
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

function generateUniqueGroupCode($pdo) {
    do {
        $rand_part = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));
        $candidate = 'GRP-' . $rand_part;
        $chk = $pdo->prepare("SELECT id FROM study_groups WHERE UPPER(join_code) = ?");
        $chk->execute([$candidate]);
    } while ($chk->fetch());
    return $candidate;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_group') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $custom_code = strtoupper(trim($_POST['custom_code'] ?? ''));

    if (empty($name)) {
        $error = "Group name is required.";
    } else {
        $join_code = '';
        if (!empty($custom_code)) {

            $custom_code = preg_replace('/[^A-Z0-9\-]/', '', $custom_code);
            if (strlen($custom_code) < 3 || strlen($custom_code) > 16) {
                $error = "Custom join code must be between 3 and 16 characters (letters and numbers only).";
            } else {
                $chk = $pdo->prepare("SELECT id FROM study_groups WHERE UPPER(join_code) = ?");
                $chk->execute([$custom_code]);
                if ($chk->fetch()) {
                    $error = "The join code '$custom_code' is already in use by another group. Please choose another code or leave blank to auto-generate.";
                } else {
                    $join_code = $custom_code;
                }
            }
        }

        if (empty($error)) {
            if (empty($join_code)) {
                $join_code = generateUniqueGroupCode($pdo);
            }

            $stmt = $pdo->prepare("INSERT INTO study_groups (name, description, join_code, created_by) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$name, $description, $join_code, $_SESSION['user_id']])) {
                $group_id = $pdo->lastInsertId();

                $stmt2 = $pdo->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
                $stmt2->execute([$group_id, $_SESSION['user_id']]);

                $_SESSION['flash_created_code'] = $join_code;
                $_SESSION['flash_created_group'] = $name;
                redirect("group_view.php?id=" . $group_id);
            } else {
                $error = "Failed to create group.";
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'join_group') {
    $input_code = strtoupper(trim($_POST['join_code'] ?? ''));
    $group_id = (int)($_POST['group_id'] ?? 0);

    if (empty($input_code)) {
        $error = "Please enter the group's join code.";
    } else {
        $target_group = null;
        if ($group_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM study_groups WHERE id = ?");
            $stmt->execute([$group_id]);
            $target_group = $stmt->fetch();

            if (!$target_group) {
                $error = "Group not found.";
            } elseif (strtoupper(trim($target_group['join_code'] ?? '')) !== $input_code) {
                $error = "Invalid join code for '" . htmlspecialchars($target_group['name']) . "'. Please ask the group admin for the correct code.";
            }
        } else {

            $stmt = $pdo->prepare("SELECT * FROM study_groups WHERE UPPER(TRIM(join_code)) = ?");
            $stmt->execute([$input_code]);
            $target_group = $stmt->fetch();

            if (!$target_group) {
                $error = "No study group found with join code: " . htmlspecialchars($input_code);
            }
        }

        if (empty($error) && !empty($target_group)) {
            $gid = $target_group['id'];

            $chk = $pdo->prepare("SELECT COUNT(*) FROM group_members WHERE group_id = ? AND user_id = ?");
            $chk->execute([$gid, $_SESSION['user_id']]);
            if ($chk->fetchColumn() > 0) {
                $_SESSION['success_msg'] = "You are already a member of " . htmlspecialchars($target_group['name']) . "!";
                redirect("group_view.php?id=" . $gid);
            } else {
                try {
                    $join_stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
                    $join_stmt->execute([$gid, $_SESSION['user_id']]);
                    $_SESSION['success_msg'] = "🎉 Successfully joined " . htmlspecialchars($target_group['name']) . "!";
                    redirect("group_view.php?id=" . $gid);
                } catch (Exception $e) {
                    $error = "Failed to join group. Please try again.";
                }
            }
        }
    }
}

$stmt = $pdo->query("SELECT g.*, u.name as creator_name,
                     (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count,
                     (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND user_id = {$_SESSION['user_id']}) as is_member
                     FROM study_groups g
                     LEFT JOIN users u ON g.created_by = u.id
                     ORDER BY g.created_at DESC");
$groups = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Groups - NSBM StudyCircle</title>
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

        <main class="main-content">

        <div class="info-banner" style="background: #F0FDF4; border-color: #D1FAE5; margin-bottom: 1.5rem;">
            <div class="info-banner-left">
                <div class="info-banner-icon" style="color: #059669; font-size: 2rem;">👥</div>
                <div>
                    <h4 style="color: #065F46; font-size: 1.2rem;">Collaborate and Learn Together</h4>
                    <p style="color: #047857;">Create private study groups with secret join codes, share revision notes, and host live study sessions.</p>
                </div>
            </div>
            <button onclick="document.getElementById('groupModal').classList.add('active')" class="btn" style="background: #059669; color: white; font-weight: 700; white-space: nowrap;">+ Create New Group</button>
        </div>

        <div class="quick-join-card" style="background: white; border: 1px solid var(--border-color); border-radius: var(--border-radius-md); padding: 1.25rem 1.5rem; margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between; gap: 1.25rem; flex-wrap: wrap; box-shadow: var(--shadow-sm);">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 46px; height: 46px; border-radius: 12px; background: #EEF2FF; color: #4F46E5; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">🔑</div>
                <div>
                    <h4 style="margin: 0 0 3px 0; color: #1E293B; font-size: 1.05rem; font-weight: 800;">Have an Invite Code?</h4>
                    <p style="margin: 0; color: #64748B; font-size: 0.88rem;">Enter the code shared by your group admin to join instantly.</p>
                </div>
            </div>
            <form method="POST" class="quick-join-form" style="display: flex; gap: 8px; flex-grow: 1; max-width: 440px; margin: 0;">
                <input type="hidden" name="action" value="join_group">
                <input type="text" name="join_code" placeholder="Enter Join Code (e.g. GRP-4821)" required style="flex-grow: 1; padding: 0.7rem 1rem; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 0.95rem; text-transform: uppercase; font-family: monospace; font-weight: 700; letter-spacing: 0.5px;">
                <button type="submit" class="btn btn-primary" style="background: #4F46E5; color: white; white-space: nowrap; font-weight: 700; padding: 0.7rem 1.3rem; border: none; cursor: pointer;">🔑 Join Group</button>
            </form>
        </div>

        <?php if($error): ?><div style="padding: 1rem 1.25rem; background: #FEE2E2; color: #991B1B; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 700; border-left: 4px solid #EF4444;"><?= $error ?></div><?php endif; ?>
        <?php if($success): ?><div style="padding: 1rem 1.25rem; background: #D1FAE5; color: #065F46; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 700; border-left: 4px solid #10B981;"><?= $success ?></div><?php endif; ?>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h2>Active Study Groups</h2>
            <span style="color: var(--text-secondary); font-size: 0.9rem; font-weight: 600;"><?= count($groups) ?> Groups Available</span>
        </div>

        <div class="grid-container">
            <?php foreach($groups as $group):
                $is_creator = ($group['created_by'] == $_SESSION['user_id']);
            ?>
            <div class="group-card">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.85rem;">
                    <div class="group-icon" style="margin-bottom: 0;">📚</div>
                    <?php if($is_creator): ?>
                        <span style="background: #ECFDF5; color: #059669; font-size: 0.75rem; font-weight: 800; padding: 3px 8px; border-radius: 20px; border: 1px solid #A7F3D0;">👑 You're Admin</span>
                    <?php elseif($group['is_member']): ?>
                        <span style="background: #F1F5F9; color: #475569; font-size: 0.75rem; font-weight: 800; padding: 3px 8px; border-radius: 20px;">✓ Member</span>
                    <?php endif; ?>
                </div>

                <h3 style="font-size: 1.2rem; margin-bottom: 0.5rem;"><?= htmlspecialchars($group['name']) ?></h3>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1rem; flex-grow: 1;"><?= htmlspecialchars($group['description'] ?: 'No description provided.') ?></p>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; padding-top: 0.85rem; border-top: 1px solid var(--border-color);">
                    <span style="font-weight: 700; font-size: 0.85rem; color: var(--text-secondary);">👤 <?= $group['member_count'] ?> Members</span>
                    <span style="font-weight: 700; font-size: 0.85rem; color: var(--text-secondary);">Admin: <?= htmlspecialchars(explode(' ', $group['creator_name'])[0]) ?></span>
                </div>

                <?php if($is_creator): ?>

                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 0.85rem; background: #F8FAFC; border: 1px dashed #CBD5E1; padding: 0.45rem 0.75rem; border-radius: 6px;">
                        <span style="font-size: 0.82rem; color: #64748B; font-weight: 600;">🔑 Code: <strong style="color: #0F172A; font-family: monospace; font-size: 0.9rem; letter-spacing: 0.5px;"><?= htmlspecialchars($group['join_code'] ?? 'N/A') ?></strong></span>
                        <button type="button" onclick="copyJoinCode('<?= htmlspecialchars($group['join_code'] ?? '', ENT_QUOTES) ?>')" style="background: none; border: none; cursor: pointer; color: #4F46E5; font-size: 0.8rem; font-weight: 700; padding: 2px 6px;" title="Copy Code">📋 Copy</button>
                    </div>
                    <a href="group_view.php?id=<?= $group['id'] ?>" class="btn" style="width: 100%; background: #059669; color: white; text-align: center; display: inline-block; text-decoration: none; padding: 0.75rem; font-weight: 700; border-radius: 8px;">Open Group Dashboard &rarr;</a>
                <?php elseif($group['is_member']): ?>

                    <a href="group_view.php?id=<?= $group['id'] ?>" class="btn" style="width: 100%; background: #F1F5F9; color: var(--accent-green); text-align: center; display: inline-block; text-decoration: none; padding: 0.75rem; font-weight: 700; border-radius: 8px;">✓ Joined (View)</a>
                <?php else: ?>

                    <button type="button" onclick="openJoinModal(<?= $group['id'] ?>, '<?= htmlspecialchars($group['name'], ENT_QUOTES) ?>')" class="btn btn-primary" style="width: 100%; background: var(--accent-green); font-weight: 700; padding: 0.75rem; border-radius: 8px;">
                        🔒 Enter Code to Join
                    </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php if(empty($groups)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; background: white; border-radius: var(--border-radius-md); border: 2px dashed var(--border-color);">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📚</div>
                <h3 style="margin-bottom: 0.5rem;">No groups available yet</h3>
                <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Start the community by creating the first study group!</p>
                <button onclick="document.getElementById('groupModal').classList.add('active')" class="btn btn-primary" style="background: var(--accent-green);">Create a Group</button>
            </div>
            <?php endif; ?>
        </div>
        </main>
    </div>

    <?php include '../includes/footer.php'; ?>

    <div id="groupModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2>Create Study Group</h2>
                <button onclick="document.getElementById('groupModal').classList.remove('active')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">×</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_group">
                <div class="form-group">
                    <label class="form-label">Group Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Computing Batch 26.1 Study Hub">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="What will this group focus on? (Exam prep, assignments, etc.)"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Group Join Code (Optional)</span>
                        <span style="font-weight: 500; font-size: 0.8rem; color: #64748B;">Auto-generated if empty</span>
                    </label>
                    <input type="text" name="custom_code" class="form-control" placeholder="e.g. BATCH26-SE (or leave blank)" style="text-transform: uppercase; font-family: monospace; font-weight: 600; letter-spacing: 0.5px;">
                    <small style="color: #64748B; font-size: 0.8rem; margin-top: 5px; display: block;">Only students who have this code will be able to join your study group.</small>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; background: var(--accent-green); padding: 0.85rem; font-weight: 700; font-size: 1rem;">Create Study Group</button>
            </form>
        </div>
    </div>

    <div id="joinModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h2 style="font-size: 1.35rem; margin: 0; color: #0F172A;">🔑 Enter Join Code</h2>
                <button onclick="document.getElementById('joinModal').classList.remove('active')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">×</button>
            </div>
            <p style="color: var(--text-secondary); margin-bottom: 1.25rem; font-size: 0.92rem; line-height: 1.5;">
                Please enter the secret Join Code shared by the group admin to join <strong id="joinGroupName" style="color: #0F172A;">this study group</strong>:
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="join_group">
                <input type="hidden" name="group_id" id="joinGroupId" value="">
                <div class="form-group">
                    <label class="form-label">Join Code</label>
                    <input type="text" name="join_code" id="joinCodeInput" class="form-control" required placeholder="e.g. GRP-4821" style="text-transform: uppercase; font-family: monospace; font-size: 1.15rem; font-weight: 800; letter-spacing: 1px; padding: 0.75rem 1rem;">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; background: var(--accent-green); padding: 0.85rem; font-weight: 700; font-size: 1rem;">Verify Code & Join Group</button>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/groups.js"></script>
</body>
</html>