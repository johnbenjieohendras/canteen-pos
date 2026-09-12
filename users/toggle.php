<?php

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: index.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Prevent current logged-in user from disabling himself
|--------------------------------------------------------------------------
*/

if ($id === (int)$_SESSION['user_id']) {
    die('You cannot deactivate your own account.');
}

$stmt = $pdo->prepare("
    SELECT status
    FROM users
    WHERE id = ?
");

$stmt->execute([$id]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {

    $new_status =
        $user['status'] === 'active'
        ? 'inactive'
        : 'active';

    $stmt = $pdo->prepare("
        UPDATE users
        SET status = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $new_status,
        $id
    ]);
}

header("Location: index.php");
exit;