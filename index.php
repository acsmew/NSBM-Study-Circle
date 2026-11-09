<?php

require_once 'includes/functions.php';

if (isset($_GET['admin'])) {
    if (isLoggedIn() && !isAdmin()) {
        session_unset();
        session_destroy();
        session_start();
    }
}

if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/index.php');
    } else {
        redirect('student/index.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $pdo->prepare("SELECT id, name, batch, faculty, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['batch'] = $user['batch'] ?? '26.1';
            $_SESSION['faculty'] = $user['faculty'] ?? 'Computing';
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') {
                redirect('admin/index.php');
            } else {
                redirect('student/index.php');
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $batch = trim($_POST['batch'] ?? '26.1');
    $faculty = trim($_POST['faculty'] ?? 'Computing');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($batch)) $batch = '26.1';
    if (empty($faculty)) $faculty = 'Computing';

    if (empty($name) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email is already registered.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, batch, faculty, role) VALUES (?, ?, ?, ?, ?, 'student')");
            if ($stmt->execute([$name, $email, $hashed, $batch, $faculty])) {
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['name'] = $name;
                $_SESSION['batch'] = $batch;
                $_SESSION['faculty'] = $faculty;
                $_SESSION['role'] = 'student';
                redirect('student/index.php');
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
$active_tab = (isset($_POST['action']) && $_POST['action'] === 'register') || (isset($_GET['tab']) && $_GET['tab'] === 'register') ? 'register' : 'login';
$is_admin_mode = isset($_GET['admin']);
$login_email = $is_admin_mode ? 'admin@nsbm.ac.lk' : '';
$login_pass = $is_admin_mode ? 'password' : '';
$reg_name = isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '';
$reg_email = isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '';
$reg_batch = isset($_POST['batch']) ? htmlspecialchars($_POST['batch']) : '26.1';
$reg_faculty = isset($_POST['faculty']) ? htmlspecialchars($_POST['faculty']) : 'IFSL Computing';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NSBM StudyCircle - Connect, Share, Learn</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page">

    <div class="auth-page-backdrop"></div>

    <div class="auth-layout-container">

        <div class="auth-form-section">
            <div class="auth-card">

                <div class="auth-card-top">
                    <div class="auth-crest-wrap">
                        <img src="assets/img/nsbm-logo.png" alt="NSBM Green University" class="auth-crest-img">
                    </div>
                    <div>
                        <h2 class="auth-card-title">StudyCircle</h2>
                        <span class="auth-card-sub">Student Portal</span>
                    </div>
                </div>

                <?php if (!$is_admin_mode): ?>
                <div class="auth-tab-nav">
                    <button type="button" class="auth-tab-btn <?= $active_tab === 'login' ? 'active' : '' ?>" id="tabLoginBtn" onclick="toggleForms(event, 'login')">
                        Sign In
                    </button>
                    <button type="button" class="auth-tab-btn <?= $active_tab === 'register' ? 'active' : '' ?>" id="tabRegisterBtn" onclick="toggleForms(event, 'register')">
                        Create Account
                    </button>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger" style="text-align: left; border-radius: 12px; font-size: 0.88rem; margin-bottom: 1.25rem; padding: 0.75rem 1rem;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($is_admin_mode): ?>
                    <div style="padding: 0.75rem 1rem; background: #ECFDF5; border: 1.5px solid #A7F3D0; color: #065F46; border-radius: 12px; margin-bottom: 1.25rem; font-weight: 700; font-size: 0.88rem; text-align: left; display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 1.2rem;">👑</span>
                        <span>Admin Credentials pre-filled! Click <strong>Sign In</strong> below.</span>
                    </div>
                <?php endif; ?>

                <form method="POST" class="auth-form login-form <?= $active_tab === 'login' ? 'active-form' : '' ?>" id="loginForm">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label class="form-label">School Email</label>
                        <input type="email" name="email" class="form-control" required placeholder="student@nsbm.ac.lk" value="<?= htmlspecialchars($login_email) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required placeholder="••••••••" value="<?= htmlspecialchars($login_pass) ?>">
                    </div>
                    <button type="submit" class="auth-btn-submit">Sign In to Study Circle &rarr;</button>
                    <div class="auth-card-footer">
                        <div>
                            New student? <a href="#register" onclick="toggleForms(event, 'register')">Create account</a>
                        </div>
                        <?php if (!$is_admin_mode): ?>
                            <a href="index.php?admin=1" style="color: #64748B;">👑 Admin</a>
                        <?php else: ?>
                            <a href="index.php" style="color: #64748B;">👤 Student Login</a>
                        <?php endif; ?>
                    </div>
                </form>

                <form method="POST" class="auth-form register-form <?= $active_tab === 'register' ? 'active-form' : '' ?>" id="registerForm">
                    <input type="hidden" name="action" value="register">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Kasun Perera" value="<?= $reg_name ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">School Email</label>
                        <input type="email" name="email" class="form-control" required placeholder="student@nsbm.ac.lk" value="<?= $reg_email ?>">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 0.75rem;">
                        <div class="form-group">
                            <label class="form-label">Batch</label>
                            <input type="text" name="batch" class="form-control" required placeholder="e.g. 26.1" value="<?= $reg_batch ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Faculty</label>
                            <input type="text" name="faculty" class="form-control" required placeholder="e.g. Computing" value="<?= $reg_faculty ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Create a Password</label>
                        <input type="password" name="password" class="form-control" required placeholder="••••••••">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required placeholder="••••••••">
                    </div>
                    <button type="submit" class="auth-btn-submit">Create My Account &rarr;</button>
                    <div class="auth-card-footer" style="justify-content: center;">
                        <div>
                            Already registered? <a href="#login" onclick="toggleForms(event, 'login')">Sign in here</a>
                        </div>
                    </div>
                </form>

            </div>
        </div>

    </div>

    <script src="assets/js/auth.js"></script>
</body>
</html>
