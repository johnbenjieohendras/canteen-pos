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
| Date Filters
|--------------------------------------------------------------------------
*/

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to   = $_GET['date_to'] ?? date('Y-m-d');

/*
|--------------------------------------------------------------------------
| Sales Summary
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS transactions,
        COALESCE(SUM(subtotal), 0) AS subtotal,
        COALESCE(SUM(discount), 0) AS discount,
        COALESCE(SUM(tax), 0) AS tax,
        COALESCE(SUM(total_amount), 0) AS total_sales
    FROM sales
    WHERE status = 'COMPLETED'
    AND DATE(transaction_date) BETWEEN ? AND ?
");

$stmt->execute([
    $date_from,
    $date_to
]);

$summary = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Payment Summary
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        payment_method,
        COUNT(*) AS transactions,
        COALESCE(SUM(total_amount), 0) AS total
    FROM sales
    WHERE status = 'COMPLETED'
    AND DATE(transaction_date) BETWEEN ? AND ?
    GROUP BY payment_method
    ORDER BY total DESC
");

$stmt->execute([
    $date_from,
    $date_to
]);

$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Daily Sales
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        DATE(transaction_date) AS sale_date,
        COUNT(*) AS transactions,
        COALESCE(SUM(total_amount), 0) AS total
    FROM sales
    WHERE status = 'COMPLETED'
    AND DATE(transaction_date) BETWEEN ? AND ?
    GROUP BY DATE(transaction_date)
    ORDER BY sale_date DESC
");

$stmt->execute([
    $date_from,
    $date_to
]);

$daily_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Top Selling Products
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        si.product_id,
        si.product_name,
        SUM(si.quantity) AS quantity_sold,
        SUM(si.subtotal) AS sales_amount,
        SUM(
            (si.selling_price - si.cost_price)
            * si.quantity
        ) AS estimated_profit
    FROM sale_items si

    INNER JOIN sales s
        ON s.id = si.sale_id

    WHERE s.status = 'COMPLETED'

    AND DATE(s.transaction_date)
        BETWEEN ? AND ?

    GROUP BY
        si.product_id,
        si.product_name

    ORDER BY quantity_sold DESC

    LIMIT 20
");

$stmt->execute([
    $date_from,
    $date_to
]);

$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Low Stock
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        p.product_code,
        p.product_name,
        c.category_name,
        p.stock,
        p.reorder_level,
        p.unit
    FROM products p

    LEFT JOIN categories c
        ON c.id = p.category_id

    WHERE p.status = 'active'
    AND p.stock <= p.reorder_level

    ORDER BY p.stock ASC
");

$low_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
Sales Report | Mini Canteen
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

    max-width: 1450px;

    margin: 30px auto;

    padding: 0 20px;
}

.header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;

    gap: 15px;
}

.header h1 {

    margin: 0;

    font-size: 28px;
}

.header p {

    margin: 6px 0 0;

    color: #6c757d;
}

.card {

    background: white;

    border-radius: 10px;

    padding: 20px;

    margin-bottom: 20px;

    box-shadow:
        0 2px 10px
        rgba(0,0,0,.06);
}

.filters {

    display: flex;

    gap: 10px;

    align-items: end;

    flex-wrap: wrap;
}

.form-group {

    min-width: 180px;
}

.form-group label {

    display: block;

    font-size: 13px;

    font-weight: bold;

    margin-bottom: 6px;
}

.form-group input {

    width: 100%;

    padding: 10px;

    border:
        1px solid #ced4da;

    border-radius: 6px;
}

.btn {

    display: inline-block;

    padding: 10px 16px;

    border: none;

    border-radius: 6px;

    text-decoration: none;

    cursor: pointer;

    font-size: 14px;
}

.btn-primary {

    background: #0d6efd;

    color: white;
}

.btn-secondary {

    background: #6c757d;

    color: white;
}

.btn-print {

    background: #198754;

    color: white;
}

.summary-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;
}

.summary-box {

    background: #f8f9fa;

    padding: 20px;

    border-radius: 8px;
}

.summary-label {

    font-size: 13px;

    color: #6c757d;
}

.summary-value {

    font-size: 25px;

    font-weight: bold;

    margin-top: 7px;
}

.report-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 20px;
}

table {

    width: 100%;

    border-collapse:
        collapse;
}

th {

    background: #212529;

    color: white;

    padding: 11px;

    text-align: left;

    font-size: 13px;

    white-space: nowrap;
}

td {

    padding: 11px;

    border-bottom:
        1px solid #dee2e6;

    font-size: 13px;
}

.text-right {

    text-align: right;
}

.profit {

    font-weight: bold;
}

.low-stock {

    background: #fff3cd;
}

.badge {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: bold;
}

.badge-low {

    background: #f8d7da;

    color: #842029;
}

@media print {

    body {

        background: white;
    }

    .no-print {

        display: none !important;
    }

    .card {

        box-shadow: none;

        border: 1px solid #ddd;
    }

}

@media(max-width:900px) {

    .summary-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .report-grid {

        grid-template-columns: 1fr;
    }

}

@media(max-width:600px) {

    .summary-grid {

        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="header no-print">

        <div>

            <h1>
                📊 Sales Report
            </h1>

            <p>
                Sales performance and transaction summary
            </p>

        </div>

        <div>

            <a
                href="../index.php"
                class="btn btn-secondary"
            >
                ← Back Office
            </a>

            <button
                onclick="window.print()"
                class="btn btn-print"
            >
                🖨 Print
            </button>

        </div>

    </div>


    <!-- FILTER -->

    <div class="card no-print">

        <form method="GET">

            <div class="filters">

                <div class="form-group">

                    <label>
                        Date From
                    </label>

                    <input
                        type="date"
                        name="date_from"
                        value="<?= htmlspecialchars(
                            $date_from
                        ) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Date To
                    </label>

                    <input
                        type="date"
                        name="date_to"
                        value="<?= htmlspecialchars(
                            $date_to
                        ) ?>"
                    >

                </div>


                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    🔍 Generate Report
                </button>

            </div>

        </form>

    </div>


    <!-- SUMMARY -->

    <div class="card">

        <h2>
            Sales Summary
        </h2>

        <p style="color:#6c757d;">

            <?= date(
                'M d, Y',
                strtotime($date_from)
            ) ?>

            to

            <?= date(
                'M d, Y',
                strtotime($date_to)
            ) ?>

        </p>


        <div class="summary-grid">

            <div class="summary-box">

                <div class="summary-label">
                    Transactions
                </div>

                <div class="summary-value">

                    <?= number_format(
                        $summary['transactions']
                    ) ?>

                </div>

            </div>


            <div class="summary-box">

                <div class="summary-label">
                    Gross Sales
                </div>

                <div class="summary-value">

                    ₱<?= number_format(
                        $summary['subtotal'],
                        2
                    ) ?>

                </div>

            </div>


            <div class="summary-box">

                <div class="summary-label">
                    Discounts
                </div>

                <div class="summary-value">

                    ₱<?= number_format(
                        $summary['discount'],
                        2
                    ) ?>

                </div>

            </div>


            <div class="summary-box">

                <div class="summary-label">
                    Net Sales
                </div>

                <div class="summary-value">

                    ₱<?= number_format(
                        $summary['total_sales'],
                        2
                    ) ?>

                </div>

            </div>

        </div>

    </div>


    <!-- PAYMENT + DAILY -->

    <div class="report-grid">

        <!-- PAYMENT -->

        <div class="card">

            <h2>
                Payment Summary
            </h2>

            <table>

                <thead>

                    <tr>

                        <th>
                            Payment
                        </th>

                        <th>
                            Transactions
                        </th>

                        <th class="text-right">
                            Total
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if (!$payments): ?>

                    <tr>

                        <td colspan="3">
                            No payment data.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach (
                        $payments as $payment
                    ): ?>

                        <tr>

                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $payment[
                                            'payment_method'
                                        ]
                                    ) ?>

                                </strong>

                            </td>

                            <td>

                                <?= number_format(
                                    $payment[
                                        'transactions'
                                    ]
                                ) ?>

                            </td>

                            <td class="text-right">

                                ₱<?= number_format(
                                    $payment['total'],
                                    2
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>


        <!-- DAILY SALES -->

        <div class="card">

            <h2>
                Daily Sales
            </h2>

            <table>

                <thead>

                    <tr>

                        <th>
                            Date
                        </th>

                        <th>
                            Transactions
                        </th>

                        <th class="text-right">
                            Sales
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if (!$daily_sales): ?>

                    <tr>

                        <td colspan="3">
                            No sales data.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach (
                        $daily_sales as $day
                    ): ?>

                        <tr>

                            <td>

                                <?= date(
                                    'M d, Y',
                                    strtotime(
                                        $day['sale_date']
                                    )
                                ) ?>

                            </td>

                            <td>

                                <?= number_format(
                                    $day['transactions']
                                ) ?>

                            </td>

                            <td class="text-right">

                                ₱<?= number_format(
                                    $day['total'],
                                    2
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- TOP PRODUCTS -->

    <div class="card">

        <h2>
            🏆 Top Selling Products
        </h2>

        <div style="overflow-x:auto;">

            <table>

                <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            Product
                        </th>

                        <th>
                            Qty Sold
                        </th>

                        <th class="text-right">
                            Sales
                        </th>

                        <th class="text-right">
                            Estimated Profit
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if (!$top_products): ?>

                    <tr>

                        <td colspan="5">
                            No product sales data.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php
                    $rank = 1;
                    ?>

                    <?php foreach (
                        $top_products as $product
                    ): ?>

                        <tr>

                            <td>

                                <?= $rank++ ?>

                            </td>

                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $product[
                                            'product_name'
                                        ]
                                    ) ?>

                                </strong>

                            </td>

                            <td>

                                <?= number_format(
                                    $product[
                                        'quantity_sold'
                                    ],
                                    3
                                ) ?>

                            </td>

                            <td class="text-right">

                                ₱<?= number_format(
                                    $product[
                                        'sales_amount'
                                    ],
                                    2
                                ) ?>

                            </td>

                            <td
                                class="text-right profit"
                            >

                                ₱<?= number_format(
                                    $product[
                                        'estimated_profit'
                                    ],
                                    2
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- LOW STOCK -->

    <div class="card">

        <h2>
            ⚠ Low Stock Report
        </h2>

        <div style="overflow-x:auto;">

            <table>

                <thead>

                    <tr>

                        <th>
                            Product Code
                        </th>

                        <th>
                            Product
                        </th>

                        <th>
                            Category
                        </th>

                        <th>
                            Current Stock
                        </th>

                        <th>
                            Reorder Level
                        </th>

                        <th>
                            Status
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if (!$low_stock): ?>

                    <tr>

                        <td colspan="6">

                            ✅ No low-stock products.

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach (
                        $low_stock as $product
                    ): ?>

                        <tr class="low-stock">

                            <td>

                                <?= htmlspecialchars(
                                    $product[
                                        'product_code'
                                    ]
                                ) ?>

                            </td>

                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $product[
                                            'product_name'
                                        ]
                                    ) ?>

                                </strong>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $product[
                                        'category_name'
                                    ] ?? 'N/A'
                                ) ?>

                            </td>

                            <td>

                                <?= number_format(
                                    $product['stock'],
                                    3
                                ) ?>

                                <?= htmlspecialchars(
                                    $product['unit']
                                ) ?>

                            </td>

                            <td>

                                <?= number_format(
                                    $product[
                                        'reorder_level'
                                    ],
                                    3
                                ) ?>

                            </td>

                            <td>

                                <span
                                    class="badge badge-low"
                                >
                                    LOW STOCK
                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>

</html>