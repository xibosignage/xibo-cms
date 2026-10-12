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

import { afterEach, describe, expect, test } from 'vitest';

import { formatCmsDate, formatCmsDateTime } from '../date';

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
  process.env.TZ = originalTz;
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

  test.fails('browser in Asia/Manila: shows 08:29, not 01:29', () => {
    browserIn('Asia/Manila');
    expect(formatCmsDateTime(SERVER_STRING, { format: FORMAT, timeZone: CMS_TZ })).toBe(
      '2026-10-08 08:29',
    );
  });

  test.fails('browser in Pacific/Auckland: shows 08:29 on the same day', () => {
    browserIn('Pacific/Auckland');
    expect(formatCmsDateTime(SERVER_STRING, { format: FORMAT, timeZone: CMS_TZ })).toBe(
      '2026-10-08 08:29',
    );
  });

  test.fails('browser in America/Los_Angeles: shows 08:29, not a later time', () => {
    browserIn('America/Los_Angeles');
    expect(formatCmsDateTime(SERVER_STRING, { format: FORMAT, timeZone: CMS_TZ })).toBe(
      '2026-10-08 08:29',
    );
  });

  test.fails('date only, browser in Pacific/Auckland: shows the stored day', () => {
    browserIn('Pacific/Auckland');
    expect(formatCmsDate(SERVER_STRING, { format: FORMAT, timeZone: CMS_TZ })).toBe('2026-10-08');
  });
});
