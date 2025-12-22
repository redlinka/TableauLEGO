# img2brick

PHP/Bootstrap web app that turns an uploaded photo into LEGO-styled mosaic previews, lets users pick a board size and color variant, and walks them through a simulated checkout with email-based security (2FA, password reset).

# Features
- User auth with strong password rules, email 2FA on login, and password reset via signed links.
- Image upload (JPG/PNG/WebP, max 2 MB, min 512×512), cropping with CropperJS, and three preview variants (blue/red/bw).
- Order flow: choose board size (32×32/64×64/96×96), select variant, fill checkout with math captcha, and see confirmation + email if SMTP is configured.
- Account page to update profile/billing details and change password; order history and per-order details.
- Admin orders table at admin/orders.php (requires is_admin flag).
- English/French translations switchable via ?lang=en|fr.

# Stack
- PHP 8+ with PDO (PostgreSQL), fileinfo, and session extensions
- PostgreSQL (schema in db/schema.sql)
- Composer packages: phpmailer/phpmailer
- Frontend: Bootstrap 5, CropperJS, vanilla JS (assets/upload.js)

# Prerequisites
- PHP runtime with pdo_pgsql and fileinfo
- PostgreSQL instance you can write to
- Composer
- Writable uploads/ directory for user images

# Setup
1. Install PHP dependencies:
   bash
   composer install
   
2. Create .env (see existing file for keys). Required variables:
   - DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
   - SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM, SMTP_FROM_NAME, SMTP_SECURE
   Keep secrets out of version control.
3. Prepare the database:
   bash
   createdb img2brick        
   psql -d img2brick -f db/schema.sql
   
4. Ensure uploads/ exists and is writable by the web server:
   bash
   mkdir -p uploads
   chmod 755 uploads
   
5. Run locally (built-in server) or point your web server’s document root to this folder:
   bash
   php -S localhost:8000 -t .
   

# Usage
- Register, then log in: entering correct credentials triggers a 6-digit email 2FA challenge.
- Upload an image on index.php, crop it, then compare the three color previews on results.php.
- Pick a board size and variant, complete the checkout form + captcha on order.php, and review the confirmation page.
- View past orders via commandes.php and update profile/password on compte.php.
- Admins can review all orders at admin/orders.php once their user row has is_admin = true.

# Notes
- Emails are sent only if Composer dependencies are installed and SMTP values are valid; otherwise the app continues without mail.
- Payments are simulated; card fields are placeholders only.
- Uploaded files are stored under uploads/ with randomized names; cleanup/rotation is not automatic.
