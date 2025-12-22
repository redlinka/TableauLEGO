<?php
return [
    // index.php

    'title' => 'Transformez votre image en œuvre d’art LEGO®',
    'subtitle' => 'Sélectionnez votre fichier ci-dessous.',
    'button_continue' => 'Continuer',
    'btn_login' => 'Connexion',
    'btn_register' => 'Inscription',
    'upload_title' => 'Importez votre image',
    'upload_formats' => 'Formats acceptés : .jpg, .jpeg, .png, .webp · Taille minimale 512x512 · Taille maximale 2 Mo',

    // confirmation.php

    'titlec' => 'Merci pour votre commande !',
    'orderref' => 'Référence de commande : ',
    'var' => 'Variante',
    '-size' => '- Taille : ',
    'size' => 'Taille',
    'ordersaved' => 'Votre commande a été enregistrée dans notre système.',
    'dbunavailable' => '(Base de données indisponible, commande non enregistrée.)',
    'order' => 'Commande',
    'internalID' => 'ID interne',
    'choosen_mosaic' => 'Mosaïque choisie',
    'backtohome' => 'Retour à l’accueil',

    // legal.php

    'titlel' => 'Mentions légales',
    'Acadproj' => 'Projet académique. Aucune utilisation commerciale. © img2brick.',

    // login.php

    'errorlog' => 'Adresse e-mail invalide.',
    'error' => 'Adresse e-mail et/ou mot de passe invalide(s)',
    'login_toomany' => 'Trop de tentatives. Veuillez attendre 1 minute et réessayer.',
    'connexion' => 'Connexion',
    'mail' => 'E-mail',
    'password' => 'Mot de passe',
    'login' => 'Se connecter',
    'noacc' => 'Pas encore inscrit ?',

    // register.php

    'register' => 'S’inscrire',
    'errpassword' => 'Le mot de passe doit contenir au moins 8 caractères.',
    'errpassword2' => 'Les mots de passe ne correspondent pas.',
    'errpassword_strong' => '12 caractères minimum dont 1 minuscule, 1 majuscule et 1 chiffre.',
    'emailerr' => 'Cette adresse e-mail existe déjà.',
    'firstname' => 'Prénom',
    'lastname' => 'Nom',
    'passwordconfirm' => 'Confirmez le mot de passe',
    'createacc' => 'Créer mon compte',
    'alraedyacc' => 'Déjà inscrit ?',

    // header / compte

    'logout' => 'Se déconnecter',

    // checkout.php

    'uploaderr' => 'Veuillez d’abord téléverser une image',
    'check' => 'Passer au paiement',
    'acc' => 'Compte',
    'secure' => 'Sera haché, conformément aux règles de la CNIL.',
    'address' => 'Adresse',
    'zip' => 'Code postal',
    'city' => 'Ville',
    'country' => 'Pays',
    'phone' => 'Téléphone',
    'payment' => 'Paiement',
    'cardnumber' => 'Numéro de carte',
    'expiry' => 'Expiration',
    'cvc' => 'CVC',
    'paysim' => 'Paiement simulé dans le cadre du projet (aucun débit réel).',
    'captcha' => 'Vérification anti-robot',
    'question' => 'Combien font',
    'captchaverif' => 'Vérification simple, sans pistage et sans recours à Google.',
    'confirmation' => 'Confirmer ma commande',

    // upload errors

    'uploadfailed' => 'Échec du téléversement',
    'filetoolarge' => 'Fichier trop volumineux',
    'invalidimage' => 'Image non valide',
    'imagetoosmall' => 'Image trop petite',
    'movefailed' => 'Échec du déplacement',

    // privacy

    'privacy' => 'Confidentialité',
    'safety' => 'Nous ne conservons pas vos informations, conformément aux règles de la CNIL.',

    // mosaics

    'uploadfirst' => 'Veuillez d’abord téléverser une image',
    'genmosaic' => 'Voici vos mosaïques générées',
    'styleprefer' => 'Choisissez le style que vous préférez. Il s’agit d’aperçus fictifs avec différents traitements de couleurs.',
    'blueaccent' => 'Aperçu accent bleu',
    'blueaccent2' => 'Accent bleu',
    'redaccent' => 'Aperçu accent rouge',
    'bwaccent' => 'Aperçu noir et blanc accentué',
    'bwaccent2' => 'Noir et blanc accentué',
    'validatechoice' => 'Valider mon choix',
    'return' => 'Retour',

    // erreurs d’accès

    'access' => '403 - Accès refusé.',
    'admin' => 'Vous devez être administrateur pour accéder à cette page.',

    // admin - orders.php

    'backoffice' => 'Back-office - Commandes',
    'getback' => 'Retour au site',
    'dberrcon' => 'La connexion à la base de données est indisponible.',
    'noorders' => 'Aucune commande pour le moment.',
    'status' => 'Statut',
    'amount' => 'Montant',
    'details' => 'Voir les détails',

    // Mail lors d'une connexion
    'mail_login_subject' => 'Connexion à votre compte img2brick',
    'mail_login_body' => 'Bonjour,<br><br>Une connexion à votre compte img2brick vient d’avoir lieu.<br>
    Voici les informations liées à cette connexion : <br>
    <ul>
    <li><strong>Date :</strong> {{date}}</li>
    <li><strong>Adresse email :</strong> {{email}}</li>
    <li><strong>Adresse IP :</strong> {{ip}}</li>
    </ul>
    Si cette connexion vient de vous, aucune action n’est nécessaire.<br>
    Si ce n’est pas le cas, nous vous recommandons de modifier votre mot de passe rapidement.<br><br>
    À bientôt sur img2brick !<br><br>
    <small>Ceci est un message automatique. Merci de ne pas y répondre.</small>',

    // Mail lors d'une inscription
    'mail_register_subject' => 'Bienvenue sur img2brick',
    'mail_register_body' => 'Bonjour friend,<br><br>Votre compte img2brick a bien été créé avec l’adresse suivante :<br>
    <strong>{{email}}</strong><br><br>
    Vous pouvez maintenant vous connecter et commencer à transformer vos photos en oeuvres LEGO.<br><br>
    Nous vous recommandons de garder cette adresse e mail à jour et de choisir un mot de passe sécurisé.<br><br>
    À très bientôt sur img2brick !<br><br>
    <small>Ceci est un message automatique. Merci de ne pas y répondre.</small>',

    'upload_click' => 'Cliquez pour ajouter une image',
    'upload_subtext' => 'Drag and drop',
    'welcome' => 'Bienvenue',
    'myorders' => 'Mes commandes',
    'hereare' => 'Voici vos commandes',
    'placedorder' => 'Pas de commandes.',
    'start' => 'Nouvelle mosaique',
    'commande' => 'Commande',
    'taille' => 'Taille',
    'account_section_identity' => 'Identité',
    'account_section_billing' => 'Adresse de livraison',
    'save_changes' => 'Sauvegarder',
    'myaccount' => 'Mon compte',
    'account_updated' => 'Modification réussie',
    'drag_drop_title' => 'Glissez-déposez votre image ici',
    'drag_drop_subtitle' => 'Formats acceptés : JPG • PNG • WEBP — Taille min. 512×512 px',
    'drag_drop_button' => 'Choisir un fichier',
    'drag_drop_none' => 'Aucun fichier sélectionné',
    'results_title' => 'Voici vos mosaïques générées',
    'results_subtitle' => 'Choisissez le style que vous préférez. Ce sont des aperçus (mock) avec différents         traitements de couleur.',
    'results_board_title' => 'Choisissez la taille du plateau',
    'results_board_subtitle' => 'Cette taille sera utilisée pour votre mosaïque LEGO®.',
    'results_board_32' => '32 × 32 pixels - Petit',
    'results_board_64' => '64 × 64 pixels - Standard',
    'results_board_96' => '96 × 96 pixels - Grand',
    'results_meta_blue' => '~ 24 couleurs · Est. 99€',
    'results_meta_red' => '~ 20 couleurs · Est. 95€',
    'results_meta_bw' => '~ 8 couleurs · Est. 89€',
'2fa_subject' => 'Votre code de connexion (2FA)',
'2fa_body' => 'Voici votre code : <b>{{code}}</b><br>Il expire dans {{minutes}} minute.',
'2fa_title' => 'Vérification en 2 étapes',
'2fa_subtitle' => 'Un code vient d’être envoyé à :',
'2fa_code_label' => 'Code à 6 chiffres',
'2fa_hint' => 'Le code est valable 1 minute.',
'2fa_verify_btn' => 'Vérifier',
'2fa_back_login' => 'Retour à la connexion',
'2fa_missing' => 'Aucune vérification 2FA en cours. Reconnectez-vous.',
'2fa_expired' => 'Code expiré. Reconnectez-vous pour en recevoir un nouveau.',
'2fa_invalid' => 'Code incorrect.',
'2fa_toomany' => 'Trop de tentatives. Reconnectez-vous.',
'2fa_timer_label' => 'Prochain code',
'2fa_resend' => 'Envoyer un nouveau code',
'forgot_title' => 'Mot de passe oublié',
'forgot_subtitle' => 'Saisissez votre email. Si un compte existe, vous recevrez un lien de réinitialisation.',
'forgot_btn' => 'Envoyer le lien',
'forgot_success' => 'Si un compte correspond à cet email, un lien de réinitialisation a été envoyé.',
'back_login' => 'Retour à la connexion',

'reset_subject' => 'Réinitialisation de votre mot de passe',
'reset_body' => 'Cliquez sur ce lien pour réinitialiser votre mot de passe :<br><a href="{{link}}">{{link}}</a><br><br>Ce lien expire dans {{minutes}} minutes.',
'reset_title' => 'Réinitialiser le mot de passe',
'reset_new_password' => 'Nouveau mot de passe',
'reset_confirm_password' => 'Confirmer le mot de passe',
'reset_btn' => 'Mettre à jour',
'reset_invalid' => 'Lien invalide ou expiré. Veuillez refaire une demande.',
'reset_success' => 'Mot de passe mis à jour avec succès.',
'mosaicc' => 'Votre mosaic est en préparation.'



];
