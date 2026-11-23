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
  fetchGroupFolderPermissions,
  fetchMultiPermissions,
  fetchPermissions,
  saveMultiPermissions,
  savePermissions,
} from '@/services/permissionsApi';
import {
  installHttpStub,
  lastRequest,
  respondWith,
  restoreHttpStub,
} from '@/testUtils/httpAdapter';
import { knownFailure } from '@/testUtils/knownFailure';

beforeEach(installHttpStub);
afterEach(restoreHttpStub);

const perm = (view: number, edit: number, del: number) => ({
  permissionId: 1,
  view,
  edit,
  delete: del,
});

describe('fetchPermissions', () => {
  test('asks for the entity and id, from row 0, ten rows by default', async () => {
    respondWith({ data: [] });

    await fetchPermissions({ entity: 'Layout', id: 12 });

    const { method, path, query } = lastRequest();
    expect(method).toBe('GET');
    expect(path).toBe('/json/user/permissions/Layout/12');
    expect(query.get('start')).toBe('0');
    expect(query.get('length')).toBe('10');
  });

  test('sends start=0 when asked for the first page, not nothing', async () => {
    respondWith({ data: [] });

    await fetchPermissions({ entity: 'Layout', id: 12, start: 0, length: 50 });

    expect(lastRequest().query.get('start')).toBe('0');
    expect(lastRequest().query.get('length')).toBe('50');
  });

  test.each([
    ['user', '1'],
    ['group', '0'],
  ] as const)('the %s filter sends isUserSpecific=%s', async (type, expected) => {
    respondWith({ data: [] });

    await fetchPermissions({ entity: 'Layout', id: 12, type });

    expect(lastRequest().query.get('isUserSpecific')).toBe(expected);
  });

  test('the "all" filter sends no isUserSpecific', async () => {
    respondWith({ data: [] });

    await fetchPermissions({ entity: 'Layout', id: 12, type: 'all' });

    expect(lastRequest().query.has('isUserSpecific')).toBe(false);
  });

  test('sends the name filter and the sort only when given', async () => {
    respondWith({ data: [] }, { data: [] });

    await fetchPermissions({ entity: 'Layout', id: 12 });
    expect([...lastRequest().query.keys()].sort()).toEqual(['length', 'start']);

    await fetchPermissions({
      entity: 'Layout',
      id: 12,
      name: 'ed',
      sortBy: 'group',
      sortDir: 'desc',
    });
    const { query } = lastRequest();
    expect(query.get('name')).toBe('ed');
    expect(query.get('sortBy')).toBe('group');
    expect(query.get('sortDir')).toBe('desc');
  });

  test('returns the rows and the total from x-total-count', async () => {
    const rows = [{ groupId: 1 }, { groupId: 2 }];
    respondWith({ data: rows, headers: { 'x-total-count': '31' } });

    expect(await fetchPermissions({ entity: 'Layout', id: 12 })).toEqual({ rows, totalCount: 31 });
  });

  test('reports a total of 0 when the header is missing', async () => {
    respondWith({ data: [] });

    expect((await fetchPermissions({ entity: 'Layout', id: 12 })).totalCount).toBe(0);
  });
});

describe('fetchMultiPermissions', () => {
  test('asks for all the ids at once, comma-joined', async () => {
    respondWith({ data: {} });

    await fetchMultiPermissions({ entity: 'Campaign', ids: [3, 4, 5] });

    const { path, query } = lastRequest();
    expect(path).toBe('/json/user/permissions/Campaign');
    expect(query.get('ids')).toBe('3,4,5');
  });

  test('returns no rows for an empty response', async () => {
    respondWith({ data: {} });

    expect((await fetchMultiPermissions({ entity: 'Campaign', ids: [3] })).rows).toEqual([]);
  });

  // Open. Bulk Share's "Filter by" User / Group passes `type` here, but it is
  // never sent, so the list doesn't change (single-item Share sends it). The
  // server's multi-item route ignores isUserSpecific as well, so a fix needs both.
  // https://github.com/xibosignage/xibo/issues/4079
  knownFailure(
    'the User filter sends isUserSpecific=1, as single-item Share does',
    async () => {
      respondWith({ data: {} });
      await fetchMultiPermissions({ entity: 'Campaign', ids: [3, 4], type: 'user' });
      return lastRequest().query;
    },
    (query) => expect(query.get('isUserSpecific')).toBe('1'),
  );

  // Open, same cause. The flattened rows drop isUserSpecific, so bulk Share
  // labels every user "Group". (The server's multi-item route doesn't return
  // it either yet.)
  // https://github.com/xibosignage/xibo/issues/4079
  knownFailure(
    'a user in the response is still marked as a user',
    async () => {
      respondWith({
        data: {
          9: {
            groupId: 9,
            group: 'ann',
            isUser: null,
            isUserSpecific: 1,
            entity: 'Campaign',
            permissions: { 3: perm(0, 0, 0) },
          },
        },
      });
      return (await fetchMultiPermissions({ entity: 'Campaign', ids: [3] })).rows[0]!;
    },
    (row) => expect(row.isUserSpecific).toBe(1),
  );

  test('gives one row per group, mixed where the items disagree', async () => {
    respondWith({
      data: {
        7: {
          groupId: 7,
          group: 'Editors',
          isUser: 0,
          entity: 'Campaign',
          permissions: { 3: perm(1, 1, 0), 4: perm(1, 0, 0) },
        },
      },
      headers: { 'x-total-count': '1' },
    });

    const { rows, totalCount } = await fetchMultiPermissions({ entity: 'Campaign', ids: [3, 4] });

    expect(totalCount).toBe(1);
    expect(rows).toHaveLength(1);
    expect(rows[0]).toMatchObject({ groupId: 7, group: 'Editors', view: 1, edit: -1, delete: 0 });
  });

  test('a group with no permissions on any item is all 0', async () => {
    respondWith({
      data: { 8: { groupId: 8, group: 'Viewers', isUser: 0, entity: 'Campaign', permissions: {} } },
    });

    const { rows } = await fetchMultiPermissions({ entity: 'Campaign', ids: [3] });

    expect(rows[0]).toMatchObject({ view: 0, edit: 0, delete: 0 });
  });
});

describe('savePermissions', () => {
  test('posts each group setting as a form, with the owner', async () => {
    await savePermissions({
      entity: 'Layout',
      id: 12,
      ownerId: 1,
      groupIds: { 7: { view: 1, edit: 0, delete: 0 } },
    });

    const req = lastRequest();
    expect(req.method).toBe('POST');
    expect(req.path).toBe('/json/user/permissions/Layout/12');
    expect(req.body.get('ownerId')).toBe('1');
    expect(req.body.get('groupIds[7][view]')).toBe('1');
    expect(req.body.get('groupIds[7][edit]')).toBe('0');
    expect(req.body.get('groupIds[7][delete]')).toBe('0');
    expect(req.body.has('ids')).toBe(false);
  });

  test('leaves out a mixed (-1) setting so the items keep their own', async () => {
    await savePermissions({
      entity: 'Layout',
      id: 12,
      groupIds: { 7: { view: -1, edit: 1, delete: -1 } },
    });

    const { body } = lastRequest();
    expect(body.has('groupIds[7][view]')).toBe(false);
    expect(body.get('groupIds[7][edit]')).toBe('1');
    expect(body.has('groupIds[7][delete]')).toBe(false);
    expect(body.has('ownerId')).toBe(false);
  });
});

describe('saveMultiPermissions', () => {
  test('posts to the entity, with the ids comma-joined', async () => {
    await saveMultiPermissions({
      entity: 'Campaign',
      ids: [3, 4],
      groupIds: { 7: { view: 1, edit: 1, delete: 1 } },
    });

    const req = lastRequest();
    expect(req.path).toBe('/json/user/permissions/Campaign');
    expect(req.body.get('ids')).toBe('3,4');
    expect(req.body.get('groupIds[7][delete]')).toBe('1');
  });
});

describe('fetchGroupFolderPermissions', () => {
  // This used to send start=0&length=<number of folders>, so the server paged
  // the result and a user's own group could be left out of it. Fixed by
  // xibo-cms PR #3918.
  // https://github.com/xibosignage/xibo/issues/3938
  // https://github.com/xibosignage/xibo-cms/pull/3918
  test('asks for every folder with paging turned off', async () => {
    respondWith({ data: {} });

    await fetchGroupFolderPermissions([1, 2, 3], 7);

    const { path, query } = lastRequest();
    expect(path).toBe('/json/user/permissions/Folder');
    expect(query.get('ids')).toBe('1,2,3');
    expect(query.get('disablePaging')).toBe('1');
    expect(query.get('isUserSpecific')).toBe('1');
    expect(query.has('start')).toBe(false);
    expect(query.has('length')).toBe(false);
  });

  test('returns only the folders the group can do something with', async () => {
    respondWith({
      data: {
        a: {
          groupId: 6,
          group: 'Other',
          isUser: 1,
          entity: 'Folder',
          permissions: { 1: perm(1, 1, 1) },
        },
        b: {
          groupId: 7,
          group: 'Ann',
          isUser: 1,
          entity: 'Folder',
          permissions: { 1: perm(1, 0, 0), 2: perm(0, 0, 0), 3: perm(0, 1, 0) },
        },
      },
    });

    const result = await fetchGroupFolderPermissions([1, 2, 3], 7);

    expect([...result.keys()]).toEqual([1, 3]);
    expect(result.get(3)).toEqual({ view: 0, edit: 1, delete: 0 });
  });

  test('returns an empty map when the group is not in the response', async () => {
    respondWith({ data: {} });

    expect((await fetchGroupFolderPermissions([1], 7)).size).toBe(0);
  });
});
