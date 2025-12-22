<?php
session_start();
?>
<header class="main-header">
        <div class="header-container">
            <a href="index.php" class="header-logo">
                <img src="assets/images/img2brick.png" alt="Img2Brick">
            </a>

            <nav class="header-nav">
                <a href="index.php">Home</a>

                <?php if (isset($_SESSION["user_id"])): ?>
                    <a href="user.php">My Account</a>
                    <a href="logout.php" class="btn-auth logout">Logout</a>
                <?php else: ?>
                    <a href="connection.php" class="btn-auth login">Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>