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

export type ExpiryValue =
  | { type: 'never' }
  | { type: 'preset'; value: string }
  | { type: 'datePicked'; date: Date };

function getParts(date: Date, options: Intl.DateTimeFormatOptions) {
  const parts = new Intl.DateTimeFormat('en', options).formatToParts(date);
  return (type: string) => parts.find((p) => p.type === type)?.value ?? '00';
}

export function formatDate(date: Date, timeZone?: string) {
  const get = getParts(date, {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    ...(timeZone ? { timeZone } : {}),
  });
  return `${get('month')}-${get('day')}-${get('year')}`;
}

export function formatDateTime(date: Date, timeZone?: string) {
  const get = getParts(date, {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
    ...(timeZone ? { timeZone } : {}),
  });
  return `${get('year')}-${get('month')}-${get('day')} ${get('hour')}:${get('minute')}:${get('second')}`;
}

function daysFromNow(days: number) {
  return new Date(Date.now() + days * 24 * 60 * 60 * 1000);
}

export function expiryToDateTime(expiry: ExpiryValue): string | undefined {
  if (expiry.type === 'never') return undefined;

  let date: Date;

  if (expiry.type === 'preset') {
    switch (expiry.value) {
      case 'End of Today': {
        date = new Date();
        date.setHours(23, 59, 59, 0);
        break;
      }

      case 'In 7 Days':
        date = daysFromNow(7);
        break;

      case 'In 14 Days':
        date = daysFromNow(14);
        break;

      case 'In 30 Days':
        date = daysFromNow(30);
        break;

      default:
        return undefined;
    }
  } else {
    date = expiry.date;
  }

  return formatDateTime(date);
}

export function expiresToExpiryValue(expires?: string): ExpiryValue {
  if (!expires) return { type: 'never' };

  const iso = expires.replace(' ', 'T');

  const date = new Date(iso);

  if (isNaN(date.getTime())) {
    return { type: 'never' };
  }

  return {
    type: 'datePicked',
    date,
  };
}

export function resolveLastModified(value?: string | null) {
  if (!value) return {};

  const now = new Date();

  function startOfDay(date: Date) {
    const d = new Date(date);
    d.setHours(0, 0, 0, 0);
    return d;
  }

  function endOfDay(date: Date) {
    const d = new Date(date);
    d.setHours(23, 59, 59, 999);
    return d;
  }

  if (value === 'today') {
    return {
      modifiedDateFrom: formatDateTime(startOfDay(now)),
      modifiedDateTo: formatDateTime(endOfDay(now)),
    };
  }
  const daysAgo = (days: number) => new Date(Date.now() - days * 24 * 60 * 60 * 1000);

  if (value === '7d') {
    return {
      modifiedDateFrom: formatDateTime(daysAgo(7)),
      modifiedDateTo: formatDateTime(now),
    };
  }

  if (value === '30d') {
    return {
      modifiedDateFrom: formatDateTime(daysAgo(30)),
      modifiedDateTo: formatDateTime(now),
    };
  }

  if (value === '1y') {
    return {
      modifiedDateFrom: formatDateTime(daysAgo(365)),
      modifiedDateTo: formatDateTime(now),
    };
  }

  if (value.startsWith('range:')) {
    const rangeValue = value.replace('range:', '');
    const parts = rangeValue.split('|');

    if (parts.length !== 2) return {};

    const [from, to] = parts;
    if (!from || !to) return {};

    return {
      modifiedDateFrom: formatDateTime(startOfDay(new Date(from))),
      modifiedDateTo: formatDateTime(endOfDay(new Date(to))),
    };
  }

  return {};
}

export function resolveLastAccessed(value?: string | null) {
  if (!value) return {};
  return { lastAccessed: value };
}

export const DEFAULT_CMS_DATE_FORMAT = 'YYYY-MM-DD HH:mm';
export const DEFAULT_CMS_TIME_FORMAT = 'HH:mm';

type DateParts = {
  year: number;
  month: number; // 1-12
  day: number; // 1-31
  hour: number; // 0-23
  minute: number;
  second: number;
  millisecond: number;
  weekday: number; // ISO: 1 (Mon) - 7 (Sun)
};

const pad = (value: number, length = 2) => String(value).padStart(length, '0');

const ISO_WEEKDAYS: Record<string, number> = {
  Mon: 1,
  Tue: 2,
  Wed: 3,
  Thu: 4,
  Fri: 5,
  Sat: 6,
  Sun: 7,
};

function extractParts(date: Date, timeZone?: string): DateParts {
  const parts = new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
    weekday: 'short',
    ...(timeZone ? { timeZone } : {}),
  }).formatToParts(date);

  const find = (type: string) => parts.find((p) => p.type === type)?.value ?? '';

  let hour = Number(find('hour'));
  if (hour === 24) {
    hour = 0;
  }

  return {
    year: Number(find('year')),
    month: Number(find('month')),
    day: Number(find('day')),
    hour,
    minute: Number(find('minute')),
    second: Number(find('second')),
    millisecond: date.getMilliseconds(),
    weekday: ISO_WEEKDAYS[find('weekday')] ?? 1,
  };
}

function localizedName(
  date: Date,
  option: Intl.DateTimeFormatOptions,
  type: string,
  timeZone?: string,
  locale = 'en',
): string {
  const parts = new Intl.DateTimeFormat(locale, {
    ...option,
    ...(timeZone ? { timeZone } : {}),
  }).formatToParts(date);
  return parts.find((p) => p.type === type)?.value ?? '';
}

function ordinalSuffix(value: number): string {
  const mod100 = value % 100;
  if (mod100 >= 11 && mod100 <= 13) {
    return 'th';
  }
  switch (value % 10) {
    case 1:
      return 'st';
    case 2:
      return 'nd';
    case 3:
      return 'rd';
    default:
      return 'th';
  }
}

function dayOfYear(p: DateParts): number {
  const start = Date.UTC(p.year, 0, 1);
  const current = Date.UTC(p.year, p.month - 1, p.day);
  return Math.floor((current - start) / 86400000) + 1;
}

function isoWeek(p: DateParts): number {
  const d = new Date(Date.UTC(p.year, p.month - 1, p.day));
  const dayNum = (d.getUTCDay() + 6) % 7; // Monday = 0
  d.setUTCDate(d.getUTCDate() - dayNum + 3); // Thursday of this week
  const firstThursday = new Date(Date.UTC(d.getUTCFullYear(), 0, 4));
  const firstDayNum = (firstThursday.getUTCDay() + 6) % 7;
  return 1 + Math.round((d.getTime() - firstThursday.getTime()) / 86400000 / 7 - firstDayNum / 7);
}

const MOMENT_TOKEN_REGEX =
  /\[([^\]]*)]|YYYY|YY|MMMM|MMM|MM|M|DDD|DD|Do|D|dddd|ddd|E|e|HH|H|hh|h|mm|m|ss|s|SSS|A|a|X|W|o|zz/g;

export interface CmsDateFormatOptions {
  format?: string;
  timeZone?: string;
  locale?: string;
}

export function formatWithMomentTokens(
  date: Date,
  format: string,
  timeZone?: string,
  locale = 'en',
): string {
  if (Number.isNaN(date.getTime())) {
    return '';
  }

  const p = extractParts(date, timeZone);
  const hour12 = p.hour % 12 === 0 ? 12 : p.hour % 12;

  return format.replace(MOMENT_TOKEN_REGEX, (match, literal?: string) => {
    if (literal !== undefined) {
      return literal;
    }
    switch (match) {
      case 'YYYY':
        return pad(p.year, 4);
      case 'YY':
        return pad(p.year % 100);
      case 'MMMM':
        return localizedName(date, { month: 'long' }, 'month', timeZone, locale);
      case 'MMM':
        return localizedName(date, { month: 'short' }, 'month', timeZone, locale);
      case 'MM':
        return pad(p.month);
      case 'M':
        return String(p.month);
      case 'DDD':
        return String(dayOfYear(p));
      case 'DD':
        return pad(p.day);
      case 'Do':
        return `${p.day}${ordinalSuffix(p.day)}`;
      case 'D':
        return String(p.day);
      case 'dddd':
        return localizedName(date, { weekday: 'long' }, 'weekday', timeZone, locale);
      case 'ddd':
        return localizedName(date, { weekday: 'short' }, 'weekday', timeZone, locale);
      case 'E':
        return String(p.weekday);
      case 'e':
        return String(p.weekday % 7);
      case 'HH':
        return pad(p.hour);
      case 'H':
        return String(p.hour);
      case 'hh':
        return pad(hour12);
      case 'h':
        return String(hour12);
      case 'mm':
        return pad(p.minute);
      case 'm':
        return String(p.minute);
      case 'ss':
        return pad(p.second);
      case 's':
        return String(p.second);
      case 'SSS':
        return pad(p.millisecond, 3);
      case 'A':
        return p.hour < 12 ? 'AM' : 'PM';
      case 'a':
        return p.hour < 12 ? 'am' : 'pm';
      case 'X':
        return String(Math.floor(date.getTime() / 1000));
      case 'W':
        return String(isoWeek(p));
      case 'o':
        return ordinalSuffix(p.day);
      case 'zz':
        return timeZone ?? '';
      default:
        return match;
    }
  });
}

export type DateLike = Date | string | null | undefined;

function toDateOrNull(value: DateLike): Date | null {
  if (value === null || value === undefined || value === '') {
    return null;
  }
  const date = value instanceof Date ? value : new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

export function formatCmsDateTime(value: DateLike, options: CmsDateFormatOptions = {}): string {
  const date = toDateOrNull(value);
  if (!date) {
    return '';
  }
  return formatWithMomentTokens(
    date,
    options.format || DEFAULT_CMS_DATE_FORMAT,
    options.timeZone,
    options.locale,
  );
}

export function deriveDateOnlyFormat(format: string): string {
  const firstTimeToken = format.search(/(HH|H|hh|h|mm|m|ss|s|SSS|A|a)/);
  const datePart = firstTimeToken === -1 ? format : format.slice(0, firstTimeToken);
  const trimmed = datePart.replace(/[\s,:/\-.]+$/, '').trim();
  return trimmed || format;
}

export function formatCmsDate(value: DateLike, options: CmsDateFormatOptions = {}): string {
  const date = toDateOrNull(value);
  if (!date) {
    return '';
  }
  return formatWithMomentTokens(
    date,
    deriveDateOnlyFormat(options.format || DEFAULT_CMS_DATE_FORMAT),
    options.timeZone,
    options.locale,
  );
}

export function formatCmsTime(value: DateLike, options: CmsDateFormatOptions = {}): string {
  const date = toDateOrNull(value);
  if (!date) {
    return '';
  }
  return formatWithMomentTokens(
    date,
    options.format || DEFAULT_CMS_TIME_FORMAT,
    options.timeZone,
    options.locale,
  );
}
