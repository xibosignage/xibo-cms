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
// Test type: Hook — report option hook requests and saved-choice names
// =============================================================================

import { renderHook, waitFor } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { useUserGroupOptions } from '../../LibraryUsage/hooks/useUserGroupOptions';
import { useCampaignOptions } from '../../shared/hooks/useCampaignOptions';
import { useDisplayGroupOptions } from '../../shared/hooks/useDisplayGroupOptions';
import { useDisplayGroupSelect } from '../../shared/hooks/useDisplayGroupSelect';
import { useDisplayOptions } from '../../shared/hooks/useDisplayOptions';
import { useLayoutOptions } from '../../shared/hooks/useLayoutOptions';
import { useMediaOptions } from '../../shared/hooks/useMediaOptions';
import { useUserOptions } from '../../shared/hooks/useUserOptions';
import { DISPLAY_GROUPS, DISPLAYS, EMPTY_PAGE, single, USERS } from '../fixtures/options';
import { setupOptionServiceMocks } from '../mocks/optionServices';

import { fetchCampaigns } from '@/services/campaignApi';
import { fetchDisplayGroups } from '@/services/displayGroupApi';
import { fetchDisplays } from '@/services/displaysApi';
import { fetchLayouts } from '@/services/layoutsApi';
import { fetchMedia } from '@/services/mediaApi';
import { fetchUsers } from '@/services/userApi';
import { fetchUserGroups } from '@/services/userGroupApi';

vi.mock('@/services/displaysApi', () => ({ fetchDisplays: vi.fn() }));
vi.mock('@/services/displayGroupApi', () => ({ fetchDisplayGroups: vi.fn() }));
vi.mock('@/services/layoutsApi', () => ({ fetchLayouts: vi.fn() }));
vi.mock('@/services/mediaApi', () => ({ fetchMedia: vi.fn() }));
vi.mock('@/services/campaignApi', () => ({ fetchCampaigns: vi.fn() }));
vi.mock('@/services/userApi', () => ({ fetchUsers: vi.fn() }));
vi.mock('@/services/userGroupApi', () => ({ fetchUserGroups: vi.fn() }));

describe('report option hooks - first request', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setupOptionServiceMocks();
  });

  test.each([
    ['displays', () => useDisplayOptions(), fetchDisplays, { start: 0, length: 20, display: '' }],
    [
      'display groups',
      () => useDisplayGroupSelect(),
      fetchDisplayGroups,
      { start: 0, length: 20, displayGroup: '' },
    ],
    ['users', () => useUserOptions(), fetchUsers, { start: 0, length: 20, userName: '' }],
    ['layouts', () => useLayoutOptions(), fetchLayouts, { start: 0, length: 20, layout: '' }],
    ['media', () => useMediaOptions(), fetchMedia, { start: 0, length: 20, media: '' }],
    ['campaigns', () => useCampaignOptions(), fetchCampaigns, { start: 0, length: 20, name: '' }],
  ])('the %s list asks for its first page by name', async (_name, useHook, service, params) => {
    const { result } = renderHook(useHook);

    await waitFor(() => expect(result.current.options.length).toBeGreaterThan(0));
    expect(service).toHaveBeenCalledWith(expect.objectContaining(params));
  });

  test('the Library Usage user and group lists ask 10 at a time, groups excluding per-user ones', async () => {
    const { result } = renderHook(() => useUserGroupOptions());

    await waitFor(() => expect(result.current.groups.options.length).toBeGreaterThan(0));
    expect(fetchUsers).toHaveBeenCalledWith(expect.objectContaining({ start: 0, length: 10 }));
    expect(fetchUserGroups).toHaveBeenCalledWith(
      expect.objectContaining({ start: 0, length: 10, isUser: 0 }),
    );
    expect(result.current.users.options[0]).toEqual({ label: 'alice', value: '1' });
    expect(result.current.groups.options[0]).toEqual({ label: 'Editors', value: '601' });
  });

  test('the options carry the id as value and the name as label', async () => {
    const { result } = renderHook(() => useDisplayOptions());

    await waitFor(() => expect(result.current.options.length).toBeGreaterThan(0));
    expect(result.current.options[0]).toEqual({ value: '1', label: 'Lobby Screen' });
  });

  // Known issue 13: one request for at most 200, no search or paging.
  test('the multi-select display group list asks once, for at most 200', async () => {
    const { result } = renderHook(() => useDisplayGroupOptions());

    await waitFor(() => expect(result.current.length).toBeGreaterThan(0));
    expect(fetchDisplayGroups).toHaveBeenCalledTimes(1);
    expect(fetchDisplayGroups).toHaveBeenCalledWith(
      expect.objectContaining({ start: 0, length: 200, displayGroup: '' }),
    );
  });
});

describe('report option hooks - restoring a saved choice', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setupOptionServiceMocks();
  });

  test('a saved display, display group or user gets its name back by id', async () => {
    vi.mocked(fetchDisplays).mockResolvedValue(single(DISPLAYS[1]!) as never);
    vi.mocked(fetchDisplayGroups).mockResolvedValue(single(DISPLAY_GROUPS[0]!) as never);
    vi.mocked(fetchUsers).mockResolvedValue(single(USERS[1]!) as never);
    const displays = renderHook(() => useDisplayOptions()).result;
    const groups = renderHook(() => useDisplayGroupSelect()).result;
    const users = renderHook(() => useUserOptions()).result;

    await expect(displays.current.resolveLabel('2')).resolves.toBe('Back Office');
    await expect(groups.current.resolveLabel('201')).resolves.toBe('Ground Floor');
    await expect(users.current.resolveLabel('2')).resolves.toBe('bob');
    expect(fetchDisplays).toHaveBeenCalledWith(
      expect.objectContaining({ displayId: 2, length: 1 }),
    );
    expect(fetchDisplayGroups).toHaveBeenCalledWith(
      expect.objectContaining({ displayGroupId: 201, length: 1 }),
    );
    expect(fetchUsers).toHaveBeenCalledWith(expect.objectContaining({ userId: 2, length: 1 }));
  });

  // The shared hooks fall back to blank; the Library Usage ones to the raw id.
  test('an unknown id falls back as each hook intends', async () => {
    vi.mocked(fetchDisplays).mockResolvedValue(EMPTY_PAGE as never);
    vi.mocked(fetchUsers).mockResolvedValue(EMPTY_PAGE as never);
    vi.mocked(fetchUserGroups).mockResolvedValue(EMPTY_PAGE as never);
    const displays = renderHook(() => useDisplayOptions()).result;
    const libraryUsage = renderHook(() => useUserGroupOptions()).result;

    await expect(displays.current.resolveLabel('9999')).resolves.toBe('');
    await expect(libraryUsage.current.users.resolveLabel('9999')).resolves.toBe('9999');
    await expect(libraryUsage.current.groups.resolveLabel('9999')).resolves.toBe('9999');
  });
});
