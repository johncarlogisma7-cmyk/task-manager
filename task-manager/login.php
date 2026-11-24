<?php

declare(strict_types=1);
require __DIR__ . '/auth.php';

if (!empty($_SESSION['user_id'])) {
    $activeCheck = $pdo->prepare('SELECT is_active FROM users WHERE id = ?');
    $activeCheck->execute([(int)$_SESSION['user_id']]);
    if ((int)$activeCheck->fetchColumn() === 1) {
        header('Location: index.php');
        exit;
    }
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_role']);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $passwordInput = (string)($_POST['password'] ?? '');
    $query = $pdo->prepare('SELECT id, name, password_hash, role, is_active FROM users WHERE email = ?');
    $query->execute([$email]);
    $user = $query->fetch();
    if ($user && (int)$user['is_active'] === 1 && password_verify($passwordInput, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        header('Location: index.php');
        exit;
    }
    $error = 'Email or password is incorrect.';
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in · My Tasks</title>
    <link rel="stylesheet" href="style.css">
</head>

<body class="auth-body">
    <main class="auth-card"><a class="brand" href="login.php">✓ <span>My Tasks</span></a>
        <p class="eyebrow">WELCOME BACK</p>
        <h1>Log in</h1>
        <p class="auth-sub">Sign in to see your tasks.</p>
        <?php if (isset($_GET['disabled'])): ?><p class="notice" role="alert">This account is inactive. Contact an administrator.</p><?php endif; ?>
        <?php if ($error): ?><p class="notice" role="alert"><?= h($error) ?></p><?php endif; ?>
        <form method="post" class="auth-form"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><label class="field">Email<input type="email" name="email" required autocomplete="email" value="<?= h($_POST['email'] ?? '') ?>"></label><label class="field">Password<input type="password" name="password" required autocomplete="current-password"></label><button class="primary" type="submit">Log in</button></form>
        <p class="auth-switch">New here? <a href="register.php">Create an account</a></p>
    </main>
</body>

</html>