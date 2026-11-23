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

import { afterEach, beforeEach, describe, expect, test } from 'vitest';

import {
  addDisplayViaCode,
  assignDisplayGroups,
  assignLayouts,
  assignMedia,
  fetchDisplays,
  fetchDisplaysMap,
  setBandwidthLimitMultiple,
  updateDisplay,
} from '@/services/displaysApi';
import {
  installHttpStub,
  lastRequest,
  respondWith,
  restoreHttpStub,
} from '@/testUtils/httpAdapter';

beforeEach(installHttpStub);
afterEach(restoreHttpStub);

describe('fetchDisplays', () => {
  test('defaults to the first ten displays', async () => {
    respondWith({ data: [] });

    await fetchDisplays();

    const { method, path, query } = lastRequest();
    expect(method).toBe('GET');
    expect(path).toBe('/json/display');
    expect(query.get('start')).toBe('0');
    expect(query.get('length')).toBe('10');
  });

  test('returns the rows and the total from x-total-count, or 0 without it', async () => {
    const rows = [{ displayId: 1 }, { displayId: 2 }];
    respondWith({ data: rows, headers: { 'x-total-count': '2' } }, { data: [] });

    expect(await fetchDisplays()).toEqual({ rows, totalCount: 2 });
    expect((await fetchDisplays()).totalCount).toBe(0);
  });
});

describe('updateDisplay', () => {
  test('puts the fields as a form to the display', async () => {
    await updateDisplay(4, { display: 'Lobby', licensed: 1, incSchedule: 0 });

    const req = lastRequest();
    expect(req.method).toBe('PUT');
    expect(req.path).toBe('/json/display/4');
    expect(req.body.get('display')).toBe('Lobby');
    expect(req.body.get('licensed')).toBe('1');
    expect(req.body.get('incSchedule')).toBe('0');
  });

  // The server reads a missing field as empty, so leaving one out is how it is cleared.
  test('leaves out empty and null fields', async () => {
    await updateDisplay(4, {
      display: 'Lobby',
      description: '',
      latitude: null,
      venueId: undefined,
    });

    expect([...lastRequest().body.keys()]).toEqual(['display']);
  });

  test('sends each language as a repeated languages[] key', async () => {
    await updateDisplay(4, { display: 'Lobby', languages: ['en', 'cy'] });

    expect(lastRequest().body.getAll('languages[]')).toEqual(['en', 'cy']);
  });

  test('sends picture options as indexed pictureControls', async () => {
    await updateDisplay(4, {
      display: 'Lobby',
      overrideValues: { pictureOptions: '{"brightness":50,"contrast":70}' },
    });

    const { body } = lastRequest();
    expect(body.get('pictureControls[0][property]')).toBe('brightness');
    expect(body.get('pictureControls[0][value]')).toBe('50');
    expect(body.get('pictureControls[1][property]')).toBe('contrast');
    expect(body.has('pictureOptions')).toBe(false);
  });

  test('sends one empty picture control when all options are removed, so they are cleared', async () => {
    await updateDisplay(4, { display: 'Lobby', overrideValues: { pictureOptions: '{}' } });

    const { body } = lastRequest();
    expect(body.get('pictureControls[0][property]')).toBe('');
    expect(body.get('pictureControls[0][value]')).toBe('0');
  });

  test('sends on/off timers per day', async () => {
    await updateDisplay(4, {
      display: 'Lobby',
      overrideValues: { timers: '{"monday":{"on":"08:00","off":"18:00"}}' },
    });

    const { body } = lastRequest();
    expect(body.get('timers[0][day]')).toBe('monday');
    expect(body.get('timers[0][on]')).toBe('08:00');
    expect(body.get('timers[0][off]')).toBe('18:00');
  });

  test('sends the lock options as true, false or empty', async () => {
    await updateDisplay(4, {
      display: 'Lobby',
      overrideValues: {
        lockOptions: '{"usblock":true,"osdlock":null,"keylock":{"local":"allowall"}}',
      },
    });

    const { body } = lastRequest();
    expect(body.get('usblock')).toBe('true');
    expect(body.get('osdlock')).toBe('empty');
    expect(body.get('keylockLocal')).toBe('allowall');
    expect(body.has('keylockRemote')).toBe(false);
  });

  test('sends any other override as it is', async () => {
    await updateDisplay(4, { display: 'Lobby', overrideValues: { collectInterval: '300' } });

    expect(lastRequest().body.get('collectInterval')).toBe('300');
  });
});

describe('addDisplayViaCode', () => {
  test('sends just the code when given a string', async () => {
    await addDisplayViaCode('ABC123');

    const req = lastRequest();
    expect(req.method).toBe('POST');
    expect(req.path).toBe('/json/display/addViaCode');
    expect([...req.body.entries()]).toEqual([['user_code', 'ABC123']]);
  });

  test('sends the name, folder, group and authorised flag when given', async () => {
    await addDisplayViaCode({
      userCode: 'ABC123',
      displayName: 'Lobby',
      folderId: 3,
      displayGroupId: 8,
      authorised: true,
    });

    const { body } = lastRequest();
    expect(body.get('displayName')).toBe('Lobby');
    expect(body.get('folderId')).toBe('3');
    expect(body.get('displayGroupId')).toBe('8');
    expect(body.get('authorised')).toBe('1');
  });

  test('sends authorised=0 when it is turned off', async () => {
    await addDisplayViaCode({ userCode: 'ABC123', authorised: false });

    expect(lastRequest().body.get('authorised')).toBe('0');
  });
});

describe('setBandwidthLimitMultiple', () => {
  test('sends the ids comma-joined and the limit in kb', async () => {
    await setBandwidthLimitMultiple([1, 2], 2048);

    const req = lastRequest();
    expect(req.method).toBe('PUT');
    expect(req.path).toBe('/json/display/setBandwidthLimit/multi');
    expect(req.body.get('ids')).toBe('1,2');
    expect(req.body.get('bandwidthLimit')).toBe('2048');
    expect(req.body.get('bandwidthLimitUnits')).toBe('kb');
  });

  test('sends a limit of 0, which removes the limit', async () => {
    await setBandwidthLimitMultiple([1], 0);

    expect(lastRequest().body.get('bandwidthLimit')).toBe('0');
  });
});

describe.each([
  [
    'assignMedia',
    assignMedia,
    '/json/displaygroup/5/media/assign',
    'mediaId[]',
    'unassignMediaId[]',
  ],
  [
    'assignLayouts',
    assignLayouts,
    '/json/displaygroup/5/layout/assign',
    'layoutId[]',
    'unassignLayoutId[]',
  ],
  [
    'assignDisplayGroups',
    assignDisplayGroups,
    '/json/display/5/displaygroup/assign',
    'displayGroupId[]',
    'unassignDisplayGroupId[]',
  ],
] as const)('%s', (_name, assign, path, addKey, removeKey) => {
  test('sends a repeated key for each item added and each removed', async () => {
    await assign(5, [1, 2], [9]);

    const req = lastRequest();
    expect(req.method).toBe('POST');
    expect(req.path).toBe(path);
    expect(req.body.getAll(addKey)).toEqual(['1', '2']);
    expect(req.body.getAll(removeKey)).toEqual(['9']);
  });

  test('sends no removals when none are given', async () => {
    await assign(5, [1]);

    expect(lastRequest().body.has(removeKey)).toBe(false);
  });
});

describe('fetchDisplaysMap', () => {
  // A web route, not /json: it goes through the bare axios export. At a root
  // install; the /cms/ sub-folder case is in installRoot.test.ts.
  test('asks the web route, not the JSON API, with the filters', async () => {
    respondWith({ data: { type: 'FeatureCollection', features: [] } });

    const map = await fetchDisplaysMap({ displayGroupId: 3 });

    const { path, query } = lastRequest();
    expect(path).toBe('/display/map');
    expect(query.get('displayGroupId')).toBe('3');
    expect(map).toEqual({ type: 'FeatureCollection', features: [] });
  });
});
