<?php

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Get current user
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, username, full_name, role
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$user_id]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Check current open session
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM cashier_sessions
    WHERE user_id = ?
      AND status = 'open'
    ORDER BY id DESC
    LIMIT 1
");

$stmt->execute([$user_id]);

$current_session = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Get today's completed sales
|--------------------------------------------------------------------------
*/

$today_sales = 0;
$today_transactions = 0;

if ($current_session) {

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS transactions,
            COALESCE(SUM(total_amount), 0) AS sales
        FROM sales
        WHERE cashier_session_id = ?
          AND status = 'COMPLETED'
    ");

    $stmt->execute([$current_session['id']]);

    $sales_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $today_transactions = (int) $sales_data['transactions'];
    $today_sales = (float) $sales_data['sales'];
}

/*
|--------------------------------------------------------------------------
| Flash messages
|--------------------------------------------------------------------------
*/

$message = $_SESSION['session_message'] ?? '';
$message_type = $_SESSION['session_message_type'] ?? '';

unset($_SESSION['session_message']);
unset($_SESSION['session_message_type']);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Cashier Session | Mini Canteen</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f4f6f9;
    font-family: Arial, Helvetica, sans-serif;
    color: #212529;
}

.container {
    max-width: 1100px;
    margin: 35px auto;
    padding: 0 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.page-header h1 {
    margin: 0;
    font-size: 28px;
}

.page-header p {
    margin: 5px 0 0;
    color: #6c757d;
}

.back-btn {
    text-decoration: none;
    background: #6c757d;
    color: white;
    padding: 10px 16px;
    border-radius: 6px;
}

.card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.07);
}

.card h2 {
    margin-top: 0;
}

.alert {
    padding: 14px 16px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.alert.success {
    background: #d1e7dd;
    color: #0f5132;
}

.alert.error {
    background: #f8d7da;
    color: #842029;
}

.status {
    display: inline-block;
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
}

.status.open {
    background: #d1e7dd;
    color: #0f5132;
}

.status.closed {
    background: #e9ecef;
    color: #495057;
}

.session-info {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-top: 20px;
}

.info-box {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 18px;
}

.info-label {
    font-size: 13px;
    color: #6c757d;
    margin-bottom: 6px;
}

.info-value {
    font-size: 20px;
    font-weight: bold;
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 7px;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 15px;
}

.form-group textarea {
    min-height: 90px;
    resize: vertical;
}

.btn {
    border: none;
    padding: 12px 20px;
    border-radius: 6px;
    color: white;
    cursor: pointer;
    font-size: 15px;
    font-weight: bold;
}

.btn-open {
    background: #198754;
}

.btn-close {
    background: #dc3545;
}

.btn-pos {
    background: #0d6efd;
    text-decoration: none;
    display: inline-block;
}

.button-row {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.money {
    font-size: 30px;
    font-weight: bold;
}

@media (max-width: 700px) {

    .session-info {
        grid-template-columns: 1fr;
    }

    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

}

</style>

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="page-header">

        <div>

            <h1>Cashier Session</h1>

            <p>
                Manage your cashier shift and cash drawer.
            </p>

        </div>

        <a href="../index.php" class="back-btn">
            ← Back 
        </a>

    </div>


    <!-- MESSAGE -->

    <?php if ($message): ?>

        <div class="alert <?= htmlspecialchars($message_type) ?>">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <!-- USER -->

    <div class="card">

        <h2>Cashier Information</h2>

        <div class="session-info">

            <div class="info-box">

                <div class="info-label">
                    Cashier
                </div>

                <div class="info-value">
                    <?= htmlspecialchars($user['full_name']) ?>
                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    Username
                </div>

                <div class="info-value">
                    <?= htmlspecialchars($user['username']) ?>
                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    Role
                </div>

                <div class="info-value">
                    <?= htmlspecialchars(ucfirst($user['role'])) ?>
                </div>

            </div>

        </div>

    </div>


    <?php if (!$current_session): ?>

        <!-- OPEN SESSION -->

        <div class="card">

            <h2>Open Cashier Session</h2>

            <p>
                Enter the amount of cash currently inside the
                cash drawer before starting sales.
            </p>

            <form
                action="open_session.php"
                method="POST"
            >

                <div class="form-group">

                    <label>
                        Opening Cash
                    </label>

                    <input
                        type="number"
                        name="opening_cash"
                        min="0"
                        step="0.01"
                        value="0.00"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Notes
                    </label>

                    <textarea
                        name="notes"
                        placeholder="Optional notes..."
                    ></textarea>

                </div>


                <button
                    type="submit"
                    class="btn btn-open"
                >
                    🔓 Open Session
                </button>

            </form>

        </div>

    <?php else: ?>

        <!-- CURRENT SESSION -->

        <div class="card">

            <div style="
                display:flex;
                justify-content:space-between;
                align-items:center;
                gap:15px;
                flex-wrap:wrap;
            ">

                <div>

                    <h2>
                        Current Session
                    </h2>

                    <span class="status open">
                        ● SESSION OPEN
                    </span>

                </div>

                <a
                    href="index.php"
                    class="btn btn-pos"
                >
                    🛒 Go to POS
                </a>

            </div>


            <div class="session-info">

                <div class="info-box">

                    <div class="info-label">
                        Opening Cash
                    </div>

                    <div class="info-value">
                        ₱<?= number_format(
                            $current_session['opening_cash'],
                            2
                        ) ?>
                    </div>

                </div>


                <div class="info-box">

                    <div class="info-label">
                        Session Started
                    </div>

                    <div class="info-value"
                         style="font-size:16px;">

                        <?= date(
                            'M d, Y h:i A',
                            strtotime(
                                $current_session['opened_at']
                            )
                        ) ?>

                    </div>

                </div>


                <div class="info-box">

                    <div class="info-label">
                        Transactions
                    </div>

                    <div class="info-value">

                        <?= number_format(
                            $today_transactions
                        ) ?>

                    </div>

                </div>

            </div>

        </div>


        <!-- SALES -->

        <div class="card">

            <h2>Session Sales</h2>

            <div class="money">

                ₱<?= number_format(
                    $today_sales,
                    2
                ) ?>

            </div>

            <p>
                Total completed sales for this session.
            </p>

        </div>


        <!-- CLOSE SESSION -->

        <div class="card">

            <h2>Close Cashier Session</h2>

            <p>
                Count the actual cash inside the drawer
                before closing the session.
            </p>

            <form
                action="close_session.php"
                method="POST"
            >

                <input
                    type="hidden"
                    name="session_id"
                    value="<?= $current_session['id'] ?>"
                >


                <div class="form-group">

                    <label>
                        Actual Closing Cash
                    </label>

                    <input
                        type="number"
                        name="closing_cash"
                        min="0"
                        step="0.01"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Notes
                    </label>

                    <textarea
                        name="notes"
                        placeholder="Optional closing notes..."
                    ></textarea>

                </div>


                <button
                    type="submit"
                    class="btn btn-close"
                    onclick="return confirm(
                        'Are you sure you want to close this cashier session?'
                    );"
                >
                    🔒 Close Session
                </button>

            </form>

        </div>

    <?php endif; ?>

</div>

</body>

</html>