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

import { Calendar, ChevronDown, ChevronLeft, ChevronRight } from 'lucide-react';
import { DateTime } from 'luxon';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '@/components/ui/Button';
import DatePicker from '@/components/ui/DatePicker';
import MonthPicker from '@/components/ui/MonthPicker';
import { useUserContext } from '@/context/UserContext';
import { formatDateTime } from '@/utils/date';

type ViewMode = 'day' | 'week' | 'month' | 'year' | 'custom' | 'always';

export interface DateRangeControllerState {
  viewMode: ViewMode;
  currentDate: string;
  customFrom: string;
  customTo: string;
}

interface DateRangeControllerProps {
  onDateRangeChange: (fromDt: string | undefined, toDt: string | undefined) => void;
  onDateChange?: (date: Date) => void;
  lockedViewMode?: ViewMode;
  initialState?: Partial<DateRangeControllerState> | null;
  isReady?: boolean;
  onStateChange?: (state: DateRangeControllerState) => void;
}

function toLocalISO(d: Date): string {
  const p = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
}

function computeRange(
  mode: ViewMode,
  date: Date,
  timezone: string,
): { from: Date; to: Date } | null {
  const dt = DateTime.fromJSDate(date, { zone: timezone });
  if (mode === 'day') {
    return { from: dt.startOf('day').toJSDate(), to: dt.endOf('day').toJSDate() };
  }
  if (mode === 'week') {
    const weekStart = dt.startOf('week');
    return { from: weekStart.toJSDate(), to: weekStart.endOf('week').toJSDate() };
  }
  if (mode === 'month') {
    return { from: dt.startOf('month').toJSDate(), to: dt.endOf('month').toJSDate() };
  }
  if (mode === 'year') {
    return { from: dt.startOf('year').toJSDate(), to: dt.endOf('year').toJSDate() };
  }
  return null;
}

function formatDateLabel(
  mode: ViewMode,
  date: Date,
  customFrom: string,
  customTo: string,
  timezone: string,
): string {
  const fmt = (d: Date, opts: Intl.DateTimeFormatOptions) =>
    new Intl.DateTimeFormat(undefined, opts).format(d);

  if (mode === 'always') {
    return 'Always';
  }
  if (mode === 'day') {
    return fmt(date, { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' });
  }
  if (mode === 'week') {
    const dt = DateTime.fromJSDate(date, { zone: timezone });
    const start = dt.startOf('week').toJSDate();
    const end = dt.endOf('week').toJSDate();
    const sameYear = start.getFullYear() === end.getFullYear();
    const startStr = fmt(start, {
      day: 'numeric',
      month: 'short',
      ...(!sameYear ? { year: 'numeric' } : {}),
    });
    const endStr = fmt(end, { day: 'numeric', month: 'short', year: 'numeric' });
    return `${startStr} – ${endStr}`;
  }
  if (mode === 'month') {
    return fmt(date, { month: 'long', year: 'numeric' });
  }
  if (mode === 'year') {
    return String(date.getFullYear());
  }
  if (mode === 'custom') {
    if (!customFrom || !customTo) {
      return 'Select range';
    }
    const fromDate = new Date(customFrom);
    const toDate = new Date(customTo);
    const sameYear = fromDate.getFullYear() === toDate.getFullYear();
    const fromStr = fmt(fromDate, {
      day: 'numeric',
      month: 'short',
      ...(!sameYear ? { year: 'numeric' } : {}),
    });
    const toStr = fmt(toDate, { day: 'numeric', month: 'short', year: 'numeric' });
    return `${fromStr} – ${toStr}`;
  }
  return '';
}

function addUnit(date: Date, mode: ViewMode, delta: number): Date {
  const d = new Date(date);
  if (mode === 'day') {
    d.setDate(d.getDate() + delta);
  } else if (mode === 'week') {
    d.setDate(d.getDate() + delta * 7);
  } else if (mode === 'month') {
    d.setMonth(d.getMonth() + delta);
  } else if (mode === 'year') {
    d.setFullYear(d.getFullYear() + delta);
  }
  return d;
}

const VIEW_MODE_OPTIONS: { value: ViewMode; label: string }[] = [
  { value: 'always', label: 'Always' },
  { value: 'day', label: 'Day' },
  { value: 'week', label: 'Week' },
  { value: 'month', label: 'Month' },
  { value: 'year', label: 'Year' },
  { value: 'custom', label: 'Custom' },
];

export function DateRangeController({
  onDateRangeChange,
  onDateChange,
  lockedViewMode,
  initialState,
  isReady = false,
  onStateChange,
}: DateRangeControllerProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const timezone = user?.settings?.defaultTimezone ?? 'UTC';

  const [viewMode, setViewMode] = useState<ViewMode>(
    lockedViewMode ?? initialState?.viewMode ?? 'day',
  );
  const [currentDate, setCurrentDate] = useState<Date>(() =>
    initialState?.currentDate ? new Date(initialState.currentDate) : new Date(),
  );
  const [customFrom, setCustomFrom] = useState(initialState?.customFrom ?? '');
  const [customTo, setCustomTo] = useState(initialState?.customTo ?? '');
  const [showViewDropdown, setShowViewDropdown] = useState(false);
  const [showDatePicker, setShowDatePicker] = useState(false);
  const [showMonthPicker, setShowMonthPicker] = useState(false);

  const dropdownRef = useRef<HTMLDivElement>(null);
  const datePickerRef = useRef<HTMLDivElement>(null);
  const monthPickerRef = useRef<HTMLDivElement>(null);
  const hasHydrated = useRef(false);

  useEffect(() => {
    function handleClickOutside(e: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
        setShowViewDropdown(false);
      }
      if (datePickerRef.current && !datePickerRef.current.contains(e.target as Node)) {
        setShowDatePicker(false);
      }
      if (monthPickerRef.current && !monthPickerRef.current.contains(e.target as Node)) {
        setShowMonthPicker(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  useEffect(() => {
    if (!isReady || hasHydrated.current) {
      return;
    }
    hasHydrated.current = true;
    if (initialState?.viewMode) {
      setViewMode(initialState.viewMode);
    }
    if (initialState?.currentDate) {
      setCurrentDate(new Date(initialState.currentDate));
    }
    if (initialState?.customFrom !== undefined) {
      setCustomFrom(initialState.customFrom);
    }
    if (initialState?.customTo !== undefined) {
      setCustomTo(initialState.customTo);
    }
  }, [isReady, initialState]);

  useEffect(() => {
    if (lockedViewMode) {
      setViewMode(lockedViewMode);
    }
  }, [lockedViewMode]);

  useEffect(() => {
    onDateChange?.(currentDate);
  }, [currentDate, onDateChange]);

  useEffect(() => {
    if (!hasHydrated.current) {
      return;
    }
    onStateChange?.({
      viewMode,
      currentDate: currentDate.toISOString(),
      customFrom,
      customTo,
    });
  }, [viewMode, currentDate, customFrom, customTo, onStateChange]);

  useEffect(() => {
    if (viewMode === 'always') {
      onDateRangeChange(undefined, undefined);
      return;
    }
    if (viewMode === 'custom') {
      if (customFrom && customTo) {
        const from = DateTime.fromISO(customFrom, { zone: timezone }).startOf('day').toJSDate();
        const to = DateTime.fromISO(customTo, { zone: timezone }).endOf('day').toJSDate();
        onDateRangeChange(formatDateTime(from, timezone), formatDateTime(to, timezone));
      } else {
        onDateRangeChange(undefined, undefined);
      }
      return;
    }
    const range = computeRange(viewMode, currentDate, timezone);
    if (range) {
      onDateRangeChange(formatDateTime(range.from, timezone), formatDateTime(range.to, timezone));
    }
  }, [viewMode, currentDate, customFrom, customTo, timezone]);

  const handlePrev = () => {
    setCurrentDate((d) => addUnit(d, viewMode, -1));
  };

  const handleNext = () => {
    setCurrentDate((d) => addUnit(d, viewMode, 1));
  };

  const handleToday = () => {
    setCurrentDate(new Date());
  };

  const handleSelectViewMode = (mode: ViewMode) => {
    setViewMode(mode);
    setShowViewDropdown(false);
    if (mode === 'custom') {
      setShowDatePicker(true);
    } else {
      setCustomFrom('');
      setCustomTo('');
    }
  };

  const currentLabel = formatDateLabel(viewMode, currentDate, customFrom, customTo, timezone);

  return (
    <div className="w-full lg:flex-1 md:min-w-0 flex items-center gap-3 flex-wrap">
      {!lockedViewMode && (
        <div className="relative" ref={dropdownRef}>
          <Button
            rightIcon={ChevronDown}
            variant="tertiary"
            onClick={() => setShowViewDropdown((prev) => !prev)}
          >
            {t(VIEW_MODE_OPTIONS.find((o) => o.value === viewMode)?.label ?? 'Day')}
          </Button>
          {showViewDropdown && (
            <div className="absolute left-0 top-full mt-1 z-50 bg-white border border-gray-200 rounded-lg shadow-md min-w-27.5">
              {VIEW_MODE_OPTIONS.map((option) => (
                <Button
                  key={option.value}
                  variant="tertiary"
                  onClick={() => handleSelectViewMode(option.value)}
                  className={`w-full justify-start rounded-none px-4 py-2 first:rounded-t-lg last:rounded-b-lg ${
                    viewMode === option.value ? 'font-semibold text-xibo-blue-600' : 'text-gray-700'
                  }`}
                >
                  {t(option.label)}
                </Button>
              ))}
            </div>
          )}
        </div>
      )}

      {viewMode === 'month' && lockedViewMode === 'month' && (
        <div className="relative" ref={monthPickerRef}>
          <Button
            variant="tertiary"
            rightIcon={Calendar}
            onClick={() => setShowMonthPicker((prev) => !prev)}
          />

          {showMonthPicker && (
            <div className="absolute left-0 top-full mt-1 z-50">
              <MonthPicker
                value={currentDate}
                onChange={(date) => {
                  setCurrentDate(date);
                  setShowMonthPicker(false);
                }}
              />
            </div>
          )}
        </div>
      )}

      {viewMode !== 'custom' && viewMode !== 'always' && (
        <>
          <Button variant="tertiary" className="text-xibo-blue-600" onClick={handleToday}>
            {t('Today')}
          </Button>
          <Button variant="tertiary" className="text-xibo-blue-600" onClick={handlePrev}>
            <ChevronLeft size={21} />
          </Button>
          <Button variant="tertiary" className="text-xibo-blue-600" onClick={handleNext}>
            <ChevronRight size={21} />
          </Button>
        </>
      )}

      {viewMode === 'custom' && (
        <div className="relative" ref={datePickerRef}>
          <Button
            rightIcon={Calendar}
            variant="tertiary"
            onClick={() => setShowDatePicker((prev) => !prev)}
          />
          {showDatePicker && (
            <div className="absolute left-0 top-full mt-1 z-50 shadow-lg rounded-lg border border-gray-200 bg-white">
              <DatePicker
                mode="range"
                value={
                  customFrom && customTo
                    ? { from: new Date(customFrom), to: new Date(customTo) }
                    : undefined
                }
                onApply={(result) => {
                  if (result.type === 'range') {
                    setCustomFrom(toLocalISO(result.from));
                    setCustomTo(toLocalISO(result.to));
                    setViewMode('custom');
                  }
                  setShowDatePicker(false);
                }}
                onCancel={() => setShowDatePicker(false)}
              />
            </div>
          )}
        </div>
      )}

      <div className="text-base font-semibold leading-7.5">{currentLabel}</div>
    </div>
  );
}
