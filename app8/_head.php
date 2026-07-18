<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?? 'Untitled' ?></title>
    <link rel="shortcut icon" href="/images/favicon.png">
    <link rel="stylesheet" href="/css/app.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="/js/app.js"></script>
</head>
<body>
    <!-- Flash message -->
    <div id="info"><?= temp('info') ?></div>

    <header>
        <h1><a href="/">Send Email</a></h1>

        <?php if ($_user): ?>
            <div>
                <?= $_user->name ?><br>
                <?= $_user->role ?>
            </div>
            <img src="/photos/<?= $_user->photo ?>">
        <?php endif ?>
    </header>

    <nav>
        <a href="/">Index</a>
        <a href="/demo.php">Demo</a>

        <div></div>

        <?php if ($_user): ?>
            <a href="/user/profile.php">Profile</a>
            <a href="/user/password.php">Password</a>
            <a href="/logout.php">Logout</a>
        <?php else: ?>
            <a href="/user/register.php">Register</a>
            <a href="/user/reset.php">Reset Password</a>
            <a href="/login.php">Login</a>
        <?php endif ?>
    </nav>

    <main>
        <h1><?= $_title ?? 'Untitled' ?></h1>