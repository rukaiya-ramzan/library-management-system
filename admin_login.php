
<?php
session_start();
require_once __DIR__ . '/admin_config.php';

if (
    isset($_SESSION['admin_logged_in']) &&
    $_SESSION['admin_logged_in'] === true
) {
    header('Location: view.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (
        strtolower($email) === strtolower(ADMIN_EMAIL) &&
        password_verify($password, ADMIN_PASSWORD_HASH)
    ) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;

        header('Location: view.php');
        exit;
    }

    $error = 'Invalid administrator Gmail or password.';
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login - Library Management System</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #F5ECD3;
            color: #173E28;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background-color: #173E28;
            padding: 15px 0;
        }

        .navbar-brand {
            color: #F5ECD3;
            font-weight: 600;
        }

        .navbar-brand:hover {
            color: #D7BC7F;
        }

        .login-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 45px 15px;
        }

        .login-card {
            width: 100%;
            max-width: 450px;
            background-color: #FFFAF0;
            border: none;
            border-radius: 18px;
        }

        .login-icon {
            font-size: 48px;
            color: #2C553B;
        }

        .form-control {
            border-radius: 10px;
            padding: 12px;
        }

        .btn-green {
            background-color: #2C553B;
            color: #FFFFFF;
            border: 1px solid #2C553B;
            border-radius: 10px;
            padding: 12px;
        }

        .btn-green:hover {
            background-color: #173E28;
            color: #FFFFFF;
        }

        footer {
            background-color: #173E28;
            color: #F5ECD3;
            padding: 18px 10px;
        }
    </style>
</head>

<body>

<nav class="navbar">
    <div class="container">
        <a class="navbar-brand text-decoration-none" href="index.html">
            <i class="bi bi-book-half me-2"></i>Library Management System
        </a>
    </div>
</nav>

<section class="login-section">
    <div class="card login-card shadow-sm p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="login-icon mb-2">
                <i class="bi bi-person-lock"></i>
            </div>

            <h2 class="fw-bold">Admin Login</h2>
            <p class="text-secondary mb-0">Sign in to access library entries.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-warning" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="admin_login.php">
            <div class="mb-3">
                <label for="email" class="form-label">Administrator Gmail</label>
                <input
                    type="email"
                    class="form-control"
                    id="email"
                    name="email"
                    placeholder="Enter administrator Gmail"
                    value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="username"
                    required>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input
                    type="password"
                    class="form-control"
                    id="password"
                    name="password"
                    placeholder="Enter password"
                    autocomplete="current-password"
                    required>
            </div>

            <button type="submit" class="btn btn-green w-100">
                <i class="bi bi-box-arrow-in-right me-2"></i>Login
            </button>
        </form>

        <div class="text-center mt-4">
            <a href="index.html" class="text-decoration-none" style="color: #2C553B;">
                <i class="bi bi-arrow-left me-1"></i>Back to Home
            </a>
        </div>
    </div>
</section>

<footer class="text-center">
    <p class="mb-0">Developed by Rukaiya Ramzan - B7 | 2026</p>
</footer>

</body>
</html>
