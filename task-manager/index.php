<?php

declare(strict_types=1);
require __DIR__ . '/auth.php';
require_login();
$userId = (int)$_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if ($action === 'save') {
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        if ($title === '' || mb_strlen($title) > 160) {
            $message = 'Enter a task name (up to 160 characters).';
        } elseif ($id) {
            $query = $pdo->prepare('UPDATE tasks SET title = ?, description = ? WHERE id = ? AND user_id = ?');
            $query->execute([$title, $description, $id, $userId]);
            $message = $query->rowCount() ? 'Task updated.' : 'Task not found.';
        } else {
            $query = $pdo->prepare('INSERT INTO tasks (user_id, title, description) VALUES (?, ?, ?)');
            $query->execute([$userId, $title, $description]);
            $message = 'Task added.';
        }
    } elseif ($action === 'toggle' && $id) {
        $query = $pdo->prepare("UPDATE tasks SET status = IF(status = 'done', 'todo', 'done') WHERE id = ? AND user_id = ?");
        $query->execute([$id, $userId]);
    } elseif ($action === 'delete' && $id) {
        $query = $pdo->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?');
        $query->execute([$id, $userId]);
        $message = 'Task deleted.';
    }
}

$editing = null;
if (isset($_GET['edit']) && filter_var($_GET['edit'], FILTER_VALIDATE_INT)) {
    $query = $pdo->prepare('SELECT * FROM tasks WHERE id = ? AND user_id = ?');
    $query->execute([(int)$_GET['edit'], $userId]);
    $editing = $query->fetch() ?: null;
}
$filter = in_array($_GET['filter'] ?? '', ['todo', 'done'], true) ? (string)$_GET['filter'] : '';
$sql = 'SELECT * FROM tasks WHERE user_id = ?' . ($filter ? ' AND status = ?' : '') . ' ORDER BY status ASC, created_at DESC';
$query = $pdo->prepare($sql);
$query->execute($filter ? [$userId, $filter] : [$userId]);
$tasks = $query->fetchAll();
$query = $pdo->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(status = 'todo'), 0) AS todo, COALESCE(SUM(status = 'done'), 0) AS done FROM tasks WHERE user_id = ?");
$query->execute([$userId]);
$counts = $query->fetch();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Tasks</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <main class="wrap">
        <header class="header">
            <div>
                <p class="eyebrow">YOUR PERSONAL SPACE</p>
                <h1>My Tasks</h1>
                <p class="sub">A simple place to keep track of what needs doing.</p>
            </div>
            <div class="header-right">
                <div class="total"><strong><?= (int)$counts['todo'] ?></strong><span>to do</span></div>
                <div class="account"><span><?= h($_SESSION['user_name'] ?? '') ?></span>
                    <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?><a class="admin-link" href="admin.php">Manage users</a><?php endif; ?>
                    <form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><button type="submit">Log out</button></form>
                </div>
            </div>
        </header>
        <?php if ($message): ?><p class="notice" role="status"><?= h($message) ?></p><?php endif; ?>
        <section class="panel form-panel">
            <h2><?= $editing ? 'Edit task' : 'Add a task' ?></h2>
            <form method="post" class="task-form"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="save">
                <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
                <label class="field">Task name<input name="title" maxlength="160" required placeholder="What needs doing?" value="<?= h($editing['title'] ?? '') ?>"></label>
                <label class="field">Notes <span class="optional">(optional)</span><input name="description" maxlength="1000" placeholder="Add a little detail" value="<?= h($editing['description'] ?? '') ?>"></label>
                <div class="buttons"><button class="primary" type="submit"><?= $editing ? 'Save changes' : 'Add task' ?></button><?php if ($editing): ?><a class="cancel" href="index.php">Cancel</a><?php endif; ?></div>
            </form>
        </section>
        <section class="list-section">
            <div class="list-head">
                <div>
                    <h2>Tasks</h2>
                    <p><?= (int)$counts['total'] ?> total · <?= (int)$counts['done'] ?> completed</p>
                </div>
                <nav class="filters" aria-label="Filter tasks"><a class="<?= !$filter ? 'selected' : '' ?>" href="index.php">All</a><a class="<?= $filter === 'todo' ? 'selected' : '' ?>" href="?filter=todo">To do</a><a class="<?= $filter === 'done' ? 'selected' : '' ?>" href="?filter=done">Done</a></nav>
            </div>
            <div class="tasks">
                <?php if (!$tasks): ?><div class="empty"><span>✓</span>
                        <h3><?= $filter === 'done' ? 'No completed tasks yet' : ($filter === 'todo' ? 'Nothing to do right now' : 'Nothing on your list yet') ?></h3>
                        <p>Add a task above and it will show up here.</p>
                    </div><?php endif; ?>
                <?php foreach ($tasks as $task): ?><article class="task <?= $task['status'] === 'done' ? 'is-done' : '' ?>">
                        <form method="post" class="toggle-form"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$task['id'] ?>"><button class="check" type="submit" aria-label="<?= $task['status'] === 'done' ? 'Mark as not done' : 'Mark as done' ?>"><?= $task['status'] === 'done' ? '✓' : '' ?></button></form>
                        <div class="task-copy">
                            <h3><?= h($task['title']) ?></h3><?php if ($task['description'] !== ''): ?><p><?= h($task['description']) ?></p><?php endif; ?><small>Added <?= date('M j, Y', strtotime($task['created_at'])) ?></small>
                        </div>
                        <div class="task-actions"><a href="?edit=<?= (int)$task['id'] ?>" aria-label="Edit <?= h($task['title']) ?>">Edit</a>
                            <form method="post" onsubmit="return confirm('Delete this task?')"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$task['id'] ?>"><button class="delete" type="submit">Delete</button></form>
                        </div>
                    </article><?php endforeach; ?>
            </div>
        </section>
        <footer>Keep it simple. Take it one task at a time.</footer>
    </main>
</body>

</html>