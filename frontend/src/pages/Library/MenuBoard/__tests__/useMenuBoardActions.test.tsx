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
import { describe, it, expect, vi, beforeEach } from 'vitest';

import { useMenuBoardActions } from '../hooks/useMenuBoardActions';

import { mockMenuBoard } from './MenuBoardSetup';

const mockDeleteMenuBoard = vi.fn();
const mockCopyMenuBoard = vi.fn();

vi.mock('@/services/menuBoardApi', () => ({
  deleteMenuBoard: (...args: unknown[]) => mockDeleteMenuBoard(...args),
  copyMenuBoard: (...args: unknown[]) => mockCopyMenuBoard(...args),
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

describe('useMenuBoardActions', () => {
  const mockT = ((str: string) => str) as unknown as TFunction;
  const mockHandleRefresh = vi.fn();
  const mockCloseModal = vi.fn();
  const mockSetRowSelection = vi.fn();
  const mockSetItemsToMove = vi.fn();

  const renderActions = () =>
    renderHook(() =>
      useMenuBoardActions({
        t: mockT,
        handleRefresh: mockHandleRefresh,
        closeModal: mockCloseModal,
        setRowSelection: mockSetRowSelection,
        setItemsToMove: mockSetItemsToMove,
      }),
    );

  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('initializes with default states', () => {
    const { result } = renderActions();

    expect(result.current.isDeleting).toBe(false);
    expect(result.current.isCloning).toBe(false);
    expect(result.current.deleteError).toBeNull();
  });

  // Scenarios 26, 30 — successful delete
  describe('confirmDelete', () => {
    it('deletes all selected boards and triggers callbacks', async () => {
      mockDeleteMenuBoard.mockResolvedValue({});
      const { result } = renderActions();

      const items = [mockMenuBoard({ menuId: 1 }), mockMenuBoard({ menuId: 2 })];

      await act(async () => {
        await result.current.confirmDelete(items);
      });

      expect(mockDeleteMenuBoard).toHaveBeenCalledTimes(2);
      expect(mockDeleteMenuBoard).toHaveBeenCalledWith(1);
      expect(mockDeleteMenuBoard).toHaveBeenCalledWith(2);
      expect(mockSetRowSelection).toHaveBeenCalledWith({});
      expect(mockHandleRefresh).toHaveBeenCalled();
      expect(mockCloseModal).toHaveBeenCalled();
      expect(result.current.isDeleting).toBe(false);
    });

    it('sets deleteError and does not close modal when a deletion fails', async () => {
      mockDeleteMenuBoard
        .mockResolvedValueOnce({})
        .mockRejectedValueOnce(new Error('Network Error'));
      const { result } = renderActions();

      await act(async () => {
        await result.current.confirmDelete([
          mockMenuBoard({ menuId: 1 }),
          mockMenuBoard({ menuId: 2 }),
        ]);
      });

      expect(result.current.deleteError).toBeTruthy();
      expect(mockHandleRefresh).toHaveBeenCalled();
      expect(mockCloseModal).not.toHaveBeenCalled();
    });

    it('does nothing when itemsToDelete is empty', async () => {
      const { result } = renderActions();

      await act(async () => {
        await result.current.confirmDelete([]);
      });

      expect(mockDeleteMenuBoard).not.toHaveBeenCalled();
    });

    it('exposes setDeleteError to clear the error externally', () => {
      const { result } = renderActions();

      act(() => {
        result.current.setDeleteError('some error');
      });

      expect(result.current.deleteError).toBe('some error');

      act(() => {
        result.current.setDeleteError(null);
      });

      expect(result.current.deleteError).toBeNull();
    });
  });

  // Scenario 141 — copy via handleConfirmClone
  describe('handleConfirmClone', () => {
    it('calls copyMenuBoard and shows success notification', async () => {
      mockCopyMenuBoard.mockResolvedValue(mockMenuBoard({ menuId: 99, name: 'Copy' }));
      const { result } = renderActions();

      await act(async () => {
        await result.current.handleConfirmClone(mockMenuBoard({ menuId: 5 }), 'Copy', 'desc', 'CP');
      });

      expect(mockCopyMenuBoard).toHaveBeenCalledWith({
        menuBoardId: 5,
        name: 'Copy',
        description: 'desc',
        code: 'CP',
      });
      expect(mockNotifySuccess).toHaveBeenCalledWith('Menu Board copied successfully');
      expect(mockHandleRefresh).toHaveBeenCalled();
      expect(mockCloseModal).toHaveBeenCalled();
      expect(result.current.isCloning).toBe(false);
    });

    it('shows error notification when copy fails', async () => {
      mockCopyMenuBoard.mockRejectedValue(new Error('Network Error'));
      const { result } = renderActions();

      await act(async () => {
        await result.current.handleConfirmClone(mockMenuBoard({ menuId: 5 }), 'Copy', '', '');
      });

      expect(mockNotifyError).toHaveBeenCalledWith('Failed to copy Menu Board');
      expect(mockNotifySuccess).not.toHaveBeenCalled();
      expect(mockCloseModal).not.toHaveBeenCalled();
      expect(result.current.isCloning).toBe(false);
    });

    it('does nothing when selectedMenuBoard is null', async () => {
      const { result } = renderActions();

      await act(async () => {
        await result.current.handleConfirmClone(null, 'Copy', '', '');
      });

      expect(mockCopyMenuBoard).not.toHaveBeenCalled();
    });
  });

  // Scenario 31 — move to folder
  describe('handleConfirmMove', () => {
    it('moves all boards and shows info notification on full success', async () => {
      mockSelectFolder.mockResolvedValue({ success: true });
      const { result } = renderActions();

      const items = [mockMenuBoard({ menuId: 1 }), mockMenuBoard({ menuId: 2 })];

      await act(async () => {
        await result.current.handleConfirmMove(items, 10);
      });

      expect(mockSelectFolder).toHaveBeenCalledTimes(2);
      expect(mockSelectFolder).toHaveBeenCalledWith({
        folderId: 10,
        targetId: 1,
        targetType: 'menuboard',
      });
      expect(mockNotifyInfo).toHaveBeenCalled();
      expect(mockHandleRefresh).toHaveBeenCalled();
      expect(mockCloseModal).toHaveBeenCalled();
    });

    it('shows error notification when all moves fail', async () => {
      mockSelectFolder.mockResolvedValue({ success: false });
      const { result } = renderActions();

      await act(async () => {
        await result.current.handleConfirmMove([mockMenuBoard({ menuId: 1 })], 10);
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
          [mockMenuBoard({ menuId: 1 }), mockMenuBoard({ menuId: 2 })],
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
  });
});
