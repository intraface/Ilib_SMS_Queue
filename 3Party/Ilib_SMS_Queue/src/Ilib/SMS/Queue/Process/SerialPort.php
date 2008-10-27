<?php
/**
 * Class for processing sms queue with SerialPort
 *
 * PHP Version 5
 *
 * @category SMS
 * @package  Ilib_SMS_Queue
 * @author   Sune Jensen <sj@sunet.dk>
 * @author   Lars Olesen <lars@legestue.net>
 * @version  @@VERSION@@
 */

/**
 * Class for processing sms queue with SerialPort
 *
 * @category SMS
 * @package  Ilib_SMS_Queue
 * @author   Sune Jensen <sj@sunet.dk>
 * @author   Lars Olesen <lars@legestue.net>
 * @version  @@VERSION@@
 */
class Ilib_SMS_Queue_Process_SerialPort
{

    /**
     * @var object MDB2 object
     */
    private $db;

    /**
     * @var object serialport
     */
    private $serialport;

    /**
     * @var boolean debug
     */
    private $debug;

    /**
     * Constructor
     */
    public function __construct($db, $serialport, $debug = false)
    {
        $this->db = $db;
        $this->serialport = $serialport;
        $this->debug = $debug;

        // sets the way to send message.
        $this->serialport->sendMessage('AT+CMGF=1'.chr(13));
        if ($this->debug) {
            echo "Message mode set \n";
        }
    }

    public function execute($run_time = 60, $max_attempts = 3)
    {
        $start_time = time();

        $result = $this->getMessageQueryResult($max_attempts);

        if ($this->debug) echo $result->numRows() . " messages initialized \n\n";

        // a 15 seconds span is given to send the last message.
        while(($row = $result->fetchRow(MDB2_FETCHMODE_ASSOC)) && $start_time + $run_time - 20 > time()) {

            $insert_status = $this->db->exec('INSERT INTO ilib_sms_queue_attempt SET ilib_sms_queue_id = '.$row['id'].', date_started = NOW()');
            if (PEAR::isError($insert_status)) {
                throw new Exception('Error in query: '.$insert_status->getUserInfo());
            }
            $status_id = $this->db->lastInsertID('ilib_sms_queue_attempt', 'id');

            $this->serialport->sendMessage('AT+CMGS="+45'.$row['recipient'].'"'.chr(13));
            $this->serialport->sendMessage($row['message'].chr(26).chr(13), 1);

            if ($this->debug) echo "Message send. Waiting for response";

            $response_start_time = time();
            $success = 0;

            $response = $this->parseResponse($this->serialport->readPort());
            if ($this->isResponseOk($response)) {
                $success = 1;
            }

            // make sure response is not more than 255 chars long to save in db.
            $response = substr($response, 0, 255);

            $update_attempt = $this->db->exec('UPDATE ilib_sms_queue_attempt SET date_ended = NOW(), status = '.$this->db->quote($response, 'text').' WHERE id = '.$this->db->quote($status_id, 'integer'));
            if (PEAR::isError($update_attempt)) {
                throw new Exception('Error in query: '.$update_attempt->getUserInfo());
            }

            $update_queue = $this->db->exec('UPDATE ilib_sms_queue SET is_sent = '.$this->db->quote($success, 'integer').', attempt = attempt + 1 WHERE id = '.$this->db->quote($row['id'], 'integer'));
            if (PEAR::isError($update_queue)) {
                throw new Exception('Error in query: '.$update_queue->getUserInfo());
            }

            if ($this->debug) {
                echo "Status updated\n\n";
            }

            // @todo this is a hack to make it work
            $this->serialport->deviceClose();
            $this->serialport->deviceOpen();
        }
    }

    private function isResponseOk($response)
    {
        return ($response == 'OK');
    }

    /**
     * returns the sms to send
     */
    public function getMessageCount($max_attempts = 3)
    {
        return $this->getMessageQueryResult($max_attempts)->numRows();
    }

    private function getMessageQueryResult($max_attempts)
    {
        $result = $this->db->query('SELECT * FROM ilib_sms_queue WHERE is_sent = 0 AND attempt <= '.intval($max_attempts).' ORDER BY date_queued ASC');

        if (PEAR::isError($result)) {
            throw new Exception('Error in query: '.$result->getUserInfo());
        }
        return $result;
    }

    /**
     * Returns the last line of the response
     */
    private function parseResponse($response)
    {
        $retur = trim(substr($response, strrpos($response, PHP_EOL) - 2));
        if ($this->debug) {
            echo "\n\nRecieved response\n";
            echo $response . "\n\n";
            echo "Parsed response\n\n";
            echo $retur;
        }

        return $retur;
    }
}