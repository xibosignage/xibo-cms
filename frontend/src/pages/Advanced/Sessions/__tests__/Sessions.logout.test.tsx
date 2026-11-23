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
