
ALTER TABLE  `module` ADD  `render_as` VARCHAR( 10 ) NULL;
ALTER TABLE  `module` ADD  `settings` TEXT NULL;

UPDATE `resolution` SET enabled = 0;

ALTER TABLE  `resolution` ADD  `version` TINYINT NOT NULL DEFAULT  '1';
ALTER TABLE  `resolution` ADD  `enabled` TINYINT NOT NULL DEFAULT  '1';
ALTER TABLE  `resolution` CHANGE  `resolution`  `resolution` VARCHAR( 254 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

INSERT INTO `resolution` (`resolutionID`, `resolution`, `width`, `height`, `intended_width`, `intended_height`, `version`, `enabled`) VALUES
(9, '1080p HD Landscape', 800, 450, 1920, 1080, 2, 1),
(10, '720p HD Landscape', 800, 450, 1280, 720, 2, 1),
(11, '1080p HD Portrait', 450, 800, 1080, 1920, 2, 1),
(12, '720p HD Portrait', 450, 800, 720, 1280, 2, 1),
(13, '4k', 800, 450, 4096, 2304, 2, 1),
(14, 'Common PC Monitor 4:3', 800, 600, 1024, 768, 2, 1);

DELETE FROM `lktemplategroup` WHERE TemplateID IN (SELECT TemplateID FROM `template` WHERE isSystem = 1);
DELETE FROM `template` WHERE isSystem = 1;

ALTER TABLE `template` DROP `isSystem`;

ALTER TABLE  `display` ADD  `displayprofileid` INT NULL;

INSERT INTO `pages` (`name`, `pagegroupID`)
SELECT 'displayprofile', pagegroupID FROM `pagegroup` WHERE pagegroup.pagegroup = 'Displays';

INSERT INTO `menuitem` (MenuID, PageID, Args, Text, Class, Img, Sequence, External)
SELECT 7, PageID, NULL, 'Display Settings', NULL, NULL, 4, 0
  FROM `pages`
 WHERE name = 'displayprofile';

UPDATE `version` SET `app_ver` = '1.7.0-alpha', `XmdsVersion` = 4;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '80';
