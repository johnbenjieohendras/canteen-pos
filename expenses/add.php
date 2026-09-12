<?php

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

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
| GENERATE NEXT EXPENSE NUMBER
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT expense_no
    FROM expenses
    ORDER BY id DESC
    LIMIT 1
");

$last_expense = $stmt->fetch();

$next_number = 1;

if ($last_expense) {

    /*
    | Extract numeric part from EXP-000001
    */

    $last_number = (int) preg_replace(
        '/[^0-9]/',
        '',
        $last_expense['expense_no']
    );

    $next_number = $last_number + 1;
}

$expense_no = 'EXP-' . str_pad(
    $next_number,
    6,
    '0',
    STR_PAD_LEFT
);


/*
|--------------------------------------------------------------------------
| FORM DEFAULTS
|--------------------------------------------------------------------------
*/

$error = '';

$expense_category = '';
$description = '';
$amount = '';
$expense_date = date('Y-m-d\TH:i');


/*
|--------------------------------------------------------------------------
| SAVE EXPENSE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $expense_category = trim(
        $_POST['expense_category'] ?? ''
    );

    $description = trim(
        $_POST['description'] ?? ''
    );

    $amount = trim(
        $_POST['amount'] ?? ''
    );

    $expense_date = trim(
        $_POST['expense_date'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($expense_category === '') {

        $error = 'Please enter an expense category.';

    } elseif ($amount === '') {

        $error = 'Please enter the expense amount.';

    } elseif (!is_numeric($amount)) {

        $error = 'Please enter a valid expense amount.';

    } elseif ((float)$amount <= 0) {

        $error = 'Expense amount must be greater than zero.';

    } elseif ($expense_date === '') {

        $error = 'Please select the expense date.';

    }


    /*
    |--------------------------------------------------------------------------
    | INSERT
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $formatted_date = date(
            'Y-m-d H:i:s',
            strtotime($expense_date)
        );

        try {

            $stmt = $pdo->prepare("
                INSERT INTO expenses
                (
                    expense_no,
                    user_id,
                    expense_category,
                    description,
                    amount,
                    expense_date
                )
                VALUES
                (
                    :expense_no,
                    :user_id,
                    :expense_category,
                    :description,
                    :amount,
                    :expense_date
                )
            ");

            $stmt->execute([
                ':expense_no' =>
                    $expense_no,

                ':user_id' =>
                    $user_id,

                ':expense_category' =>
                    $expense_category,

                ':description' =>
                    $description !== ''
                        ? $description
                        : null,

                ':amount' =>
                    $amount,

                ':expense_date' =>
                    $formatted_date
            ]);


            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            header(
                "Location: index.php?success=added"
            );

            exit;


        } catch (PDOException $e) {

            /*
            | Handle duplicate expense number
            */

            if (
                $e->getCode() === '23000'
            ) {

                $error =
                    'Expense number already exists. Please try again.';

            } else {

                $error =
                    'Failed to save expense. Please try again.';

            }

        }

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
    Add Expense | Canteen POS
</title>


<style>

/* ==========================================================
   RESET
========================================================== */

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}


/* ==========================================================
   BODY
========================================================== */

body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        #f5f6f8;

    color:
        #1f2937;

}


/* ==========================================================
   PAGE
========================================================== */

.page {

    max-width:
        850px;

    margin:
        40px auto;

    padding:
        0 20px;

}


/* ==========================================================
   HEADER
========================================================== */

.page-header {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    margin-bottom:
        25px;

}


.page-header h1 {

    font-size:
        28px;

    font-weight:
        700;

}


.page-header p {

    color:
        #6b7280;

    margin-top:
        6px;

}


/* ==========================================================
   CARD
========================================================== */

.card {

    background:
        #ffffff;

    border-radius:
        12px;

    padding:
        30px;

    box-shadow:
        0 2px 10px
        rgba(0,0,0,0.05);

}


/* ==========================================================
   FORM
========================================================== */

.form-grid {

    display:
        grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap:
        20px;

}


.form-group {

    display:
        flex;

    flex-direction:
        column;

}


.form-group.full {

    grid-column:
        1 / -1;

}


label {

    font-size:
        14px;

    font-weight:
        600;

    margin-bottom:
        8px;

}


input,
textarea {

    width:
        100%;

    padding:
        12px 14px;

    border:
        1px solid #d1d5db;

    border-radius:
        8px;

    font-size:
        14px;

    font-family:
        inherit;

}


input:focus,
textarea:focus {

    outline:
        none;

    border-color:
        #2563eb;

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,0.10);

}


input[readonly] {

    background:
        #f9fafb;

    color:
        #6b7280;

}


textarea {

    min-height:
        120px;

    resize:
        vertical;

}


/* ==========================================================
   ERROR
========================================================== */

.alert-error {

    background:
        #fee2e2;

    color:
        #991b1b;

    border:
        1px solid #fecaca;

    padding:
        14px;

    border-radius:
        8px;

    margin-bottom:
        20px;

    font-size:
        14px;

}


/* ==========================================================
   BUTTONS
========================================================== */

.form-actions {

    display:
        flex;

    justify-content:
        flex-end;

    gap:
        10px;

    margin-top:
        30px;

}


.btn {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    padding:
        11px 20px;

    border-radius:
        8px;

    border:
        none;

    cursor:
        pointer;

    text-decoration:
        none;

    font-size:
        14px;

    font-weight:
        600;

}


.btn-primary {

    background:
        #2563eb;

    color:
        #ffffff;

}


.btn-primary:hover {

    background:
        #1d4ed8;

}


.btn-secondary {

    background:
        #e5e7eb;

    color:
        #374151;

}


.btn-secondary:hover {

    background:
        #d1d5db;

}


/* ==========================================================
   RESPONSIVE
========================================================== */

@media (max-width: 700px) {

    .form-grid {

        grid-template-columns:
            1fr;

    }

    .form-group.full {

        grid-column:
            auto;

    }

    .page-header {

        align-items:
            flex-start;

    }

    .form-actions {

        flex-direction:
            column-reverse;

    }

    .btn {

        width:
            100%;

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
            Add Expense
        </h1>

        <p>
            Record a new canteen expense.
        </p>

    </div>

</div>



<!-- ========================================================
     FORM CARD
======================================================== -->

<div class="card">


<?php if ($error !== ''): ?>

    <div class="alert-error">

        <?= htmlspecialchars($error) ?>

    </div>

<?php endif; ?>



<form method="POST">


<div class="form-grid">


<!-- ======================================================
     EXPENSE NUMBER
====================================================== -->

<div class="form-group">

    <label>
        Expense No.
    </label>

    <input
        type="text"
        value="<?= htmlspecialchars($expense_no) ?>"
        readonly
    >

</div>



<!-- ======================================================
     EXPENSE DATE
====================================================== -->

<div class="form-group">

    <label>
        Expense Date
    </label>

    <input
        type="datetime-local"
        name="expense_date"
        value="<?= htmlspecialchars($expense_date) ?>"
        required
    >

</div>



<!-- ======================================================
     CATEGORY
====================================================== -->

<div class="form-group full">

    <label>
        Expense Category
    </label>

    <input
        type="text"
        name="expense_category"
        placeholder="Example: Utilities, Supplies, Repairs"
        value="<?= htmlspecialchars($expense_category) ?>"
        required
    >

</div>



<!-- ======================================================
     AMOUNT
====================================================== -->

<div class="form-group">

    <label>
        Amount
    </label>

    <input
        type="number"
        name="amount"
        min="0.01"
        step="0.01"
        placeholder="0.00"
        value="<?= htmlspecialchars($amount) ?>"
        required
    >

</div>



<!-- ======================================================
     DESCRIPTION
====================================================== -->

<div class="form-group full">

    <label>
        Description
    </label>

    <textarea
        name="description"
        placeholder="Enter expense description..."
    ><?= htmlspecialchars($description) ?></textarea>

</div>


</div>



<!-- ======================================================
     ACTIONS
====================================================== -->

<div class="form-actions">


<a
    href="index.php"
    class="btn btn-secondary"
>
    Cancel
</a>


<button
    type="submit"
    class="btn btn-primary"
>
    Save Expense
</button>


</div>


</form>


</div>


</div>


</body>

</html>