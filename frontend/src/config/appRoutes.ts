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

import {
  type LucideIcon,
  LayoutDashboard,
  Palette,
  Library,
  Monitor,
  UserRoundCog,
  ChartArea,
  CodeXml,
  FileLineChart,
  CalendarDays,
} from 'lucide-react';
import type { ComponentType } from 'react';

import type { TabNavItem } from '@/components/ui/TabNav';
import type { User } from '@/types/user';
import { filterRoutesByUser } from '@/utils/permissions';

export const DEFAULT_INTERNAL_ROUTE = 'dashboard';

enum UserType {
  SuperAdmin = 1,
  GroupAdmin = 2,
  User = 3,
}

export interface AppRoute {
  path: string;
  labelKey: string;
  icon?: LucideIcon;
  lazy?: () => Promise<{ Component: ComponentType<unknown> }>;
  externalURL?: string | undefined;
  subLinks?: AppRoute[];
  feature?: string;
  validator?: (user: User) => boolean;
  hideFromMenu?: boolean;
}

const isSuperAdmin = (user: User) => user.userTypeId === UserType.SuperAdmin;

const canAccessReport =
  (feature: string, adminOnly = false) =>
  (user: User) =>
    (isSuperAdmin(user) || !!user.features?.[feature]) && (!adminOnly || isSuperAdmin(user));

const canViewUsers = (user: User) => {
  const hasFeature = user.features?.['users.view'];
  const isAdmin =
    user.userTypeId === UserType.SuperAdmin || user.userTypeId === UserType.GroupAdmin;
  return !!(hasFeature && isAdmin);
};

export const generateTabNavigation = (parentRoute: AppRoute, user: User | null): TabNavItem[] => {
  if (!parentRoute.subLinks || !user) {
    return [];
  }

  const authorizedSubLinks = filterRoutesByUser(parentRoute.subLinks, user).filter(
    (route) => !route.hideFromMenu,
  );

  return authorizedSubLinks.map((subLink) => {
    const absolutePath = `/${parentRoute.path}/${subLink.path}`;

    return {
      labelKey: subLink.labelKey,
      path: absolutePath,
      externalURL: subLink.externalURL,
    };
  });
};

export const APP_ROUTES: AppRoute[] = [
  {
    path: 'welcome',
    labelKey: 'Welcome',
    hideFromMenu: true,
    lazy: () => import('@/pages/Welcome/Welcome').then((m) => ({ Component: m.default })),
  },
  {
    path: 'dashboard',
    labelKey: 'Dashboard',
    icon: LayoutDashboard,
    lazy: () => import('@/pages/Dashboard/DashboardRouter').then((m) => ({ Component: m.default })),
  },
  {
    path: 'schedule',
    labelKey: 'Schedule',
    icon: CalendarDays,
    subLinks: [
      {
        path: 'events',
        labelKey: 'Events',
        lazy: () =>
          import('@/pages/Schedule/Schedule/Events').then((m) => ({ Component: m.default })),
        feature: 'schedule.view',
      },
      {
        path: 'dayparting',
        labelKey: 'Dayparting',
        lazy: () =>
          import('@/pages/Schedule/Daypart/Daypart').then((m) => ({ Component: m.default })),
        feature: 'daypart.view',
      },
    ],
  },
  {
    path: 'design',
    labelKey: 'Design',
    icon: Palette,
    subLinks: [
      {
        path: 'campaign',
        labelKey: 'Campaigns',
        lazy: () =>
          import('@/pages/Design/Campaigns/Campaigns').then((m) => ({ Component: m.default })),
        feature: 'campaign.view',
      },
      {
        path: 'campaign/:id',
        labelKey: 'Edit Campaign',
        hideFromMenu: true,
        lazy: () =>
          import('@/pages/Design/Campaigns/CampaignEditor').then((m) => ({
            Component: m.default,
          })),
        feature: 'ad.campaign',
      },
      {
        path: 'layout',
        labelKey: 'Layouts',
        lazy: () =>
          import('@/pages/Design/Layouts/Layouts').then((m) => ({ Component: m.default })),
        feature: 'layout.view',
      },
      {
        path: 'templates',
        labelKey: 'Templates',
        lazy: () =>
          import('@/pages/Design/Templates/Templates').then((m) => ({ Component: m.default })),
        feature: 'template.view',
      },
      {
        path: 'resolutions',
        labelKey: 'Resolutions',
        lazy: () =>
          import('@/pages/Design/Resolutions/Resolutions').then((m) => ({ Component: m.default })),
        feature: 'resolution.view',
      },
    ],
  },
  {
    path: 'library',
    labelKey: 'Library',
    icon: Library,
    subLinks: [
      {
        path: 'playlists',
        labelKey: 'Playlists',
        lazy: () =>
          import('@/pages/Library/Playlists/Playlists').then((m) => ({ Component: m.default })),
        feature: 'playlist.view',
      },
      {
        path: 'media',
        labelKey: 'Media',
        lazy: () => import('@/pages/Library/Media/Media').then((m) => ({ Component: m.default })),
        feature: 'library.view',
      },
      {
        path: 'datasets',
        labelKey: 'Datasets',
        lazy: () =>
          import('@/pages/Library/Dataset/Datasets').then((m) => ({ Component: m.default })),
        feature: 'dataset.view',
      },
      {
        path: 'datasets/:datasetId/column',
        labelKey: 'Dataset Columns',
        hideFromMenu: true,
        lazy: () =>
          import('@/pages/Library/Dataset/subPages/Columns/DatasetColumns').then((m) => ({
            Component: m.default,
          })),
        feature: 'dataset.view',
      },
      {
        path: 'datasets/:datasetId/data',
        labelKey: 'Dataset Data',
        hideFromMenu: true,
        lazy: () =>
          import('@/pages/Library/Dataset/subPages/Data/DatasetData').then((m) => ({
            Component: m.default,
          })),
        feature: 'dataset.view',
      },
      {
        path: 'datasets/:datasetId/rss',
        labelKey: 'Dataset RSS',
        hideFromMenu: true,
        lazy: () =>
          import('@/pages/Library/Dataset/subPages/Rss/DatasetRss').then((m) => ({
            Component: m.default,
          })),
        feature: 'dataset.view',
      },
      {
        path: 'datasets/:datasetId/dataconnector',
        labelKey: 'Dataset Data Connector',
        hideFromMenu: true,
        lazy: () =>
          import('@/pages/Library/Dataset/subPages/DataConnector/DatasetDataConnector').then(
            (m) => ({
              Component: m.default,
            }),
          ),
        feature: 'dataset.view',
      },
      {
        path: 'menu-boards',
        labelKey: 'Menu Boards',
        lazy: () =>
          import('@/pages/Library/MenuBoard/MenuBoards').then((m) => ({ Component: m.default })),
        feature: 'menuBoard.view',
      },
      {
        path: 'menu-boards/:menuId/categories',
        labelKey: 'Menu Board Categories',
        hideFromMenu: true,
        lazy: () =>
          import('@/pages/Library/MenuBoard/subPages/Categories/MenuBoardCategories').then((m) => ({
            Component: m.default,
          })),
        feature: 'menuBoard.view',
      },
      {
        path: 'menu-boards/:menuId/categories/:categoryId/products',
        labelKey: 'Menu Board Products',
        hideFromMenu: true,
        lazy: () =>
          import('@/pages/Library/MenuBoard/subPages/Products/MenuBoardProducts').then((m) => ({
            Component: m.default,
          })),
        feature: 'menuBoard.view',
      },
    ],
  },
  {
    path: 'displays',
    labelKey: 'Displays',
    icon: Monitor,
    subLinks: [
      {
        path: 'displays',
        labelKey: 'Displays',
        lazy: () =>
          import('@/pages/Displays/Displays/Displays').then((m) => ({
            Component: m.default,
          })),
        feature: 'displays.view',
      },
      {
        path: 'display-groups',
        labelKey: 'Display Groups',
        lazy: () =>
          import('@/pages/Displays/DisplayGroup/DisplayGroup').then((m) => ({
            Component: m.default,
          })),
        feature: 'displaygroup.view',
      },
      {
        path: 'sync-groups',
        labelKey: 'Sync Groups',
        lazy: () =>
          import('@/pages/Displays/SyncGroups/SyncGroups').then((m) => ({
            Component: m.default,
          })),
        feature: 'display.syncView',
      },
      {
        path: 'settings',
        labelKey: 'Display Settings',
        lazy: () =>
          import('@/pages/Displays/DisplayProfile/DisplayProfile').then((m) => ({
            Component: m.default,
          })),
        feature: 'displayprofile.view',
      },
      {
        path: 'player-versions',
        labelKey: 'Player Versions',
        lazy: () =>
          import('@/pages/Displays/PlayerVersions/PlayerVersions').then((m) => ({
            Component: m.default,
          })),
        feature: 'playersoftware.view',
      },
      {
        path: 'commands',
        labelKey: 'Commands',
        lazy: () =>
          import('@/pages/Displays/Commands/Commands').then((m) => ({
            Component: m.default,
          })),
        feature: 'command.view',
      },
    ],
  },
  {
    path: 'administration',
    labelKey: 'Administration',
    icon: UserRoundCog,
    subLinks: [
      {
        path: 'users',
        labelKey: 'Users',
        lazy: () =>
          import('@/pages/Administration/Users/Users').then((m) => ({ Component: m.default })),
        validator: canViewUsers,
      },
      {
        path: 'user-groups',
        labelKey: 'User Groups',
        lazy: () =>
          import('@/pages/Administration/UserGroups/UserGroups').then((m) => ({
            Component: m.default,
          })),
        feature: 'usergroup.view',
      },
      {
        path: 'settings',
        labelKey: 'Settings',
        lazy: () =>
          import('@/pages/Administration/Settings/Settings').then((m) => ({
            Component: m.default,
          })),
        validator: isSuperAdmin,
      },
      {
        path: 'applications',
        labelKey: 'Applications',
        lazy: () =>
          import('@/pages/Administration/Applications/Applications').then((m) => ({
            Component: m.default,
          })),
        validator: isSuperAdmin,
      },
      {
        path: 'connectors',
        labelKey: 'Connectors',
        lazy: () =>
          import('@/pages/Administration/Connectors/Connectors').then((m) => ({
            Component: m.default,
          })),
        validator: isSuperAdmin,
      },
      {
        path: 'modules',
        labelKey: 'Modules',
        lazy: () =>
          import('@/pages/Administration/Modules/Modules').then((m) => ({
            Component: m.default,
          })),
        feature: 'module.view',
      },
      {
        path: 'transitions',
        labelKey: 'Transitions',
        lazy: () =>
          import('@/pages/Administration/Transitions/Transitions').then((m) => ({
            Component: m.default,
          })),
        feature: 'transition.view',
      },
      {
        path: 'tasks',
        labelKey: 'Tasks',
        lazy: () =>
          import('@/pages/Administration/Tasks/Tasks').then((m) => ({
            Component: m.default,
          })),
        feature: 'task.view',
      },
      {
        path: 'tags',
        labelKey: 'Tags',
        lazy: () =>
          import('@/pages/Administration/Tags/Tags').then((m) => ({ Component: m.default })),
        feature: 'tag.view',
      },
      {
        path: 'folders',
        labelKey: 'Folders',
        lazy: () =>
          import('@/pages/Administration/Folders/Folders').then((m) => ({
            Component: m.default,
          })),
        validator: isSuperAdmin,
      },
      {
        path: 'fonts',
        labelKey: 'Fonts',
        lazy: () =>
          import('@/pages/Administration/Fonts/Fonts').then((m) => ({ Component: m.default })),
        feature: 'font.view',
      },
    ],
  },
  {
    path: 'reporting',
    labelKey: 'Reporting',
    icon: ChartArea,
    subLinks: [
      {
        path: 'all-reports',
        labelKey: 'All Reports',
        lazy: () =>
          import('@/pages/Reporting/AllReports/AllReports').then((m) => ({ Component: m.default })),
        feature: 'report.view',
      },
      {
        path: 'report-schedules',
        labelKey: 'Report Schedules',
        lazy: () =>
          import('@/pages/Reporting/ReportSchedules/ReportSchedules').then((m) => ({
            Component: m.default,
          })),
        feature: 'report.scheduling',
      },
      {
        path: 'saved-reports',
        labelKey: 'Saved Reports',
        lazy: () =>
          import('@/pages/Reporting/SavedReports/SavedReports').then((m) => ({
            Component: m.default,
          })),
        feature: 'report.saving',
      },
      {
        path: 'time-connected',
        labelKey: 'Time Connected',
        hideFromMenu: true,
        lazy: () =>
          import('@/pages/Reporting/Reports/TimeConnected/TimeConnected').then((m) => ({
            Component: m.default,
          })),
        validator: canAccessReport('displays.reporting'),
      },
      {
        path: 'bandwidth',
        labelKey: 'Display Statistics: Bandwidth',
        hideFromMenu: true,
        lazy: () =>
          import('@/pages/Reporting/Reports/Bandwidth/Bandwidth').then((m) => ({
            Component: m.default,
          })),
        validator: canAccessReport('displays.reporting'),
      },
      {
        path: 'time-connected-summary',
        labelKey: 'Time Connected Summary',
        hideFromMenu: true,
        lazy: () =>
          import('@/pages/Reporting/Reports/TimeDisconnectedSummary/TimeDisconnectedSummary').then(
            (m) => ({
              Component: m.default,
            }),
          ),
        validator: canAccessReport('displays.reporting'),
      },
      {
        path: 'summary',
        labelKey: 'Summary by Layout, Media or Event',
        hideFromMenu: true,
        lazy: () =>
          import('@/pages/Reporting/Reports/Summary/Summary').then((m) => ({
            Component: m.default,
          })),
        validator: canAccessReport('proof-of-play'),
      },
      {
        path: 'distribution',
        labelKey: 'Distribution by Layout, Media or Event',
        hideFromMenu: true,
        lazy: () =>
          import('@/pages/Reporting/Reports/Distribution/Distribution').then((m) => ({
            Component: m.default,
          })),
        validator: canAccessReport('proof-of-play'),
      },
      {
        path: 'library-usage',
        labelKey: 'Library Usage',
        hideFromMenu: true,
        lazy: () =>
          import('@/pages/Reporting/Reports/LibraryUsage/LibraryUsage').then((m) => ({
            Component: m.default,
          })),
        validator: canAccessReport('admin', true),
      },
      {
        path: 'ssp-activity',
        labelKey: 'SSP Activity Report',
        hideFromMenu: true,
        lazy: () =>
          import('@/pages/Reporting/Reports/SspActivity/SspActivity').then((m) => ({
            Component: m.default,
          })),
        validator: canAccessReport('report.view', true),
      },
      {
        path: 'api-requests',
        labelKey: 'API Request History',
        hideFromMenu: true,
        lazy: () =>
          import('@/pages/Reporting/Reports/ApiRequestsHistory/ApiRequestsHistory').then((m) => ({
            Component: m.default,
          })),
        validator: canAccessReport('admin', true),
      },
      {
        path: 'session-history',
        labelKey: 'Session History',
        hideFromMenu: true,
        lazy: () =>
          import('@/pages/Reporting/Reports/SessionHistory/SessionHistory').then((m) => ({
            Component: m.default,
          })),
        validator: canAccessReport('admin', true),
      },
      {
        path: 'proof-of-play',
        labelKey: 'Proof of Play',
        hideFromMenu: true,
        lazy: () =>
          import('@/pages/Reporting/Reports/ProofOfPlay/ProofOfPlay').then((m) => ({
            Component: m.default,
          })),
        validator: canAccessReport('proof-of-play'),
      },
      {
        path: 'display-alerts',
        labelKey: 'Display Alerts',
        hideFromMenu: true,
        lazy: () =>
          import('@/pages/Reporting/Reports/DisplayAlerts/DisplayAlerts').then((m) => ({
            Component: m.default,
          })),
        validator: canAccessReport('displays.reporting'),
      },
    ],
  },
  {
    path: 'advanced',
    labelKey: 'Advanced',
    icon: FileLineChart,
    subLinks: [
      {
        path: 'log',
        labelKey: 'Log',
        lazy: () => import('@/pages/Advanced/Logs/Logs').then((m) => ({ Component: m.default })),
        feature: 'log.view',
      },
      {
        path: 'sessions',
        labelKey: 'Sessions',
        lazy: () =>
          import('@/pages/Advanced/Sessions/Sessions').then((m) => ({ Component: m.default })),
        feature: 'session.view',
      },
      {
        path: 'audit-trail',
        labelKey: 'Audit Trail',
        lazy: () =>
          import('@/pages/Advanced/AuditTrail/AuditTrail').then((m) => ({
            Component: m.default,
          })),
        feature: 'auditlog.view',
      },
    ],
  },
  {
    path: 'developer',
    labelKey: 'Developer',
    icon: CodeXml,
    feature: 'developer.edit',
    subLinks: [
      {
        path: 'template',
        labelKey: 'Module Templates',
        lazy: () =>
          import('@/pages/Developer/ModuleTemplates/ModuleTemplates').then((m) => ({
            Component: m.default,
          })),
        feature: 'developer.edit',
      },
      {
        path: 'template/:id/edit',
        labelKey: 'Edit Module Template',
        hideFromMenu: true,
        lazy: () =>
          import('@/pages/Developer/ModuleTemplates/ModuleTemplateEdit').then((m) => ({
            Component: m.default,
          })),
        feature: 'developer.edit',
      },
    ],
  },
  {
    path: 'notification',
    labelKey: 'Notification Centre',
    hideFromMenu: true,
    lazy: () => import('@/pages/Notification/Notification').then((m) => ({ Component: m.default })),
    feature: 'notification.centre',
  },
];
