<?php

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Dashboard Summary
|--------------------------------------------------------------------------
*/

$today = date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS transactions,
        COALESCE(SUM(total_amount), 0) AS sales
    FROM sales
    WHERE status = 'COMPLETED'
    AND DATE(transaction_date) = ?
");

$stmt->execute([$today]);

$today_sales = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Product Count
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM products
    WHERE status = 'active'
");

$total_products = $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Low Stock
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM products
    WHERE status = 'active'
    AND stock <= reorder_level
");

$low_stock = $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Today's Expenses
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM expenses
    WHERE DATE(expense_date) = ?
");

$stmt->execute([$today]);

$today_expenses = $stmt->fetchColumn();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Reports | Canteen POS</title>

<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f4f6f9;

    color: #212529;
}

.container {

    max-width: 1400px;

    margin: auto;

    padding: 30px 20px;
}

.header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 30px;
}

.header h1 {

    margin: 0;

    font-size: 30px;
}

.header p {

    color: #6c757d;

    margin-top: 6px;
}

.btn {

    display: inline-block;

    padding: 10px 16px;

    border-radius: 7px;

    text-decoration: none;

    color: white;

    background: #6c757d;
}

.summary {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 30px;
}

.summary-card {

    background: white;

    padding: 22px;

    border-radius: 12px;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.06);
}

.summary-title {

    color: #6c757d;

    font-size: 13px;
}

.summary-value {

    font-size: 27px;

    font-weight: bold;

    margin-top: 8px;
}

.reports {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 20px;
}

.report-card {

    background: white;

    border-radius: 12px;

    padding: 25px;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.06);

    transition:
        transform .2s,
        box-shadow .2s;
}

.report-card:hover {

    transform:
        translateY(-4px);

    box-shadow:
        0 7px 20px
        rgba(0,0,0,.10);
}

.icon {

    width: 50px;

    height: 50px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 10px;

    background: #eef2ff;

    font-size: 25px;

    margin-bottom: 15px;
}

.report-card h2 {

    margin: 0 0 8px;

    font-size: 19px;
}

.report-card p {

    color: #6c757d;

    min-height: 45px;

    font-size: 14px;

    line-height: 1.5;
}

.report-link {

    display: inline-block;

    margin-top: 10px;

    padding: 9px 14px;

    background: #0d6efd;

    color: white;

    text-decoration: none;

    border-radius: 6px;

    font-size: 13px;
}

@media(max-width:1000px) {

    .reports {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .summary {

        grid-template-columns:
            repeat(2, 1fr);
    }
}

@media(max-width:600px) {

    .reports,
    .summary {

        grid-template-columns: 1fr;
    }

    .header {

        align-items: flex-start;

        gap: 15px;

        flex-direction: column;
    }
}

</style>

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="header">

        <div>

            <h1>
                📊 Reports
            </h1>

            <p>
                Canteen sales, inventory and financial reports
            </p>

        </div>

        <a
            href="../index.php"
            class="btn"
        >
            ← Back Office
        </a>

    </div>


    <!-- SUMMARY -->

    <div class="summary">

        <div class="summary-card">

            <div class="summary-title">
                Today's Sales
            </div>

            <div class="summary-value">

                ₱<?= number_format(
                    $today_sales['sales'],
                    2
                ) ?>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Transactions Today
            </div>

            <div class="summary-value">

                <?= number_format(
                    $today_sales['transactions']
                ) ?>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Active Products
            </div>

            <div class="summary-value">

                <?= number_format(
                    $total_products
                ) ?>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Low Stock
            </div>

            <div class="summary-value">

                <?= number_format(
                    $low_stock
                ) ?>

            </div>

        </div>

    </div>


    <!-- REPORT CARDS -->

    <div class="reports">


        <!-- SALES -->

        <div class="report-card">

            <div class="icon">
                📊
            </div>

            <h2>
                Sales Report
            </h2>

            <p>
                View sales by date,
                transactions, discounts,
                tax and payment method.
            </p>

            <a
                href="sales.php"
                class="report-link"
            >
                Open Report →
            </a>

        </div>


        <!-- PRODUCTS -->

        <div class="report-card">

            <div class="icon">
                🛒
            </div>

            <h2>
                Product Sales
            </h2>

            <p>
                See best-selling products,
                quantities sold and total
                product sales.
            </p>

            <a
                href="products.php"
                class="report-link"
            >
                Open Report →
            </a>

        </div>


        <!-- PROFIT -->

        <div class="report-card">

            <div class="icon">
                💰
            </div>

            <h2>
                Profit Report
            </h2>

            <p>
                Compare selling price and
                cost price to calculate
                estimated profit.
            </p>

            <a
                href="profit.php"
                class="report-link"
            >
                Open Report →
            </a>

        </div>


        <!-- INVENTORY -->

        <div class="report-card">

            <div class="icon">
                📦
            </div>

            <h2>
                Inventory Report
            </h2>

            <p>
                View current stock,
                inventory value, cost and
                selling price.
            </p>

            <a
                href="inventory.php"
                class="report-link"
            >
                Open Report →
            </a>

        </div>


        <!-- MOVEMENTS -->

        <div class="report-card">

            <div class="icon">
                🔄
            </div>

            <h2>
                Stock Movements
            </h2>

            <p>
                Track stock in, sales,
                adjustments, returns
                and inventory changes.
            </p>

            <a
                href="movements.php"
                class="report-link"
            >
                Open Report →
            </a>

        </div>


        <!-- EXPENSES -->

        <div class="report-card">

            <div class="icon">
                💸
            </div>

            <h2>
                Expenses Report
            </h2>

            <p>
                Review expenses by date,
                category and amount.
            </p>

            <a
                href="expenses.php"
                class="report-link"
            >
                Open Report →
            </a>

        </div>


        <!-- CASHIER -->

        <div class="report-card">

            <div class="icon">
                🧾
            </div>

            <h2>
                Cashier Report
            </h2>

            <p>
                View cashier sessions,
                opening cash, sales,
                expected cash and differences.
            </p>

            <a
                href="cashier.php"
                class="report-link"
            >
                Open Report →
            </a>

        </div>


    </div>

</div>

</body>

</html>