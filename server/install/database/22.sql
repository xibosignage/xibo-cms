INSERT INTO `pagegroup` (`pagegroupID`, `pagegroup`) VALUES (NULL, 'Web Services');

INSERT INTO `pages` (`name`, `pagegroupID`)
SELECT 'oauth', pagegroupID FROM pagegroup WHERE pagegroup = 'Web Services';

INSERT INTO `module` (`ModuleID`, `Module`, `Enabled`, `RegionSpecific`, `Description`, `ImageUri`, `SchemaVersion`, `ValidExtensions`) VALUES (NULL, 'MicroBlog', '1', '1', NULL, 'img/forms/webpage.gif', '1', NULL);


#
# Log table to hold all OAuth request when you enabled logging
#

CREATE TABLE IF NOT EXISTS oauth_log (
    olg_id                  int(11) not null auto_increment,
    olg_osr_consumer_key    varchar(64) binary,
    olg_ost_token           varchar(64) binary,
    olg_ocr_consumer_key    varchar(64) binary,
    olg_oct_token           varchar(64) binary,
    olg_usa_id_ref          int(11),
    olg_received            text not null,
    olg_sent                text not null,
    olg_base_string         text not null,
    olg_notes               text not null,
    olg_timestamp           timestamp not null default current_timestamp,
    olg_remote_ip           bigint not null,

    primary key (olg_id),
    key (olg_osr_consumer_key, olg_id),
    key (olg_ost_token, olg_id),
    key (olg_ocr_consumer_key, olg_id),
    key (olg_oct_token, olg_id),
    key (olg_usa_id_ref, olg_id)

#   , foreign key (olg_usa_id_ref) references any_user_auth (usa_id_ref)
#       on update cascade
#       on delete cascade
) engine=InnoDB default charset=utf8;

#--SPLIT--

#
# /////////////////// CONSUMER SIDE ///////////////////
#

# This is a registry of all consumer codes we got from other servers
# The consumer_key/secret is obtained from the server
# We also register the server uri, so that we can find the consumer key and secret
# for a certain server.  From that server we can check if we have a token for a
# particular user.

CREATE TABLE IF NOT EXISTS oauth_consumer_registry (
    ocr_id                  int(11) not null auto_increment,
    ocr_usa_id_ref          int(11),
    ocr_consumer_key        varchar(64) binary not null,
    ocr_consumer_secret     varchar(64) binary not null,
    ocr_signature_methods   varchar(255) not null default 'HMAC-SHA1,PLAINTEXT',
    ocr_server_uri          varchar(255) not null,
    ocr_server_uri_host     varchar(128) not null,
    ocr_server_uri_path     varchar(128) binary not null,

    ocr_request_token_uri   varchar(255) not null,
    ocr_authorize_uri       varchar(255) not null,
    ocr_access_token_uri    varchar(255) not null,
    ocr_timestamp           timestamp not null default current_timestamp,

    primary key (ocr_id),
    unique key (ocr_consumer_key, ocr_usa_id_ref),
    key (ocr_server_uri),
    key (ocr_server_uri_host, ocr_server_uri_path),
    key (ocr_usa_id_ref)

#   , foreign key (ocr_usa_id_ref) references any_user_auth(usa_id_ref)
#       on update cascade
#       on delete set null
) engine=InnoDB default charset=utf8;

#--SPLIT--

# Table used to sign requests for sending to a server by the consumer
# The key is defined for a particular user.  Only one single named
# key is allowed per user/server combination

CREATE TABLE IF NOT EXISTS oauth_consumer_token (
    oct_id                  int(11) not null auto_increment,
    oct_ocr_id_ref          int(11) not null,
    oct_usa_id_ref          int(11) not null,
    oct_name                varchar(64) binary not null default '',
    oct_token               varchar(64) binary not null,
    oct_token_secret        varchar(64) binary not null,
    oct_token_type          enum('request','authorized','access'),
    oct_token_ttl           datetime not null default '9999-12-31',
    oct_timestamp           timestamp not null default current_timestamp,

    primary key (oct_id),
    unique key (oct_ocr_id_ref, oct_token),
    unique key (oct_usa_id_ref, oct_ocr_id_ref, oct_token_type, oct_name),
	key (oct_token_ttl),

    foreign key (oct_ocr_id_ref) references oauth_consumer_registry (ocr_id)
        on update cascade
        on delete cascade

#   , foreign key (oct_usa_id_ref) references any_user_auth (usa_id_ref)
#       on update cascade
#       on delete cascade
) engine=InnoDB default charset=utf8;

#--SPLIT--


#
# ////////////////// SERVER SIDE /////////////////
#

# Table holding consumer key/secret combos an user issued to consumers.
# Used for verification of incoming requests.

CREATE TABLE IF NOT EXISTS oauth_server_registry (
    osr_id                      int(11) not null auto_increment,
    osr_usa_id_ref              int(11),
    osr_consumer_key            varchar(64) binary not null,
    osr_consumer_secret         varchar(64) binary not null,
    osr_enabled                 tinyint(1) not null default '1',
    osr_status                  varchar(16) not null,
    osr_requester_name          varchar(64) not null,
    osr_requester_email         varchar(64) not null,
    osr_callback_uri            varchar(255) not null,
    osr_application_uri         varchar(255) not null,
    osr_application_title       varchar(80) not null,
    osr_application_descr       text not null,
    osr_application_notes       text not null,
    osr_application_type        varchar(20) not null,
    osr_application_commercial  tinyint(1) not null default '0',
    osr_issue_date              datetime not null,
    osr_timestamp               timestamp not null default current_timestamp,

    primary key (osr_id),
    unique key (osr_consumer_key),
    key (osr_usa_id_ref)

#   , foreign key (osr_usa_id_ref) references any_user_auth(usa_id_ref)
#       on update cascade
#       on delete set null
) engine=InnoDB default charset=utf8;

#--SPLIT--

# Nonce used by a certain consumer, every used nonce should be unique, this prevents
# replaying attacks.  We need to store all timestamp/nonce combinations for the
# maximum timestamp received.

CREATE TABLE IF NOT EXISTS oauth_server_nonce (
    osn_id                  int(11) not null auto_increment,
    osn_consumer_key        varchar(64) binary not null,
    osn_token               varchar(64) binary not null,
    osn_timestamp           bigint not null,
    osn_nonce               varchar(80) binary not null,

    primary key (osn_id),
    unique key (osn_consumer_key, osn_token, osn_timestamp, osn_nonce)
) engine=InnoDB default charset=utf8;

#--SPLIT--

# Table used to verify signed requests sent to a server by the consumer
# When the verification is succesful then the associated user id is returned.

CREATE TABLE IF NOT EXISTS oauth_server_token (
    ost_id                  int(11) not null auto_increment,
    ost_osr_id_ref          int(11) not null,
    ost_usa_id_ref          int(11) not null,
    ost_token               varchar(64) binary not null,
    ost_token_secret        varchar(64) binary not null,
    ost_token_type          enum('request','access'),
    ost_authorized          tinyint(1) not null default '0',
	ost_referrer_host		varchar(128) not null,
	ost_token_ttl           datetime not null default '9999-12-31',
    ost_timestamp           timestamp not null default current_timestamp,

	primary key (ost_id),
    unique key (ost_token),
    key (ost_osr_id_ref),
	key (ost_token_ttl),

	foreign key (ost_osr_id_ref) references oauth_server_registry (osr_id)
        on update cascade
        on delete cascade

#   , foreign key (ost_usa_id_ref) references any_user_auth (usa_id_ref)
#       on update cascade
#       on delete cascade
) engine=InnoDB default charset=utf8;


CREATE TABLE `file` (
`FileID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`CreatedDT` INT NOT NULL ,
`UserID` INT NOT NULL
) ENGINE = INNODB;

ALTER TABLE  `file` ADD INDEX (  `UserID` );

ALTER TABLE  `file` ADD FOREIGN KEY (  `UserID` ) REFERENCES  `user` (
`UserID`
);


/* VERSION UPDATE */
/* Set the version table, etc */
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';

UPDATE  `version` SET  `app_ver` =  '1.1.1', `XmdsVersion` =  '1', `DBVersion` =  '22';