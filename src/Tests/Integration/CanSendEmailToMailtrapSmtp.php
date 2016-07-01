<?php

namespace Drupal\netsmtp\Tests\Integration;

use GuzzleHttp\Client;

define('NETSMTP_MAILTRAP_API_ENDPOINT', 'https://mailtrap.io/api/v1');

class CanSendEmailToMailtrapSmtp  {
  private $kernel;

  private $smtp_token;

  private $smtp_inbox_id;

  public function __construct($kernel) {
    $this->kernel = $kernel;
    $this->mailManager = $this->kernel->getContainer()->get('plugin.manager.mail');
    $this->smtp_inbox_id = getenv('MAILTRAP_INBOX_ID');
    $this->smtp_token = getenv('MAILTRAP_TOKEN');
  }
  
  public function testSendEmail() {
    
    try {
      $result = $this->mailManager->mail('netsmtp', 'test_message');
      $message_key = \Drupal::state()->get('netsmtp.last_message_id');
    }
    catch (\Exception $e) {
      throw new \RuntimeException(sprintf('Can\'t send an email. Details: %s', $e->getMessage()));
    }

    $inbox_url = implode('/', array(
      NETSMTP_MAILTRAP_API_ENDPOINT,
      'inboxes', 
      $this->smtp_inbox_id
    ));
    
    $client = new Client([
      'base_uri' => $inbox_url . '/'
    ]);
    
    $response = $client->request('GET', 'messages', [
      'query'   => ['search' => $message_key],
      'headers' => ['Api-Token' => $this->smtp_token]
    ]);

    $data = \GuzzleHttp\json_decode($response->getBody()->getContents());
    $mail = reset($data);

    if (404 == $response->getStatusCode()) {
      throw new \ErrorException(sprintf('Can\'t find a email with email subject: %s', $message_key));
    }

    if ($mail->subject == $message_key) {
      return;
    }
    else {
      throw new \ErrorException(sprintf('There is no email with email subject: %s', $message_key));
    }
  }
}

