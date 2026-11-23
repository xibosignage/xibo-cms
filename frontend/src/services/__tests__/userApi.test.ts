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

import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';

import {
  assignUserGroups,
  deleteUser,
  fetchUserPreference,
  fetchUsers,
  saveUserPreference,
} from '@/services/userApi';
import {
  installHttpStub,
  lastRequest,
  respondWith,
  restoreHttpStub,
} from '@/testUtils/httpAdapter';

beforeEach(installHttpStub);
afterEach(() => {
  restoreHttpStub();
  vi.restoreAllMocks();
});

describe('fetchUserPreference', () => {
  test('asks for the one preference by name', async () => {
    respondWith({ data: { option: 'layout_page', value: '{}' } });

    await fetchUserPreference('layout_page');

    expect(lastRequest().method).toBe('GET');
    expect(lastRequest().path).toBe('/json/user/pref');
    expect(lastRequest().query.get('preference')).toBe('layout_page');
  });

  test('parses the stored JSON value', async () => {
    respondWith({ data: { option: 'layout_page', value: '{"viewMode":"grid"}' } });

    expect(await fetchUserPreference('layout_page')).toEqual({ viewMode: 'grid' });
  });

  // The two cases callers must tell apart: a folder deliberately set to "All
  // Items" is stored as null, while a page never visited has no value. Callers
  // relied on this from xibo-cms PR #3919.
  // https://github.com/xibosignage/xibo/issues/3936
  test('returns null for a preference stored as null', async () => {
    respondWith({ data: { option: 'globalSelectedFolderId', value: 'null' } });

    expect(await fetchUserPreference('globalSelectedFolderId')).toBeNull();
  });

  test('returns undefined when nothing is stored', async () => {
    respondWith({ data: {} });

    expect(await fetchUserPreference('globalSelectedFolderId')).toBeUndefined();
  });

  test('returns undefined for an empty response body', async () => {
    respondWith({ data: '' });

    expect(await fetchUserPreference('layout_page')).toBeUndefined();
  });

  test('returns undefined, and logs, when the stored value is not JSON', async () => {
    const error = vi.spyOn(console, 'error').mockImplementation(() => {});
    respondWith({ data: { option: 'layout_page', value: '{broken' } });

    expect(await fetchUserPreference('layout_page')).toBeUndefined();
    expect(error).toHaveBeenCalledOnce();
  });
});

describe('saveUserPreference', () => {
  test('posts the option and its JSON value as a form', async () => {
    await saveUserPreference({ option: 'layout_page', value: { viewMode: 'grid' } });

    const req = lastRequest();
    expect(req.method).toBe('POST');
    expect(req.path).toBe('/json/user/pref');
    expect(req.headers['Content-Type']).toBe('application/x-www-form-urlencoded');
    expect(req.body.get('preference[0][option]')).toBe('layout_page');
    expect(req.body.get('preference[0][value]')).toBe('{"viewMode":"grid"}');
  });

  test('sends null as the JSON text null, so it reads back as null', async () => {
    await saveUserPreference({ option: 'globalSelectedFolderId', value: null });

    expect(lastRequest().body.get('preference[0][value]')).toBe('null');
  });
});

describe('fetchUsers', () => {
  test('sends the paging and filter params, and not the abort signal', async () => {
    const controller = new AbortController();
    respondWith({ data: [], headers: { 'x-total-count': '0' } });

    await fetchUsers({ start: 0, length: 25, userName: 'ann', signal: controller.signal });

    const { path, query } = lastRequest();
    expect(path).toBe('/json/user');
    expect(query.get('start')).toBe('0');
    expect(query.get('length')).toBe('25');
    expect(query.get('userName')).toBe('ann');
    expect(query.has('signal')).toBe(false);
  });

  test('defaults to the first ten rows', async () => {
    respondWith({ data: [] });

    await fetchUsers();

    expect(lastRequest().query.get('start')).toBe('0');
    expect(lastRequest().query.get('length')).toBe('10');
  });

  test('returns the rows and the total from the x-total-count header', async () => {
    const rows = [{ userId: 1 }, { userId: 2 }];
    respondWith({ data: rows, headers: { 'x-total-count': '42' } });

    expect(await fetchUsers()).toEqual({ rows, totalCount: 42 });
  });

  test('reports a total of 0 when the header is missing', async () => {
    respondWith({ data: [] });

    expect((await fetchUsers()).totalCount).toBe(0);
  });
});

describe('deleteUser', () => {
  test('sends no options when none are given', async () => {
    await deleteUser(7);

    expect(lastRequest().method).toBe('DELETE');
    expect(lastRequest().url).toBe('/json/user/7');
  });

  test('sends deleteAllItems=0 and the reassignment target', async () => {
    await deleteUser(7, { deleteAllItems: 0, reassignUserId: 3 });

    const { query } = lastRequest();
    expect(query.get('deleteAllItems')).toBe('0');
    expect(query.get('reassignUserId')).toBe('3');
  });
});

describe('assignUserGroups', () => {
  test('sends a repeated key for each group added and each removed', async () => {
    await assignUserGroups(7, [1, 2], [9]);

    const req = lastRequest();
    expect(req.method).toBe('POST');
    expect(req.path).toBe('/json/user/7/usergroup/assign');
    expect(req.body.getAll('userGroupId[]')).toEqual(['1', '2']);
    expect(req.body.getAll('unassignUserGroupId[]')).toEqual(['9']);
  });

  test('sends an empty body when nothing changes', async () => {
    await assignUserGroups(7, [], []);

    expect(lastRequest().rawBody).toBe('');
  });
});
