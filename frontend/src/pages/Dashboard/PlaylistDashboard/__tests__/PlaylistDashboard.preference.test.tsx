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

import { act, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, afterEach, beforeEach, describe, test, expect } from 'vitest';

import { buildDashboardPlaylist, buildSpotsResponse, PREF_KEY } from './fixtures/playlistDashboard';
import {
  getPicker,
  renderPlaylistDashboardPage,
  selectPlaylist,
} from './helpers/renderPlaylistDashboardPage';
import {
  mockNoPref,
  mockPlaylists,
  mockPrefPending,
  mockSavedPref,
} from './mocks/playlistDashboardApi';

import { fetchPlaylistSpots } from '@/services/dashboardApi';
import { saveUserPreference } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/dashboardApi', () => ({
  fetchPlaylistsForDashboard: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  fetchPlaylistSpots: vi.fn(),
  uploadSpotMedia: vi.fn(),
  deleteSpotWidget: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(undefined),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/pages/Library/Media/components/MediaPreviewer', () => ({
  default: () => null,
}));
vi.mock('@/components/ui/forms/SelectDropdown', () => ({
  default: ({
    label,
    value,
    options,
    onSelect,
  }: {
    label?: string;
    value: string;
    options: { value: string; label: string }[];
    onSelect: (v: string) => void;
  }) => (
    <select aria-label={label} value={value} onChange={(e) => onSelect(e.target.value)}>
      <option value="">—</option>
      {options.map((o) => (
        <option key={o.value} value={o.value}>
          {o.label}
        </option>
      ))}
    </select>
  ),
}));

// =============================================================================
// Helpers local to this file
// =============================================================================

const LOBBY = buildDashboardPlaylist({ playlistId: 1, name: 'Lobby' });
const RECEPTION = buildDashboardPlaylist({ playlistId: 2, name: 'Reception' });

// The selection is saved 500 ms after it last changed.
const SAVE_DELAY = 500;

// Lets React run any pending effects first (the save timer is set in an effect, which can
// still be pending when waitFor sees the new DOM), then moves the fake clock on.
const advance = async (ms: number) => {
  await act(async () => {});
  await act(async () => {
    vi.advanceTimersByTime(ms);
  });
};

const savedValues = () => vi.mocked(saveUserPreference).mock.calls.map(([arg]) => arg);

// =============================================================================
// Tests
// =============================================================================

describe('Playlist Dashboard - remembering the chosen playlist', () => {
  beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    testQueryClient.clear();
    vi.clearAllMocks();
    mockPlaylists({ rows: [LOBBY, RECEPTION], totalCount: 2 });
    vi.mocked(fetchPlaylistSpots).mockImplementation(async (id: number) =>
      buildSpotsResponse({ playlist: { playlistId: id }, spotsFound: 1 }),
    );
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  const setupUser = () => userEvent.setup({ advanceTimers: vi.advanceTimersByTime });

  test('a previously chosen playlist is selected on load and its spots are loaded', async () => {
    mockSavedPref(2);
    renderPlaylistDashboardPage();

    await waitFor(() => expect(getPicker()).toHaveValue('2'));
    await waitFor(() => expect(fetchPlaylistSpots).toHaveBeenCalled());
    expect(vi.mocked(fetchPlaylistSpots).mock.calls[0]![0]).toBe(2);
  });

  test('with nothing saved, no playlist is selected and nothing is saved', async () => {
    mockNoPref();
    renderPlaylistDashboardPage();
    await screen.findByRole('option', { name: 'Lobby' });

    await advance(1000);

    expect(getPicker()).toHaveValue('');
    expect(saveUserPreference).not.toHaveBeenCalled();
  });

  // Payload (rule 1.1) and timing: nothing straight away, one save after the delay.
  test('choosing a playlist saves it, but only once the delay has passed', async () => {
    const user = setupUser();
    mockNoPref();
    renderPlaylistDashboardPage();

    await selectPlaylist(user, 'Lobby');
    expect(saveUserPreference).not.toHaveBeenCalled();

    await advance(SAVE_DELAY);

    expect(saveUserPreference).toHaveBeenCalledTimes(1);
    expect(saveUserPreference).toHaveBeenCalledWith({
      option: PREF_KEY,
      value: { selectedPlaylistId: 1 },
    });
  });

  test('three quick changes save only once, with the last choice', async () => {
    const user = setupUser();
    mockNoPref();
    renderPlaylistDashboardPage();

    await selectPlaylist(user, 'Lobby');
    await selectPlaylist(user, 'Reception');
    await selectPlaylist(user, 'Lobby');
    await advance(SAVE_DELAY);

    expect(savedValues()).toEqual([{ option: PREF_KEY, value: { selectedPlaylistId: 1 } }]);
  });

  test('clearing the picker saves "no playlist"', async () => {
    const user = setupUser();
    mockSavedPref(1);
    renderPlaylistDashboardPage();
    await waitFor(() => expect(getPicker()).toHaveValue('1'));
    await advance(SAVE_DELAY);

    await user.selectOptions(getPicker(), '');
    await advance(SAVE_DELAY);

    const values = savedValues();
    expect(values[values.length - 1]).toEqual({
      option: PREF_KEY,
      value: { selectedPlaylistId: null },
    });
  });

  // Low priority. Pins K2: restoring the saved playlist triggers a redundant save of the
  // same value. If that is fixed, flip this to expect no save.
  test('opening the page re-saves the remembered playlist once', async () => {
    mockSavedPref(1);
    renderPlaylistDashboardPage();
    await waitFor(() => expect(getPicker()).toHaveValue('1'));

    await advance(SAVE_DELAY);

    expect(savedValues()).toEqual([{ option: PREF_KEY, value: { selectedPlaylistId: 1 } }]);
  });

  // Known-open (K1): until the saved preference arrives, a choice isn't saved, and when it
  // arrives it replaces what the user picked. Flip to `test` when fixed.
  test.fails('a playlist chosen before the saved preference loads is kept', async () => {
    const user = setupUser();
    const resolvePref = mockPrefPending();
    renderPlaylistDashboardPage();

    await selectPlaylist(user, 'Reception');
    await act(async () => resolvePref({ selectedPlaylistId: 1 }));
    await advance(SAVE_DELAY);

    expect(getPicker()).toHaveValue('2');
  });
});
