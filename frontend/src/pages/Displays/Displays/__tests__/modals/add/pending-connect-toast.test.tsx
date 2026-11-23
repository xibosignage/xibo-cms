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

import { act, renderHook } from '@testing-library/react';
import { vi, beforeEach, afterEach, describe, test, expect } from 'vitest';

import {
  readPendingCode,
  usePendingConnectToast,
  writePendingCode,
} from '../../../components/AddDisplay/hooks/usePendingConnect';
import { buildDisplay } from '../../fixtures/display';

import { notify } from '@/components/ui/Notification';
import { fetchConnectStatus, fetchDisplays } from '@/services/displaysApi';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/displaysApi', () => ({
  fetchConnectStatus: vi.fn(),
  fetchDisplays: vi.fn(),
}));
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn() },
}));

// =============================================================================
// Helpers
// =============================================================================

/** The hook checks every 20 seconds. */
const POLL_MS = 20000;

const tick = async () => {
  await act(async () => {
    await vi.advanceTimersByTimeAsync(POLL_MS);
  });
};

// =============================================================================
// Tests
// =============================================================================

// Reports a Player that connects after the Add Display form was closed.
describe('usePendingConnectToast', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.clearAllMocks();
    window.localStorage.clear();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  // An activation code's settings were applied when the Player registered, so it is finished.
  test('says when an activation-code Player connects, and forgets the code', async () => {
    writePendingCode({ code: 'AB12', mode: 'code' });
    vi.mocked(fetchConnectStatus).mockResolvedValue({
      expired: false,
      connected: true,
      displayId: 5,
      display: 'Lobby',
    });
    vi.mocked(fetchDisplays).mockResolvedValue({
      rows: [buildDisplay({ displayId: 5, display: 'Lobby' })],
      totalCount: 1,
    });
    renderHook(() => usePendingConnectToast(true, vi.fn()));

    await tick();

    expect(fetchConnectStatus).toHaveBeenCalledWith('AB12');
    expect(notify.success).toHaveBeenCalledWith('Lobby has connected.', expect.anything());
    expect(readPendingCode()).toBeNull();
  });

  // A manual code carries no settings, so the form still has to save them.
  test('a manual Player that connects keeps its code and offers to finish setting it up', async () => {
    writePendingCode({ code: 'K7QX', mode: 'manual' });
    vi.mocked(fetchConnectStatus).mockResolvedValue({
      expired: false,
      connected: true,
      displayId: 5,
      display: 'Lobby',
    });
    const onFinishSetup = vi.fn();
    renderHook(() => usePendingConnectToast(true, vi.fn(), onFinishSetup));

    await tick();

    expect(notify.success).toHaveBeenCalledWith('Lobby has connected. Finish setting it up.', {
      action: { label: 'Finish setup', onClick: expect.any(Function) },
    });
    expect(readPendingCode()).toEqual({ code: 'K7QX', mode: 'manual', announced: true });
    expect(fetchDisplays).not.toHaveBeenCalled();

    vi.mocked(notify.success).mock.calls[0]?.[1]?.action?.onClick();
    expect(onFinishSetup).toHaveBeenCalled();

    // Announced once; the form takes it from here.
    await tick();
    expect(fetchConnectStatus).toHaveBeenCalledTimes(1);
  });

  // e.g. a page reload, or coming back to Displays, while the code is still live.
  test('a manual Player already announced is not announced again', async () => {
    writePendingCode({ code: 'K7QX', mode: 'manual', announced: true });
    vi.mocked(fetchConnectStatus).mockResolvedValue({
      expired: false,
      connected: true,
      displayId: 5,
      display: 'Lobby',
    });
    renderHook(() => usePendingConnectToast(true, vi.fn(), vi.fn()));

    await tick();
    await tick();

    expect(notify.success).not.toHaveBeenCalled();
    // Still there for the form to resume.
    expect(readPendingCode()).toEqual({ code: 'K7QX', mode: 'manual', announced: true });
    expect(fetchConnectStatus).toHaveBeenCalledTimes(1);
  });

  // An already-registered Player gets no settings, so there is nothing left to finish.
  test('a manual code claimed by an already-registered Player is forgotten', async () => {
    writePendingCode({ code: 'K7QX', mode: 'manual' });
    vi.mocked(fetchConnectStatus).mockResolvedValue({
      expired: false,
      connected: true,
      displayId: 5,
      display: 'Lobby',
      existing: true,
    });
    vi.mocked(fetchDisplays).mockResolvedValue({
      rows: [buildDisplay({ displayId: 5, display: 'Lobby' })],
      totalCount: 1,
    });
    renderHook(() => usePendingConnectToast(true, vi.fn(), vi.fn()));

    await tick();

    expect(notify.success).toHaveBeenCalledWith('Lobby has connected.', expect.anything());
    expect(readPendingCode()).toBeNull();
  });

  test('keeps checking while the Player has not connected', async () => {
    writePendingCode({ code: 'K7QX', mode: 'manual' });
    vi.mocked(fetchConnectStatus).mockResolvedValue({
      expired: false,
      connected: false,
      displayId: null,
      display: null,
    });
    renderHook(() => usePendingConnectToast(true));

    await tick();
    await tick();

    expect(fetchConnectStatus).toHaveBeenCalledTimes(2);
    expect(readPendingCode()).toEqual({ code: 'K7QX', mode: 'manual' });
  });

  // The bell notification stays as the record of an abandoned setup.
  test('forgets an expired code without saying anything', async () => {
    writePendingCode({ code: 'K7QX', mode: 'manual' });
    vi.mocked(fetchConnectStatus).mockResolvedValue({
      expired: true,
      connected: false,
      displayId: null,
      display: null,
    });
    renderHook(() => usePendingConnectToast(true));

    await tick();
    await tick();

    expect(fetchConnectStatus).toHaveBeenCalledTimes(1);
    expect(notify.success).not.toHaveBeenCalled();
    expect(readPendingCode()).toBeNull();
  });

  // e.g. a code left in this browser by an account that can add displays, read by one that can't.
  test('stops and forgets the code when the CMS refuses the check', async () => {
    writePendingCode({ code: 'K7QX', mode: 'manual' });
    vi.mocked(fetchConnectStatus).mockRejectedValue({
      isAxiosError: true,
      response: { status: 403, data: {} },
    });
    renderHook(() => usePendingConnectToast(true));

    await tick();
    await tick();

    expect(fetchConnectStatus).toHaveBeenCalledTimes(1);
    expect(readPendingCode()).toBeNull();
  });

  // An expired session (401) or rate limiting (429) passes, so the code is still worth watching.
  test.each([[500], [401], [429]])('tries again after a %i', async (status) => {
    writePendingCode({ code: 'K7QX', mode: 'manual' });
    vi.mocked(fetchConnectStatus).mockRejectedValue({
      isAxiosError: true,
      response: { status, data: {} },
    });
    renderHook(() => usePendingConnectToast(true));

    await tick();
    await tick();

    expect(fetchConnectStatus).toHaveBeenCalledTimes(2);
    expect(readPendingCode()).toEqual({ code: 'K7QX', mode: 'manual' });
  });

  // The open form watches the code itself; two watchers would announce it twice.
  test('does nothing while disabled', async () => {
    writePendingCode({ code: 'K7QX', mode: 'manual' });
    renderHook(() => usePendingConnectToast(false));

    await tick();

    expect(fetchConnectStatus).not.toHaveBeenCalled();
  });
});
