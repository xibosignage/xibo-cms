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

import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';

import Button from './Button';

const MONTH_ABBRS = Array.from({ length: 12 }, (_, i) =>
  new Intl.DateTimeFormat(undefined, { month: 'short' }).format(new Date(2000, i, 1)),
);

export interface MonthPickerProps {
  value?: Date;
  onChange: (date: Date) => void;
}

export default function MonthPicker({ value, onChange }: MonthPickerProps) {
  const [pickerYear, setPickerYear] = useState<number>(
    () => value?.getFullYear() ?? new Date().getFullYear(),
  );

  return (
    <div className="bg-white border border-gray-200 rounded-lg shadow-md p-3 min-w-56">
      <div className="flex items-center justify-between mb-3">
        <Button
          variant="tertiary"
          leftIcon={ChevronLeft}
          ariaLabel="Previous year"
          className="p-1"
          onClick={() => setPickerYear((y) => y - 1)}
        />
        <span className="text-sm font-semibold">{pickerYear}</span>
        <Button
          variant="tertiary"
          leftIcon={ChevronRight}
          ariaLabel="Next year"
          className="p-1"
          onClick={() => setPickerYear((y) => y + 1)}
        />
      </div>
      <div className="grid grid-cols-3 gap-1">
        {MONTH_ABBRS.map((abbr, idx) => {
          const isSelected =
            value !== undefined && pickerYear === value.getFullYear() && idx === value.getMonth();
          return (
            <Button
              key={abbr}
              variant="tertiary"
              onClick={() => onChange(new Date(pickerYear, idx, 1, 0, 0, 0, 0))}
              className={`w-full py-1.5 rounded ${
                isSelected ? 'bg-xibo-blue-600 text-white hover:bg-xibo-blue-700' : 'text-gray-700'
              }`}
            >
              {abbr}
            </Button>
          );
        })}
      </div>
    </div>
  );
}
