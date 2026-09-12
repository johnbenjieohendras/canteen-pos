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
| Filters
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$status = $_GET['status'] ?? '';

/*
|--------------------------------------------------------------------------
| Build Query
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];

$where[] = "1=1";

if ($search !== '') {

    $where[] = "
        (
            s.transaction_no LIKE ?
            OR u.full_name LIKE ?
        )
    ";

    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($date_from !== '') {

    $where[] = "DATE(s.transaction_date) >= ?";

    $params[] = $date_from;
}

if ($date_to !== '') {

    $where[] = "DATE(s.transaction_date) <= ?";

    $params[] = $date_to;
}

if ($status !== '') {

    $where[] = "s.status = ?";

    $params[] = $status;
}

$where_sql = implode(" AND ", $where);

/*
|--------------------------------------------------------------------------
| Get Transactions
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        s.id,
        s.transaction_no,
        s.transaction_date,
        s.subtotal,
        s.discount,
        s.tax,
        s.total_amount,
        s.amount_tendered,
        s.change_amount,
        s.payment_method,
        s.status,
        s.void_reason,
        u.full_name AS cashier_name
    FROM sales s
    INNER JOIN users u
        ON u.id = s.cashier_id
    WHERE {$where_sql}
    ORDER BY s.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

$summary_sql = "
    SELECT
        COUNT(*) AS transactions,
        COALESCE(SUM(total_amount), 0) AS total_sales
    FROM sales s
    INNER JOIN users u
        ON u.id = s.cashier_id
    WHERE {$where_sql}
";

$stmt = $pdo->prepare($summary_sql);
$stmt->execute($params);

$summary = $stmt->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Sales Transactions | Mini Canteen</title>

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
    max-width: 1400px;
    margin: 30px auto;
    padding: 0 20px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
}

.header h1 {
    margin: 0;
    font-size: 28px;
}

.header p {
    color: #6c757d;
    margin: 6px 0 0;
}

.btn {
    display: inline-block;
    text-decoration: none;
    border: none;
    padding: 10px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
}

.btn-back {
    background: #6c757d;
    color: white;
}

.btn-search {
    background: #0d6efd;
    color: white;
}

.btn-reset {
    background: #e9ecef;
    color: #212529;
}

.card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}

.filters {
    display: grid;
    grid-template-columns:
        2fr 1fr 1fr 1fr auto auto;
    gap: 10px;
    align-items: end;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 6px;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    background: white;
}

.summary {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.summary-box {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 18px;
}

.summary-label {
    color: #6c757d;
    font-size: 13px;
}

.summary-value {
    font-size: 27px;
    font-weight: bold;
    margin-top: 5px;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #212529;
    color: white;
    text-align: left;
    padding: 12px;
    font-size: 13px;
    white-space: nowrap;
}

td {
    padding: 12px;
    border-bottom: 1px solid #dee2e6;
    font-size: 13px;
    white-space: nowrap;
}

tr:hover td {
    background: #f8f9fa;
}

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

.badge-completed {
    background: #d1e7dd;
    color: #0f5132;
}

.badge-void {
    background: #f8d7da;
    color: #842029;
}

.badge-refunded {
    background: #fff3cd;
    color: #664d03;
}

.payment {
    font-weight: bold;
}

.view-btn {
    background: #0d6efd;
    color: white;
    padding: 7px 11px;
    border-radius: 5px;
    text-decoration: none;
    font-size: 12px;
}

.empty {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

@media (max-width: 900px) {

    .filters {
        grid-template-columns: 1fr 1fr;
    }

    .summary {
        grid-template-columns: 1fr;
    }

    .header {
        flex-direction: column;
        align-items: flex-start;
    }

}

</style>

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="header">

        <div>

            <h1>Sales Transactions</h1>

            <p>
                View and manage all completed sales transactions.
            </p>

        </div>

        <a
            href="../index.php"
            class="btn btn-back"
        >
            ← Back Office
        </a>

    </div>


    <!-- FILTERS -->

    <div class="card">

        <form method="GET">

            <div class="filters">

                <div class="form-group">

                    <label>
                        Search
                    </label>

                    <input
                        type="text"
                        name="search"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Transaction no. or cashier..."
                    >

                </div>


                <div class="form-group">

                    <label>
                        Date From
                    </label>

                    <input
                        type="date"
                        name="date_from"
                        value="<?= htmlspecialchars($date_from) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Date To
                    </label>

                    <input
                        type="date"
                        name="date_to"
                        value="<?= htmlspecialchars($date_to) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Status
                    </label>

                    <select name="status">

                        <option value="">
                            All Status
                        </option>

                        <option
                            value="COMPLETED"
                            <?= $status === 'COMPLETED'
                                ? 'selected'
                                : '' ?>
                        >
                            Completed
                        </option>

                        <option
                            value="VOID"
                            <?= $status === 'VOID'
                                ? 'selected'
                                : '' ?>
                        >
                            Void
                        </option>

                        <option
                            value="REFUNDED"
                            <?= $status === 'REFUNDED'
                                ? 'selected'
                                : '' ?>
                        >
                            Refunded
                        </option>

                    </select>

                </div>


                <button
                    type="submit"
                    class="btn btn-search"
                >
                    🔍 Search
                </button>


                <a
                    href="index.php"
                    class="btn btn-reset"
                >
                    Reset
                </a>

            </div>

        </form>

    </div>


    <!-- SUMMARY -->

    <div class="card">

        <div class="summary">

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
                    Total Sales
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


    <!-- SALES TABLE -->

    <div class="card">

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            Transaction No.
                        </th>

                        <th>
                            Date / Time
                        </th>

                        <th>
                            Cashier
                        </th>

                        <th>
                            Subtotal
                        </th>

                        <th>
                            Discount
                        </th>

                        <th>
                            Total
                        </th>

                        <th>
                            Payment
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (!$sales): ?>

                    <tr>

                        <td
                            colspan="9"
                            class="empty"
                        >
                            No transactions found.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($sales as $sale): ?>

                        <tr>

                            <td>
                                <strong>
                                    <?= htmlspecialchars(
                                        $sale['transaction_no']
                                    ) ?>
                                </strong>
                            </td>


                            <td>

                                <?= date(
                                    'M d, Y h:i A',
                                    strtotime(
                                        $sale['transaction_date']
                                    )
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $sale['cashier_name']
                                ) ?>

                            </td>


                            <td>

                                ₱<?= number_format(
                                    $sale['subtotal'],
                                    2
                                ) ?>

                            </td>


                            <td>

                                ₱<?= number_format(
                                    $sale['discount'],
                                    2
                                ) ?>

                            </td>


                            <td>

                                <strong>

                                    ₱<?= number_format(
                                        $sale['total_amount'],
                                        2
                                    ) ?>

                                </strong>

                            </td>


                            <td class="payment">

                                <?= htmlspecialchars(
                                    $sale['payment_method']
                                ) ?>

                            </td>


                            <td>

                                <?php

                                $badge_class =
                                    'badge-completed';

                                if ($sale['status'] === 'VOID') {
                                    $badge_class =
                                        'badge-void';
                                }

                                if ($sale['status'] === 'REFUNDED') {
                                    $badge_class =
                                        'badge-refunded';
                                }

                                ?>

                                <span
                                    class="badge <?= $badge_class ?>"
                                >

                                    <?= htmlspecialchars(
                                        $sale['status']
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <a
                                    href="view.php?id=<?= $sale['id'] ?>"
                                    class="view-btn"
                                >
                                    View
                                </a>

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