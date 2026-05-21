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

import { useDatasetActions } from '../hooks/useDatasetActions';

import { mockDataset } from './DatasetSetup';

const mockDeleteDataset = vi.fn();
const mockCloneDataset = vi.fn();
vi.mock('@/services/datasetApi', () => ({
  deleteDataset: (...args: unknown[]) => mockDeleteDataset(...args),
  cloneDataset: (...args: unknown[]) => mockCloneDataset(...args),
}));

const mockSelectFolder = vi.fn();
vi.mock('@/services/folderApi', () => ({
  selectFolder: (...args: unknown[]) => mockSelectFolder(...args),
}));

const mockNotifySuccess = vi.fn();
const mockNotifyError = vi.fn();
const mockNotifyWarning = vi.fn();
const mockNotifyInfo = vi.fn();
vi.mock('@/components/ui/Notification', () => ({
  notify: {
    success: (...args: unknown[]) => mockNotifySuccess(...args),
    error: (...args: unknown[]) => mockNotifyError(...args),
    info: (...args: unknown[]) => mockNotifyInfo(...args),
    warning: (...args: unknown[]) => mockNotifyWarning(...args),
  },
}));

describe('useDatasetActions', () => {
  // Bypass $TFunctionBrand strict typing
  const mockT = ((str: string) => str) as unknown as TFunction;
  const mockHandleRefresh = vi.fn();
  const mockCloseModal = vi.fn();
  const mockSetRowSelection = vi.fn();
  const mockSetItemsToMove = vi.fn();

  const renderActions = () =>
    renderHook(() =>
      useDatasetActions({
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

  describe('confirmDelete', () => {
    it('successfully deletes items and triggers callbacks', async () => {
      mockDeleteDataset.mockResolvedValue({});

      const { result } = renderActions();

      const itemsToDelete = [mockDataset({ dataSetId: 1 }), mockDataset({ dataSetId: 2 })];

      await act(async () => {
        await result.current.confirmDelete(itemsToDelete, { deleteData: true });
      });

      expect(mockDeleteDataset).toHaveBeenCalledTimes(2);
      expect(mockDeleteDataset).toHaveBeenCalledWith(1, { deleteData: true });
      expect(mockSetRowSelection).toHaveBeenCalledWith({});
      expect(mockHandleRefresh).toHaveBeenCalled();
      expect(mockCloseModal).toHaveBeenCalled();
      expect(result.current.isDeleting).toBe(false);
    });

    it('handles deletion rejection and sets deleteError', async () => {
      mockDeleteDataset.mockResolvedValueOnce({}).mockRejectedValueOnce(new Error('Network Error'));

      const { result } = renderActions();

      await act(async () => {
        await result.current.confirmDelete(
          [mockDataset({ dataSetId: 1 }), mockDataset({ dataSetId: 2 })],
          { deleteData: false },
        );
      });

      expect(result.current.deleteError).toBe('{{count}} item(s) could not be deleted.');
      expect(mockHandleRefresh).toHaveBeenCalled();
      expect(mockCloseModal).not.toHaveBeenCalled();
    });

    it('resets isDeleting to false after completion', async () => {
      mockDeleteDataset.mockResolvedValue({});

      const { result } = renderActions();

      await act(async () => {
        await result.current.confirmDelete([mockDataset({ dataSetId: 1 })], { deleteData: false });
      });

      expect(result.current.isDeleting).toBe(false);
    });

    it('does not call closeModal when some items fail to delete', async () => {
      mockDeleteDataset.mockRejectedValue(new Error('Failed'));

      const { result } = renderActions();

      await act(async () => {
        await result.current.confirmDelete([mockDataset({ dataSetId: 1 })], { deleteData: false });
      });

      expect(mockCloseModal).not.toHaveBeenCalled();
    });

    it('still calls handleRefresh when some deletions fail', async () => {
      mockDeleteDataset.mockRejectedValue(new Error('Failed'));

      const { result } = renderActions();

      await act(async () => {
        await result.current.confirmDelete([mockDataset({ dataSetId: 1 })], { deleteData: false });
      });

      expect(mockHandleRefresh).toHaveBeenCalled();
    });
  });

  describe('handleConfirmClone', () => {
    it('successfully clones and triggers notifications', async () => {
      mockCloneDataset.mockResolvedValue({});

      const { result } = renderActions();

      await act(async () => {
        await result.current.handleConfirmClone(
          mockDataset({ dataSetId: 99 }),
          'New Name',
          'Desc',
          'CODE',
          false,
        );
      });

      expect(mockCloneDataset).toHaveBeenCalledWith({
        datasetId: 99,
        dataSet: 'New Name',
        description: 'Desc',
        code: 'CODE',
        copyRows: false,
      });

      expect(mockNotifySuccess).toHaveBeenCalledWith('Dataset copied successfully');
      expect(mockNotifyError).not.toHaveBeenCalled();
      expect(mockCloseModal).toHaveBeenCalled();
    });

    it('shows error notification when clone fails', async () => {
      mockCloneDataset.mockRejectedValue(new Error('Network Error'));

      const { result } = renderActions();

      await act(async () => {
        await result.current.handleConfirmClone(
          mockDataset({ dataSetId: 99 }),
          'New Name',
          'Desc',
          'CODE',
          false,
        );
      });

      expect(mockNotifyError).toHaveBeenCalledWith('Failed to copy dataset');
      expect(mockNotifySuccess).not.toHaveBeenCalled();
      expect(mockCloseModal).not.toHaveBeenCalled();
      expect(result.current.isCloning).toBe(false);
    });

    it('does nothing when selectedDataset is null', async () => {
      const { result } = renderActions();

      await act(async () => {
        await result.current.handleConfirmClone(null, 'New Name', 'Desc', 'CODE', false);
      });

      expect(mockCloneDataset).not.toHaveBeenCalled();
    });

    it('resets isCloning to false after completion', async () => {
      mockCloneDataset.mockResolvedValue({});

      const { result } = renderActions();

      await act(async () => {
        await result.current.handleConfirmClone(
          mockDataset({ dataSetId: 1 }),
          'Clone',
          '',
          '',
          false,
        );
      });

      expect(result.current.isCloning).toBe(false);
    });

    it('passes copyRows: true to cloneDataset when flag is set', async () => {
      mockCloneDataset.mockResolvedValue({});

      const { result } = renderActions();

      await act(async () => {
        await result.current.handleConfirmClone(
          mockDataset({ dataSetId: 5 }),
          'CopyWithRows',
          '',
          '',
          true,
        );
      });

      expect(mockCloneDataset).toHaveBeenCalledWith(expect.objectContaining({ copyRows: true }));
    });
  });

  describe('handleConfirmMove', () => {
    it('successfully moves items and triggers success notification', async () => {
      mockSelectFolder.mockResolvedValue({ success: true });

      const { result } = renderActions();

      const itemsToMove = [mockDataset({ dataSetId: 1 }), mockDataset({ dataSetId: 2 })];

      await act(async () => {
        await result.current.handleConfirmMove(itemsToMove, 10);
      });

      expect(mockSelectFolder).toHaveBeenCalledTimes(2);
      expect(mockSelectFolder).toHaveBeenCalledWith({
        folderId: 10,
        targetId: 1,
        targetType: 'dataset',
      });
      expect(mockNotifySuccess).not.toHaveBeenCalled();
      expect(mockHandleRefresh).toHaveBeenCalled();
      expect(mockCloseModal).toHaveBeenCalled();
    });

    it('shows error notification when all moves fail', async () => {
      mockSelectFolder.mockResolvedValue({ success: false });

      const { result } = renderActions();

      await act(async () => {
        await result.current.handleConfirmMove([mockDataset({ dataSetId: 1 })], 10);
      });

      expect(mockNotifyError).toHaveBeenCalledWith('Failed to move items.');
    });

    it('shows partial warning when some moves fail', async () => {
      mockSelectFolder
        .mockResolvedValueOnce({ success: true })
        .mockResolvedValueOnce({ success: false });

      const { result } = renderActions();

      await act(async () => {
        await result.current.handleConfirmMove(
          [mockDataset({ dataSetId: 1 }), mockDataset({ dataSetId: 2 })],
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

    it('calls selectFolder with targetType: "dataset"', async () => {
      mockSelectFolder.mockResolvedValue({ success: true });

      const { result } = renderActions();

      await act(async () => {
        await result.current.handleConfirmMove([mockDataset({ dataSetId: 7 })], 3);
      });

      expect(mockSelectFolder).toHaveBeenCalledWith(
        expect.objectContaining({ targetType: 'dataset' }),
      );
    });
  });
});
