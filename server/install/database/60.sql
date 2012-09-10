INSERT INTO `help` (`HelpID`, `Topic`, `Category`, `Link`) 
    VALUES (NULL, 'Transition', 'General', 'http://wiki.xibo.org.uk/wiki/Manual:Media:Transitions'), 
    (NULL, 'Transition', 'Edit', 'http://wiki.xibo.org.uk/wiki/Manual:Media:Transitions#Edit');

INSERT INTO `setting` (`settingid`, `setting`, `value`, `type`, `helptext`, `options`, `cat`, `userChange`) VALUES 
    (NULL, 'TRANSITION_CONFIG_LOCKED_CHECKB', 'Unchecked', 'dropdown', 'Is the Transition config locked?', 'Checked|Unchecked', 'general', '0');

INSERT INTO `pages` (`pageID`, `name`, `pagegroupID`) VALUES (NULL, 'transition', '4');

INSERT INTO `menuitem` (`MenuID`, `PageID`, `Args`, `Text`, `Class`, `Img`, `Sequence`)
SELECT 8, `PageID`, NULL, 'Transitions', NULL, NULL, 6
  FROM `pages`
 WHERE `name` = 'transition';

CREATE TABLE  `transition` (
`TransitionID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`Transition` VARCHAR( 254 ) NOT NULL ,
`Code` VARCHAR( 50 ) NOT NULL ,
`SupportsDuration` TINYINT NOT NULL ,
`SupportsDirection` TINYINT NOT NULL ,
`AvailableAsIn` TINYINT NOT NULL ,
`AvailableAsOut` TINYINT NOT NULL
) ENGINE = MYISAM ;


UPDATE `version` SET `app_ver` = '1.5.0', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '60';