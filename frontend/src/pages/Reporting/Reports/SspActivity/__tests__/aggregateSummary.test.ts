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

// =============================================================================
// Test type: Unit — aggregateSummary (SSP Activity)
// =============================================================================

import { afterAll, beforeAll, describe, test, expect } from 'vitest';

import { buildSspRow } from '../../__tests__/fixtures/reportRows';
import { aggregateSummary } from '../SspActivityConfig';

import { setTimezone } from '@/testUtils/timezone';

const fmt = (value: unknown) => `date:${String(value)}`;
const originalTz = process.env.TZ;

describe('aggregateSummary', () => {
  beforeAll(() => {
    process.env.TZ = 'UTC';
  });
  afterAll(() => {
    setTimezone(originalTz);
  });

  test('groups by hour, by error code, or by both', () => {
    const rows = [
      buildSspRow({ scheduledAt: '2027-01-01T09:15:00Z', errorCode: 'E1' }),
      buildSspRow({ scheduledAt: '2027-01-01T09:45:00Z', errorCode: 'E2' }),
      buildSspRow({ scheduledAt: '2027-01-01T10:05:00Z', errorCode: 'E1' }),
    ];

    expect(aggregateSummary(rows, 'hour', fmt).rows.map((r) => r.key)).toEqual([
      '2027-01-01 09:00',
      '2027-01-01 10:00',
    ]);
    expect(aggregateSummary(rows, 'errorCode', fmt).rows.map((r) => r.key)).toEqual(['E1', 'E2']);
    expect(aggregateSummary(rows, 'hourerrorcode', fmt).rows.map((r) => r.key)).toEqual([
      '2027-01-01 09:00 - E1',
      '2027-01-01 09:00 - E2',
      '2027-01-01 10:00 - E1',
    ]);
  });

  // A miss is a row that neither played nor errored.
  test('counts plays and misses, and sums errors and impressions', () => {
    const rows = [
      buildSspRow({
        isPlayed: true,
        isErrored: false,
        errors: 0,
        impressions: 2,
        impressionActual: 2,
      }),
      buildSspRow({
        isPlayed: false,
        isErrored: true,
        errors: 3,
        impressions: 0,
        impressionActual: 0,
      }),
      buildSspRow({
        isPlayed: false,
        isErrored: false,
        errors: 0,
        impressions: 1,
        impressionActual: 1,
      }),
    ];

    const [group] = aggregateSummary(rows, 'hour', fmt).rows;

    expect(group).toEqual(
      expect.objectContaining({
        playCount: 1,
        errorCount: 3,
        missesCount: 1,
        impressions: 3,
        impressionActual: 3,
      }),
    );
  });

  test('groups are ordered by their key, with undated rows first', () => {
    const rows = [
      buildSspRow({ scheduledAt: '2027-01-01T10:05:00Z' }),
      buildSspRow({ scheduledAt: null }),
      buildSspRow({ scheduledAt: '2027-01-01T09:15:00Z' }),
    ];

    const { rows: groups } = aggregateSummary(rows, 'hour', fmt);

    expect(groups.map((g) => g.key)).toEqual(['', '2027-01-01 09:00', '2027-01-01 10:00']);
    expect(groups[0]).toEqual(expect.objectContaining({ date: '', time: '' }));
  });

  test('totals add up across groups', () => {
    const rows = [
      buildSspRow({ scheduledAt: '2027-01-01T09:15:00Z', isPlayed: true, impressions: 2 }),
      buildSspRow({
        scheduledAt: '2027-01-01T10:15:00Z',
        isPlayed: false,
        isErrored: true,
        errors: 2,
        impressions: 0,
        impressionActual: 0,
      }),
      buildSspRow({
        scheduledAt: '2027-01-01T11:15:00Z',
        isPlayed: false,
        isErrored: false,
        impressions: 0,
        impressionActual: 0,
      }),
    ];

    expect(aggregateSummary(rows, 'hour', fmt).stats).toEqual({
      totalPlayCount: 1,
      totalErrorCount: 2,
      totalMissCount: 1,
      totalImpressions: 2,
      impressionActual: 2,
    });
  });

  test("a group's date, hour, campaign and error code come from its last row", () => {
    const rows = [
      buildSspRow({ scheduledAt: '2027-01-01T09:15:00Z', campaignId: 'first', errorCode: 'E1' }),
      buildSspRow({ scheduledAt: '2027-01-01T09:45:00Z', campaignId: 'last', errorCode: 'E2' }),
    ];

    const [group] = aggregateSummary(rows, 'hour', fmt).rows;

    expect(group).toEqual(
      expect.objectContaining({
        campaignId: 'last',
        errorCode: 'E2',
        date: 'date:2027-01-01T09:45:00Z',
        time: '09:00',
      }),
    );
  });

  test('no rows give no groups and zero totals', () => {
    expect(aggregateSummary([], 'hour', fmt)).toEqual({
      rows: [],
      stats: {
        totalErrorCount: 0,
        totalPlayCount: 0,
        totalMissCount: 0,
        totalImpressions: 0,
        impressionActual: 0,
      },
    });
  });
});
