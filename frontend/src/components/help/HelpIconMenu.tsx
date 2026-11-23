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

import { CircleHelp } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import HelpMenu from './HelpMenu';

export default function HelpIconMenu() {
  const { t } = useTranslation();

  return (
    <HelpMenu
      placement="bottom-end"
      renderTrigger={({ ref, referenceProps, isOpen }) => (
        <button
          ref={ref}
          {...referenceProps}
          type="button"
          aria-label={t('Help & Support')}
          aria-expanded={isOpen}
          title={t('Help & Support')}
          className="cursor-pointer flex items-center justify-center h-9.5 w-9.5 rounded-lg transition hover:bg-blue-100"
        >
          <CircleHelp size={16} className="text-xibo-blue-600" />
        </button>
      )}
    />
  );
}
