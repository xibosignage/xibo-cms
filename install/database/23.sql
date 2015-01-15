/* Add new settings for the maintenance script. The upgrade will populate values for the user */
INSERT INTO `setting` (`setting`, `value`, `type`, `helptext`, `options`, `cat`, `userChange`) VALUES
  ('MAINTENANCE_ENABLED', 'Off', 'dropdown', 'Allow the maintenance script to run if it is called?','Protected|On|Off','maintenance','1'),
  ('MAINTENANCE_EMAIL_ALERTS', 'On', 'dropdown', 'Global switch for email alerts to be sent','On|Off','maintenance','1'),
  ('MAINTENANCE_KEY', 'changeme', 'text', 'String appended to the maintenance script to prevent malicious calls to the script.',NULL,'maintenance','1'),
  ('MAINTENANCE_LOG_MAXAGE', '30', 'text', 'Maximum age for log entries. Set to 0 to keep logs indefinitely.',NULL,'maintenance','1'),
  ('MAINTENANCE_STAT_MAXAGE', '30', 'text', 'Maximum age for statistics entries. Set to 0 to keep statistics indefinitely.',NULL,'maintenance','1'),
  ('MAINTENANCE_ALERT_TOUT', '12', 'text', 'How long in minutes after the last time a client connects should we send an alert? Can be overridden on a per client basis.',NULL,'maintenance','1');

/* Unhide the email settings that exist already and move them to the maintenance tab */
UPDATE `setting` SET `cat` = 'maintenance', `userChange` = '1' WHERE (`setting` = 'mail_to' OR `setting` = 'mail_from');

/* Change the default email address from support@xibo.org.uk :D */
UPDATE `setting` SET `value` = 'admin@yoursite.com' WHERE `setting` = 'mail_to' LIMIT 1;

/* Add a column to the display table to store email alert settings */
ALTER TABLE `display` ADD `email_alert` TINYINT(1) NOT NULL DEFAULT '1';
ALTER TABLE `display` ADD `alert_timeout` INT NOT NULL DEFAULT '0';

/* Add column for client IP to the display table */
ALTER TABLE  `display` ADD  `ClientAddress` VARCHAR( 100 ) NULL;

/* Add a setting to turn the display name into a VNC link */
INSERT INTO `setting` (`setting`, `value`, `type`, `helptext`, `cat`, `userChange`) VALUES
  ('SHOW_DISPLAY_AS_VNCLINK', '', 'text', 'Turn the display name in display management into a VNC link using the IP address last collected. The %s is replaced with the IP address. Leave blank to disable.','general','1');


/* VERSION UPDATE */
/* Set the version table, etc */
UPDATE `version` SET `app_ver` = '1.2.0-rc1', `XmdsVersion` = 1;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '23';
