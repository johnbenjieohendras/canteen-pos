<?php

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| ADMIN ONLY
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Only administrators can void transactions.");
}


/*
|--------------------------------------------------------------------------
| Validate Transaction ID
|--------------------------------------------------------------------------
*/

$sale_id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($sale_id <= 0) {
    die("Invalid transaction.");
}


/*
|--------------------------------------------------------------------------
| Get Transaction
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        s.*,
        u.full_name AS cashier_name
    FROM sales s
    INNER JOIN users u
        ON u.id = s.cashier_id
    WHERE s.id = ?
    LIMIT 1
");

$stmt->execute([$sale_id]);

$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    die("Transaction not found.");
}


/*
|--------------------------------------------------------------------------
| Already Voided / Refunded
|--------------------------------------------------------------------------
*/

if ($sale['status'] !== 'COMPLETED') {
    die(
        "This transaction cannot be voided because its current status is: "
        . htmlspecialchars($sale['status'])
    );
}


/*
|--------------------------------------------------------------------------
| Get Sale Items
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        si.*,
        p.product_name AS current_product_name,
        p.stock
    FROM sale_items si
    INNER JOIN products p
        ON p.id = si.product_id
    WHERE si.sale_id = ?
    ORDER BY si.id ASC
");

$stmt->execute([$sale_id]);

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$items) {
    die("Transaction has no items.");
}


/*
|--------------------------------------------------------------------------
| Process Void
|--------------------------------------------------------------------------
*/

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $reason = trim($_POST['reason'] ?? '');

    if ($reason === '') {

        $error = "Void reason is required.";

    } elseif (strlen($reason) < 3) {

        $error = "Please provide a proper void reason.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | START DATABASE TRANSACTION
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Lock Sale
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT *
                FROM sales
                WHERE id = ?
                FOR UPDATE
            ");

            $stmt->execute([$sale_id]);

            $locked_sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$locked_sale) {
                throw new Exception("Transaction not found.");
            }


            /*
            |--------------------------------------------------------------------------
            | Check Again
            |--------------------------------------------------------------------------
            */

            if ($locked_sale['status'] !== 'COMPLETED') {

                throw new Exception(
                    "Transaction has already been processed."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Process Each Item
            |--------------------------------------------------------------------------
            */

            foreach ($items as $item) {

                /*
                |--------------------------------------------------------------------------
                | Lock Product
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT
                        id,
                        product_name,
                        stock
                    FROM products
                    WHERE id = ?
                    FOR UPDATE
                ");

                $stmt->execute([
                    $item['product_id']
                ]);

                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {

                    throw new Exception(
                        "Product not found: "
                        . $item['product_name']
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | Current Stock
                |--------------------------------------------------------------------------
                */

                $stock_before = (float) $product['stock'];

                $quantity = (float) $item['quantity'];


                /*
                |--------------------------------------------------------------------------
                | Return Stock
                |--------------------------------------------------------------------------
                */

                $stock_after =
                    $stock_before + $quantity;


                /*
                |--------------------------------------------------------------------------
                | Update Product Stock
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    UPDATE products
                    SET stock = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $stock_after,
                    $item['product_id']
                ]);


                /*
                |--------------------------------------------------------------------------
                | Inventory Movement
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    INSERT INTO inventory_movements
                    (
                        product_id,
                        user_id,
                        movement_type,
                        reference_type,
                        reference_id,
                        quantity,
                        stock_before,
                        stock_after,
                        remarks
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        'VOID',
                        'SALE',
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");

                $stmt->execute([
                    $item['product_id'],
                    $_SESSION['user_id'],
                    $sale_id,
                    $quantity,
                    $stock_before,
                    $stock_after,
                    'Stock returned due to void transaction '
                    . $locked_sale['transaction_no']
                ]);

            }


            /*
            |--------------------------------------------------------------------------
            | Update Sale Status
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE sales
                SET
                    status = 'VOID',
                    void_reason = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $reason,
                $sale_id
            ]);


            /*
            |--------------------------------------------------------------------------
            | Audit Log
            |--------------------------------------------------------------------------
            */

            $description =
                'Voided transaction '
                . $locked_sale['transaction_no']
                . '. Reason: '
                . $reason;


            $stmt = $pdo->prepare("
                INSERT INTO audit_logs
                (
                    user_id,
                    action,
                    module,
                    reference_id,
                    description,
                    ip_address
                )
                VALUES
                (
                    ?,
                    'VOID_TRANSACTION',
                    'SALES',
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $_SESSION['user_id'],
                $sale_id,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | Redirect
            |--------------------------------------------------------------------------
            */

            header(
                "Location: view.php?id="
                . $sale_id
                . "&void=success"
            );

            exit;


        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | ROLLBACK
            |--------------------------------------------------------------------------
            */

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
Void Transaction
</title>

<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    background: #f4f6f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: #212529;
}

.container {

    max-width: 700px;

    margin: 50px auto;

    padding: 20px;
}

.card {

    background: white;

    border-radius: 12px;

    padding: 25px;

    box-shadow:
        0 3px 15px
        rgba(0,0,0,.08);
}

.header {

    margin-bottom: 25px;
}

.header h1 {

    margin: 0 0 8px;

    font-size: 26px;
}

.header p {

    margin: 0;

    color: #6c757d;
}

.warning {

    background: #fff3cd;

    color: #664d03;

    border: 1px solid #ffecb5;

    padding: 15px;

    border-radius: 8px;

    margin-bottom: 20px;

    line-height: 1.5;
}

.error {

    background: #f8d7da;

    color: #842029;

    border: 1px solid #f5c2c7;

    padding: 12px;

    border-radius: 7px;

    margin-bottom: 20px;
}

.info {

    background: #f8f9fa;

    padding: 15px;

    border-radius: 8px;

    margin-bottom: 20px;
}

.info-row {

    display: flex;

    justify-content: space-between;

    padding: 7px 0;

    border-bottom:
        1px solid #dee2e6;
}

.info-row:last-child {

    border-bottom: none;
}

.label {

    color: #6c757d;
}

.value {

    font-weight: bold;
}

textarea {

    width: 100%;

    min-height: 120px;

    padding: 12px;

    border:
        1px solid #ced4da;

    border-radius: 7px;

    resize: vertical;

    font-family: inherit;

    font-size: 14px;
}

textarea:focus {

    outline: none;

    border-color: #dc3545;

    box-shadow:
        0 0 0 2px
        rgba(220,53,69,.1);
}

.actions {

    display: flex;

    gap: 10px;

    margin-top: 20px;
}

.btn {

    display: inline-block;

    padding: 11px 18px;

    border: none;

    border-radius: 7px;

    text-decoration: none;

    cursor: pointer;

    font-size: 14px;
}

.btn-danger {

    background: #dc3545;

    color: white;
}

.btn-secondary {

    background: #6c757d;

    color: white;
}

.btn-danger:hover {

    background: #bb2d3b;
}

.btn-secondary:hover {

    background: #5c636a;
}

.item-table {

    width: 100%;

    border-collapse:
        collapse;

    margin-top: 20px;
}

.item-table th {

    background: #212529;

    color: white;

    padding: 10px;

    text-align: left;

    font-size: 13px;
}

.item-table td {

    padding: 10px;

    border-bottom:
        1px solid #dee2e6;

    font-size: 13px;
}

@media(max-width:600px) {

    .container {

        margin: 20px auto;
    }

    .actions {

        flex-direction: column;
    }

    .btn {

        text-align: center;
    }

}

</style>

</head>

<body>

<div class="container">

<div class="card">

    <div class="header">

        <h1>
            ⚠ Void Transaction
        </h1>

        <p>
            This action will cancel the sale
            and return the sold items to inventory.
        </p>

    </div>


    <?php if ($error): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <div class="warning">

        <strong>
            Warning:
        </strong>

        This action will:

        <ul>

            <li>
                Mark the transaction as
                <strong>VOID</strong>
            </li>

            <li>
                Return all sold quantities
                to inventory
            </li>

            <li>
                Create inventory movement records
            </li>

            <li>
                Create an audit log
            </li>

        </ul>

        This action cannot be undone
        automatically.

    </div>


    <!-- TRANSACTION INFO -->

    <div class="info">

        <div class="info-row">

            <span class="label">
                Transaction No.
            </span>

            <span class="value">

                <?= htmlspecialchars(
                    $sale['transaction_no']
                ) ?>

            </span>

        </div>


        <div class="info-row">

            <span class="label">
                Date
            </span>

            <span class="value">

                <?= date(
                    'M d, Y h:i A',
                    strtotime(
                        $sale['transaction_date']
                    )
                ) ?>

            </span>

        </div>


        <div class="info-row">

            <span class="label">
                Cashier
            </span>

            <span class="value">

                <?= htmlspecialchars(
                    $sale['cashier_name']
                ) ?>

            </span>

        </div>


        <div class="info-row">

            <span class="label">
                Total
            </span>

            <span class="value">

                ₱<?= number_format(
                    $sale['total_amount'],
                    2
                ) ?>

            </span>

        </div>

    </div>


    <!-- ITEMS -->

    <h3>
        Items to be Returned
    </h3>

    <table class="item-table">

        <thead>

            <tr>

                <th>
                    Product
                </th>

                <th>
                    Quantity
                </th>

                <th>
                    Current Stock
                </th>

            </tr>

        </thead>

        <tbody>

        <?php foreach ($items as $item): ?>

            <tr>

                <td>

                    <?= htmlspecialchars(
                        $item['product_name']
                    ) ?>

                </td>

                <td>

                    <?= number_format(
                        $item['quantity'],
                        3
                    ) ?>

                    <?= htmlspecialchars(
                        $item['unit'] ?? ''
                    ) ?>

                </td>

                <td>

                    <?= number_format(
                        $item['stock'],
                        3
                    ) ?>

                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>


    <!-- FORM -->

    <form
        method="POST"
        onsubmit="
            return confirm(
                'Are you sure you want to VOID this transaction?'
            );
        "
    >

        <div style="margin-top:25px;">

            <label
                for="reason"
                style="
                    display:block;
                    font-weight:bold;
                    margin-bottom:7px;
                "
            >
                Void Reason *
            </label>

            <textarea
                id="reason"
                name="reason"
                required
                minlength="3"
                placeholder="Enter reason for voiding this transaction..."
            ></textarea>

        </div>


        <div class="actions">

            <button
                type="submit"
                class="btn btn-danger"
            >
                ⚠ Confirm Void
            </button>

            <a
                href="view.php?id=<?= $sale_id ?>"
                class="btn btn-secondary"
            >
                Cancel
            </a>

        </div>

    </form>

</div>

</div>

</body>

</html>