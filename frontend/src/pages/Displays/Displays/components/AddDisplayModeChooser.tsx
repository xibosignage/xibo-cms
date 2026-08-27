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

import { ChevronRight, KeyRound, Sliders } from 'lucide-react';
import { useTranslation } from 'react-i18next';

/** Which route the operator takes to connect their Player. */
export type ConnectMode = 'code' | 'manual';

interface AddDisplayModeChooserProps {
  onSelect: (mode: ConnectMode) => void;
}

/**
 * First screen of Add Display: how is this Player going to be pointed at the CMS?
 *
 * The two routes differ in more than presentation. With an activation code the CMS knows which
 * submission a registration belongs to and applies the chosen settings itself. Configured by hand
 * there is no such link, so the settings are applied from here once the Player turns up.
 */
export default function AddDisplayModeChooser({ onSelect }: AddDisplayModeChooserProps) {
  const { t } = useTranslation();

  const options = [
    {
      mode: 'code' as const,
      icon: KeyRound,
      title: t('Use Activation Code'),
      description: t('Enter the code shown on the Player screen. Best for Players you can see.'),
    },
    {
      mode: 'manual' as const,
      icon: Sliders,
      title: t('Manually Configure'),
      description: t('Copy the CMS address and key into the Player yourself.'),
    },
  ];

  return (
    <div className="px-6 py-4 flex flex-col gap-4">
      <p className="text-sm text-gray-500">{t('How would you like to connect your display?')}</p>

      <div className="flex flex-col gap-3">
        {options.map((option) => (
          <button
            key={option.mode}
            type="button"
            onClick={() => onSelect(option.mode)}
            className="group flex items-center gap-4 rounded-lg border border-gray-200 bg-white p-4 text-left transition-colors hover:border-xibo-blue-400 hover:bg-xibo-blue-50 focus:outline-none focus:border-xibo-blue-600 focus:ring-1 focus:ring-xibo-blue-600/25"
          >
            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-xibo-blue-100 text-xibo-blue-500">
              <option.icon size={20} aria-hidden="true" />
            </span>

            <span className="flex min-w-0 flex-1 flex-col gap-0.5">
              <span className="text-sm font-semibold text-gray-800">{option.title}</span>
              <span className="text-xs text-gray-500">{option.description}</span>
            </span>

            <ChevronRight
              size={16}
              aria-hidden="true"
              className="shrink-0 text-gray-400 group-hover:text-xibo-blue-500"
            />
          </button>
        ))}
      </div>
    </div>
  );
}
