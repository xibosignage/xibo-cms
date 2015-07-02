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
-- Constraints for table `datasetdata`
--
ALTER TABLE `datasetdata`
ADD CONSTRAINT `datasetdata_ibfk_1` FOREIGN KEY (`DataSetColumnID`) REFERENCES `datasetcolumn` (`DataSetColumnID`);

--
-- Constraints for table `file`
--
ALTER TABLE `file`
ADD CONSTRAINT `file_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `user` (`UserID`);

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
-- Constraints for table `lklayoutmedia`
--
ALTER TABLE `lklayoutmedia`
ADD CONSTRAINT `lklayoutmedia_ibfk_1` FOREIGN KEY (`mediaID`) REFERENCES `media` (`mediaID`),
ADD CONSTRAINT `lklayoutmedia_ibfk_2` FOREIGN KEY (`layoutID`) REFERENCES `layout` (`layoutID`);

--
-- Constraints for table `lkmediagroup`
--
ALTER TABLE `lkmediagroup`
ADD CONSTRAINT `lkmediagroup_ibfk_1` FOREIGN KEY (`MediaID`) REFERENCES `media` (`mediaID`),
ADD CONSTRAINT `lkmediagroup_ibfk_2` FOREIGN KEY (`GroupID`) REFERENCES `group` (`groupID`);

--
-- Constraints for table `lkusergroup`
--
ALTER TABLE `lkusergroup`
ADD CONSTRAINT `lkusergroup_ibfk_1` FOREIGN KEY (`GroupID`) REFERENCES `group` (`groupID`),
ADD CONSTRAINT `lkusergroup_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `user` (`UserID`);

--
-- Constraints for table `menuitem`
--
ALTER TABLE `menuitem`
ADD CONSTRAINT `menuitem_ibfk_1` FOREIGN KEY (`MenuID`) REFERENCES `menu` (`MenuID`),
ADD CONSTRAINT `menuitem_ibfk_2` FOREIGN KEY (`PageID`) REFERENCES `pages` (`pageID`);

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
-- Constraints for table `schedule`
--
ALTER TABLE `schedule`
ADD CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`CampaignID`) REFERENCES `campaign` (`CampaignID`);

--
-- Constraints for table `schedule_detail`
--
ALTER TABLE `schedule_detail`
ADD CONSTRAINT `schedule_detail_ibfk_7` FOREIGN KEY (`eventID`) REFERENCES `schedule` (`eventID`);

--
-- Constraints for table `user`
--
ALTER TABLE `user`
ADD CONSTRAINT `user_ibfk_2` FOREIGN KEY (`usertypeid`) REFERENCES `usertype` (`usertypeid`);
