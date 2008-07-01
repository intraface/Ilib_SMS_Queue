<?php
/**
 * Class for queuing sms to send through local gateway
 *
 * PHP Version 5
 *
 * @category SMS
 * @package  Ilib_SMS_Queue
 * @author   Sune Jensen <sj@sunet.dk>
 * @author   Lars Olesen <lars@legestue.net>
 */

/**
 * Class for queuing sms to send through local gateway
 *
 * <code>
 * $sms = new Ilib_SMS_Queue($db, 'my name');
 * $sms->setMessage('Test sms');
 * $sms->addRecipient('12345678');
 * $sms->addRecipient('87654321');
 * $sms->send();
 * </code>
 *
 * @category SMS
 * @package  Ilib_SMS_Queue
 * @author   Sune Jensen <sj@sunet.dk>
 * @author   Lars Olesen <lars@legestue.net>
 *
 */
class Ilib_SMS_Queue
{
    protected $message;
    protected $recipient;
    protected $sendername;
    protected $errormessage;
    protected $db;

    /**
     * constructor
     *
     * @param object $db MDB2 object
     * @param string $dbsendername
     */
    public function __construct($db, $sendername)
    {
        $this->db = $db;
        $this->sendername = $sendername; // Sendername

        $this->recipient = array();
        $this->errormessage = '';
    }

    /**
     * Sets the message to be send
     *
     * @param string message Maximum 459 characters
     * @return boolean true or false
     */
    public function setMessage($message)
    {
        if(empty($message)) {
            $this->setErrorMessage('The message is empty');
            return false;
        }
        
        /**
         * @todo: process the message. How long can it be...
         */

        if(strlen($message) > 160) {
            $this->setErrorMessage('The message is to long. Only 459 characters is allowed.');
            return false;
        }

        $this->message = $message;
        return true;
    }

    /**
     * Adds recipients to sms
     *
     * @param string recipient Only 8 numeric characters
     * @return boolean true or false
     */
    public function addRecipient($recipient)
    {
        if(!ereg("^[0-9]{8}$", $recipient)) {
            $this->setErrorMessage('Invalid recepient. The number is not 8 numerix characters');
            return false;
        }

        $this->recipient[] = $recipient;
        return true;
    }

    /**
     * Send sms
     *
     * @return boolean true on success
     */
    public function send()
    {
        if(empty($this->message)) {
            $this->setErrorMessage('The message is empty.');
            return false;
        }

        if(!is_array($this->recipient) || empty($this->recipient)) {
            $this->setErrorMessage('No recipients is given.');
            return false;
        }

        foreach($this->recipient AS $recipient) {
            
            $result = $this->db->exec('INSERT INTO ilib_sms_queue ' .
                'SET sender = '.$this->db->quote($this->sendername, 'text').', ' .
                'message = '.$this->db->quote($this->message, 'text').', ' .
                'recipient = '.$this->db->quote($recipient).', ' .
                'date_queued = NOW()'); 
            
            if(PEAR::isError($result)) {
                throw new Exception('Unable to save message. Got message: '.$result->getMessage().': '.$result->getUserInfo());
            }
        }
        
        return true;
    }

    /**
     * Sets an error message on error
     *
     * @param string error message
     * @return void
     */
    protected function setErrorMessage($message)
    {
        $this->errormessage = $message;
    }

    /**
     * Returns an error message after error
     *
     * @return string error message
     */
    public function getErrorMessage()
    {
        return $this->errormessage;
    }
}