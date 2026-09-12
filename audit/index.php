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

$user_id = (int) $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';

/*
|--------------------------------------------------------------------------
| ADMIN ONLY
|--------------------------------------------------------------------------
*/

if ($role !== 'admin') {

    header("Location: ../pos/index.php");
    exit;

}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$module = trim($_GET['module'] ?? '');
$action = trim($_GET['action'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$per_page = 20;

$page = isset($_GET['page'])
    ? max(1, (int) $_GET['page'])
    : 1;

$offset = ($page - 1) * $per_page;

/*
|--------------------------------------------------------------------------
| BUILD QUERY
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];

if ($search !== '') {

    $where[] = "
        (
            username LIKE :search
            OR action LIKE :search
            OR module LIKE :search
            OR description LIKE :search
            OR ip_address LIKE :search
        )
    ";

    $params[':search'] = "%{$search}%";

}

if ($module !== '') {

    $where[] = "module = :module";

    $params[':module'] = $module;

}

if ($action !== '') {

    $where[] = "action = :action";

    $params[':action'] = $action;

}

if ($date_from !== '') {

    $where[] = "DATE(created_at) >= :date_from";

    $params[':date_from'] = $date_from;

}

if ($date_to !== '') {

    $where[] = "DATE(created_at) <= :date_to";

    $params[':date_to'] = $date_to;

}

$where_sql = '';

if (!empty($where)) {

    $where_sql = "WHERE " . implode(" AND ", $where);

}

/*
|--------------------------------------------------------------------------
| TOTAL RECORDS
|--------------------------------------------------------------------------
*/

$count_sql = "

    SELECT COUNT(*)

    FROM audit_logs

    {$where_sql}

";

$count_stmt = $pdo->prepare($count_sql);

$count_stmt->execute($params);

$total_records = (int) $count_stmt->fetchColumn();

$total_pages = max(
    1,
    (int) ceil($total_records / $per_page)
);

/*
|--------------------------------------------------------------------------
| GET ACTIVITY LOGS
|--------------------------------------------------------------------------
*/

$sql = "

    SELECT *

    FROM audit_logs

    {$where_sql}

    ORDER BY created_at DESC

    LIMIT :limit OFFSET :offset

";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {

    $stmt->bindValue(
        $key,
        $value
    );

}

$stmt->bindValue(
    ':limit',
    $per_page,
    PDO::PARAM_INT
);

$stmt->bindValue(
    ':offset',
    $offset,
    PDO::PARAM_INT
);

$stmt->execute();

$logs = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);

/*
|--------------------------------------------------------------------------
| MODULES
|--------------------------------------------------------------------------
*/

$modules = $pdo
    ->query("
        SELECT DISTINCT module
        FROM audit_logs
        ORDER BY module ASC
    ")
    ->fetchAll(
        PDO::FETCH_COLUMN
    );

/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

$actions = $pdo
    ->query("
        SELECT DISTINCT action
        FROM audit_logs
        ORDER BY action ASC
    ")
    ->fetchAll(
        PDO::FETCH_COLUMN
    );

/*
|--------------------------------------------------------------------------
| BUILD PAGINATION URL
|--------------------------------------------------------------------------
*/

function buildPageUrl($pageNumber)
{

    $query = $_GET;

    $query['page'] = $pageNumber;

    return '?' . http_build_query($query);

}

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
    Activity Log
</title>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>

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

    color: #222;

}

.container {

    padding: 25px;

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

.page-title {

    display: flex;

    align-items: center;

    gap: 12px;

}

.page-title h1 {

    margin: 0;

    font-size: 26px;

}

.page-title i {

    font-size: 28px;

}

/*
|--------------------------------------------------------------------------
| BUTTONS
|--------------------------------------------------------------------------
*/

.btn {

    border: none;

    padding: 10px 16px;

    border-radius: 6px;

    cursor: pointer;

    text-decoration: none;

    display: inline-flex;

    align-items: center;

    gap: 7px;

    font-size: 14px;

}

.btn-back {

    background: #555;

    color: white;

}

.btn-danger {

    background: #dc3545;

    color: white;

}

.btn-primary {

    background: #0d6efd;

    color: white;

}

.btn-secondary {

    background: #6c757d;

    color: white;

}

/*
|--------------------------------------------------------------------------
| CARD
|--------------------------------------------------------------------------
*/

.card {

    background: white;

    border-radius: 10px;

    padding: 20px;

    box-shadow:

        0 2px 10px

        rgba(0,0,0,.05);

    margin-bottom: 20px;

}

/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

.filter-grid {

    display: grid;

    grid-template-columns:

        repeat(
            auto-fit,
            minmax(180px, 1fr)
        );

    gap: 15px;

}

.form-group {

    display: flex;

    flex-direction: column;

    gap: 6px;

}

label {

    font-size: 13px;

    font-weight: 600;

}

input,
select {

    padding: 10px;

    border:

        1px solid #ddd;

    border-radius: 6px;

    font-size: 14px;

}

.filter-actions {

    display: flex;

    gap: 10px;

    align-items: end;

}

/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.table-wrapper {

    overflow-x: auto;

}

table {

    width: 100%;

    border-collapse: collapse;

}

th {

    background: #f8f9fa;

    text-align: left;

    padding: 12px;

    font-size: 13px;

}

td {

    padding: 12px;

    border-top:

        1px solid #eee;

    font-size: 13px;

}

.badge {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    background: #e9ecef;

    font-size: 11px;

    font-weight: bold;

}

.empty {

    text-align: center;

    padding: 40px;

    color: #777;

}

/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

.pagination {

    display: flex;

    gap: 5px;

    justify-content: center;

    margin-top: 25px;

}

.pagination a {

    padding: 8px 12px;

    text-decoration: none;

    background: white;

    border:

        1px solid #ddd;

    color: #333;

    border-radius: 5px;

}

.pagination a.active {

    background: #0d6efd;

    color: white;

}

/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 700px) {

    .page-header {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;

    }

    .filter-actions {

        align-items: stretch;

        flex-direction: column;

    }

}

</style>

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="page-header">

        <div class="page-title">

            <i class="bi bi-clock-history"></i>

            <div>

                <h1>
                    Activity Log
                </h1>

                <small>

                    Monitor all system activities

                </small>

            </div>

        </div>


        <div>

            <a
                href="../index.php"
                class="btn btn-back"
            >

                <i class="bi bi-arrow-left"></i>

                Back

            </a>


            <a
                href="clear.php"
                class="btn btn-danger"
                onclick="
                    return confirm(
                        'Are you sure you want to clear all activity logs?'
                    );
                "
            >

                <i class="bi bi-trash"></i>

                Clear Logs

            </a>

        </div>

    </div>


    <!-- FILTER -->

    <div class="card">

        <form
            method="GET"
        >

            <div class="filter-grid">


                <div class="form-group">

                    <label>

                        Search

                    </label>

                    <input
                        type="text"
                        name="search"
                        placeholder="Search activity..."
                        value="<?= htmlspecialchars($search) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>

                        Module

                    </label>

                    <select
                        name="module"
                    >

                        <option value="">

                            All Modules

                        </option>

                        <?php foreach ($modules as $item): ?>

                            <option
                                value="<?= htmlspecialchars($item) ?>"
                                <?= $module === $item ? 'selected' : '' ?>
                            >

                                <?= htmlspecialchars($item) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>

                        Action

                    </label>

                    <select
                        name="action"
                    >

                        <option value="">

                            All Actions

                        </option>

                        <?php foreach ($actions as $item): ?>

                            <option
                                value="<?= htmlspecialchars($item) ?>"
                                <?= $action === $item ? 'selected' : '' ?>
                            >

                                <?= htmlspecialchars($item) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

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


                <div class="filter-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i class="bi bi-search"></i>

                        Filter

                    </button>


                    <a
                        href="index.php"
                        class="btn btn-secondary"
                    >

                        Reset

                    </a>

                </div>

            </div>

        </form>

    </div>


    <!-- TABLE -->

    <div class="card">

        <div
            style="
                display:flex;
                justify-content:space-between;
                align-items:center;
                margin-bottom:15px;
            "
        >

            <strong>

                Total Records:
                <?= number_format($total_records) ?>

            </strong>

        </div>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>#</th>

                        <th>User</th>

                        <th>Action</th>

                        <th>Module</th>

                        <th>Description</th>

                        <th>IP Address</th>

                        <th>Date & Time</th>

                    </tr>

                </thead>


                <tbody>

                <?php if (empty($logs)): ?>

                    <tr>

                        <td
                            colspan="7"
                            class="empty"
                        >

                            <i
                                class="bi bi-clock-history"
                                style="
                                    font-size:35px;
                                "
                            ></i>

                            <br><br>

                            No activity logs found.

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($logs as $index => $log): ?>

                        <tr>

                            <td>

                                <?=

                                    $offset
                                    + $index
                                    + 1

                                ?>

                            </td>


                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $log['username']
                                        ?: 'System'
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <span
                                    class="badge"
                                >

                                    <?= htmlspecialchars(
                                        $log['action']
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $log['module']
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $log['description']
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $log['ip_address']
                                ) ?>

                            </td>


                            <td>

                                <?= date(
                                    'M d, Y h:i A',
                                    strtotime(
                                        $log['created_at']
                                    )
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>


        <!-- PAGINATION -->

        <?php if ($total_pages > 1): ?>

            <div
                class="pagination"
            >

                <?php if ($page > 1): ?>

                    <a
                        href="<?= buildPageUrl($page - 1) ?>"
                    >

                        Previous

                    </a>

                <?php endif; ?>


                <?php

                $start_page =
                    max(
                        1,
                        $page - 2
                    );

                $end_page =
                    min(
                        $total_pages,
                        $page + 2
                    );

                ?>


                <?php for (

                    $i = $start_page;

                    $i <= $end_page;

                    $i++

                ): ?>

                    <a
                        href="<?= buildPageUrl($i) ?>"
                        class="<?=

                            $i === $page

                            ? 'active'

                            : ''

                        ?>"
                    >

                        <?= $i ?>

                    </a>

                <?php endfor; ?>


                <?php if ($page < $total_pages): ?>

                    <a
                        href="<?= buildPageUrl($page + 1) ?>"
                    >

                        Next

                    </a>

                <?php endif; ?>

            </div>

        <?php endif; ?>


    </div>

</div>

</body>

</html>