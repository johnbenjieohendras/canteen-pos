<?php

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$sale_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($sale_id <= 0) {
    die("Invalid transaction.");
}

/*
|--------------------------------------------------------------------------
| Get Sale
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
| Get Sale Items
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        product_name,
        quantity,
        selling_price,
        discount,
        subtotal
    FROM sale_items
    WHERE sale_id = ?
    ORDER BY id ASC
");

$stmt->execute([$sale_id]);

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
Receipt - <?= htmlspecialchars($sale['transaction_no']) ?>
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #eee;
    font-family: Arial, sans-serif;
}

.receipt {
    width: 380px;
    margin: 30px auto;
    background: white;
    padding: 25px;
    box-shadow: 0 2px 15px rgba(0,0,0,.15);
}

.header {
    text-align: center;
}

.header h2 {
    margin: 0;
    font-size: 22px;
}

.header p {
    margin: 4px 0;
    font-size: 13px;
}

.divider {
    border-top: 1px dashed #333;
    margin: 15px 0;
}

.info {
    font-size: 13px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
}

.item {
    margin-bottom: 10px;
}

.item-name {
    font-weight: bold;
    font-size: 14px;
}

.item-details {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
}

.total-row {
    display: flex;
    justify-content: space-between;
    margin: 6px 0;
    font-size: 14px;
}

.grand-total {
    font-size: 20px;
    font-weight: bold;
}

.footer {
    text-align: center;
    margin-top: 20px;
    font-size: 12px;
}

.actions {
    width: 380px;
    margin: 15px auto;
    display: flex;
    gap: 10px;
}

button,
a {
    flex: 1;
    padding: 12px;
    border: none;
    text-align: center;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
    border-radius: 5px;
}

.print-btn {
    background: #198754;
    color: white;
}

.pos-btn {
    background: #0d6efd;
    color: white;
}

@media print {

    body {
        background: white;
    }

    .receipt {
        width: 80mm;
        margin: 0;
        padding: 10px;
        box-shadow: none;
    }

    .actions {
        display: none;
    }

    @page {
        size: 80mm auto;
        margin: 0;
    }

}

</style>

</head>

<body>


<div class="receipt">

    <!-- HEADER -->

    <div class="header">

        <h2>ADCOM MINI CANTEEN</h2>

        <p>
            Canteen Cashiering System
        </p>

        <p>
            Official Sales Receipt
        </p>

    </div>


    <div class="divider"></div>


    <!-- TRANSACTION INFO -->

    <div class="info">

        <div class="info-row">

            <span>
                Transaction:
            </span>

            <strong>
                <?= htmlspecialchars(
                    $sale['transaction_no']
                ) ?>
            </strong>

        </div>


        <div class="info-row">

            <span>
                Date:
            </span>

            <span>
                <?= date(
                    'M d, Y h:i A',
                    strtotime($sale['transaction_date'])
                ) ?>
            </span>

        </div>


        <div class="info-row">

            <span>
                Cashier:
            </span>

            <span>
                <?= htmlspecialchars(
                    $sale['cashier_name']
                ) ?>
            </span>

        </div>


        <div class="info-row">

            <span>
                Payment:
            </span>

            <span>
                <?= htmlspecialchars(
                    $sale['payment_method']
                ) ?>
            </span>

        </div>

    </div>


    <div class="divider"></div>


    <!-- ITEMS -->

    <?php foreach ($items as $item): ?>

        <div class="item">

            <div class="item-name">

                <?= htmlspecialchars(
                    $item['product_name']
                ) ?>

            </div>


            <div class="item-details">

                <span>

                    <?= number_format(
                        $item['quantity'],
                        3
                    ) ?>

                    ×

                    ₱<?= number_format(
                        $item['selling_price'],
                        2
                    ) ?>

                </span>


                <strong>

                    ₱<?= number_format(
                        $item['subtotal'],
                        2
                    ) ?>

                </strong>

            </div>

        </div>

    <?php endforeach; ?>


    <div class="divider"></div>


    <!-- TOTALS -->

    <div class="total-row">

        <span>
            Subtotal
        </span>

        <span>
            ₱<?= number_format(
                $sale['subtotal'],
                2
            ) ?>
        </span>

    </div>


    <?php if ((float)$sale['discount'] > 0): ?>

    <div class="total-row">

        <span>
            Discount
        </span>

        <span>
            -₱<?= number_format(
                $sale['discount'],
                2
            ) ?>
        </span>

    </div>

    <?php endif; ?>


    <?php if ((float)$sale['tax'] > 0): ?>

    <div class="total-row">

        <span>
            Tax
        </span>

        <span>
            ₱<?= number_format(
                $sale['tax'],
                2
            ) ?>
        </span>

    </div>

    <?php endif; ?>


    <div class="divider"></div>


    <div class="total-row grand-total">

        <span>
            TOTAL
        </span>

        <span>
            ₱<?= number_format(
                $sale['total_amount'],
                2
            ) ?>
        </span>

    </div>


    <div class="total-row">

        <span>
            Amount Tendered
        </span>

        <span>
            ₱<?= number_format(
                $sale['amount_tendered'],
                2
            ) ?>
        </span>

    </div>


    <div class="total-row">

        <span>
            Change
        </span>

        <strong>
            ₱<?= number_format(
                $sale['change_amount'],
                2
            ) ?>
        </strong>

    </div>


    <div class="divider"></div>


    <!-- FOOTER -->

    <div class="footer">

        <strong>
            THANK YOU!
        </strong>

        <br>

        Please come again.

        <br><br>

        Transaction:
        <?= htmlspecialchars(
            $sale['transaction_no']
        ) ?>

    </div>

</div>


<!-- ACTION BUTTONS -->

<div class="actions">

    <button
        class="print-btn"
        onclick="window.print()"
    >

        🖨 Print Receipt

    </button>


    <a
        href="index.php"
        class="pos-btn"
    >

        ← Back to POS

    </a>

</div>


</body>

</html>