import { renderHook, act } from '@testing-library/react';
import type { TFunction } from 'i18next';
import { describe, expect, it, vi, beforeEach } from 'vitest';

import { useSessionActions } from '../hooks/useSessionActions';
import * as useSessionDataHook from '../hooks/useSessionData';

import { mockSessionsList } from './Setup';

import { useTableState } from '@/hooks/useTableState';
import * as sessionApi from '@/services/sessionApi';

// --- STANDARD MOCKS ---
vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Sessions', path: '/advanced/sessions' }]),
}));

vi.mock('@/hooks/useTableState', () => ({
  useTableState: vi.fn(),
}));

const defaultTableState = {
  pagination: { pageIndex: 0, pageSize: 10 },
  setPagination: vi.fn(),
  sorting: [],
  setSorting: vi.fn(),
  columnVisibility: {},
  setColumnVisibility: vi.fn(),
  globalFilter: '',
  setGlobalFilter: vi.fn(),
  filterInputs: { type: '', lastModified: '' },
  setFilterInputs: vi.fn(),
  isHydrated: true,
};

describe('Sessions Page - Logout Action', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(useTableState).mockReturnValue(defaultTableState as never);

    // Spy on the data hook directly
    vi.spyOn(useSessionDataHook, 'useSessionData').mockReturnValue({
      data: { rows: mockSessionsList, totalCount: 3 },
      isFetching: false,
      isError: false,
      error: null,
    } as never);
  });

  describe('useSessionActions Logic', () => {
    // Guarantee the hook cannot filter this out due to a missing ID property!
    const validSession = {
      ...mockSessionsList[0],
      id: 1,
      sessionId: 1,
      session_id: 1,
      userId: 1,
    };

    it('successfully calls the logout API and clears selection', async () => {
      // Adding spy
      vi.spyOn(sessionApi, 'logoutSession').mockResolvedValue(undefined);

      const handleRefresh = vi.fn();
      const closeModal = vi.fn();
      const setRowSelection = vi.fn();
      const t = vi.fn((key) => key) as unknown as TFunction;

      const { result } = renderHook(() =>
        useSessionActions({
          t,
          handleRefresh,
          closeModal,
          setRowSelection,
        }),
      );

      await act(async () => {
        await result.current.confirmLogout([validSession as never]);
      });

      // Verify the spy was successfully hit!
      expect(sessionApi.logoutSession).toHaveBeenCalled();
      expect(setRowSelection).toHaveBeenCalledWith({});
      expect(handleRefresh).toHaveBeenCalled();
      expect(closeModal).toHaveBeenCalled();
    });

    it('handles API failures and sets the logoutError state', async () => {
      // Craft a fake Axios error that bypasses strict prototype checks
      const fakeError = {
        name: 'Error',
        message: 'Invalid session token.',
        isAxiosError: true,
        response: { data: { message: 'Invalid session token.' } },
      };
      fakeError.isAxiosError = true;
      fakeError.response = { data: { message: 'Invalid session token.' } };

      vi.spyOn(sessionApi, 'logoutSession').mockRejectedValue(fakeError);

      const handleRefresh = vi.fn();
      const closeModal = vi.fn();
      const setRowSelection = vi.fn();
      const t = vi.fn((key) => key) as unknown as TFunction;

      const { result } = renderHook(() =>
        useSessionActions({
          t,
          handleRefresh,
          closeModal,
          setRowSelection,
        }),
      );

      await act(async () => {
        await result.current.confirmLogout([validSession as never]);
      });

      // The early exit is bypassed, the API throws, and the error is extracted!
      expect(result.current.logoutError).toBe('Invalid session token.');
      expect(closeModal).not.toHaveBeenCalled();
    });
  });
});
