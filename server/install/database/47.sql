ALTER TABLE  `schedule_detail` CHANGE  `layoutID`  `CampaignID` INT( 11 ) NOT NULL;

ALTER TABLE  `schedule_detail` ADD FOREIGN KEY (  `CampaignID` ) REFERENCES  `campaign` (
`CampaignID`
) ON DELETE RESTRICT ON UPDATE RESTRICT ;

ALTER TABLE  `schedule` CHANGE  `layoutID`  `CampaignID` INT( 11 ) NOT NULL;

ALTER TABLE  `schedule` ADD FOREIGN KEY (  `CampaignID` ) REFERENCES  `campaign` (
`CampaignID`
) ON DELETE RESTRICT ON UPDATE RESTRICT ;

DROP TABLE lklayoutgroup;

UPDATE `version` SET `app_ver` = '1.3.3', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '47';