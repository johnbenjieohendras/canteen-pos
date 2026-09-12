<?php

$currentPage = basename($_SERVER['PHP_SELF']);

?>

<aside class="sidebar">

    <div class="sidebar-brand">

        <div class="brand-icon">
            <i class="bi bi-shop"></i>
        </div>

        <div>
            <div class="brand-title">
                Canteen POS
            </div>

            <small>
                Back Office
            </small>
        </div>

    </div>


    <!-- USER -->

    <div class="sidebar-user">

        <div class="user-avatar">
            <?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?>
        </div>

        <div class="user-info">

            <strong>
                <?= htmlspecialchars($_SESSION['full_name']) ?>
            </strong>

            <small>
                <?= ucfirst(htmlspecialchars($_SESSION['role'])) ?>
            </small>

        </div>

    </div>


    <!-- MENU -->

    <div class="menu-title">
        MAIN MENU
    </div>


    <nav class="sidebar-menu">

        <a
            href="/canteen_pos/index.php"
            class="<?= $currentPage === 'index.php' ? 'active' : '' ?>"
        >
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>


        <a href="/canteen_pos/products/index.php">

            <i class="bi bi-box-seam"></i>

            <span>
                Products
            </span>

        </a>


        <a href="/canteen_pos/categories/index.php">

            <i class="bi bi-tags"></i>

            <span>
                Categories
            </span>

        </a>


        <a href="/canteen_pos/inventory/index.php">

            <i class="bi bi-boxes"></i>

            <span>
                Inventory
            </span>

        </a>


        <a href="/canteen_pos/inventory/stock_in.php">

            <i class="bi bi-box-arrow-in-down"></i>

            <span>
                Stock In
            </span>

        </a>


        <a href="/canteen_pos/suppliers/index.php">

            <i class="bi bi-truck"></i>

            <span>
                Suppliers
            </span>

        </a>


        <div class="menu-title">
            SALES
        </div>


        <a href="/canteen_pos/pos/index.php">

            <i class="bi bi-cart3"></i>

            <span>
                Cashier / POS
            </span>

        </a>


        <a href="/canteen_pos/cashier_report/index.php">

            <i class="bi bi-wallet"></i>

            <span>
                Cashiers report
            </span>

        </a>


        <a href="/canteen_pos/sales/index.php">

            <i class="bi bi-receipt"></i>

            <span>
                Sales Transactions
            </span>

        </a>


        <a href="/canteen_pos/sales/reports.php">

            <i class="bi bi-bar-chart-line"></i>

            <span>
                Sales Report
            </span>

        </a>


        <div class="menu-title">
            MANAGEMENT
        </div>


        <a href="/canteen_pos/expenses/index.php">

            <i class="bi bi-wallet2"></i>

            <span>
                Expenses
            </span>

        </a>


        <a href="/canteen_pos/users/index.php">

            <i class="bi bi-people"></i>

            <span>
                Users
            </span>

        </a>
        
        <a href="/canteen_pos/receipt_settings/edit.php">

            <i class="bi bi-tools"></i>

            <span>
                Receipt settings
            </span>

        </a>
        
        <a href="/canteen_pos/about_us.php">

            <i class="bi bi-question"></i>

            <span>
                About us
            </span>

        </a>
        

    </nav>


    <!-- LOGOUT -->

    <div class="sidebar-bottom">

        <a
            href="/canteen_pos/logout.php"
            class="logout-link"
        >

            <i class="bi bi-box-arrow-right"></i>

            <span>
                Logout
            </span>

        </a>

    </div>

</aside>