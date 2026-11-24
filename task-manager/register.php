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
    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $passwordInput = (string)($_POST['password'] ?? '');
    if ($name === '' || mb_strlen($name) > 80) {
        $error = 'Enter your name (up to 80 characters).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($passwordInput) < 8) {
        $error = 'Use a password with at least 8 characters.';
    } else {
        try {
            $query = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
            $query->execute([$name, $email, password_hash($passwordInput, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$pdo->lastInsertId();
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = 'user';
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            header('Location: index.php');
            exit;
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = 'An account with that email already exists.';
            } else {
                throw $exception;
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create account · My Tasks</title>
    <link rel="stylesheet" href="style.css">
</head>

<body class="auth-body">
    <main class="auth-card"><a class="brand" href="login.php">✓ <span>My Tasks</span></a>
        <p class="eyebrow">GET STARTED</p>
        <h1>Create account</h1>
        <p class="auth-sub">Your task list is private to your account.</p>
        <?php if ($error): ?><p class="notice" role="alert"><?= h($error) ?></p><?php endif; ?>
        <form method="post" class="auth-form"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><label class="field">Your name<input name="name" maxlength="80" required autocomplete="name" value="<?= h($_POST['name'] ?? '') ?>"></label><label class="field">Email<input type="email" name="email" maxlength="190" required autocomplete="email" value="<?= h($_POST['email'] ?? '') ?>"></label><label class="field">Password<input type="password" name="password" minlength="8" required autocomplete="new-password"><span class="hint">At least 8 characters</span></label><button class="primary" type="submit">Create account</button></form>
        <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
    </main>
</body>

</html>