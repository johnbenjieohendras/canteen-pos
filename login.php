<?php

session_start();

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/header.php";

/*
|--------------------------------------------------------------------------
| Already Logged In
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['user_id'], $_SESSION['role'])) {

    switch ($_SESSION['role']) {

        case 'admin':
            header("Location: index.php");
            exit;

        case 'cashier':
            header("Location: pos/index.php");
            exit;

        case 'inventory':
            header("Location: inventory/index.php");
            exit;

        default:
            session_unset();
            session_destroy();
            break;
    }
}

$error = "";

/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === "" || $password === "") {

        $error = "Please enter username and password.";

    } else {

        $stmt = $pdo->prepare("
            SELECT
                id,
                username,
                password,
                full_name,
                role,
                status
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->execute([$username]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            $user &&
            password_verify($password, $user['password'])
        ) {

            /*
            |--------------------------------------------------------------------------
            | CHECK STATUS
            |--------------------------------------------------------------------------
            */

            if ($user['status'] !== 'active') {

                $error = "Your account is inactive.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | NEW SESSION
                |--------------------------------------------------------------------------
                */

                session_regenerate_id(true);

                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];

                /*
                |--------------------------------------------------------------------------
                | REDIRECT BY ROLE
                |--------------------------------------------------------------------------
                */

                switch ($user['role']) {

                    case 'admin':

                        header("Location: index.php");
                        exit;

                    case 'cashier':

                        header("Location: pos/index.php");
                        exit;

                    case 'inventory':

                        header("Location: inventory/index.php");
                        exit;

                    default:

                        session_unset();
                        session_destroy();

                        $error = "Invalid user role.";
                        break;
                }
            }

        } else {

            $error = "Invalid username or password.";
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

    <title>Canteen POS - Login</title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            height: 100%;
            margin: 0;
        }

        body {

            font-family:
                "Segoe UI",
                Arial,
                Helvetica,
                sans-serif;

            background:
                url("assets/images/canteen-bg.jpg")
                center center /
                cover
                no-repeat;

            overflow: hidden;
        }

        /*
        |--------------------------------------------------------------------------
        | DARK OVERLAY
        |--------------------------------------------------------------------------
        */

        body::before {

            content: "";

            position: fixed;

            inset: 0;

            background:
                linear-gradient(
                    135deg,
                    rgba(0,0,0,.78),
                    rgba(0,0,0,.48),
                    rgba(0,0,0,.70)
                );

            z-index: 0;
        }

        /*
        |--------------------------------------------------------------------------
        | MAIN CONTAINER
        |--------------------------------------------------------------------------
        */

        .login-wrapper {

            position: relative;

            z-index: 1;

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 30px;
        }

        /*
        |--------------------------------------------------------------------------
        | LOGIN CARD
        |--------------------------------------------------------------------------
        */

        .login-card {

            width: 430px;

            max-width: 100%;

            border-radius: 24px;

            overflow: hidden;

            background:
                rgba(255,255,255,.94);

            backdrop-filter: blur(15px);

            -webkit-backdrop-filter: blur(15px);

            border:
                1px solid
                rgba(255,255,255,.35);

            box-shadow:
                0 30px 80px
                rgba(0,0,0,.45);

            animation:
                loginAppear .5s ease;
        }

        @keyframes loginAppear {

            from {

                opacity: 0;

                transform:
                    translateY(25px)
                    scale(.97);
            }

            to {

                opacity: 1;

                transform:
                    translateY(0)
                    scale(1);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        .login-header {

            position: relative;

            padding: 38px 30px 32px;

            text-align: center;

            color: white;

            background:
                linear-gradient(
                    135deg,
                    #111827,
                    #1f2937
                );
        }

        .header-pattern {

            position: absolute;

            inset: 0;

            opacity: .08;

            background-image:

                radial-gradient(
                    circle at 20% 20%,
                    white 1px,
                    transparent 1px
                );

            background-size: 20px 20px;

            pointer-events: none;
        }

        /*
        |--------------------------------------------------------------------------
        | LOGO
        |--------------------------------------------------------------------------
        */

        .login-logo {

            position: relative;

            width: 82px;

            height: 82px;

            margin:
                0 auto 18px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 22px;

            background:
                linear-gradient(
                    135deg,
                    #ffffff,
                    #e5e7eb
                );

            color: #18191d;

            box-shadow:
                0 12px 30px
                rgba(0,0,0,.30);

            font-size: 34px;
        }

        .login-title {

            position: relative;

            margin: 0;

            font-size: 28px;

            font-weight: 800;

            letter-spacing: -.5px;
        }

        .login-subtitle {

            position: relative;

            margin:
                7px 0 0;

            color:
                rgba(255,255,255,.70);

            font-size: 13px;

            letter-spacing: .3px;
        }

        /*
        |--------------------------------------------------------------------------
        | BODY
        |--------------------------------------------------------------------------
        */

        .login-body {

            padding: 32px;
        }

        /*
        |--------------------------------------------------------------------------
        | ERROR
        |--------------------------------------------------------------------------
        */

        .login-error {

            display: flex;

            align-items: center;

            gap: 10px;

            padding: 12px 14px;

            margin-bottom: 22px;

            border-radius: 12px;

            background:
                #fff1f2;

            border:
                1px solid
                #fecdd3;

            color:
                #be123c;

            font-size: 13px;

            font-weight: 600;
        }

        /*
        |--------------------------------------------------------------------------
        | LABEL
        |--------------------------------------------------------------------------
        */

        .form-label {

            display: block;

            margin-bottom: 8px;

            color: #b8bbc0;

            font-size: 13px;

            font-weight: 700;
        }

        /*
        |--------------------------------------------------------------------------
        | INPUT GROUP
        |--------------------------------------------------------------------------
        */

        .input-group-custom {

            position: relative;

            margin-bottom: 20px;
        }

        .input-icon {

            position: absolute;

            left: 15px;

            top: 50%;

            transform:
                translateY(-50%);

            z-index: 5;

            color: #3813db;

            font-size: 17px;

            pointer-events: none;
        }

        .form-control {

            width: 100%;

            height: 52px;

            padding:
                0 45px;

            border:
                1px solid
                #d1d5db;

            border-radius: 13px;

            background:
                #f9fafb;

            color: #1e5fd8;

            font-size: 14px;

            transition:
                .2s ease;
        }

        .form-control:hover {

            border-color:
                #9ca3af;
        }

        .form-control:focus {

            border-color:
                #bcc1c9;

            background: white;

            box-shadow:
                0 0 0 4px
                rgba(31,41,55,.10);

            outline: none;
        }

        /*
        |--------------------------------------------------------------------------
        | PASSWORD BUTTON
        |--------------------------------------------------------------------------
        */

        .password-toggle {

            position: absolute;

            right: 13px;

            top: 50%;

            transform:
                translateY(-50%);

            border: none;

            background: transparent;

            color: #6b7280;

            cursor: pointer;

            font-size: 17px;

            z-index: 5;
        }

        .password-toggle:hover {

            color: #111827;
        }

        /*
        |--------------------------------------------------------------------------
        | LOGIN BUTTON
        |--------------------------------------------------------------------------
        */

        .btn-login {

            width: 100%;

            height: 52px;

            border: none;

            border-radius: 13px;

            background:
                linear-gradient(
                    135deg,
                    #239673,
                    #374151
                );

            color: white;

            font-size: 15px;

            font-weight: 700;

            letter-spacing: .2px;

            box-shadow:
                0 10px 20px
                rgba(17,24,39,.22);

            transition:
                all .2s ease;
        }

        .btn-login:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 14px 28px
                rgba(17,24,39,.28);

            background:
                linear-gradient(
                    135deg,
                    #1f2937,
                    #4b5563
                );
        }

        .btn-login:active {

            transform:
                translateY(0);
        }

        /*
        |--------------------------------------------------------------------------
        | FOOTER
        |--------------------------------------------------------------------------
        */

        .login-footer {

            text-align: center;

            margin-top: 25px;

            color: #9ca3af;

            font-size: 11px;

            letter-spacing: .5px;
        }

        .system-status {

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 6px;

            margin-top: 8px;

            color: #6b7280;

            font-size: 11px;
        }

        .status-dot {

            width: 7px;

            height: 7px;

            border-radius: 50%;

            background: #22c55e;

            box-shadow:
                0 0 0 3px
                rgba(34,197,94,.12);
        }

        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 500px) {

            .login-wrapper {

                padding: 18px;
            }

            .login-card {

                border-radius: 20px;
            }

            .login-header {

                padding: 30px 20px;
            }

            .login-body {

                padding: 25px 22px;
            }

            .login-title {

                font-size: 24px;
            }

            .login-logo {

                width: 70px;

                height: 70px;

                font-size: 29px;
            }
        }

    </style>

</head>

<body>


<div class="login-wrapper">


    <div class="login-card">


        <!-- HEADER -->

        <div class="login-header">

            <div class="header-pattern"></div>

            <div class="login-logo">

                <i class="bi bi-shop"></i>

            </div>

            <h1 class="login-title">

                Mini Canteen POS

            </h1>

            <p class="login-subtitle">

                Cashiering & Back Office System

            </p>

        </div>


        <!-- BODY -->

        <div class="login-body">


            <?php if ($error !== ""): ?>

                <div class="login-error">

                    <i class="bi bi-exclamation-circle-fill"></i>

                    <span>
                        <?= htmlspecialchars($error) ?>
                    </span>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                autocomplete="on"
            >


                <!-- USERNAME -->

                <label
                    class="form-label"
                    for="username"
                >

                    Username

                </label>


                <div class="input-group-custom">

                    <i class="bi bi-person input-icon"></i>

                    <input
                        type="text"
                        name="username"
                        id="username"
                        class="form-control"
                        placeholder="Enter your username"
                        value="<?= htmlspecialchars(
                            $_POST['username'] ?? ''
                        ) ?>"
                        autocomplete="username"
                        required
                        autofocus
                    >

                </div>


                <!-- PASSWORD -->

                <label
                    class="form-label"
                    for="password"
                >

                    Password

                </label>


                <div class="input-group-custom">

                    <i class="bi bi-lock input-icon"></i>

                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-control"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        onclick="togglePassword()"
                        aria-label="Show password"
                    >

                        <i
                            id="passwordIcon"
                            class="bi bi-eye"
                        ></i>

                    </button>

                </div>


                <!-- LOGIN -->

                <button
                    type="submit"
                    class="btn-login"
                >

                    <i class="bi bi-box-arrow-in-right me-2"></i>

                    Sign In

                </button>


            </form>


            <!-- FOOTER -->

            <div class="login-footer">

                ADCOM CANTEEN POS SYSTEM

            </div>


            <div class="system-status">

                <span class="status-dot"></span>

                System Ready

            </div>


        </div>


    </div>


</div>


<script>

function togglePassword()
{

    const password =
        document.getElementById("password");

    const icon =
        document.getElementById("passwordIcon");


    if (password.type === "password") {

        password.type = "text";

        icon.classList.remove(
            "bi-eye"
        );

        icon.classList.add(
            "bi-eye-slash"
        );

    } else {

        password.type = "password";

        icon.classList.remove(
            "bi-eye-slash"
        );

        icon.classList.add(
            "bi-eye"
        );
    }

}

</script>


</body>

</html>