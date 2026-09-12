<?php

require_once __DIR__ . "/config/database.php";

/*
|--------------------------------------------------------------------------
| ADMIN ACCOUNT SETUP
|--------------------------------------------------------------------------
| Change these values if needed.
*/

$adminUsername = "admin";
$adminPassword = "password";
$adminFullName = "System Administrator";

/*
|--------------------------------------------------------------------------
| Generate secure password hash
|--------------------------------------------------------------------------
*/

$passwordHash = password_hash(
    $adminPassword,
    PASSWORD_DEFAULT
);

try {

    /*
    |--------------------------------------------------------------------------
    | Check if admin already exists
    |--------------------------------------------------------------------------
    */

    $check = $pdo->prepare("
        SELECT id
        FROM users
        WHERE username = ?
        LIMIT 1
    ");

    $check->execute([$adminUsername]);

    $existingUser = $check->fetch();

    if ($existingUser) {

        /*
        |--------------------------------------------------------------------------
        | Reset existing admin
        |--------------------------------------------------------------------------
        */

        $update = $pdo->prepare("
            UPDATE users
            SET
                password = ?,
                full_name = ?,
                role = 'admin',
                status = 'active'
            WHERE username = ?
        ");

        $update->execute([
            $passwordHash,
            $adminFullName,
            $adminUsername
        ]);

        $message = "Admin account already existed. Password has been reset.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Create new admin
        |--------------------------------------------------------------------------
        */

        $insert = $pdo->prepare("
            INSERT INTO users
            (
                username,
                password,
                full_name,
                role,
                status
            )
            VALUES
            (
                ?,
                ?,
                ?,
                'admin',
                'active'
            )
        ");

        $insert->execute([
            $adminUsername,
            $passwordHash,
            $adminFullName
        ]);

        $message = "Admin account successfully created.";
    }

} catch (PDOException $e) {

    die(
        "Database error: " .
        htmlspecialchars($e->getMessage())
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

    <title>Create Admin - Canteen POS</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>

<body class="bg-light">

<div class="container">

    <div class="row justify-content-center mt-5">

        <div class="col-md-6">

            <div class="card shadow">

                <div class="card-body p-4">

                    <h3 class="mb-4">
                        Canteen POS Admin Setup
                    </h3>

                    <div class="alert alert-success">

                        <?= htmlspecialchars($message) ?>

                    </div>

                    <table class="table table-bordered">

                        <tr>
                            <th>Username</th>
                            <td>
                                <?= htmlspecialchars($adminUsername) ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Password</th>
                            <td>
                                <?= htmlspecialchars($adminPassword) ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Role</th>
                            <td>
                                Admin
                            </td>
                        </tr>

                        <tr>
                            <th>Status</th>
                            <td>
                                Active
                            </td>
                        </tr>

                    </table>

                    <a
                        href="login.php"
                        class="btn btn-dark"
                    >
                        Go to Login
                    </a>

                </div>

            </div>

        </div>

    </div>

</div>

</body>

</html>