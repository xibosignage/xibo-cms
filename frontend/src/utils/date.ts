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

export function formatDateTime(date: Date) {
  return new Intl.DateTimeFormat('sv-SE', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  })
    .format(date)
    .replace(',', '');
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

  const endOfToday = new Date();
  endOfToday.setHours(23, 59, 59, 999);

  if (value === 'today') {
    const start = new Date();
    start.setHours(0, 0, 0, 0);

    return {
      modifiedDateFrom: formatDateTime(start),
      modifiedDateTo: formatDateTime(endOfToday),
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
      modifiedDateFrom: formatDateTime(new Date(from)),
      modifiedDateTo: formatDateTime(new Date(to)),
    };
  }

  return {};
}
