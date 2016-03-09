<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (OAuthServerInstallStep.php)
 */


namespace Xibo\Upgrade;


use Xibo\Helper\Install;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class OAuthServerInstallStep
 * @package Xibo\Upgrade
 */
class OAuthServerInstallStep implements Step
{
    /** @var  StorageServiceInterface */
    private $store;

    /** @var  LogServiceInterface */
    private $log;

    /** @var  ConfigServiceInterface */
    private $config;

    /**
     * DataSetConvertStep constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     */
    public function __construct($store, $log, $config)
    {
        $this->store = $store;
        $this->log = $log;
        $this->config = $config;
    }

    /**
     * @param \Slim\Helper\Set $container
     * @throws \Xibo\Exception\NotFoundException
     */
    public function doStep($container)
    {
        $dbh = $this->store->getConnection();

        // Run the SQL to create the necessary tables
        $statements = Install::remove_remarks(self::$dbStructure);
        $statements = Install::split_sql_file($statements, ';');

        foreach ($statements as $sql) {
            $dbh->exec($sql);
        }
    }

    private static $dbStructure = <<<END
--
-- Table structure for table `oauth_access_tokens`
--

CREATE TABLE IF NOT EXISTS `oauth_access_tokens` (
`access_token` varchar(254) NOT NULL,
`session_id` int(10) unsigned NOT NULL,
`expire_time` int(11) NOT NULL,
PRIMARY KEY (`access_token`),
KEY `session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_access_token_scopes`
--

CREATE TABLE IF NOT EXISTS `oauth_access_token_scopes` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`access_token` varchar(254) NOT NULL,
`scope` varchar(254) NOT NULL,
PRIMARY KEY (`id`),
KEY `access_token` (`access_token`),
KEY `scope` (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_auth_codes`
--

CREATE TABLE IF NOT EXISTS `oauth_auth_codes` (
`auth_code` varchar(254) NOT NULL,
`session_id` int(10) unsigned NOT NULL,
`expire_time` int(11) NOT NULL,
`client_redirect_uri` varchar(500) NOT NULL,
PRIMARY KEY (`auth_code`),
KEY `session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_auth_code_scopes`
--

CREATE TABLE IF NOT EXISTS `oauth_auth_code_scopes` (
`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
`auth_code` varchar(254) NOT NULL,
`scope` varchar(254) NOT NULL,
PRIMARY KEY (`id`),
KEY `auth_code` (`auth_code`),
KEY `scope` (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_clients`
--

CREATE TABLE IF NOT EXISTS `oauth_clients` (
`id` varchar(254) NOT NULL,
`secret` varchar(254) NOT NULL,
`name` varchar(254) NOT NULL,
`userId` int(11) NOT NULL,
`authCode` tinyint(4) NOT NULL,
`clientCredentials` tinyint(4) NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
-- --------------------------------------------------------

--
-- Table structure for table `oauth_client_redirect_uris`
--

CREATE TABLE IF NOT EXISTS `oauth_client_redirect_uris` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`client_id` varchar(254) NOT NULL,
`redirect_uri` varchar(500) NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2 ;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_refresh_tokens`
--

CREATE TABLE IF NOT EXISTS `oauth_refresh_tokens` (
`refresh_token` varchar(254) NOT NULL,
`expire_time` int(11) NOT NULL,
`access_token` varchar(254) NOT NULL,
PRIMARY KEY (`refresh_token`),
KEY `access_token` (`access_token`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_scopes`
--

CREATE TABLE IF NOT EXISTS `oauth_scopes` (
`id` varchar(254) NOT NULL,
`description` varchar(1000) NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_sessions`
--

CREATE TABLE IF NOT EXISTS `oauth_sessions` (
`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
`owner_type` varchar(254) NOT NULL,
`owner_id` varchar(254) NOT NULL,
`client_id` varchar(254) NOT NULL,
`client_redirect_uri` varchar(500) DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `client_id` (`client_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=14 ;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_session_scopes`
--

CREATE TABLE IF NOT EXISTS `oauth_session_scopes` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`session_id` int(10) unsigned NOT NULL,
`scope` varchar(254) NOT NULL,
PRIMARY KEY (`id`),
KEY `session_id` (`session_id`),
KEY `scope` (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `oauth_access_tokens`
--
ALTER TABLE `oauth_access_tokens`
ADD CONSTRAINT `oauth_access_tokens_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `oauth_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `oauth_access_token_scopes`
--
ALTER TABLE `oauth_access_token_scopes`
ADD CONSTRAINT `oauth_access_token_scopes_ibfk_1` FOREIGN KEY (`access_token`) REFERENCES `oauth_access_tokens` (`access_token`) ON DELETE CASCADE,
ADD CONSTRAINT `oauth_access_token_scopes_ibfk_2` FOREIGN KEY (`scope`) REFERENCES `oauth_scopes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `oauth_auth_codes`
--
ALTER TABLE `oauth_auth_codes`
ADD CONSTRAINT `oauth_auth_codes_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `oauth_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `oauth_auth_code_scopes`
--
ALTER TABLE `oauth_auth_code_scopes`
ADD CONSTRAINT `oauth_auth_code_scopes_ibfk_1` FOREIGN KEY (`auth_code`) REFERENCES `oauth_auth_codes` (`auth_code`) ON DELETE CASCADE,
ADD CONSTRAINT `oauth_auth_code_scopes_ibfk_2` FOREIGN KEY (`scope`) REFERENCES `oauth_scopes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `oauth_refresh_tokens`
--
ALTER TABLE `oauth_refresh_tokens`
ADD CONSTRAINT `oauth_refresh_tokens_ibfk_1` FOREIGN KEY (`access_token`) REFERENCES `oauth_access_tokens` (`access_token`) ON DELETE CASCADE;

--
-- Constraints for table `oauth_sessions`
--
ALTER TABLE `oauth_sessions`
ADD CONSTRAINT `oauth_sessions_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `oauth_clients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `oauth_session_scopes`
--
ALTER TABLE `oauth_session_scopes`
ADD CONSTRAINT `oauth_session_scopes_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `oauth_sessions` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `oauth_session_scopes_ibfk_2` FOREIGN KEY (`scope`) REFERENCES `oauth_scopes` (`id`) ON DELETE CASCADE;

END;

}