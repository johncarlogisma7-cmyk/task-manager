<?php

declare(strict_types=1);
require __DIR__ . '/auth.php';
require_admin();

$adminId = (int)$_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $targetId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$targetId || $targetId < 1) {
        $error = 'Choose a valid account.';
    } else {
        $query = $pdo->prepare('SELECT id, name, email, role, is_active FROM users WHERE id = ?');
        $query->execute([$targetId]);
        $target = $query->fetch();
        if (!$target) {
            $error = 'That account was not found.';
        } elseif ($action === 'role') {
            $newRole = (string)($_POST['role'] ?? '');
            if (!in_array($newRole, ['admin', 'user'], true)) {
                $error = 'Choose a valid role.';
            } elseif ($targetId === $adminId && $newRole !== 'admin') {
                $error = 'You cannot remove your own administrator access.';
            } elseif ($target['role'] === 'admin' && (int)$target['is_active'] === 1 && $newRole !== 'admin') {
                $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
                if ($adminCount <= 1) {
                    $error = 'The last active administrator cannot be demoted.';
                }
            }
            if ($error === '') {
                $query = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
                $query->execute([$newRole, $targetId]);
                $message = 'User role updated.';
            }
        } elseif ($action === 'active') {
            $newActive = filter_var($_POST['is_active'] ?? null, FILTER_VALIDATE_INT);
            if (!in_array($newActive, [0, 1], true)) {
                $error = 'Choose a valid account status.';
            } elseif ($targetId === $adminId && $newActive === 0) {
                $error = 'You cannot deactivate your own account.';
            } elseif ($target['role'] === 'admin' && (int)$target['is_active'] === 1 && $newActive === 0) {
                $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
                if ($adminCount <= 1) {
                    $error = 'The last active administrator cannot be deactivated.';
                }
            }
            if ($error === '') {
                $query = $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?');
                $query->execute([$newActive, $targetId]);
                $message = $newActive ? 'Account enabled.' : 'Account disabled.';
            }
        } elseif ($action === 'delete') {
            if ($targetId === $adminId) {
                $error = 'You cannot delete the account you are using.';
            } elseif ($target['role'] === 'admin' && (int)$target['is_active'] === 1) {
                $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
                if ($adminCount <= 1) {
                    $error = 'The last active administrator cannot be deleted.';
                }
            }
            if ($error === '') {
                $query = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $query->execute([$targetId]);
                $message = 'User deleted. Their tasks were deleted too.';
            }
        } else {
            $error = 'Unknown action.';
        }
    }
}

$search = trim((string)($_GET['search'] ?? ''));
if ($search !== '') {
    $query = $pdo->prepare('SELECT id, name, email, role, is_active, created_at FROM users WHERE name LIKE ? OR email LIKE ? ORDER BY created_at DESC');
    $like = '%' . $search . '%';
    $query->execute([$like, $like]);
} else {
    $query = $pdo->query('SELECT id, name, email, role, is_active, created_at FROM users ORDER BY created_at DESC');
}
$users = $query->fetchAll();
$summary = $pdo->query('SELECT COUNT(*) AS total, COALESCE(SUM(is_active = 1), 0) AS active FROM users')->fetch();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User management · My Tasks</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <main class="wrap admin-wrap">
        <header class="header admin-header">
            <div>
                <p class="eyebrow">ADMINISTRATOR</p>
                <h1>User Management</h1>
                <p class="sub">Manage accounts connected to your task manager.</p>
            </div>
            <div class="admin-nav"><a href="index.php">← Back to tasks</a>
                <form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><button type="submit">Log out</button></form>
            </div>
        </header>

        <section class="admin-summary">
            <div><strong><?= (int)$summary['total'] ?></strong><span>accounts</span></div>
            <div><strong><?= (int)$summary['active'] ?></strong><span>active</span></div>
            <div><strong><?= (int)$summary['total'] - (int)$summary['active'] ?></strong><span>disabled</span></div>
        </section>

        <?php if ($message): ?><p class="notice" role="status"><?= h($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="notice admin-error" role="alert"><?= h($error) ?></p><?php endif; ?>

        <section class="panel users-panel">
            <div class="users-heading">
                <div>
                    <h2>Accounts</h2>
                    <p>New accounts are added through registration.</p>
                </div>
                <form method="get" class="user-search"><input type="search" name="search" placeholder="Search name or email" value="<?= h($search) ?>"><button type="submit">Search</button><?php if ($search !== ''): ?><a href="admin.php">Clear</a><?php endif; ?></form>
            </div>
            <?php if (!$users): ?><div class="empty">
                    <h3>No accounts found</h3>
                    <p>Try a different search.</p>
                </div><?php else: ?>
                <div class="user-list">
                    <?php foreach ($users as $user): ?>
                        <article class="user-row">
                            <div class="user-info">
                                <div class="user-avatar"><?= h(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></div>
                                <div>
                                    <h3><?= h($user['name']) ?><?php if ((int)$user['id'] === $adminId): ?> <span class="you-tag">You</span><?php endif; ?></h3>
                                    <p><?= h($user['email']) ?></p><small>Joined <?= date('M j, Y', strtotime($user['created_at'])) ?></small>
                                </div>
                            </div>
                            <div class="user-controls">
                                <span class="role-badge <?= $user['role'] === 'admin' ? 'role-admin' : '' ?>"><?= $user['role'] === 'admin' ? 'Admin' : 'User' ?></span>
                                <span class="status-badge <?= (int)$user['is_active'] ? 'status-active' : 'status-disabled' ?>"><?= (int)$user['is_active'] ? 'Active' : 'Disabled' ?></span>
                                <form method="post" class="inline-control"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>"><input type="hidden" name="action" value="role"><select name="role" aria-label="Role for <?= h($user['name']) ?>">
                                        <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select><button type="submit">Save role</button></form>
                                <form method="post" class="inline-control"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>"><input type="hidden" name="action" value="active"><input type="hidden" name="is_active" value="<?= (int)$user['is_active'] ? 0 : 1 ?>"><button type="submit" class="subtle-button" <?= (int)$user['id'] === $adminId ? 'disabled title="You cannot disable your own account"' : '' ?>><?= (int)$user['is_active'] ? 'Disable' : 'Enable' ?></button></form>
                                <form method="post" class="inline-control" onsubmit="return confirm('Delete this account and all its tasks?')"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>"><input type="hidden" name="action" value="delete"><button type="submit" class="delete" <?= (int)$user['id'] === $adminId ? 'disabled title="You cannot delete your own account"' : '' ?>>Delete</button></form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div><?php endif; ?>
        </section>
        <footer>Administrator tools for My Tasks.</footer>
    </main>
</body>

</html>