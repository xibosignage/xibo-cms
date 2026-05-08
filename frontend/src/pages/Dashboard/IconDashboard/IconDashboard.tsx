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
  BarChart3,
  CalendarDays,
  CircleHelp,
  Info,
  Layout,
  LayoutTemplate,
  Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { twMerge } from 'tailwind-merge';

import AboutModal from '@/components/layout/UserMenu/AboutModal';
import { useUserContext } from '@/context/UserContext';
import type { User } from '@/types/user';
import { UserType } from '@/types/user';
import { hasFeature } from '@/utils/permissions';

interface DashboardItem {
  label: string;
  icon: LucideIcon;
  path: string;
  externalURL?: string;
  action?: string;
  visible: (user: User) => boolean;
}

const DASHBOARD_ITEMS: DashboardItem[] = [
  {
    label: 'Schedule',
    icon: CalendarDays,
    path: '/schedule/events',
    visible: (u) => hasFeature(u, 'schedule.view'),
  },
  {
    label: 'Templates',
    icon: LayoutTemplate,
    path: '/design/templates',
    visible: (u) => hasFeature(u, 'template.view'),
  },
  {
    label: 'Layouts',
    icon: Layout,
    path: '/design/layout',
    visible: (u) => hasFeature(u, 'layout.view'),
  },
  {
    label: 'Users',
    icon: Users,
    path: '/administration/users',
    externalURL: '/user/view',
    visible: (u) =>
      hasFeature(u, 'users.view') &&
      (u.userTypeId === UserType.SuperAdmin || u.userTypeId === UserType.GroupAdmin),
  },
  {
    label: 'Library',
    icon: BarChart3,
    path: '/library/playlist',
    visible: (u) => hasFeature(u, 'library.view'),
  },
  {
    label: 'About',
    icon: Info,
    path: '/about',
    action: 'about',
    visible: () => true,
  },
  {
    label: 'Report Faults',
    icon: CircleHelp,
    path: '/advanced/report-fault',
    externalURL: '/fault/view',
    visible: (u) => hasFeature(u, 'fault.view'),
  },
];

export default function IconDashboard() {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const [showAbout, setShowAbout] = useState(false);

  const visibleItems = user ? DASHBOARD_ITEMS.filter((item) => item.visible(user)) : [];

  const cardClasses = (index: number) =>
    twMerge(
      'flex items-center gap-4 rounded-lg border border-gray-200 bg-slate-50 py-4 px-8 text-sm font-medium',
      index >= 5 ? 'col-span-1' : 'col-span-2',
    );

  return (
    <div className="mx-auto w-full p-5">
      <div className="grid grid-cols-4 gap-4">
        {visibleItems.map((item, index) => {
          const Icon = item.icon;
          const content = (
            <>
              <div className="flex h-11.5 w-11.5 shrink-0 items-center justify-center rounded-full bg-xibo-blue-100">
                <Icon className="h-5 w-5 text-xibo-blue-800" />
              </div>
              <span className="text-[20px] font-semibold text-gray-800">{t(item.label)}</span>
            </>
          );

          if (item.action) {
            return (
              <button
                key={item.path}
                type="button"
                onClick={() => item.action === 'about' && setShowAbout(true)}
                className={cardClasses(index)}
              >
                {content}
              </button>
            );
          }

          if (item.externalURL) {
            return (
              <a key={item.path} href={item.externalURL} className={cardClasses(index)}>
                {content}
              </a>
            );
          }

          return (
            <Link key={item.path} to={item.path} className={cardClasses(index)}>
              {content}
            </Link>
          );
        })}
      </div>

      {showAbout && <AboutModal onClose={() => setShowAbout(false)} />}
    </div>
  );
}
