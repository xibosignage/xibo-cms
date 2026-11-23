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
// Test type: Unit — the in-browser sort the report tables use
// =============================================================================

import { describe, test, expect } from 'vitest';

import { sortRows } from '../../shared/utils/sortRows';

const rows = [
  { name: 'banana', size: 10 },
  { name: 'apple', size: 9 },
  { name: 'cherry', size: 100 },
];

describe('sortRows', () => {
  // A text sort would put 10 before 9 and 100 before both.
  test('numbers sort numerically', () => {
    expect(sortRows(rows, [{ id: 'size', desc: false }]).map((r) => r.size)).toEqual([9, 10, 100]);
  });

  test('text sorts alphabetically', () => {
    expect(sortRows(rows, [{ id: 'name', desc: false }]).map((r) => r.name)).toEqual([
      'apple',
      'banana',
      'cherry',
    ]);
  });

  test('descending reverses the order', () => {
    expect(sortRows(rows, [{ id: 'size', desc: true }]).map((r) => r.size)).toEqual([100, 10, 9]);
  });

  test('no sort keeps the original rows untouched', () => {
    expect(sortRows(rows, [])).toBe(rows);
  });

  test('a field the rows do not have leaves the order unchanged', () => {
    expect(sortRows(rows, [{ id: 'missing', desc: false }]).map((r) => r.name)).toEqual([
      'banana',
      'apple',
      'cherry',
    ]);
  });
});
