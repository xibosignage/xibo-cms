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

import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { UserEvent } from '@testing-library/user-event';
import type * as ReactI18nextModule from 'react-i18next';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  buildDashboardPlaylist,
  buildSpotsResponse,
  buildSpotWidget,
  buildSpotWidgets,
  REGION_SPECIFIC_WIDGET,
} from './fixtures/playlistDashboard';
import {
  getSpot,
  openDeleteForSpot,
  renderPlaylistDashboardPage,
  selectPlaylist,
} from './helpers/renderPlaylistDashboardPage';
import {
  mockDeleteOk,
  mockDeletePending,
  mockPlaylists,
  mockSpots,
} from './mocks/playlistDashboardApi';

import { notify } from '@/components/ui/Notification';
import { deleteSpotWidget, fetchPlaylistSpots } from '@/services/dashboardApi';
import { testQueryClient } from '@/setupTests';
import { trackSequentialCalls } from '@/testUtils/sequentialMock';
import type { PlaylistSpotsResponse, SpotWidget } from '@/types/dashboard';

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
vi.mock('react-i18next', async (importOriginal) => {
  const actual = await importOriginal<typeof ReactI18nextModule>();
  const t = (key: string, opts?: Record<string, unknown>) =>
    key.replace(/{{(\w+)}}/g, (_, name: string) => String(opts?.[name] ?? ''));
  return { ...actual, useTranslation: () => ({ t, i18n: { language: 'en' } }) };
});

// =============================================================================
// Helpers local to this file
// =============================================================================

const LOBBY = buildDashboardPlaylist({ playlistId: 1, name: 'Lobby' });

const spotsWith = (widgets: SpotWidget[], extra: { isDynamic?: number } = {}) =>
  buildSpotsResponse({
    playlist: { playlistId: 1, name: 'Lobby', widgets, ...extra },
    spotsFound: widgets.length,
  });

// Renders, chooses Lobby, and waits for the first spot to show.
const openLobby = async (user: UserEvent, spots: PlaylistSpotsResponse) => {
  mockSpots(spots);
  renderPlaylistDashboardPage();
  await selectPlaylist(user, 'Lobby');
  await screen.findByText(/Spots Available/);
};

const confirmButton = (dialog: HTMLElement) =>
  within(dialog).getByRole('button', { name: /^(yes, remove|removing\.\.\.)$/i });

// Every widget id passed to deleteSpotWidget, in call order, with its deleteMedia flag.
const deleteCalls = () => vi.mocked(deleteSpotWidget).mock.calls;

// =============================================================================
// Tests
// =============================================================================

describe('Playlist Dashboard - removing one widget', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockPlaylists({ rows: [LOBBY], totalCount: 1 });
    mockDeleteOk();
  });

  test('Delete opens a "Remove Widget?" confirmation naming the widget', async () => {
    const user = userEvent.setup();
    await openLobby(user, spotsWith([buildSpotWidget({ name: 'Welcome' })]));

    const dialog = await openDeleteForSpot(user, 1);

    expect(within(dialog).getByRole('heading', { name: 'Remove Widget?' })).toBeInTheDocument();
    expect(dialog).toHaveTextContent('Welcome');
  });

  // Payload (rule 1.1): the right widget, and media deletion off by default.
  test('confirming removes that widget without deleting its library media', async () => {
    const user = userEvent.setup();
    const widgets = buildSpotWidgets(3);
    await openLobby(user, spotsWith(widgets));

    const dialog = await openDeleteForSpot(user, 2);
    await user.click(confirmButton(dialog));

    await waitFor(() => expect(deleteSpotWidget).toHaveBeenCalledTimes(1));
    expect(deleteSpotWidget).toHaveBeenCalledWith(widgets[1]!.widgetId, false);
  });

  test('for a library-linked widget, ticking the option also deletes the media', async () => {
    const user = userEvent.setup();
    const widget = buildSpotWidget({ widgetId: 9, regionSpecific: 0 });
    await openLobby(user, spotsWith([widget]));

    const dialog = await openDeleteForSpot(user, 1);
    await user.click(
      within(dialog).getByRole('checkbox', { name: 'Also delete from the Library?' }),
    );
    await user.click(confirmButton(dialog));

    await waitFor(() => expect(deleteSpotWidget).toHaveBeenCalledTimes(1));
    expect(deleteSpotWidget).toHaveBeenCalledWith(9, true);
  });

  test('a widget not linked to library media offers no library option', async () => {
    const user = userEvent.setup();
    await openLobby(user, spotsWith([buildSpotWidget({ regionSpecific: 1 })]));

    const dialog = await openDeleteForSpot(user, 1);

    expect(within(dialog).queryByRole('checkbox')).not.toBeInTheDocument();
  });

  test('the widget disappears at once, then a success message and a reload follow', async () => {
    const user = userEvent.setup();
    await openLobby(user, spotsWith([buildSpotWidget()]));
    const resolve = mockDeletePending();
    const loadsBefore = vi.mocked(fetchPlaylistSpots).mock.calls.length;

    const dialog = await openDeleteForSpot(user, 1);
    await user.click(confirmButton(dialog));

    // Gone while the request is still outstanding (optimistic removal).
    await waitFor(() => expect(screen.queryByText('test-widget.png')).not.toBeInTheDocument());
    expect(notify.success).not.toHaveBeenCalled();

    resolve();
    await waitFor(() => expect(notify.success).toHaveBeenCalledWith('Widget removed'));
    await waitFor(() =>
      expect(vi.mocked(fetchPlaylistSpots).mock.calls.length).toBeGreaterThan(loadsBefore),
    );
  });

  test('a failed removal shows an error and the reload brings the widget back', async () => {
    const user = userEvent.setup();
    await openLobby(user, spotsWith([buildSpotWidget()]));
    vi.mocked(deleteSpotWidget).mockRejectedValue(new Error('500'));

    const dialog = await openDeleteForSpot(user, 1);
    await user.click(confirmButton(dialog));

    await waitFor(() => expect(notify.error).toHaveBeenCalledWith('Failed to remove widget'));
    await screen.findByText('test-widget.png');
  });

  test('Cancel closes the confirmation and removes nothing', async () => {
    const user = userEvent.setup();
    await openLobby(user, spotsWith([buildSpotWidget()]));

    const dialog = await openDeleteForSpot(user, 1);
    await user.click(within(dialog).getByRole('button', { name: 'Cancel' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(deleteSpotWidget).not.toHaveBeenCalled();
  });
});

describe('Playlist Dashboard - Remove All', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockPlaylists({ rows: [LOBBY], totalCount: 1 });
  });

  // Rule 1.7: a bulk action must send all N ids. They must also go one at a time.
  test('confirming removes every widget, one after another, in order', async () => {
    const user = userEvent.setup();
    const widgets = buildSpotWidgets(3, { regionSpecific: 1 });
    await openLobby(user, spotsWith(widgets));
    const tracker = trackSequentialCalls(vi.mocked(deleteSpotWidget), undefined);

    await user.click(screen.getByRole('button', { name: 'Remove All' }));
    const dialog = await screen.findByRole('dialog');
    expect(
      within(dialog).getByRole('heading', { name: 'Remove All Widgets?' }),
    ).toBeInTheDocument();
    await user.click(confirmButton(dialog));

    await waitFor(() => expect(notify.success).toHaveBeenCalledWith('All widgets removed'));
    expect(deleteCalls()).toEqual(widgets.map((w) => [w.widgetId, false]));
    expect(tracker.maxInFlight).toBe(1);
  });

  // One library-linked widget is enough for the option to appear; the choice applies to all.
  test('with any library-linked widget, the media choice applies to every widget', async () => {
    const user = userEvent.setup();
    mockDeleteOk();
    const library = buildSpotWidget({ widgetId: 2, regionSpecific: 0 });
    await openLobby(user, spotsWith([REGION_SPECIFIC_WIDGET, library]));

    await user.click(screen.getByRole('button', { name: 'Remove All' }));
    const dialog = await screen.findByRole('dialog');
    await user.click(
      within(dialog).getByRole('checkbox', { name: 'Also delete from the Library?' }),
    );
    await user.click(confirmButton(dialog));

    await waitFor(() => expect(deleteSpotWidget).toHaveBeenCalledTimes(2));
    expect(deleteCalls().map(([, deleteMedia]) => deleteMedia)).toEqual([true, true]);
  });

  // Pins current behaviour: the loop stops at the first failure, leaving later spots in place.
  test('if one removal fails, the rest are not attempted and the list reloads', async () => {
    const user = userEvent.setup();
    const widgets = buildSpotWidgets(3, { regionSpecific: 1 });
    await openLobby(user, spotsWith(widgets));
    vi.mocked(deleteSpotWidget)
      .mockResolvedValueOnce(undefined)
      .mockRejectedValueOnce(new Error('500'))
      .mockResolvedValueOnce(undefined);
    const loadsBefore = vi.mocked(fetchPlaylistSpots).mock.calls.length;

    await user.click(screen.getByRole('button', { name: 'Remove All' }));
    await user.click(confirmButton(await screen.findByRole('dialog')));

    await waitFor(() => expect(notify.error).toHaveBeenCalledWith('Failed to remove all widgets'));
    expect(deleteCalls().map(([id]) => id)).toEqual([widgets[0]!.widgetId, widgets[1]!.widgetId]);
    await waitFor(() =>
      expect(vi.mocked(fetchPlaylistSpots).mock.calls.length).toBeGreaterThan(loadsBefore),
    );
  });

  test('while removing, the confirm button reads "Removing..." and is disabled', async () => {
    const user = userEvent.setup();
    await openLobby(user, spotsWith(buildSpotWidgets(2)));
    const resolve = mockDeletePending();

    await user.click(screen.getByRole('button', { name: 'Remove All' }));
    const dialog = await screen.findByRole('dialog');
    await user.click(confirmButton(dialog));

    const busy = await within(dialog).findByRole('button', { name: 'Removing...' });
    expect(busy).toBeDisabled();

    resolve();
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
  });
});

describe('Playlist Dashboard - dynamic playlists', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockPlaylists({ rows: [LOBBY], totalCount: 1 });
  });

  test('the confirm button is disabled and a message explains why', async () => {
    const user = userEvent.setup();
    await openLobby(user, spotsWith([buildSpotWidget()], { isDynamic: 1 }));

    const dialog = await openDeleteForSpot(user, 1);

    expect(confirmButton(dialog)).toBeDisabled();
    expect(dialog).toHaveTextContent('dynamically managed');
    await user.click(confirmButton(dialog));
    expect(deleteSpotWidget).not.toHaveBeenCalled();
  });

  // Pins current behaviour; product to confirm whether these should be hidden.
  test('Remove All and Delete are still offered', async () => {
    const user = userEvent.setup();
    await openLobby(user, spotsWith([buildSpotWidget()], { isDynamic: 1 }));

    expect(screen.getByRole('button', { name: 'Remove All' })).toBeInTheDocument();
    expect(within(getSpot(1)).getByRole('button', { name: 'Delete' })).toBeInTheDocument();
  });
});
