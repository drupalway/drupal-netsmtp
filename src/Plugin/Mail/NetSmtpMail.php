<?php

/**
 * Net_SMTP mailer.
 *
 * Beware that this class awaits an already formatted MIME mail as input.
 * This means you probably need another mail formatter system such as the MimeMail
 * module in order to generate the fully compliant MIME version.
 *
 * Most functions are protected, so that anyone that wants to change some
 * behavior can still extend this object and modify whatever they want.
 */
namespace Drupal\netsmtp\Plugin\Mail;

use Net_SMTP;
use PEAR;
use PEAR_Error;
use Drupal\Core\Mail\MailInterface;

/**
 * Implements Net_SMTP mail plugin with additional log features.
 *
 * @Mail(
 *   id = "netsmtp_mail",
 *   label = @Translation("NetSMTP Mailer"),
 *   description = @Translation("SMTP connector using Net_SMTP PEAR library.")
 * )
 */
class NetSmtpMail implements MailInterface {
  /**
   * Default provider key
   */
  const PROVIDER_DEFAULT = 'default';

  /**
   * Default SSL port
   */
  const DEFAULT_SSL_PORT = 465;

  /**
   * Drupal to explode regex
   */
  const REGEX_TO = '';

  /**
   * @var PEAR
   */
  private $PEAR;

  private $config;

  /**
   * Default constructor
   */
  public function __construct() {
    // Fuck hate PEAR.
    $this->PEAR = new PEAR();
    $this->config = \Drupal::config('netsmtp.settings');
  }

  /**
   * Attempt to find email addresses from input
   *
   * @param string $string
   *
   * @return string[]
   *
   * @see MailManager::mail()
   *   For input of what this function is supposed to accept.
   */
  protected function catchAddressesInto($string) {
    $ret = array();

    if (empty($string)) {
      return null; // Please bitch... Not my problem
    }

    // This should be enough to remove "Name".
    $string = preg_replace('/"[^\"]+"/', '', $string);

    // And thus, there is no risk anymore to find ',' except as separator
    foreach (explode(",", $string) as $addr) {
      $m = array();
      if (preg_match('/<([^\>]+)?>/', $addr, $m)) {
          $ret[] = trim($m[1]);
      } else {
          $ret[] = trim($addr);
      }
    }

    return $ret;
  }

  /**
   * Format an error and send it to logger service.
   *
   * @param mixed $e
   *
   * @param string $type
   *   Types: error or warning.
   */
  protected function setError($e, $type = 'error') {
    if (is_string($e)) {
      $message = $e;
    }
    elseif ($e instanceof PEAR_Error) {
      // God PEAR is so 90's, but I have to use it because no other
      // viable PHP SMTP library exists outside of Net_SMTP. Even
      // the Roundcube webmail client understood it.
      if ($debug = $e->getDebugInfo()) {
        $message = $e->getMessage() . ', DEBUG:<br/><pre>' . print_r($debug, true) . '</pre>';
      }
      else {
        $message = $e->getMessage();
      }
    }
    elseif ($e instanceof Exception) {
      $message = 'Exception ' . get_class($e) . ': ' . $e->getMessage() . '<br/><pre>' . $e->getTraceAsString() . '</pre>';
    }
    else {
      $message = 'UNKNOWN ERROR, DEBUG:<br/><pre>' . print_r($e, true) . '</pre>';
    }

    if (in_array($type, array('error', 'warning'))) {
      \Drupal::logger('netsmtp')->{$type}($message);
    }
  }

  /**
   * Get Net_SMTP instance
   *
   * Returned instance must be authenticated and connected.
   *
   * @return Net_SMTP
   *   Or null if instance could not be created or could not connect
   *   to SMTP server
   */
  protected function getInstance($module, $key) {
    $isTLS = false;

    // SMTP server configurations per module, key.
    $server_id_list = array(
      $module . '.' . $key,
      $module,
      $key,
      self::PROVIDER_DEFAULT
    );

    foreach($server_id_list as $provider) {
      $provider_config = $this->config->get($provider);
      if (!is_null($provider_config)) {
        break;
      }
    }


    if (empty($provider_config)) {
      $this->setError(sprintf("Provider '%s' does not exists, fallback on default", $provider), 'warning');
      return null;
    }

    if (empty($provider_config['hostname'])) {
      $this->setError(sprintf("Provider '%s' has no hostname", $provider));
      return null;
    }

    $info = array_filter($provider_config) + array(
      'port'      => null,
      'username'  => null,
      'use_ssl'   => false,
      'password'  => '',
      'localhost' => null,
    );

    if ($info['use_ssl']) {
      if ('tls' === $info['use_ssl']) {
          $info['hostname'] = 'tls://' . $info['hostname'];
          $isTLS = true;
      } else {
          $info['hostname'] = 'ssl://' . $info['hostname'];
      }
      if (empty($info['port'])) {
          $info['port'] = self::DEFAULT_SSL_PORT;
      }
    }

    // Attempt connection
    $smtp = new Net_SMTP($info['hostname'], $info['port'], $info['localhost']);
    if ($this->PEAR->isError($e = $smtp->connect())) {
      $this->setError($e);
      return null;
    }

    if (!empty($info['username'])) {
      if ($this->PEAR->isError($e = $smtp->auth($info['username'], $info['password'], '', $isTLS))) {
        $this->setError($e);
        return null;
      }
    }

    // Finally! We did it I guess.
    return $smtp;
  }

  /**
   * {@inheritdoc}
   */
  public function format(array $message) {
    \Drupal::logger('netsmtp')->error("I am not meant to format messages, sorry");
      return false;
    }

  /**
   * {@inheritdoc}
   */
  public function mail(array $message) {
    if (!$smtp = $this->getInstance($message['module'], $message['key'])) {
      return false;
    }

    // SMTP basically does not care about message format. MIME is the
    // standard for everybody, so just prey that the previous formatter
    // did it right, but in all cases, we don't have to attempt any
    // formatting ourselves, this would be an serious error vulgaris
    // that every one seem to do... God I hate people.
    if (empty($message['body'])) {
      \Drupal::logger('netsmtp')->warning("Sending an empty mail");
      $message['body'] = '';
    }

    if (is_array($message['body'])) {
      $message['body'] = implode("\n", $message['body']);
    }

    if (empty($message['headers']['Subject'])) {
      if ($this->config->get('netsmtp_subject_encode', true)) {
        $message['headers']['Subject'] = mime_header_encode($message['subject']);
      }
      else {
        $message['headers']['Subject'] = $message['subject'];
      }
    }

    $from = $this->catchAddressesInto($message['from']);
    $from = reset($from);

    if (empty($from)) {
      $this->setError("FROM invalid or not found");
      return false;
    }
    if ($this->PEAR->isError($e = $smtp->mailFrom($from))) {
      $this->setError($e);
      return false;
    }

    $atLeastOne = false;
    foreach ($this->catchAddressesInto($message['to']) as $to) {
      if ($this->PEAR->isError($e = $smtp->rcptTo($to))) {
        $this->setError($e);
      } else {
        $atLeastOne = true;
      }
    }
    if (!$atLeastOne) {
      $this->setError("No RCPT was accepted by the SMTP server");
      return false;
    }

    // Also note that the Net_SMTP library wants headers to be a string too
    $headers = array();
    foreach ($message['headers'] as $name => $value) {
      if (is_array($value)) {
        foreach ($value as $_value) {
          $headers[] = $name . ": " . $_value;
        }
      } else {
        $headers[] = $name . ": " . $value;
      }
    }

    // And the ugly part is, append body like a real Viking would do!
    $status = true;

    if ($this->PEAR->isError($e = $smtp->data($message['body'], implode("\n", $headers)))) {
      $this->setError($e);
      $status = false;
    }

    if ($this->config->get('netsmtp_debug_mime')) {
        $path = $this->config->get('netsmtp_debug_mime_path');
        $path .= '/' . date('Y-m-d');
        file_prepare_directory($path, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
        file_put_contents($path . '/' . $smtp->host . '-' . date('Y_m_d-H_i_s'). '.mbox', implode("\n", $headers) . "\n\n" . $message['body']);
    }

    $smtp->disconnect();
    return $status;
  }
}
