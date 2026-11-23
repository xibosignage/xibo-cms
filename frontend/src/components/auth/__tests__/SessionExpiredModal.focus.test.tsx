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

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';

import { SessionExpiredModal } from '../SessionExpiredModal';

import { useUserContext } from '@/context/UserContext';
import { triggerSessionExpired } from '@/lib/auth-events';
import {
  allRequests,
  installHttpStub,
  respondAlwaysWith,
  respondWith,
  restoreHttpStub,
} from '@/testUtils/httpAdapter';

// Coming back to the tab (window focus, or the page becoming visible) checks
// the session with /user/me, which also refreshes the user's features and
// permissions. The real request is counted at the axios adapter.

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/context/UserContext', () => ({
  useUserContext: vi.fn(),
}));

vi.mock('@/lib/auth-broadcast', () => ({
  listenForLogout: () => () => {},
}));

// ─── Helpers ──────────────────────────────────────────────────────────────────

const updateUser = vi.fn();
let visibility: DocumentVisibilityState = 'visible';
// jsdom defines visibilityState on Document.prototype, so the document has no
// own property to begin with; restoring means removing the override again.
const ownVisibilityState = Object.getOwnPropertyDescriptor(document, 'visibilityState');

const userMeCalls = () => allRequests().filter((r) => r.path === '/json/user/me');

const focusWindow = () => act(() => window.dispatchEvent(new Event('focus')));
const showTab = () => act(() => document.dispatchEvent(new Event('visibilitychange')));
// Let the request finish, so the in-flight guard is no longer what stops a second one.
const settle = () => waitFor(() => expect(updateUser).toHaveBeenCalled());

const renderModal = () =>
  render(
    <QueryClientProvider client={new QueryClient()}>
      <SessionExpiredModal />
    </QueryClientProvider>,
  );

// ─── Tests ────────────────────────────────────────────────────────────────────

beforeEach(() => {
  installHttpStub();
  respondAlwaysWith({ data: { userId: 1, userName: 'admin' } });
  vi.useFakeTimers({ toFake: ['Date'] });
  vi.setSystemTime(new Date('2026-10-27T09:00:00Z'));
  visibility = 'visible';
  Object.defineProperty(document, 'visibilityState', {
    configurable: true,
    get: () => visibility,
  });
  vi.mocked(useUserContext).mockReturnValue({
    user: null,
    isAuthenticated: true,
    logout: vi.fn(),
    updateUser,
  } as unknown as ReturnType<typeof useUserContext>);
});

afterEach(() => {
  restoreHttpStub();
  vi.useRealTimers();
  vi.clearAllMocks();
  if (ownVisibilityState) {
    Object.defineProperty(document, 'visibilityState', ownVisibilityState);
  } else {
    delete (document as { visibilityState?: unknown }).visibilityState;
  }
});

describe('checking the session on return to the tab', () => {
  test('coming back to the tab fetches the current user and updates it', async () => {
    renderModal();

    await focusWindow();
    await settle();

    expect(userMeCalls()).toHaveLength(1);
    expect(userMeCalls()[0]!.method).toBe('GET');
    expect(updateUser).toHaveBeenCalledWith({ userId: 1, userName: 'admin' });
  });

  // Fixed by xibo-cms PR #3945: switching back to a tab fires both focus and
  // visibilitychange. When the first check had already finished, the second one
  // ran too, and the user update it caused set off extra preference saves.
  // https://github.com/xibosignage/xibo-cms/pull/3945
  test('focus and visibilitychange together make one request', async () => {
    renderModal();

    await focusWindow();
    await settle();
    vi.setSystemTime(new Date('2026-10-27T09:00:00.300Z'));
    await showTab();

    expect(userMeCalls()).toHaveLength(1);
  });

  test('coming back again after a second checks again', async () => {
    renderModal();

    await focusWindow();
    await settle();
    vi.setSystemTime(new Date('2026-10-27T09:00:01.100Z'));
    await focusWindow();

    await waitFor(() => expect(userMeCalls()).toHaveLength(2));
  });

  test('a tab that is hidden does not check', async () => {
    visibility = 'hidden';
    renderModal();

    await showTab();

    expect(userMeCalls()).toHaveLength(0);
  });
});

describe('once the session has expired', () => {
  test('coming back checks with HEAD, and closes the dialog when the session is back', async () => {
    renderModal();
    await act(() => triggerSessionExpired());
    expect(await screen.findByText('Session Expired')).toBeInTheDocument();

    respondWith({ status: 200, data: '' });
    await focusWindow();

    await waitFor(() => expect(screen.queryByText('Session Expired')).not.toBeInTheDocument());
    expect(userMeCalls().map((r) => r.method)).toEqual(['HEAD']);
    expect(updateUser).not.toHaveBeenCalled();
  });

  test('stays open while the session is still expired', async () => {
    renderModal();
    await act(() => triggerSessionExpired());
    await screen.findByText('Session Expired');

    respondWith({ status: 401, data: '' });
    await focusWindow();

    await waitFor(() => expect(userMeCalls()).toHaveLength(1));
    expect(screen.getByText('Session Expired')).toBeInTheDocument();
  });
});
