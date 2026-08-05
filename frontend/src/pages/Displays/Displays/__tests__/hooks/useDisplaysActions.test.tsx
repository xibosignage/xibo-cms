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

import { useDisplaysActions } from '../../hooks/useDisplaysActions';

import { buildDisplay } from '../fixtures/display';

import { trackSequentialCalls } from '@/testUtils/sequentialMock';

vi.mock('@/services/displaysApi', () => ({
  checkLicence: vi.fn(),
  collectNow: vi.fn(),
  deleteDisplay: vi.fn(),
  moveCms: vi.fn(),
  moveCmsCancel: vi.fn(),
  purgeAll: vi.fn(),
  requestScreenShot: vi.fn(),
  sendCommand: vi.fn(),
  setBandwidthLimitMultiple: vi.fn(),
  setDefaultLayout: vi.fn(),
  toggleDisplayAuthorised: vi.fn(),
  triggerWebhook: vi.fn(),
  wakeOnLan: vi.fn(),
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

describe('useDisplaysActions - handleConfirmMove', () => {
  const mockT = ((str: string) => str) as unknown as TFunction;
  const mockHandleRefresh = vi.fn();
  const mockCloseModal = vi.fn();
  const mockSetRowSelection = vi.fn();

  const renderActions = () =>
    renderHook(
      () =>
        useDisplaysActions({
          t: mockT,
          handleRefresh: mockHandleRefresh,
          closeModal: mockCloseModal,
          setRowSelection: mockSetRowSelection,
        }),
      { wrapper: ({ children }) => <MemoryRouter>{children}</MemoryRouter> },
    );

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('moves all items, shows success notification, and resets row selection', async () => {
    mockSelectFolder.mockResolvedValue({ success: true });
    const { result } = renderActions();

    const itemsToMove = [
      buildDisplay({ displayId: 1, displayGroupId: 1 }),
      buildDisplay({ displayId: 2, displayGroupId: 2 }),
    ];

    await act(async () => {
      await result.current.handleConfirmMove(itemsToMove, 10);
    });

    expect(mockSelectFolder).toHaveBeenCalledTimes(2);
    expect(mockSelectFolder).toHaveBeenCalledWith({
      folderId: 10,
      targetId: 1,
      targetType: 'displaygroup',
    });
    expect(mockNotifyInfo).toHaveBeenCalled();
    expect(mockSetRowSelection).toHaveBeenCalledWith({});
  });

  it('shows error notification when all moves fail', async () => {
    mockSelectFolder.mockResolvedValue({ success: false });
    const { result } = renderActions();

    await act(async () => {
      await result.current.handleConfirmMove(
        [buildDisplay({ displayId: 1, displayGroupId: 1 })],
        10,
      );
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
          buildDisplay({ displayId: 1, displayGroupId: 1 }),
          buildDisplay({ displayId: 2, displayGroupId: 2 }),
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
      buildDisplay({ displayId: 1, displayGroupId: 1 }),
      buildDisplay({ displayId: 2, displayGroupId: 2 }),
      buildDisplay({ displayId: 3, displayGroupId: 3 }),
    ];

    await act(async () => {
      await result.current.handleConfirmMove(itemsToMove, 10);
    });

    expect(mockSelectFolder).toHaveBeenCalledTimes(3);
    expect(tracker.maxInFlight).toBe(1);
    expect(tracker.callOrder).toEqual([1, 2, 3]);
  });
});
