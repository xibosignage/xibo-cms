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

// TODO: Hardcoded for now, change to default page later
export const DEFAULT_INTERNAL_ROUTE = '/library/media';

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
    path: 'dashboard',
    labelKey: 'Dashboard',
    icon: LayoutDashboard,
    externalURL: '/statusdashboard',
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
        labelKey: 'Campaign',
        lazy: () =>
          import('@/pages/Design/Campaigns/Campaigns').then((m) => ({ Component: m.default })),
        feature: 'campaign.view',
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
        path: 'menu-boards',
        labelKey: 'Menu Boards',
        externalURL: '/menuboard/view',
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
        path: 'playersoftware',
        labelKey: 'Player Versions',
        externalURL: '/playersoftware/view',
        feature: 'playersoftware.view',
      },
      {
        path: 'commands',
        labelKey: 'Commands',
        externalURL: '/command/view',
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
        externalURL: '/user/view',
        validator: canViewUsers,
      },
      {
        path: 'user-groups',
        labelKey: 'User Groups',
        externalURL: '/group/view',
        feature: 'usergroup.view',
      },
      {
        path: 'settings',
        labelKey: 'Settings',
        externalURL: '/admin/view',
        validator: isSuperAdmin,
      },
      {
        path: 'applications',
        labelKey: 'Applications',
        externalURL: '/application/view',
        validator: isSuperAdmin,
      },
      {
        path: 'modules',
        labelKey: 'Modules',
        externalURL: '/module/view',
        feature: 'module.view',
      },
      {
        path: 'transitions',
        labelKey: 'Transitions',
        externalURL: '/transition/view',
        feature: 'transition.view',
      },
      {
        path: 'tasks',
        labelKey: 'Tasks',
        externalURL: '/task/view',
        feature: 'task.view',
      },
      {
        path: 'tags',
        labelKey: 'Tags',
        externalURL: '/tag/view',
        feature: 'tag.view',
      },
      {
        path: 'folders',
        labelKey: 'Folders',
        externalURL: '/folders/view',
        validator: isSuperAdmin,
      },
      {
        path: 'fonts',
        labelKey: 'Fonts',
        externalURL: '/fonts/view',
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
        externalURL: '/report/view',
        feature: 'report.view',
      },
      {
        path: 'report-schedules',
        labelKey: 'Report Schedules',
        externalURL: '/report/reportschedule/view',
        feature: 'report.scheduling',
      },
      {
        path: 'saved-reports',
        labelKey: 'Saved Reports',
        externalURL: '/report/savedreport/view',
        feature: 'report.saving',
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
        externalURL: '/log/view',
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
        externalURL: '/audit/view',
        feature: 'auditlog.view',
      },
      {
        path: 'report-fault',
        labelKey: 'Report Fault',
        externalURL: '/fault/view',
        feature: 'fault.view',
      },
    ],
  },
  {
    path: 'developer',
    labelKey: 'Developer',
    icon: CodeXml,
    externalURL: '/developer/template/view',
    feature: 'developer.edit',
  },
];
