<?php
return [
    //index.php

    'title' => 'Transform your image into LEGO® art',
    'subtitle' => 'Select your file below.',
    'button_continue' => 'Continue',
    'btn_login' => 'Login',
    'btn_register' => 'Register',
    'upload_title' => 'Upload your image',
    'upload_formats' => 'Accepted formats: .jpg, .jpeg, .png, .webp · Min size 512x512 · Max 2 MB',

    //confirmation.php

    'titlec' => 'Thanks for your order!',
    'orderref' => 'Order reference: ',
    'var' => 'Variant',
    '-size' => '- Size: ',
    'size' => 'Size',
    'ordersaved' => 'Your order has been saved in our system.',
    'dbunavailable' => '(Database unavailable, order not stored.)',
    'order' => 'Order',
    'internalID' => 'Internal ID',
    'choosen_mosaic' => 'Choosen  mosaic',
    'backtohome' => 'Back to home',

    //legal.php

    'titlel' => 'Legal',
    'Acadproj' => 'Academic project. No commercial use. © img2brick.',

    //login.php + register.php
    
    'errorlog' => 'Invalid email.',
    'error' => 'Invalid email and/or password',
    'connexion' => 'Connexion',
    'mail' => 'Email',
    'password' => 'Password',
    'login' => 'Log In',
    'noacc' => 'Not registered yet ?',
    'register' => 'Register',
    'errpassword' => 'Password must contain at least 8 characters.',
    'errpassword_strong' => 'Password must be at least 12 characters and include lowercase, uppercase, and a number.',
    'errpassword2' => 'The passwords do not match.',
    'emailerr' => 'Email already existing.',
    'firstname' => 'First name',
    'lastname' => 'Last name',
    'passwordconfirm' => 'Confirm password',
    'createacc' => 'Create my account',
    'alraedyacc' => 'Already registered ?',
    'logout' => 'Log out',

    //orders.php

    'uploaderr' => 'Please upload an image first',
    'check' => 'Checkout',
    'acc' => 'Account',
    'secure' => 'Will be hashed, following the CNIL rules.',
    'address' => 'Address',
    'zip' => 'ZIP',
    'city' => 'City',
    'country' => 'Country',
    'phone' => 'Phone',
    'payment' => 'Payment',
    'cardnumber' => 'Card number',
    'expiry' => 'Expiry',
    'cvc' => 'CVC',
    'paysim' => 'Simulated payment in the context of the project (no real charge)',
    'captcha' => 'Anti-bot check',
    'question' => 'What is',
    'captchaverif' => 'Simple verification, no tracking and no Google used.',
    'confirmation' => 'Confirm my order',

    //preview.php

    'uploadfailed' => 'Upload Failed',
    'filetoolarge' => 'File too large',
    'invalidimage' => 'Invalid image',
    'imagetoosmall' => 'Image too small',
    'movefailed' => 'Move failed',
    
    //privacy.php

    'privacy' => 'Privacy',
    'safety' => 'We do not keep you infos, according to CNIL rules.',

    //results.php

    'uploadfirst' => 'Please upload an image first',
    'genmosaic' => 'Here are your generated mosaics',
    'styleprefer' => 'Choose the style you prefer. These are mock previews with different color treatments.',
    'blueaccent' => 'Blue accent preview',
    'blueaccent2' => 'Blue accent',
    'redaccent' => 'Red accent preview',
    'redaccent2' => 'Red accent',
    'bwaccent' => 'Black and white accent preview',
    'bwaccent2' => 'Black and white accent',
    'validatechoice' => 'Validate my choice',
    'return' => 'Back',
    
    //auth.php
     
    'access' => '403 - Access denied.',
    'admin' => 'You need to be an admin to access this page.',

    //admin - orders.php

    'backoffice' => 'Back office - Orders',
    'getback' => 'Back to website',
    'dberrcon' => 'Connexion to database is unavailable.',
    'noorders' => 'No orders yet.',
    'status' => 'status',
    'amount' => 'Amount',
    'details' => 'See details',

    //mails
    'mail_login_subject' => 'Connection to your img2brick account',
    'mail_login_body' => 'Hello friend,<br><br>A connection to your img2brick account just happened.<br>
    Here is the information related to this login:<br>
    <ul>
    <li><strong>Date:</strong> {{date}}</li>
    <li><strong>Email:</strong> {{email}}</li>
    <li><strong>IP address:</strong> {{ip}}</li>
    </ul>
    If this connection was made by you, no action is required.<br>
    If not, please change your password as soon as possible.<br><br>
    See you soon on img2brick!<br><br>
    <small>This is an automatic message. Please do not reply.</small>',

    'mail_register_subject' => 'Welcome to img2brick',
    'mail_register_body' => 'Hello new friend,<br><br>Your img2brick account has been successfully created with the following email:<br>
    <strong>{{email}}</strong><br><br>
    You can now log in and start transforming your photos into LEGO artworks.<br><br>
    We recommend using a secure password and keeping your email address up to date.<br><br>
    See you soon on img2brick!<br><br>
    <small>This is an automatic message. Please do not reply.</small>',

    'upload_click' => 'Click to add an image',
    'upload_subtext' => 'Drag and drop',
    'welcome' => 'Welcome',
    'myorders' => 'My orders',
    'hereare'=>'Here are all the mosaics you have ordered.',
    'placedorder' => 'You have not placed any order yet.',
    'start' => 'Start a new mosaic',
    'commande' => 'Order',
    'taille' => 'Size',
    'account_section_identity' => 'Identity',
    'account_section_billing' => 'Billing address',
    'save_changes' => 'Save changes',
    'myaccount' => 'My Account',
    'account_updated' => 'Account updated',
    'drag_drop_title' => 'Drag and drop your image here',
    'drag_drop_subtitle' => 'Accepted formats: JPG • PNG • WEBP — Minimum size 512×512 px',
    'drag_drop_button' => 'Choose a file',
    'drag_drop_none' => 'No file selected',
    'results_title' => 'Here are your generated mosaics',
    'results_subtitle' => 'Choose the style you prefer. These are mock previews with different color treatments.',
    'results_board_title' => 'Choose your board size',
    'results_board_subtitle' => 'This size will be used for your LEGO® mosaic.',
    'results_board_32' => '32 × 32 pixels - Small',
    'results_board_64' => '64 × 64 pixels - Standard',
    'results_board_96' => '96 × 96 pixels - Large',
    'results_meta_blue' => '~ 24 colors · Est. €99',
    'results_meta_red' => '~ 20 colors · Est. €95',
    'results_meta_bw' => '~ 8 colors · Est. €89',
'2fa_subject' => 'Your login code (2FA)',
'2fa_body' => 'Here is your code: <b>{{code}}</b><br>It expires in {{minutes}} minute.',
'2fa_title' => 'Two-step verification',
'2fa_subtitle' => 'A code was sent to:',
'2fa_code_label' => '6-digit code',
'2fa_hint' => 'The code is valid for 1 minute.',
'2fa_verify_btn' => 'Verify',
'2fa_back_login' => 'Back to login',
'2fa_missing' => 'No 2FA session found. Please log in again.',
'2fa_expired' => 'Code expired. Log in again to receive a new one.',
'2fa_invalid' => 'Invalid code.',
'2fa_toomany' => 'Too many attempts. Please log in again.',
'2fa_timer_label' => 'Next attempt',
'2fa_resend' => 'Send new code',
'forgot_title' => 'Forgot password',
'forgot_subtitle' => 'Enter your email. If an account exists, you will receive a reset link.',
'forgot_btn' => 'Send reset link',
'forgot_success' => 'If an account matches this email, a reset link has been sent.',
'back_login' => 'Back to login',

'reset_subject' => 'Password reset',
'reset_body' => 'Click this link to reset your password:<br><a href="{{link}}">{{link}}</a><br><br>This link expires in {{minutes}} minutes.',
'reset_title' => 'Reset password',
'reset_new_password' => 'New password',
'reset_confirm_password' => 'Confirm password',
'reset_btn' => 'Update password',
'reset_invalid' => 'Invalid or expired link. Please request a new one.',
'reset_success' => 'Password updated successfully.',

'mosaicc' => 'Your mosaic is being prepared.'


];
