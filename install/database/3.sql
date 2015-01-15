UPDATE `setting` SET `type` = 'timezone' WHERE `setting`.`settingid` =11 LIMIT 1 ;

-- Default Layout
INSERT INTO `layout` (`layoutID`, `layout`, `permissionID`, `xml`, `userID`, `createdDT`, `modifiedDT`, `description`, `tags`, `templateID`, `retired`, `duration`, `background`) VALUES (NULL, 'Default Layout', '3', '<?xml version="1.0"?><layout schemaVersion="1" width="800" height="450" bgcolor="#000000"><region id="47ff29524ce1b" width="800" height="401" top="0" left="0" userId="1"><media id="522caef6e13cb6c9fe5fac15dde59ef7" type="text" duration="15" lkid="" userId="1" schemaVersion="1">
                            <options><xmds>1</xmds><direction>none</direction><scrollSpeed>2</scrollSpeed><fitText>0</fitText></options>
                            <raw><text><![CDATA[<p style="text-align: center;"><strong><span style="font-family:arial,helvetica,sans-serif;"><span style="font-size:72px;"><span style="color:#FFFFFF;">Welcome to&nbsp;<br />
Xibo</span></span></span></strong></p>

<p style="text-align: center;"><span style="font-size:48px;"><span style="font-family:arial,helvetica,sans-serif;"><span style="color:#FFFFFF;">Open Source Digital Signage</span></span></span></p>

<p style="text-align: center;"><span style="color:#D3D3D3;"><span style="font-size:26px;"><span style="font-family:arial,helvetica,sans-serif;">This is the default layout - please feel free to change it whenever you like.</span></span></span></p>
]]></text></raw>
                    </media></region><region id="53654d56726e0" userId="1" width="194" height="48" top="402" left="609"><media id="11846d5d9f686fb75fc9dad0b19ca9de" type="text" duration="10" lkid="" userId="1" schemaVersion="1">
                            <options><xmds>1</xmds><direction>none</direction><scrollSpeed>2</scrollSpeed><fitText>0</fitText></options>
                            <raw><text><![CDATA[<p style="text-align: right;"><span style="font-size:24px;"><span style="font-family:arial,helvetica,sans-serif;"><span style="color:#D3D3D3;">[Clock]</span></span></span></p>
]]></text></raw>
                    </media></region></layout>', '1', NOW(), NOW(), NULL, NULL, NULL, '0', '0', NULL);

ALTER TABLE `log` CHANGE `page` `page` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL  ;

UPDATE `version` SET `DBVersion` = '3';
