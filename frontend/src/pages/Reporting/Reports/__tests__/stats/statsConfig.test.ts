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
// Test type: Unit — the Summary and Distribution configs
// =============================================================================

import { describe, test, expect } from 'vitest';

import { DISTRIBUTION_CONFIG } from '../../Distribution/DistributionConfig';
import { SUMMARY_CONFIG } from '../../Summary/SummaryConfig';

const values = (options: { value: string }[]) => options.map((o) => o.value);

describe('Summary config', () => {
  test.each([
    ['today', []],
    ['yesterday', []],
    ['thisweek', []],
    ['lastweek', []],
    ['thismonth', ['byday', 'byweek']],
    ['lastmonth', ['byday', 'byweek']],
    ['thisyear', ['byday', 'byweek', 'bymonth']],
    ['lastyear', ['byday', 'byweek', 'bymonth']],
    ['range:2027-01-01|2027-01-07', ['byday', 'byweek', 'bymonth']],
  ])('the %s range offers Group By %j', (range, expected) => {
    expect(values(SUMMARY_CONFIG.getGroupByOptions(range))).toEqual(expected);
  });

  test('year ranges default to Month; everything else to Day', () => {
    expect(SUMMARY_CONFIG.defaultGroupBy('thisyear')).toBe('bymonth');
    expect(SUMMARY_CONFIG.defaultGroupBy('lastyear')).toBe('bymonth');
    expect(SUMMARY_CONFIG.defaultGroupBy('thismonth')).toBe('byday');
    expect(SUMMARY_CONFIG.defaultGroupBy('range:2027-01-01|2027-01-07')).toBe('byday');
  });
});

describe('Distribution config', () => {
  test('every range offers Hour, Day of the week and Day of the month', () => {
    for (const range of ['today', 'thisyear', 'range:2027-01-01|2027-01-07']) {
      expect(values(DISTRIBUTION_CONFIG.getGroupByOptions(range))).toEqual([
        'byhour',
        'bydayofweek',
        'bydayofmonth',
      ]);
    }
  });

  test('the default Group By is Hour, and the schedule form offers the same choices', () => {
    expect(DISTRIBUTION_CONFIG.defaultGroupBy('thisyear')).toBe('byhour');
    expect(values(DISTRIBUTION_CONFIG.scheduleGroupByOptions!)).toEqual([
      'byhour',
      'bydayofweek',
      'bydayofmonth',
    ]);
  });
});

describe.each([
  ['Summary', SUMMARY_CONFIG],
  ['Distribution', DISTRIBUTION_CONFIG],
])('%s large-data warning', (_name, config) => {
  test.each(['thismonth', 'lastmonth', 'thisyear', 'lastyear'])('the %s range warns', (range) => {
    expect(config.shouldShowDataWarning(range)).toBe(true);
  });

  test.each(['today', 'yesterday', 'thisweek', 'lastweek'])(
    "the %s range doesn't warn",
    (range) => {
      expect(config.shouldShowDataWarning(range)).toBe(false);
    },
  );

  test('a picked range warns from 30 days apart, not at 29', () => {
    expect(config.shouldShowDataWarning('range:2027-01-01|2027-01-31')).toBe(true);
    expect(config.shouldShowDataWarning('range:2027-01-01|2027-01-30')).toBe(false);
  });
});
