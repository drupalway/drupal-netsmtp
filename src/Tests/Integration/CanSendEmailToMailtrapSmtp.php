<?php
/**
 * @file
 * Class CanSendEmailToMailtrapSmtp.
 */

namespace Drupal\netsmtp\Tests\Integration;

use Drupal\Core\DrupalKernel;
use GuzzleHttp\Client;

define('NETSMTP_MAILTRAP_API_ENDPOINT', 'https://mailtrap.io/api/v1');

/**
 * Class CanSendEmailToMailtrapSmtp.
 *
 * @package Drupal\netsmtp\Tests\Integration
 */
class CanSendEmailToMailtrapSmtp {
  /**
   * Drupal Kernel.
   *
   * @var DrupalKernel
   */
  private $kernel;
  /**
   * Token value.
   *
   * @var string
   */
  private $smtpToken;
  /**
   * Inbox ID.
   *
   * @var string
   */
  private $smtpInboxId;
  /**
   * Mail manager.
   *
   * @var \Drupal\Core\Mail\MailManager
   */
  private $mailManager;

  /**
   * CanSendEmailToMailtrapSmtp constructor.
   *
   * @param DrupalKernel $kernel
   *   Drupal Kernel.
   */
  public function __construct(DrupalKernel $kernel) {
    // Prepare the initial properties.
    $this->kernel       = $kernel;
    $this->mailManager  = $this->kernel->getContainer()->get('plugin.manager.mail');
    $this->smtpInboxId  = getenv('MAILTRAP_INBOX_ID');
    $this->smtpToken    = getenv('MAILTRAP_TOKEN');
  }

  /**
   * Test email sending.
   */
  public function testSendEmail() {
    try {
      $result = $this->mailManager->mail('netsmtp', 'test_message');
      $message_key = \Drupal::state()->get('netsmtp.last_message_id');
    }
    catch (\RuntimeException $e) {
      echo sprintf('Can\'t send an email. Details: %s', $e->getMessage());
      exit(1);
    }

    $inbox_url = implode('/', [
      NETSMTP_MAILTRAP_API_ENDPOINT,
      'inboxes',
      $this->smtpInboxId,
    ]);

    $client = new Client([
      'base_uri' => $inbox_url . '/',
    ]);

    $response = $client->request('GET', 'messages', [
      'query'   => ['search' => $message_key],
      'headers' => ['Api-Token' => $this->smtpToken],
    ]);

    $data = \GuzzleHttp\json_decode($response->getBody()->getContents());
    $mail = reset($data);

    if (404 == $response->getStatusCode()) {
      echo sprintf('Can\'t find a email with email subject: %s', $message_key);
      exit(1);
    }

    if ($mail->subject != $message_key) {
      sprintf('There is no email with email subject: %s', $message_key);
      exit(1);
    }
  }

}
