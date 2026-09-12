<?php

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'] ?? '';

/*
|--------------------------------------------------------------------------
| BACK OFFICE ACCESS
|--------------------------------------------------------------------------
*/

if ($role === 'cashier') {
    header("Location: ../pos/index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| GET EXPENSE ID
|--------------------------------------------------------------------------
*/

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id) {
    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| CHECK IF EXPENSE EXISTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        expense_no,
        expense_category,
        description,
        amount
    FROM expenses
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $id
]);

$expense = $stmt->fetch();


if (!$expense) {
    header("Location: index.php?error=not_found");
    exit;
}


/*
|--------------------------------------------------------------------------
| DELETE EXPENSE
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        DELETE FROM expenses
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $id
    ]);


    /*
    |--------------------------------------------------------------------------
    | CHECK DELETE RESULT
    |--------------------------------------------------------------------------
    */

    if ($stmt->rowCount() > 0) {

        header(
            "Location: index.php?success=deleted"
        );

        exit;

    }


    header(
        "Location: index.php?error=delete_failed"
    );

    exit;


} catch (PDOException $e) {

    /*
    |--------------------------------------------------------------------------
    | DELETE ERROR
    |--------------------------------------------------------------------------
    */

    header(
        "Location: index.php?error=delete_failed"
    );

    exit;
}