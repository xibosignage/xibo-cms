ALTER TABLE oauth_server_token ADD ost_verifier char(10);

ALTER TABLE oauth_server_token ADD ost_callback_url varchar(512);

ALTER TABLE  `oauth_server_registry` ADD FOREIGN KEY (  `osr_usa_id_ref` ) REFERENCES  `user` (
`UserID`
) ON DELETE CASCADE ON UPDATE CASCADE ;

ALTER TABLE  `oauth_server_token` ADD INDEX (  `ost_usa_id_ref` );

ALTER TABLE  `oauth_server_token` ADD FOREIGN KEY (  `ost_usa_id_ref` ) REFERENCES  `user` (
`UserID`
) ON DELETE CASCADE ON UPDATE CASCADE ;


/* VERSION UPDATE */
/* Set the version table, etc */
UPDATE `version` SET `app_ver` = '1.3.0', `XmdsVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '40';
