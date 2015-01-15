-- phpMyAdmin SQL Dump
-- version 2.11.7
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Dec 10, 2008 at 10:57 PM
-- Server version: 5.0.45
-- PHP Version: 5.2.4

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

-- From server-pingback branch.
INSERT INTO `setting` (
`settingid` ,
`setting` ,
`value` ,
`type` ,
`helptext` ,
`options` ,
`cat` ,
`userChange`
)
VALUES (
NULL , 'PHONE_HOME', 'On', 'dropdown', 'Should the server send anonymous statistics back to the Xibo project?', 'On|Off', 'general', '1'
);

INSERT INTO `setting` (
`settingid` ,
`setting` ,
`value` ,
`type` ,
`helptext` ,
`options` ,
`cat` ,
`userChange`
)
VALUES (
NULL , 'PHONE_HOME_KEY', 'DEMO_KEY', 'text', 'Key used to distinguish each Xibo instance. This is generated randomly based on the time you first installed Xibo, and is completely untraceable.', NULL , 'general', '0'
);

INSERT INTO `setting` (
`settingid` ,
`setting` ,
`value` ,
`type` ,
`helptext` ,
`options` ,
`cat` ,
`userChange`
)
VALUES (
NULL , 'PHONE_HOME_URL', 'http://www.xibo.org.uk/stats/track.php', 'text', 'The URL to connect to to PHONE_HOME (if enabled)', NULL , 'path', '0'
);

INSERT INTO `setting` (
`settingid` ,
`setting` ,
`value` ,
`type` ,
`helptext` ,
`options` ,
`cat` ,
`userChange`
)
VALUES (
NULL , 'PHONE_HOME_DATE', '0', 'text', 'The last time we PHONED_HOME in seconds since the epoch', NULL , 'general', '0'
);

UPDATE `version` SET `app_ver` = '1.0.0';

UPDATE `version` SET `DBVersion` = '2';
