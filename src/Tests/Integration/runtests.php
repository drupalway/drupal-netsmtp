<?php
/**
 * @file
 * Test runner.
 */

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

$drupal_dir = getenv('DRUPAL_DIR');

//static $kernel;

/*if (!isset($kernel) || !($kernel instanceof DrupalKernel)) {
  require_once $drupal_dir . '/core/includes/database.inc';
  require_once $drupal_dir . '/core/includes/schema.inc';

  $autoloader = require_once $drupal_dir . '/autoload.php';
  $request    = Request::createFromGlobals();

  $kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
  $kernel->boot();
  $kernel->prepareLegacyRequest($request);
}*/

//require_once './CanSendEmailToMailtrapSmtp.php';
$mailer = new \Drupal\netsmtp\Tests\Integration\CanSendEmailToMailtrapSmtp();
$mailer->testSendEmail();
