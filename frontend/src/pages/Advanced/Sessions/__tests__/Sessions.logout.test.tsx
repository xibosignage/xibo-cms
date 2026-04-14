import { renderHook, act } from '@testing-library/react';
import type { TFunction } from 'i18next';
import { describe, expect, it, vi, beforeEach } from 'vitest';

import { useSessionActions } from '../hooks/useSessionActions';

import * as sessionApi from '@/services/sessionApi';

// Single hoisted mock — no other file re-declares this, so the hook and this
// test share the same vi.fn() references throughout.
vi.mock('@/services/sessionApi', () => ({
  fetchSession: vi.fn(),
  logoutSession: vi.fn(),
}));

const validSession = {
  userId: 1,
  userName: 'alice_admin',
  remoteAddress: '192.168.1.1',
  userAgent: 'Chrome 120',
  isExpired: 0,
  lastAccessed: '2024-01-01T10:00:00Z',
  expiresAt: '2024-01-02T10:00:01Z',
};

describe('Sessions Page - Logout Action', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('useSessionActions Logic', () => {
    it('successfully calls the logout API and clears selection', async () => {
      vi.mocked(sessionApi.logoutSession).mockResolvedValue(undefined);

      const handleRefresh = vi.fn();
      const closeModal = vi.fn();
      const setRowSelection = vi.fn();
      const t = vi.fn((key) => key) as unknown as TFunction;

      const { result } = renderHook(() =>
        useSessionActions({ t, handleRefresh, closeModal, setRowSelection }),
      );

      await act(async () => {
        await result.current.confirmLogout([validSession as never]);
      });

      expect(sessionApi.logoutSession).toHaveBeenCalledWith(validSession.userId);
      expect(setRowSelection).toHaveBeenCalledWith({});
      expect(handleRefresh).toHaveBeenCalled();
      expect(closeModal).toHaveBeenCalled();
    });

    it('handles API failures and sets the logoutError state', async () => {
      const fakeError = {
        isAxiosError: true,
        response: { data: { message: 'Invalid session token.' } },
      };

      vi.mocked(sessionApi.logoutSession).mockRejectedValue(fakeError);

      const handleRefresh = vi.fn();
      const closeModal = vi.fn();
      const setRowSelection = vi.fn();
      const t = vi.fn((key) => key) as unknown as TFunction;

      const { result } = renderHook(() =>
        useSessionActions({ t, handleRefresh, closeModal, setRowSelection }),
      );

      await act(async () => {
        await result.current.confirmLogout([validSession as never]);
      });

      expect(result.current.logoutError).toBe('Invalid session token.');
      expect(closeModal).not.toHaveBeenCalled();
    });
  });
});
