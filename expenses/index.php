<?php

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$role     = $_SESSION['role'] ?? '';

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
| SUMMARY
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        COALESCE(
            SUM(
                CASE
                    WHEN DATE(expense_date) = CURDATE()
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS today_expenses,

        COALESCE(
            SUM(
                CASE
                    WHEN YEAR(expense_date) = YEAR(CURDATE())
                    AND MONTH(expense_date) = MONTH(CURDATE())
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS month_expenses,

        COALESCE(SUM(amount), 0) AS total_expenses

    FROM expenses
");

$summary = $stmt->fetch();

$today_expenses = $summary['today_expenses'];
$month_expenses = $summary['month_expenses'];
$total_expenses = $summary['total_expenses'];


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');


/*
|--------------------------------------------------------------------------
| EXPENSE LIST
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        e.*,
        u.full_name
    FROM expenses e

    LEFT JOIN users u
        ON u.id = e.user_id
";

$params = [];

if ($search !== '') {

    $sql .= "
        WHERE
            e.expense_no LIKE :search
            OR e.expense_category LIKE :search
            OR e.description LIKE :search
            OR u.full_name LIKE :search
    ";

    $params[':search'] = '%' . $search . '%';
}

$sql .= "
    ORDER BY
        e.expense_date DESC,
        e.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$expenses = $stmt->fetchAll();

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
    Expenses | Canteen POS
</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f6f8;
    color: #1f2937;
}

.page {
    padding: 30px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.page-header h1 {
    font-size: 28px;
    font-weight: 700;
}

.page-header p {
    color: #6b7280;
    margin-top: 5px;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn-back {
    background: #3196f5;
    color: #374151;
    border: 1px solid #d1d5db;
}

.btn-back:hover {
    background: #3475f5;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 11px 18px;
    border-radius: 8px;
    border: none;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-edit {
    background: #f59e0b;
    color: white;
    padding: 7px 12px;
}

.btn-delete {
    background: #dc2626;
    color: white;
    padding: 7px 12px;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.summary-card {
    background: white;
    border-radius: 12px;
    padding: 22px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.summary-label {
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 10px;
}

.summary-value {
    font-size: 28px;
    font-weight: 700;
}

.card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.search-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.search-bar input {
    width: 100%;
    max-width: 400px;
    padding: 11px 14px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
}

.search-btn {
    background: #374151;
    color: white;
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
    padding: 14px;
    font-size: 13px;
    color: #6b7280;
    border-bottom: 1px solid #e5e7eb;
}

td {
    padding: 15px 14px;
    border-bottom: 1px solid #f1f1f1;
    font-size: 14px;
}

tr:hover {
    background: #fafafa;
}

.amount {
    font-weight: 700;
    color: #dc2626;
}

.actions {
    display: flex;
    gap: 6px;
}

.empty {
    text-align: center;
    padding: 50px;
    color: #9ca3af;
}

@media (max-width: 900px) {

    .summary-grid {
        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>

<div class="page">

   <div class="page-header">

    <div>

        <h1>
            Expenses
        </h1>

        <p>
            Manage and monitor canteen expenses.
        </p>

    </div>


    <div class="header-actions">

        <a
            href="../index.php"
            class="btn btn-back"
        >
            ← Back
        </a>

        <a
            href="add.php"
            class="btn btn-primary"
        >
            + Add Expense
        </a>

    </div>

</div>


    <!-- SUMMARY -->

    <div class="summary-grid">

        <div class="summary-card">

            <div class="summary-label">
                Expenses Today
            </div>

            <div class="summary-value">
                ₱<?= number_format($today_expenses, 2) ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-label">
                Expenses This Month
            </div>

            <div class="summary-value">
                ₱<?= number_format($month_expenses, 2) ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-label">
                Total Expenses
            </div>

            <div class="summary-value">
                ₱<?= number_format($total_expenses, 2) ?>
            </div>

        </div>

    </div>


    <!-- EXPENSE TABLE -->

    <div class="card">

        <form
            method="GET"
            class="search-bar"
        >

            <input
                type="text"
                name="search"
                placeholder="Search expense..."
                value="<?= htmlspecialchars($search) ?>"
            >

            <button
                type="submit"
                class="btn search-btn"
            >
                Search
            </button>

        </form>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>Expense No.</th>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Recorded By</th>
                        <th>Action</th>

                    </tr>

                </thead>


                <tbody>

                <?php if (count($expenses) > 0): ?>

                    <?php foreach ($expenses as $row): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars($row['expense_no']) ?>
                            </td>

                            <td>
                                <?= date(
                                    "M d, Y h:i A",
                                    strtotime($row['expense_date'])
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row['expense_category']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row['description'] ?? '-'
                                ) ?>
                            </td>

                            <td class="amount">
                                ₱<?= number_format(
                                    $row['amount'],
                                    2
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row['full_name'] ?? 'Unknown'
                                ) ?>
                            </td>

                            <td>

                                <div class="actions">

                                    <a
                                        href="edit.php?id=<?= $row['id'] ?>"
                                        class="btn btn-edit"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="delete.php?id=<?= $row['id'] ?>"
                                        class="btn btn-delete"
                                        onclick="return confirm('Delete this expense?');"
                                    >
                                        Delete
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="7"
                            class="empty"
                        >
                            No expenses found.
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