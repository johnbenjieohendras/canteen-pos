<?php

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$role = $_SESSION['role'] ?? '';

/*
|--------------------------------------------------------------------------
| ADMIN ONLY
|--------------------------------------------------------------------------
*/

if ($role !== 'admin') {
    header("Location: ../index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| GET CURRENT SETTINGS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT *
    FROM receipt_settings
    ORDER BY id ASC
    LIMIT 1
");

$settings = $stmt->fetch();

if (!$settings) {

    $stmt = $pdo->prepare("
        INSERT INTO receipt_settings
        (
            company_name,
            address,
            contact_number,
            receipt_header,
            receipt_footer
        )
        VALUES
        (
            :company_name,
            :address,
            :contact_number,
            :receipt_header,
            :receipt_footer
        )
    ");

    $stmt->execute([
        ':company_name'   => 'Canteen POS',
        ':address'        => '',
        ':contact_number' => '',
        ':receipt_header' => 'OFFICIAL RECEIPT',
        ':receipt_footer' => 'Thank you! Please come again.'
    ]);

    $stmt = $pdo->query("
        SELECT *
        FROM receipt_settings
        ORDER BY id ASC
        LIMIT 1
    ");

    $settings = $stmt->fetch();
}


/*
|--------------------------------------------------------------------------
| UPDATE SETTINGS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $company_name = trim($_POST['company_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $receipt_header = trim($_POST['receipt_header'] ?? '');
    $receipt_footer = trim($_POST['receipt_footer'] ?? '');

    $show_company_name =
        isset($_POST['show_company_name']) ? 1 : 0;

    $show_address =
        isset($_POST['show_address']) ? 1 : 0;

    $show_contact =
        isset($_POST['show_contact']) ? 1 : 0;

    $show_cashier =
        isset($_POST['show_cashier']) ? 1 : 0;

    $show_transaction_no =
        isset($_POST['show_transaction_no']) ? 1 : 0;

    $show_date_time =
        isset($_POST['show_date_time']) ? 1 : 0;

    $show_payment_method =
        isset($_POST['show_payment_method']) ? 1 : 0;

    $show_amount_tendered =
        isset($_POST['show_amount_tendered']) ? 1 : 0;

    $show_change =
        isset($_POST['show_change']) ? 1 : 0;


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($company_name === '') {

        $error = "Company / Brand Name is required.";

    } else {

        $stmt = $pdo->prepare("
            UPDATE receipt_settings

            SET
                company_name = :company_name,
                address = :address,
                contact_number = :contact_number,
                receipt_header = :receipt_header,
                receipt_footer = :receipt_footer,

                show_company_name = :show_company_name,
                show_address = :show_address,
                show_contact = :show_contact,
                show_cashier = :show_cashier,
                show_transaction_no = :show_transaction_no,
                show_date_time = :show_date_time,
                show_payment_method = :show_payment_method,
                show_amount_tendered = :show_amount_tendered,
                show_change = :show_change

            WHERE id = :id
        ");

        $stmt->execute([

            ':company_name' =>
                $company_name,

            ':address' =>
                $address,

            ':contact_number' =>
                $contact_number,

            ':receipt_header' =>
                $receipt_header,

            ':receipt_footer' =>
                $receipt_footer,

            ':show_company_name' =>
                $show_company_name,

            ':show_address' =>
                $show_address,

            ':show_contact' =>
                $show_contact,

            ':show_cashier' =>
                $show_cashier,

            ':show_transaction_no' =>
                $show_transaction_no,

            ':show_date_time' =>
                $show_date_time,

            ':show_payment_method' =>
                $show_payment_method,

            ':show_amount_tendered' =>
                $show_amount_tendered,

            ':show_change' =>
                $show_change,

            ':id' =>
                $settings['id']
        ]);


        /*
        |--------------------------------------------------------------------------
        | REFRESH
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query("
            SELECT *
            FROM receipt_settings
            WHERE id = " . (int)$settings['id'] . "
            LIMIT 1
        ");

        $settings = $stmt->fetch();

        $success = "Receipt settings successfully updated.";
    }
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
    Receipt Settings | Canteen POS
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

    max-width: 1100px;

    margin: auto;

    padding: 30px;
}


/* HEADER */

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


/* BUTTON */

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

.btn-primary {

    background: #2563eb;

    color: white;
}

.btn-primary:hover {

    background: #1d4ed8;
}


/* CARD */

.card {

    background: white;

    border-radius: 12px;

    padding: 25px;

    margin-bottom: 20px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,0.05);
}

.card-title {

    font-size: 18px;

    font-weight: 700;

    margin-bottom: 20px;
}


/* FORM */

.form-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 18px;
}

.form-group {

    display: flex;

    flex-direction: column;
}

.form-group.full {

    grid-column: 1 / -1;
}

.form-group label {

    font-size: 13px;

    font-weight: 600;

    margin-bottom: 7px;
}

.form-group input,
.form-group textarea {

    width: 100%;

    padding: 11px 12px;

    border: 1px solid #d1d5db;

    border-radius: 8px;

    font-size: 14px;

    outline: none;
}

.form-group textarea {

    min-height: 90px;

    resize: vertical;
}

.form-group input:focus,
.form-group textarea:focus {

    border-color: #2563eb;

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.10);
}


/* CHECKBOX */

.options {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 12px;
}

.option {

    display: flex;

    align-items: center;

    gap: 10px;

    padding: 12px;

    border: 1px solid #e5e7eb;

    border-radius: 8px;

    background: #fafafa;
}

.option input {

    width: 17px;

    height: 17px;

    cursor: pointer;
}

.option label {

    cursor: pointer;

    font-size: 14px;
}


/* ALERT */

.alert {

    padding: 13px 15px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-size: 14px;
}

.alert-success {

    background: #dcfce7;

    color: #166534;

    border: 1px solid #bbf7d0;
}

.alert-error {

    background: #fee2e2;

    color: #991b1b;

    border: 1px solid #fecaca;
}


/* SAVE */

.form-footer {

    display: flex;

    justify-content: flex-end;

    gap: 10px;

    margin-top: 25px;

    padding-top: 20px;

    border-top: 1px solid #e5e7eb;
}


/* PREVIEW */

.receipt-preview {

    max-width: 320px;

    margin: auto;

    background: white;

    border: 1px solid #d1d5db;

    padding: 20px;

    font-family:
        "Courier New",
        monospace;

    font-size: 12px;

    line-height: 1.5;
}

.receipt-center {

    text-align: center;
}

.receipt-company {

    font-size: 18px;

    font-weight: bold;

    margin-bottom: 4px;
}

.receipt-line {

    border-top:
        1px dashed #000;

    margin: 10px 0;
}

.receipt-row {

    display: flex;

    justify-content: space-between;
}

.receipt-total {

    font-size: 14px;

    font-weight: bold;

    margin-top: 5px;
}


/* MOBILE */

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

    .form-grid,
    .options {

        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>

<div class="page">


<!-- HEADER -->

<div class="page-header">

    <div>

        <h1>
            🧾 Receipt Settings
        </h1>

        <p>
            Customize the information displayed on printed receipts.
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


<?php if (isset($success)): ?>

    <div class="alert alert-success">

        ✓ <?= htmlspecialchars($success) ?>

    </div>

<?php endif; ?>


<?php if (isset($error)): ?>

    <div class="alert alert-error">

        ⚠ <?= htmlspecialchars($error) ?>

    </div>

<?php endif; ?>


<form method="POST">


<!-- =========================================================
     COMPANY INFORMATION
========================================================= -->

<div class="card">

    <div class="card-title">

        🏢 Company / Brand Information

    </div>


    <div class="form-grid">


        <div class="form-group">

            <label>
                Company / Brand Name *
            </label>

            <input
                type="text"
                name="company_name"
                required
                maxlength="150"
                value="<?= htmlspecialchars(
                    $settings['company_name'] ?? ''
                ) ?>"
                placeholder="Example: ABC Canteen"
            >

        </div>


        <div class="form-group">

            <label>
                Contact Number
            </label>

            <input
                type="text"
                name="contact_number"
                maxlength="50"
                value="<?= htmlspecialchars(
                    $settings['contact_number'] ?? ''
                ) ?>"
                placeholder="Example: 09123456789"
            >

        </div>


        <div class="form-group full">

            <label>
                Address
            </label>

            <input
                type="text"
                name="address"
                maxlength="255"
                value="<?= htmlspecialchars(
                    $settings['address'] ?? ''
                ) ?>"
                placeholder="Example: ABC School, Cebu City"
            >

        </div>


    </div>

</div>


<!-- =========================================================
     RECEIPT TEXT
========================================================= -->

<div class="card">

    <div class="card-title">

        🧾 Receipt Text

    </div>


    <div class="form-grid">


        <div class="form-group">

            <label>
                Receipt Header
            </label>

            <input
                type="text"
                name="receipt_header"
                maxlength="255"
                value="<?= htmlspecialchars(
                    $settings['receipt_header'] ?? ''
                ) ?>"
                placeholder="Example: OFFICIAL RECEIPT"
            >

        </div>


        <div class="form-group">

            <label>
                Receipt Footer
            </label>

            <input
                type="text"
                name="receipt_footer"
                maxlength="255"
                value="<?= htmlspecialchars(
                    $settings['receipt_footer'] ?? ''
                ) ?>"
                placeholder="Thank you! Please come again."
            >

        </div>


    </div>

</div>


<!-- =========================================================
     DISPLAY OPTIONS
========================================================= -->

<div class="card">

    <div class="card-title">

        ⚙ Receipt Display Options

    </div>


    <div class="options">


        <div class="option">

            <input
                type="checkbox"
                id="show_company_name"
                name="show_company_name"
                <?= !empty(
                    $settings['show_company_name']
                )
                    ? 'checked'
                    : ''
                ?>
            >

            <label for="show_company_name">
                Show Company / Brand Name
            </label>

        </div>


        <div class="option">

            <input
                type="checkbox"
                id="show_address"
                name="show_address"
                <?= !empty(
                    $settings['show_address']
                )
                    ? 'checked'
                    : ''
                ?>
            >

            <label for="show_address">
                Show Address
            </label>

        </div>


        <div class="option">

            <input
                type="checkbox"
                id="show_contact"
                name="show_contact"
                <?= !empty(
                    $settings['show_contact']
                )
                    ? 'checked'
                    : ''
                ?>
            >

            <label for="show_contact">
                Show Contact Number
            </label>

        </div>


        <div class="option">

            <input
                type="checkbox"
                id="show_cashier"
                name="show_cashier"
                <?= !empty(
                    $settings['show_cashier']
                )
                    ? 'checked'
                    : ''
                ?>
            >

            <label for="show_cashier">
                Show Cashier
            </label>

        </div>


        <div class="option">

            <input
                type="checkbox"
                id="show_transaction_no"
                name="show_transaction_no"
                <?= !empty(
                    $settings['show_transaction_no']
                )
                    ? 'checked'
                    : ''
                ?>
            >

            <label for="show_transaction_no">
                Show Transaction Number
            </label>

        </div>


        <div class="option">

            <input
                type="checkbox"
                id="show_date_time"
                name="show_date_time"
                <?= !empty(
                    $settings['show_date_time']
                )
                    ? 'checked'
                    : ''
                ?>
            >

            <label for="show_date_time">
                Show Date & Time
            </label>

        </div>


        <div class="option">

            <input
                type="checkbox"
                id="show_payment_method"
                name="show_payment_method"
                <?= !empty(
                    $settings['show_payment_method']
                )
                    ? 'checked'
                    : ''
                ?>
            >

            <label for="show_payment_method">
                Show Payment Method
            </label>

        </div>


        <div class="option">

            <input
                type="checkbox"
                id="show_amount_tendered"
                name="show_amount_tendered"
                <?= !empty(
                    $settings['show_amount_tendered']
                )
                    ? 'checked'
                    : ''
                ?>
            >

            <label for="show_amount_tendered">
                Show Amount Tendered
            </label>

        </div>


        <div class="option">

            <input
                type="checkbox"
                id="show_change"
                name="show_change"
                <?= !empty(
                    $settings['show_change']
                )
                    ? 'checked'
                    : ''
                ?>
            >

            <label for="show_change">
                Show Change
            </label>

        </div>


    </div>


    <div class="form-footer">

        <a
            href="../index.php"
            class="btn btn-back"
        >
            Cancel
        </a>

        <button
            type="submit"
            class="btn btn-primary"
        >
            💾 Save Receipt Settings
        </button>

    </div>

</div>


</form>


<!-- =========================================================
     RECEIPT PREVIEW
========================================================= -->

<div class="card">

    <div class="card-title">

        👁 Receipt Preview

    </div>


    <div class="receipt-preview">


        <?php if (
            !empty($settings['show_company_name'])
        ): ?>

            <div class="receipt-center">

                <div class="receipt-company">

                    <?= htmlspecialchars(
                        $settings['company_name']
                    ) ?>

                </div>

            </div>

        <?php endif; ?>


        <?php if (
            !empty($settings['show_address']) &&
            !empty($settings['address'])
        ): ?>

            <div class="receipt-center">

                <?= htmlspecialchars(
                    $settings['address']
                ) ?>

            </div>

        <?php endif; ?>


        <?php if (
            !empty($settings['show_contact']) &&
            !empty($settings['contact_number'])
        ): ?>

            <div class="receipt-center">

                Tel:
                <?= htmlspecialchars(
                    $settings['contact_number']
                ) ?>

            </div>

        <?php endif; ?>


        <?php if (
            !empty($settings['receipt_header'])
        ): ?>

            <div class="receipt-center">

                <?= htmlspecialchars(
                    $settings['receipt_header']
                ) ?>

            </div>

        <?php endif; ?>


        <div class="receipt-line"></div>


        <?php if (
            !empty($settings['show_transaction_no'])
        ): ?>

            <div>
                Transaction:
                #000001
            </div>

        <?php endif; ?>


        <?php if (
            !empty($settings['show_date_time'])
        ): ?>

            <div>
                Date:
                <?= date('M d, Y h:i A') ?>
            </div>

        <?php endif; ?>


        <?php if (
            !empty($settings['show_cashier'])
        ): ?>

            <div>
                Cashier:
                Sample Cashier
            </div>

        <?php endif; ?>


        <div class="receipt-line"></div>


        <div class="receipt-row">

            <span>
                Coke 1.5L x1
            </span>

            <span>
                55.00
            </span>

        </div>


        <div class="receipt-row">

            <span>
                Water x1
            </span>

            <span>
                15.00
            </span>

        </div>


        <div class="receipt-line"></div>


        <div class="receipt-row receipt-total">

            <span>
                TOTAL
            </span>

            <span>
                ₱70.00
            </span>

        </div>


        <?php if (
            !empty($settings['show_payment_method'])
        ): ?>

            <div class="receipt-row">

                <span>
                    Payment
                </span>

                <span>
                    CASH
                </span>

            </div>

        <?php endif; ?>


        <?php if (
            !empty($settings['show_amount_tendered'])
        ): ?>

            <div class="receipt-row">

                <span>
                    Tendered
                </span>

                <span>
                    ₱100.00
                </span>

            </div>

        <?php endif; ?>


        <?php if (
            !empty($settings['show_change'])
        ): ?>

            <div class="receipt-row">

                <span>
                    Change
                </span>

                <span>
                    ₱30.00
                </span>

            </div>

        <?php endif; ?>


        <div class="receipt-line"></div>


        <?php if (
            !empty($settings['receipt_footer'])
        ): ?>

            <div class="receipt-center">

                <?= nl2br(
                    htmlspecialchars(
                        $settings['receipt_footer']
                    )
                ) ?>

            </div>

        <?php endif; ?>


    </div>

</div>


</div>

</body>

</html>