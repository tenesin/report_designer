<?php
// login.php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Here you can add your user validation logic
    // For simplicity, let's use static credentials
    if ($username == 'admin' && $password == 'password') {
        $_SESSION['loggedin'] = true;
        $_SESSION['role'] = 'admin'; // Setting role to admin
        header('Location: home.php');
        exit;
    } elseif ($username == 'user' && $password == 'password') {
        $_SESSION['loggedin'] = true;
        $_SESSION['role'] = 'user'; // Setting role to user
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
