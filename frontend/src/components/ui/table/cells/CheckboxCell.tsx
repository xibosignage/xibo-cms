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

import type { ChangeEvent } from 'react';

interface CheckboxCellProps {
  checked?: boolean;
  onChange: (event: ChangeEvent<HTMLInputElement>) => void;
  ariaLabel?: string;
}

export function CheckboxCell({ checked, onChange, ariaLabel = 'Select row' }: CheckboxCellProps) {
  return (
    <input
      type="checkbox"
      checked={checked}
      onChange={onChange}
      aria-label={ariaLabel}
      className="rounded border-gray-200 no-print cursor-pointer focus:border-blue-500 focus:border-2 focus:ring-0 focus:outline-0"
    />
  );
}
