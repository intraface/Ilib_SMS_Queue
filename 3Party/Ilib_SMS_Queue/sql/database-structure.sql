 CREATE TABLE `ilib_sms_queue` (
`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`sender` VARCHAR( 255 ) NOT NULL ,
`message` TEXT NOT NULL ,
`recipient` VARCHAR( 255 ) NOT NULL ,
`date_queued` DATETIME NOT NULL ,
`is_sent` TINYINT( 1 ) NOT NULL DEFAULT '0',
`attempt` INT NOT NULL DEFAULT '0'
) ENGINE = MYISAM ;

CREATE TABLE `ilib_sms_queue_attempt` (
`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`ilib_sms_queue_id` INT NOT NULL ,
`date_started` DATETIME NOT NULL ,
`date_ended` DATETIME NOT NULL ,
`status` VARCHAR( 255 ) NOT NULL
) ENGINE = MYISAM ;


ALTER TABLE `ilib_sms_queue_attempt` ADD INDEX ( `ilib_sms_queue_id` );
ALTER TABLE `ilib_sms_queue_attempt` CHANGE `date_ended` `date_ended` DATETIME NOT NULL DEFAULT '0000-00-00';
ALTER TABLE `ilib_sms_queue_attempt` CHANGE `date_started` `date_started` DATETIME NOT NULL DEFAULT '0000-00-00';
