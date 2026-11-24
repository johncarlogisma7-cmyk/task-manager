<?php

declare(strict_types=1);

session_start();

$host = getenv('DB_HOST') ?: '127.0.0.1';
$database = getenv('DB_NAME') ?: 'task_manager';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO("mysql:host={$host};dbname={$database};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    exit('Could not connect to MySQL. Check the database settings in README.md.');
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function verify_csrf(): void
{
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid request. Refresh the page and try again.');
    }
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    $query = $GLOBALS['pdo']->prepare('SELECT name, role, is_active FROM users WHERE id = ?');
    $query->execute([(int)$_SESSION['user_id']]);
    $user = $query->fetch();
    if (!$user || !(int)$user['is_active']) {
        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_role']);
        session_regenerate_id(true);
        header('Location: login.php?disabled=1');
        exit;
    }
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
}

function require_admin(): void
{
    require_login();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        header('Location: index.php');
        exit;
    }
}
