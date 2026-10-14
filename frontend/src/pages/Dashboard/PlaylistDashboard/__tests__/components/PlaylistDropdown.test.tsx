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

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { UserEvent } from '@testing-library/user-event';
import { vi, afterEach, beforeEach, describe, test, expect } from 'vitest';

import PlaylistDropdown from '../../components/PlaylistDropdown';
import { buildDashboardPlaylist } from '../fixtures/playlistDashboard';
import { installIntersectionObserverStub } from '../helpers/intersectionObserverStub';

import type { FetchPlaylistsForDashboardResponse } from '@/services/dashboardApi';
import { fetchPlaylistsForDashboard } from '@/services/dashboardApi';

// =============================================================================
// Module mocks
// =============================================================================

// No SelectDropdown mock on purpose: four dropdown paging faults hid behind native <select>
// stubs (standards §6), so paging and search run against the real component here.
vi.mock('@/services/dashboardApi', () => ({
  fetchPlaylistsForDashboard: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));

// =============================================================================
// Helpers local to this file
// =============================================================================

// `count` playlists starting at id `from`, named "Playlist <id>", with the given total.
const page = (
  from: number,
  count: number,
  totalCount: number,
): FetchPlaylistsForDashboardResponse => ({
  rows: Array.from({ length: count }, (_, i) =>
    buildDashboardPlaylist({ playlistId: from + i, name: `Playlist ${from + i}` }),
  ),
  totalCount,
});

const requests = () => vi.mocked(fetchPlaylistsForDashboard).mock.calls.map(([params]) => params);

const renderDropdown = (value: number | null = null) => {
  const onSelect = vi.fn();
  render(<PlaylistDropdown value={value} onSelect={onSelect} />);
  return { onSelect };
};

const openDropdown = async (user: UserEvent) => {
  await user.click(screen.getByRole('combobox', { name: 'Playlist' }));
  return screen.findByRole('listbox');
};

// =============================================================================
// Tests
// =============================================================================

describe('PlaylistDropdown', () => {
  let observer: ReturnType<typeof installIntersectionObserverStub>;

  beforeEach(() => {
    vi.clearAllMocks();
    observer = installIntersectionObserverStub();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  test('is labelled "Playlist" and loads the first ten playlists', async () => {
    vi.mocked(fetchPlaylistsForDashboard).mockResolvedValue(page(1, 3, 3));
    renderDropdown();

    expect(screen.getByRole('combobox', { name: 'Playlist' })).toBeInTheDocument();
    await waitFor(() => expect(fetchPlaylistsForDashboard).toHaveBeenCalledTimes(1));
    expect(requests()[0]).toEqual({ start: 0, length: 10, name: undefined });
  });

  test('each option shows the playlist’s name', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchPlaylistsForDashboard).mockResolvedValue({
      rows: [
        buildDashboardPlaylist({ playlistId: 1, name: 'Lobby' }),
        buildDashboardPlaylist({ playlistId: 2, name: 'Reception' }),
      ],
      totalCount: 2,
    });
    renderDropdown();

    await openDropdown(user);

    await screen.findByRole('option', { name: 'Lobby' });
    expect(screen.getByRole('option', { name: 'Reception' })).toBeInTheDocument();
  });

  test('choosing an option selects that playlist by its number', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchPlaylistsForDashboard).mockResolvedValue(page(1, 2, 2));
    const { onSelect } = renderDropdown();

    await openDropdown(user);
    await user.click(await screen.findByRole('option', { name: 'Playlist 2' }));

    expect(onSelect).toHaveBeenCalledWith(2);
  });

  // The clear entry is labelled with the placeholder text.
  test('clearing the picker removes the selection', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchPlaylistsForDashboard).mockResolvedValue(page(1, 2, 2));
    const { onSelect } = renderDropdown(1);

    await openDropdown(user);
    await user.click(await screen.findByRole('button', { name: 'Select' }));

    expect(onSelect).toHaveBeenCalledWith(null);
  });

  // The search box has no accessible name (only a placeholder), but it is the only text box
  // in the open dropdown.
  test('searching waits for a short pause, then asks by name from the first page', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchPlaylistsForDashboard).mockResolvedValue(page(1, 10, 25));
    renderDropdown();
    await waitFor(() => expect(fetchPlaylistsForDashboard).toHaveBeenCalledTimes(1));

    await openDropdown(user);
    await user.type(screen.getByRole('textbox'), 'lob');

    // Not straight away: the request waits for typing to pause.
    expect(requests().some((r) => r.name === 'lob')).toBe(false);
    await waitFor(() =>
      expect(requests()[requests().length - 1]).toEqual({ start: 0, length: 10, name: 'lob' }),
    );
    // One request for the finished word, not one per keystroke.
    expect(requests().filter((r) => r.name)).toHaveLength(1);
  });

  // Multi-page (rule 1.3): 25 playlists, 10 per page.
  test('scrolling to the end loads the next ten and adds them below', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchPlaylistsForDashboard)
      .mockResolvedValueOnce(page(1, 10, 25))
      .mockResolvedValueOnce(page(11, 10, 25));
    renderDropdown();
    await openDropdown(user);
    await screen.findByRole('option', { name: 'Playlist 10' });

    await observer.scrollToEnd();

    await screen.findByRole('option', { name: 'Playlist 20' });
    expect(screen.getAllByRole('option')).toHaveLength(20);
    expect(requests()[1]).toEqual({ start: 10, length: 10, name: undefined });
  });

  test('scrolling to the end again loads the page after that', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchPlaylistsForDashboard)
      .mockResolvedValueOnce(page(1, 10, 25))
      .mockResolvedValueOnce(page(11, 10, 25))
      .mockResolvedValueOnce(page(21, 5, 25));
    renderDropdown();
    await openDropdown(user);
    await screen.findByRole('option', { name: 'Playlist 10' });

    await observer.scrollToEnd();
    await screen.findByRole('option', { name: 'Playlist 20' });
    await observer.scrollToEnd();

    await screen.findByRole('option', { name: 'Playlist 25' });
    expect(screen.getAllByRole('option')).toHaveLength(25);
    expect(requests().map((r) => r.start)).toEqual([0, 10, 20]);
  });

  test('loading more after a search keeps searching for the same name', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchPlaylistsForDashboard)
      .mockResolvedValueOnce(page(1, 10, 25))
      .mockResolvedValueOnce(page(101, 10, 15))
      .mockResolvedValueOnce(page(111, 5, 15));
    renderDropdown();
    await openDropdown(user);
    await screen.findByRole('option', { name: 'Playlist 10' });

    await user.type(screen.getByRole('textbox'), 'lob');
    await screen.findByRole('option', { name: 'Playlist 101' });
    await observer.scrollToEnd();

    await screen.findByRole('option', { name: 'Playlist 115' });
    expect(requests()[requests().length - 1]).toEqual({ start: 10, length: 10, name: 'lob' });
  });

  test('once every playlist is loaded, scrolling to the end loads nothing more', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchPlaylistsForDashboard).mockResolvedValue(page(1, 3, 3));
    renderDropdown();
    await openDropdown(user);
    await screen.findByRole('option', { name: 'Playlist 3' });

    await observer.scrollToEnd();

    expect(fetchPlaylistsForDashboard).toHaveBeenCalledTimes(1);
  });

  test('if the first page cannot be loaded, there are no options and nothing breaks', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchPlaylistsForDashboard).mockRejectedValue(new Error('500'));
    renderDropdown();
    await waitFor(() => expect(fetchPlaylistsForDashboard).toHaveBeenCalled());

    await openDropdown(user);

    await screen.findByText('No results');
    expect(screen.queryByRole('option')).not.toBeInTheDocument();
  });

  test('an already-selected playlist shows its name', async () => {
    vi.mocked(fetchPlaylistsForDashboard).mockResolvedValue({
      rows: [buildDashboardPlaylist({ playlistId: 2, name: 'Reception' })],
      totalCount: 1,
    });
    renderDropdown(2);

    await screen.findByText('Reception', { selector: '[role="combobox"] span' });
  });
});
