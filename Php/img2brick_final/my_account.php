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
if ($_SESSION['username'] == '4DM1N1STRAT0R_4ND_4LM16HTY') {
    header("Location: admin_panel.php");
    exit;
}

$userId  = $_SESSION['userId'];
$errors  = [];
$success = '';
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = tr('account.update_success', 'Information updated successfully!');
}

// Fetch latest user data for display
try {
    $stmt = $cnx->prepare("
    SELECT 
        user_id,
        username,
        email,
        first_name,
        last_name,
        phone,
        birth_year
    FROM USER
    WHERE user_id = ?
");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        //http_response_code(404);
        header("Location: index.php"); // create error message
        exit;
    }

    $stmtAddr = $cnx->prepare("SELECT street, postal_code, city, country FROM ADDRESS WHERE user_id = ? AND is_default = 1 LIMIT 1");
    $stmtAddr->execute([$userId]);
    $addressData = $stmtAddr->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    //echo "Database error: " . $e->getMessage();
    header("Location: index.php"); // create error message
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate CSRF token
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $errors[] = tr('account.session_expired', 'Session expired. Please refresh.');
    } else {
        // Sanitize input fields
        $username = trim($_POST['username'] ?? '');
        $newEmail = !empty($_POST['email']) ? trim($_POST['email']) : $user['email']; //$newEmail    = trim($_POST['email'] ?? '');
        $name     = trim($_POST['name'] ?? '');
        $surname  = trim($_POST['surname'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $birthYear = !empty($_POST['birth_year']) ? (int)$_POST['birth_year'] : null;
        $street  = trim($_POST['street'] ?? '');
        $zip     = trim($_POST['zip'] ?? '');
        $city    = trim($_POST['city'] ?? '');
        $country = trim($_POST['country'] ?? '');

        if (empty($username)) $errors[] = "Username is required.";
        if (empty($newEmail)) $errors[] = "Email is required.";

        // Check if username/email is already taken
        if (empty($errors) && $username !== $user['username']) {
            $check = $cnx->prepare("SELECT 1 FROM USER WHERE username = ? AND user_id <> ?");
            $check->execute([$username, $userId]);
            if ($check->fetchColumn()) {
                $errors[] = "Username '$username' is already taken.";
            }
        }

        // Check if email is already taken
        if (empty($errors) && $newEmail !== $user['email']) {
            $checkEmail = $cnx->prepare("SELECT 1 FROM USER WHERE email = ? AND user_id <> ?");
            $checkEmail->execute([$newEmail, $userId]);
            if ($checkEmail->fetchColumn()) {
                $errors[] = "The email address '$newEmail' is already associated with another account.";
            }
        }

        // Update user information in database
        if (empty($errors)) {
            try {
                $cnx->beginTransaction();

                // Update USER table
                $emailChanged = ($newEmail !== $user['email']);
                $sql = "UPDATE USER SET 
                username = ?, 
                email = ?, 
                first_name = ?, 
                last_name = ?, 
                phone = ?, 
                birth_year = ?" . ($emailChanged ? ", is_verified = 0" : "") . " 
                WHERE user_id = ?";

                $upd = $cnx->prepare($sql);
                $upd->execute([$username, $newEmail, $name, $surname, $phone, $birthYear, $userId]);

                // Update user default address in ADDRESS table
                $stmtCheck = $cnx->prepare("SELECT address_id FROM ADDRESS WHERE user_id = ? AND is_default = 1 LIMIT 1");
                $stmtCheck->execute([$userId]);
                $existingAddressId = $stmtCheck->fetchColumn();

                if ($existingAddressId) {
                    $stmtAddr = $cnx->prepare("UPDATE ADDRESS SET street = ?, postal_code = ?, city = ?, country = ? WHERE address_id = ?");
                    $stmtAddr->execute([$street, $zip, $city, $country, $existingAddressId]);
                } else {
                    $stmtAddr = $cnx->prepare("INSERT INTO ADDRESS (street, postal_code, city, country, user_id, is_default) VALUES (?, ?, ?, ?, ?, 1)");
                    $stmtAddr->execute([$street, $zip, $city, $country, $userId]);
                }

                if ($emailChanged) {
                    // Generate verification token
                    $token = bin2hex(random_bytes(32));
                    $expire_at = date('Y-m-d H:i:s', time() + 60);

                    // delete old token
                    $cnx->prepare("DELETE FROM 2FA WHERE user_id = ?")->execute([$userId]);
                    // Store token in database
                    $ins = $cnx->prepare("INSERT INTO 2FA (user_id, verification_token, token_expire_at) VALUES (?, ?, ?)");
                    $ins->execute([$userId, $token, $expire_at]);

                    // Construct magic link
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                    $domain = $_SERVER['HTTP_HOST'];
                    $link = $protocol . $domain . dirname($_SERVER['PHP_SELF']) . '/verify_connexion.php?token=' . $token;

                    $emailBody = "
                            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; max-width: 600px;'>
                                <h2 style='color: #0d6efd;'>Verify Your New Email Address</h2>
                                <p>You recently updated your email address on Img2Brick. To maintain your account security and verification status, please click the button below:</p>
                                <p style='text-align: center;'>
                                    <a href='{$link}' style='display: inline-block; background-color: #0d6efd; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Verify My Email</a>
                                </p>
                                <p style='color: #6c757d; font-size: 12px; margin-top: 20px;'>If the button doesn't work, copy this link: {$link}</p>
                                <p style='color: #6c757d; font-size: 12px;'>This link will expire in 1 minute.</p>
                            </div>";

                    sendMail(
                            $newEmail,
                            'Verify your new email address - Img2Brick',
                            $emailBody
                    );

                    $success = "Information updated successfully. A verification link has been sent to your new email address.";
                }
                $cnx->commit();
                $success = tr('account.update_success', 'Information updated successfully!');

                // Update session variable
                $_SESSION['email']    = $newEmail;
                $_SESSION['username'] = $username;

                // Refresh data for display
                header("Location: my_account.php?success=1");
                exit;

            } catch (PDOException $e) {
                $cnx->rollBack();
                //$errors[] = "Database error: " . $e->getMessage();
                $errors[] = "Database error";
            }
        }
    }
}

$user = [
        'username'   => ($_SERVER['REQUEST_METHOD'] === 'POST') ? $username : $user['username'],
        'email'      => $user['email'], // Toujours l'email de la BDD car disabled
        'name'       => ($_SERVER['REQUEST_METHOD'] === 'POST') ? $name : $user['first_name'],
        'surname'    => ($_SERVER['REQUEST_METHOD'] === 'POST') ? $surname : $user['last_name'],
        'phone'      => ($_SERVER['REQUEST_METHOD'] === 'POST') ? $phone : $user['phone'],
        'birth_year' => ($_SERVER['REQUEST_METHOD'] === 'POST') ? $birthYear : $user['birth_year'],
        'street'     => ($_SERVER['REQUEST_METHOD'] === 'POST') ? $street : ($addressData['street'] ?? ''),
        'zip'        => ($_SERVER['REQUEST_METHOD'] === 'POST') ? $zip : ($addressData['postal_code'] ?? ''),
        'city'       => ($_SERVER['REQUEST_METHOD'] === 'POST') ? $city : ($addressData['city'] ?? ''),
        'country'    => ($_SERVER['REQUEST_METHOD'] === 'POST') ? $country : ($addressData['country'] ?? '')
];
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
                    <ul class="mb-0"><?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
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
                                <input type="text" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
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
                                <input type="number" class="form-control" name="birth_year"
                                       min="1900" max="<?= date('Y') ?>"
                                       value="<?= htmlspecialchars($user['birth_year'] ?? '') ?>"
                                       placeholder="YYYY" data-i18n-attr="placeholder:account.birth_year_placeholder">
                                <div class="form-text" data-i18n="account.birth_year_hint">Used for age statistics only.</div>
                            </div>
                        </div>

                        <h5 class="mb-3 text-muted border-top pt-3" data-i18n="account.delivery_title">Delivery Defaults</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-12">
                                <label class="form-label" data-i18n="account.phone">Phone Number</label>
                                <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+33 6 12 34 56 78">
                            </div>

                            <div class="col-12">
                                <label class="form-label" data-i18n="account.street">Street Address</label>
                                <input type="text" class="form-control" name="street" value="<?= htmlspecialchars($user['street'] ?? '') ?>" placeholder="123 Brick Street">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" data-i18n="account.zip">Zip Code</label>
                                <input type="text" class="form-control" name="zip" value="<?= htmlspecialchars($user['zip'] ?? '') ?>" placeholder="75001">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" data-i18n="account.city">City</label>
                                <input type="text" class="form-control" name="city" value="<?= htmlspecialchars($user['city'] ?? '') ?>" placeholder="Paris">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" data-i18n="account.country">Country</label>
                                <select class="form-select" name="country">
                                    <option value="France" <?= ($user['country'] === 'France' ? 'selected' : '') ?>>France</option>
                                    <option value="Spain" <?= ($user['country'] === 'Spain' ? 'selected' : '') ?>>Spain</option>
                                    <option value="USA" <?= ($user['country'] === 'USA' ? 'selected' : '') ?>>USA</option>
                                    <option value="UK" <?= ($user['country'] === 'UK' ? 'selected' : '') ?>>UK</option>
                                </select>
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
