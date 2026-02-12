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

import type { User } from '@/types/user';

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
}

const isSuperAdmin = (user: User) => user.userTypeId === UserType.SuperAdmin;

const canViewUsers = (user: User) => {
  const hasFeature = user.features?.['users.view'];
  const isAdmin =
    user.userTypeId === UserType.SuperAdmin || user.userTypeId === UserType.GroupAdmin;
  return !!(hasFeature && isAdmin);
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
        path: 'event',
        labelKey: 'Event',
        externalURL: '/schedule/view',
        feature: 'schedule.view',
      },
      {
        path: 'dayparting',
        labelKey: 'Dayparting',
        externalURL: '/dayparting/view',
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
        externalURL: '/campaign/view',
        feature: 'campaign.view',
      },
      {
        path: 'layout',
        labelKey: 'Layouts',
        externalURL: '/layout/view',
        feature: 'layout.view',
      },
      {
        path: 'templates',
        labelKey: 'Templates',
        externalURL: '/template/view',
        feature: 'template.view',
      },
      {
        path: 'resolutions',
        labelKey: 'Resolutions',
        externalURL: '/resolution/view',
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
        externalURL: '/playlist/view',
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
        externalURL: '/dataset/view',
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
        path: 'add-displays',
        labelKey: 'Add Displays',
        externalURL: '/display/view',
        feature: 'displays.view',
      },
      {
        path: 'display-groups',
        labelKey: 'Display Groups',
        externalURL: '/displaygroup/view',
        feature: 'displaygroup.view',
      },
      {
        path: 'sync-groups',
        labelKey: 'Sync Groups',
        externalURL: '/syncgroup/view',
        feature: 'display.syncView',
      },
      {
        path: 'settings',
        labelKey: 'Settings',
        externalURL: '/displayprofile/view',
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
        externalURL: '/sessions/view',
        feature: 'sessions.view',
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
