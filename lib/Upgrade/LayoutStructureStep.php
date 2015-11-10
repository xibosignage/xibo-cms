<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (LayoutStructureStep.php)
 */


namespace Xibo\Upgrade;


class LayoutStructureStep implements Step
{
    public function doStep()
    {
        $doStep = <<<END
        /* Take existing permissions and pull them into the permissions table */
        INSERT INTO `permission` (`groupId`, `entityId`, `objectId`, `objectIdString`, `view`, `edit`, `delete`)
        SELECT groupId, 4, NULL, CONCAT(LayoutId, '_', RegionID, '_', MediaID), view, edit, del
        FROM `lklayoutmediagroup`;

        DROP TABLE `lklayoutmediagroup`;

        INSERT INTO `permission` (`groupId`, `entityId`, `objectId`, `objectIdString`, `view`, `edit`, `delete`)
        SELECT groupId, 3, NULL, CONCAT(LayoutId, '_', RegionID), view, edit, del
        FROM `lklayoutregiongroup`;

        DROP TABLE `lklayoutregiongroup`;
END;

    }

    private $dbStructure = <<<END
CREATE TABLE IF NOT EXISTS `lkregionplaylist` (
`regionId` int(11) NOT NULL,
`playlistId` int(11) NOT NULL,
`displayOrder` int(11) NOT NULL,
PRIMARY KEY (`regionId`,`playlistId`,`displayOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `lkwidgetmedia` (
`widgetId` int(11) NOT NULL,
`mediaId` int(11) NOT NULL,
PRIMARY KEY (`widgetId`,`mediaId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `playlist` (
`playlistId` int(11) NOT NULL AUTO_INCREMENT,
`name` varchar(254) DEFAULT NULL,
`ownerId` int(11) NOT NULL,
PRIMARY KEY (`playlistId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `region` (
`regionId` int(11) NOT NULL AUTO_INCREMENT,
`layoutId` int(11) NOT NULL,
`ownerId` int(11) NOT NULL,
`name` varchar(254) DEFAULT NULL,
`width` decimal(12,4) NOT NULL,
`height` decimal(12,4) NOT NULL,
`top` decimal(12,4) NOT NULL,
`left` decimal(12,4) NOT NULL,
`zIndex` smallint(6) NOT NULL,
PRIMARY KEY (`regionId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `regionoption` (
`regionId` int(11) NOT NULL,
`option` varchar(50) NOT NULL,
`value` text NULL,
PRIMARY KEY (`regionId`,`option`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `widget` (
`widgetId` int(11) NOT NULL AUTO_INCREMENT,
`playlistId` int(11) NOT NULL,
`ownerId` int(11) NOT NULL,
`type` varchar(50) NOT NULL,
`duration` int(11) NOT NULL,
`displayOrder` int(11) NOT NULL,
PRIMARY KEY (`widgetId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `widgetoption` (
`widgetId` int(11) NOT NULL,
`type` varchar(50) NOT NULL,
`option` varchar(254) NOT NULL,
`value` text NULL,
PRIMARY KEY (`widgetId`,`type`,`option`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
END;

}