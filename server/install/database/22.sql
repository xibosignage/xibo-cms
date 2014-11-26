INSERT INTO `pagegroup` (`pagegroupID`, `pagegroup`) VALUES (NULL, 'Web Services');

INSERT INTO `pages` (`name`, `pagegroupID`)
SELECT 'oauth', pagegroupID FROM pagegroup WHERE pagegroup = 'Web Services';


DELETE FROM `setting` WHERE `setting` = 'NUSOAP_PATH';

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

) engine=InnoDB default charset=utf8;

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