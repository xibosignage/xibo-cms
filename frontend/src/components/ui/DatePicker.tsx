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
import { DayPicker, getDefaultClassNames } from 'react-day-picker';
import 'react-day-picker/dist/style.css';

import Button from './Button';

export type DatePickerMode = 'single' | 'range';

export interface DatePickerProps {
  mode: DatePickerMode;
  value?: {
    date?: Date;
    from?: Date;
    to?: Date;
  };

  onApply: (
    value: { type: 'single'; date: Date } | { type: 'range'; from: Date; to: Date },
  ) => void;

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

export default function DatePicker({ onApply, onCancel, value, mode }: DatePickerProps) {
  const defaultClassNames = getDefaultClassNames();
  const [single, setSingle] = useState<Date | undefined>(value?.date);
  const [range, setRange] = useState<DateRange | undefined>({
    from: value?.from,
    to: value?.to,
  });

  const [hour, setHour] = useState('12');
  const [minute, setMinute] = useState('00');
  const [period, setPeriod] = useState<'AM' | 'PM'>('PM');

  const timeClass =
    'h-[32px] font-semibold w-[70px] rounded-lg border border-gray-200 px-3 text-xs bg-white';

  // helper to apply time to date
  const applyTime = (date: Date) => {
    const h24 = to24Hour(hour, period);
    const d = new Date(date);
    d.setHours(h24);
    d.setMinutes(Number(minute));
    d.setSeconds(0);
    d.setMilliseconds(0);
    return d;
  };

  const handleApply = () => {
    if (mode === 'single' && single) {
      onApply({
        type: 'single',
        date: applyTime(single),
      });
    }

    if (mode === 'range' && range?.from && range?.to) {
      onApply({
        type: 'range',
        from: new Date(range.from),
        to: new Date(range.to),
      });
    }
  };

  const commonClassNames = {
    months: 'flex flex-col items-center',
    caption: 'flex items-center justify-center mb-2 px-1',
    caption_label: 'font-medium text-gray-800 text-sm',
    month_caption: 'w-full flex justify-center h-[36px] items-center text-[20px] font-semibold',
    nav: 'flex items-center justify-between w-full absolute top-0',
    nav_button:
      'h-8 w-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-700',
  };

  return (
    <div className="w-[380px]">
      <div className="p-3 bg-white">
        {mode === 'single' ? (
          <DayPicker
            mode="single"
            selected={single}
            onSelect={setSingle}
            numberOfMonths={1}
            disabled={{ before: new Date() }}
            className="text-sm flex flex-col datepicker"
            classNames={commonClassNames}
          />
        ) : (
          <DayPicker
            mode="range"
            selected={range}
            onSelect={setRange}
            numberOfMonths={1}
            disabled={{ after: new Date() }}
            className="datepicker"
            classNames={{
              ...commonClassNames,
              day_button: `${defaultClassNames.day_button} text-sm`,
              selected: 'text-sm',
            }}
          />
        )}
      </div>

      {/* Time picker */}
      {mode === 'single' && (
        <div className="px-3 pb-3 flex justify-center">
          <div className="flex items-center gap-x-2">
            <select value={hour} onChange={(e) => setHour(e.target.value)} className={timeClass}>
              {HOURS.map((h) => (
                <option key={h}>{h}</option>
              ))}
            </select>
            <span className="text-gray-500 font-semibold">:</span>
            <select
              value={minute}
              onChange={(e) => setMinute(e.target.value)}
              className={timeClass}
            >
              {MINUTES.map((m) => (
                <option key={m}>{m}</option>
              ))}
            </select>
            <select
              value={period}
              onChange={(e) => setPeriod(e.target.value as 'AM' | 'PM')}
              className={timeClass}
            >
              {PERIODS.map((p) => (
                <option key={p}>{p}</option>
              ))}
            </select>
          </div>
        </div>
      )}
      {/* Footer */}
      <div className="flex justify-between items-center p-4 border-t border-gray-200">
        <p className="text-xs text-gray-600">
          {mode === 'single' && single && single.toLocaleDateString()}

          {mode === 'range' && range?.from && (
            <>
              {range.from.toLocaleDateString()}
              {range.to ? ` – ${range.to.toLocaleDateString()}` : ''}
            </>
          )}
        </p>
        <div className="flex gap-x-2.5">
          <Button variant="secondary" onClick={onCancel}>
            Cancel
          </Button>
          <Button
            onClick={handleApply}
            disabled={mode === 'single' ? !single : !range?.from || !range?.to}
          >
            Apply
          </Button>
        </div>
      </div>
    </div>
  );
}
