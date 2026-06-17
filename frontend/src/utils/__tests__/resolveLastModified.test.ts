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

import { describe, test, expect, vi, beforeEach, afterEach } from 'vitest';

import { resolveLastModified } from '../date';

// ---------------------------------------------------------------------------
// Pin "now" to a fixed local time so assertions are deterministic.
// Using a local-time string (no "Z") keeps startOfDay / formatDateTime in sync
// regardless of the test runner's timezone.
// ---------------------------------------------------------------------------

const FAKE_NOW = new Date('2026-06-15T12:00:00');

describe('resolveLastModified', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(FAKE_NOW);
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  // -------------------------------------------------------------------------
  // S21–S23 — no value → empty object
  // -------------------------------------------------------------------------

  test('S21: undefined returns empty object', () => {
    expect(resolveLastModified(undefined)).toEqual({});
  });

  test('S22: empty string returns empty object', () => {
    expect(resolveLastModified('')).toEqual({});
  });

  test('S23: null returns empty object', () => {
    expect(resolveLastModified(null)).toEqual({});
  });

  // -------------------------------------------------------------------------
  // S24 — "today"
  // -------------------------------------------------------------------------

  test('S24: "today" returns start-of-day and end-of-day for the current date', () => {
    const result = resolveLastModified('today');

    expect(result).toHaveProperty('modifiedDateFrom');
    expect(result).toHaveProperty('modifiedDateTo');

    // Both should fall on the same calendar date as the fake now (2026-06-15).
    expect(result.modifiedDateFrom).toContain('2026-06-15');
    expect(result.modifiedDateTo).toContain('2026-06-15');

    // From should be earlier than To.
    expect(new Date(result.modifiedDateFrom!).getTime()).toBeLessThan(
      new Date(result.modifiedDateTo!).getTime(),
    );
  });

  // -------------------------------------------------------------------------
  // S25 — "7d"
  // -------------------------------------------------------------------------

  test('S25: "7d" returns a window starting ~7 days ago and ending now', () => {
    const result = resolveLastModified('7d');

    expect(result).toHaveProperty('modifiedDateFrom');
    expect(result).toHaveProperty('modifiedDateTo');

    // From should be 7 days before 2026-06-15 → somewhere in 2026-06-08.
    expect(result.modifiedDateFrom).toContain('2026-06-08');

    // To is "now" (no rounding) → 2026-06-15.
    expect(result.modifiedDateTo).toContain('2026-06-15');
  });

  // -------------------------------------------------------------------------
  // S26 — "30d"
  // -------------------------------------------------------------------------

  test('S26: "30d" returns a window starting ~30 days ago', () => {
    const result = resolveLastModified('30d');

    expect(result).toHaveProperty('modifiedDateFrom');
    // 30 days before 2026-06-15 = 2026-05-16.
    expect(result.modifiedDateFrom).toContain('2026-05-16');
    expect(result.modifiedDateTo).toContain('2026-06-15');
  });

  // -------------------------------------------------------------------------
  // S27 — "1y"
  // -------------------------------------------------------------------------

  test('S27: "1y" returns a window starting ~365 days ago', () => {
    const result = resolveLastModified('1y');

    expect(result).toHaveProperty('modifiedDateFrom');
    // 365 days before 2026-06-15 = 2025-06-15.
    expect(result.modifiedDateFrom).toContain('2025-06-15');
    expect(result.modifiedDateTo).toContain('2026-06-15');
  });

  // -------------------------------------------------------------------------
  // S28 — custom range
  // -------------------------------------------------------------------------

  test('S28: a valid range string returns modifiedDateFrom and modifiedDateTo', () => {
    const result = resolveLastModified('range:2026-01-01|2026-01-31');

    expect(result).toHaveProperty('modifiedDateFrom');
    expect(result).toHaveProperty('modifiedDateTo');

    // To must be strictly later than From.
    const from = new Date(result.modifiedDateFrom!);
    const to = new Date(result.modifiedDateTo!);
    expect(to.getTime()).toBeGreaterThan(from.getTime());
  });

  // -------------------------------------------------------------------------
  // S29 — malformed / unknown values
  // -------------------------------------------------------------------------

  test('S29a: range with a single part returns empty object', () => {
    expect(resolveLastModified('range:only-one')).toEqual({});
  });

  test('S29b: range with empty "to" part returns empty object', () => {
    expect(resolveLastModified('range:2026-01-01|')).toEqual({});
  });

  test('S29c: unknown preset string returns empty object', () => {
    expect(resolveLastModified('custom')).toEqual({});
  });
});
