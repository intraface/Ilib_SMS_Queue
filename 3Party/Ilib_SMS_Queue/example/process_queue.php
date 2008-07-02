<?php

define('DB_DSN', 'mysql://root:@localhost/test');
define('DEBUG', 'false');
define('MAX_EXECUTION_TIME', 120); /* seconds */
define('SERIAL_DEVICE', 'COM4');

ini_set('max_execution_time', MAX_EXECUTION_TIME);

require_once('Ilib/SerialPort.php');
$serial = new Ilib_SerialPort();
$serial->deviceSet(SERIAL_DEVICE); 
$serial->confBaudRate('9600');
$serial->confCharacterLength(8);
$serial->confParity('none');
$serial->confStopBits (1);
$serial->deviceOpen();

require_once('MDB2.php');
$db = MDB2::factory(DB_DSN);

require_once('Ilib/SMS/Queue/Process/SerialPort.php');
$process = new Ilib_SMS_Queue_Process_SerialPort($db, $serial, DEBUG);
$process->execute(MAX_EXECUTION_TIME - 2); /* 2 seconds less to ensure excution stops before */

$serial->deviceClose();

echo "Queue processed!\n";

?>