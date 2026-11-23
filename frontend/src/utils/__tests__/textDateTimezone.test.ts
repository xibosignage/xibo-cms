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

import { afterEach, describe, expect, test } from 'vitest';

import { formatCmsDate, formatCmsDateTime } from '../date';

import { setTimezone } from '@/testUtils/timezone';

/**
 * The server sends most grid dates (Layouts Modified, Events Created On, ...) as the CMS's own
 * wall-clock time with no zone: "2026-10-08 08:29:37" means 08:29 in the CMS timezone. Whatever
 * timezone the browser is in, the grid should show 08:29.
 */

const CMS_TZ = 'Europe/London'; // BST (UTC+1) on 8 October
const SERVER_STRING = '2026-10-08 08:29:37'; // 08:29 London = 07:29 UTC
const FORMAT = 'YYYY-MM-DD HH:mm';

const originalTz = process.env.TZ;
afterEach(() => {
  setTimezone(originalTz);
});

/** Run the rest of the test as if the browser were in `zone`. */
const browserIn = (zone: string) => {
  process.env.TZ = zone;
};

describe('the environment can switch browser timezone', () => {
  test('process.env.TZ really changes the local offset', () => {
    // Guards the tests below: if TZ switching stopped working, they would all run in one zone.
    const instant = new Date('2026-10-08T07:29:37Z');
    browserIn('Asia/Manila');
    expect(instant.getTimezoneOffset()).toBe(-8 * 60);
    browserIn('Pacific/Auckland');
    expect(instant.getTimezoneOffset()).toBe(-13 * 60);
  });
});

describe('a zone-less server date shows the CMS wall-clock time in any browser timezone', () => {
  test('browser in the CMS timezone: shows the stored time (control)', () => {
    browserIn(CMS_TZ);
    expect(formatCmsDateTime(SERVER_STRING, { format: FORMAT, timeZone: CMS_TZ })).toBe(
      '2026-10-08 08:29',
    );
  });

  test('an instant with a zone shows the CMS time from any browser timezone (control)', () => {
    // Events Start arrives as a timestamp, which is why it was steady in every zone at runtime.
    browserIn('Asia/Manila');
    expect(formatCmsDateTime('2026-10-08T07:29:37Z', { format: FORMAT, timeZone: CMS_TZ })).toBe(
      '2026-10-08 08:29',
    );
  });

  test.fails('browser in Asia/Manila: shows 08:29, not 01:29', { retry: 0 }, () => {
    browserIn('Asia/Manila');
    expect(formatCmsDateTime(SERVER_STRING, { format: FORMAT, timeZone: CMS_TZ })).toBe(
      '2026-10-08 08:29',
    );
  });

  test.fails('browser in Pacific/Auckland: shows 08:29 on the same day', { retry: 0 }, () => {
    browserIn('Pacific/Auckland');
    expect(formatCmsDateTime(SERVER_STRING, { format: FORMAT, timeZone: CMS_TZ })).toBe(
      '2026-10-08 08:29',
    );
  });

  test.fails('browser in America/Los_Angeles: shows 08:29, not a later time', { retry: 0 }, () => {
    browserIn('America/Los_Angeles');
    expect(formatCmsDateTime(SERVER_STRING, { format: FORMAT, timeZone: CMS_TZ })).toBe(
      '2026-10-08 08:29',
    );
  });

  test.fails('date only, browser in Pacific/Auckland: shows the stored day', { retry: 0 }, () => {
    browserIn('Pacific/Auckland');
    expect(formatCmsDate(SERVER_STRING, { format: FORMAT, timeZone: CMS_TZ })).toBe('2026-10-08');
  });
});
