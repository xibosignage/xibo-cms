/* Add new settings for the maintenance script. The upgrade will populate values for the user */
INSERT INTO `setting` (`setting`, `value`, `type`, `helptext`, `options`, `cat`, `userChange`) VALUES
  ('MAINTENANCE_ENABLED', 'Off', 'dropdown', 'Allow the maintenance script to run if it is called?','On|Off','maintenance','1'),
  ('MAINTENANCE_EMAIL_ALERTS', 'Off', 'dropdown', 'Global switch for email alerts to be sent','On|Off','maintenance','1'),
  ('MAINTENANCE_LOG_MAXAGE', '0', 'text', 'Maximum age for log entries. Set to 0 to keep logs indefinitely.',NULL,'maintenance','1'),
  ('MAINTENANCE_STAT_MAXAGE', '0', 'text', 'Maximum age for statistics entries. Set to 0 to keep statistics indefinitely.',NULL,'maintenance','1');

/* Unhide the email settings that exist already and move them to the maintenance tab */
UPDATE `setting` SET `cat` = 'maintenance', `userChange` = '1' WHERE (`setting` = 'mail_to' OR `setting` = 'mail_from');

/* Change the default email address from support@xibo.org.uk :D */
UPDATE `setting` SET `value` = 'admin@yoursite.com' WHERE `setting` = 'mail_to' LIMIT 1;

/* Add a column to the display table to store email alert settings */
ALTER TABLE `display` ADD `email_alert` TINYINT(1) NOT NULL DEFAULT '1';


/* VERSION UPDATE */
/* Set the version table, etc */
UPDATE `version` SET `app_ver` = '1.3.0';
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '30';
