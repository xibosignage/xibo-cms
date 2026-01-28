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

import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

export interface TabNavItem {
  labelKey: string;
  path: string;
  externalURL?: string;
}

type TabNavProps = {
  navigation: TabNavItem[];
  activeTab: string;
  onTabClick?: (tab: TabNavItem) => void;
};

export default function TabNav({ navigation, activeTab, onTabClick }: TabNavProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();

  const handleInternalClick = (tab: TabNavItem) => {
    if (onTabClick) {
      onTabClick(tab);
      return;
    }

    if (tab.externalURL) {
      window.location.assign(tab.externalURL);
    } else {
      navigate(tab.path);
    }
  };

  return (
    <>
      <nav
        className="-mb-px hidden sm:flex gap-x-6 justify-between overflow-x-auto"
        aria-label="Tabs"
      >
        {navigation.map((tab) => {
          const isActive = tab.labelKey === activeTab;

          return (
            <button
              key={tab.path}
              type="button"
              className={`text-sm font-semibold px-3 py-2 inline-flex items-center gap-x-2 border-b-2 whitespace-nowrap focus:outline-none transition-colors cursor-pointer ${
                isActive
                  ? 'text-xibo-blue-600 border-xibo-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-600 hover:border-gray-300'
              }`}
              aria-current={isActive ? 'page' : undefined}
              onClick={() => handleInternalClick(tab)}
            >
              {t(tab.labelKey)}
            </button>
          );
        })}
      </nav>

      <div className="flex align-middle font-semibold text-[16px] sm:hidden">{t(activeTab)}</div>
    </>
  );
}
