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
// Test type: Pure function — transformData (Time Connected)
// =============================================================================

import { describe, test, expect } from 'vitest';

import { transformData } from '../hooks/useTimeConnectedData';

import type { TimeConnectedTable } from '@/services/timeConnectedApi';
import { knownFailure } from '@/testUtils/knownFailure';

const table = (t: unknown) => t as TimeConnectedTable;

const LOBBY_AND_OFFICE = table({
  timeConnected: [
    {
      '2027-01-01': { '1': { label: '1 Jan', percent: 100 }, '2': { label: '1 Jan', percent: 50 } },
      '2027-01-02': { '1': { label: '2 Jan', percent: 100 }, '2': { label: '2 Jan', percent: 0 } },
    },
  ],
  displays: [{ '1': 'Lobby Screen', '2': 'Back Office' }],
  displayMeta: { '1': { lastAccessed: '2027-01-02 09:00' } },
});

describe('transformData', () => {
  test('one row per display, with a figure per period', () => {
    const rows = transformData(LOBBY_AND_OFFICE);

    expect(rows.map((r) => r.displayName)).toEqual(['Lobby Screen', 'Back Office']);
    expect(rows[1]!.periods).toEqual([
      { label: '1 Jan', percent: 50 },
      { label: '2 Jan', percent: 0 },
    ]);
    expect(rows[1]!.uptimePercent).toBe(25);
  });

  // Known issue 15 (intent unconfirmed): pinned as current behaviour.
  test('a period with no data for a display currently counts as fully connected', () => {
    const rows = transformData(
      table({
        timeConnected: [
          { '2027-01-01': {}, '2027-01-02': { '1': { label: '2 Jan', percent: 0 } } },
        ],
        displays: [{ '1': 'Lobby Screen' }],
      }),
    );

    expect(rows[0]!.periods).toEqual([
      { label: '2027-01-01', percent: 100 },
      { label: '2 Jan', percent: 0 },
    ]);
    expect(rows[0]!.uptimePercent).toBe(50);
  });

  test('percentages are kept within 0–100 and uptime is their average to 2 places', () => {
    const rows = transformData(
      table({
        timeConnected: [
          {
            a: { '1': { label: 'A', percent: 120 }, '2': { label: 'A', percent: 33.333 } },
            b: { '1': { label: 'B', percent: -5 }, '2': { label: 'B', percent: 66.666 } },
            c: { '1': { label: 'C', percent: 50 }, '2': { label: 'C', percent: 0 } },
          },
        ],
        displays: [{ '1': 'Clamped', '2': 'Rounded' }],
      }),
    );

    expect(rows[0]!.periods.map((p) => p.percent)).toEqual([100, 0, 50]);
    expect(rows[0]!.uptimePercent).toBe(50);
    expect(rows[1]!.uptimePercent).toBe(33.33);
  });

  test('a display with no periods has 0% uptime', () => {
    const rows = transformData(table({ timeConnected: [{}], displays: [{ '1': 'Lobby Screen' }] }));

    expect(rows[0]!).toEqual(expect.objectContaining({ uptimePercent: 0, periods: [] }));
  });

  test("last seen comes from the display's details, or is empty", () => {
    const rows = transformData(LOBBY_AND_OFFICE);

    expect(rows[0]!.lastAccessed).toBe('2027-01-02 09:00');
    expect(rows[1]!.lastAccessed).toBeNull();
  });

  // Known issue 15: xibosignage/xibo#4099 (display in two groups becomes two rows)
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4099 is fixed', () => {
    knownFailure(
      'a display in two groups is listed once',
      () =>
        transformData(
          table({
            timeConnected: [
              { '2027-01-01': { '1': { label: '1 Jan', percent: 100 } } },
              { '2027-01-01': { '1': { label: '1 Jan', percent: 100 } } },
            ],
            displays: [{ '1': 'Lobby Screen' }, { '1': 'Lobby Screen' }],
          }),
        ),
      (rows) => expect(rows.map((r) => r.displayId)).toEqual([1]),
    );
  });
});
