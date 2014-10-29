CREATE TABLE IF NOT EXISTS `tag` (
  `tagId` int(11) NOT NULL AUTO_INCREMENT,
  `tag` varchar(50) NOT NULL,
  PRIMARY KEY (`tagId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=4 ;

INSERT INTO `tag` (`tagId`, `tag`) VALUES
(1, 'template'),
(2, 'background'),
(3, 'thumbnail');

CREATE TABLE IF NOT EXISTS `lktaglayout` (
  `lkTagLayoutId` int(11) NOT NULL AUTO_INCREMENT,
  `tagId` int(11) NOT NULL,
  `layoutId` int(11) NOT NULL,
  PRIMARY KEY (`lkTagLayoutId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf32 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `lktagmedia` (
  `lkTagMediaId` int(11) NOT NULL AUTO_INCREMENT,
  `tagId` int(11) NOT NULL,
  `mediaId` int(11) NOT NULL,
  PRIMARY KEY (`lkTagMediaId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf16 AUTO_INCREMENT=1 ;

/* Clear the un-used template id field */
UPDATE `layout` SET templateID = NULL;

/* Insert into layout, populating the template id */
INSERT INTO `layout` (layout, xml, userID, createdDT, modifiedDT, description, tags, retired, templateID)
SELECT template, xml, userID, createdDT, modifiedDT, description, tags, retired, templateID
  FROM template;

/* Add the template tag */
INSERT INTO lktaglayout (tagId, layoutId)
SELECT 1, layoutId
  FROM `layout`
 WHERE templateId IS NOT NULL;

/* Add two temporary columns to the campaign table */
ALTER TABLE  `campaign` ADD  `layoutId` INT NOT NULL ,
	ADD  `templateId` INT NOT NULL;

/* Insert into campaign */
INSERT INTO `campaign` (campaign, islayoutspecific, userid, layoutid, templateid)
SELECT layout, 1, userid, layoutid, templateId
  FROM layout
 WHERE templateId IS NOT NULL;

/* Link up */
INSERT INTO `lkcampaignlayout` (CampaignID, LayoutId)
SELECT CampaignID, LayoutId
  FROM `campaign`
 WHERE templateid <> 0

/* Permissions */
INSERT INTO `lkcampaigngroup` (CampaignID, GroupID, View, Edit, Del)
SELECT campaign.CampaignID, groupid, view, edit, del
  FROM lktemplategroup
  	INNER JOIN campaign
  	ON lktemplategroup.templateid = campaign.templateid;

/* Tidy up */
ALTER TABLE `campaign`
  DROP `layoutId`,
  DROP `templateId`;

DROP TABLE  `lktemplategroup`;
DROP TABLE  `template`;

ALTER TABLE `layout` DROP `templateID`;

/* Tags from the layout table */
INSERT INTO tag (tag)
SELECT tags
  FROM (
    SELECT DISTINCT
      SUBSTRING_INDEX(SUBSTRING_INDEX(layout.tags, ' ', numbers.n), ' ', -1) tags
    FROM
      (
          SELECT 1 n  UNION ALL 
          SELECT 2 UNION ALL 
          SELECT 3  UNION ALL 
          SELECT 4  UNION ALL 
          SELECT 5  UNION ALL 
          SELECT 6  UNION ALL 
          SELECT 7  UNION ALL 
          SELECT 8  UNION ALL 
          SELECT 9  UNION ALL 
          SELECT 10  UNION ALL 
          SELECT 11 UNION ALL 
          SELECT 12 UNION ALL 
          SELECT 13 UNION ALL 
          SELECT 14 UNION ALL 
          SELECT 15
      ) numbers INNER JOIN layout
      ON CHAR_LENGTH(layout.tags)
         -CHAR_LENGTH(REPLACE(layout.tags, ' ', ''))>=numbers.n-1
    ORDER BY
      layoutid, n
    ) tags
 WHERE tags.tags NOT IN (SELECT tag FROM tag);

INSERT INTO lktaglayout (tagId, layoutId)
SELECT tagid, layoutid
  FROM tag
    INNER JOIN (
        SELECT
          layout.layoutid,
          SUBSTRING_INDEX(SUBSTRING_INDEX(layout.tags, ' ', numbers.n), ' ', -1) tag
        FROM
          (
              SELECT 1 n  UNION ALL 
              SELECT 2 UNION ALL 
              SELECT 3  UNION ALL 
              SELECT 4  UNION ALL 
              SELECT 5  UNION ALL 
              SELECT 6  UNION ALL 
              SELECT 7  UNION ALL 
              SELECT 8  UNION ALL 
              SELECT 9  UNION ALL 
              SELECT 10  UNION ALL 
              SELECT 11 UNION ALL 
              SELECT 12 UNION ALL 
              SELECT 13 UNION ALL 
              SELECT 14 UNION ALL 
              SELECT 15
          ) numbers INNER JOIN layout
          ON CHAR_LENGTH(layout.tags)
             -CHAR_LENGTH(REPLACE(layout.tags, ' ', ''))>=numbers.n-1
        ORDER BY
          layoutid, n
    ) tagFromLayout
    ON tagFromLayout.tag = tag.tag;

ALTER TABLE `layout` DROP `tags`;

INSERT INTO  `setting` (`setting` ,`value` ,`fieldType` ,`helptext` ,`options` ,`cat` ,`userChange` ,`title` ,`validation` ,`ordering` ,`default` ,`userSee` ,`type`)
VALUES ('DEFAULTS_IMPORTED',  '0',  'text',  'Has the default layout been imported?', NULL ,  'general',  '0',  'Defaults Imported?',  'required',  '100',  '0',  '0',  'checkbox');

ALTER TABLE  `display` CHANGE  `Cidr`  `Cidr` VARCHAR( 6 ) NULL DEFAULT NULL;

UPDATE `version` SET `app_ver` = '1.7.0-beta', `XmdsVersion` = 4, `XlfVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '82';