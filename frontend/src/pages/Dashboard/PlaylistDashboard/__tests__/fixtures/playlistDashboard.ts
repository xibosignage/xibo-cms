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

import type { FetchPlaylistsForDashboardResponse } from '@/services/dashboardApi';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { PlaylistSpotsResponse, SpotWidget } from '@/types/dashboard';
import type { Playlist } from '@/types/playlist';
import type { User } from '@/types/user';

// -----------------------------------------------------------------------------
// Factory that produces a SpotWidget (one filled spot) with safe minimal defaults.
// Only fields used in assertions or required for rendering carry meaningful
// values; everything else is the zero value for its type.
//
// widgetId: 1 — asserted in delete payloads (deleteSpotWidget) and upload
//   payloads (uploadSpotMedia widgetId)
// type: 'image' — drives the thumbnail branch and the file picker's accept list
// playlistId: 1 — matches mockPlaylist so the widget belongs to the selected playlist
// name: 'Test Widget' — shown as the badge and in the "Remove Widget?" dialog
// viewable / editable / deletable: true — without these the spot renders as
//   Locked with no Select File / Delete / preview, so a plain spot needs them on
// mediaIds: [101] — sent as oldMediaId on replace; used for the thumbnail URL and preview
// mediaFiles[0].fileName / fileSize — the file name and size shown in the row
// regionSpecific: 0 — zero value, and it also means "linked to library media"
//   (shows the thumbnail and the "Also delete from the Library?" option)
// -----------------------------------------------------------------------------
export const buildSpotWidget = (overrides: Partial<SpotWidget> = {}): SpotWidget => ({
  widgetId: 1,
  type: 'image',
  playlistId: 1,
  name: 'Test Widget',
  regionSpecific: 0,
  moduleIcon: '',
  viewable: true,
  editable: true,
  deletable: true,
  mediaIds: [101],
  mediaFiles: [
    { mediaId: 101, fileName: 'test-widget.png', mediaType: 'image', fileSize: '12 KiB' },
  ],
  ...overrides,
});

// N widgets with unique ids, media ids and file names, for multi-spot tests
// (Remove All ordering, upload index checks). Ids: 1..n, media ids: 101..100+n.
export const buildSpotWidgets = (
  count: number,
  overrides: Partial<SpotWidget> = {},
): SpotWidget[] =>
  Array.from({ length: count }, (_, i) =>
    buildSpotWidget({
      widgetId: i + 1,
      name: `Test Widget ${i + 1}`,
      mediaIds: [101 + i],
      mediaFiles: [
        {
          mediaId: 101 + i,
          fileName: `test-widget-${i + 1}.png`,
          mediaType: 'image',
          fileSize: '12 KiB',
        },
      ],
      ...overrides,
    }),
  );

// Named SpotRow states (see the permission matrix in Dashboard/DASHBOARD_TESTS.md).
export const SUBPLAYLIST_WIDGET = buildSpotWidget({
  type: 'subplaylist',
  name: 'Nested Playlist',
  mediaIds: [],
  mediaFiles: [],
});
export const LOCKED_WIDGET = buildSpotWidget({ editable: false, deletable: false });
export const LOCKED_HIDDEN_WIDGET = buildSpotWidget({
  editable: false,
  viewable: false,
  deletable: false,
});
export const REGION_SPECIFIC_WIDGET = buildSpotWidget({
  type: 'text',
  regionSpecific: 1,
  mediaIds: [],
  mediaFiles: [],
});

export const mockSpotWidget = buildSpotWidget();

// -----------------------------------------------------------------------------
// GET /playlistdashboard/{id} response.
//
// playlistId: 1 / name: 'Test Playlist' — the selected playlist
// isDynamic: 0 — 1 disables the delete confirm button
// spotsFound — the number of rows drawn; the counter is spotsFound - widgets.length
// -----------------------------------------------------------------------------
export const buildSpotsResponse = (
  overrides: {
    playlist?: Partial<PlaylistSpotsResponse['playlist']>;
    spotsFound?: number;
  } = {},
): PlaylistSpotsResponse => ({
  playlist: {
    playlistId: 1,
    name: 'Test Playlist',
    isDynamic: 0,
    widgets: [],
    ...overrides.playlist,
  },
  spotsFound: overrides.spotsFound ?? 0,
});

// Lobby-style default: 1 filled spot out of 3 (counter reads "2/3").
export const mockSpotsResponse = buildSpotsResponse({
  playlist: { widgets: [mockSpotWidget] },
  spotsFound: 3,
});

export const EMPTY_SPOTS_RESPONSE = buildSpotsResponse();

// -----------------------------------------------------------------------------
// Playlist row for the PlaylistDropdown (GET /playlistdashboard/data).
//
// playlistId: 1 — becomes the option value and the selectedPlaylistId saved to prefs
// name: 'Test Playlist' — the option label
// -----------------------------------------------------------------------------
export const buildDashboardPlaylist = (overrides: Partial<Playlist> = {}): Playlist => ({
  folderId: 0,
  playlistId: 1,
  name: 'Test Playlist',
  createdDt: '',
  modifiedDt: '',
  ownerId: '',
  valid: false,
  tags: [],
  duration: 0,
  enableStat: '',
  retired: false,
  expires: 0,
  updateInLayouts: false,
  isDynamic: 0,
  filterMediaName: '',
  filterFolderId: null,
  maxNumberOfItems: 0,
  ...overrides,
});

export const mockPlaylist = buildDashboardPlaylist();

export const SINGLE_PLAYLIST: FetchPlaylistsForDashboardResponse = {
  rows: [mockPlaylist],
  totalCount: 1,
};

export const EMPTY_PLAYLIST_TABLE: FetchPlaylistsForDashboardResponse = {
  rows: [],
  totalCount: 0,
};

// A File for upload / drag-drop tests. Image MIME by default so a blob preview is created.
export const makeFile = (name = 'photo.png', type = 'image/png', size = 2048): File =>
  new File([new Uint8Array(size)], name, { type });

// The default logged-in user. Super Admin, so the router's dashboard.playlist gate passes;
// spot-level permissions come from each widget's viewable/editable/deletable, not the user.
export const mockUser: User = buildCurrentUser(PERSONAS.superAdmin, {
  userId: 1,
  userName: 'TestUser',
  groupId: 1,
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
});

// The user-preference option the dashboard saves the chosen playlist under.
export const PREF_KEY = 'playlist_dashboard';

// Query keys used by PlaylistDashboard / usePlaylistSpots.
export const queryKeys = {
  playlistDashboardPref: ['userPref', PREF_KEY] as const,
  spots: (playlistId: number) => ['playlistDashboard', 'spots', playlistId] as const,
};
