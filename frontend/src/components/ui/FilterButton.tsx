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

import { Filter, FilterX } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import Button from './Button';

export interface FilterButtonProps {
  isOpen: boolean;
  onToggle: () => void;
  activeCount?: number;
  disabled?: boolean;
  label?: string;
}

export default function FilterButton({
  isOpen,
  onToggle,
  activeCount = 0,
  disabled = false,
  label,
}: FilterButtonProps) {
  const { t } = useTranslation();

  return (
    <Button
      leftIcon={!isOpen ? Filter : FilterX}
      variant="secondary"
      onClick={onToggle}
      disabled={disabled}
      removeTextOnMobile
    >
      {label ?? t('Filters')}
      {activeCount > 0 && (
        <span className="inline-flex items-center justify-center min-w-4.5 h-4.5 px-1 rounded-full bg-xibo-blue-600 text-white text-xs font-medium">
          {activeCount}
        </span>
      )}
    </Button>
  );
}
