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

// =============================================================================
// Test type: Page test
// A display is shared through its display group, so every Share on the
// Displays page must hand the Share modal display group ids, never display
// ids. Before the fix for xibo#3976, Share Selected sent display ids, so
// permissions were saved on whichever display group had that number.
//
// The shared fixture uses 1 for both ids, which would let the wrong field pass,
// so the displays here keep them apart (display 1 is display group 501).
// ShareModal is stubbed to show what it was given; its own behaviour is tested
// elsewhere.
// =============================================================================

// The shared notify mock must be imported before the page so its notify is the mock.
// eslint-disable-next-line import/order -- must load before the page
import { mockNotifyInfo, mockNotifyWarning } from '@/testUtils/notifyMock';

import { act, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockDisplay } from '../fixtures/display';
import { renderDisplaysPage } from '../helpers/renderDisplaysPage';
import { mockFetchDisplays } from '../mocks/displaysApi';

import { testQueryClient } from '@/setupTests';
import { selectAllRowsAndWait } from '@/testUtils/rtl';
import { sharedWith } from '@/testUtils/shareModalStub';
import type { Display } from '@/types/display';

vi.mock('@/services/displaysApi');
vi.mock('@/services/displayGroupApi', () => ({
  fetchDisplayGroups: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  deleteDisplayGroup: vi.fn(),
}));
vi.mock('@/services/displayProfileApi', () => ({
  fetchDisplayProfile: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  fetchDisplayProfileById: vi.fn().mockResolvedValue(null),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn().mockResolvedValue([]),
}));
vi.mock('@/services/folderApi', () => ({
  fetchFolderById: vi.fn().mockResolvedValue({ id: 1, text: 'Root' }),
  fetchFolderTree: vi.fn().mockResolvedValue([]),
  searchFolders: vi.fn().mockResolvedValue([]),
  fetchContextButtons: vi.fn().mockResolvedValue({ create: true }),
  selectFolder: vi.fn().mockResolvedValue({ success: true }),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('../../hooks/useDisplaysFilterOptions', () => ({
  useDisplaysFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// Shows what the Share modal was opened for.
vi.mock('@/components/ui/modals/ShareModal', async () => ({
  default: (await import('@/testUtils/shareModalStub')).ShareModalStub,
}));

const displayA: Display = { ...mockDisplay, displayId: 1, displayGroupId: 501, display: 'Lobby' };
const displayB: Display = { ...mockDisplay, displayId: 2, displayGroupId: 502, display: 'Cafe' };

const showDisplays = (...rows: Display[]) =>
  mockFetchDisplays({ rows, totalCount: rows.length } as never);

const notShareable = (display: Display): Display => ({
  ...display,
  userPermissions: { ...display.userPermissions!, modifyPermissions: 0 },
});

beforeEach(() => {
  testQueryClient.clear();
  vi.clearAllMocks();
});

describe('Displays page - Share sends display group ids', () => {
  test('row Share opens Share for the display’s display group', async () => {
    const user = userEvent.setup();
    showDisplays(displayA, displayB);
    renderDisplaysPage();

    await screen.findByText('Lobby');
    await user.click(screen.getAllByRole('button', { name: 'More actions' })[0]!);
    await user.click(await screen.findByRole('button', { name: 'Share' }));

    expect(await sharedWith()).toEqual({ ids: 501, type: 'displayGroup', title: 'Share Display' });
  });

  test('Share Selected opens Share for every selected display’s display group', async () => {
    const user = userEvent.setup();
    showDisplays(displayA, displayB);
    renderDisplaysPage();

    await selectAllRowsAndWait(user, 'Lobby');
    await user.click(screen.getByRole('button', { name: 'Share' }));

    expect(await sharedWith()).toEqual({
      ids: [501, 502],
      type: 'displayGroup',
      title: 'Share Display',
    });
  });

  test('Share Selected leaves out displays the user cannot share, and says how many', async () => {
    const user = userEvent.setup();
    showDisplays(displayA, notShareable(displayB));
    renderDisplaysPage();

    await selectAllRowsAndWait(user, 'Lobby');
    await user.click(screen.getByRole('button', { name: 'Share' }));

    expect(await sharedWith()).toEqual({
      ids: [501],
      type: 'displayGroup',
      title: 'Share Display',
    });
    expect(mockNotifyInfo).toHaveBeenCalledTimes(1);
    expect(mockNotifyInfo.mock.calls[0]![0]).toMatch(/^1 items were skipped/);
  });

  test('Share Selected with no display the user can share opens nothing and warns', async () => {
    const user = userEvent.setup();
    showDisplays(notShareable(displayA), notShareable(displayB));
    renderDisplaysPage();

    await selectAllRowsAndWait(user, 'Lobby');
    await user.click(screen.getByRole('button', { name: 'Share' }));

    expect(mockNotifyWarning).toHaveBeenCalledTimes(1);
    expect(mockNotifyWarning.mock.calls[0]![0]).toBe(
      'You do not have permission to share any of the selected items.',
    );
    expect(mockNotifyInfo).not.toHaveBeenCalled();
    // Let anything the click started finish, so a Share that opens late still counts.
    await act(async () => new Promise((settled) => setTimeout(settled, 0)));
    expect(screen.queryByRole('dialog', { name: 'Share' })).not.toBeInTheDocument();
  });
});
