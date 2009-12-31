INSERT INTO `pages` (
`pageID` ,
`name` ,
`pagegroupID`
)
VALUES (
28 , 'manual', '2'
);

INSERT INTO `menuitem` (`MenuItemID`, `MenuID`, `PageID`, `Args`, `Text`, `Class`, `Img`, `Sequence`)
VALUES (NULL, 2, 28, 'http://wiki.xibo.org.uk/wiki/Manual:TOC', 'Manual', 'help_button', 'img/dashboard/help.png', '10');


UPDATE `module` SET `ValidExtensions` = 'ppt,pps,pptx' WHERE `module`.`ModuleID` =4 LIMIT 1 ;>>>>>>> MERGE-SOURCE
