<?php
require_once 'config.test.php';
require_once 'PHPUnit/Framework.php';

PHPUnit_Util_Filter::addDirectoryToWhitelist(realpath(dirname(__FILE__) . '/../src/'));

require_once 'MDB2.php';
require_once '../src/Ilib/SMS/Queue.php';

class SMSQueueTest extends PHPUnit_Framework_TestCase
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
        $sms = $this->createSMSQueue();
        $this->assertTrue(is_object($sms));
    }
    
    function testSetMessageReturnsTrueOnValidMessage() 
    {
        $sms = $this->createSMSQueue();
        $this->assertTrue($sms->setMessage('This is a test message! also with רזו'));
    }
    
    function testSetMessageReturnsFalseOnTooLongMessage() 
    {
        $sms = $this->createSMSQueue();
        $this->assertFalse($sms->setMessage('10 charac!10 charac!10 charac!10 charac!' .
                '10 charac!10 charac!10 charac!10 charac!' .
                '10 charac!10 charac!10 charac!10 charac!' .
                '10 charac!10 charac!10 charac!10 charac!' .
                '10 charac!'));
        
    }
    
    function testAddRecipientReturnsTrueOnValidNumber() 
    {
        $sms = $this->createSMSQueue();
        $this->assertTrue($sms->addRecipient('11223344'));
        
    }
    
    function testAddRecipientReturnsFalseOnTooLongNumber() 
    {
        $sms = $this->createSMSQueue();
        $this->assertFalse($sms->addRecipient('1122334455'));
        
    }
    
    function testSendReturnsTrueOnValidData()
    {
        $sms = $this->createSMSQueue();
        $sms->setMessage('This is a test message! also with רזו');
        $sms->addRecipient('11223344');
        $this->assertTrue($sms->send());
    }
    
    function testSendReturnsFalseOnIncompleteMessage()
    {
        $sms = $this->createSMSQueue();
        $sms->setMessage('This is a test message! also with רזו');
        $this->assertFalse($sms->send());
    }
    
    function testSendSavesDataToDatabase()
    {
        $sms = $this->createSMSQueue();
        $sms->setMessage('This is a test message! also with רזו');
        $sms->addRecipient('11223344');
        $sms->send();
        
        $result = $this->db->query('SELECT * FROM ilib_sms_queue');
        $this->assertEquals(1, $result->numRows());
        
        $row = $result->fetchAll();
        $expected = array(
            0 => array(
                0 => 1,
                1 => 'tester', 
                2 => "This is a test message! also with רזו",
                3 => "11223344",
                4 => $row[0][4],
                5 => 0,
                6 => 0
            )
        );
        $this->assertEquals($expected, $row);
        
    }
    
    function testSendSavesTwoMessagesToDatabaseWhenTwoRecipient()
    {
        $sms = $this->createSMSQueue();
        $sms->setMessage('This is a test message! also with רזו');
        $sms->addRecipient('11223344');
        $sms->addRecipient('11223366');
        $sms->send();
        
        $result = $this->db->query('SELECT * FROM ilib_sms_queue');
        $this->assertEquals(2, $result->numRows());
        
        $row = $result->fetchAll();
        $expected = array(
            0 => array(
                0 => 1,
                1 => 'tester', 
                2 => "This is a test message! also with רזו",
                3 => "11223344",
                4 => $row[0][4],
                5 => 0,
                6 => 0
            ),
            1 => array(
                0 => 2,
                1 => 'tester', 
                2 => "This is a test message! also with רזו",
                3 => "11223366",
                4 => $row[1][4],
                5 => 0,
                6 => 0
            )
        );
        $this->assertEquals($expected, $row);
        
    }  
    
    function testGetErrorMessage()
    {
        $sms = $this->createSMSQueue();
        $sms->addRecipient('1122334455');
        $this->assertEquals('Invalid recepient. The number is not 8 numerix characters', $sms->getErrorMessage());
    }  
    
    

    ///////////////////////////////////////////////////////////////////////////////

    function createSMSQueue()
    {
        return new Ilib_SMS_Queue($this->db, 'tester');
    }
}
?>