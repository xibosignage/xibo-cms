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

import '@/testUtils/notifyMock';

import { renderHook, act } from '@testing-library/react';
import { AxiosError, AxiosHeaders } from 'axios';
import type { TFunction } from 'i18next';
import { MemoryRouter } from 'react-router-dom';
import { describe, test, expect, vi, beforeEach, beforeAll, afterAll, afterEach } from 'vitest';

import { useLayoutActions } from '../../hooks/useLayoutActions';
import { mockLayout } from '../layoutTestUtils';

import {
  assignLayoutToCampaign,
  checkoutLayout,
  copyLayout,
  createLayout,
  discardLayout,
  exportLayout,
  publishLayout,
} from '@/services/layoutsApi';
import { mockNotifyError, mockNotifyInfo, mockNotifySuccess } from '@/testUtils/notifyMock';
import { setTimezone } from '@/testUtils/timezone';
import type { Layout } from '@/types/layout';

// Companion to useLayoutActions.test.tsx, which covers Move. This file covers every
// other action the hook owns apart from Delete (Layouts.delete.test.tsx). Six
// page-level Layouts tests replace this hook with stubs, so until now none of these
// actions had run for real in a test.

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/services/layoutsApi', () => ({
  assignLayoutToCampaign: vi.fn(),
  checkoutLayout: vi.fn(),
  copyLayout: vi.fn(),
  createLayout: vi.fn(),
  deleteLayout: vi.fn(),
  discardLayout: vi.fn(),
  exportLayout: vi.fn(),
  publishLayout: vi.fn(),
}));
vi.mock('@/services/folderApi', () => ({ selectFolder: vi.fn() }));

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal();
  return { ...(actual as object), useNavigate: () => mockNavigate };
});

// ─── Timezones ────────────────────────────────────────────────────────────────

// The browser is 17 hours ahead of the CMS. A scheduled publish date must be sent
// as wall-clock time in the CMS zone, never the browser's.
const BROWSER_TZ = 'Pacific/Auckland'; // UTC+13 in March
const CMS_TZ = 'America/New_York'; // UTC-4 in March (DST from 8 March 2026)

const originalTz = process.env.TZ;
beforeAll(() => {
  process.env.TZ = BROWSER_TZ;
});
afterAll(() => {
  setTimezone(originalTz);
});

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const LAYOUT: Layout = { ...mockLayout, layoutId: 861001, layout: 'My Layout' };

const apiError = (message: string) =>
  new AxiosError('Request failed', '422', undefined, undefined, {
    status: 422,
    statusText: 'Unprocessable',
    headers: {},
    config: { headers: new AxiosHeaders() },
    data: { message },
  });

// ─── Helpers ──────────────────────────────────────────────────────────────────

const mockT = ((str: string) => str) as unknown as TFunction;
const handleRefresh = vi.fn();
const closeModal = vi.fn();
const setRowSelection = vi.fn();

const renderActions = (folderId: number | null = null) =>
  renderHook(
    () =>
      useLayoutActions({
        t: mockT,
        handleRefresh,
        closeModal,
        setRowSelection,
        setItemsToMove: vi.fn(),
        timezone: CMS_TZ,
        folderId,
      }),
    { wrapper: ({ children }) => <MemoryRouter>{children}</MemoryRouter> },
  );

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('useLayoutActions — every action except Move and Delete', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(console, 'error').mockImplementation(() => {});
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  test('the test really runs with the browser and the CMS in different timezones', () => {
    expect(new Date('2026-03-10T00:00:00Z').getTimezoneOffset()).toBe(-13 * 60);
  });

  describe('copy', () => {
    test('sends the new name, description and the copy-media choice for the chosen layout', async () => {
      vi.mocked(copyLayout).mockResolvedValue(LAYOUT);
      const { result } = renderActions();

      await act(() =>
        result.current.handleConfirmClone(LAYOUT, 'Copy of lobby', 'Second floor', true),
      );

      expect(copyLayout).toHaveBeenCalledWith(861001, {
        name: 'Copy of lobby',
        description: 'Second floor',
        copyMediaFiles: 1,
      });
      expect(mockNotifySuccess).toHaveBeenCalledWith('Layout copied successfully');
      expect(handleRefresh).toHaveBeenCalledOnce();
      expect(closeModal).toHaveBeenCalledOnce();
    });

    test('leaving "copy media files" unticked sends 0, not false', async () => {
      vi.mocked(copyLayout).mockResolvedValue(LAYOUT);
      const { result } = renderActions();

      await act(() => result.current.handleConfirmClone(LAYOUT, 'Copy', '', false));

      expect(copyLayout).toHaveBeenCalledWith(
        861001,
        expect.objectContaining({ copyMediaFiles: 0 }),
      );
    });

    test('a failed copy tells the user and keeps the modal open', async () => {
      vi.mocked(copyLayout).mockRejectedValue(new Error('boom'));
      const { result } = renderActions();

      await act(() => result.current.handleConfirmClone(LAYOUT, 'Copy', '', false));

      expect(mockNotifyError).toHaveBeenCalledWith('Failed to copy layout');
      expect(closeModal).not.toHaveBeenCalled();
      expect(result.current.isCloning).toBe(false);
    });
  });

  describe('create and open', () => {
    test('a new layout is created in the current folder and opened in the editor', async () => {
      vi.mocked(createLayout).mockResolvedValue({ ...LAYOUT, layoutId: 123 } as never);
      const { result } = renderActions(5);

      await act(() => result.current.handleCreateLayout());

      expect(createLayout).toHaveBeenCalledWith({ folderId: 5 });
      expect(mockNavigate).toHaveBeenCalledWith('/design/layout/123/editor');
      expect(handleRefresh).toHaveBeenCalledOnce();
    });

    test('if the server does not return the new layout id, nothing opens and the user is told', async () => {
      vi.mocked(createLayout).mockResolvedValue({} as never);
      const { result } = renderActions();

      await act(() => result.current.handleCreateLayout());

      expect(mockNavigate).not.toHaveBeenCalled();
      expect(mockNotifyError).toHaveBeenCalledWith('Failed to create layout');
    });

    test('opening a layout goes straight to its editor', () => {
      const { result } = renderActions();

      act(() => result.current.handleOpenLayout(77));

      expect(mockNavigate).toHaveBeenCalledWith('/design/layout/77/editor');
    });
  });

  describe('checkout', () => {
    test('checks the layout out, then opens it in the editor', async () => {
      vi.mocked(checkoutLayout).mockResolvedValue(LAYOUT);
      const { result } = renderActions();

      await act(() => result.current.handleCheckoutLayout(861001));

      expect(checkoutLayout).toHaveBeenCalledWith(861001);
      expect(mockNotifyInfo).toHaveBeenCalledWith('Preparing layout for editing...');
      expect(mockNotifySuccess).toHaveBeenCalledWith('Layout checked out successfully');
      expect(closeModal).toHaveBeenCalledOnce();
      expect(mockNavigate).toHaveBeenCalledWith('/design/layout/861001/editor');
      // The editor is opened only after the checkout has succeeded.
      expect(vi.mocked(checkoutLayout).mock.invocationCallOrder[0]!).toBeLessThan(
        mockNavigate.mock.invocationCallOrder[0]!,
      );
    });

    // From the Checkout modal the error is shown inside the modal, so the user can retry.
    test('a failure from the Checkout modal keeps the modal open and shows the server reason in it', async () => {
      vi.mocked(checkoutLayout).mockRejectedValue(apiError('Layout is locked'));
      const { result } = renderActions();

      await act(() => result.current.handleCheckoutLayout(861001));

      expect(result.current.checkoutError).toBe('Layout is locked');
      expect(mockNotifyError).not.toHaveBeenCalled();
      expect(closeModal).not.toHaveBeenCalled();
      expect(mockNavigate).not.toHaveBeenCalled();
    });

    // From a row action there is no modal to show the error in, so it becomes a toast.
    test('a failure from a row action is shown as a notification instead', async () => {
      vi.mocked(checkoutLayout).mockRejectedValue(apiError('Layout is locked'));
      const { result } = renderActions();

      await act(() => result.current.handleCheckoutLayout(861001, { notifyOnError: true }));

      expect(mockNotifyError).toHaveBeenCalledWith('Layout is locked');
      expect(result.current.checkoutError).toBeNull();
    });

    test('a failure with no server message falls back to a generic one', async () => {
      vi.mocked(checkoutLayout).mockRejectedValue(new Error('Network Error'));
      const { result } = renderActions();

      await act(() => result.current.handleCheckoutLayout(861001));

      expect(result.current.checkoutError).toBe('Failed to checkout layout');
    });
  });

  describe('discard', () => {
    test('discards the draft, clears the selection and refreshes the grid', async () => {
      vi.mocked(discardLayout).mockResolvedValue(LAYOUT);
      const { result } = renderActions();

      await act(() => result.current.handleConfirmDiscard(2));

      expect(discardLayout).toHaveBeenCalledWith(2);
      expect(mockNotifySuccess).toHaveBeenCalledWith('Layout restored successfully');
      expect(setRowSelection).toHaveBeenCalledWith({});
      expect(handleRefresh).toHaveBeenCalledOnce();
      expect(closeModal).toHaveBeenCalledOnce();
    });

    test('with no layout chosen nothing is sent', async () => {
      const { result } = renderActions();

      await act(() => result.current.handleConfirmDiscard(null));

      expect(discardLayout).not.toHaveBeenCalled();
    });

    test('a failed discard tells the user and keeps the modal open', async () => {
      vi.mocked(discardLayout).mockRejectedValue(new Error('boom'));
      const { result } = renderActions();

      await act(() => result.current.handleConfirmDiscard(2));

      expect(mockNotifyError).toHaveBeenCalledWith('Failed to discard layout changes');
      expect(closeModal).not.toHaveBeenCalled();
    });
  });

  describe('publish', () => {
    test('"publish now" sends publishNow 1 and no date', async () => {
      vi.mocked(publishLayout).mockResolvedValue(LAYOUT);
      const { result } = renderActions();

      await act(() => result.current.confirmPublish(2, { type: 'now' }));

      expect(publishLayout).toHaveBeenCalledWith(2, { publishNow: 1, publishDate: undefined });
      expect(closeModal).toHaveBeenCalledOnce();
    });

    test('a scheduled publish sends the date as CMS wall-clock time, not browser time', async () => {
      vi.mocked(publishLayout).mockResolvedValue(LAYOUT);
      const { result } = renderActions();

      // 02:00 UTC on 10 March = 22:00 on 9 March in New York = 15:00 on 10 March in Auckland.
      await act(() =>
        result.current.confirmPublish(2, {
          type: 'scheduled',
          date: new Date('2026-03-10T02:00:00Z'),
        }),
      );

      expect(publishLayout).toHaveBeenCalledWith(2, {
        publishNow: 0,
        publishDate: '2026-03-09 22:00:00',
      });
    });

    test('a refused publish shows the reason the server gave', async () => {
      vi.mocked(publishLayout).mockRejectedValue(apiError('Layout has empty regions'));
      const { result } = renderActions();

      await act(() => result.current.confirmPublish(2, { type: 'now' }));

      expect(mockNotifyError).toHaveBeenCalledWith('Layout has empty regions');
      expect(closeModal).not.toHaveBeenCalled();
      expect(result.current.isPublishing).toBe(false);
    });
  });

  describe('assign to campaign', () => {
    test('sends the chosen campaign and the layout, in that order', async () => {
      vi.mocked(assignLayoutToCampaign).mockResolvedValue(undefined);
      const { result } = renderActions();

      await act(() => result.current.handleConfirmAssign(31, 861001));

      expect(assignLayoutToCampaign).toHaveBeenCalledWith(31, 861001);
      expect(mockNotifySuccess).toHaveBeenCalledWith('Layout assigned to campaign');
      expect(closeModal).toHaveBeenCalledOnce();
    });

    test('with no layout chosen nothing is sent', async () => {
      const { result } = renderActions();

      await act(() => result.current.handleConfirmAssign(31, null));

      expect(assignLayoutToCampaign).not.toHaveBeenCalled();
    });

    test('a failed assignment tells the user and keeps the modal open', async () => {
      vi.mocked(assignLayoutToCampaign).mockRejectedValue(new Error('boom'));
      const { result } = renderActions();

      await act(() => result.current.handleConfirmAssign(31, 861001));

      expect(mockNotifyError).toHaveBeenCalledWith('Failed to assign layout');
      expect(closeModal).not.toHaveBeenCalled();
    });
  });

  describe('export', () => {
    // jsdom has no createObjectURL/revokeObjectURL; put back whatever was there before.
    const originalCreate = window.URL.createObjectURL;
    const originalRevoke = window.URL.revokeObjectURL;
    afterEach(() => {
      Object.assign(window.URL, {
        createObjectURL: originalCreate,
        revokeObjectURL: originalRevoke,
      });
    });

    const stubDownload = () => {
      const createObjectURL = vi.fn().mockReturnValue('blob:export-1');
      const revokeObjectURL = vi.fn();
      Object.assign(window.URL, { createObjectURL, revokeObjectURL });
      const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});
      return { createObjectURL, revokeObjectURL, click };
    };

    test('sends the export options and downloads the file under the chosen name', async () => {
      const blob = new Blob(['PK']);
      vi.mocked(exportLayout).mockResolvedValue(blob);
      const { createObjectURL, revokeObjectURL, click } = stubDownload();
      const { result } = renderActions();

      await act(() =>
        result.current.handleExportLayout(861001, {
          includeData: true,
          includeFallback: false,
          fileName: 'lobby-export',
        }),
      );

      expect(exportLayout).toHaveBeenCalledWith(861001, {
        includeData: true,
        includeFallback: false,
      });
      expect(createObjectURL).toHaveBeenCalledWith(blob);
      expect(click).toHaveBeenCalledOnce();
      const link = click.mock.contexts[0] as HTMLAnchorElement;
      expect(link.download).toBe('lobby-export.zip');
      expect(link.href).toBe('blob:export-1');
      // The temporary URL is released so the blob doesn't leak.
      expect(revokeObjectURL).toHaveBeenCalledWith('blob:export-1');
      expect(closeModal).toHaveBeenCalledOnce();
    });

    test('a failed export downloads nothing and keeps the modal open', async () => {
      vi.mocked(exportLayout).mockRejectedValue(new Error('boom'));
      const { click } = stubDownload();
      const { result } = renderActions();

      await act(() =>
        result.current.handleExportLayout(861001, {
          includeData: false,
          includeFallback: false,
          fileName: 'x',
        }),
      );

      expect(click).not.toHaveBeenCalled();
      expect(mockNotifyError).toHaveBeenCalledWith('Failed to export layout');
      expect(closeModal).not.toHaveBeenCalled();
    });
  });

  describe('jump to related pages', () => {
    test.each([
      ['Playlists', 'handleJumpToPlaylists', '/library/playlists'],
      ['Campaigns', 'handleJumpToCampaigns', '/design/campaign'],
      ['Media', 'handleJumpToMedia', '/library/media'],
    ] as const)('jumping to %s opens that page filtered to this layout', (_label, action, path) => {
      const { result } = renderActions();

      act(() => result.current[action](861001));

      expect(mockNavigate).toHaveBeenCalledWith(path, { state: { layoutId: 861001 } });
    });
  });
});
