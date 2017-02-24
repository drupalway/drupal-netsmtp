# Net SMTP (Drupal 8)

SMTP connector using Net_SMTP PEAR library.

## But why?

Drupal contrib already has a nice SMTP module called "SMTP" which can be found
at the following URL:

    http://www.drupal.org/project/smtp

But in real life, this module attempts to use the [PHPMailer library](https://github.com/PHPMailer/PHPMailer) to connect
to the SMTP server.

If you look at it a bit more, you'll see that PHPMailer is not an SMTP
connector, while it can, its main goal is to format the MIME messages for
you.

Whenever you use Drupal with a module such as [MIMEMail](http://www.drupal.org/project/mimemail),
you'll notice that your messages are already well formatted in a very precise
and valid MIME envelope.

*What happens behind this scenario is that the SMTP module needs to deconstruct
the valid MIME encoded message in order to be able to use the PHPMailer API
which then will attempt to rebuild a MIME message.*

In real life, it does not, it does deconstruct your MIME encoded message, but in
a very wrong way, and breaks it in a lot of cases.

## Requirements

[Mailsystem](https://www.drupal.org/project/mailsystem)

Used to provide different backends for formatting and sending e-mails by default, per module and per mail key.

Soft dependency on [composer_manager](https://www.drupal.org/project/composer_manager)
 
## Installation

### Installation on [Drupal Project](https://github.com/drupal-composer/drupal-project)

- In root folder add dependencies to composer.json:

        composer require drupal/netsmtp drupal/mailsystem

- Go to `web/` folder and enable netsmtp module:

        drush en netsmtp -y

### Installation on standard drupal

This module defines external non-drupal dependencies via composer.json, 
so you can use a drupal module [composer_manager](https://www.drupal.org/project/composer_manager) to install dependencies.

**Procedure**

- Download this module, composer_manager, mailsystem modules to the `modules/contrib` dir or similar:

        drush pmi netsmtp, composer_manager, mailsystem -y

- Enable composer_manager module && run install script && clear cache:

        drush en composer_manager -y && php PATH_TO_COMPOSER_MANAGER_MODULE/scripts/init.php && drush cr

- Enable netsmtp & mailsystem modules:

        drusn en netsmtp, mailsystem -y

- Update root `composer.json` file:

        composer drupal-update


## Runtime configuration

### Drupal mail system configuration

This module uses a Mailsystem module as mail manager which lets you use different
formatter and mailer.

#### Sender settings
In order to use this module as a sender (mailer), simply add to your `settings.php` or `settings.local.php` file:

	$config['mailsystem.settings']['defaults']['sender'] = 'netsmtp_mail';

If you want to use this mailer for some module that construct an email or specific mail key just set this

	/* For all mails that constructed by user module */
	$config['mailsystem.settings']['modules']['user']['sender'] = 'netsmtp_mail';
	/* Only for mail that constructed by user module and has a key password_reset
	$config['mailsystem.settings']['modules']['user']['password_reset']['sender'] = 'netsmtp_mail';

see more `core/modules/user/user.module/user_mail`, `core/modules/user/config/install/user.mail.yml`

See more for overriding algorithm `\Drupal\Core\Mail\MailManager::mail`

#### Formatter settings

You can set the formatter this way:

	$config['mailsystem.settings']['defaults']['formatter'] = 'php_mail';

### SMTP configuration

At minimal you would need to specify your SMTP server host:

    $config['netsmtp.settings']['providers'] = [
      'default' => [
        'hostname' => '1.2.3.4'
      ],
    ];

Hostname can be an IP or a valid hostname.

In order to work with SSL, just add the 'use_ssl' key with true or false.

You can set the port if you wish using the 'port' key.

If you need authentication, use this:

    $config['netsmtp.settings']['providers'] = [
      'default' => [
        'hostname' => 'smtp.provider.net',
        'username' => 'john',
        'password' => 'foobar',
      ],
    ];

And additionnaly, if you need to advertise yourself as a different hostname
than the current localhost.localdomain, you can set the 'localhost' variable.

An complete example:

    $config['netsmtp.settings']['providers'] = [
      'default' => [
        'hostname'  => 'smtp.provider.net',
        'port'      => 465,
        'use_ssl'   => true,
        'username'  => 'john',
        'password'  => 'foobar',
        'localhost' => 'host.example.tld',
      ],
    ];

Note that for now this only supports the PLAIN and LOGIN authentication
methods, I am definitely too lazy to include the Auth_SASL PEAR package
as well.

Additionally, you can change the 'use_ssl' paramater to the 'tls' value
instead, and hope for the best to happen, it should force the Net::SMTP
library to do a TLS connection instead.

### Advanced SMTP configuration

Additionally you can define a set of servers, for example if you need a
mailjet or mandrill connection:

    $config['netsmtp.settings']['providers'] = [
      'default' => [
        'host' => '1.2.3.4',
        'ssl'  => true,
      ),
      'mailjet' => [
        'host' => '1.2.3.4',
        'ssl'  => true,
        'user' => 'john',
        'pass' => 'foobar',
      ],
    ];

You can then force mails to go throught another server than default by
settings per mail module/key `$config['netsmtp.settings']['module']['key'][...]`

See more here `\Drupal\netsmtp\Plugin\Mail\NetSmtpMail::getInstance`

### Additional configuration

Per default this module uses the Drupal native function correctly encode
mail subjects, if you use a formatter that does the job for you, set
the _netsmtp\_subject\_encode_ to false to deactivate this behavior:

    $config['netsmtp.settings']['netsmtp_subject_encode'] = false;

### Debugging

#### Re-route all outgoing mail

This feature is useful when working in a development phase where you don't
want mails to be sent to their real recipients. In order to activate it
just set:

    $config['netsmtp.settings']['netsmtp_catch'] = 'someuser@example.com';

Moreover, you can set multiple recipients:

    $config['netsmtp.settings']['netsmtp_catch'] = [
      'user1@example.com',
      'user2@example.com',
      'user3@example.com',
      // ...
    ];

Be careful that this is a debug feature and the recipient user addresses
won't be processed in any way, which means that you can set a mail address
containing a ',' character, it won't be escaped.

#### Sent data dumping

Additionally you can enable a debug output that will dump all MIME encoded
messages this module will send onto the file system. Just set:

    $config['netsmtp.settings']['netsmtp_debug_mime'] = true;

And every mail will be dumped into the following Drupal temp folder:

    temporary://netsmtp/YYYY-MM-DD/

Additionally you can change the path using this variable:

    $config['netsmtp.settings']['netsmtp_debug_mime_path'] = 'private://netsmtp';

#### Sent mail trace

This probably should belong to another module, but if you need extensive mail
tracing logging, you can enable:

    $config['netsmtp.settings']['netsmtp_debug_trace'] = true;

This will activate a _hook\_mail\_alter()_ implementation that will log every
mail activity sent by the platform in a single file:

    temporary://netsmtp/netsmtp-trace-YYYY-MM-DD.log

In this file you'll find various internal Drupal modules information about the
mails being sent, including the stack trace at the time the mail is beint sent.

Additionally you can change the path using this variable:

    $config['netsmtp.settings']['netsmtp_debug_trace_path'] = 'private://netsmtp';
