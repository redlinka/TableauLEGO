<?php
require_once __DIR__ . '/config/session.php';
global $cnx;
include(__DIR__ . '/config/cnx.php');
require_once __DIR__ . '/includes/i18n.php';

http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <?php include(__DIR__ . '/includes/navbar.php'); ?>

    <div class="container text-center" style="margin-top: 100px; margin-bottom: 100px;">
        <h1 class="display-1 fw-bold text-muted">404</h1>
        <h2 class="mb-3">Page Not Found</h2>
        <p class="text-muted mb-4">The page you are looking for does not exist or has been moved.</p>
        <a href="index.php" class="btn btn-primary btn-lg">Back to Home</a>
    </div>

    <?php include(__DIR__ . '/includes/footer.php'); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
