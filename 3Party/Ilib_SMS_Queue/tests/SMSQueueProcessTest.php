<?php
require_once 'config.test.php';
require_once 'PHPUnit/Framework.php';

PHPUnit_Util_Filter::addDirectoryToWhitelist(realpath(dirname(__FILE__) . '/../src/'));

require_once 'MDB2.php';
require_once '../src/Ilib/SMS/Queue/Process/SerialPort.php';

class FakeSerialPort
{
    
    private $read_call = 0;
    private $device = '';
    public $response = 'OK';
    
    public function sendMessage($message)
    {
        $this->device .= PHP_EOL . $message;
    }
    
    public function readPort()
    {
        if($this->read_call == 3) {
            $this->device .= PHP_EOL . $this->response;
            $this->read_call = 0;
        }
        $this->read_call++;
        return $this->device;
    }
}


class SMSQueueProcessTest extends PHPUnit_Framework_TestCase
{
    private $db;
    

    function setUp()
    {
        $this->db = MDB2::factory(DB_DSN);
        if (PEAR::isError($this->db)) {
            die($this->db->getUserInfo());
        }
        $result = $this->db->exec('DROP TABLE ilib_sms_queue');
        $result = $this->db->exec('DROP TABLE ilib_sms_queue_attempt');
        
        $sql = file_get_contents('../sql/database-structure.sql');
        $sql = split(';', $sql);
        
        $result = $this->db->exec($sql[0]);
        if (PEAR::isError($result)) {
            die($result->getUserInfo());
        }
        
        $result = $this->db->exec($sql[1]);
        if (PEAR::isError($result)) {
            die($result->getUserInfo());
        }
    }

    function testConstructor()
    {
        $process = new Ilib_SMS_Queue_Process_SerialPort($this->db, new FakeSerialPort);
        $this->assertTrue(is_object($process));
    }
    
    function testSendTwoMessagesWithSuccess()
    {
        $this->insertData(2);
        $process = new Ilib_SMS_Queue_Process_SerialPort($this->db, new FakeSerialPort, true);
        $process->execute();
        
        $result = $this->db->query('SELECT status FROM ilib_sms_queue_attempt')->fetchAll(MDB2_FETCHMODE_ASSOC);
        
        $this->assertEquals(array(array('status' => 'OK'), array('status' => 'OK')), $result);
        $this->assertEquals(0, $process->getMessageCount());
    }
    
    function testSendTwoMessagesWithError()
    {
        $this->insertData(2);
        $serial = new FakeSerialPort;
        $serial->response = 'ERROR: 433';
        $process = new Ilib_SMS_Queue_Process_SerialPort($this->db, $serial, true);
        $process->execute();
        
        $result = $this->db->query('SELECT status FROM ilib_sms_queue_attempt')->fetchAll(MDB2_FETCHMODE_ASSOC);
        
        $this->assertEquals(array(array('status' => 'ERROR: 433'), array('status' => 'ERROR: 433')), $result);
        $this->assertEquals(2, $process->getMessageCount());
    }
    
    function testSendMoreMessagesThanExecutionTime()
    {
        $this->insertData(3);
        $process = new Ilib_SMS_Queue_Process_SerialPort($this->db, new FakeSerialPort, true);
        $process->execute(12);
        
        $result = $this->db->query('SELECT status FROM ilib_sms_queue_attempt')->fetchAll(MDB2_FETCHMODE_ASSOC);
        
        $this->assertEquals(array(array('status' => 'OK')), $result);
        $this->assertEquals(2, $process->getMessageCount());
    }
    
    
    
    //////////////
    
    function insertData($max = 4)
    {
        require_once '../src/Ilib/SMS/Queue.php';
        $sms = new Ilib_SMS_Queue($this->db, 'tester');
        $sms->setMessage('This is a test message! also with רזו');
        for($i = 0; $i < $max; $i++) {
            $sms->addRecipient('1122334'.$i);
        }
        $sms->send();
    }
    
    

   
}
?>