<?php
/**
 * Created by PhpStorm.
 * User: vladdancer
 * Date: 6/13/16
 * Time: 13:29
 */

namespace Drupal\netsmtp\Tests;

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;
use GuzzleHttp\Client;

define('DRUPAL_DIR', '/var/www/drupalvm/drupal');


class SendEmailViaSmtp  {
  
  protected function getDrupal() {
    if ($this->kernel instanceof DrupalKernel) {
      return $this->kernel;
    }
    
    require_once DRUPAL_DIR . '/core/includes/database.inc';
    require_once DRUPAL_DIR . '/core/includes/schema.inc';

    $autoloader = require_once DRUPAL_DIR . '/autoload.php';
    $request    = Request::createFromGlobals();

    $kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
    $kernel->boot();
    $kernel->prepareLegacyRequest($request);
    return $kernel;
  }

  public function __construct() {
    $this->kernel = $this->getDrupal();
    $this->mailManager = $this->kernel->getContainer()->get('plugin.manager.mail');
  }
  
  public function sendEmail() {

    $this->mailManager = $this->kernel->getContainer()->get('plugin.manager.mail');


    $result = $this->mailManager->mail('netsmtp', 'test_message');

    $message_key = \Drupal::state()->get('netsmtp.last_message_id');

    $client = new Client(['base_uri' => 'https://mailtrap.io/api/v1/inboxes/***REMOVED***/']);
    $response = $client->request('GET', 'messages', [
      'query' => ['search' => $message_key],
      'headers' => ['Api-Token' => '***REMOVED***']
    ]);

    $data = \GuzzleHttp\json_decode($response->getBody()->getContents());
    $mail = reset($data);

    if ($mail->subject == $message_key) {
      var_dump('WOOOHOOO!');
    }
    else {
      var_dump('Nope!');

    }
  }


}

