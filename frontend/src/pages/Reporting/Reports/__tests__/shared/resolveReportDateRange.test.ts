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
// Test type: Unit — how a report's Range value becomes request dates
// =============================================================================

import { describe, test, expect } from 'vitest';

import { resolveReportDateRange } from '../../shared/utils/resolveReportDateRange';

describe('resolveReportDateRange', () => {
  test('a named range passes through unchanged', () => {
    expect(resolveReportDateRange('today')).toEqual({ reportFilter: 'today' });
  });

  test('a picked range becomes start-of-day and end-of-day bounds', () => {
    expect(resolveReportDateRange('range:2027-01-01|2027-01-07')).toEqual({
      fromDt: '2027-01-01 00:00:00',
      toDt: '2027-01-07 23:59:59',
      fromDtTime: undefined,
      toDtTime: undefined,
    });
  });

  test('times from the picker are kept beside the day bounds', () => {
    expect(resolveReportDateRange('range:2027-01-01T09:30|2027-01-07T17:00')).toEqual({
      fromDt: '2027-01-01 00:00:00',
      toDt: '2027-01-07 23:59:59',
      fromDtTime: '09:30',
      toDtTime: '17:00',
    });
  });

  test('an empty range gives nothing', () => {
    expect(resolveReportDateRange('')).toEqual({});
  });

  test('a malformed range gives no dates instead of throwing', () => {
    const range = resolveReportDateRange('range:nonsense');

    expect(range.fromDt).toBeUndefined();
    expect(range.toDt).toBeUndefined();
  });
});
