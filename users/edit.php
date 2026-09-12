<?php

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT *
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die('User not found.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username  = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role      = $_POST['role'] ?? 'cashier';
    $password  = $_POST['password'] ?? '';

    if ($username === '' || $full_name === '') {

        $error = 'Username and full name are required.';

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
              AND id != ?
            LIMIT 1
        ");

        $stmt->execute([
            $username,
            $id
        ]);

        if ($stmt->fetch()) {

            $error = 'Username already exists.';

        } else {

            if ($password !== '') {

                if (strlen($password) < 6) {

                    $error =
                        'Password must be at least 6 characters.';

                } else {

                    $hash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET
                            username = ?,
                            full_name = ?,
                            role = ?,
                            password = ?
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        $username,
                        $full_name,
                        $role,
                        $hash,
                        $id
                    ]);

                    header("Location: index.php");
                    exit;
                }

            } else {

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET
                        username = ?,
                        full_name = ?,
                        role = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $username,
                    $full_name,
                    $role,
                    $id
                ]);

                header("Location: index.php");
                exit;
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Edit User | Canteen POS</title>

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

.help {
    font-size: 12px;
    color: #6c757d;
    margin-top: 5px;
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
    ✏️ Edit User
</h1>

<?php if ($error): ?>

<div class="error">
    <?= htmlspecialchars($error) ?>
</div>

<?php endif; ?>

<form method="POST">

    <div class="form-group">

        <label>
            Username
        </label>

        <input
            type="text"
            name="username"
            value="<?= htmlspecialchars(
                $user['username']
            ) ?>"
            required
        >

    </div>


    <div class="form-group">

        <label>
            Full Name
        </label>

        <input
            type="text"
            name="full_name"
            value="<?= htmlspecialchars(
                $user['full_name']
            ) ?>"
            required
        >

    </div>


    <div class="form-group">

        <label>
            Role
        </label>

        <select name="role">

            <option
                value="admin"
                <?= $user['role'] === 'admin'
                    ? 'selected'
                    : '' ?>
            >
                Administrator
            </option>

            <option
                value="cashier"
                <?= $user['role'] === 'cashier'
                    ? 'selected'
                    : '' ?>
            >
                Cashier
            </option>

            <option
                value="inventory"
                <?= $user['role'] === 'inventory'
                    ? 'selected'
                    : '' ?>
            >
                Inventory
            </option>

        </select>

    </div>


    <div class="form-group">

        <label>
            New Password
        </label>

        <input
            type="password"
            name="password"
            minlength="6"
        >

        <div class="help">
            Leave blank if you don't want to change the password.
        </div>

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
            Save Changes
        </button>

    </div>

</form>

</div>

</div>

</body>

</html>