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
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';

/*
|--------------------------------------------------------------------------
| POS ACCESS
|--------------------------------------------------------------------------
*/

if (!in_array($role, ['admin', 'cashier'])) {
    die("Access denied.");
}

/*
|--------------------------------------------------------------------------
| OPEN CASHIER SESSION
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

$cashierSession = $stmt->fetch(PDO::FETCH_ASSOC);

$hasSession = $cashierSession ? true : false;

/*
|--------------------------------------------------------------------------
| LOAD PRODUCTS
|--------------------------------------------------------------------------
| Products are NOT displayed on screen.
| They will only be found through barcode / product code / search.
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        p.id,
        p.product_code,
        p.barcode,
        p.product_name,
        p.category_id,
        p.unit,
        p.stock,
        p.cost_price,
        p.selling_price,
        c.category_name
    FROM products p
    LEFT JOIN categories c
        ON c.id = p.category_id
    WHERE p.status = 'active'
    ORDER BY p.product_name ASC
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Mini Canteen POS</title>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
>

<style>

/* =========================================================
   GLOBAL
========================================================= */

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
    width: 100%;
    height: 100%;
}

body {
    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f5f7f8;

    color: #17202a;

    overflow: hidden;
}


/* =========================================================
   HEADER
========================================================= */

.pos-header {

    height: 72px;

    background: #17202a;

    color: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 28px;

    border-bottom: 3px solid #18a558;

}

.brand {

    display: flex;

    align-items: center;

    gap: 12px;

    font-size: 22px;

    font-weight: 800;

    letter-spacing: .3px;

}

.brand-icon {

    width: 42px;

    height: 42px;

    border-radius: 8px;

    background: white;

    color: #159447;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 23px;

}

.header-right {

    display: flex;

    align-items: center;

    gap: 20px;

}

.cashier-info {

    font-size: 14px;

    color: #ffffff;

}

.cashier-info strong {

    color: #23c66d;

    font-size: 16px;

}

.back-btn {

    border: 1px solid #18a558;

    background: transparent;

    color: #18a558;

    padding: 10px 16px;

    border-radius: 7px;

    font-weight: 700;

    cursor: pointer;

    font-size: 14px;

    display: flex;

    align-items: center;

    gap: 7px;

    transition: .2s;

}

.back-btn:hover {

    background: #18a558;

    color: white;

}

.logout-btn {

    border: 0;

    background: #dc3545;

    color: white;

    padding: 11px 18px;

    border-radius: 7px;

    font-weight: 700;

    cursor: pointer;

}

.logout-btn:hover {

    background: #bb2d3b;

}


/* =========================================================
   MAIN POS
========================================================= */

.pos-wrapper {

    height: calc(100vh - 72px);

    padding: 22px 26px;

    display: grid;

    grid-template-columns: minmax(0, 1fr) 430px;

    gap: 22px;

    overflow: hidden;

}


/* =========================================================
   LEFT SIDE
========================================================= */

.left-panel {

    min-width: 0;

    min-height: 0;

    height: 100%;

    display: flex;

    flex-direction: column;

    gap: 14px;

}

/* =========================================================
   SECTION TITLE
========================================================= */

.section-title {

    font-size: 14px;

    font-weight: 800;

    color: #198754;

    letter-spacing: .3px;

    margin-bottom: 7px;

}


/* =========================================================
   SEARCH
========================================================= */

.search-container {

    position: relative;

}

.search-input {

    width: 100%;

    height: 62px;

    border: 2px solid #21a65a;

    border-radius: 9px;

    background: white;

    padding: 0 58px 0 58px;

    font-size: 18px;

    outline: none;

    box-shadow: 0 2px 6px rgba(0,0,0,.04);

}

.search-input:focus {

    border-color: #087f3d;

    box-shadow:
        0 0 0 3px rgba(25,135,84,.12);

}

.search-icon-left {

    position: absolute;

    left: 20px;

    top: 50%;

    transform: translateY(-50%);

    color: #159447;

    font-size: 25px;

}

.search-icon-right {

    position: absolute;

    right: 19px;

    top: 50%;

    transform: translateY(-50%);

    color: #159447;

    font-size: 25px;

}


/* =========================================================
   TRANSACTION
========================================================= */

.transaction-title {

    margin-top: 2px;

}

.transaction-box {

    flex: 1;

    min-height: 0;

    background: white;

    border: 1px solid #dce2e6;

    border-radius: 9px;

    overflow: hidden;

    display: flex;

    flex-direction: column;

}

/* =========================================================
   TRANSACTION SECTION
========================================================= */

.transaction-section {

    flex: 1;

    min-height: 0;

    display: flex;

    flex-direction: column;

}


/* =========================================================
   TRANSACTION BOX
========================================================= */

.transaction-box {

    flex: 1;

    min-height: 0;

    background: white;

    border: 1px solid #dce2e6;

    border-radius: 9px;

    overflow: hidden;

    display: flex;

    flex-direction: column;

}


/* =========================================================
   FIXED TABLE HEADER
========================================================= */

.transaction-header-table {

    flex-shrink: 0;

}

.transaction-header-table th {

    background: #f3f6f8;

}


/* =========================================================
   SCROLLABLE CART BODY
========================================================= */

.cart-body-scroll {

    flex: 1;

    min-height: 0;

    overflow-y: auto;

    overflow-x: hidden;

}


/* =========================================================
   CUSTOM SCROLLBAR
========================================================= */

.cart-body-scroll::-webkit-scrollbar {

    width: 8px;

}

.cart-body-scroll::-webkit-scrollbar-track {

    background: #f1f3f5;

}

.cart-body-scroll::-webkit-scrollbar-thumb {

    background: #b7c2c9;

    border-radius: 10px;

}

.cart-body-scroll::-webkit-scrollbar-thumb:hover {

    background: #8d9aa3;

}


/* =========================================================
   TABLE WIDTH FIX
========================================================= */

.transaction-table {

    width: 100%;

    border-collapse: collapse;

    table-layout: fixed;

}


/* SAME COLUMN WIDTHS */

.transaction-table th:nth-child(1),
.transaction-table td:nth-child(1) {

    width: 16%;

}


.transaction-table th:nth-child(2),
.transaction-table td:nth-child(2) {

    width: 36%;

}


.transaction-table th:nth-child(3),
.transaction-table td:nth-child(3) {

    width: 15%;

    text-align: center;

}


.transaction-table th:nth-child(4),
.transaction-table td:nth-child(4) {

    width: 15%;

    text-align: right;

}


.transaction-table th:nth-child(5),
.transaction-table td:nth-child(5) {

    width: 14%;

    text-align: right;

}


.transaction-table th:nth-child(6),
.transaction-table td:nth-child(6) {

    width: 8%;

    text-align: center;

}


/* =========================================================
   TABLE
========================================================= */

.transaction-table {

    width: 100%;

    border-collapse: collapse;

    table-layout: fixed;

}

.transaction-table thead {

    background: #f3f6f8;

}

.transaction-table th {

    height: 45px;

    padding: 0 20px;

    text-align: left;

    font-size: 12px;

    color: #34495e;

    font-weight: 800;

    border-bottom: 1px solid #dce2e6;

}

.transaction-table th:nth-child(1) {

    width: 16%;

}

.transaction-table th:nth-child(2) {

    width: 36%;

}

.transaction-table th:nth-child(3) {

    width: 15%;

    text-align: center;

}

.transaction-table th:nth-child(4) {

    width: 15%;

    text-align: right;

}

.transaction-table th:nth-child(5) {

    width: 14%;

    text-align: right;

}

.transaction-table th:nth-child(6) {

    width: 8%;

    text-align: center;

}

.transaction-table td {

    padding: 12px 20px;

    height: 60px;

    border-bottom: 1px solid #edf0f2;

    font-size: 15px;

    font-weight: 600;

}

.transaction-table td:nth-child(3) {

    text-align: center;

}

.transaction-table td:nth-child(4),
.transaction-table td:nth-child(5) {

    text-align: right;

}

.transaction-table td:nth-child(6) {

    text-align: center;

}

.transaction-code {

    font-weight: 800;

}

.transaction-product {

    font-weight: 700;

}

.transaction-total {

    color: #198754;

    font-weight: 800;

}


/* =========================================================
   QUANTITY CONTROL
========================================================= */

.qty-control {

    display: inline-flex;

    align-items: center;

    border: 1px solid #d8dee3;

    border-radius: 7px;

    overflow: hidden;

}

.qty-btn {

    width: 34px;

    height: 34px;

    border: 0;

    background: white;

    color: #198754;

    font-size: 18px;

    cursor: pointer;

}

.qty-btn:hover {

    background: #edf8f1;

}

.qty-number {

    width: 36px;

    text-align: center;

    font-size: 14px;

    border-left: 1px solid #e0e4e7;

    border-right: 1px solid #e0e4e7;

    line-height: 34px;

}


/* =========================================================
   DELETE BUTTON
========================================================= */

.delete-btn {

    border: 0;

    background: transparent;

    color: #dc3545;

    font-size: 20px;

    cursor: pointer;

}

.delete-btn:hover {

    color: #a71d2a;

}


/* =========================================================
   EMPTY TRANSACTION
========================================================= */
/* =========================================================
   TRANSACTION
========================================================= */

.transaction-box {
    flex: 1;
    min-height: 0;

    background: white;

    border: 1px solid #dce2e6;

    border-radius: 9px;

    overflow: hidden;

    display: flex;

    flex-direction: column;
}


/* TABLE CONTAINER */

.transaction-scroll {

    flex: 1;

    min-height: 0;

    overflow-y: auto;

    overflow-x: hidden;

}


/* SCROLLBAR */

.transaction-scroll::-webkit-scrollbar {
    width: 8px;
}

.transaction-scroll::-webkit-scrollbar-track {
    background: #f1f3f5;
}

.transaction-scroll::-webkit-scrollbar-thumb {
    background: #b8c2c9;
    border-radius: 10px;
}

.transaction-scroll::-webkit-scrollbar-thumb:hover {
    background: #8f9aa3;
}


/* =========================================================
   TABLE
========================================================= */

.transaction-table {

    width: 100%;

    border-collapse: separate;

    border-spacing: 0;

    table-layout: fixed;

}


/* FIXED HEADER */

.transaction-table thead {

    position: sticky;

    top: 0;

    z-index: 20;

    background: #f3f6f8;

}


/* TABLE HEADER */

.transaction-table th {

    height: 45px;

    padding: 0 20px;

    text-align: left;

    font-size: 12px;

    color: #34495e;

    font-weight: 800;

    border-bottom: 1px solid #dce2e6;

    background: #f3f6f8;

}


/* COLUMN WIDTH */

.transaction-table th:nth-child(1) {
    width: 16%;
}

.transaction-table th:nth-child(2) {
    width: 36%;
}

.transaction-table th:nth-child(3) {
    width: 15%;
    text-align: center;
}

.transaction-table th:nth-child(4) {
    width: 15%;
    text-align: right;
}

.transaction-table th:nth-child(5) {
    width: 14%;
    text-align: right;
}

.transaction-table th:nth-child(6) {
    width: 8%;
    text-align: center;
}


/* TABLE BODY */

.transaction-table td {

    padding: 12px 20px;

    height: 60px;

    border-bottom: 1px solid #edf0f2;

    font-size: 15px;

    font-weight: 600;

}


/* ALIGNMENT */

.transaction-table td:nth-child(3) {
    text-align: center;
}

.transaction-table td:nth-child(4),
.transaction-table td:nth-child(5) {
    text-align: right;
}

.transaction-table td:nth-child(6) {
    text-align: center;
}


/* HOVER */

.transaction-table tbody tr:hover {
    background: #f7fbf8;
}


/* =========================================================
   CASHIER SESSION
========================================================= */

.session-box {

    background: white;

    border: 1px solid #25a55b;

    border-radius: 9px;

    min-height: 130px;

    padding: 18px 24px;

    display: grid;

    grid-template-columns: 1.4fr 1fr 1fr 1fr;

    align-items: center;

}

.session-main {

    border-right: 1px solid #dce2e6;

    padding-right: 20px;

}

.session-title {

    color: #198754;

    font-size: 15px;

    font-weight: 800;

    margin-bottom: 6px;

}

.session-status {

    color: #198754;

    font-size: 20px;

    font-weight: 800;

}

.session-status i {

    font-size: 20px;

}

.session-opening {

    margin-top: 8px;

    font-size: 13px;

    color: #52616b;

}

.session-opening strong {

    color: #198754;

}

.session-info {

    padding: 0 20px;

    border-right: 1px solid #dce2e6;

}

.session-info:last-child {

    border-right: 0;

}

.session-info-label {

    font-size: 12px;

    color: #53606b;

    margin-bottom: 5px;

}

.session-info-value {

    font-size: 14px;

    font-weight: 800;

}

.session-info-value.green {

    color: #198754;

}


/* =========================================================
   RIGHT PAYMENT PANEL
========================================================= */

.payment-panel {

    background: white;

    border: 1px solid #dce2e6;

    border-radius: 9px;

    padding: 28px 26px;

    display: flex;

    flex-direction: column;

    min-height: 0;

    box-shadow: 0 2px 7px rgba(0,0,0,.04);

}


/* =========================================================
   TOTALS
========================================================= */

.summary-row {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 17px;

    font-size: 14px;

    font-weight: 700;

}

.summary-row strong {

    font-size: 16px;

}

.summary-divider {

    height: 1px;

    background: #dce2e6;

    margin: 5px 0 20px;

}

.total-row {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 24px;

}

.total-label {

    font-size: 22px;

    font-weight: 900;

}

.total-value {

    font-size: 32px;

    color: #159447;

    font-weight: 900;

}


/* =========================================================
   PAYMENT
========================================================= */

.form-label {

    display: block;

    font-size: 13px;

    font-weight: 800;

    margin-bottom: 8px;

}

.payment-select {

    width: 100%;

    height: 54px;

    border: 1px solid #d5dce1;

    border-radius: 8px;

    padding: 0 15px;

    background: white;

    font-size: 16px;

    outline: none;

    margin-bottom: 20px;

}

.cash-input {

    width: 100%;

    height: 62px;

    border: 1px solid #d5dce1;

    border-radius: 8px;

    padding: 0 20px;

    font-size: 21px;

    outline: none;

    margin-bottom: 20px;

}

.cash-input:focus {

    border-color: #198754;

    box-shadow:
        0 0 0 3px rgba(25,135,84,.1);

}


/* =========================================================
   CHANGE
========================================================= */

.change-box {

    border: 1px solid #36b56d;

    background: #effaf3;

    border-radius: 8px;

    height: 82px;

    display: flex;

    align-items: center;

    justify-content: center;

    margin-bottom: 22px;

}

.change-value {

    font-size: 32px;

    font-weight: 900;

    color: #159447;

}


/* =========================================================
   BUTTONS
========================================================= */

.complete-btn {

    width: 100%;

    height: 65px;

    border: 0;

    border-radius: 8px;

    background: #159447;

    color: white;

    font-size: 18px;

    font-weight: 900;

    cursor: pointer;

    margin-bottom: 14px;

}

.complete-btn:hover {

    background: #087f3d;

}

.complete-btn:disabled {

    background: #9db9a9;

    cursor: not-allowed;

}

.clear-btn {

    width: 100%;

    height: 58px;

    border: 1px solid #dc3545;

    border-radius: 8px;

    background: white;

    color: #dc3545;

    font-size: 16px;

    font-weight: 900;

    cursor: pointer;

}

.clear-btn:hover {

    background: #fff1f2;

}


/* =========================================================
   SEARCH RESULT
========================================================= */

.search-result {

    position: absolute;

    top: 68px;

    left: 0;

    right: 0;

    background: white;

    border: 1px solid #d8dee3;

    border-radius: 8px;

    box-shadow: 0 8px 20px rgba(0,0,0,.12);

    z-index: 999;

    display: none;

    max-height: 300px;

    overflow-y: auto;

}

.search-result-item {

    padding: 14px 18px;

    border-bottom: 1px solid #edf0f2;

    cursor: pointer;

    display: flex;

    justify-content: space-between;

    align-items: center;

}

.search-result-item:hover {

    background: #f1faf5;

}

.search-result-name {

    font-weight: 800;

}

.search-result-code {

    font-size: 12px;

    color: #69757d;

    margin-top: 3px;

}

.search-result-price {

    color: #198754;

    font-weight: 900;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1100px) {

    body {
        overflow: auto;
    }

    .pos-wrapper {

        height: auto;

        min-height: calc(100vh - 72px);

        grid-template-columns: 1fr;

    }

    .payment-panel {

        min-height: auto;

    }

}

@media (max-width: 700px) {

    .pos-header {

        padding: 0 15px;

    }

    .brand {

        font-size: 17px;

    }

    .cashier-info {

        display: none;

    }

    .pos-wrapper {

        padding: 14px;

    }

    .session-box {

        grid-template-columns: 1fr;

        gap: 15px;

    }

    .session-main,
    .session-info {

        border-right: 0;

        border-bottom: 1px solid #dce2e6;

        padding: 10px 0;

    }

}

</style>

</head>


<body>


<!-- =========================================================
     HEADER
========================================================= -->

<header class="pos-header">

    <div class="brand">

        <div class="brand-icon">

            <i class="bi bi-shop"></i>

        </div>

        MINI CANTEEN POS

    </div>


   <div class="header-right">

    <div class="cashier-info">

        <i class="bi bi-person-circle"></i>

        Cashier:

        <strong>
            <?= htmlspecialchars($username) ?>
        </strong>

    </div>


    <?php if ($role === 'admin'): ?>

        <button
            type="button"
            class="back-btn"
            onclick="window.location.href='../index.php'"
        >
            <i class="bi bi-arrow-left"></i>
            Back to Back Office
        </button>

    <?php endif; ?>


    <button 
        type="button"
        class="logout-btn" 
        onclick="window.location.href='../logout.php'"
    >

        <i class="bi bi-box-arrow-right"></i>

        Logout

    </button>

</div>

</header>



<!-- =========================================================
     POS CONTENT
========================================================= -->

<main class="pos-wrapper">


<!-- =========================================================
     LEFT PANEL
========================================================= -->

<section class="left-panel">


    <!-- SEARCH -->

    <div>

        <div class="section-title">

            BARCODE / ITEM SEARCH

        </div>


        <div class="search-container">

            <i
                class="bi bi-upc-scan search-icon-left"
            ></i>


            <input
                type="text"
                id="searchProduct"
                class="search-input"
                autocomplete="off"
                placeholder="Scan barcode or enter product code..."
                autofocus
            >


            <i
                class="bi bi-search search-icon-right"
            ></i>


            <div
                id="searchResults"
                class="search-result"
            ></div>

        </div>

    </div>



    <!-- TRANSACTION -->

    <div
        style="
            flex:1;
            min-height:0;
            display:flex;
            flex-direction:column;
        "
    >

        <!-- TRANSACTION -->

<div class="transaction-section">

    <div class="section-title transaction-title">
        TRANSACTION
    </div>

    <div class="transaction-box">

        <!-- FIXED TABLE HEADER -->

        <table class="transaction-table transaction-header-table">

            <thead>

                <tr>

                    <th>CODE</th>

                    <th>PRODUCT</th>

                    <th>QTY</th>

                    <th>PRICE</th>

                    <th>TOTAL</th>

                    <th>ACTION</th>

                </tr>

            </thead>

        </table>


        <!-- SCROLLABLE CART BODY -->

        <div class="cart-body-scroll">

            <table class="transaction-table">

                <tbody id="cartTableBody">

                </tbody>

            </table>


            <div
                id="emptyTransaction"
                class="empty-transaction"
            >

                <i class="bi bi-cart3"></i>

                <span>
                    No items in transaction
                </span>

            </div>

        </div>

    </div>

</div>
    </div>



    <!-- CASHIER SESSION -->

    <div class="session-box">

        <div class="session-main">

            <div class="session-title">

            <button
            type="button"
            class="back-btn"
            onclick="window.location.href='../pos/open_session.php'"
        >
            <i class="bi bi-wallet"></i>
            OPEN SESSION
        


                <i class="bi bi-check-circle-fill"></i>

                CASHIER SESSION

            </div>


            <?php if ($hasSession): ?>

                <div class="session-status">

                    Session Open

                </div>


                <div class="session-opening">

                    Opening Cash:

                    <strong>
                        ₱<?= number_format($cashierSession['opening_cash'], 2) ?>
                    </strong>

                </div>

            <?php else: ?>

                <div
                    class="session-status"
                    style="color:#dc3545"
                >

                    Session Closed

                </div>


                <div class="session-opening">

                    Open a cashier session before selling.

                </div>

            <?php endif; ?>

        </div>


        <div class="session-info">

            <div class="session-info-label">

                <i class="bi bi-calendar3"></i>

                Opened At

            </div>

            <div class="session-info-value">

                <?php if ($hasSession): ?>

                    <?= date(
                        'M d, Y',
                        strtotime($cashierSession['opened_at'])
                    ) ?>

                    <br>

                    <?= date(
                        'h:i A',
                        strtotime($cashierSession['opened_at'])
                    ) ?>

                <?php else: ?>

                    —

                <?php endif; ?>

            </div>

        </div>


        <div class="session-info">

            <div class="session-info-label">

                <i class="bi bi-person"></i>

                Session ID

            </div>

            <div class="session-info-value">

                <?= $hasSession
                    ? '#' . htmlspecialchars($cashierSession['id'])
                    : '—'
                ?>

            </div>

        </div>


        <div class="session-info">

            <div class="session-info-label">

                <i class="bi bi-cash-stack"></i>

                Status

            </div>

            <div
                class="session-info-value
                <?= $hasSession ? 'green' : '' ?>"
            >

                <?= $hasSession ? 'OPEN' : 'CLOSED' ?>

            </div>

        </div>

    </div>

</section>



<!-- =========================================================
     PAYMENT PANEL
========================================================= -->

<aside class="payment-panel">


    <!-- SUBTOTAL -->

    <div class="summary-row">

        <span>SUBTOTAL</span>

        <strong id="subtotal">
            ₱0.00
        </strong>

    </div>


    <!-- DISCOUNT -->

    <div class="summary-row">

        <span>DISCOUNT</span>

        <strong id="discount">
            ₱0.00
        </strong>

    </div>


    <div class="summary-divider"></div>


    <!-- TOTAL -->

    <div class="total-row">

        <span class="total-label">

            TOTAL

        </span>

        <span
            class="total-value"
            id="total"
        >

            ₱0.00

        </span>

    </div>


    <!-- PAYMENT -->

    <label class="form-label">

        PAYMENT METHOD

    </label>


    <select
        id="paymentMethod"
        class="payment-select"
    >

        <option value="CASH">
            💵 CASH
        </option>

        <option value="GCASH">
            📱 GCASH
        </option>

        <option value="CARD">
            💳 CARD
        </option>

        <option value="OTHER">
            OTHER
        </option>

    </select>


    <!-- CASH -->

    <label class="form-label">

        CASH

    </label>


    <input
        type="number"
        id="amountTendered"
        class="cash-input"
        step="0.01"
        min="0"
        placeholder="0.00"
    >


    <!-- CHANGE -->

    <label class="form-label">

        CHANGE

    </label>


    <div class="change-box">

        <span
            id="change"
            class="change-value"
        >

            ₱0.00

        </span>

    </div>


    <!-- BUTTONS -->

    <div style="margin-top:auto;">

        <button
            class="complete-btn"
            id="completeSaleBtn"
            onclick="processSale()"
            <?= !$hasSession ? 'disabled' : '' ?>
        >

            <i class="bi bi-check-circle-fill"></i>

            COMPLETE SALE

        </button>


        <button
            class="clear-btn"
            onclick="clearCart()"
        >

            <i class="bi bi-trash3"></i>

            CLEAR SALE

        </button>

    </div>

</aside>


</main>



<script>

/* =========================================================
   PRODUCTS FROM PHP
========================================================= */

const products = <?= json_encode(
    $products,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
) ?>;


/* =========================================================
   CART
========================================================= */

let cart = [];


/* =========================================================
   ELEMENTS
========================================================= */

const searchInput =
    document.getElementById('searchProduct');

const searchResults =
    document.getElementById('searchResults');

const cartTableBody =
    document.getElementById('cartTableBody');

const emptyTransaction =
    document.getElementById('emptyTransaction');

const amountTendered =
    document.getElementById('amountTendered');

const paymentMethod =
    document.getElementById('paymentMethod');


/* =========================================================
   SEARCH PRODUCT
========================================================= */

searchInput.addEventListener(
    'input',
    function() {

        const keyword =
            this.value
            .trim()
            .toLowerCase();


        if (keyword === '') {

            searchResults.style.display = 'none';

            return;

        }


        const matches =
            products.filter(product => {

                return (

                    String(
                        product.product_name || ''
                    )
                    .toLowerCase()
                    .includes(keyword)

                    ||

                    String(
                        product.product_code || ''
                    )
                    .toLowerCase()
                    .includes(keyword)

                    ||

                    String(
                        product.barcode || ''
                    )
                    .toLowerCase()
                    .includes(keyword)

                );

            })
            .slice(0, 10);


        renderSearchResults(matches);

    }
);


/* =========================================================
   BARCODE SCANNER
========================================================= */

searchInput.addEventListener(
    'keydown',
    function(event) {

        if (event.key !== 'Enter') {

            return;

        }


        event.preventDefault();


        const keyword =
            this.value
            .trim()
            .toLowerCase();


        if (!keyword) {

            return;

        }


        /*
        Exact barcode / product code
        */

        const exact =
            products.find(product => {

                return (

                    String(product.barcode || '')
                        .toLowerCase() === keyword

                    ||

                    String(product.product_code || '')
                        .toLowerCase() === keyword

                );

            });


        if (exact) {

            addToCart(exact);

            searchInput.value = '';

            searchResults.style.display = 'none';

            return;

        }


        /*
        Exact product name
        */

        const exactName =
            products.find(product => {

                return String(
                    product.product_name || ''
                )
                .toLowerCase() === keyword;

            });


        if (exactName) {

            addToCart(exactName);

            searchInput.value = '';

            searchResults.style.display = 'none';

            return;

        }


        alert(
            'Product not found.'
        );

    }
);


/* =========================================================
   SEARCH RESULTS
========================================================= */

function renderSearchResults(matches)
{

    if (matches.length === 0) {

        searchResults.innerHTML = `

            <div
                style="
                    padding:18px;
                    text-align:center;
                    color:#777;
                "
            >

                Product not found

            </div>

        `;

        searchResults.style.display = 'block';

        return;

    }


    let html = '';


    matches.forEach(product => {

        html += `

            <div
                class="search-result-item"
                onclick="selectSearchProduct(${product.id})"
            >

                <div>

                    <div class="search-result-name">

                        ${escapeHtml(
                            product.product_name
                        )}

                    </div>

                    <div class="search-result-code">

                        ${escapeHtml(
                            product.product_code
                        )}

                        ${
                            product.barcode
                            ? ' • ' +
                              escapeHtml(product.barcode)
                            : ''
                        }

                    </div>

                </div>


                <div class="search-result-price">

                    ₱${Number(
                        product.selling_price
                    ).toFixed(2)}

                </div>

            </div>

        `;

    });


    searchResults.innerHTML = html;

    searchResults.style.display = 'block';

}


/* =========================================================
   SELECT SEARCH PRODUCT
========================================================= */

function selectSearchProduct(id)
{

    const product =
        products.find(
            p => Number(p.id) === Number(id)
        );


    if (!product) {

        return;

    }


    addToCart(product);

    searchInput.value = '';

    searchResults.style.display = 'none';

    searchInput.focus();

}


/* =========================================================
   ADD TO CART
========================================================= */

function addToCart(product)
{

    const stock =
        parseFloat(product.stock) || 0;


    if (stock <= 0) {

        alert(
            product.product_name +
            ' is out of stock.'
        );

        return;

    }


    const existing =
        cart.find(
            item =>
                Number(item.id) ===
                Number(product.id)
        );


    if (existing) {

        if (
            existing.quantity >=
            existing.stock
        ) {

            alert(
                'Not enough stock available.'
            );

            return;

        }


        existing.quantity++;

    } else {

        cart.push({

            id: Number(product.id),

            code: product.product_code,

            name: product.product_name,

            price:
                parseFloat(
                    product.selling_price
                ) || 0,

            cost_price:
                parseFloat(
                    product.cost_price
                ) || 0,

            quantity: 1,

            stock: stock

        });

    }


    renderCart();

    searchInput.focus();

}


/* =========================================================
   RENDER CART
========================================================= */

function renderCart()
{

    if (cart.length === 0) {

        cartTableBody.innerHTML = '';

        emptyTransaction.style.display = 'flex';

        updateTotals();

        return;

    }


    emptyTransaction.style.display = 'none';


    let html = '';


    cart.forEach((item, index) => {

        const itemTotal =
            item.price *
            item.quantity;


        html += `

            <tr>

                <td>

                    <span class="transaction-code">

                        ${escapeHtml(
                            item.code
                        )}

                    </span>

                </td>


                <td>

                    <span class="transaction-product">

                        ${escapeHtml(
                            item.name
                        )}

                    </span>

                </td>


                <td>

                    <div class="qty-control">

                        <button
                            class="qty-btn"
                            onclick="changeQty(
                                ${index},
                                -1
                            )"
                        >
                            −
                        </button>


                        <div class="qty-number">

                            ${item.quantity}

                        </div>


                        <button
                            class="qty-btn"
                            onclick="changeQty(
                                ${index},
                                1
                            )"
                        >
                            +
                        </button>

                    </div>

                </td>


                <td>

                    ₱${item.price.toFixed(2)}

                </td>


                <td>

                    <span class="transaction-total">

                        ₱${itemTotal.toFixed(2)}

                    </span>

                </td>


                <td>

                    <button
                        class="delete-btn"
                        onclick="removeItem(${index})"
                        title="Remove item"
                    >

                        <i class="bi bi-trash3"></i>

                    </button>

                </td>

            </tr>

        `;

    });


    cartTableBody.innerHTML = html;

    updateTotals();

}


/* =========================================================
   CHANGE QUANTITY
========================================================= */

function changeQty(index, amount)
{

    const item = cart[index];

    if (!item) {

        return;

    }


    const newQty =
        item.quantity + amount;


    if (newQty <= 0) {

        cart.splice(index, 1);

    }

    else if (
        newQty > item.stock
    ) {

        alert(
            'Not enough stock available.'
        );

        return;

    }

    else {

        item.quantity = newQty;

    }


    renderCart();

}


/* =========================================================
   REMOVE
========================================================= */

function removeItem(index)
{

    cart.splice(index, 1);

    renderCart();

    searchInput.focus();

}


/* =========================================================
   CLEAR
========================================================= */

function clearCart()
{

    if (cart.length === 0) {

        return;

    }


    if (
        !confirm(
            'Clear current sale?'
        )
    ) {

        return;

    }


    cart = [];

    amountTendered.value = '';

    renderCart();

    searchInput.focus();

}


/* =========================================================
   TOTALS
========================================================= */

function updateTotals()
{

    let subtotal = 0;


    cart.forEach(item => {

        subtotal +=
            item.price *
            item.quantity;

    });


    const discount = 0;

    const total =
        subtotal -
        discount;


    document.getElementById(
        'subtotal'
    ).innerText =
        '₱' +
        subtotal.toFixed(2);


    document.getElementById(
        'discount'
    ).innerText =
        '₱' +
        discount.toFixed(2);


    document.getElementById(
        'total'
    ).innerText =
        '₱' +
        total.toFixed(2);


    calculateChange();

}


/* =========================================================
   CHANGE
========================================================= */

amountTendered.addEventListener(
    'input',
    calculateChange
);


paymentMethod.addEventListener(
    'change',
    function() {

        if (this.value !== 'CASH') {

            amountTendered.value = '';

            amountTendered.placeholder =
                'Not required';

        } else {

            amountTendered.placeholder =
                '0.00';

        }


        calculateChange();

    }
);


function calculateChange()
{

    let subtotal = 0;


    cart.forEach(item => {

        subtotal +=
            item.price *
            item.quantity;

    });


    const tendered =
        parseFloat(
            amountTendered.value
        ) || 0;


    const method =
        paymentMethod.value;


    let change = 0;


    if (method === 'CASH') {

        change =
            tendered -
            subtotal;

        if (change < 0) {

            change = 0;

        }

    }


    document.getElementById(
        'change'
    ).innerText =
        '₱' +
        change.toFixed(2);

}


/* =========================================================
   PROCESS SALE
========================================================= */

function processSale()
{

    if (cart.length === 0) {

        alert(
            'Transaction is empty.'
        );

        searchInput.focus();

        return;

    }


    let total = 0;


    cart.forEach(item => {

        total +=
            item.price *
            item.quantity;

    });


    const method =
        paymentMethod.value;


    let tendered =
        parseFloat(
            amountTendered.value
        ) || 0;


    if (
        method === 'CASH' &&
        tendered < total
    ) {

        alert(
            'Cash received is not enough.'
        );

        amountTendered.focus();

        return;

    }


    if (method !== 'CASH') {

        tendered = total;

    }


    const confirmed =
        confirm(
            'Complete this sale for ₱' +
            total.toFixed(2) +
            '?'
        );


    if (!confirmed) {

        return;

    }


    const form =
        document.createElement('form');


    form.method = 'POST';

    form.action =
        'process_sale.php';


    const cartInput =
        document.createElement('input');

    cartInput.type = 'hidden';

    cartInput.name = 'cart';

    cartInput.value =
        JSON.stringify(cart);

    form.appendChild(cartInput);


    const paymentInput =
        document.createElement('input');

    paymentInput.type = 'hidden';

    paymentInput.name =
        'payment_method';

    paymentInput.value =
        method;

    form.appendChild(paymentInput);


    const tenderedInput =
        document.createElement('input');

    tenderedInput.type = 'hidden';

    tenderedInput.name =
        'amount_tendered';

    tenderedInput.value =
        tendered;

    form.appendChild(tenderedInput);


    document.body.appendChild(form);

    form.submit();

}


/* =========================================================
   ESCAPE HTML
========================================================= */

function escapeHtml(value)
{

    return String(value ?? '')
        .replace(
            /&/g,
            '&amp;'
        )
        .replace(
            /</g,
            '&lt;'
        )
        .replace(
            />/g,
            '&gt;'
        )
        .replace(
            /"/g,
            '&quot;'
        )
        .replace(
            /'/g,
            '&#039;'
        );

}


/* =========================================================
   CLOSE SEARCH WHEN CLICK OUTSIDE
========================================================= */

document.addEventListener(
    'click',
    function(event) {

        if (
            !event.target.closest(
                '.search-container'
            )
        ) {

            searchResults.style.display =
                'none';

        }

    }
);


/* =========================================================
   INITIAL
========================================================= */

renderCart();

searchInput.focus();

</script>

</body>

</html>