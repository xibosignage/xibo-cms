/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

import type { TFunction } from 'i18next';

export interface FeatureDefinition {
  feature: string;
  group: string;
  title: string;
}

export interface FeatureCategory {
  key: string;
  label: string;
  groups: string[];
}

export const getFeatureCategories = (t: TFunction): FeatureCategory[] => [
  {
    key: 'content',
    label: t('Content'),
    groups: [
      'folders',
      'library',
      'layout-design',
      'campaigns',
      'tagging',
      'playlist-design',
      'menuboard-design',
      'fonts',
    ],
  },
  {
    key: 'scheduling',
    label: t('Scheduling'),
    groups: ['scheduling'],
  },
  {
    key: 'displays',
    label: t('Displays'),
    groups: ['displays'],
  },
  {
    key: 'reports',
    label: t('Reports'),
    groups: ['reporting', 'troubleshooting'],
  },
  {
    key: 'users',
    label: t('Users'),
    groups: ['users', 'notifications', 'users-management', 'dashboards'],
  },
  {
    key: 'system',
    label: t('System'),
    groups: ['system'],
  },
];

/**
 * Static feature catalog matching backend UserGroupFactory::getFeatures().
 * Features are grouped by their backend `group` key.
 */
export const getFeatureDefinitions = (t: TFunction): FeatureDefinition[] => [
  // Folders
  { feature: 'folder.view', group: 'folders', title: t('View Folder Tree on Grids and Forms') },
  {
    feature: 'folder.add',
    group: 'folders',
    title: t(
      'Allow users to create Sub-Folders under Folders they have access to. (Except the Root Folder)',
    ),
  },
  { feature: 'folder.modify', group: 'folders', title: t('Rename and Delete existing Folders') },
  { feature: 'folder.userHome', group: 'folders', title: t('Set a home folder for a user') },

  // Library
  {
    feature: 'library.view',
    group: 'library',
    title: t('Page which shows all items uploaded to the Library for Media Management'),
  },
  {
    feature: 'library.add',
    group: 'library',
    title: t('Include "Add Media" buttons to allow additional content to be uploaded'),
  },
  {
    feature: 'library.modify',
    group: 'library',
    title: t('Allow edits including deletion to all items uploaded to the Media Library'),
  },
  {
    feature: 'dataset.view',
    group: 'library',
    title: t('Page which shows all DataSets that have been created'),
  },
  {
    feature: 'dataset.add',
    group: 'library',
    title: t('Include "Add DataSet" button to allow for additional DataSets to be created'),
  },
  {
    feature: 'dataset.modify',
    group: 'library',
    title: t('Allow edits including deletion to all created DataSets'),
  },
  {
    feature: 'dataset.data',
    group: 'library',
    title: t('Allow edits including deletion to all data contained within a DataSet'),
  },
  {
    feature: 'dataset.realtime',
    group: 'library',
    title: t('Create and update real time DataSets'),
  },

  // Layout Design
  {
    feature: 'layout.view',
    group: 'layout-design',
    title: t('Page which shows all Layouts for Layout Management'),
  },
  {
    feature: 'layout.add',
    group: 'layout-design',
    title: t('Include "Add Layout" button to allow additional Layouts to be created'),
  },
  {
    feature: 'layout.modify',
    group: 'layout-design',
    title: t('Allow edits including deletion to all created Layouts'),
  },
  {
    feature: 'layout.export',
    group: 'layout-design',
    title: t('Include the Export function for Layouts'),
  },
  {
    feature: 'template.view',
    group: 'layout-design',
    title: t('Page which shows all saved Templates'),
  },
  {
    feature: 'template.add',
    group: 'layout-design',
    title: t('Add "Save Template" function for all Layouts'),
  },
  {
    feature: 'template.modify',
    group: 'layout-design',
    title: t('Allow edits to all saved Templates'),
  },
  {
    feature: 'resolution.view',
    group: 'layout-design',
    title: t('Page which shows all Resolutions'),
  },
  { feature: 'resolution.add', group: 'layout-design', title: t('Add Resolution button') },
  {
    feature: 'resolution.modify',
    group: 'layout-design',
    title: t('Allow edits to all Resolutions'),
  },

  // Campaigns
  { feature: 'campaign.view', group: 'campaigns', title: t('Page which shows all Campaigns') },
  { feature: 'campaign.add', group: 'campaigns', title: t('Include "Add Campaign" button') },
  { feature: 'campaign.modify', group: 'campaigns', title: t('Allow edits to all Campaigns') },
  { feature: 'ad.campaign', group: 'campaigns', title: t('Access to Ad Campaigns') },

  // Tagging
  {
    feature: 'tag.view',
    group: 'tagging',
    title: t('Page which shows all Tags for Tag Management'),
  },
  {
    feature: 'tag.tagging',
    group: 'tagging',
    title: t('Ability to add and edit Tags when assigning to items'),
  },

  // Playlist Design
  {
    feature: 'playlist.view',
    group: 'playlist-design',
    title: t('Page which shows all Playlists'),
  },
  { feature: 'playlist.add', group: 'playlist-design', title: t('Include "Add Playlist" button') },
  {
    feature: 'playlist.modify',
    group: 'playlist-design',
    title: t('Allow edits to all Playlists'),
  },

  // Menu Board Design
  { feature: 'menuBoard.view', group: 'menuboard-design', title: t('View the Menu Board page') },
  {
    feature: 'menuBoard.add',
    group: 'menuboard-design',
    title: t('Include "Add Menu Board" button'),
  },
  {
    feature: 'menuBoard.modify',
    group: 'menuboard-design',
    title: t('Allow edits to Menu Board content'),
  },

  // Fonts
  { feature: 'font.view', group: 'fonts', title: t('View the Fonts page') },
  { feature: 'font.add', group: 'fonts', title: t('Upload new Fonts') },
  { feature: 'font.delete', group: 'fonts', title: t('Delete existing Fonts') },

  // Scheduling
  {
    feature: 'schedule.view',
    group: 'scheduling',
    title: t('Page which shows all Events on the Calendar for Schedule Management'),
  },
  {
    feature: 'schedule.agenda',
    group: 'scheduling',
    title: t('Include the Agenda View on the Calendar'),
  },
  {
    feature: 'schedule.add',
    group: 'scheduling',
    title: t('Include "Add Event" button for new Scheduled Events'),
  },
  {
    feature: 'schedule.modify',
    group: 'scheduling',
    title: t('Allow edits including deletion of Scheduled Events'),
  },
  {
    feature: 'schedule.sync',
    group: 'scheduling',
    title: t('Allow creation of Synchronised Schedules'),
  },
  {
    feature: 'schedule.dataConnector',
    group: 'scheduling',
    title: t('Allow creation of Data Connector Schedules'),
  },
  {
    feature: 'schedule.geoLocation',
    group: 'scheduling',
    title: t('Geo Schedule - location aware scheduling'),
  },
  {
    feature: 'schedule.reminders',
    group: 'scheduling',
    title: t('Allow reminders to be added to events'),
  },
  {
    feature: 'schedule.criteria',
    group: 'scheduling',
    title: t('Allow criteria to determine when events are active'),
  },
  { feature: 'daypart.view', group: 'scheduling', title: t('Page which shows all Dayparts') },
  { feature: 'daypart.add', group: 'scheduling', title: t('Include "Add Daypart" button') },
  { feature: 'daypart.modify', group: 'scheduling', title: t('Allow edits to all Dayparts') },

  // Users
  { feature: 'user.profile', group: 'users', title: t('Ability to update own Profile') },
  { feature: 'drawer', group: 'users', title: t('Notifications appear in the navigation bar') },
  { feature: 'application.view', group: 'users', title: t('Access to API applications') },
  {
    feature: 'user.sharing',
    group: 'users',
    title: t('Allow Sharing capabilities for all User objects'),
  },

  // Notifications
  {
    feature: 'notification.centre',
    group: 'notifications',
    title: t('Access to the Notification Centre'),
  },
  {
    feature: 'notification.add',
    group: 'notifications',
    title: t('Include "Add Notification" button'),
  },
  {
    feature: 'notification.modify',
    group: 'notifications',
    title: t('Allow edits to notifications'),
  },

  // Users Management
  {
    feature: 'users.view',
    group: 'users-management',
    title: t('Page which shows all Users for User Management'),
  },
  { feature: 'users.add', group: 'users-management', title: t('Include "Add User" button') },
  {
    feature: 'users.modify',
    group: 'users-management',
    title: t('Allow Group Admins to edit Users within their group'),
  },
  {
    feature: 'usergroup.view',
    group: 'users-management',
    title: t('Page which shows all User Groups'),
  },
  {
    feature: 'usergroup.modify',
    group: 'users-management',
    title: t('Allow edits to all User Groups'),
  },

  // Dashboards
  {
    feature: 'dashboard.status',
    group: 'dashboards',
    title: t('Status Dashboard showing key platform metrics'),
  },
  { feature: 'dashboard.media.manager', group: 'dashboards', title: t('Media Manager Dashboard') },
  { feature: 'dashboard.playlist', group: 'dashboards', title: t('Playlist Dashboard') },

  // Displays
  {
    feature: 'displays.view',
    group: 'displays',
    title: t('Page which shows all Displays for Display Management'),
  },
  { feature: 'displays.add', group: 'displays', title: t('Include "Add Display" button') },
  {
    feature: 'displays.modify',
    group: 'displays',
    title: t('Allow edits including deletion for all Displays'),
  },
  {
    feature: 'displays.limitedView',
    group: 'displays',
    title: t('Allow access to non-destructive edit-only features'),
  },
  {
    feature: 'displaygroup.view',
    group: 'displays',
    title: t('Page which shows all Display Groups'),
  },
  {
    feature: 'displaygroup.add',
    group: 'displays',
    title: t('Include "Add Display Group" button'),
  },
  {
    feature: 'displaygroup.modify',
    group: 'displays',
    title: t('Allow edits for all Display Groups'),
  },
  {
    feature: 'displaygroup.limitedView',
    group: 'displays',
    title: t('Non-destructive edit-only for Display Groups'),
  },
  {
    feature: 'displayprofile.view',
    group: 'displays',
    title: t('Page which shows all Display Setting Profiles'),
  },
  { feature: 'displayprofile.add', group: 'displays', title: t('Include "Add Profile" button') },
  {
    feature: 'displayprofile.modify',
    group: 'displays',
    title: t('Allow edits for all Display Setting Profiles'),
  },
  {
    feature: 'playersoftware.view',
    group: 'displays',
    title: t('Page to view/manage Player Software Versions'),
  },
  { feature: 'command.view', group: 'displays', title: t('Page to view/manage Commands') },
  { feature: 'command.add', group: 'displays', title: t('Allow creation of Commands') },
  { feature: 'command.modify', group: 'displays', title: t('Allow edits of Commands') },
  { feature: 'display.syncView', group: 'displays', title: t('Page which shows all Sync Groups') },
  {
    feature: 'display.syncAdd',
    group: 'displays',
    title: t('Allow creation of Synchronised Groups'),
  },
  {
    feature: 'display.syncModify',
    group: 'displays',
    title: t('Allow edits of Synchronised Groups'),
  },

  // Reporting
  {
    feature: 'report.view',
    group: 'reporting',
    title: t('Dashboard which shows all available Reports'),
  },
  {
    feature: 'displays.reporting',
    group: 'reporting',
    title: t('Display Reports for bandwidth and connectivity'),
  },
  { feature: 'proof-of-play', group: 'reporting', title: t('Proof of Play Reports') },
  {
    feature: 'report.scheduling',
    group: 'reporting',
    title: t('Page which shows all Scheduled Reports'),
  },
  { feature: 'report.saving', group: 'reporting', title: t('Page which shows all Saved Reports') },

  // Troubleshooting
  {
    feature: 'log.view',
    group: 'troubleshooting',
    title: t('Page to show debug and error logging'),
  },
  { feature: 'session.view', group: 'troubleshooting', title: t('Page to show all User Sessions') },
  { feature: 'auditlog.view', group: 'troubleshooting', title: t('Page to show the Audit Trail') },

  // System
  { feature: 'module.view', group: 'system', title: t('Page for Module Management') },
  { feature: 'developer.edit', group: 'system', title: t('Add/Edit custom modules and templates') },
  { feature: 'developer.delete', group: 'system', title: t('Delete custom modules and templates') },
  { feature: 'transition.view', group: 'system', title: t('Page for Transition Management') },
  { feature: 'task.view', group: 'system', title: t('Page for Task Management') },
];

/** Group description mapping for display */
export const getGroupDescriptions = (t: TFunction): Record<string, string> => ({
  folders: t('Organise content sharing with Folders'),
  library: t(
    'Media Library that stores file based content for use in Layouts, DataSets, Playlists and Menu Boards',
  ),
  'layout-design': t(
    'Allow content creators to create Layouts - which hold the content you want to show on your Displays',
  ),
  campaigns: t('Ensure ordering by grouping Layouts into Campaigns'),
  tagging: t('Organise and filter items by using Tags'),
  'playlist-design': t('Create and manage Playlists independently to Layouts'),
  'menuboard-design': t('Create and manage Menu Boards'),
  fonts: t('Administrative access to Fonts'),
  scheduling: t('Schedule content to Displays'),
  displays: t('Manage Displays connected to the platform'),
  reporting: t('Access to Reports and Proof of Play'),
  users: t('User profile preferences for the logged in User'),
  notifications: t(
    'Notification Centre allows for users to create/edit Notifications sent to other Users or used in Layouts',
  ),
  'users-management': t(
    "Manage Users that can authenticate with the CMS. Create and organise them into User Groups to enable 'Group Features'",
  ),
  dashboards: t('Dashboards bring together key features for Users'),
  system: t('Configuration'),
  troubleshooting: t('Tools to diagnose problems when seeking help'),
  custom: t('Third party extensions to the platform.'),
});

/** Group label mapping for display */
export const getGroupLabels = (t: TFunction): Record<string, string> => ({
  folders: t('Folders'),
  library: t('Library'),
  'layout-design': t('Layout Design'),
  campaigns: t('Campaigns'),
  tagging: t('Tagging'),
  'playlist-design': t('Playlists'),
  'menuboard-design': t('Menu Boards'),
  fonts: t('Fonts'),
  scheduling: t('Scheduling'),
  users: t('Users'),
  notifications: t('Notifications'),
  'users-management': t('User Management'),
  dashboards: t('Dashboards'),
  displays: t('Displays'),
  reporting: t('Reporting'),
  troubleshooting: t('Troubleshooting'),
  system: t('System'),
});
