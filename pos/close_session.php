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

$session_id = isset($_POST['session_id'])
    ? (int) $_POST['session_id']
    : 0;

$closing_cash = isset($_POST['closing_cash'])
    ? (float) $_POST['closing_cash']
    : 0;

$notes = trim($_POST['notes'] ?? '');

if ($session_id <= 0) {

    $_SESSION['session_message'] =
        'Invalid session.';

    $_SESSION['session_message_type'] =
        'error';

    header("Location: session.php");
    exit;
}

if ($closing_cash < 0) {

    $_SESSION['session_message'] =
        'Invalid closing cash.';

    $_SESSION['session_message_type'] =
        'error';

    header("Location: session.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Get session
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM cashier_sessions
    WHERE id = ?
      AND user_id = ?
      AND status = 'open'
    LIMIT 1
");

$stmt->execute([
    $session_id,
    $user_id
]);

$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {

    $_SESSION['session_message'] =
        'Open cashier session not found.';

    $_SESSION['session_message_type'] =
        'error';

    header("Location: session.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Calculate expected cash
|--------------------------------------------------------------------------
|
| Opening Cash
| + Cash Sales
| = Expected Cash
|
*/

$stmt = $pdo->prepare("
    SELECT
        COALESCE(
            SUM(
                CASE
                    WHEN payment_method = 'CASH'
                    THEN total_amount
                    ELSE 0
                END
            ),
            0
        ) AS cash_sales
    FROM sales
    WHERE cashier_session_id = ?
      AND status = 'COMPLETED'
");

$stmt->execute([$session_id]);

$sales = $stmt->fetch(PDO::FETCH_ASSOC);

$cash_sales = (float) $sales['cash_sales'];

$expected_cash =
    (float) $session['opening_cash']
    + $cash_sales;


/*
|--------------------------------------------------------------------------
| Difference
|--------------------------------------------------------------------------
*/

$cash_difference =
    $closing_cash - $expected_cash;


/*
|--------------------------------------------------------------------------
| Close session
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    UPDATE cashier_sessions
    SET
        closing_cash = ?,
        expected_cash = ?,
        cash_difference = ?,
        closed_at = NOW(),
        status = 'closed',
        notes = ?
    WHERE id = ?
      AND user_id = ?
      AND status = 'open'
");

$stmt->execute([
    $closing_cash,
    $expected_cash,
    $cash_difference,
    $notes ?: $session['notes'],
    $session_id,
    $user_id
]);


$_SESSION['session_message'] =
    'Cashier session closed successfully.';

$_SESSION['session_message_type'] =
    'success';

header("Location: session.php");

exit;