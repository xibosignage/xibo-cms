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
(1, 'Fade In', 'fadeIn', 1, 0, 0, 0),
(2, 'Fade Out', 'fadeOut', 1, 0, 0, 0),
(3, 'Fly', 'fly', 1, 1, 0, 0);

INSERT INTO `setting` (`settingid`, `setting`, `value`, `type`, `helptext`, `options`, `cat`, `userChange`) VALUES (NULL, 'GLOBAL_THEME_NAME', 'default', 'text', 'The Theme to apply to all pages by default', NULL, 'general', '1');

INSERT INTO `pages` (`pageID`, `name`, `pagegroupID`) VALUES (NULL, 'timeline', '3');

/* Get all of the current permission links between pages in the Layouts group and add a duplicate for the new time line page  */
INSERT INTO `lkpagegroup` (`pageID`, `groupID`)
SELECT DISTINCT newpage.pageID, lkpagegroup.groupID
  FROM `lkpagegroup`
    INNER JOIN `pages`
    ON pages.pageID = lkpagegroup.pageID
    CROSS JOIN (
      SELECT pageID
        FROM `pages`
       WHERE `name` = 'timeline'
    ) newpage
   WHERE pages.pagegroupID = 3;

UPDATE `module` SET `ImageUri` = REPLACE(ImageUri, 'img/forms/', 'forms/') WHERE ImageUri IS NOT NULL;

UPDATE `menuitem` SET `Img` = REPLACE(Img, 'img/dashboard/', 'dashboard/') WHERE Img IS NOT NULL;

TRUNCATE TABLE `resolution`;

ALTER TABLE  `resolution` ADD  `intended_width` SMALLINT NOT NULL ,
ADD  `intended_height` SMALLINT NOT NULL;

INSERT INTO `resolution` (`resolutionID`, `resolution`, `width`, `height`, `intended_width`, `intended_height`) VALUES
(1, '4:3 Monitor', 800, 600, 1024, 768),
(2, '3:2 Tv', 720, 480, 1440, 960),
(3, '16:10 Widescreen Mon', 800, 500, 1680, 1050),
(4, '16:9 HD Widescreen', 800, 450, 1920, 1080),
(5, '3:4 Monitor', 600, 800, 768, 1024),
(6, '2:3 Tv', 480, 720, 960, 1440),
(7, '10:16 Widescreen', 500, 800, 1050, 1680),
(8, '9:16 HD Widescreen', 450, 800, 1080, 1920);

INSERT INTO `menuitem` (`MenuID`, `PageID`, `Args`, `Text`, `Class`, `Img`, `Sequence`)
SELECT 9, `PageID`, NULL, 'Help Links', NULL, NULL, 6
  FROM `pages`
 WHERE `name` = 'help';

UPDATE `pages` SET name = 'log' WHErE name = 'report';
INSERT INTO `pages` (`pageID`, `name`, `pagegroupID`) VALUES (NULL, 'sessions', '9');

UPDATE `menuitem` SET Args = NULL, PageID = (SELECT PageID FROM `pages` WHERE name = 'sessions' LIMIT 1) WHERE PageID = (SELECT PageID FROM `pages` WHERE name = 'log' LIMIT 1) AND `Args` = 'sp=sessions';
UPDATE `menuitem` SET Args = NULL WHERE PageID = (SELECT PageID FROM `pages` WHERE name = 'log' LIMIT 1) AND `Args` = 'sp=log';
UPDATE `menuitem` SET `Text` = 'About' WHERE `Text` = 'License';

UPDATE `version` SET `app_ver` = '1.5.0', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '60';