<?php

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$sale_id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($sale_id <= 0) {
    die("Invalid transaction.");
}


/*
|--------------------------------------------------------------------------
| Get Sale
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        s.*,
        u.full_name AS cashier_name
    FROM sales s
    INNER JOIN users u
        ON u.id = s.cashier_id
    WHERE s.id = ?
    LIMIT 1
");

$stmt->execute([$sale_id]);

$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    die("Transaction not found.");
}


/*
|--------------------------------------------------------------------------
| Get Items
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        si.*,
        p.product_code,
        p.barcode,
        p.unit
    FROM sale_items si
    INNER JOIN products p
        ON p.id = si.product_id
    WHERE si.sale_id = ?
    ORDER BY si.id ASC
");

$stmt->execute([$sale_id]);

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
Transaction <?= htmlspecialchars(
    $sale['transaction_no']
) ?>
</title>

<style>

body {
    margin: 0;
    background: #f4f6f9;
    font-family: Arial, sans-serif;
}

.container {
    max-width: 1000px;
    margin: 30px auto;
    padding: 0 20px;
}

.card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.07);
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.header h1 {
    margin: 0;
}

.btn {
    display: inline-block;
    padding: 10px 15px;
    border-radius: 6px;
    text-decoration: none;
    border: none;
    cursor: pointer;
}

.btn-back {
    background: #6c757d;
    color: white;
}

.btn-print {
    background: #198754;
    color: white;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.info-box {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 7px;
}

.label {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 5px;
}

.value {
    font-weight: bold;
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
}

td {
    padding: 12px;
    border-bottom: 1px solid #dee2e6;
}

.text-right {
    text-align: right;
}

.total {
    font-size: 20px;
    font-weight: bold;
}

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.completed {
    background: #d1e7dd;
    color: #0f5132;
}

.void {
    background: #f8d7da;
    color: #842029;
}

.refunded {
    background: #fff3cd;
    color: #664d03;
}

@media (max-width: 700px) {

    .info-grid {
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

    <div class="card">

        <div class="header">

            <div>

                <h1>
                    Transaction Details
                </h1>

                <p>

                    <?= htmlspecialchars(
                        $sale['transaction_no']
                    ) ?>

                </p>

            </div>

            <div>

                <a
                    href="index.php"
                    class="btn btn-back"
                >
                    ← Back
                </a>

                <a
                    href="../pos/receipt.php?id=<?= $sale['id'] ?>"
                    class="btn btn-print"
                    target="_blank"
                >
                    🖨 Receipt
                </a>

            </div>

        </div>

    </div>

    <?php if (
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'admin' &&
    $sale['status'] === 'COMPLETED'
): ?>

    <a
        href="void.php?id=<?= $sale['id'] ?>"
        class="btn btn-void"
    >
        ⚠ Void
    </a>

<?php endif; ?>


    <!-- TRANSACTION INFO -->

    <div class="card">

        <div class="info-grid">

            <div class="info-box">

                <div class="label">
                    Transaction No.
                </div>

                <div class="value">
                    <?= htmlspecialchars(
                        $sale['transaction_no']
                    ) ?>
                </div>

            </div>


            <div class="info-box">

                <div class="label">
                    Date
                </div>

                <div class="value">

                    <?= date(
                        'M d, Y h:i A',
                        strtotime(
                            $sale['transaction_date']
                        )
                    ) ?>

                </div>

            </div>


            <div class="info-box">

                <div class="label">
                    Cashier
                </div>

                <div class="value">

                    <?= htmlspecialchars(
                        $sale['cashier_name']
                    ) ?>

                </div>

            </div>


            <div class="info-box">

                <div class="label">
                    Payment Method
                </div>

                <div class="value">

                    <?= htmlspecialchars(
                        $sale['payment_method']
                    ) ?>

                </div>

            </div>


            <div class="info-box">

                <div class="label">
                    Status
                </div>

                <div class="value">

                    <?php

                    $class = 'completed';

                    if ($sale['status'] === 'VOID') {
                        $class = 'void';
                    }

                    if ($sale['status'] === 'REFUNDED') {
                        $class = 'refunded';
                    }

                    ?>

                    <span class="status <?= $class ?>">

                        <?= htmlspecialchars(
                            $sale['status']
                        ) ?>

                    </span>

                </div>

            </div>


            <div class="info-box">

                <div class="label">
                    Session ID
                </div>

                <div class="value">

                    <?= $sale['cashier_session_id']
                        ? '#' . $sale['cashier_session_id']
                        : 'N/A'
                    ?>

                </div>

            </div>

        </div>

    </div>


    <!-- ITEMS -->

    <div class="card">

        <h2>
            Items
        </h2>

        <table>

            <thead>

                <tr>

                    <th>
                        Product
                    </th>

                    <th>
                        Qty
                    </th>

                    <th>
                        Selling Price
                    </th>

                    <th>
                        Discount
                    </th>

                    <th class="text-right">
                        Subtotal
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php foreach ($items as $item): ?>

                <tr>

                    <td>

                        <strong>

                            <?= htmlspecialchars(
                                $item['product_name']
                            ) ?>

                        </strong>

                        <br>

                        <small>

                            <?= htmlspecialchars(
                                $item['product_code']
                            ) ?>

                        </small>

                    </td>


                    <td>

                        <?= number_format(
                            $item['quantity'],
                            3
                        ) ?>

                        <?= htmlspecialchars(
                            $item['unit']
                        ) ?>

                    </td>


                    <td>

                        ₱<?= number_format(
                            $item['selling_price'],
                            2
                        ) ?>

                    </td>


                    <td>

                        ₱<?= number_format(
                            $item['discount'],
                            2
                        ) ?>

                    </td>


                    <td class="text-right">

                        ₱<?= number_format(
                            $item['subtotal'],
                            2
                        ) ?>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>


    <!-- PAYMENT SUMMARY -->

    <div class="card">

        <div style="max-width:400px;margin-left:auto;">

            <p>
                Subtotal:
                <strong style="float:right;">
                    ₱<?= number_format(
                        $sale['subtotal'],
                        2
                    ) ?>
                </strong>
            </p>

            <p>
                Discount:
                <strong style="float:right;">
                    ₱<?= number_format(
                        $sale['discount'],
                        2
                    ) ?>
                </strong>
            </p>

            <p>
                Tax:
                <strong style="float:right;">
                    ₱<?= number_format(
                        $sale['tax'],
                        2
                    ) ?>
                </strong>
            </p>

            <hr>

            <p class="total">

                TOTAL:

                <span style="float:right;">

                    ₱<?= number_format(
                        $sale['total_amount'],
                        2
                    ) ?>

                </span>

            </p>

            <p>
                Amount Tendered:
                <strong style="float:right;">
                    ₱<?= number_format(
                        $sale['amount_tendered'],
                        2
                    ) ?>
                </strong>
            </p>

            <p>
                Change:
                <strong style="float:right;">
                    ₱<?= number_format(
                        $sale['change_amount'],
                        2
                    ) ?>
                </strong>
            </p>

        </div>

    </div>


    <?php if ($sale['status'] === 'VOID'): ?>

        <div class="card">

            <strong>
                Void Reason:
            </strong>

            <p>
                <?= htmlspecialchars(
                    $sale['void_reason'] ?? 'No reason provided.'
                ) ?>
            </p>

        </div>

    <?php endif; ?>


</div>

</body>

</html>