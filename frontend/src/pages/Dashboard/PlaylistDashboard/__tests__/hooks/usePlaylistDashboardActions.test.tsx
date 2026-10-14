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

// Covers only what the page can't show: temporary preview URLs (created and released) and
// the widget leaving the cached list before the server answers. Upload and delete payloads
// are tested through the page in PlaylistDashboard.upload/delete.test.tsx.

import { QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { usePlaylistDashboardActions } from '../../hooks/usePlaylistDashboardActions';
import {
  buildSpotsResponse,
  buildSpotWidgets,
  makeFile,
  queryKeys,
  SUBPLAYLIST_WIDGET,
} from '../fixtures/playlistDashboard';
import { mockDeletePending, mockUploadPending, uploadOk } from '../mocks/playlistDashboardApi';

import type { UploadSpotMediaParams } from '@/services/dashboardApi';
import { uploadSpotMedia } from '@/services/dashboardApi';
import { testQueryClient } from '@/setupTests';
import type { PlaylistSpotsResponse } from '@/types/dashboard';

// =============================================================================
// Module mocks
// =============================================================================

// Every export reachable from the code under test, not only the two the hook calls:
// usePlaylistDashboardActions imports usePlaylistSpots (for its query key), which imports
// fetchPlaylistSpots, and the shared mocks helper refers to fetchPlaylistsForDashboard.
vi.mock('@/services/dashboardApi', () => ({
  fetchPlaylistsForDashboard: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  fetchPlaylistSpots: vi.fn(),
  uploadSpotMedia: vi.fn(),
  deleteSpotWidget: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));

// =============================================================================
// Helpers local to this file
// =============================================================================

const wrapper = ({ children }: { children: ReactNode }) => (
  <QueryClientProvider client={testQueryClient}>{children}</QueryClientProvider>
);

const renderActions = (playlistId: number | null = 1) =>
  renderHook(() => usePlaylistDashboardActions(testQueryClient, playlistId), { wrapper });

const [WIDGET_A, WIDGET_B] = buildSpotWidgets(2);

// =============================================================================
// Tests
// =============================================================================

describe('usePlaylistDashboardActions', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    // JSDOM has no object URLs.
    URL.createObjectURL = vi.fn(() => 'blob:mock-1');
    URL.revokeObjectURL = vi.fn();
    const spots = buildSpotsResponse({
      playlist: { widgets: [WIDGET_A!, WIDGET_B!] },
      spotsFound: 2,
    });
    // The hook refetches this query after an upload; give it a fetcher so that works.
    testQueryClient.setQueryDefaults(queryKeys.spots(1), { queryFn: () => Promise.resolve(spots) });
    testQueryClient.setQueryData(queryKeys.spots(1), spots);
  });

  test('choosing an image creates a temporary preview; other files do not', async () => {
    mockUploadPending();
    const { result } = renderActions();

    act(() => {
      void result.current.startUpload(0, makeFile('a.png', 'image/png'));
      void result.current.startUpload(1, makeFile('a.pdf', 'application/pdf'));
    });

    expect(URL.createObjectURL).toHaveBeenCalledTimes(1);
    expect(result.current.uploadStates.get(0)?.blobUrl).toBe('blob:mock-1');
    expect(result.current.uploadStates.get(1)?.blobUrl).toBeUndefined();
  });

  // Not yet discriminating: while K12 is open, the state-change cleanup also releases the
  // preview, so removing the release in clearUpload still passes (checked by mutation). It
  // becomes a real check once K12 is fixed.
  test('the temporary preview is released when its upload finishes', async () => {
    const resolve = mockUploadPending();
    const { result } = renderActions();

    act(() => {
      void result.current.startUpload(0, makeFile('a.png', 'image/png'));
    });
    await act(async () => resolve(uploadOk()));

    await waitFor(() => expect(result.current.uploadStates.has(0)).toBe(false));
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:mock-1');
  });

  test('previews still in use are released when the user leaves the page', () => {
    mockUploadPending();
    const { result, unmount } = renderActions();

    act(() => {
      void result.current.startUpload(0, makeFile('a.png', 'image/png'));
    });
    unmount();

    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:mock-1');
  });

  // Known-open (K12): the "release on leaving" cleanup is tied to every change of the upload
  // state, so the first progress update releases the preview of an upload still running.
  // Flip to `test` when the cleanup only runs on unmount.
  test.fails('a preview is not released while its upload is still running', () => {
    mockUploadPending();
    const { result } = renderActions();

    act(() => {
      void result.current.startUpload(0, makeFile('a.png', 'image/png'));
    });
    const params = vi.mocked(uploadSpotMedia).mock.calls[0]![0] as UploadSpotMediaParams;
    act(() => params.onProgress?.(40));

    expect(result.current.uploadStates.get(0)?.status).toBe('uploading');
    expect(URL.revokeObjectURL).not.toHaveBeenCalled();
  });

  test('with no playlist selected, nothing is uploaded', async () => {
    const { result } = renderActions(null);

    await act(async () => {
      await result.current.startUpload(0, makeFile());
    });

    expect(uploadSpotMedia).not.toHaveBeenCalled();
    expect(result.current.uploadStates.size).toBe(0);
  });

  test('a sub-playlist spot is never uploaded to', async () => {
    const { result } = renderActions();

    await act(async () => {
      await result.current.startUpload(0, makeFile(), SUBPLAYLIST_WIDGET);
    });

    expect(uploadSpotMedia).not.toHaveBeenCalled();
  });

  test('a removed widget leaves the cached list before the server answers', () => {
    mockDeletePending();
    const { result } = renderActions();

    act(() => {
      void result.current.handleDeleteWidget(WIDGET_A!.widgetId);
    });

    const cached = testQueryClient.getQueryData<PlaylistSpotsResponse>(queryKeys.spots(1));
    expect(cached?.playlist.widgets.map((w) => w.widgetId)).toEqual([WIDGET_B!.widgetId]);
  });
});
