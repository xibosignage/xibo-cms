--
-- Constraints for table `blacklist`
--
ALTER TABLE `blacklist`
ADD CONSTRAINT `blacklist_ibfk_1` FOREIGN KEY (`MediaID`) REFERENCES `media` (`mediaID`),
ADD CONSTRAINT `blacklist_ibfk_2` FOREIGN KEY (`DisplayID`) REFERENCES `display` (`displayid`);

--
-- Constraints for table `campaign`
--
ALTER TABLE `campaign`
ADD CONSTRAINT `campaign_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `user` (`UserID`);

--
-- Constraints for table `datasetcolumn`
--
ALTER TABLE `datasetcolumn`
ADD CONSTRAINT `datasetcolumn_ibfk_1` FOREIGN KEY (`DataSetID`) REFERENCES `dataset` (`DataSetID`);

--
-- Constraints for table `lkcampaignlayout`
--
ALTER TABLE `lkcampaignlayout`
ADD CONSTRAINT `lkcampaignlayout_ibfk_1` FOREIGN KEY (`CampaignID`) REFERENCES `campaign` (`CampaignID`),
ADD CONSTRAINT `lkcampaignlayout_ibfk_2` FOREIGN KEY (`LayoutID`) REFERENCES `layout` (`layoutID`);

--
-- Constraints for table `lkdisplaydg`
--
ALTER TABLE `lkdisplaydg`
ADD CONSTRAINT `lkdisplaydg_ibfk_1` FOREIGN KEY (`DisplayGroupID`) REFERENCES `displaygroup` (`DisplayGroupID`),
ADD CONSTRAINT `lkdisplaydg_ibfk_2` FOREIGN KEY (`DisplayID`) REFERENCES `display` (`displayid`);


--
-- Constraints for table `lkusergroup`
--
ALTER TABLE `lkusergroup`
ADD CONSTRAINT `lkusergroup_ibfk_1` FOREIGN KEY (`GroupID`) REFERENCES `group` (`groupID`),
ADD CONSTRAINT `lkusergroup_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `user` (`UserID`);


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

--
-- Constraints for table `oauth_clients`
--
ALTER TABLE `oauth_clients`
  ADD CONSTRAINT oauth_clients_user_UserID_fk FOREIGN KEY (userId) REFERENCES `user` (UserID) ON DELETE CASCADE;

--
-- Constraints for table `schedule`
--
ALTER TABLE `schedule`
ADD CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`CampaignID`) REFERENCES `campaign` (`CampaignID`);

--
-- Constraints for table `user`
--
ALTER TABLE `user`
ADD CONSTRAINT `user_ibfk_2` FOREIGN KEY (`usertypeid`) REFERENCES `usertype` (`usertypeid`);


CREATE UNIQUE INDEX lkwidgetaudio_widgetId_mediaId_uindex ON lkwidgetaudio (widgetId, mediaId);

CREATE UNIQUE INDEX oauth_client_scopes_clientId_scopeId_uindex ON oauth_client_scopes (clientId, scopeId);

ALTER TABLE oauth_client_scopes
ADD CONSTRAINT oauth_client_scopes_oauth_clients_id_fk
FOREIGN KEY (clientId) REFERENCES oauth_clients (id) ON DELETE CASCADE;

ALTER TABLE oauth_client_scopes
ADD CONSTRAINT oauth_client_scopes_oauth_scopes_id_fk
FOREIGN KEY (scopeId) REFERENCES oauth_scopes (id) ON DELETE CASCADE;

CREATE INDEX requiredfile_displayId_type_index ON requiredfile (displayId, type);

CREATE INDEX idx_lkregionplaylist_playlistId ON lkregionplaylist (playlistId);

CREATE INDEX lkdgdg_parentId_childId_depth_index ON lkdgdg (parentId, childId, depth);

CREATE INDEX lkdgdg_childId_parentId_depth_index ON lkdgdg (childId, parentId, depth);

CREATE INDEX media_editedMediaID_index ON media (editedMediaID);

# Tag foreign keys
ALTER TABLE `lktagcampaign` ADD CONSTRAINT `lktagcampaign_ibfk_1` FOREIGN KEY (`campaignId`) REFERENCES `campaign` (`campaignId`);
ALTER TABLE `lktaglayout` ADD CONSTRAINT `lktaglayout_ibfk_1` FOREIGN KEY (`layoutId`) REFERENCES `layout` (`layoutId`);
ALTER TABLE `lktagmedia` ADD CONSTRAINT `lktagmedia_ibfk_1` FOREIGN KEY (`mediaId`) REFERENCES `media` (`mediaId`);
ALTER TABLE `lktagdisplaygroup` ADD CONSTRAINT `lktagdisplaygroup_ibfk_1` FOREIGN KEY (`displayGroupId`) REFERENCES `displaygroup` (`displayGroupId`);

# Permissions foreign keys and index
CREATE INDEX permission_objectId_index ON permission (objectId);

ALTER TABLE permission
  ADD CONSTRAINT permission_group_groupID_fk
FOREIGN KEY (groupId) REFERENCES `group` (groupID);

ALTER TABLE permission
  ADD CONSTRAINT permission_permissionentity_entityId_fk
FOREIGN KEY (entityId) REFERENCES permissionentity (entityId);