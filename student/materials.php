<?php
require_once '../includes/functions.php';
requireStudent();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_note') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $subject_id = (int)($_POST['subject_id'] ?? 1);
    $faculty = $_POST['faculty'] ?? ($_SESSION['faculty'] ?? 'Computing');
    $category = $_POST['category'] ?? 'Lecture Notes';
    $year_level = $_POST['year_level'] ?? '1st Year';

    if (empty($title) || empty($_FILES['note_file']['name'])) {
        $error = "Title and File are required.";
    } else {
        $target_dir = "../uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_extension = strtolower(pathinfo($_FILES["note_file"]["name"], PATHINFO_EXTENSION));
        $file_size_bytes = $_FILES["note_file"]["size"] ?? 0;
        $size_str = formatFileBytes($file_size_bytes);
        $new_filename = uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;

        $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip'];
        if (!in_array($file_extension, $allowed)) {
            $error = "Sorry, only PDF, DOC, PPT, & ZIP files are allowed.";
        } else {
            if (move_uploaded_file($_FILES["note_file"]["tmp_name"], $target_file)) {
                $stmt = $pdo->prepare("INSERT INTO materials (user_id, subject_id, title, description, file_path, status, faculty, category, year_level, file_size) VALUES (?, ?, ?, ?, ?, 'approved', ?, ?, ?, ?)");
                if ($stmt->execute([$_SESSION['user_id'], $subject_id, $title, $description, $new_filename, $faculty, $category, $year_level, $size_str])) {
                    $success = "Note uploaded successfully and is now live on NSBM StudyCircle! 📚";
                } else {
                    $error = "Database error during upload.";
                }
            } else {
                $error = "Sorry, there was an error uploading your file.";
            }
        }
    }
}

$subjects = $pdo->query("SELECT * FROM subjects")->fetchAll();
if (empty($subjects)) {
    $pdo->query("INSERT INTO subjects (name, description) VALUES ('General', 'General'), ('Computing', 'Computing Faculty'), ('Business', 'Business Faculty')");
    $subjects = $pdo->query("SELECT * FROM subjects")->fetchAll();
}

$stmt = $pdo->prepare("SELECT m.*, s.name as subject_name FROM materials m LEFT JOIN subjects s ON m.subject_id = s.id WHERE m.user_id = ? ORDER BY m.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$my_uploads = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Notes - NSBM StudyCircle</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
                <li><a href="forum.php" class="sidebar-link"><span class="sidebar-icon">💬</span><span class="sidebar-text">Forum</span></a></li>
                <li><a href="groups.php" class="sidebar-link"><span class="sidebar-icon">👥</span><span class="sidebar-text">Study Groups</span></a></li>
            </ul>
        </aside>

        <main class="main-content" style="max-width: 800px;">
        <div class="hero-banner" style="background: linear-gradient(135deg, #EFF6FF, #E0E7FF); padding: 2rem; margin-bottom: 2rem;">
            <div class="hero-content">
                <h1 style="font-size: 2rem;">Share Your Knowledge</h1>
                <p>Upload your lecture notes, summaries, or past papers to help your peers and earn StudyCredits!</p>
            </div>
        </div>

        <?php if($error): ?><div style="padding: 1rem; background: #FEE2E2; color: #991B1B; border-radius: 8px; margin-bottom: 1rem; font-weight: 700;"><?= $error ?></div><?php endif; ?>
        <?php if($success): ?><div style="padding: 1rem; background: #D1FAE5; color: #065F46; border-radius: 8px; margin-bottom: 1rem; font-weight: 700;"><?= $success ?></div><?php endif; ?>

        <div class="upload-card" style="background: white; border-radius: var(--border-radius-lg); padding: 2.5rem; box-shadow: var(--shadow-md); border: 1px solid var(--border-color); margin-bottom: 3rem;">
            <h2 style="margin-bottom: 1.5rem;">Upload a new Note</h2>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_note">

                <div class="form-group">
                    <label class="form-label">Title of the Note</label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. Data Structures Chapter 1 Summary">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Faculty</label>
                        <select name="faculty" class="form-control" required>
                            <option value="Computing">Computing</option>
                            <option value="Business">Business</option>
                            <option value="Engineering">Engineering</option>
                            <option value="Science">Science</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-control" required>
                            <option value="Lecture Notes">Lecture Notes</option>
                            <option value="Past Papers">Past Papers</option>
                            <option value="Summaries">Summaries</option>
                            <option value="Assignments">Assignments</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Year Level</label>
                        <select name="year_level" class="form-control" required>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Subject / Module</label>
                        <select name="subject_id" class="form-control">
                            <?php foreach($subjects as $sub): ?>
                                <option value="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description (Optional)</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Briefly describe what this note covers..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">File Attachment (PDF, DOCX, PPT)</label>
                    <input type="file" name="note_file" class="form-control" style="padding: 0.6rem;" required accept=".pdf,.doc,.docx,.ppt,.pptx,.zip">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 1.1rem; padding: 1rem;">Submit Note for Approval</button>
            </form>
        </div>

        <h2>My Uploads</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($my_uploads as $upload): ?>
                    <tr>
                        <td style="font-weight: 700;"><?= htmlspecialchars($upload['title']) ?></td>
                        <td><?= htmlspecialchars($upload['subject_name']) ?></td>
                        <td>
                            <?php if($upload['status'] === 'approved'): ?>
                                <span style="background: #D1FAE5; color: #065F46; padding: 0.25rem 0.75rem; border-radius: 50px; font-weight: 800; font-size: 0.75rem;">Approved</span>
                            <?php elseif($upload['status'] === 'rejected'): ?>
                                <span style="background: #FEE2E2; color: #991B1B; padding: 0.25rem 0.75rem; border-radius: 50px; font-weight: 800; font-size: 0.75rem;">Rejected</span>
                            <?php else: ?>
                                <span style="background: #FEF3C7; color: #D97706; padding: 0.25rem 0.75rem; border-radius: 50px; font-weight: 800; font-size: 0.75rem;">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td style="color: var(--text-secondary); font-weight: 700;"><?= date('M d, Y', strtotime($upload['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($my_uploads)): ?>
                    <tr><td colspan="4" style="text-align: center; color: var(--text-secondary); padding: 2rem;">You haven't uploaded any notes yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </div>
        </main>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="../assets/js/main.js"></script>
</body>
</html>
