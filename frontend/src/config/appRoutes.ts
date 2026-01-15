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
.*/

import {
  type LucideIcon,
  LayoutDashboard,
  Settings,
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

export interface AppRoute {
  path: string;
  labelKey: string;
  icon?: LucideIcon;
  lazy?: () => Promise<{ Component: ComponentType<unknown> }>;
  externalURL?: string | undefined;
  subLinks?: AppRoute[];
}

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
      },
      {
        path: 'dayparting',
        labelKey: 'Dayparting',
        externalURL: '/dayparting/view',
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
      },
      {
        path: 'layout',
        labelKey: 'Layouts',
        externalURL: '/layout/view',
      },
      {
        path: 'templates',
        labelKey: 'Templates',
        externalURL: '/template/view',
      },
      {
        path: 'resolutions',
        labelKey: 'Resolutions',
        externalURL: '/resolution/view',
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
      },
      {
        path: 'media',
        labelKey: 'Media',
        lazy: () => import('@/pages/Library/Media/Media').then((m) => ({ Component: m.default })),
      },
      {
        path: 'datasets',
        labelKey: 'Datasets',
        externalURL: '/dataset/view',
      },
      {
        path: 'menu-boards',
        labelKey: 'Menu Boards',
        externalURL: '/menuboard/view',
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
      },
      {
        path: 'display-groups',
        labelKey: 'Display Groups',
        externalURL: '/displaygroup/view',
      },
      {
        path: 'sync-groups',
        labelKey: 'Sync Groups',
        externalURL: '/syncgroup/view',
      },
      {
        path: 'settings',
        labelKey: 'Settings',
        externalURL: '/displayprofile/view',
      },
      {
        path: 'commands',
        labelKey: 'Commands',
        externalURL: '/command/view',
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
      },
      {
        path: 'user-groups',
        labelKey: 'User Groups',
        externalURL: '/group/view',
      },
      {
        path: 'settings',
        labelKey: 'Settings',
        externalURL: '/admin/view',
      },
      {
        path: 'applications',
        labelKey: 'Applications',
        externalURL: '/application/view',
      },
      {
        path: 'modules',
        labelKey: 'Modules',
        externalURL: '/module/view',
      },
      {
        path: 'transitions',
        labelKey: 'Transitions',
        externalURL: '/transition/view',
      },
      {
        path: 'tasks',
        labelKey: 'Tasks',
        externalURL: '/task/view',
      },
      {
        path: 'tags',
        labelKey: 'Tags',
        externalURL: '/tag/view',
      },
      {
        path: 'folders',
        labelKey: 'Folders',
        externalURL: '/folders/view',
      },
      {
        path: 'fonts',
        labelKey: 'Fonts',
        externalURL: '/fonts/view',
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
      },
      {
        path: 'report-schedules',
        labelKey: 'Report Schedules',
        externalURL: '/report/reportschedule/view',
      },
      {
        path: 'saved-reports',
        labelKey: 'Saved Reports',
        externalURL: '/report/savedreport/view',
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
        externalURL: '/report/savedreport/view',
      },
      {
        path: 'sessions',
        labelKey: 'Sessions',
        externalURL: '/sessions/view',
      },
      {
        path: 'audit-trail',
        labelKey: 'Audit Trail',
        externalURL: '/audit/view',
      },
      {
        path: 'report-fault',
        labelKey: 'Report Fault',
        externalURL: '/fault/view',
      },
    ],
  },
  {
    path: 'developer',
    labelKey: 'Developer',
    icon: CodeXml,
    externalURL: '/developer/template/view',
  },
  {
    path: 'settings',
    labelKey: 'Settings',
    icon: Settings,
    externalURL: '/admin/view',
  },
];
