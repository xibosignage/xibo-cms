ALTER TABLE  `layout` ADD  `width` DECIMAL NOT NULL ,
ADD  `height` DECIMAL NOT NULL ,
ADD  `backgroundColor` VARCHAR( 25 ) NULL ,
ADD  `schemaVersion` TINYINT NOT NULL;

/* Take existing permissions and pull them into the permissions table */
INSERT INTO `permission` (`groupId`, `entityId`, `objectId`, `objectIdString`, `view`, `edit`, `delete`)
SELECT groupId, 4, NULL, CONCAT(LayoutId, '_', RegionID, '_', MediaID), view, edit, del
  FROM `lklayoutmediagroup`;

DROP TABLE `lklayoutmediagroup`;

INSERT INTO `permission` (`groupId`, `entityId`, `objectId`, `objectIdString`, `view`, `edit`, `delete`)
SELECT groupId, 1, campaignId, NULL, view, edit, del
  FROM `lkcampaigngroup`;

DROP TABLE `lkcampaigngroup`;

INSERT INTO `permission` (`groupId`, `entityId`, `objectId`, `objectIdString`, `view`, `edit`, `delete`)
  SELECT groupId, 3, NULL, CONCAT(LayoutId, '_', RegionID), view, edit, del
  FROM `lklayoutregiongroup`;

DROP TABLE `lklayoutregiongroup`;

INSERT INTO `permission` (`groupId`, `entityId`, `objectId`, `objectIdString`, `view`, `edit`, `delete`)
  SELECT groupId, 6, mediaId, NULL, view, edit, del
  FROM `lkmediagroup`;

DROP TABLE `lkmediagroup`;
/* End permissions swap */

DROP TABLE `lklayoutmedia`;

ALTER TABLE  `log` CHANGE  `type`  `type` VARCHAR( 254 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

UPDATE `version` SET `app_ver` = '1.8.0-alpha', `XmdsVersion` = 4, `XlfVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '120';