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

// The Menu Boards page with its real modals: MenuBoards.restricted.test.tsx
// stubs MenuBoardModals out, so the add flow lives here.

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, test, vi } from 'vitest';

import MenuBoards from '../MenuBoards';

import { mockMenuBoard, renderWithProviders } from './MenuBoardSetup';

import { createMenuBoard, fetchMenuBoard } from '@/services/menuBoardApi';
import { testQueryClient } from '@/setupTests';
import { escapeRegExp } from '@/testUtils/escapeRegExp';
import { PERSONAS, buildCurrentUser } from '@/testUtils/personas';
import type { MenuBoard } from '@/types/menuBoard';

vi.mock('@/services/folderApi');
vi.mock('@/services/menuBoardApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('../hooks/useMenuBoardFilterOptions', () => ({
  useMenuBoardFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/forms/SelectFolder', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

// A content manager given the menu board features (only a super admin has
// them as shipped), working in their own home folder rather than the root, so
// Add is enabled without super-admin rights and the new board lands there.
const HOME_FOLDER_ID = 7;
const menuBoardEditor = buildCurrentUser(PERSONAS.contentManager, {
  homeFolderId: HOME_FOLDER_ID,
  features: { 'menuBoard.view': true, 'menuBoard.add': true, 'menuBoard.modify': true },
});

const fullPermissions = { view: 1, edit: 1, delete: 1, modifyPermissions: 1 };
const breakfast = mockMenuBoard({
  menuId: 1,
  name: 'Breakfast',
  description: 'Served until 11am',
  code: 'BRK_01',
  userPermissions: fullPermissions,
});
const drinks = mockMenuBoard({
  menuId: 2,
  name: 'Drinks',
  description: 'Hot and cold drinks',
  code: 'DRK_01',
  userPermissions: fullPermissions,
});
const created = mockMenuBoard({
  menuId: 3,
  name: 'Lunch Specials',
  description: 'Weekday lunch menu',
  code: 'LUN_01',
  folderId: HOME_FOLDER_ID,
  userPermissions: fullPermissions,
});

const mockMenuBoardRows = (rows: MenuBoard[]) =>
  vi.mocked(fetchMenuBoard).mockResolvedValue({ rows, totalCount: rows.length });

// The accessible name of a table row is its cells' text joined by spaces.
const rowFor = (board: MenuBoard) =>
  new RegExp([board.name, board.description, board.code].map((v) => escapeRegExp(v!)).join('\\s+'));

describe('Listing and adding menu boards on the Menu Boards page', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockMenuBoardRows([breakfast, drinks]);
  });

  test(
    'the table shows each menu board with its name, description and code',
    { tags: ['smoke'] },
    async () => {
      renderWithProviders(<MenuBoards />, { user: menuBoardEditor });

      await screen.findByRole('row', { name: rowFor(breakfast) });
      expect(screen.getByRole('row', { name: rowFor(drinks) })).toBeInTheDocument();
    },
  );

  test(
    'adding a menu board from the page sends the entered values and shows the new board',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup();
      vi.mocked(createMenuBoard).mockResolvedValueOnce(created);

      renderWithProviders(<MenuBoards />, { user: menuBoardEditor });
      await screen.findByRole('row', { name: rowFor(breakfast) });

      await user.click(screen.getByRole('button', { name: 'Add Menu Board' }));
      const dialog = await screen.findByRole('dialog', { name: 'Add Menu Board' });

      // Once saved, the refreshed list includes the new board.
      mockMenuBoardRows([breakfast, drinks, created]);

      await user.type(screen.getByRole('textbox', { name: 'Name' }), 'Lunch Specials');
      await user.type(screen.getByRole('textbox', { name: /^Code/ }), 'LUN_01');
      await user.type(screen.getByRole('textbox', { name: /^Description/ }), 'Weekday lunch menu');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(createMenuBoard).toHaveBeenCalledWith({
          name: 'Lunch Specials',
          description: 'Weekday lunch menu',
          code: 'LUN_01',
          folderId: HOME_FOLDER_ID,
        });
      });
      expect(createMenuBoard).toHaveBeenCalledTimes(1);

      await waitFor(() => expect(dialog).not.toBeInTheDocument());
      await screen.findByRole('row', { name: rowFor(created) });
    },
  );
});
