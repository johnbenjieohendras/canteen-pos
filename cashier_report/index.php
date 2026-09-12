<?php

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header.php';

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
| FILTERS
|--------------------------------------------------------------------------
*/

$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to   = $_GET['date_to'] ?? date('Y-m-d');
$cashier   = $_GET['cashier'] ?? '';
$session   = $_GET['session'] ?? '';


/*
|--------------------------------------------------------------------------
| CASHIERS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        full_name
    FROM users
    WHERE role = 'cashier'
    AND status = 'active'
    ORDER BY full_name
");

$cashiers = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| CASHIER SESSIONS FOR FILTER
|--------------------------------------------------------------------------
*/

$session_sql = "
    SELECT
        cs.id,
        cs.opened_at,
        cs.closed_at,
        cs.status,
        u.full_name

    FROM cashier_sessions cs

    INNER JOIN users u
        ON u.id = cs.user_id

    WHERE DATE(cs.opened_at)
        BETWEEN :date_from AND :date_to
";

$session_params = [
    ':date_from' => $date_from,
    ':date_to'   => $date_to
];

if ($cashier !== '') {

    $session_sql .= "
        AND cs.user_id = :cashier
    ";

    $session_params[':cashier'] = $cashier;
}

if ($session !== '') {

    $session_sql .= "
        AND cs.id = :session_id
    ";

    $session_params[':session_id'] = $session;
}

$session_sql .= "
    ORDER BY cs.opened_at DESC
";

$stmt = $pdo->prepare($session_sql);
$stmt->execute($session_params);

$sessions = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| INITIAL VALUES
|--------------------------------------------------------------------------
*/

$total_transactions = 0;
$total_sales        = 0;
$cash_sales         = 0;
$gcash_sales        = 0;
$card_sales         = 0;
$other_sales        = 0;

$total_expenses     = 0;

$total_opening      = 0;
$total_expected     = 0;
$total_closing      = 0;
$total_difference   = 0;


/*
|--------------------------------------------------------------------------
| SALES SUMMARY
|--------------------------------------------------------------------------
*/

$sales_sql = "
    SELECT

        COUNT(*) AS total_transactions,

        COALESCE(
            SUM(total_amount),
            0
        ) AS total_sales,

        COALESCE(
            SUM(
                CASE
                    WHEN payment_method = 'CASH'
                    THEN total_amount
                    ELSE 0
                END
            ),
            0
        ) AS cash_sales,

        COALESCE(
            SUM(
                CASE
                    WHEN payment_method = 'GCASH'
                    THEN total_amount
                    ELSE 0
                END
            ),
            0
        ) AS gcash_sales,

        COALESCE(
            SUM(
                CASE
                    WHEN payment_method = 'CARD'
                    THEN total_amount
                    ELSE 0
                END
            ),
            0
        ) AS card_sales,

        COALESCE(
            SUM(
                CASE
                    WHEN payment_method = 'OTHER'
                    THEN total_amount
                    ELSE 0
                END
            ),
            0
        ) AS other_sales

    FROM sales

    WHERE status = 'COMPLETED'

    AND DATE(transaction_date)
        BETWEEN :date_from AND :date_to
";

$sales_params = [
    ':date_from' => $date_from,
    ':date_to'   => $date_to
];

if ($cashier !== '') {

    $sales_sql .= "
        AND cashier_id = :cashier
    ";

    $sales_params[':cashier'] = $cashier;
}

if ($session !== '') {

    $sales_sql .= "
        AND cashier_session_id = :session_id
    ";

    $sales_params[':session_id'] = $session;
}

$stmt = $pdo->prepare($sales_sql);
$stmt->execute($sales_params);

$sales_summary = $stmt->fetch();


$total_transactions =
    (int) ($sales_summary['total_transactions'] ?? 0);

$total_sales =
    (float) ($sales_summary['total_sales'] ?? 0);

$cash_sales =
    (float) ($sales_summary['cash_sales'] ?? 0);

$gcash_sales =
    (float) ($sales_summary['gcash_sales'] ?? 0);

$card_sales =
    (float) ($sales_summary['card_sales'] ?? 0);

$other_sales =
    (float) ($sales_summary['other_sales'] ?? 0);


/*
|--------------------------------------------------------------------------
| EXPENSE SUMMARY
|--------------------------------------------------------------------------
*/

$expense_sql = "
    SELECT

        COALESCE(
            SUM(amount),
            0
        ) AS total_expenses

    FROM expenses

    WHERE DATE(expense_date)
        BETWEEN :date_from AND :date_to
";

$expense_params = [
    ':date_from' => $date_from,
    ':date_to'   => $date_to
];

if ($cashier !== '') {

    $expense_sql .= "
        AND user_id = :cashier
    ";

    $expense_params[':cashier'] = $cashier;
}

$stmt = $pdo->prepare($expense_sql);
$stmt->execute($expense_params);

$expense_summary = $stmt->fetch();

$total_expenses =
    (float) ($expense_summary['total_expenses'] ?? 0);


/*
|--------------------------------------------------------------------------
| CASHIER SESSION SUMMARY
|--------------------------------------------------------------------------
*/

$session_cash_sql = "
    SELECT

        COALESCE(
            SUM(opening_cash),
            0
        ) AS total_opening,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'closed'
                    THEN expected_cash
                    ELSE 0
                END
            ),
            0
        ) AS total_expected,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'closed'
                    THEN closing_cash
                    ELSE 0
                END
            ),
            0
        ) AS total_closing,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'closed'
                    THEN cash_difference
                    ELSE 0
                END
            ),
            0
        ) AS total_difference

    FROM cashier_sessions

    WHERE DATE(opened_at)
        BETWEEN :date_from AND :date_to
";

$session_cash_params = [
    ':date_from' => $date_from,
    ':date_to'   => $date_to
];

if ($cashier !== '') {

    $session_cash_sql .= "
        AND user_id = :cashier
    ";

    $session_cash_params[':cashier'] = $cashier;
}

if ($session !== '') {

    $session_cash_sql .= "
        AND id = :session_id
    ";

    $session_cash_params[':session_id'] = $session;
}

$stmt = $pdo->prepare($session_cash_sql);
$stmt->execute($session_cash_params);

$cash_summary = $stmt->fetch();


$total_opening =
    (float) ($cash_summary['total_opening'] ?? 0);

$total_expected =
    (float) ($cash_summary['total_expected'] ?? 0);

$total_closing =
    (float) ($cash_summary['total_closing'] ?? 0);

$total_difference =
    (float) ($cash_summary['total_difference'] ?? 0);


/*
|--------------------------------------------------------------------------
| NET SALES
|--------------------------------------------------------------------------
*/

$net_sales =
    $total_sales - $total_expenses;


/*
|--------------------------------------------------------------------------
| SESSION REPORT
|--------------------------------------------------------------------------
*/

$report_sql = "
    SELECT

        cs.id,

        cs.opening_cash,

        cs.closing_cash,

        cs.expected_cash,

        cs.cash_difference,

        cs.opened_at,

        cs.closed_at,

        cs.status,

        u.full_name,

        (
            SELECT
                COUNT(*)
            FROM sales s

            WHERE s.cashier_session_id = cs.id

            AND s.status = 'COMPLETED'

        ) AS transactions,

        (
            SELECT
                COALESCE(
                    SUM(s.total_amount),
                    0
                )

            FROM sales s

            WHERE s.cashier_session_id = cs.id

            AND s.status = 'COMPLETED'

        ) AS sales_total,

        (
            SELECT
                COALESCE(
                    SUM(e.amount),
                    0
                )

            FROM expenses e

            WHERE e.user_id = cs.user_id

            AND e.expense_date >= cs.opened_at

            AND e.expense_date <= COALESCE(
                cs.closed_at,
                NOW()
            )

        ) AS expenses_total

    FROM cashier_sessions cs

    INNER JOIN users u
        ON u.id = cs.user_id

    WHERE DATE(cs.opened_at)
        BETWEEN :date_from AND :date_to
";

$report_params = [
    ':date_from' => $date_from,
    ':date_to'   => $date_to
];

if ($cashier !== '') {

    $report_sql .= "
        AND cs.user_id = :cashier
    ";

    $report_params[':cashier'] = $cashier;
}

if ($session !== '') {

    $report_sql .= "
        AND cs.id = :session_id
    ";

    $report_params[':session_id'] = $session;
}

$report_sql .= "
    ORDER BY cs.opened_at DESC
";

$stmt = $pdo->prepare($report_sql);
$stmt->execute($report_params);

$reports = $stmt->fetchAll();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Cashier Report | Canteen POS
</title>


<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f5f6f8;

    color: #1f2937;

}

.page {

    padding: 30px;

}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.page-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;

}

.page-header h1 {

    font-size: 28px;

}

.page-header p {

    color: #6b7280;

    margin-top: 5px;

}

.header-actions {

    display: flex;

    gap: 10px;

}


/*
|--------------------------------------------------------------------------
| BUTTONS
|--------------------------------------------------------------------------
*/

.btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding: 10px 16px;

    border-radius: 8px;

    text-decoration: none;

    border: none;

    cursor: pointer;

    font-size: 14px;

    font-weight: 600;

}

.btn-back {

    background: white;

    color: #374151;

    border: 1px solid #d1d5db;

}

.btn-back:hover {

    background: #f3f4f6;

}

.btn-primary {

    background: #2563eb;

    color: white;

}

.btn-primary:hover {

    background: #1d4ed8;

}


/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

.filter-card {

    background: white;

    border-radius: 12px;

    padding: 20px;

    margin-bottom: 20px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,0.05);

}

.filter-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

}

.form-group {

    display: flex;

    flex-direction: column;

}

.form-group label {

    font-size: 13px;

    font-weight: 600;

    margin-bottom: 7px;

}

.form-group input,
.form-group select {

    padding: 10px 12px;

    border: 1px solid #d1d5db;

    border-radius: 7px;

    font-size: 14px;

    background: white;

}

.filter-actions {

    display: flex;

    align-items: center;

    gap: 8px;

    margin-top: 15px;

}


/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

.summary-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-bottom: 20px;

}

.summary-card {

    background: white;

    border-radius: 12px;

    padding: 20px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,0.05);

}

.summary-label {

    color: #6b7280;

    font-size: 13px;

    margin-bottom: 8px;

}

.summary-value {

    font-size: 24px;

    font-weight: 700;

}

.summary-value.sales {

    color: #16a34a;

}

.summary-value.expenses {

    color: #dc2626;

}

.summary-value.difference {

    color: #2563eb;

}


/*
|--------------------------------------------------------------------------
| PAYMENT SUMMARY
|--------------------------------------------------------------------------
*/

.sales-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-bottom: 20px;

}

.sales-card {

    background: white;

    border-radius: 12px;

    padding: 18px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,0.05);

}

.sales-card-title {

    font-size: 13px;

    color: #6b7280;

    margin-bottom: 7px;

}

.sales-card-value {

    font-size: 20px;

    font-weight: 700;

}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.card {

    background: white;

    border-radius: 12px;

    padding: 25px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,0.05);

}

.card-title {

    font-size: 18px;

    font-weight: 700;

    margin-bottom: 18px;

}

.table-wrapper {

    overflow-x: auto;

}

table {

    width: 100%;

    border-collapse: collapse;

}

thead {

    background: #f9fafb;

}

th {

    text-align: left;

    padding: 13px;

    font-size: 12px;

    color: #6b7280;

    border-bottom:
        1px solid #e5e7eb;

    white-space: nowrap;

}

td {

    padding: 14px 13px;

    font-size: 13px;

    border-bottom:
        1px solid #f1f1f1;

    white-space: nowrap;

}

tr:hover {

    background: #fafafa;

}

.amount {

    font-weight: 700;

}

.positive {

    color: #16a34a;

}

.negative {

    color: #dc2626;

}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

.status {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 700;

}

.status-open {

    background: #fef3c7;

    color: #92400e;

}

.status-closed {

    background: #dcfce7;

    color: #166534;

}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.empty {

    text-align: center;

    padding: 50px;

    color: #9ca3af;

}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 1100px) {

    .summary-grid,
    .sales-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }

    .filter-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media (max-width: 700px) {

    .page {

        padding: 15px;

    }

    .page-header {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;

    }

    .header-actions {

        width: 100%;

    }

    .header-actions .btn {

        flex: 1;

    }

    .filter-grid,
    .summary-grid,
    .sales-grid {

        grid-template-columns: 1fr;

    }

}

</style>

</head>


<body>


<div class="page">


<!-- ========================================================
     HEADER
======================================================== -->

<div class="page-header">

    <div>

        <h1>
            Cashier Report
        </h1>

        <p>
            Monitor cashier sales, sessions and cash performance.
        </p>

    </div>


    <div class="header-actions">

        <a
            href="../index.php"
            class="btn btn-back"
        >
            ← Back
        </a>

    </div>

</div>


<!-- ========================================================
     FILTERS
======================================================== -->

<div class="filter-card">

<form method="GET">


<div class="filter-grid">


    <!-- DATE FROM -->

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


    <!-- DATE TO -->

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


    <!-- CASHIER -->

    <div class="form-group">

        <label>
            Cashier
        </label>

        <select name="cashier">

            <option value="">
                All Cashiers
            </option>

            <?php foreach (
                $cashiers
                as $cashier_row
            ): ?>

                <option
                    value="<?= $cashier_row['id'] ?>"
                    <?= (
                        $cashier ==
                        $cashier_row['id']
                    )
                        ? 'selected'
                        : ''
                    ?>
                >

                    <?= htmlspecialchars(
                        $cashier_row['full_name']
                    ) ?>

                </option>

            <?php endforeach; ?>

        </select>

    </div>


    <!-- SESSION -->

    <div class="form-group">

        <label>
            Session
        </label>

        <select name="session">

            <option value="">
                All Sessions
            </option>

            <?php foreach (
                $sessions
                as $session_row
            ): ?>

                <option
                    value="<?= $session_row['id'] ?>"
                    <?= (
                        $session ==
                        $session_row['id']
                    )
                        ? 'selected'
                        : ''
                    ?>
                >

                    #<?= $session_row['id'] ?>

                    -

                    <?= htmlspecialchars(
                        $session_row['full_name']
                    ) ?>

                    -

                    <?= date(
                        'M d, Y h:i A',
                        strtotime(
                            $session_row['opened_at']
                        )
                    ) ?>

                </option>

            <?php endforeach; ?>

        </select>

    </div>


</div>


<div class="filter-actions">

    <button
        type="submit"
        class="btn btn-primary"
    >
        Apply Filter
    </button>


    <a
        href="index.php"
        class="btn btn-back"
    >
        Reset
    </a>

</div>


</form>

</div>


<!-- ========================================================
     MAIN SUMMARY
======================================================== -->

<div class="summary-grid">


    <div class="summary-card">

        <div class="summary-label">
            Total Transactions
        </div>

        <div class="summary-value">

            <?= number_format(
                $total_transactions
            ) ?>

        </div>

    </div>


    <div class="summary-card">

        <div class="summary-label">
            Total Sales
        </div>

        <div class="summary-value sales">

            ₱<?= number_format(
                $total_sales,
                2
            ) ?>

        </div>

    </div>


    <div class="summary-card">

        <div class="summary-label">
            Total Expenses
        </div>

        <div class="summary-value expenses">

            ₱<?= number_format(
                $total_expenses,
                2
            ) ?>

        </div>

    </div>


    <div class="summary-card">

        <div class="summary-label">
            Net Sales
        </div>

        <div class="summary-value">

            ₱<?= number_format(
                $net_sales,
                2
            ) ?>

        </div>

    </div>


</div>


<!-- ========================================================
     PAYMENT SUMMARY
======================================================== -->

<div class="sales-grid">


    <div class="sales-card">

        <div class="sales-card-title">
            Cash Sales
        </div>

        <div class="sales-card-value">

            ₱<?= number_format(
                $cash_sales,
                2
            ) ?>

        </div>

    </div>


    <div class="sales-card">

        <div class="sales-card-title">
            GCash Sales
        </div>

        <div class="sales-card-value">

            ₱<?= number_format(
                $gcash_sales,
                2
            ) ?>

        </div>

    </div>


    <div class="sales-card">

        <div class="sales-card-title">
            Card Sales
        </div>

        <div class="sales-card-value">

            ₱<?= number_format(
                $card_sales,
                2
            ) ?>

        </div>

    </div>


    <div class="sales-card">

        <div class="sales-card-title">
            Other Sales
        </div>

        <div class="sales-card-value">

            ₱<?= number_format(
                $other_sales,
                2
            ) ?>

        </div>

    </div>


</div>


<!-- ========================================================
     CASH SUMMARY
======================================================== -->

<div class="summary-grid">


    <div class="summary-card">

        <div class="summary-label">
            Opening Cash
        </div>

        <div class="summary-value">

            ₱<?= number_format(
                $total_opening,
                2
            ) ?>

        </div>

    </div>


    <div class="summary-card">

        <div class="summary-label">
            Expected Cash
        </div>

        <div class="summary-value">

            ₱<?= number_format(
                $total_expected,
                2
            ) ?>

        </div>

    </div>


    <div class="summary-card">

        <div class="summary-label">
            Actual Closing Cash
        </div>

        <div class="summary-value">

            ₱<?= number_format(
                $total_closing,
                2
            ) ?>

        </div>

    </div>


    <div class="summary-card">

        <div class="summary-label">
            Cash Difference
        </div>

        <div class="summary-value difference">

            ₱<?= number_format(
                $total_difference,
                2
            ) ?>

        </div>

    </div>


</div>


<!-- ========================================================
     SESSION REPORT
======================================================== -->

<div class="card">


    <div class="card-title">

        Cashier Session Report

    </div>


    <div class="table-wrapper">


    <table>


        <thead>

            <tr>

                <th>
                    Session
                </th>

                <th>
                    Cashier
                </th>

                <th>
                    Opened
                </th>

                <th>
                    Closed
                </th>

                <th>
                    Transactions
                </th>

                <th>
                    Sales
                </th>

                <th>
                    Expenses
                </th>

                <th>
                    Opening
                </th>

                <th>
                    Expected
                </th>

                <th>
                    Closing
                </th>

                <th>
                    Difference
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


        <?php if (
            count($reports) > 0
        ): ?>


            <?php foreach (
                $reports
                as $row
            ): ?>


                <tr>


                    <!-- SESSION -->

                    <td>

                        #<?= $row['id'] ?>

                    </td>


                    <!-- CASHIER -->

                    <td>

                        <?= htmlspecialchars(
                            $row['full_name']
                        ) ?>

                    </td>


                    <!-- OPENED -->

                    <td>

                        <?= date(
                            'M d, Y h:i A',
                            strtotime(
                                $row['opened_at']
                            )
                        ) ?>

                    </td>


                    <!-- CLOSED -->

                    <td>

                        <?php if (
                            $row['closed_at']
                        ): ?>

                            <?= date(
                                'M d, Y h:i A',
                                strtotime(
                                    $row['closed_at']
                                )
                            ) ?>

                        <?php else: ?>

                            —

                        <?php endif; ?>

                    </td>


                    <!-- TRANSACTIONS -->

                    <td class="amount">

                        <?= number_format(
                            $row['transactions']
                        ) ?>

                    </td>


                    <!-- SALES -->

                    <td class="amount positive">

                        ₱<?= number_format(
                            $row['sales_total'],
                            2
                        ) ?>

                    </td>


                    <!-- EXPENSES -->

                    <td class="amount negative">

                        ₱<?= number_format(
                            $row['expenses_total'],
                            2
                        ) ?>

                    </td>


                    <!-- OPENING -->

                    <td>

                        ₱<?= number_format(
                            $row['opening_cash'],
                            2
                        ) ?>

                    </td>


                    <!-- EXPECTED -->

                    <td>

                        ₱<?= number_format(
                            $row['expected_cash'] ?? 0,
                            2
                        ) ?>

                    </td>


                    <!-- CLOSING -->

                    <td>

                        <?php if (
                            $row['closing_cash']
                            !== null
                        ): ?>

                            ₱<?= number_format(
                                $row['closing_cash'],
                                2
                            ) ?>

                        <?php else: ?>

                            —

                        <?php endif; ?>

                    </td>


                    <!-- DIFFERENCE -->

                    <td>

                        <?php if (
                            $row['cash_difference']
                            !== null
                        ): ?>

                            <span
                                class="<?=
                                    (
                                        $row[
                                            'cash_difference'
                                        ] < 0
                                    )
                                        ? 'negative'
                                        : 'positive'
                                ?>"
                            >

                                ₱<?= number_format(
                                    $row[
                                        'cash_difference'
                                    ],
                                    2
                                ) ?>

                            </span>

                        <?php else: ?>

                            —

                        <?php endif; ?>

                    </td>


                    <!-- STATUS -->

                    <td>

                        <?php if (
                            $row['status']
                            === 'open'
                        ): ?>

                            <span
                                class="
                                    status
                                    status-open
                                "
                            >

                                OPEN

                            </span>

                        <?php else: ?>

                            <span
                                class="
                                    status
                                    status-closed
                                "
                            >

                                CLOSED

                            </span>

                        <?php endif; ?>

                    </td>


                    <!-- ACTION -->

                    <td>

                        <a
                            href="print.php?id=<?= $row['id'] ?>"
                            target="_blank"
                            class="btn btn-primary"
                            style="
                                padding: 7px 10px;
                                font-size: 12px;
                            "
                        >

                            🖨 Print

                        </a>

                    </td>


                </tr>


            <?php endforeach; ?>


        <?php else: ?>


            <tr>

                <td
                    colspan="13"
                    class="empty"
                >

                    No cashier sessions found
                    for the selected filters.

                </td>

            </tr>


        <?php endif; ?>


        </tbody>


    </table>


    </div>


</div>


</div>


</body>

</html>