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

// Move needs the Folders feature (xibo-cms PR #3875). These tests run as a
// content manager, not a super admin: hasFeature() lets a super admin through
// without reading any features, so only a restricted user can show the gate.

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, test, vi } from 'vitest';

import MenuBoards from '../MenuBoards';

import { mockMenuBoard, renderWithProviders } from './MenuBoardSetup';

import { fetchMenuBoard } from '@/services/menuBoardApi';
import { PERSONAS, buildCurrentUser } from '@/testUtils/personas';
import { openFirstRowMenu, selectAllRowsAndWait } from '@/testUtils/rtl';
import type { MenuBoard } from '@/types/menuBoard';

vi.mock('@/services/folderApi');
vi.mock('@/services/menuBoardApi', () => ({
  fetchMenuBoard: vi.fn(),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('../hooks/useMenuBoardActions', () => ({
  useMenuBoardActions: () => ({
    isDeleting: false,
    deleteError: null,
    setDeleteError: vi.fn(),
    isCloning: false,
    confirmDelete: vi.fn(),
    handleConfirmClone: vi.fn(),
    handleConfirmMove: vi.fn(),
  }),
}));
vi.mock('../hooks/useMenuBoardFilterOptions', () => ({
  useMenuBoardFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));
vi.mock('../components/MenuBoardModals', () => ({ MenuBoardModals: () => null }));
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));

// A content manager has the Folders feature as shipped. Only a super admin
// has the menu board features, so they are switched on here. No settings, like
// the rest of this suite, which renders with no user at all.
const menuBoardEditor = { 'menuBoard.view': true, 'menuBoard.modify': true };
const withFolders = buildCurrentUser(PERSONAS.contentManager, { features: menuBoardEditor });
const withoutFolders = buildCurrentUser(PERSONAS.contentManager, {
  features: { ...menuBoardEditor, 'folder.view': false },
});

// Every menu board can be fully acted on, so a missing Move can only come
// from the user's features.
const fullPermissions = { view: 1, edit: 1, delete: 1, modifyPermissions: 1 };
const menuBoards: MenuBoard[] = [
  mockMenuBoard({ menuId: 1, name: 'Prices', userPermissions: fullPermissions }),
  mockMenuBoard({ menuId: 2, name: 'Stock', userPermissions: fullPermissions }),
];

describe('Menu Boards: Move needs folder access', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(fetchMenuBoard).mockResolvedValue({
      rows: menuBoards,
      totalCount: menuBoards.length,
    });
  });

  test('a user without folder access gets no Move in the row menu', async () => {
    const user = userEvent.setup();
    renderWithProviders(<MenuBoards />, { user: withoutFolders });

    await openFirstRowMenu(user, 'Prices');

    expect(screen.queryByRole('button', { name: /^move$/i })).not.toBeInTheDocument();
  });

  test('a user with folder access gets Move in the row menu', { tags: ['smoke'] }, async () => {
    const user = userEvent.setup();
    renderWithProviders(<MenuBoards />, { user: withFolders });

    await openFirstRowMenu(user, 'Prices');

    expect(screen.getByRole('button', { name: /^move$/i })).toBeInTheDocument();
  });

  test('a user without folder access gets no Move in the bulk menu', async () => {
    const user = userEvent.setup();
    renderWithProviders(<MenuBoards />, { user: withoutFolders });

    await selectAllRowsAndWait(user, 'Prices');

    expect(screen.queryByRole('button', { name: /^move$/i })).not.toBeInTheDocument();
  });

  test('a user with folder access gets Move in the bulk menu', async () => {
    const user = userEvent.setup();
    renderWithProviders(<MenuBoards />, { user: withFolders });

    await selectAllRowsAndWait(user, 'Prices');

    expect(screen.getByRole('button', { name: /^move$/i })).toBeInTheDocument();
  });
});
