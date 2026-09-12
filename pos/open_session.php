<?php

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: session.php");
    exit;
}

$opening_cash = isset($_POST['opening_cash'])
    ? (float) $_POST['opening_cash']
    : 0;

$notes = trim($_POST['notes'] ?? '');

if ($opening_cash < 0) {
    $_SESSION['session_message'] = 'Invalid opening cash.';
    $_SESSION['session_message_type'] = 'error';

    header("Location: session.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Prevent multiple open sessions
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM cashier_sessions
    WHERE user_id = ?
      AND status = 'open'
    LIMIT 1
");

$stmt->execute([$user_id]);

if ($stmt->fetch()) {

    $_SESSION['session_message'] =
        'You already have an open cashier session.';

    $_SESSION['session_message_type'] = 'error';

    header("Location: session.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Create session
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    INSERT INTO cashier_sessions
    (
        user_id,
        opening_cash,
        status,
        notes
    )
    VALUES
    (
        ?,
        ?,
        'open',
        ?
    )
");

$stmt->execute([
    $user_id,
    $opening_cash,
    $notes ?: null
]);


$_SESSION['session_message'] =
    'Cashier session opened successfully.';

$_SESSION['session_message_type'] =
    'success';

header("Location: session.php");

exit;