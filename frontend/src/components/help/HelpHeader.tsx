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

import { X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface HelpHeaderProps {
  title: string;
  onClose?: () => void;
}

export default function HelpHeader({ title, onClose }: HelpHeaderProps) {
  const { t } = useTranslation();

  return (
    <div className="flex items-center gap-4 px-3 py-2 bg-gray-100">
      <span className="flex-1 text-sm font-semibold uppercase text-gray-500 leading-5">
        {title}
      </span>
      {onClose && (
        <button
          type="button"
          aria-label={t('Close')}
          onClick={onClose}
          className="flex items-center justify-center size-8.5 absolute right-0 top-0 shrink-0 text-gray-500 hover:text-gray-900 cursor-pointer"
        >
          <X className="size-4" />
        </button>
      )}
    </div>
  );
}
