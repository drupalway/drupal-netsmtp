<?php
/**
 * @file
 * Class CanSendEmailToMailtrapSmtp.
 */

namespace Drupal\netsmtp\Tests\Integration;

use Drupal\Core\DrupalKernel;
use GuzzleHttp\Client;

define('NETSMTP_MAILTRAP_API_ENDPOINT', 'https://mailtrap.io/api/v1');
define('NETSMTP_MAILTRAP_SMTP_HOSTNAME', 'mailtrap.io');
define('NETSMTP_MAILTRAP_SMTP_PORT', 465);

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
  private $apiToken;
  /**
   * Inbox ID.
   *
   * @var string
   */
  private $apiInboxId;
  /**
   * Mail manager.
   *
   * @var \Drupal\Core\Mail\MailManager
   */
  private $mailManager;

  /**
   * CanSendEmailToMailtrapSmtp constructor.
   */
  public function __construct() {
    // Prepare the initial properties.
    $this->apiInboxId   = getenv('MAILTRAP_INBOX_ID');
    $this->apiToken     = getenv('MAILTRAP_TOKEN');
    $this->smtpUserName = getenv('MAILTRAP_SMTP_USERNAME');
    $this->smtpPassword = getenv('MAILTRAP_SMTP_PASSWORD');

    // We need this before initMailManager(),
    // to properly construct a netsmtp_mail plugin.
    $this->createSmtpConfig(
      $this->smtpUserName,
      $this->smtpPassword
    );
    $this->initMailManager();
  }

  /**
   * Construct mailtrap smtp config object.
   *
   * This object later will be used by NetSmtpMail::__construct().
   */
  private function createSmtpConfig() {
    \Drupal::configFactory()
      ->getEditable('mailsystem.settings')
      ->set('defaults.sender', 'netsmtp_mail')
      ->set('defaults.formatter','php_mail')
      ->save();

    \Drupal::configFactory()
      ->getEditable('netsmtp.settings')
      ->set('providers.netsmtp.test_message.hostname', NETSMTP_MAILTRAP_SMTP_HOSTNAME)
      ->set('providers.netsmtp.test_message.port', NETSMTP_MAILTRAP_SMTP_PORT)
      ->set('providers.netsmtp.test_message.use_ssl', FALSE)
      ->set('providers.netsmtp.test_message.username', $this->smtpUserName)
      ->set('providers.netsmtp.test_message.password', $this->smtpPassword)
      ->save();
  }

  /**
   * Get current mail manager.
   */
  private function initMailManager() {
    $this->mailManager = \Drupal::getContainer()->get('plugin.manager.mail');
  }

  /**
   * Test email sending.
   */
  public function testSendEmail() {
    try {
      $this->mailManager->mail('netsmtp', 'test_message', 'netsmtp@example.com', []);
    }
    catch (\RuntimeException $e) {
      file_put_contents('php://stderr', $e->getTraceAsString());
      file_put_contents('php://stderr', sprintf('Can\'t send an email. Details: %s', $e->getMessage()));
      exit(1);
    }

    $message_key = \Drupal::state()->get('netsmtp.last_message_id');

    $inbox_url = implode('/', [
      NETSMTP_MAILTRAP_API_ENDPOINT,
      'inboxes',
      $this->apiInboxId,
    ]);

    $client = new Client([
      'base_uri' => $inbox_url . '/',
      'headers' => ['Api-Token' => $this->apiToken],
    ]);

    $response = $client->request('GET', 'messages', [
      'query'   => ['search' => $message_key],
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
    var_dump('mail_id: ' . $mail->id);
    $response = $client->request('GET', "messages/$mail->id/body.raw");
    var_dump($data = ($response->getBody()->getContents()));
  }

}
