INSERT INTO `pages` (
`pageID` ,
`name` ,
`pagegroupID`
)
VALUES (
NULL , 'help', '2'
);

CREATE TABLE `help` (
`HelpID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`Topic` VARCHAR( 254 ) NOT NULL ,
`Category` VARCHAR( 254 ) NOT NULL DEFAULT 'General',
`Link` VARCHAR( 254 ) NOT NULL
) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci 