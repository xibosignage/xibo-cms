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

import { useState } from 'react';
import type { DateRange } from 'react-day-picker';
import { DayPicker } from 'react-day-picker';
import 'react-day-picker/dist/style.css';
import { twMerge } from 'tailwind-merge';

import Button from './Button';

interface FormDatePickerProps {
  onApply: (range: { from: Date; to: Date }) => void;
  onCancel: () => void;
}

const HOURS = Array.from({ length: 12 }, (_, i) => String(i + 1).padStart(2, '0'));

const MINUTES = Array.from({ length: 60 }, (_, i) => String(i).padStart(2, '0'));

const PERIODS: Array<'AM' | 'PM'> = ['AM', 'PM'];

function to24Hour(hour: string, period: 'AM' | 'PM') {
  const h = Number(hour);
  if (period === 'AM') return h === 12 ? 0 : h;
  return h === 12 ? 12 : h + 12;
}

export default function FormDatePicker({ onApply, onCancel }: FormDatePickerProps) {
  const [range, setRange] = useState<DateRange | undefined>();
  const [hour, setHour] = useState('12');
  const [minute, setMinute] = useState('00');
  const [period, setPeriod] = useState<'AM' | 'PM'>('PM');

  const timeClass =
    'h-[32px] font-semibold w-[70px] rounded-lg border border-gray-200 px-3 text-xs bg-white';

  const handleApply = () => {
    if (!range?.from || !range?.to) return;

    const hours24 = to24Hour(hour, period);
    const minutes = Number(minute);

    const from = new Date(range.from);
    const to = new Date(range.to);

    from.setHours(hours24, minutes, 0, 0);
    to.setHours(hours24, minutes, 0, 0);

    onApply({ from, to });
  };

  return (
    <div className="w-[380px]">
      <div className="p-3 bg-white">
        <DayPicker
          mode="range"
          selected={range}
          onSelect={setRange}
          numberOfMonths={1}
          className="text-sm flex flex-col"
          classNames={{
            months: 'flex flex-col items-center',
            caption: 'flex items-center justify-center mb-2 px-1',
            caption_label: 'font-medium text-gray-800 text-sm',
            month_caption:
              'w-ful flex justify-center h-[36px] items-center text-[20px] font-semibold',
            nav: 'flex items-center justify-between w-full absolute top-0',
            chevron: 'fill-gray-400',
            nav_button:
              'h-8 w-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-700',
            selected: 'bg-blue-600',
            range_start: 'bg-blue-600 text-white rounded-l-full',
            range_end: 'bg-blue-600 text-white rounded-r-full',
            // 👇 THIS is the gray bar you’re missing
            range_middle: 'bg-gray-100 text-black',
          }}
        />
      </div>
      <div className="px-3 pb-3 flex justify-center">
        <div className="flex w- items-center gap-x-2">
          {/* Hour */}
          <select
            value={hour}
            onChange={(e) => setHour(e.target.value)}
            className={twMerge(timeClass)}
          >
            {HOURS.map((h) => (
              <option key={h} value={h}>
                {h}
              </option>
            ))}
          </select>

          <span className="text-gray-500 font-semibold">:</span>

          {/* Minute */}
          <select
            value={minute}
            onChange={(e) => setMinute(e.target.value)}
            className={twMerge(timeClass)}
          >
            {MINUTES.map((m) => (
              <option key={m} value={m}>
                {m}
              </option>
            ))}
          </select>

          {/* AM / PM */}
          <select
            value={period}
            onChange={(e) => setPeriod(e.target.value as 'AM' | 'PM')}
            className={twMerge(timeClass)}
          >
            {PERIODS.map((p) => (
              <option key={p} value={p}>
                {p}
              </option>
            ))}
          </select>
        </div>
      </div>
      <div className="flex justify-between items-center px-4 border-t border-gray-200 pt-4">
        <p className="mt-2 text-xs text-gray-600">
          {range && range.from?.toLocaleDateString()} – {range && range.to?.toLocaleDateString()}
        </p>
        <div className="gap-x-2.5 flex">
          <Button variant="secondary" onClick={onCancel}>
            Cancel
          </Button>
          <Button onClick={handleApply} disabled={!range?.from || !range?.to}>
            Apply
          </Button>
        </div>
      </div>
    </div>
  );
}
