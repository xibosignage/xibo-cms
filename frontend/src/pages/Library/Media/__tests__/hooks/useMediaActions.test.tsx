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

import '@/testUtils/notifyMock';

import { renderHook, act } from '@testing-library/react';
import type { TFunction } from 'i18next';
import { describe, it, expect, vi, beforeEach } from 'vitest';

import { useMediaActions } from '../../hooks/useMediaActions';
import { mockEditMedia } from '../mediaTestUtils';

import { mockNotifyError, mockNotifyInfo, mockNotifyWarning } from '@/testUtils/notifyMock';
import { trackSequentialCalls } from '@/testUtils/sequentialMock';

vi.mock('@/services/mediaApi', () => ({
  cloneMedia: vi.fn(),
  deleteMedia: vi.fn(),
  tidyLibrary: vi.fn(),
  updateMedia: vi.fn(),
}));

const mockSelectFolder = vi.fn();
vi.mock('@/services/folderApi', () => ({
  selectFolder: (...args: unknown[]) => mockSelectFolder(...args),
}));

describe('useMediaActions - handleConfirmMove', () => {
  const mockT = ((str: string) => str) as unknown as TFunction;
  const mockHandleRefresh = vi.fn();
  const mockCloseModal = vi.fn();
  const mockSetRowSelection = vi.fn();
  const mockSetItemsToMove = vi.fn();
  const mockSetItemsToDelete = vi.fn();

  const renderActions = () =>
    renderHook(() =>
      useMediaActions({
        t: mockT,
        handleRefresh: mockHandleRefresh,
        closeModal: mockCloseModal,
        setRowSelection: mockSetRowSelection,
        setItemsToMove: mockSetItemsToMove,
        setItemsToDelete: mockSetItemsToDelete,
      }),
    );

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('moves all items and shows success notification', async () => {
    mockSelectFolder.mockResolvedValue({ success: true });
    const { result } = renderActions();

    const itemsToMove = [
      { ...mockEditMedia, mediaId: 1 },
      { ...mockEditMedia, mediaId: 2 },
    ];

    await act(async () => {
      await result.current.handleConfirmMove(itemsToMove, 10);
    });

    expect(mockSelectFolder).toHaveBeenCalledTimes(2);
    expect(mockSelectFolder).toHaveBeenCalledWith({
      folderId: 10,
      targetId: 1,
      targetType: 'library',
    });
    expect(mockNotifyInfo).toHaveBeenCalled();
    expect(mockSetRowSelection).toHaveBeenCalledWith({});
  });

  it('shows error notification when all moves fail', async () => {
    mockSelectFolder.mockResolvedValue({ success: false });
    const { result } = renderActions();

    await act(async () => {
      await result.current.handleConfirmMove([{ ...mockEditMedia, mediaId: 1 }], 10);
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
          { ...mockEditMedia, mediaId: 1 },
          { ...mockEditMedia, mediaId: 2 },
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
      { ...mockEditMedia, mediaId: 1 },
      { ...mockEditMedia, mediaId: 2 },
      { ...mockEditMedia, mediaId: 3 },
    ];

    await act(async () => {
      await result.current.handleConfirmMove(itemsToMove, 10);
    });

    expect(mockSelectFolder).toHaveBeenCalledTimes(3);
    expect(tracker.maxInFlight).toBe(1);
    expect(tracker.callOrder).toEqual([1, 2, 3]);
  });
});
