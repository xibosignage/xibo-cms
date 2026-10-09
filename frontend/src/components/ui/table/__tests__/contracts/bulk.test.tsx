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

// The shared notify mock must be imported before any hook that uses notify.
// eslint-disable-next-line import/order -- must load before the hooks so their notify is the mock
import { mockNotifySuccess } from '@/testUtils/notifyMock';

import { act, render, renderHook, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AxiosError } from 'axios';
import type { TFunction } from 'i18next';
import type { ReactNode } from 'react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, test, vi } from 'vitest';

const mockDeleteCampaign = vi.fn();
const mockDeleteLayout = vi.fn();
const mockDeleteMedia = vi.fn();
const mockCollectNow = vi.fn();
// vi.mock factories are hoisted above this line, so the object they use must be hoisted too.
const mockHttp = vi.hoisted(() => ({ get: vi.fn(), put: vi.fn(), post: vi.fn(), delete: vi.fn() }));

vi.mock('@/lib/api', () => ({ default: mockHttp }));
vi.mock('@/services/campaignApi', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  deleteCampaign: (...args: unknown[]) => mockDeleteCampaign(...args),
}));
vi.mock('@/services/layoutsApi', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  deleteLayout: (...args: unknown[]) => mockDeleteLayout(...args),
}));
vi.mock('@/services/mediaApi', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  deleteMedia: (...args: unknown[]) => mockDeleteMedia(...args),
}));
vi.mock('@/services/displaysApi', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  collectNow: (...args: unknown[]) => mockCollectNow(...args),
}));
vi.mock('@/components/ui/modals/Modal');

import EnableStatsMultipleModal from '@/components/ui/modals/EnableStatsMultipleModal';
import { useCampaignActions } from '@/pages/Design/Campaigns/hooks/useCampaignActions';
import { useLayoutActions } from '@/pages/Design/Layouts/hooks/useLayoutActions';
import { useDisplaysActions } from '@/pages/Displays/Displays/hooks/useDisplaysActions';
import { useMediaActions } from '@/pages/Library/Media/hooks/useMediaActions';
import { saveMultiPermissions } from '@/services/permissionsApi';
import { editMultipleTags } from '@/services/tagApi';
import { trackSequentialCalls } from '@/testUtils/sequentialMock';

const t = vi.fn((key: string) => key) as unknown as TFunction;
const routerWrapper = ({ children }: { children: ReactNode }) => (
  <MemoryRouter>{children}</MemoryRouter>
);

/** What the API returns for an item someone else has already deleted. */
const alreadyDeleted = () => {
  const error = new AxiosError('Not Found');
  error.response = {
    status: 404,
    data: {},
    statusText: 'Not Found',
    headers: {},
    config: {} as never,
  };
  return error;
};

const bulkHookProps = () => ({
  t,
  handleRefresh: vi.fn(),
  closeModal: vi.fn(),
  setRowSelection: vi.fn(),
  setItemsToMove: vi.fn(),
});

beforeEach(() => {
  vi.clearAllMocks();
});

describe('bulk delete sends every selected item and counts one already deleted as deleted', () => {
  test('Campaigns', async () => {
    const tracker = trackSequentialCalls(mockDeleteCampaign, undefined, {
      key: (id: number) => id,
      respond: (id: number) => (id === 2 ? Promise.reject(alreadyDeleted()) : undefined),
    });
    const props = bulkHookProps();
    const { result } = renderHook(() => useCampaignActions(props), { wrapper: routerWrapper });

    await act(() =>
      result.current.confirmDelete([1, 2, 3].map((campaignId) => ({ campaignId }) as never)),
    );

    expect([...tracker.callOrder].sort()).toEqual([1, 2, 3]);
    expect(t).toHaveBeenCalledWith('{{count}} campaign(s) deleted successfully.', { count: 3 });
    expect(mockNotifySuccess).toHaveBeenCalled();
    expect(result.current.deleteError).toBeNull();
    expect(props.closeModal).toHaveBeenCalled();
  });

  test('Layouts', async () => {
    const tracker = trackSequentialCalls(mockDeleteLayout, undefined, {
      key: (id: number) => id,
      respond: (id: number) => (id === 2 ? Promise.reject(alreadyDeleted()) : undefined),
    });
    const props = { ...bulkHookProps(), timezone: 'UTC', folderId: null };
    const { result } = renderHook(() => useLayoutActions(props), { wrapper: routerWrapper });

    await act(() =>
      result.current.confirmDelete([1, 2, 3].map((layoutId) => ({ layoutId }) as never)),
    );

    expect([...tracker.callOrder].sort()).toEqual([1, 2, 3]);
    expect(mockNotifySuccess).toHaveBeenCalled();
    expect(result.current.deleteError).toBeNull();
    expect(props.closeModal).toHaveBeenCalled();
  });

  test('Media', async () => {
    const tracker = trackSequentialCalls(mockDeleteMedia, undefined, {
      key: (id: number) => id,
      respond: (id: number) => (id === 2 ? Promise.reject(alreadyDeleted()) : undefined),
    });
    const props = { ...bulkHookProps(), setItemsToDelete: vi.fn() };
    const { result } = renderHook(() => useMediaActions(props));

    await act(() =>
      result.current.confirmDelete(
        [1, 2, 3].map((mediaId) => ({ mediaId }) as never),
        { allLayouts: false, purgeList: false },
      ),
    );

    expect([...tracker.callOrder].sort()).toEqual([1, 2, 3]);
    expect(mockNotifySuccess).toHaveBeenCalled();
    expect(result.current.deleteError).toBeNull();
    expect(props.closeModal).toHaveBeenCalled();
  });

  test('a real failure is still reported, after the other items are deleted', async () => {
    const serverError = new AxiosError('Server Error');
    serverError.response = {
      status: 500,
      data: { message: 'Campaign 2 is in use.' },
      statusText: '',
      headers: {},
      config: {} as never,
    };
    const tracker = trackSequentialCalls(mockDeleteCampaign, undefined, {
      key: (id: number) => id,
      respond: (id: number) => (id === 2 ? Promise.reject(serverError) : undefined),
    });
    const props = bulkHookProps();
    const { result } = renderHook(() => useCampaignActions(props), { wrapper: routerWrapper });

    await act(() =>
      result.current.confirmDelete([1, 2, 3].map((campaignId) => ({ campaignId }) as never)),
    );

    expect([...tracker.callOrder].sort()).toEqual([1, 2, 3]);
    expect(result.current.deleteError).toBe(
      '{{count}} campaign(s) deleted successfully. Campaign 2 is in use.',
    );
    expect(props.closeModal).not.toHaveBeenCalled();
  });
});

describe('bulk actions that send every id in one request', () => {
  test('Edit Tags sends every selected id', async () => {
    mockHttp.put.mockResolvedValue({ data: { failedCount: 0, failedNames: [] } });

    await editMultipleTags({ targetType: 'layout', ids: [1, 2, 3], addTags: 'promo' });

    const [url, body] = mockHttp.put.mock.calls[0]! as [string, string];
    expect(url).toBe('/tag/layout/multi');
    expect(new URLSearchParams(body).get('targetIds')).toBe('1,2,3');
  });

  test('Share sends every selected id', async () => {
    mockHttp.post.mockResolvedValue({ data: {} });

    await saveMultiPermissions({
      entity: 'Layout',
      ids: [1, 2, 3],
      groupIds: { 5: { view: 1, edit: 0, delete: 0 } },
    } as Parameters<typeof saveMultiPermissions>[0]);

    const [url, body] = mockHttp.post.mock.calls[0]! as [string, string];
    expect(url).toBe('/user/permissions/Layout');
    expect(new URLSearchParams(body).get('ids')).toBe('1,2,3');
  });
});

describe('bulk actions that send one request per item', () => {
  test('Enable Stats updates every selected item, one at a time, even when one fails', async () => {
    const setEnableStat = vi.fn();
    const tracker = trackSequentialCalls(setEnableStat, undefined, {
      key: (id: number) => id,
      respond: (id: number) => (id === 2 ? Promise.reject(new Error('locked')) : undefined),
    });
    const user = userEvent.setup();
    render(
      <EnableStatsMultipleModal
        isOpen
        ids={[1, 2, 3]}
        setEnableStat={setEnableStat}
        onClose={vi.fn()}
      />,
    );

    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(tracker.callOrder).toEqual([1, 2, 3]));
    expect(tracker.maxInFlight).toBe(1);
  });

  test('Displays Collect Now reaches every display even when one fails, and reports the failure', async () => {
    const tracker = trackSequentialCalls(mockCollectNow, undefined, {
      key: (displayGroupId: number) => displayGroupId,
      respond: (displayGroupId: number) =>
        displayGroupId === 12 ? Promise.reject(new Error('offline')) : undefined,
    });
    const props = {
      ...bulkHookProps(),
      showThumbnailColumn: false,
      revealThumbnailColumns: vi.fn(),
    };
    const { result } = renderHook(() => useDisplaysActions(props), { wrapper: routerWrapper });

    await act(() =>
      result.current.confirmBulkCollectNow(
        [11, 12, 13].map(
          (displayGroupId) => ({ displayId: displayGroupId, displayGroupId }) as never,
        ),
      ),
    );

    expect([...tracker.callOrder].sort()).toEqual([11, 12, 13]);
    expect(result.current.actionError).not.toBeNull();
  });
});
