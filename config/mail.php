<?php
define('MAIL_HOST',      getenv('MAIL_HOST')      ?: 'smtp.gmail.com');
define('MAIL_PORT',      (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_USERNAME',  getenv('MAIL_USERNAME')  ?: '');
define('MAIL_PASSWORD',  getenv('MAIL_PASSWORD')  ?: '');
define('MAIL_FROM',      getenv('MAIL_FROM')      ?: '');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Mero Gadi');
