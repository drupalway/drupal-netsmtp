<?php
/**
 * @file
 * Test runner.
 */

$mailer = new \Drupal\netsmtp\Tests\Integration\CanSendEmailToMailtrapSmtp();
$mailer->testSendEmail();
