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

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react';
import { createElement, type ReactNode } from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';

import { INITIAL_FILTER_STATE } from '../MediaConfig';
import { useMediaData } from '../hooks/useMediaData';

import type { Tag } from '@/types/tag';

// ---------------------------------------------------------------------------
// Mock fetchMedia at the service boundary — we verify what it was called with.
// ---------------------------------------------------------------------------

const mockFetchMedia = vi.fn();

vi.mock('@/services/mediaApi', () => ({
  fetchMedia: (...args: unknown[]) => mockFetchMedia(...args),
}));

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Creates a fresh QueryClient + wrapper for each test to avoid cache pollution. */
function createWrapper() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return ({ children }: { children: ReactNode }) =>
    createElement(QueryClientProvider, { client: qc }, children);
}

/** Minimal params shared by most tests — only advancedFilters varies. */
const BASE_PARAMS = {
  pagination: { pageIndex: 0, pageSize: 10 },
  sorting: [],
  folderId: null as null,
  enabled: true,
};

const tag = (name: string): Tag => ({ tagId: 1, tag: name, value: '' });

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('useMediaData — API request transformation', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockFetchMedia.mockResolvedValue({ rows: [], totalCount: 0 });
  });

  // S38
  it('joins a tags array into a comma-separated string for fetchMedia', async () => {
    renderHook(
      () =>
        useMediaData({
          ...BASE_PARAMS,
          advancedFilters: {
            ...INITIAL_FILTER_STATE,
            tags: [tag('nature'), tag('design')],
          },
        }),
      { wrapper: createWrapper() },
    );

    await waitFor(() => expect(mockFetchMedia).toHaveBeenCalled());

    expect(mockFetchMedia).toHaveBeenCalledWith(expect.objectContaining({ tags: 'nature,design' }));
  });

  // S39
  it('does not include "tags" in the request when the tags array is empty', async () => {
    renderHook(
      () =>
        useMediaData({
          ...BASE_PARAMS,
          advancedFilters: { ...INITIAL_FILTER_STATE, tags: [] },
        }),
      { wrapper: createWrapper() },
    );

    await waitFor(() => expect(mockFetchMedia).toHaveBeenCalled());

    const callArg = mockFetchMedia.mock.calls[0]?.[0] as Record<string, unknown>;
    expect(callArg).not.toHaveProperty('tags');
  });

  // S40
  it('converts exactTags: true to exactTags: 1', async () => {
    renderHook(
      () =>
        useMediaData({
          ...BASE_PARAMS,
          advancedFilters: { ...INITIAL_FILTER_STATE, exactTags: true },
        }),
      { wrapper: createWrapper() },
    );

    await waitFor(() => expect(mockFetchMedia).toHaveBeenCalled());

    expect(mockFetchMedia).toHaveBeenCalledWith(expect.objectContaining({ exactTags: 1 }));
  });

  // S41
  it('converts exactTags: false to exactTags: 0', async () => {
    renderHook(
      () =>
        useMediaData({
          ...BASE_PARAMS,
          advancedFilters: { ...INITIAL_FILTER_STATE, exactTags: false },
        }),
      { wrapper: createWrapper() },
    );

    await waitFor(() => expect(mockFetchMedia).toHaveBeenCalled());

    expect(mockFetchMedia).toHaveBeenCalledWith(expect.objectContaining({ exactTags: 0 }));
  });

  // S42
  it('sets useRegexForName: 1 when flag is true and media contains a valid regex', async () => {
    renderHook(
      () =>
        useMediaData({
          ...BASE_PARAMS,
          advancedFilters: {
            ...INITIAL_FILTER_STATE,
            media: 'abc.*',
            useRegexForName: true,
          },
        }),
      { wrapper: createWrapper() },
    );

    await waitFor(() => expect(mockFetchMedia).toHaveBeenCalled());

    expect(mockFetchMedia).toHaveBeenCalledWith(expect.objectContaining({ useRegexForName: 1 }));
  });

  // S43
  it('omits useRegexForName when the media string contains an invalid regex', async () => {
    renderHook(
      () =>
        useMediaData({
          ...BASE_PARAMS,
          advancedFilters: {
            ...INITIAL_FILTER_STATE,
            media: '[', // invalid — unclosed character class
            useRegexForName: true,
          },
        }),
      { wrapper: createWrapper() },
    );

    await waitFor(() => expect(mockFetchMedia).toHaveBeenCalled());

    const callArg = mockFetchMedia.mock.calls[0]?.[0] as Record<string, unknown>;
    expect(callArg).not.toHaveProperty('useRegexForName');
  });

  // S44
  it('omits mediaId from the request when it is null', async () => {
    renderHook(
      () =>
        useMediaData({
          ...BASE_PARAMS,
          advancedFilters: { ...INITIAL_FILTER_STATE, mediaId: null },
        }),
      { wrapper: createWrapper() },
    );

    await waitFor(() => expect(mockFetchMedia).toHaveBeenCalled());

    const callArg = mockFetchMedia.mock.calls[0]?.[0] as Record<string, unknown>;
    expect(callArg).not.toHaveProperty('mediaId');
  });

  // Additional: layoutId null → omitted (mirrors S44 for a different field)
  it('omits layoutId from the request when it is null', async () => {
    renderHook(
      () =>
        useMediaData({
          ...BASE_PARAMS,
          advancedFilters: { ...INITIAL_FILTER_STATE, layoutId: null },
        }),
      { wrapper: createWrapper() },
    );

    await waitFor(() => expect(mockFetchMedia).toHaveBeenCalled());

    const callArg = mockFetchMedia.mock.calls[0]?.[0] as Record<string, unknown>;
    expect(callArg).not.toHaveProperty('layoutId');
  });
});
