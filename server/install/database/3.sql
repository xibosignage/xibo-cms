UPDATE `setting` SET `type` = 'timezone' WHERE `setting`.`settingid` =11 LIMIT 1 ;

-- Default Layout
INSERT INTO `layout` (`layoutID`, `layout`, `permissionID`, `xml`, `userID`, `createdDT`, `modifiedDT`, `description`, `tags`, `templateID`, `retired`, `duration`, `background`) VALUES (NULL, 'Default Layout', '3', '<?xml version="1.0"?>
<layout schemaVersion="1" width="800" height="500" bgcolor="#000000"><region id="47ff29524ce1b" width="800" height="500" top="0" left="0"><media id="ba2b90ee2e21f9aaffbc45f253068c60" type="text" duration="20" lkid="" schemaVersion="1">
					<options><direction>none</direction></options>
					<raw><text><![CDATA[<h1 style="text-align: center;"><span style="font-size: 2em;"><span style="font-family: Verdana;"><span style="color: rgb(255, 255, 255);">Welcome to </span></span></span></h1><h1 style="text-align: center;"><span style="font-size: 2em;"><span style="font-family: Verdana;"><span style="color: rgb(255, 255, 255);">Xibo! </span></span></span></h1><h1 style="text-align: center;"><span style="font-size: 2em;"><span style="font-family: Verdana;"><span style="color: rgb(255, 255, 255);">Open Source Digital Signage</span></span></span></h1><p style="text-align: center;"><span style="font-family: Verdana;"><span style="color: rgb(255, 255, 255);"><span style="font-size: 1.6em;">This is the default layout - please feel free to change it whenever you like!</span></span></span></p>]]></text></raw>
				</media><media id="7695b17df85b666d420c232ee768ef68" type="ticker" duration="100" lkid="" schemaVersion="1">
					<options><direction>up</direction><uri>http://xibo.org.uk/feed/</uri></options>
					<raw><template><![CDATA[<h2 style="text-align: center;"><span style="color: rgb(255, 255, 255);"><span style="font-size: 1.6em;"><u><span style="font-size: 1.8em;">[Title]</span></u></span></span></h2><p><span style="color: rgb(255, 255, 255);"><span style="font-size: 1.6em;">[Description]</span></span></p><p>&nbsp;</p><p>&nbsp;</p>]]></template></raw>
				</media></region></layout>
', '1', NOW(), NOW(), NULL, NULL, NULL, '0', '0', NULL);

ALTER TABLE `log` CHANGE `page` `page` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL  ;

UPDATE `version` SET `DBVersion` = '3';
