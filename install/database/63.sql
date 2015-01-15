
CREATE TABLE IF NOT EXISTS `datatype` (
  `DataTypeID` smallint(6) NOT NULL,
  `DataType` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `datasetcolumntype` (
  `DataSetColumnTypeID` smallint(6) NOT NULL,
  `DataSetColumnType` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `datatype` (`DataTypeID`, `DataType`) VALUES
(1, 'String'),
(2, 'Number'),
(3, 'Date');

INSERT INTO `datasetcolumntype` (`DataSetColumnTypeID`, `DataSetColumnType`) VALUES
(1, 'Value'),
(2, 'Formula');

ALTER TABLE  `datasetcolumn` ADD  `DataSetColumnTypeID` smallint(6) NOT NULL AFTER  `DataTypeID`;
ALTER TABLE  `datasetcolumn` ADD  `Formula` VARCHAR( 1000 ) NULL;

UPDATE `datasetcolumn` SET `DataSetColumnTypeID` = 1;

ALTER TABLE  `display` ADD  `GeoLocation` POINT NULL;

UPDATE `version` SET `app_ver` = '1.5.1', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '63';