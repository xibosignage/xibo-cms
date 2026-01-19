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

import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import Button from '../Button';

type MediaTopbarProps = {
  navigation: string[];
  activeTab: string;
  onTabClick: (tab: string) => void;
};

export default function MediaTopbar({ navigation, activeTab, onTabClick }: MediaTopbarProps) {
  const { t } = useTranslation();

  return (
    <div className="flex flex-row justify-between py-5 items-center gap-4">
      {/* TODO: Navigation tabs */}
      <nav
        className="-mb-px hidden sm:flex gap-x-6 justify-between overflow-x-auto"
        aria-label="Tabs"
      >
        {navigation.map((tab) => (
          <button
            key={tab}
            type="button"
            className={`py-2 text-[14px] px-1 inline-flex text-gray-500 items-center gap-x-2 border-b-2 whitespace-nowrap focus:outline-none transition-colors font-semibold ${
              tab === activeTab
                ? 'text-xibo-blue-600 border-xibo-blue-600'
                : 'border-transparent  hover:text-gray-600 hover:border-gray-300'
            }`}
            aria-current={tab === activeTab ? 'page' : undefined}
            onClick={() => onTabClick(tab)}
          >
            {t(tab)}
          </button>
        ))}
      </nav>
      <div className="flex align-middle font-semibold text-[16px] sm:hidden">{t(activeTab)}</div>

      {/* TODO: Buttons */}
      <div className="flex items-center gap-2 md:mb-0">
        <Button variant="primary" icon={Plus}>
          {t('Add Media')}
        </Button>
      </div>
    </div>
  );
}
