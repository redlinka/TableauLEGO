<?php
require_once "session.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign In</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/log_out.css">
</head>
<body>



<div class="container">
    <h1>Sign In</h1>

    <form method="post" enctype="multipart/form-data">
        <label for="email">Email*</label>
        <input type="email" id="email" name="email" required>

        <label for="password">Password*</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Sign In</button>
    </form>

    <div class="links">
        <a href="#">Create an account</a>
        <a href="#">Lost password?</a>
    </div>
</div>
