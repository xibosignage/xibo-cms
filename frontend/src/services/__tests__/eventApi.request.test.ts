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

// What the schedule service puts on the wire. eventApi.test.ts covers the
// reminder ids that updateEvent sends; this file covers the rest of the
// request building, through the real axios pipeline.

import { afterEach, beforeEach, describe, expect, test } from 'vitest';

import {
  cloneEvent,
  createEvent,
  deleteEventOccurrence,
  fetchEvent,
  type CreateEventRequest,
} from '@/services/eventApi';
import {
  installHttpStub,
  lastRequest,
  respondWith,
  restoreHttpStub,
} from '@/testUtils/httpAdapter';

beforeEach(installHttpStub);
afterEach(restoreHttpStub);

const baseEvent: CreateEventRequest = {
  eventTypeId: 1,
  displayGroupIds: [5],
  dayPartId: 2,
};

describe('fetchEvent', () => {
  test('defaults to the first ten events', async () => {
    respondWith({ data: [] });

    await fetchEvent();

    const { method, path, query } = lastRequest();
    expect(method).toBe('GET');
    expect(path).toBe('/json/schedule');
    expect(query.get('start')).toBe('0');
    expect(query.get('length')).toBe('10');
  });

  test('sends each display group as a repeated displayGroupIds[] key', async () => {
    respondWith({ data: [] });

    await fetchEvent({
      start: 0,
      length: 10,
      displayGroupIds: [3, 8],
      displaySpecificGroupIds: [9],
    });

    const { query } = lastRequest();
    expect(query.getAll('displayGroupIds[]')).toEqual(['3', '8']);
    expect(query.getAll('displaySpecificGroupIds[]')).toEqual(['9']);
  });

  test('leaves out filters set to null, and never sends the abort signal', async () => {
    respondWith({ data: [] });

    await fetchEvent({
      start: 0,
      length: 10,
      eventTypeId: null,
      campaignId: null,
      recurring: 0,
      signal: new AbortController().signal,
    });

    const { query } = lastRequest();
    expect(query.has('eventTypeId')).toBe(false);
    expect(query.has('campaignId')).toBe(false);
    expect(query.get('recurring')).toBe('0');
    expect(query.has('signal')).toBe(false);
  });

  test('returns the rows and the total from x-total-count, or 0 without it', async () => {
    const rows = [{ eventId: 1 }];
    respondWith({ data: rows, headers: { 'x-total-count': '12' } }, { data: [] });

    expect(await fetchEvent()).toEqual({ rows, totalCount: 12 });
    expect((await fetchEvent()).totalCount).toBe(0);
  });
});

describe('createEvent', () => {
  test('posts the required fields as a form', async () => {
    await createEvent({ ...baseEvent, displayGroupIds: [5, 6] });

    const req = lastRequest();
    expect(req.method).toBe('POST');
    expect(req.path).toBe('/json/schedule');
    expect(req.headers['Content-Type']).toBe('application/x-www-form-urlencoded');
    expect(req.body.get('eventTypeId')).toBe('1');
    expect(req.body.get('dayPartId')).toBe('2');
    expect(req.body.getAll('displayGroupIds[]')).toEqual(['5', '6']);
  });

  test('sends nothing it was not given', async () => {
    await createEvent(baseEvent);

    expect([...lastRequest().body.keys()].sort()).toEqual([
      'dayPartId',
      'displayGroupIds[]',
      'eventTypeId',
    ]);
  });

  test('keeps a 0 where 0 is a real setting', async () => {
    await createEvent({
      ...baseEvent,
      displayOrder: 0,
      isPriority: 0,
      maxPlaysPerHour: 0,
      shareOfVoice: 0,
      isGeoAware: 0,
      syncTimezone: 0,
      layoutDuration: 0,
      recurrenceMonthlyRepeatsOn: 0,
    });

    const { body } = lastRequest();
    for (const key of [
      'displayOrder',
      'isPriority',
      'maxPlaysPerHour',
      'shareOfVoice',
      'isGeoAware',
      'syncTimezone',
      'layoutDuration',
      'recurrenceMonthlyRepeatsOn',
    ]) {
      expect(body.get(key), key).toBe('0');
    }
  });

  test('drops an id of 0, which means none chosen', async () => {
    await createEvent({ ...baseEvent, campaignId: 0, commandId: 0, resolutionId: 0 });

    const { body } = lastRequest();
    expect(body.has('campaignId')).toBe(false);
    expect(body.has('commandId')).toBe(false);
    expect(body.has('resolutionId')).toBe(false);
  });

  test('formats the dates in the CMS timezone', async () => {
    await createEvent(
      {
        ...baseEvent,
        // London is on BST (UTC+1) in July and on GMT in December.
        fromDt: '2026-07-01T09:00:00Z',
        toDt: '2026-07-01T23:30:00Z',
        recurrenceRange: '2026-12-31T23:00:00Z',
      },
      'Europe/London',
    );

    const { body } = lastRequest();
    expect(body.get('fromDt')).toBe('2026-07-01 10:00:00');
    expect(body.get('toDt')).toBe('2026-07-02 00:30:00');
    expect(body.get('recurrenceRange')).toBe('2026-12-31 23:00:00');
  });

  test('formats the dates in UTC when no timezone is given', async () => {
    await createEvent({ ...baseEvent, fromDt: '2026-07-01T09:00:00+02:00' });

    expect(lastRequest().body.get('fromDt')).toBe('2026-07-01 07:00:00');
  });

  test('sends each repeat day as a repeated recurrenceRepeatsOn[] key', async () => {
    await createEvent({
      ...baseEvent,
      recurrenceType: 'Week',
      recurrenceDetail: 1,
      recurrenceRepeatsOn: [1, 3, 5],
    });

    const { body } = lastRequest();
    expect(body.get('recurrenceType')).toBe('Week');
    expect(body.get('recurrenceDetail')).toBe('1');
    expect(body.getAll('recurrenceRepeatsOn[]')).toEqual(['1', '3', '5']);
  });

  test('sends a layout for each synced display that has one', async () => {
    await createEvent({ ...baseEvent, syncGroupId: 4, syncDisplayLayouts: { 11: 20, 12: null } });

    const { body } = lastRequest();
    expect(body.get('syncGroupId')).toBe('4');
    expect(body.get('layoutId_11')).toBe('20');
    expect(body.has('layoutId_12')).toBe(false);
  });

  test('sends each criterion under its own index', async () => {
    await createEvent({
      ...baseEvent,
      criteria: [
        { type: 'custom', metric: 'temp', condition: 'gt', value: '20' },
        { type: 'weather', metric: 'rain', condition: 'eq', value: '1' },
      ],
    });

    const { body } = lastRequest();
    expect(body.get('criteria[0][metric]')).toBe('temp');
    expect(body.get('criteria[0][condition]')).toBe('gt');
    expect(body.get('criteria[1][type]')).toBe('weather');
    expect(body.get('criteria[1][value]')).toBe('1');
  });

  test('sends each reminder under its own index, without an id on create', async () => {
    await createEvent({
      ...baseEvent,
      scheduleReminders: [
        { reminder_value: 15, reminder_type: 1, reminder_option: 1, reminder_isEmailHidden: 0 },
        { reminder_value: 2, reminder_type: 2, reminder_option: 2, reminder_isEmailHidden: 1 },
      ],
    });

    const { body } = lastRequest();
    expect(body.get('reminder_value[0]')).toBe('15');
    expect(body.get('reminder_type[1]')).toBe('2');
    expect(body.get('reminder_isEmailHidden[1]')).toBe('1');
    expect(body.has('reminder_scheduleReminderId[0]')).toBe(false);
  });

  test("rejects with the server's own message when it gives one", async () => {
    respondWith({ status: 422, data: { message: 'Please select a Layout' } });

    await expect(createEvent(baseEvent)).rejects.toThrow('Please select a Layout');
  });

  test('rejects with the request error when the server gives no message', async () => {
    respondWith({ status: 500, data: '' });

    await expect(createEvent(baseEvent)).rejects.toThrow('status code 500');
  });
});

describe('deleteEventOccurrence', () => {
  test('sends the occurrence start and end with the delete', async () => {
    await deleteEventOccurrence(9, 1761555600, 1761559200);

    const { method, path, query } = lastRequest();
    expect(method).toBe('DELETE');
    expect(path).toBe('/json/schedulerecurrence/9');
    expect(query.get('eventStart')).toBe('1761555600');
    expect(query.get('eventEnd')).toBe('1761559200');
  });
});

describe('cloneEvent', () => {
  test('posts the new name to the copy route', async () => {
    respondWith({ data: { eventId: 10 } });

    const copy = await cloneEvent({ eventId: 9, name: 'Lunch & learn' });

    const req = lastRequest();
    expect(req.method).toBe('POST');
    expect(req.path).toBe('/json/schedule/copy/9');
    expect(req.body.get('name')).toBe('Lunch & learn');
    expect(copy).toEqual({ eventId: 10 });
  });
});
