<?php

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {

    header("Location: ../login.php");
    exit;

}

$role = $_SESSION['role'] ?? '';

/*
|--------------------------------------------------------------------------
| ADMIN ONLY
|--------------------------------------------------------------------------
*/

if ($role !== 'admin') {

    header("Location: index.php");
    exit;

}

try {

    $pdo->exec("
        DELETE FROM audit_logs
    ");

    /*
    |--------------------------------------------------------------------------
    | Optional:
    | Log the clearing action after deleting everything
    |--------------------------------------------------------------------------
    */

    require_once
        __DIR__ . '/../includes/activity_logger.php';

    log_activity(
        $pdo,
        'CLEARED',
        'ACTIVITY LOG',
        'Cleared all activity logs'
    );

} catch (Throwable $e) {

    error_log(
        $e->getMessage()
    );

}

header(
    "Location: index.php"
);

exit;