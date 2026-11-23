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
      className="rounded border-gray-200 no-print cursor-pointer text-xibo-blue-600 focus:border-xibo-blue-500 focus:border-2 focus:ring-0 focus:outline-0"
    />
  );
}
