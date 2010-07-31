/* Add a setting to turn the display name into a VNC link */
INSERT INTO `setting` (`setting`, `value`, `type`, `helptext`, `cat`, `userChange`) VALUES
  ('SHOW_DISPLAY_AS_VNC_TGT', '_top', 'text', 'If the display name is shown as a link in display management, what target should the link have? Set _top to open the link in the same window or _blank to open in a new window.','general','1');

/* VERSION UPDATE */
/* Set the version table, etc */
UPDATE `version` SET `app_ver` = '1.2.0-rc2', `XmdsVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '24';
