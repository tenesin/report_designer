<?php
// login.php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if ($username == 'admin' && $password == 'password') {
        $_SESSION['loggedin'] = true;
        header('Location: home.php');
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" type="text/css" href="styles.css">
</head>
<body>
    <h2>Login</h2>
    <form method="post">
        <?php if (isset($error)) { echo "<p>$error</p>"; } ?>
        <label for="username">Username:</label>
        <input type="text" id="username" placeholder="admin" name="username" required><br>
        <label for="password">Password:</label>
        <input type="password" id="password" placeholder="password" name="password" required><br>
        <button type="submit">Login</button>
    </form>
</body>
</html>
