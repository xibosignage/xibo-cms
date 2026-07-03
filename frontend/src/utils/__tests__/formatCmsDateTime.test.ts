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

import { describe, test, expect } from 'vitest';

import { formatCmsDateTime, formatCmsTime, formatWithMomentTokens } from '../date';

const INSTANT = new Date('2026-03-09T14:07:05.042Z');

describe('formatCmsDateTime — common CMS DATE_FORMAT values (UTC)', () => {
  const cases: Array<[string, string]> = [
    ['YYYY-MM-DD HH:mm', '2026-03-09 14:07'], // CMS default (Y-m-d H:i)
    ['YYYY-MM-DD HH:mm:ss', '2026-03-09 14:07:05'],
    ['DD/MM/YYYY HH:mm', '09/03/2026 14:07'], // d/m/Y H:i
    ['MM/DD/YYYY h:mm A', '03/09/2026 2:07 PM'], // m/d/Y g:i A
    ['DD/MM/YYYY', '09/03/2026'],
    ['YYYY-MM-DD', '2026-03-09'],
    ['ddd, DD MMM YYYY', 'Mon, 09 Mar 2026'], // named weekday + month
    ['dddd', 'Monday'],
    ['MMMM YYYY', 'March 2026'],
    ['hh:mm a', '02:07 pm'],
  ];

  test.each(cases)('format "%s" -> "%s"', (format, expected) => {
    expect(formatCmsDateTime(INSTANT, { format, timeZone: 'UTC' })).toBe(expected);
  });
});

describe('formatCmsDateTime — timezone awareness', () => {
  test('renders wall-clock time in the requested timezone', () => {
    expect(
      formatCmsDateTime(INSTANT, { format: 'YYYY-MM-DD HH:mm', timeZone: 'America/New_York' }),
    ).toBe('2026-03-09 10:07');
  });

  test('date can roll to the previous day in a western timezone', () => {
    const earlyUtc = new Date('2026-03-09T02:00:00Z');
    expect(
      formatCmsDateTime(earlyUtc, { format: 'YYYY-MM-DD', timeZone: 'America/Los_Angeles' }),
    ).toBe('2026-03-08');
  });
});

describe('formatCmsDateTime — input handling', () => {
  test('defaults to the CMS default format when none supplied', () => {
    expect(formatCmsDateTime(INSTANT, { timeZone: 'UTC' })).toBe('2026-03-09 14:07');
  });

  test('accepts an ISO string', () => {
    expect(
      formatCmsDateTime('2026-03-09T14:07:05Z', { format: 'YYYY-MM-DD', timeZone: 'UTC' }),
    ).toBe('2026-03-09');
  });

  test('returns empty string for null/undefined/invalid', () => {
    expect(formatCmsDateTime(null, { timeZone: 'UTC' })).toBe('');
    expect(formatCmsDateTime(undefined, { timeZone: 'UTC' })).toBe('');
    expect(formatCmsDateTime('not-a-date', { timeZone: 'UTC' })).toBe('');
  });
});

describe('formatCmsTime', () => {
  test('formats time only with the default time format', () => {
    expect(formatCmsTime(INSTANT, { timeZone: 'UTC' })).toBe('14:07');
  });

  test('honours a 12-hour time format', () => {
    expect(formatCmsTime(INSTANT, { format: 'h:mm A', timeZone: 'UTC' })).toBe('2:07 PM');
  });
});

describe('formatWithMomentTokens — escaping and edge tokens', () => {
  test('emits [bracketed] literals verbatim', () => {
    expect(formatWithMomentTokens(INSTANT, '[Updated] YYYY', 'UTC')).toBe('Updated 2026');
  });

  test('midnight renders as 00, not 24', () => {
    const midnight = new Date('2026-03-09T00:00:00Z');
    expect(formatWithMomentTokens(midnight, 'HH:mm', 'UTC')).toBe('00:00');
    expect(formatWithMomentTokens(midnight, 'hh:mm A', 'UTC')).toBe('12:00 AM');
  });

  test('unix seconds token', () => {
    expect(formatWithMomentTokens(INSTANT, 'X', 'UTC')).toBe(
      String(Math.floor(INSTANT.getTime() / 1000)),
    );
  });
});
