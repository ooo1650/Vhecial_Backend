<?php
define('MAIL_HOST',      getenv('MAIL_HOST')      ?: 'smtp.gmail.com');
define('MAIL_PORT',      (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_USERNAME',  getenv('MAIL_USERNAME')  ?: '');
define('MAIL_PASSWORD',  getenv('MAIL_PASSWORD')  ?: '');
// For Resend free tier without a verified domain, use: onboarding@resend.dev
// Once you verify your domain, change this to your own email.
define('MAIL_FROM',      getenv('MAIL_FROM')      ?: 'onboarding@resend.dev');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Mero Gadi');
