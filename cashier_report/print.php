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
| CASHIER ACCESS
|--------------------------------------------------------------------------
*/

if ($role === 'cashier') {
    header("Location: ../pos/index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| GET SESSION
|--------------------------------------------------------------------------
*/

$session_id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$session_id) {
    die('Invalid cashier session.');
}


/*
|--------------------------------------------------------------------------
| GET SESSION DETAILS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        cs.id,
        cs.user_id,
        cs.opening_cash,
        cs.closing_cash,
        cs.expected_cash,
        cs.cash_difference,
        cs.opened_at,
        cs.closed_at,
        cs.status,
        cs.notes,
        u.full_name,
        u.username
    FROM cashier_sessions cs
    INNER JOIN users u
        ON u.id = cs.user_id
    WHERE cs.id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $session_id
]);

$session = $stmt->fetch();

if (!$session) {
    die('Cashier session not found.');
}


/*
|--------------------------------------------------------------------------
| SALES SUMMARY
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        COUNT(*) AS transactions,

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

    WHERE cashier_session_id = :session_id

    AND status = 'COMPLETED'
");

$stmt->execute([
    ':session_id' => $session_id
]);

$sales = $stmt->fetch();


/*
|--------------------------------------------------------------------------
| EXPENSES
|--------------------------------------------------------------------------
|
| Your current expenses table does not have
| cashier_session_id.
|
| We therefore use the cashier user and the
| session date/time.
|
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        expense_no,
        expense_category,
        description,
        amount,
        expense_date
    FROM expenses

    WHERE user_id = :user_id

    AND expense_date >= :opened_at

    AND expense_date <= COALESCE(
        :closed_at,
        NOW()
    )

    ORDER BY expense_date ASC
");

$stmt->execute([
    ':user_id'   => $session['user_id'],
    ':opened_at' => $session['opened_at'],
    ':closed_at' => $session['closed_at']
]);

$expenses = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| EXPENSE TOTAL
|--------------------------------------------------------------------------
*/

$total_expenses = 0;

foreach ($expenses as $expense) {

    $total_expenses +=
        (float) $expense['amount'];
}


/*
|--------------------------------------------------------------------------
| VALUES
|--------------------------------------------------------------------------
*/

$transactions =
    (int) ($sales['transactions'] ?? 0);

$total_sales =
    (float) ($sales['total_sales'] ?? 0);

$cash_sales =
    (float) ($sales['cash_sales'] ?? 0);

$gcash_sales =
    (float) ($sales['gcash_sales'] ?? 0);

$card_sales =
    (float) ($sales['card_sales'] ?? 0);

$other_sales =
    (float) ($sales['other_sales'] ?? 0);

$opening_cash =
    (float) $session['opening_cash'];

$expected_cash =
    $session['expected_cash'] !== null
        ? (float) $session['expected_cash']
        : 0;

$closing_cash =
    $session['closing_cash'] !== null
        ? (float) $session['closing_cash']
        : null;

$cash_difference =
    $session['cash_difference'] !== null
        ? (float) $session['cash_difference']
        : null;

$net_sales =
    $total_sales - $total_expenses;


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function money($amount)
{
    return '₱' . number_format(
        (float) $amount,
        2
    );
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
    Cashier Report #<?= $session['id'] ?>
</title>


<style>

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

body {

    margin: 0;

    padding: 20px;

    background: #eeeeee;

    font-family:
        "Courier New",
        monospace;

    color: #000;

}


/*
|--------------------------------------------------------------------------
| RECEIPT
|--------------------------------------------------------------------------
*/

.receipt {

    width: 80mm;

    max-width: 80mm;

    margin: 0 auto;

    background: #fff;

    padding: 12px;

    box-sizing: border-box;

}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.center {

    text-align: center;

}


.title {

    font-size: 18px;

    font-weight: bold;

}


.subtitle {

    font-size: 13px;

    margin-top: 3px;

}


/*
|--------------------------------------------------------------------------
| DIVIDER
|--------------------------------------------------------------------------
*/

.divider {

    border-top:
        1px dashed #000;

    margin:
        10px 0;

}


/*
|--------------------------------------------------------------------------
| INFO
|--------------------------------------------------------------------------
*/

.info {

    font-size: 12px;

    line-height: 1.6;

}


.info-row {

    display: flex;

    justify-content: space-between;

    gap: 10px;

}


.info-row span:last-child {

    text-align: right;

}


/*
|--------------------------------------------------------------------------
| SECTION
|--------------------------------------------------------------------------
*/

.section-title {

    font-weight: bold;

    font-size: 13px;

    margin:
        8px 0 5px;

}


/*
|--------------------------------------------------------------------------
| AMOUNTS
|--------------------------------------------------------------------------
*/

.amount-row {

    display: flex;

    justify-content: space-between;

    font-size: 12px;

    line-height: 1.7;

}


.amount-row.total {

    font-weight: bold;

    font-size: 14px;

}


.amount-row.net {

    font-weight: bold;

    font-size: 15px;

}


/*
|--------------------------------------------------------------------------
| EXPENSES
|--------------------------------------------------------------------------
*/

.expense-item {

    font-size: 11px;

    margin-bottom: 7px;

}


.expense-description {

    color: #333;

}


/*
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
*/

.footer {

    text-align: center;

    font-size: 11px;

    margin-top: 12px;

    line-height: 1.5;

}


/*
|--------------------------------------------------------------------------
| PRINT BUTTONS
|--------------------------------------------------------------------------
*/

.print-buttons {

    width: 80mm;

    margin: 0 auto 15px;

    display: flex;

    gap: 8px;

}


.print-buttons button {

    flex: 1;

    padding: 10px;

    border: none;

    border-radius: 6px;

    cursor: pointer;

    font-family: Arial, sans-serif;

    font-weight: bold;

}


.btn-print {

    background: #2563eb;

    color: #fff;

}


.btn-back {

    background: #ddd;

    color: #222;

}


/*
|--------------------------------------------------------------------------
| PRINT
|--------------------------------------------------------------------------
*/

@media print {

    @page {

        size: 80mm auto;

        margin: 0;

    }

    body {

        background: #fff;

        padding: 0;

    }

    .receipt {

        width: 80mm;

        max-width: 80mm;

        margin: 0;

        padding: 8px;

    }

    .print-buttons {

        display: none;

    }

}

</style>

</head>


<body>


<!-- ========================================================
     BUTTONS
======================================================== -->

<div class="print-buttons">

    <button
        class="btn-back"
        onclick="window.location.href='index.php'"
    >
        ← Back
    </button>

    <button
        class="btn-print"
        onclick="window.print()"
    >
        🖨 Print
    </button>

</div>


<!-- ========================================================
     RECEIPT
======================================================== -->

<div class="receipt">


<!-- HEADER -->

<div class="center">

    <div class="title">
       ADCOM MINI CANTEEN
    </div>

    <div class="subtitle">
        CASHIER REPORT
    </div>

</div>


<div class="divider"></div>


<!-- SESSION INFO -->

<div class="info">

    <div class="info-row">

        <span>
            Report No.
        </span>

        <span>
            #<?= $session['id'] ?>
        </span>

    </div>


    <div class="info-row">

        <span>
            Cashier
        </span>

        <span>
            <?= htmlspecialchars(
                $session['full_name']
            ) ?>
        </span>

    </div>


    <div class="info-row">

        <span>
            Username
        </span>

        <span>
            <?= htmlspecialchars(
                $session['username']
            ) ?>
        </span>

    </div>


    <div class="info-row">

        <span>
            Status
        </span>

        <span>
            <?= strtoupper(
                $session['status']
            ) ?>
        </span>

    </div>


    <div class="info-row">

        <span>
            Opened
        </span>

        <span>
            <?= date(
                'M d, Y h:i A',
                strtotime(
                    $session['opened_at']
                )
            ) ?>
        </span>

    </div>


    <?php if ($session['closed_at']): ?>

        <div class="info-row">

            <span>
                Closed
            </span>

            <span>
                <?= date(
                    'M d, Y h:i A',
                    strtotime(
                        $session['closed_at']
                    )
                ) ?>
            </span>

        </div>

    <?php endif; ?>


</div>


<div class="divider"></div>


<!-- SALES -->

<div class="section-title">

    SALES SUMMARY

</div>


<div class="amount-row">

    <span>
        Transactions
    </span>

    <span>
        <?= number_format(
            $transactions
        ) ?>
    </span>

</div>


<div class="amount-row">

    <span>
        Cash Sales
    </span>

    <span>
        <?= money($cash_sales) ?>
    </span>

</div>


<div class="amount-row">

    <span>
        GCash Sales
    </span>

    <span>
        <?= money($gcash_sales) ?>
    </span>

</div>


<div class="amount-row">

    <span>
        Card Sales
    </span>

    <span>
        <?= money($card_sales) ?>
    </span>

</div>


<div class="amount-row">

    <span>
        Other Sales
    </span>

    <span>
        <?= money($other_sales) ?>
    </span>

</div>


<div class="divider"></div>


<div class="amount-row total">

    <span>
        TOTAL SALES
    </span>

    <span>
        <?= money($total_sales) ?>
    </span>

</div>


<div class="divider"></div>


<!-- CASH -->

<div class="section-title">

    CASH DRAWER

</div>


<div class="amount-row">

    <span>
        Opening Cash
    </span>

    <span>
        <?= money($opening_cash) ?>
    </span>

</div>


<div class="amount-row">

    <span>
        Expected Cash
    </span>

    <span>
        <?= money($expected_cash) ?>
    </span>

</div>


<div class="amount-row">

    <span>
        Actual Closing
    </span>

    <span>

        <?php if ($closing_cash !== null): ?>

            <?= money($closing_cash) ?>

        <?php else: ?>

            --

        <?php endif; ?>

    </span>

</div>


<div class="amount-row">

    <span>
        Difference
    </span>

    <span>

        <?php if ($cash_difference !== null): ?>

            <?= money($cash_difference) ?>

        <?php else: ?>

            --

        <?php endif; ?>

    </span>

</div>


<div class="divider"></div>


<!-- EXPENSES -->

<div class="section-title">

    EXPENSES

</div>


<?php if (count($expenses) > 0): ?>

    <?php foreach ($expenses as $expense): ?>

        <div class="expense-item">

            <div class="amount-row">

                <span>
                    <?= htmlspecialchars(
                        $expense['expense_category']
                    ) ?>
                </span>

                <span>
                    <?= money(
                        $expense['amount']
                    ) ?>
                </span>

            </div>


            <?php if (
                !empty(
                    $expense['description']
                )
            ): ?>

                <div class="expense-description">

                    <?= htmlspecialchars(
                        $expense['description']
                    ) ?>

                </div>

            <?php endif; ?>

        </div>

    <?php endforeach; ?>

<?php else: ?>

    <div class="expense-item">

        No expenses recorded.

    </div>

<?php endif; ?>


<div class="amount-row total">

    <span>
        TOTAL EXPENSES
    </span>

    <span>
        <?= money(
            $total_expenses
        ) ?>
    </span>

</div>


<div class="divider"></div>


<!-- NET -->

<div class="amount-row net">

    <span>
        NET SALES
    </span>

    <span>
        <?= money(
            $net_sales
        ) ?>
    </span>

</div>


<div class="divider"></div>


<!-- FOOTER -->

<div class="footer">

    CANTEEN POS<br>

    Cashier Session Report<br><br>

    Thank you!

</div>


</div>


</body>

</html>