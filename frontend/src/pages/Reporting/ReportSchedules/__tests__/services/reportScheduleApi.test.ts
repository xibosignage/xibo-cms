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
// Other suites mock this module, so form encoding is only checked here.
// =============================================================================

import { vi, beforeAll, afterAll, beforeEach, describe, test, expect } from 'vitest';

import http from '@/lib/api';
import {
  createReportSchedule,
  deleteAllSavedReportsForSchedule,
  deleteReportSchedule,
  fetchReportSchedules,
  resetReportSchedule,
  toggleActiveReportSchedule,
  updateReportSchedule,
} from '@/services/reportScheduleApi';
import type { CreateReportSchedulePayload } from '@/services/reportScheduleApi';
import { setTimezone } from '@/testUtils/timezone';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/lib/api', () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

// =============================================================================
// Helpers
// =============================================================================

const bodyOf = (method: typeof http.post | typeof http.put) => {
  const calls = vi.mocked(method).mock.calls;
  return new URLSearchParams(calls[calls.length - 1]![1] as string);
};

const BASE_CREATE: CreateReportSchedulePayload = {
  name: 'Weekly PoP',
  reportName: 'proofofplayReport',
  filter: 'weekly',
  groupByFilter: '',
  displayGroupIds: [],
};

const originalTz = process.env.TZ;

// =============================================================================
// Tests
// =============================================================================

describe('reportScheduleApi', () => {
  beforeAll(() => {
    process.env.TZ = 'UTC';
  });
  afterAll(() => {
    setTimezone(originalTz);
  });
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(http.post).mockResolvedValue({ data: {} });
    vi.mocked(http.put).mockResolvedValue({ data: {} });
    vi.mocked(http.delete).mockResolvedValue({ data: {} });
  });

  // fetchReportSchedules

  test('the list reads its total from the count header and keeps the signal out of the query', async () => {
    const controller = new AbortController();
    vi.mocked(http.get).mockResolvedValue({
      data: [{ reportScheduleId: 1 }, { reportScheduleId: 2 }],
      headers: { 'x-total-count': '40' },
    });

    const result = await fetchReportSchedules({ start: 10, length: 10, signal: controller.signal });

    expect(result.totalCount).toBe(40);
    expect(result.rows).toHaveLength(2);
    expect(http.get).toHaveBeenCalledWith('/report/reportschedule', {
      params: { start: 10, length: 10 },
      signal: controller.signal,
    });
  });

  test('the list falls back to the row count without a count header', async () => {
    vi.mocked(http.get).mockResolvedValue({ data: [{}, {}], headers: {} });

    const result = await fetchReportSchedules();

    expect(result.totalCount).toBe(2);
  });

  // updateReportSchedule

  test('editing sends only the name when no dates are set', async () => {
    await updateReportSchedule(5, { name: 'Weekly PoP', fromDt: '', toDt: '' });

    expect(vi.mocked(http.put).mock.calls[0]![0]).toBe('/report/reportschedule/5');
    const body = bodyOf(http.put);
    expect(body.get('name')).toBe('Weekly PoP');
    expect(body.has('fromDt')).toBe(false);
    expect(body.has('toDt')).toBe(false);
  });

  test('editing sends set dates in the CMS date format', async () => {
    await updateReportSchedule(5, {
      name: 'Weekly PoP',
      fromDt: '2027-01-01T00:00:00.000Z',
      toDt: '2027-02-01 09:30:00',
    });

    const body = bodyOf(http.put);
    expect(body.get('fromDt')).toBe('2027-01-01 00:00:00');
    expect(body.get('toDt')).toBe('2027-02-01 09:30:00');
  });

  // createReportSchedule (used by every report page's Schedule modal)

  test('creating encodes lists as repeated fields and the email flag as 0 or 1', async () => {
    await createReportSchedule({
      ...BASE_CREATE,
      displayGroupIds: [1, 2],
      displayGroupId: [9],
      sendEmail: false,
    });

    let body = bodyOf(http.post);
    expect(body.getAll('displayGroupIds[]')).toEqual(['1', '2']);
    expect(body.getAll('displayGroupId[]')).toEqual(['9']);
    expect(body.get('sendEmail')).toBe('0');

    await createReportSchedule({ ...BASE_CREATE, sendEmail: true, nonusers: 'a@b.c' });
    body = bodyOf(http.post);
    expect(body.get('sendEmail')).toBe('1');
    expect(body.get('nonusers')).toBe('a@b.c');
  });

  // Server reads settings from both hiddenFields and top-level fields.
  test('creating sends extra settings bundled and as individual fields, skipping empty ones', async () => {
    const hiddenFields = { type: 'layout', layoutId: [3, 4], sortBy: undefined, eventTag: null };

    await createReportSchedule({ ...BASE_CREATE, hiddenFields });

    const body = bodyOf(http.post);
    expect(JSON.parse(body.get('hiddenFields')!)).toEqual({
      type: 'layout',
      layoutId: [3, 4],
      eventTag: null,
    });
    expect(body.get('type')).toBe('layout');
    expect(body.getAll('layoutId[]')).toEqual(['3', '4']);
    expect(body.has('sortBy')).toBe(false);
    expect(body.has('eventTag')).toBe(false);
  });

  test('creating omits empty optional ids', async () => {
    await createReportSchedule({ ...BASE_CREATE, displayId: null, userId: null, groupId: 0 });

    const body = bodyOf(http.post);
    expect(body.has('displayId')).toBe(false);
    expect(body.has('userId')).toBe(false);
    expect(body.has('groupId')).toBe(false);
  });

  test('creating sends the core fields and formatted dates', async () => {
    await createReportSchedule({
      ...BASE_CREATE,
      displayId: 12,
      fromDt: '2027-01-01T00:00:00.000Z',
    });

    expect(vi.mocked(http.post).mock.calls[0]![0]).toBe('/report/reportschedule');
    const body = bodyOf(http.post);
    expect(body.get('name')).toBe('Weekly PoP');
    expect(body.get('reportName')).toBe('proofofplayReport');
    expect(body.get('filter')).toBe('weekly');
    expect(body.get('displayId')).toBe('12');
    expect(body.get('fromDt')).toBe('2027-01-01 00:00:00');
    expect(body.has('toDt')).toBe(false);
  });

  // Row actions

  test("pause/resume, reset and delete-all call that schedule's own address", async () => {
    await toggleActiveReportSchedule(5);
    await resetReportSchedule(5);
    await deleteAllSavedReportsForSchedule(5);

    expect(vi.mocked(http.post).mock.calls.map(([url]) => url)).toEqual([
      '/report/reportschedule/5/toggleactive',
      '/report/reportschedule/5/reset',
      '/report/reportschedule/5/deletesavedreport',
    ]);
  });

  test('delete calls the schedule address', async () => {
    await deleteReportSchedule(5);

    expect(http.delete).toHaveBeenCalledWith('/report/reportschedule/5');
  });
});
