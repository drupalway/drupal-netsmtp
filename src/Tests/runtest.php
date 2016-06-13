<?php

require_once './SendEmailViaSmtp.php';

$mailer = new \Drupal\netsmtp\Tests\SendEmailViaSmtp();

$mailer->sendEmail();
