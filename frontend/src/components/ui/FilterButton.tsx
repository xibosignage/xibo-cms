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
