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

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';

/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
*/

if ($role === 'cashier') {
    header("Location: ../pos/index.php");
    exit;
}

if (!in_array($role, ['admin', 'inventory'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

if (!isset($conn) || !($conn instanceof PDO)) {
    die('Database connection not available.');
}

$conn->setAttribute(
    PDO::ATTR_ERRMODE,
    PDO::ERRMODE_EXCEPTION
);

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function action_badge_class($action)
{
    $action = strtoupper((string) $action);

    switch ($action) {

        case 'SALE':
        case 'STOCK_IN':
        case 'ADJUSTMENT_ADD':
        case 'RETURN':
        case 'CREATE':
        case 'OPEN_SESSION':
        case 'LOGIN':
            return 'success';

        case 'UPDATE':
        case 'ADJUSTMENT_SUBTRACT':
            return 'warning';

        case 'VOID':
        case 'REFUND':
        case 'DELETE':
        case 'CLOSE_SESSION':
            return 'danger';

        case 'LOGOUT':
            return 'secondary';

        default:
            return 'secondary';
    }
}

function format_quantity($value)
{
    if ($value === null || $value === '') {
        return '-';
    }

    $number = number_format(
        (float) $value,
        3,
        '.',
        ','
    );

    $number = rtrim($number, '0');
    $number = rtrim($number, '.');

    return $number;
}

function format_date_time($value)
{
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return '-';
    }

    return date(
        'M d, Y h:i A',
        $timestamp
    );
}

function format_date_only($value)
{
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return '-';
    }

    return date(
        'M d, Y',
        $timestamp
    );
}

function format_time_only($value)
{
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return '-';
    }

    return date(
        'h:i A',
        $timestamp
    );
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search = trim(
    $_GET['search'] ?? ''
);

$date_from = trim(
    $_GET['date_from'] ?? ''
);

$date_to = trim(
    $_GET['date_to'] ?? ''
);

$module = trim(
    $_GET['module'] ?? ''
);

$action = trim(
    $_GET['action'] ?? ''
);

$filter_user = trim(
    $_GET['user_id'] ?? ''
);

/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$per_page = 25;

$page = isset($_GET['page'])
    ? (int) $_GET['page']
    : 1;

if ($page < 1) {
    $page = 1;
}

$offset = ($page - 1) * $per_page;

/*
|--------------------------------------------------------------------------
| CONDITIONS
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $where[] = "
        (
            username LIKE :search_username
            OR full_name LIKE :search_full_name
            OR action LIKE :search_action
            OR module LIKE :search_module
            OR description LIKE :search_description
            OR reference_text LIKE :search_reference_text
            OR product_name LIKE :search_product
            OR movement_type LIKE :search_movement
            OR remarks LIKE :search_remarks
        )
    ";

    $search_like = '%' . $search . '%';

    $params[':search_username'] = $search_like;
    $params[':search_full_name'] = $search_like;
    $params[':search_action'] = $search_like;
    $params[':search_module'] = $search_like;
    $params[':search_description'] = $search_like;
    $params[':search_reference_text'] = $search_like;
    $params[':search_product'] = $search_like;
    $params[':search_movement'] = $search_like;
    $params[':search_remarks'] = $search_like;
}

/*
|--------------------------------------------------------------------------
| DATE FROM
|--------------------------------------------------------------------------
*/

if ($date_from !== '') {

    $where[] = "
        history_date >= :date_from
    ";

    $params[':date_from'] =
        $date_from . ' 00:00:00';
}

/*
|--------------------------------------------------------------------------
| DATE TO
|--------------------------------------------------------------------------
*/

if ($date_to !== '') {

    $where[] = "
        history_date <= :date_to
    ";

    $params[':date_to'] =
        $date_to . ' 23:59:59';
}

/*
|--------------------------------------------------------------------------
| MODULE
|--------------------------------------------------------------------------
*/

if ($module !== '') {

    $where[] = "
        module = :module
    ";

    $params[':module'] = $module;
}

/*
|--------------------------------------------------------------------------
| ACTION
|--------------------------------------------------------------------------
*/

if ($action !== '') {

    $where[] = "
        action = :action
    ";

    $params[':action'] = $action;
}

/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/

if ($filter_user !== '') {

    $where[] = "
        user_id = :filter_user
    ";

    $params[':filter_user'] =
        (int) $filter_user;
}

/*
|--------------------------------------------------------------------------
| WHERE SQL
|--------------------------------------------------------------------------
*/

$where_sql = '';

if (!empty($where)) {

    $where_sql =
        'WHERE ' .
        implode(
            ' AND ',
            $where
        );
}

/*
|--------------------------------------------------------------------------
| HISTORY BASE QUERY
|--------------------------------------------------------------------------
|
| AUDIT LOGS
| +
| INVENTORY MOVEMENTS
|
|--------------------------------------------------------------------------
*/

$history_base_sql = "

    SELECT

        CONCAT(
            'AUDIT-',
            al.id
        ) AS history_id,

        al.user_id,

        COALESCE(
            u.username,
            'System'
        ) AS username,

        COALESCE(
            u.full_name,
            'System'
        ) AS full_name,

        al.action AS action,

        COALESCE(
            al.module,
            'System'
        ) AS module,

        al.reference_id AS reference_id,

        CASE

            WHEN al.reference_id IS NOT NULL
            THEN CONCAT(
                '#',
                al.reference_id
            )

            ELSE ''

        END AS reference_text,

        al.description AS description,

        al.ip_address AS ip_address,

        al.created_at AS history_date,

        NULL AS product_id,

        NULL AS product_name,

        NULL AS movement_type,

        NULL AS quantity,

        NULL AS stock_before,

        NULL AS stock_after,

        NULL AS remarks,

        'AUDIT' AS source_type

    FROM audit_logs al

    LEFT JOIN users u
        ON u.id = al.user_id


    UNION ALL


    SELECT

        CONCAT(
            'INV-',
            im.id
        ) AS history_id,

        im.user_id,

        COALESCE(
            u.username,
            'System'
        ) AS username,

        COALESCE(
            u.full_name,
            'System'
        ) AS full_name,

        im.movement_type AS action,

        'Inventory' AS module,

        im.reference_id AS reference_id,

        CASE

            WHEN im.reference_type IS NOT NULL
                 AND im.reference_id IS NOT NULL

            THEN CONCAT(
                im.reference_type,
                ' #',
                im.reference_id
            )

            WHEN im.reference_type IS NOT NULL

            THEN im.reference_type

            WHEN im.reference_id IS NOT NULL

            THEN CONCAT(
                '#',
                im.reference_id
            )

            ELSE ''

        END AS reference_text,

        im.remarks AS description,

        NULL AS ip_address,

        im.created_at AS history_date,

        im.product_id,

        p.product_name,

        im.movement_type,

        im.quantity,

        im.stock_before,

        im.stock_after,

        im.remarks,

        'INVENTORY' AS source_type

    FROM inventory_movements im

    LEFT JOIN users u
        ON u.id = im.user_id

    LEFT JOIN products p
        ON p.id = im.product_id

";

/*
|--------------------------------------------------------------------------
| COUNT RECORDS
|--------------------------------------------------------------------------
*/

$count_sql = "

    SELECT COUNT(*)

    FROM (

        $history_base_sql

    ) AS history

    $where_sql

";

$count_stmt = $conn->prepare(
    $count_sql
);

foreach ($params as $key => $value) {

    if (
        $key === ':filter_user'
    ) {

        $count_stmt->bindValue(
            $key,
            $value,
            PDO::PARAM_INT
        );

    } else {

        $count_stmt->bindValue(
            $key,
            $value
        );
    }
}

$count_stmt->execute();

$total_records =
    (int) $count_stmt->fetchColumn();

$total_pages = max(
    1,
    (int) ceil(
        $total_records / $per_page
    )
);

if ($page > $total_pages) {

    $page = $total_pages;

    $offset =
        ($page - 1) * $per_page;
}

/*
|--------------------------------------------------------------------------
| MAIN HISTORY QUERY
|--------------------------------------------------------------------------
*/

$sql = "

    SELECT *

    FROM (

        $history_base_sql

    ) AS history

    $where_sql

    ORDER BY
        history_date DESC,
        history_id DESC

    LIMIT :limit
    OFFSET :offset

";

$stmt = $conn->prepare(
    $sql
);

foreach ($params as $key => $value) {

    if (
        $key === ':filter_user'
    ) {

        $stmt->bindValue(
            $key,
            $value,
            PDO::PARAM_INT
        );

    } else {

        $stmt->bindValue(
            $key,
            $value
        );
    }
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

$history =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

/*
|--------------------------------------------------------------------------
| USERS
|--------------------------------------------------------------------------
*/

$user_stmt = $conn->query("

    SELECT
        id,
        username,
        full_name

    FROM users

    ORDER BY full_name ASC

");

$users =
    $user_stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

/*
|--------------------------------------------------------------------------
| MODULES
|--------------------------------------------------------------------------
*/

$modules = [

    'POS',
    'Inventory',
    'Products',
    'Expenses',
    'Cashier Session',
    'Users',
    'Categories',
    'Suppliers',
    'Receipt Settings',
    'System'

];

/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

$actions = [

    'SALE',
    'VOID',
    'REFUND',
    'STOCK_IN',
    'ADJUSTMENT_ADD',
    'ADJUSTMENT_SUBTRACT',
    'RETURN',
    'EXPENSE',
    'CREATE',
    'UPDATE',
    'DELETE',
    'LOGIN',
    'LOGOUT',
    'OPEN_SESSION',
    'CLOSE_SESSION'

];

/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

$summary_sql = "

    SELECT

        COUNT(*) AS total_records,

        SUM(
            CASE
                WHEN source_type = 'AUDIT'
                THEN 1
                ELSE 0
            END
        ) AS audit_count,

        SUM(
            CASE
                WHEN source_type = 'INVENTORY'
                THEN 1
                ELSE 0
            END
        ) AS inventory_count

    FROM (

        $history_base_sql

    ) AS history

    $where_sql

";

$summary_stmt = $conn->prepare(
    $summary_sql
);

foreach ($params as $key => $value) {

    if (
        $key === ':filter_user'
    ) {

        $summary_stmt->bindValue(
            $key,
            $value,
            PDO::PARAM_INT
        );

    } else {

        $summary_stmt->bindValue(
            $key,
            $value
        );
    }
}

$summary_stmt->execute();

$summary =
    $summary_stmt->fetch(
        PDO::FETCH_ASSOC
    );

$summary_total =
    (int) (
        $summary['total_records']
        ?? 0
    );

$summary_audit =
    (int) (
        $summary['audit_count']
        ?? 0
    );

$summary_inventory =
    (int) (
        $summary['inventory_count']
        ?? 0
    );

/*
|--------------------------------------------------------------------------
| PAGE TITLE
|--------------------------------------------------------------------------
*/

$page_title = 'History';

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
        History | Canteen POS
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f3f5f7;
            font-family:
                "Segoe UI",
                Arial,
                sans-serif;
            color: #263238;
        }

        .page-wrapper {
            padding: 25px;
        }

        .page-header {
            background: #ffffff;
            border: 1px solid #e0e4e7;
            border-radius: 8px;
            padding: 18px 20px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .page-title {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            color: #263238;
        }

        .page-subtitle {
            margin-top: 4px;
            color: #78909c;
            font-size: 13px;
        }

        .back-btn {
            border: 1px solid #cfd8dc;
            background: #ffffff;
            color: #37474f;
            border-radius: 5px;
            padding: 8px 14px;
            text-decoration: none;
            font-size: 14px;
        }

        .back-btn:hover {
            background: #f5f7f8;
            color: #263238;
        }

        .summary-grid {
            display: grid;
            grid-template-columns:
                repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 18px;
        }

        .summary-card {
            background: #ffffff;
            border: 1px solid #e0e4e7;
            border-radius: 8px;
            padding: 17px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .summary-icon {
            width: 44px;
            height: 44px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eceff1;
            color: #455a64;
            font-size: 20px;
        }

        .summary-label {
            color: #78909c;
            font-size: 12px;
            margin-bottom: 3px;
        }

        .summary-value {
            font-size: 22px;
            font-weight: 600;
        }

        .filter-card {
            background: #ffffff;
            border: 1px solid #e0e4e7;
            border-radius: 8px;
            padding: 18px;
            margin-bottom: 18px;
        }

        .filter-title {
            font-weight: 600;
            font-size: 15px;
            margin-bottom: 15px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 600;
            color: #546e7a;
            margin-bottom: 5px;
        }

        .form-control,
        .form-select {
            border-color: #cfd8dc;
            border-radius: 5px;
            font-size: 13px;
            min-height: 38px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #78909c;
            box-shadow:
                0 0 0 2px
                rgba(120, 144, 156, .12);
        }

        .btn-filter {
            min-height: 38px;
            background: #455a64;
            border: 1px solid #455a64;
            color: #ffffff;
            border-radius: 5px;
            padding: 0 18px;
            font-size: 13px;
        }

        .btn-filter:hover {
            background: #37474f;
            color: #ffffff;
        }

        .btn-clear {
            min-height: 38px;
            border: 1px solid #cfd8dc;
            background: #ffffff;
            color: #455a64;
            border-radius: 5px;
            padding: 0 15px;
            text-decoration: none;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
        }

        .btn-clear:hover {
            background: #f5f7f8;
            color: #263238;
        }

        .table-card {
            background: #ffffff;
            border: 1px solid #e0e4e7;
            border-radius: 8px;
            overflow: hidden;
        }

        .table-header {
            padding: 15px 18px;
            border-bottom: 1px solid #e5e9eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header-title {
            font-size: 15px;
            font-weight: 600;
        }

        .record-count {
            color: #78909c;
            font-size: 12px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            margin: 0 !important;
        }

        .history-table {
            min-width: 1100px;
        }

        .history-table th {
            background: #f7f8f9;
            color: #546e7a;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .3px;
            white-space: nowrap;
            padding: 12px 14px;
            border-bottom: 1px solid #e0e4e7;
        }

        .history-table td {
            padding: 12px 14px;
            vertical-align: middle;
            font-size: 13px;
            border-bottom: 1px solid #edf0f2;
        }

        .history-table tbody tr:hover {
            background: #fafbfc;
        }

        .date-main {
            font-weight: 600;
            color: #37474f;
        }

        .date-sub {
            font-size: 11px;
            color: #90a4ae;
            margin-top: 2px;
        }

        .user-name {
            font-weight: 600;
            color: #455a64;
        }

        .username {
            color: #90a4ae;
            font-size: 11px;
        }

        .badge-action {
            display: inline-block;
            padding: 5px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .2px;
            white-space: nowrap;
        }

        .badge-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-warning {
            background: #fff8e1;
            color: #f57f17;
        }

        .badge-danger {
            background: #ffebee;
            color: #c62828;
        }

        .badge-secondary {
            background: #eceff1;
            color: #455a64;
        }

        .module-text {
            color: #546e7a;
            font-weight: 600;
        }

        .reference {
            font-family: Consolas, monospace;
            font-size: 12px;
            color: #455a64;
            white-space: nowrap;
        }

        .description {
            max-width: 320px;
            color: #607d8b;
            line-height: 1.4;
        }

        .inventory-detail {
            font-size: 11px;
            color: #78909c;
            margin-top: 3px;
        }

        .btn-view {
            border: 1px solid #cfd8dc;
            background: #ffffff;
            color: #455a64;
            border-radius: 4px;
            font-size: 12px;
            padding: 5px 9px;
        }

        .btn-view:hover {
            background: #eceff1;
            color: #263238;
        }

        .empty-state {
            text-align: center;
            padding: 55px 20px !important;
            color: #90a4ae;
        }

        .empty-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .pagination-wrapper {
            padding: 15px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #edf0f2;
        }

        .pagination-info {
            color: #78909c;
            font-size: 12px;
        }

        .pagination {
            margin: 0;
        }

        .page-link {
            color: #455a64;
            border-color: #cfd8dc;
            font-size: 12px;
        }

        .page-item.active .page-link {
            background: #455a64;
            border-color: #455a64;
            color: #ffffff;
        }

        .modal-content {
            border: 0;
            border-radius: 8px;
        }

        .modal-header {
            background: #455a64;
            color: #ffffff;
            border-radius: 8px 8px 0 0;
        }

        .modal-title {
            font-size: 16px;
            font-weight: 600;
        }

        .detail-row {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #edf0f2;
        }

        .detail-label {
            color: #78909c;
            font-size: 12px;
            font-weight: 600;
        }

        .detail-value {
            color: #37474f;
            font-size: 13px;
            word-break: break-word;
        }

        @media (max-width: 900px) {

            .page-wrapper {
                padding: 15px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .pagination-wrapper {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }

        }

    </style>

</head>

<body>

<div class="page-wrapper">

    <!-- HEADER -->

    <div class="page-header">

        <div>

            <h1 class="page-title">

                <i class="bi bi-clock-history"></i>

                History

            </h1>

            <div class="page-subtitle">

                System activity and inventory movement history

            </div>

        </div>

        <a
            href="../backoffice/index.php"
            class="back-btn"
        >

            <i class="bi bi-arrow-left"></i>

            Back to Back Office

        </a>

    </div>


    <!-- SUMMARY -->

    <div class="summary-grid">

        <div class="summary-card">

            <div class="summary-icon">

                <i class="bi bi-clock-history"></i>

            </div>

            <div>

                <div class="summary-label">
                    TOTAL HISTORY
                </div>

                <div class="summary-value">
                    <?= number_format($summary_total) ?>
                </div>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon">

                <i class="bi bi-journal-text"></i>

            </div>

            <div>

                <div class="summary-label">
                    AUDIT LOGS
                </div>

                <div class="summary-value">
                    <?= number_format($summary_audit) ?>
                </div>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon">

                <i class="bi bi-box-seam"></i>

            </div>

            <div>

                <div class="summary-label">
                    INVENTORY MOVEMENTS
                </div>

                <div class="summary-value">
                    <?= number_format($summary_inventory) ?>
                </div>

            </div>

        </div>

    </div>


    <!-- FILTER -->

    <div class="filter-card">

        <div class="filter-title">

            <i class="bi bi-funnel"></i>

            Filter History

        </div>

        <form method="GET">

            <div class="row g-3">

                <div class="col-lg-3 col-md-6">

                    <label class="form-label">
                        Search
                    </label>

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Search history..."
                        value="<?= e($search) ?>"
                    >

                </div>


                <div class="col-lg-2 col-md-6">

                    <label class="form-label">
                        Date From
                    </label>

                    <input
                        type="date"
                        name="date_from"
                        class="form-control"
                        value="<?= e($date_from) ?>"
                    >

                </div>


                <div class="col-lg-2 col-md-6">

                    <label class="form-label">
                        Date To
                    </label>

                    <input
                        type="date"
                        name="date_to"
                        class="form-control"
                        value="<?= e($date_to) ?>"
                    >

                </div>


                <div class="col-lg-2 col-md-6">

                    <label class="form-label">
                        Module
                    </label>

                    <select
                        name="module"
                        class="form-select"
                    >

                        <option value="">
                            All Modules
                        </option>

                        <?php foreach ($modules as $item): ?>

                            <option
                                value="<?= e($item) ?>"
                                <?php
                                if ($module === $item) {
                                    echo 'selected';
                                }
                                ?>
                            >

                                <?= e($item) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="col-lg-2 col-md-6">

                    <label class="form-label">
                        Action
                    </label>

                    <select
                        name="action"
                        class="form-select"
                    >

                        <option value="">
                            All Actions
                        </option>

                        <?php foreach ($actions as $item): ?>

                            <option
                                value="<?= e($item) ?>"
                                <?php
                                if ($action === $item) {
                                    echo 'selected';
                                }
                                ?>
                            >

                                <?= e($item) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="col-lg-3 col-md-6">

                    <label class="form-label">
                        User
                    </label>

                    <select
                        name="user_id"
                        class="form-select"
                    >

                        <option value="">
                            All Users
                        </option>

                        <?php foreach ($users as $u): ?>

                            <option
                                value="<?= (int) $u['id'] ?>"
                                <?php
                                if (
                                    (string) $filter_user
                                    === (string) $u['id']
                                ) {
                                    echo 'selected';
                                }
                                ?>
                            >

                                <?= e($u['full_name']) ?>

                                (<?= e($u['username']) ?>)

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="col-lg-3 col-md-6 d-flex align-items-end gap-2">

                    <button
                        type="submit"
                        class="btn-filter"
                    >

                        <i class="bi bi-search"></i>

                        Apply Filter

                    </button>

                    <a
                        href="history.php"
                        class="btn-clear"
                    >

                        <i class="bi bi-x-circle me-1"></i>

                        Clear

                    </a>

                </div>

            </div>

        </form>

    </div>


    <!-- TABLE -->

    <div class="table-card">

        <div class="table-header">

            <div class="table-header-title">

                <i class="bi bi-list-ul"></i>

                Activity History

            </div>

            <div class="record-count">

                <?= number_format($total_records) ?>

                record(s)

            </div>

        </div>


        <div class="table-responsive">

            <table class="table history-table">

                <thead>

                    <tr>

                        <th>
                            Date / Time
                        </th>

                        <th>
                            User
                        </th>

                        <th>
                            Module
                        </th>

                        <th>
                            Action
                        </th>

                        <th>
                            Reference
                        </th>

                        <th>
                            Description
                        </th>

                        <th>
                            Details
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if (!empty($history)): ?>

                    <?php foreach ($history as $index => $row): ?>

                        <?php

                        $safe_history_id =
                            preg_replace(
                                '/[^a-zA-Z0-9]/',
                                '',
                                (string)
                                $row['history_id']
                            );

                        $modal_id =
                            'historyModal'
                            . $index
                            . '_'
                            . $safe_history_id;

                        $action_value =
                            isset($row['action'])
                                ? (string)
                                $row['action']
                                : '';

                        $action_label =
                            str_replace(
                                '_',
                                ' ',
                                $action_value
                            );

                        $action_class =
                            action_badge_class(
                                $action_value
                            );

                        $reference_display = '-';

                        if (
                            isset(
                                $row['reference_text']
                            )
                            &&
                            $row['reference_text']
                            !== ''
                            &&
                            $row['reference_text']
                            !== null
                        ) {

                            $reference_display =
                                $row['reference_text'];

                        } elseif (
                            isset(
                                $row['reference_id']
                            )
                            &&
                            $row['reference_id']
                            !== null
                        ) {

                            $reference_display =
                                '#'
                                . $row['reference_id'];
                        }

                        ?>

                        <tr>

                            <!-- DATE -->

                            <td>

                                <div class="date-main">

                                    <?= e(
                                        format_date_only(
                                            $row['history_date']
                                        )
                                    ) ?>

                                </div>

                                <div class="date-sub">

                                    <?= e(
                                        format_time_only(
                                            $row['history_date']
                                        )
                                    ) ?>

                                </div>

                            </td>


                            <!-- USER -->

                            <td>

                                <div class="user-name">

                                    <?= e(
                                        $row['full_name']
                                    ) ?>

                                </div>

                                <div class="username">

                                    @<?= e(
                                        $row['username']
                                    ) ?>

                                </div>

                            </td>


                            <!-- MODULE -->

                            <td>

                                <span class="module-text">

                                    <?= e(
                                        $row['module']
                                    ) ?>

                                </span>

                            </td>


                            <!-- ACTION -->

                            <td>

                                <span
                                    class="badge-action badge-<?= e(
                                        $action_class
                                    ) ?>"
                                >

                                    <?= e(
                                        $action_label
                                    ) ?>

                                </span>

                            </td>


                            <!-- REFERENCE -->

                            <td>

                                <span class="reference">

                                    <?= e(
                                        $reference_display
                                    ) ?>

                                </span>

                            </td>


                            <!-- DESCRIPTION -->

                            <td>

                                <div class="description">

                                    <?= e(
                                        !empty(
                                            $row['description']
                                        )
                                            ? $row['description']
                                            : '-'
                                    ) ?>

                                </div>


                                <?php if (
                                    $row['source_type']
                                    === 'INVENTORY'
                                    &&
                                    !empty(
                                        $row['product_name']
                                    )
                                ): ?>

                                    <div class="inventory-detail">

                                        <?= e(
                                            $row['product_name']
                                        ) ?>

                                        · Qty:

                                        <?= e(
                                            format_quantity(
                                                $row['quantity']
                                            )
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </td>


                            <!-- VIEW -->

                            <td>

                                <button
                                    type="button"
                                    class="btn-view"
                                    data-bs-toggle="modal"
                                    data-bs-target="#<?= e(
                                        $modal_id
                                    ) ?>"
                                >

                                    <i class="bi bi-eye"></i>

                                    View

                                </button>

                            </td>

                        </tr>


                        <!-- MODAL -->

                        <div
                            class="modal fade"
                            id="<?= e($modal_id) ?>"
                            tabindex="-1"
                            aria-hidden="true"
                        >

                            <div
                                class="modal-dialog modal-lg modal-dialog-centered"
                            >

                                <div class="modal-content">

                                    <div class="modal-header">

                                        <h5 class="modal-title">

                                            <i class="bi bi-clock-history"></i>

                                            History Details

                                        </h5>

                                        <button
                                            type="button"
                                            class="btn-close btn-close-white"
                                            data-bs-dismiss="modal"
                                        ></button>

                                    </div>


                                    <div class="modal-body">

                                        <div class="detail-row">

                                            <div class="detail-label">
                                                Date / Time
                                            </div>

                                            <div class="detail-value">

                                                <?= e(
                                                    format_date_time(
                                                        $row['history_date']
                                                    )
                                                ) ?>

                                            </div>

                                        </div>


                                        <div class="detail-row">

                                            <div class="detail-label">
                                                User
                                            </div>

                                            <div class="detail-value">

                                                <?= e(
                                                    $row['full_name']
                                                ) ?>

                                                <small class="text-muted">

                                                    (@<?= e(
                                                        $row['username']
                                                    ) ?>)

                                                </small>

                                            </div>

                                        </div>


                                        <div class="detail-row">

                                            <div class="detail-label">
                                                Module
                                            </div>

                                            <div class="detail-value">

                                                <?= e(
                                                    $row['module']
                                                ) ?>

                                            </div>

                                        </div>


                                        <div class="detail-row">

                                            <div class="detail-label">
                                                Action
                                            </div>

                                            <div class="detail-value">

                                                <span
                                                    class="badge-action badge-<?= e(
                                                        $action_class
                                                    ) ?>"
                                                >

                                                    <?= e(
                                                        $action_label
                                                    ) ?>

                                                </span>

                                            </div>

                                        </div>


                                        <div class="detail-row">

                                            <div class="detail-label">
                                                Reference
                                            </div>

                                            <div class="detail-value">

                                                <?= e(
                                                    $reference_display
                                                ) ?>

                                            </div>

                                        </div>


                                        <div class="detail-row">

                                            <div class="detail-label">
                                                Description
                                            </div>

                                            <div class="detail-value">

                                                <?= e(
                                                    !empty(
                                                        $row['description']
                                                    )
                                                        ? $row['description']
                                                        : '-'
                                                ) ?>

                                            </div>

                                        </div>


                                        <?php if (
                                            $row['source_type']
                                            === 'INVENTORY'
                                        ): ?>

                                            <div class="detail-row">

                                                <div class="detail-label">
                                                    Product
                                                </div>

                                                <div class="detail-value">

                                                    <?= e(
                                                        !empty(
                                                            $row['product_name']
                                                        )
                                                            ? $row['product_name']
                                                            : '-'
                                                    ) ?>

                                                </div>

                                            </div>


                                            <div class="detail-row">

                                                <div class="detail-label">
                                                    Movement Type
                                                </div>

                                                <div class="detail-value">

                                                    <?= e(
                                                        !empty(
                                                            $row['movement_type']
                                                        )
                                                            ? $row['movement_type']
                                                            : '-'
                                                    ) ?>

                                                </div>

                                            </div>


                                            <div class="detail-row">

                                                <div class="detail-label">
                                                    Quantity
                                                </div>

                                                <div class="detail-value">

                                                    <?= e(
                                                        format_quantity(
                                                            $row['quantity']
                                                        )
                                                    ) ?>

                                                </div>

                                            </div>


                                            <div class="detail-row">

                                                <div class="detail-label">
                                                    Stock Before
                                                </div>

                                                <div class="detail-value">

                                                    <?= e(
                                                        format_quantity(
                                                            $row['stock_before']
                                                        )
                                                    ) ?>

                                                </div>

                                            </div>


                                            <div class="detail-row">

                                                <div class="detail-label">
                                                    Stock After
                                                </div>

                                                <div class="detail-value">

                                                    <?= e(
                                                        format_quantity(
                                                            $row['stock_after']
                                                        )
                                                    ) ?>

                                                </div>

                                            </div>

                                        <?php endif; ?>


                                        <?php if (
                                            !empty(
                                                $row['ip_address']
                                            )
                                        ): ?>

                                            <div class="detail-row">

                                                <div class="detail-label">
                                                    IP Address
                                                </div>

                                                <div class="detail-value">

                                                    <?= e(
                                                        $row['ip_address']
                                                    ) ?>

                                                </div>

                                            </div>

                                        <?php endif; ?>


                                        <div class="detail-row">

                                            <div class="detail-label">
                                                Source
                                            </div>

                                            <div class="detail-value">

                                                <?= e(
                                                    $row['source_type']
                                                ) ?>

                                            </div>

                                        </div>

                                    </div>


                                    <div class="modal-footer">

                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-sm"
                                            data-bs-dismiss="modal"
                                        >

                                            Close

                                        </button>

                                    </div>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="7"
                            class="empty-state"
                        >

                            <div class="empty-icon">

                                <i class="bi bi-clock-history"></i>

                            </div>

                            <div>

                                No history records found.

                            </div>

                            <small>

                                Try changing your filters
                                or search keyword.

                            </small>

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>


        <!-- PAGINATION -->

        <?php if ($total_records > 0): ?>

            <div class="pagination-wrapper">

                <div class="pagination-info">

                    Showing

                    <?= number_format(
                        $offset + 1
                    ) ?>

                    -

                    <?= number_format(
                        min(
                            $offset + $per_page,
                            $total_records
                        )
                    ) ?>

                    of

                    <?= number_format(
                        $total_records
                    ) ?>

                    records

                </div>


                <?php if ($total_pages > 1): ?>

                    <nav>

                        <ul class="pagination pagination-sm">

                            <?php

                            $query_params = $_GET;

                            unset(
                                $query_params['page']
                            );

                            ?>


                            <!-- PREVIOUS -->

                            <?php if ($page > 1): ?>

                                <?php

                                $query_params['page'] =
                                    $page - 1;

                                ?>

                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="?<?= e(
                                            http_build_query(
                                                $query_params
                                            )
                                        ) ?>"
                                    >

                                        <i class="bi bi-chevron-left"></i>

                                    </a>

                                </li>

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
                                $p = $start_page;
                                $p <= $end_page;
                                $p++
                            ): ?>

                                <?php

                                $query_params['page'] =
                                    $p;

                                ?>

                                <li
                                    class="page-item <?php
                                        if ($p === $page) {
                                            echo 'active';
                                        }
                                    ?>"
                                >

                                    <a
                                        class="page-link"
                                        href="?<?= e(
                                            http_build_query(
                                                $query_params
                                            )
                                        ) ?>"
                                    >

                                        <?= $p ?>

                                    </a>

                                </li>

                            <?php endfor; ?>


                            <!-- NEXT -->

                            <?php if (
                                $page < $total_pages
                            ): ?>

                                <?php

                                $query_params['page'] =
                                    $page + 1;

                                ?>

                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="?<?= e(
                                            http_build_query(
                                                $query_params
                                            )
                                        ) ?>"
                                    >

                                        <i class="bi bi-chevron-right"></i>

                                    </a>

                                </li>

                            <?php endif; ?>

                        </ul>

                    </nav>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>