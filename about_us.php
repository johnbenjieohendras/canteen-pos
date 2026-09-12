<?php

session_start();

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>About Us - Mini Canteen POS</title>

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

    color: #212529;

}


/* =========================================================
   PAGE
   ========================================================= */

.page {

    min-height: 100vh;

    padding: 30px;

    padding-bottom: 70px;

}


/* =========================================================
   TOP BAR
   ========================================================= */

.topbar {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;

}

.topbar h1 {

    margin: 0;

    font-size: 25px;

}

.topbar small {

    color: #777;

}

.back-btn {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 10px 15px;

    background: #343a40;

    color: white;

    text-decoration: none;

    border-radius: 7px;

    font-size: 14px;

}


/* =========================================================
   HERO
   ========================================================= */

.about-hero {

    background:
        linear-gradient(
            135deg,
            #212529,
            #343a40
        );

    color: white;

    border-radius: 15px;

    padding: 45px 30px;

    text-align: center;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,.08);

}

.logo {

    width: 85px;

    height: 85px;

    margin: auto;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 20px;

    background: rgba(255,255,255,.12);

    font-size: 42px;

}

.about-hero h2 {

    margin: 20px 0 8px;

    font-size: 32px;

}

.about-hero p {

    margin: 0;

    color: #d9dde1;

}


/* =========================================================
   FEATURE CARDS
   ========================================================= */

.features {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(190px, 1fr)
        );

    gap: 18px;

    margin-top: 22px;

}

.feature {

    background: white;

    border-radius: 12px;

    padding: 22px;

    text-align: center;

    box-shadow:
        0 2px 10px
        rgba(0,0,0,.05);

}

.feature i {

    font-size: 30px;

    color: #343a40;

}

.feature h3 {

    font-size: 16px;

    margin: 12px 0 7px;

}

.feature p {

    margin: 0;

    font-size: 13px;

    color: #777;

}


/* =========================================================
   ABOUT CONTENT
   ========================================================= */

.content {

    background: white;

    margin-top: 22px;

    padding: 30px;

    border-radius: 12px;

    box-shadow:
        0 2px 10px
        rgba(0,0,0,.05);

}

.content h2 {

    margin-top: 0;

}

.content p {

    color: #666;

    line-height: 1.7;

}


/* =========================================================
   SYSTEM INFO
   ========================================================= */

.system-info {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(180px, 1fr)
        );

    gap: 15px;

    margin-top: 20px;

}

.info {

    background: #f8f9fa;

    padding: 16px;

    border-radius: 8px;

}

.info span {

    display: block;

    color: #888;

    font-size: 12px;

    margin-bottom: 5px;

}

.info strong {

    font-size: 14px;

}


/* =========================================================
   WATERMARK
   ========================================================= */

.watermark {

    position: fixed;

    top: 50%;

    left: 50%;

    transform:
        translate(-50%, -50%)
        rotate(-25deg);

    font-size: 90px;

    font-weight: 800;

    letter-spacing: 8px;

    color: rgba(0,0,0,.025);

    pointer-events: none;

    user-select: none;

    z-index: 0;

}


/* =========================================================
   FOOTER
   ========================================================= */

.footer {

    position: fixed;

    bottom: 0;

    left: 0;

    right: 0;

    height: 38px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 22px;

    background: white;

    border-top: 1px solid #e9ecef;

    color: #888;

    font-size: 12px;

    z-index: 1000;

}

.footer strong {

    color: #495057;

}

@media(max-width:700px) {

    .page {

        padding: 15px;

    }

    .topbar {

        flex-direction: column;

        align-items: flex-start;

        gap: 12px;

    }

    .footer {

        justify-content: center;

    }

    .footer div:last-child {

        display: none;

    }

}

</style>

</head>

<body>

<div class="watermark">
    johnbenjieohendras19@gmail.com
</div>


<div class="page">

    <!-- TOP BAR -->

    <div class="topbar">

        <div>

            <h1>
                <i class="bi bi-info-circle"></i>
                About Us
            </h1>

            <small>
                System information
            </small>

        </div>


        <a
            href="index.php"
            class="back-btn"
        >

            <i class="bi bi-arrow-left"></i>

            Back

        </a>

    </div>


    <!-- HERO -->

    <div class="about-hero">

        <div class="logo">

            <i class="bi bi-shop"></i>

        </div>


        <h2>
            Mini Canteen POS
        </h2>


        <p>
            Inventory & Sales Management System
        </p>

    </div>


    <!-- FEATURES -->

    <div class="features">


        <div class="feature">

            <i class="bi bi-cart-check"></i>

            <h3>
                Point of Sale
            </h3>

            <p>
                Fast and organized transaction processing.
            </p>

        </div>


        <div class="feature">

            <i class="bi bi-box-seam"></i>

            <h3>
                Inventory
            </h3>

            <p>
                Monitor products, stock levels and movements.
            </p>

        </div>


        <div class="feature">

            <i class="bi bi-cash-stack"></i>

            <h3>
                Cashier
            </h3>

            <p>
                Manage cashier sessions and cash transactions.
            </p>

        </div>


        <div class="feature">

            <i class="bi bi-bar-chart"></i>

            <h3>
                Reports
            </h3>

            <p>
                View sales, expenses and operational reports.
            </p>

        </div>


        <div class="feature">

            <i class="bi bi-clock-history"></i>

            <h3>
                Activity Log
            </h3>

            <p>
                Monitor important activities performed in the system.
            </p>

        </div>


        <div class="feature">

            <i class="bi bi-shield-check"></i>

            <h3>
                Security
            </h3>

            <p>
                Role-based access for administrators and staff.
            </p>

        </div>

    </div>


    <!-- ABOUT -->

    <div class="content">

        <h2>
            About the System
        </h2>

        <p>

            Canteen POS is an integrated
            Point of Sale and Inventory
            Management System designed to
            help manage daily canteen
            operations efficiently.

        </p>

        <p>

            The system provides tools for
            sales processing, inventory
            management, cashier sessions,
            expense monitoring, reporting,
            and activity tracking.

        </p>
        
        <h2>Developed by:
John Benjie Ohendras</h2>

        <div class="system-info">


            <div class="info">

                <span>
                    Application
                </span>

                <strong>
                    Canteen POS
                </strong>

            </div>


            <div class="info">

                <span>
                    Version
                </span>

                <strong>
                    1.0.0
                </strong>

            </div>


            <div class="info">

                <span>
                    Platform
                </span>

                <strong>
                    Windows Desktop
                </strong>

            </div>


            <div class="info">

                <span>
                    Database
                </span>

                <strong>
                    MySQL / MariaDB
                </strong>

            </div>


            <div class="info">

                <span>
                    Application Type
                </span>

                <strong>
                    POS & Inventory System
                </strong>

            </div>


            <div class="info">

                <span>
                    Current User
                </span>

                <strong>
                    <?= htmlspecialchars($username) ?>
                </strong>

            </div>

        </div>

    </div>

</div>


<!-- FOOTER -->

<footer class="footer">

    <div>

        © 2026

        <strong>
           Mini Canteen POS
        </strong>

        • Inventory & Sales Management System

    </div>


    <div>

        All Rights Reserved

    </div>

</footer>


</body>

</html>