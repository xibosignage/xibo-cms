/* Add a setting to turn the display name into a VNC link */
INSERT INTO `setting` (`setting`, `value`, `type`, `helptext`, `cat`, `userChange`) VALUES
  ('SHOW_DISPLAY_AS_VNC_TGT', '_top', 'text', 'If the display name is shown as a link in display management, what target should the link have? Set _top to open the link in the same window or _blank to open in a new window.','general','1');

/* Add always alert setting so that you can receive only one email when a screen goes offline and another when it comes back */
INSERT INTO `setting` (`setting`, `value`, `type`, `helptext`, `options`, `cat`, `userChange`) VALUES
  ('MAINTENANCE_ALWAYS_ALERT', 'Off', 'dropdown', 'Should Xibo send an email if a display is in an error state every time the maintenance script runs?','On|Off','maintenance','1');

/* Add a setting to enable schedule lookahead */
INSERT INTO `setting` (`setting`, `value`, `type`, `helptext`, `options`, `cat`, `userChange`) VALUES
  ('SCHEDULE_LOOKAHEAD','On','dropdown','Should Xibo send future schedule information to clients?','On|Off','general','0');

/* Add a setting to configure how far in to the future RequiredFiles (and optionally Schedule) lookahead */
INSERT INTO `setting` (`setting`, `value`, `type`, `helptext`, `cat`, `userChange`) VALUES
  ('REQUIRED_FILES_LOOKAHEAD','172800','text','How many seconds in to the future should the calls to RequiredFiles look?','general','1');

/* VERSION UPDATE */
/* Set the version table, etc */
UPDATE `version` SET `app_ver` = '1.2.0-rc2', `XmdsVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '24';
