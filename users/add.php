<?php

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username  = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role      = $_POST['role'] ?? 'cashier';
    $password  = $_POST['password'] ?? '';

    if (
        $username === '' ||
        $full_name === '' ||
        $password === ''
    ) {

        $error = 'Please complete all required fields.';

    } elseif (strlen($password) < 6) {

        $error = 'Password must be at least 6 characters.';

    } elseif (
        !in_array(
            $role,
            ['admin', 'cashier', 'inventory'],
            true
        )
    ) {

        $error = 'Invalid role.';

    } else {

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->execute([$username]);

        if ($stmt->fetch()) {

            $error = 'Username already exists.';

        } else {

            $password_hash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $pdo->prepare("
                INSERT INTO users
                (
                    username,
                    password,
                    full_name,
                    role,
                    status
                )
                VALUES (?, ?, ?, ?, 'active')
            ");

            $stmt->execute([
                $username,
                $password_hash,
                $full_name,
                $role
            ]);

            header("Location: index.php");
            exit;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Add User | Canteen POS</title>

<style>

body {
    margin: 0;
    background: #f4f6f9;
    font-family: Arial, sans-serif;
}

.container {
    max-width: 650px;
    margin: 50px auto;
    padding: 20px;
}

.card {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,.08);
}

h1 {
    margin-top: 0;
}

.form-group {
    margin-bottom: 18px;
}

label {
    display: block;
    margin-bottom: 7px;
    font-weight: bold;
}

input,
select {
    width: 100%;
    padding: 11px;
    border: 1px solid #ced4da;
    border-radius: 6px;
}

.btn {
    display: inline-block;
    padding: 10px 16px;
    border: none;
    border-radius: 6px;
    text-decoration: none;
    cursor: pointer;
}

.btn-primary {
    background: #0d6efd;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.error {
    background: #f8d7da;
    color: #842029;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.actions {
    display: flex;
    gap: 10px;
}

</style>

</head>

<body>

<div class="container">

<div class="card">

<h1>
    ➕ Add User
</h1>

<?php if ($error): ?>

<div class="error">
    <?= htmlspecialchars($error) ?>
</div>

<?php endif; ?>

<form method="POST">

    <div class="form-group">

        <label>
            Username *
        </label>

        <input
            type="text"
            name="username"
            maxlength="50"
            required
        >

    </div>


    <div class="form-group">

        <label>
            Full Name *
        </label>

        <input
            type="text"
            name="full_name"
            maxlength="100"
            required
        >

    </div>


    <div class="form-group">

        <label>
            Role *
        </label>

        <select
            name="role"
            required
        >

            <option value="cashier">
                Cashier
            </option>

            <option value="inventory">
                Inventory
            </option>

            <option value="admin">
                Administrator
            </option>

        </select>

    </div>


    <div class="form-group">

        <label>
            Password *
        </label>

        <input
            type="password"
            name="password"
            minlength="6"
            required
        >

    </div>


    <div class="actions">

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
            Create User
        </button>

    </div>

</form>

</div>

</div>

</body>

</html>