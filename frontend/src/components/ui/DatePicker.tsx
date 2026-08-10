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

import { DateTime } from 'luxon';
import { useState } from 'react';
import type { DateRange } from 'react-day-picker';
import { getDefaultClassNames } from 'react-day-picker';
import { DayPicker as GregorianDayPicker } from 'react-day-picker';
import { enUS, faIR } from 'react-day-picker/locale';
import { DayPicker as PersianDayPicker } from 'react-day-picker/persian';
import { useTranslation } from 'react-i18next';

import 'react-day-picker/dist/style.css';

import Button from './Button';

import { useUserContext } from '@/context/UserContext';
import { useDateFormatter } from '@/hooks/useDateFormatter';

export type DatePickerMode = 'single' | 'range';

export interface DatePickerProps {
  mode: DatePickerMode;
  value?: {
    date?: Date;
    from?: Date;
    to?: Date;
  };
  isJalali?: boolean;
  disablePastDates?: boolean;
  disableFutureDates?: boolean;
  showTimePicker?: boolean;
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

function toFakeLocalDate(date: Date, timeZone: string): Date {
  const dt = DateTime.fromJSDate(date, { zone: timeZone });
  return new Date(dt.year, dt.month - 1, dt.day, dt.hour, dt.minute, dt.second);
}

interface TimeParts {
  hour: string;
  minute: string;
  period: 'AM' | 'PM';
}

const DEFAULT_TIME: TimeParts = { hour: '12', minute: '00', period: 'AM' };

function getTimeParts(date: Date, timeZone?: string): TimeParts {
  const dt = timeZone ? DateTime.fromJSDate(date, { zone: timeZone }) : null;
  const h = dt ? dt.hour : date.getHours();
  const m = dt ? dt.minute : date.getMinutes();
  return {
    hour: String(h % 12 || 12).padStart(2, '0'),
    minute: String(m).padStart(2, '0'),
    period: h >= 12 ? 'PM' : 'AM',
  };
}

interface TimeOfDaySelectProps {
  value: TimeParts;
  onChange: (value: TimeParts) => void;
}

function TimeOfDaySelect({ value, onChange }: TimeOfDaySelectProps) {
  const timeClass =
    'h-[32px] font-semibold w-[70px] rounded-lg border border-gray-200 px-3 text-xs bg-white';

  return (
    <div className="flex items-center gap-x-2">
      <select
        value={value.hour}
        onChange={(e) => onChange({ ...value, hour: e.target.value })}
        className={timeClass}
      >
        {HOURS.map((h) => (
          <option key={h}>{h}</option>
        ))}
      </select>
      <span className="text-gray-500 font-semibold">:</span>
      <select
        value={value.minute}
        onChange={(e) => onChange({ ...value, minute: e.target.value })}
        className={timeClass}
      >
        {MINUTES.map((m) => (
          <option key={m}>{m}</option>
        ))}
      </select>
      <select
        value={value.period}
        onChange={(e) => onChange({ ...value, period: e.target.value as 'AM' | 'PM' })}
        className={timeClass}
      >
        {PERIODS.map((p) => (
          <option key={p}>{p}</option>
        ))}
      </select>
    </div>
  );
}

export default function DatePicker({
  onApply,
  onCancel,
  value,
  mode,
  isJalali = false,
  disablePastDates = false,
  disableFutureDates = false,
  showTimePicker,
}: DatePickerProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const timeZone = user?.settings?.defaultTimezone;
  const { formatDate } = useDateFormatter();

  // Single mode has always shown its time picker by default; range mode never has, so an
  // unset prop must resolve differently per mode to keep every existing caller unaffected.
  const effectiveShowTimePicker = showTimePicker ?? mode === 'single';

  const defaultClassNames = getDefaultClassNames();
  const [single, setSingle] = useState<Date | undefined>(() => {
    if (!value?.date) return undefined;
    return timeZone ? toFakeLocalDate(value.date, timeZone) : value.date;
  });
  const [range, setRange] = useState<DateRange | undefined>({
    from:
      value?.from && timeZone && effectiveShowTimePicker
        ? toFakeLocalDate(value.from, timeZone)
        : value?.from,
    to:
      value?.to && timeZone && effectiveShowTimePicker
        ? toFakeLocalDate(value.to, timeZone)
        : value?.to,
  });

  const [time, setTime] = useState<TimeParts>(() =>
    value?.date ? getTimeParts(value.date, timeZone) : DEFAULT_TIME,
  );
  const [fromTime, setFromTime] = useState<TimeParts>(() =>
    value?.from ? getTimeParts(value.from, timeZone) : DEFAULT_TIME,
  );
  const [toTime, setToTime] = useState<TimeParts>(() =>
    value?.to ? getTimeParts(value.to, timeZone) : DEFAULT_TIME,
  );

  const getDisabledRules = () => {
    if (disablePastDates) return { before: new Date() };
    if (disableFutureDates) return { after: new Date() };
    return undefined;
  };

  const applyTime = (date: Date, time: TimeParts) => {
    const h24 = to24Hour(time.hour, time.period);
    if (timeZone) {
      return DateTime.fromObject(
        {
          year: date.getFullYear(),
          month: date.getMonth() + 1,
          day: date.getDate(),
          hour: h24,
          minute: Number(time.minute),
          second: 0,
          millisecond: 0,
        },
        { zone: timeZone },
      ).toJSDate();
    }
    const d = new Date(date);
    d.setHours(h24);
    d.setMinutes(Number(time.minute));
    d.setSeconds(0);
    d.setMilliseconds(0);
    return d;
  };

  const getRangeValues = () => {
    if (!range?.from || !range?.to) return null;
    return {
      from: effectiveShowTimePicker ? applyTime(range.from, fromTime) : new Date(range.from),
      to: effectiveShowTimePicker ? applyTime(range.to, toTime) : new Date(range.to),
    };
  };

  const rangeValues = getRangeValues();
  const isRangeInvalid =
    mode === 'range' &&
    effectiveShowTimePicker &&
    !!rangeValues &&
    rangeValues.from > rangeValues.to;

  const handleApply = () => {
    if (mode === 'single' && single) {
      onApply({
        type: 'single',
        date: effectiveShowTimePicker ? applyTime(single, time) : single,
      });
    }

    if (mode === 'range' && rangeValues && !isRangeInvalid) {
      onApply({ type: 'range', from: rangeValues.from, to: rangeValues.to });
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

  const ActivePicker = isJalali ? PersianDayPicker : GregorianDayPicker;
  const activeLocale = isJalali ? faIR : enUS;
  const layoutDirection = isJalali ? 'rtl' : 'ltr';

  return (
    <div className="w-95">
      <div className="p-3 bg-white">
        {mode === 'single' ? (
          <ActivePicker
            mode="single"
            selected={single}
            onSelect={setSingle}
            numberOfMonths={1}
            disabled={getDisabledRules()}
            locale={activeLocale}
            dir={layoutDirection}
            className="text-sm flex flex-col datepicker"
            classNames={commonClassNames}
          />
        ) : (
          <ActivePicker
            mode="range"
            selected={range}
            onSelect={setRange}
            numberOfMonths={1}
            disabled={getDisabledRules()}
            locale={activeLocale}
            dir={layoutDirection}
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
      {mode === 'single' && effectiveShowTimePicker && (
        <div className="px-3 pb-3 flex justify-center">
          <TimeOfDaySelect value={time} onChange={setTime} />
        </div>
      )}
      {mode === 'range' && effectiveShowTimePicker && (
        <div className="px-3 pb-3 flex flex-col items-center gap-y-2">
          <div className="flex items-center gap-x-2">
            <span className="text-xs font-semibold text-gray-500 w-10">{t('From')}</span>
            <TimeOfDaySelect value={fromTime} onChange={setFromTime} />
          </div>
          <div className="flex items-center gap-x-2">
            <span className="text-xs font-semibold text-gray-500 w-10">{t('To')}</span>
            <TimeOfDaySelect value={toTime} onChange={setToTime} />
          </div>
          {isRangeInvalid && (
            <p className="text-xs font-semibold text-red-500">
              {t('End time must be after start time')}
            </p>
          )}
        </div>
      )}
      {/* Footer */}
      <div className="flex justify-between items-center p-4 border-t border-gray-200">
        <p className="text-xs text-gray-600" data-testid="datepicker-selected">
          {mode === 'single' && single && formatDate(single)}

          {mode === 'range' && range?.from && (
            <>
              {new Intl.DateTimeFormat(isJalali ? 'fa-IR' : 'en-US', { dateStyle: 'short' }).format(
                range.from,
              )}
              {range.to
                ? ` – ${new Intl.DateTimeFormat(isJalali ? 'fa-IR' : 'en-US', { dateStyle: 'short' }).format(range.to)}`
                : ''}
            </>
          )}
        </p>
        <div className="flex gap-x-2.5">
          <Button variant="secondary" onClick={onCancel}>
            {t('Cancel')}
          </Button>
          <Button
            onClick={handleApply}
            disabled={mode === 'single' ? !single : !range?.from || !range?.to || isRangeInvalid}
          >
            {t('Apply')}
          </Button>
        </div>
      </div>
    </div>
  );
}
