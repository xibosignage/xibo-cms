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

const mockNotifySuccess = vi.fn();
const mockNotifyError = vi.fn();
vi.mock('@/components/ui/Notification', () => ({
  notify: {
    success: (...args: unknown[]) => mockNotifySuccess(...args),
    error: (...args: unknown[]) => mockNotifyError(...args),
    info: vi.fn(),
    warning: vi.fn(),
  },
}));

describe('useDatasetActions', () => {
  // Bypass $TFunctionBrand strict typing
  const mockT = ((str: string) => str) as unknown as TFunction;
  const mockHandleRefresh = vi.fn();
  const mockCloseModal = vi.fn();
  const mockSetRowSelection = vi.fn();
  const mockSetItemsToMove = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
    mockDeleteDataset.mockReset();
    mockCloneDataset.mockReset();
    mockNotifySuccess.mockReset();
    mockNotifyError.mockReset();
  });

  it('initializes with default states', () => {
    const { result } = renderHook(() =>
      useDatasetActions({
        t: mockT,
        handleRefresh: mockHandleRefresh,
        closeModal: mockCloseModal,
        setRowSelection: mockSetRowSelection,
        setItemsToMove: mockSetItemsToMove,
      }),
    );

    expect(result.current.isDeleting).toBe(false);
    expect(result.current.isCloning).toBe(false);
    expect(result.current.deleteError).toBeNull();
  });

  describe('confirmDelete', () => {
    it('successfully deletes items and triggers callbacks', async () => {
      mockDeleteDataset.mockResolvedValue({});

      const { result } = renderHook(() =>
        useDatasetActions({
          t: mockT,
          handleRefresh: mockHandleRefresh,
          closeModal: mockCloseModal,
          setRowSelection: mockSetRowSelection,
          setItemsToMove: mockSetItemsToMove,
        }),
      );

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

      const { result } = renderHook(() =>
        useDatasetActions({
          t: mockT,
          handleRefresh: mockHandleRefresh,
          closeModal: mockCloseModal,
          setRowSelection: mockSetRowSelection,
          setItemsToMove: mockSetItemsToMove,
        }),
      );

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
  });

  describe('handleConfirmClone', () => {
    it('successfully clones and triggers notifications', async () => {
      mockCloneDataset.mockResolvedValue({});

      const { result } = renderHook(() =>
        useDatasetActions({
          t: mockT,
          handleRefresh: mockHandleRefresh,
          closeModal: mockCloseModal,
          setRowSelection: mockSetRowSelection,
          setItemsToMove: mockSetItemsToMove,
        }),
      );

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
  });
});
