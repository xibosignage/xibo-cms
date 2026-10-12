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
import type { SortingState } from '@tanstack/react-table';
import { renderHook, waitFor } from '@testing-library/react';
import { createElement, type ReactNode } from 'react';
import { beforeEach, describe, expect, test, vi, type Mock } from 'vitest';

import { propsWith, t } from './helpers';

const mockFetchEvent = vi.fn();
const mockFetchMedia = vi.fn();
const mockFetchLayouts = vi.fn();

vi.mock('@/services/eventApi', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  fetchEvent: (...args: unknown[]) => mockFetchEvent(...args),
}));
vi.mock('@/services/mediaApi', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  fetchMedia: (...args: unknown[]) => mockFetchMedia(...args),
}));
vi.mock('@/services/layoutsApi', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  fetchLayouts: (...args: unknown[]) => mockFetchLayouts(...args),
}));

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import { UserProvider } from '@/context/UserContext';
import {
  LAYOUT_INITIAL_FILTER_STATE,
  getBaseFilterKeys as getLayoutFilterKeys,
  getLayoutColumns,
} from '@/pages/Design/Layouts/LayoutConfig';
import { useLayoutData } from '@/pages/Design/Layouts/hooks/useLayoutData';
import {
  INITIAL_FILTER_STATE as MEDIA_INITIAL_FILTER_STATE,
  getBaseFilterKeys as getMediaFilterKeys,
  getMediaColumns,
} from '@/pages/Library/Media/MediaConfig';
import { useMediaData } from '@/pages/Library/Media/hooks/useMediaData';
import {
  INITIAL_FILTER_STATE as EVENT_INITIAL_FILTER_STATE,
  getBaseFilterKeys as getEventFilterKeys,
  getEventColumns,
} from '@/pages/Schedule/Schedule/EventsConfig';
import { useAllEventData, useEventData } from '@/pages/Schedule/Schedule/hooks/useEventData';
import { sortableColumnIds } from '@/testUtils/columnContract';
import { knownFailure } from '@/testUtils/knownFailure';
import { readSortAllowList, type SortFactory } from '@/testUtils/sortAllowList';

/**
 * Sort and filter contract: every control on a grid must reach the request, and every sort key
 * must be one the server accepts.
 *
 * Grid headers and filters that silently do nothing are a repeat bug class. The server makes this worse: `buildSortQuery()` drops an
 * unknown sort key without an error and falls back to its default order.
 *
 * How it is checked, per grid:
 * - Sort: each column a user can sort is fed to the real data hook; the request must carry it as
 *   `sortBy`, and the key must be in the server's allow-list, read from the PHP factory
 *   (testUtils/sortAllowList.ts).
 * - Filter: each filter in the grid's filter config is set on its own; the request must change.
 *   Its companion switches (AND/OR, regex, exact tags) must change the request too.
 *
 * Only the service function is mocked, so the hook's real request-building code runs.
 */

type Request = Record<string, unknown>;

beforeEach(() => {
  vi.clearAllMocks();
  mockFetchEvent.mockResolvedValue({ rows: [], totalCount: 0 });
  mockFetchMedia.mockResolvedValue({ rows: [], totalCount: 0 });
  mockFetchLayouts.mockResolvedValue({ rows: [], totalCount: 0 });
});

const wrapper = () => {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return ({ children }: { children: ReactNode }) =>
    createElement(UserProvider, {
      initialUser: null,
      children: createElement(QueryClientProvider, { client }, children),
    });
};

/** The request a data hook sends to its (mocked) service, without the abort signal. */
async function requestFrom(service: Mock, runHook: () => unknown): Promise<Request> {
  service.mockClear();
  const { unmount } = renderHook(runHook, { wrapper: wrapper() });
  await waitFor(() => expect(service).toHaveBeenCalled());
  unmount();
  const calls = service.mock.calls;
  const request = { ...(calls[calls.length - 1]![0] as Request) };
  delete request.signal; // a fresh AbortSignal per call; not part of what the grid asked for
  return request;
}

const page = { pageIndex: 0, pageSize: 10 };

/** One grid: how to get its columns, its filters, and the request its data hook sends. */
type Grid<F> = {
  name: string;
  factory: SortFactory;
  sortableIds: string[];
  filters: FilterConfigItem<F>[];
  initial: F;
  request: (sorting: SortingState, filters: F) => Promise<Request>;
};

const grids = {
  events: {
    name: 'Events',
    factory: 'ScheduleFactory',
    sortableIds: sortableColumnIds(getEventColumns(propsWith())),
    filters: getEventFilterKeys(t),
    initial: EVENT_INITIAL_FILTER_STATE,
    request: (sorting, advancedFilters) =>
      requestFrom(mockFetchEvent, () =>
        useEventData({ pagination: page, sorting, filter: '', advancedFilters }),
      ),
  } as Grid<typeof EVENT_INITIAL_FILTER_STATE>,
  media: {
    name: 'Media',
    factory: 'MediaFactory',
    sortableIds: sortableColumnIds(getMediaColumns(propsWith())),
    filters: getMediaFilterKeys(t, true),
    initial: MEDIA_INITIAL_FILTER_STATE,
    request: (sorting, advancedFilters) =>
      requestFrom(mockFetchMedia, () =>
        useMediaData({ pagination: page, sorting, folderId: null, advancedFilters }),
      ),
  } as Grid<typeof MEDIA_INITIAL_FILTER_STATE>,
  layouts: {
    name: 'Layouts',
    factory: 'LayoutFactory',
    sortableIds: sortableColumnIds(getLayoutColumns(propsWith())),
    filters: getLayoutFilterKeys(t, true),
    initial: LAYOUT_INITIAL_FILTER_STATE,
    request: (sorting, advancedFilters) =>
      requestFrom(mockFetchLayouts, () =>
        useLayoutData({ pagination: page, sorting, filter: '', folderId: null, advancedFilters }),
      ),
  } as Grid<typeof LAYOUT_INITIAL_FILTER_STATE>,
};

/** Sort keys known to be missing from the server allow-list; each has its own `knownFailure` below. */
const KNOWN_UNSORTABLE: Record<string, string[]> = {
  Events: ['recurringEventDescription'], // report 221
  Layouts: ['code'], // new
};

/** A value for one filter that differs from its initial value. */
function sampleValue<F>(item: FilterConfigItem<F>, initial: F): unknown {
  const current = (initial as Record<string, unknown>)[item.name as string];
  if (item.type === 'tags') return [{ tagId: 1, tag: 'probe', value: '' }];
  if (item.type === 'text') return 'probe';
  if (item.type === 'number') return 7;
  const options = (item.options ?? [])
    .map((option) => option.value)
    .filter((value) => value !== '' && value !== null && value !== undefined && value !== current);
  return options[0] ?? 7;
}

describe.each(Object.values(grids))('$name grid', (grid) => {
  const typedGrid = grid as unknown as Grid<Record<string, unknown>>;

  test('the grid has sortable columns and filters to check', () => {
    // Guards the sweeps below: an empty list would make them pass without checking anything.
    expect(typedGrid.sortableIds.length).toBeGreaterThan(0);
    expect(typedGrid.filters.length).toBeGreaterThan(0);
  });

  test('every sortable column sends its id as the sort key, ascending and descending', async () => {
    const wrong: string[] = [];
    for (const id of typedGrid.sortableIds) {
      const request = await typedGrid.request([{ id, desc: false }], typedGrid.initial);
      if (request.sortBy !== id || request.sortDir !== 'asc') {
        wrong.push(
          `${id}: sent sortBy=${String(request.sortBy)} sortDir=${String(request.sortDir)}`,
        );
      }
    }
    const descending = await typedGrid.request(
      [{ id: typedGrid.sortableIds[0]!, desc: true }],
      typedGrid.initial,
    );

    expect(wrong).toEqual([]);
    expect(descending.sortDir).toBe('desc');
  });

  test('every sortable column is a sort key the server accepts', () => {
    const allowed = readSortAllowList(typedGrid.factory);
    const known = KNOWN_UNSORTABLE[typedGrid.name] ?? [];

    expect(
      typedGrid.sortableIds.filter((id) => !allowed.includes(id) && !known.includes(id)),
    ).toEqual([]);
  });

  test('every filter changes the request', async () => {
    const baseline = await typedGrid.request([], typedGrid.initial);
    const ignored: string[] = [];
    for (const item of typedGrid.filters) {
      const name = item.name as string;
      const request = await typedGrid.request([], {
        ...typedGrid.initial,
        [name]: sampleValue(item, typedGrid.initial),
      });
      if (JSON.stringify(request) === JSON.stringify(baseline)) ignored.push(name);
    }

    expect(ignored).toEqual([]);
  });

  test('the AND/OR, regex and exact-tags switches change the request', async () => {
    const ignored: string[] = [];
    for (const item of typedGrid.filters) {
      const name = item.name as string;
      const withValue = { ...typedGrid.initial, [name]: sampleValue(item, typedGrid.initial) };
      const switches: [string | undefined, unknown, unknown][] = [
        [item.andOrKey as string | undefined, 'OR', 'AND'],
        [item.regexKey as string | undefined, false, true],
        [item.exactTagsKey as string | undefined, false, true],
      ];
      for (const [key, off, on] of switches) {
        if (!key) continue;
        const base = item.regexKey === key ? { ...withValue, [name]: 'pro.e' } : withValue;
        const a = await typedGrid.request([], { ...base, [key]: off });
        const b = await typedGrid.request([], { ...base, [key]: on });
        if (JSON.stringify(a) === JSON.stringify(b)) ignored.push(`${name}.${key}`);
      }
    }

    expect(ignored).toEqual([]);
  });
});

describe('known sort and filter defects', () => {
  // The column sends `recurringEventDescription`; ScheduleFactory only allows
  // `recurringEvent`, so the server silently keeps its default order.
  knownFailure(
    'the Events Recurrence Description sort key is one the server accepts',
    () => readSortAllowList('ScheduleFactory'),
    (allowed) => expect(allowed).toContain('recurringEventDescription'),
  );

  // The Layouts Code column is sortable, but `code` is not in LayoutFactory's
  // allow-list, so clicking it changes nothing.
  knownFailure(
    'the Layouts Code sort key is one the server accepts',
    () => readSortAllowList('LayoutFactory'),
    (allowed) => expect(allowed).toContain('code'),
  );

  // In Day view the Events grid loads rows with useAllEventData, which takes no sort,
  // yet the headers stay clickable.
  knownFailure(
    'the Events Day view sends the chosen sort to the server',
    () =>
      requestFrom(mockFetchEvent, () =>
        useAllEventData({
          advancedFilters: EVENT_INITIAL_FILTER_STATE,
          sorting: [{ id: 'name', desc: false }],
        } as Parameters<typeof useAllEventData>[0]),
      ),
    (request) => expect(request.sortBy).toBe('name'),
  );

  // useEventData sends `campaignId: layoutCampaignId ?? campaignId`, so with both the
  // Layout and the Campaign filter set, the Campaign filter is dropped.
  knownFailure(
    'the Events Campaign filter still applies when a Layout filter is also set',
    async () => ({
      layoutOnly: await grids.events.request([], {
        ...EVENT_INITIAL_FILTER_STATE,
        layoutCampaignId: 7,
      }),
      both: await grids.events.request([], {
        ...EVENT_INITIAL_FILTER_STATE,
        layoutCampaignId: 7,
        campaignId: 9,
      }),
    }),
    ({ layoutOnly, both }) => expect(both).not.toEqual(layoutOnly),
  );
});
