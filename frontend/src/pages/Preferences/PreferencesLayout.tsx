/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { NavLink } from 'react-router-dom';
import { twMerge } from 'tailwind-merge';

import { generateTabNavigation, PREFERENCES_ROUTE } from '@/config/appRoutes';
import { useUserContext } from '@/context/UserContext';
import { useServiceVisibility } from '@/hooks/useCmsVisibility';

interface PreferencesLayoutProps {
  children: ReactNode;
}

export default function PreferencesLayout({ children }: PreferencesLayoutProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const getServiceVisibility = useServiceVisibility();
  const tabs = generateTabNavigation(PREFERENCES_ROUTE, user, getServiceVisibility);

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 py-5">
        <div className="flex-1 flex min-h-0 rounded-lg border border-gray-200 overflow-hidden">
          <nav
            aria-label={t('Preferences')}
            className="bg-white px-5 py-2 border-r border-gray-200 flex min-w-35 flex-col"
          >
            {tabs.map((tab) => (
              <NavLink
                key={tab.path}
                to={tab.path}
                className={({ isActive }) =>
                  twMerge(
                    'text-sm text-gray-500 font-semibold px-3 py-2 inline-flex items-center gap-x-2 whitespace-nowrap rounded-lg focus:outline-none transition-colors',
                    isActive ? 'text-xibo-blue-600 bg-xibo-blue-50' : 'hover:text-gray-600',
                  )
                }
              >
                {t(tab.labelKey)}
              </NavLink>
            ))}
          </nav>

          <div className="flex-1 min-w-0 min-h-0 overflow-y-auto flex flex-col bg-slate-50">
            {children}
          </div>
        </div>
      </div>
    </section>
  );
}
