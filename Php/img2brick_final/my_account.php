<?php
session_start();
global $cnx;
include("./config/cnx.php");
require_once __DIR__ . '/includes/i18n.php';

// Redirect to login if user is not authenticated
if (!isset($_SESSION['userId'])) {
    header("Location: connexion.php");
    exit;
}

$userId = $_SESSION['userId'];
$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $errors[] = tr('account.session_expired', 'Session expired. Please refresh.');
    } else {
        // Sanitize input fields
        $username = trim($_POST['username']);
        $name     = trim($_POST['name']);
        $surname  = trim($_POST['surname']);
        $phone    = trim($_POST['phone']);
        $year     = !empty($_POST['year_of_birth']) ? (int)$_POST['year_of_birth'] : null;
        $address  = trim($_POST['default_address']);

        // Check if username is already taken
        if ($username !== $_SESSION['username']) {
            $stmt = $cnx->prepare("SELECT user_id AS id_user FROM USER WHERE email = ? AND user_id != ?");
            $stmt->execute([$username, $userId]);
            if ($stmt->fetch()) {
                $errors[] = "Username '$username' is already taken.";
            }
        }

        // Update user information in database
        if (empty($errors)) {
            try {
                $sql = "UPDATE Users SET 
                            username = ?, 
                            name = ?, 
                            surname = ?, 
                            phone = ?, 
                            year_of_birth = ?, 
                            default_address = ? 
                            WHERE id_user = ?";
                $stmt = $cnx->prepare($sql);
                $stmt->execute([$username, $name, $surname, $phone, $year, $address, $userId]);

                // Update session variable
                $_SESSION['username'] = $username;
                $success = tr('account.update_success', 'Information updated successfully!');
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Fetch latest user data for display
$stmt = $cnx->prepare("SELECT user_id AS id_user, email AS username, email, password, is_verified AS is_active, phone, default_address, first_name AS name, last_name AS surname, NULL AS year_of_birth, NULL AS creation_date FROM USER WHERE user_id = ? ");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars(tr('account.page_title', 'My Account - Img2Brick')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 data-i18n="account.title">My Account</h1>
                <a href="index.php" class="btn btn-outline-secondary" data-i18n="account.back_home">Back to Home</a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0"><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white" data-i18n="account.section_personal">Personal Information</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_get()) ?>">

                        <h5 class="mb-3 text-muted" data-i18n="account.identity">Identity</h5>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label" data-i18n="account.username">Username</label>
                                <input type="text" class="form-control" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                                <div class="form-text" data-i18n="account.username_hint">Must be unique.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" data-i18n="account.email">Email</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                                <div class="form-text" data-i18n="account.email_hint">Email cannot be changed directly.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" data-i18n="account.first_name">First Name</label>
                                <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" placeholder="e.g. John (optional)" data-i18n-attr="placeholder:account.first_name_placeholder">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" data-i18n="account.surname">Surname</label>
                                <input type="text" class="form-control" name="surname" value="<?= htmlspecialchars($user['surname'] ?? '') ?>" placeholder="e.g. Doe (optional)" data-i18n-attr="placeholder:account.surname_placeholder">
                            </div>
                        </div>

                        <h5 class="mb-3 text-muted border-top pt-3" data-i18n="account.stats_title">Statistics (Privacy Friendly)</h5>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label" data-i18n="account.birth_year">Year of Birth</label>
                                <input type="number" class="form-control" name="year_of_birth"
                                       min="1900" max="<?= date('Y') ?>"
                                       value="<?= htmlspecialchars($user['year_of_birth'] ?? '') ?>"
                                       placeholder="YYYY" data-i18n-attr="placeholder:account.birth_year_placeholder">
                                <div class="form-text" data-i18n="account.birth_year_hint">Used for age statistics only.</div>
                            </div>
                        </div>

                        <h5 class="mb-3 text-muted border-top pt-3" data-i18n="account.delivery_title">Delivery Defaults</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label" data-i18n="account.phone">Phone Number</label>
                                <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+33 6 12 34 56 78" data-i18n-attr="placeholder:account.phone_placeholder">
                                <div class="form-text" data-i18n="account.phone_hint">Mandatory for shipping carriers.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" data-i18n="account.address">Default Shipping Address</label>
                                <textarea class="form-control" name="default_address" rows="2" placeholder="Street, Zip Code, City, Country" data-i18n-attr="placeholder:account.address_placeholder"><?= htmlspecialchars($user['default_address'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary" data-i18n="account.update">Update Information</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mt-4 border-danger">
                <div class="card-header bg-danger text-white" data-i18n="account.security_title">Security Zone</div>
                <div class="card-body">
                    <p data-i18n="account.security_hint">Need to change your password? We will send you a secure link.</p>
                    <a href="password_forgotten.php" class="btn btn-outline-danger" data-i18n="account.reset_password">Reset Password</a>
                </div>
            </div>

        </div>
    </div>
</div>
<?php include("./includes/footer.php"); ?>
</body>
</html>
