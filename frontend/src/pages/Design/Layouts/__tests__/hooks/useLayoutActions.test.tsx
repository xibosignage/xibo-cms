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

import { renderHook, act } from '@testing-library/react';
import type { TFunction } from 'i18next';
import { MemoryRouter } from 'react-router-dom';
import { describe, it, expect, vi, beforeEach } from 'vitest';

import { useLayoutActions } from '../../hooks/useLayoutActions';
import { mockLayout } from '../layoutTestUtils';

import { trackSequentialCalls } from '@/testUtils/sequentialMock';

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

const mockSelectFolder = vi.fn();
vi.mock('@/services/folderApi', () => ({
  selectFolder: (...args: unknown[]) => mockSelectFolder(...args),
}));

const mockNotifySuccess = vi.fn();
const mockNotifyError = vi.fn();
const mockNotifyInfo = vi.fn();
const mockNotifyWarning = vi.fn();
vi.mock('@/components/ui/Notification', () => ({
  notify: {
    success: (...args: unknown[]) => mockNotifySuccess(...args),
    error: (...args: unknown[]) => mockNotifyError(...args),
    info: (...args: unknown[]) => mockNotifyInfo(...args),
    warning: (...args: unknown[]) => mockNotifyWarning(...args),
  },
}));

describe('useLayoutActions - handleConfirmMove', () => {
  const mockT = ((str: string) => str) as unknown as TFunction;
  const mockHandleRefresh = vi.fn();
  const mockCloseModal = vi.fn();
  const mockSetRowSelection = vi.fn();
  const mockSetItemsToMove = vi.fn();

  const renderActions = () =>
    renderHook(
      () =>
        useLayoutActions({
          t: mockT,
          handleRefresh: mockHandleRefresh,
          closeModal: mockCloseModal,
          setRowSelection: mockSetRowSelection,
          setItemsToMove: mockSetItemsToMove,
          timezone: 'UTC',
        }),
      { wrapper: ({ children }) => <MemoryRouter>{children}</MemoryRouter> },
    );

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('moves all items and shows success notification', async () => {
    mockSelectFolder.mockResolvedValue({ success: true });
    const { result } = renderActions();

    const itemsToMove = [
      { ...mockLayout, campaignId: 1 },
      { ...mockLayout, campaignId: 2 },
    ];

    await act(async () => {
      await result.current.handleConfirmMove(itemsToMove, 10);
    });

    expect(mockSelectFolder).toHaveBeenCalledTimes(2);
    expect(mockSelectFolder).toHaveBeenCalledWith({
      folderId: 10,
      targetId: 1,
      targetType: 'campaign',
    });
    expect(mockNotifyInfo).toHaveBeenCalled();
    expect(mockSetRowSelection).toHaveBeenCalledWith({});
  });

  it('shows error notification when all moves fail', async () => {
    mockSelectFolder.mockResolvedValue({ success: false });
    const { result } = renderActions();

    await act(async () => {
      await result.current.handleConfirmMove([{ ...mockLayout, campaignId: 1 }], 10);
    });

    expect(mockNotifyError).toHaveBeenCalledWith('Failed to move items.');
  });

  it('shows warning notification when some moves fail', async () => {
    mockSelectFolder
      .mockResolvedValueOnce({ success: true })
      .mockResolvedValueOnce({ success: false });
    const { result } = renderActions();

    await act(async () => {
      await result.current.handleConfirmMove(
        [
          { ...mockLayout, campaignId: 1 },
          { ...mockLayout, campaignId: 2 },
        ],
        10,
      );
    });

    expect(mockNotifyWarning).toHaveBeenCalled();
  });

  it('does nothing when itemsToMove is empty', async () => {
    const { result } = renderActions();

    await act(async () => {
      await result.current.handleConfirmMove([], 10);
    });

    expect(mockSelectFolder).not.toHaveBeenCalled();
  });

  // Regression test — see trackSequentialCalls in testUtils/sequentialMock.ts
  // for why sequencing (not just call count) is what's being proven here.
  it('sends move requests sequentially, not concurrently', async () => {
    const tracker = trackSequentialCalls(mockSelectFolder);

    const { result } = renderActions();
    const itemsToMove = [
      { ...mockLayout, campaignId: 1 },
      { ...mockLayout, campaignId: 2 },
      { ...mockLayout, campaignId: 3 },
    ];

    await act(async () => {
      await result.current.handleConfirmMove(itemsToMove, 10);
    });

    expect(mockSelectFolder).toHaveBeenCalledTimes(3);
    expect(tracker.maxInFlight).toBe(1);
    expect(tracker.callOrder).toEqual([1, 2, 3]);
  });
});
