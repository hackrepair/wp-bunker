<?php
/**
 * Send Bunker's three emails to a local Mailpit so they can be read in a mail client view.
 *
 * Not a test and not shipped in the release zip (scripts/ is excluded). Run on a machine with Mailpit listening:
 *   php scripts/mailpit-preview.php [http://127.0.0.1:8025]
 * Every message is tagged "thrg-bunker" and sent from wordpress@thrg-bunker.test.
 */
require __DIR__ . '/../tests/bootstrap.php';
thrg_load_plugin();

$base = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : 'http://127.0.0.1:8025';
$to   = 'admin@thrg-bunker.test';

$GLOBALS['opts']['admin_email']      = $to;
$GLOBALS['current_user_obj']         = (object) array( 'ID' => 1, 'user_email' => $to );
$GLOBALS['mails']                    = array();

// 1. Welcome.
THRG_Bunker_Welcome::send_once();

// 2. Hatch approval.
$method = new ReflectionMethod( 'THRG_Bunker_Hatch', 'send_mail' );
$method->setAccessible( true );
$method->invoke(
    null,
    array(
        'created' => time(),
        'ip'      => '203.0.113.77',
        'agent'   => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Safari/605.1.15',
    ),
    str_repeat( 'ab', 24 )
);

// 3. Stalled scheduled tasks.
THRG_Bunker_Options::update( array( 'cron_email' => 1 ) );
update_option( THRG_Bunker_Cron::LAST, 1 );
update_option( THRG_Bunker_Cron::SINCE, 1 );
THRG_Bunker_Cron::maybe_email();

$sent = 0;
foreach ( $GLOBALS['mails'] as $mail ) {
    $payload = json_encode(
        array(
            'From'    => array( 'Email' => 'wordpress@thrg-bunker.test', 'Name' => 'WordPress' ),
            'To'      => array( array( 'Email' => $mail[0] ) ),
            'Subject' => $mail[1],
            'Text'    => $mail[2],
            'Tags'    => array( 'thrg-bunker' ),
        )
    );
    $ctx = stream_context_create(
        array(
            'http' => array(
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 10,
            ),
        )
    );
    $res = @file_get_contents( $base . '/api/v1/send', false, $ctx );
    echo ( false === $res ? 'FAILED  ' : 'sent    ' ) . $mail[1] . "\n";
    $sent += false === $res ? 0 : 1;
}
echo $sent . ' of ' . count( $GLOBALS['mails'] ) . " messages sent to $base\n";
