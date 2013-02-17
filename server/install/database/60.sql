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

CREATE TABLE IF NOT EXISTS `transition` (
  `TransitionID` int(11) NOT NULL AUTO_INCREMENT,
  `Transition` varchar(254) NOT NULL,
  `Code` varchar(50) NOT NULL,
  `HasDuration` tinyint(4) NOT NULL,
  `HasDirection` tinyint(4) NOT NULL,
  `AvailableAsIn` tinyint(4) NOT NULL,
  `AvailableAsOut` tinyint(4) NOT NULL,
  PRIMARY KEY (`TransitionID`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=4 ;

INSERT INTO `transition` (`TransitionID`, `Transition`, `Code`, `HasDuration`, `HasDirection`, `AvailableAsIn`, `AvailableAsOut`) VALUES
(1, 'Fade In', 'fadeIn', 1, 0, 1, 0),
(2, 'Fade Out', 'fadeOut', 1, 0, 0, 1),
(3, 'Fly', 'fly', 1, 1, 1, 1);

INSERT INTO `setting` (`settingid`, `setting`, `value`, `type`, `helptext`, `options`, `cat`, `userChange`) VALUES (NULL, 'GLOBAL_THEME_NAME', 'default', 'text', 'The Theme to apply to all pages by default', NULL, 'general', '1');

INSERT INTO `pages` (`pageID`, `name`, `pagegroupID`) VALUES (NULL, 'timeline', '3');

UPDATE `module` SET `ImageUri` = REPLACE(ImageUri, 'img/forms/', 'theme/default/img/forms/') WHERE ImageUri IS NOT NULL;

ALTER TABLE  `resolution` ADD  `intended_width` INT NOT NULL ,
ADD  `intended_height` INT NOT NULL;

UPDATE `version` SET `app_ver` = '1.5.0', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '60';