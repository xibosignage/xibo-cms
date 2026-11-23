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

import { screen } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { EMPTY_DISPLAY_TABLE } from './fixtures/display';
import { renderDisplaysPage } from './helpers/renderDisplaysPage';
import { mockFetchDisplays } from './mocks/displaysApi';

import { addDisplayViaCode, fetchLicenceUsage } from '@/services/displaysApi';
import { testQueryClient } from '@/setupTests';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';

// =============================================================================
// Module mocks
// =============================================================================

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
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn() },
}));
vi.mock('../hooks/useDisplaysFilterOptions', () => ({
  useDisplaysFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// =============================================================================
// Helpers
// =============================================================================

const findAddDialog = () => screen.findByRole('dialog', { name: 'Add Display' });
const queryAddDialog = () => screen.queryByRole('dialog', { name: 'Add Display' });

const openAddModal = async (user: UserEvent) => {
  renderDisplaysPage();
  const addButton = await screen.findByRole('button', { name: /add display/i });
  await user.click(addButton);
  await findAddDialog();
};

// =============================================================================
// Tests
// =============================================================================

// The Add Display wizard ships in every edition, so these cover what CE and Pro both see.
describe('Displays page - add display', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    window.localStorage.clear();
    mockFetchDisplays(EMPTY_DISPLAY_TABLE);
    vi.mocked(fetchLicenceUsage).mockResolvedValue({
      maxLicensed: null,
      currentlyLicensed: 0,
      available: null,
    });
  });

  test('clicking Add Display opens the wizard on the choice of route', async () => {
    const user = userEvent.setup();
    await openAddModal(user);

    expect(screen.getByRole('button', { name: /use activation code/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /manually configure/i })).toBeInTheDocument();
  });

  // ?add=1 comes from the getting-started checklist.
  test('?add=1 opens the wizard on load', async () => {
    renderDisplaysPage({ initialEntries: ['/displays/all-displays?add=1'] });

    await findAddDialog();
    expect(screen.getByRole('button', { name: /use activation code/i })).toBeInTheDocument();
  });

  // ?addCode= comes from a pending-connect notification or a scanned QR code: the customer has
  // already chosen the activation code route, so it opens there with the code filled in.
  test('?addCode= opens the activation code step with the code and name filled in', async () => {
    renderDisplaysPage({
      initialEntries: ['/displays/all-displays?addCode=AB12&displayName=Lobby'],
    });

    await findAddDialog();
    expect(await screen.findByRole('textbox', { name: /activation code/i })).toHaveValue('AB12');
    expect(screen.getByRole('textbox', { name: /display name/i })).toHaveValue('Lobby');
  });

  test.each([['?add=1'], ['?addCode=ABC123']])(
    '%s does nothing for a user who cannot add displays',
    async (query) => {
      const cannotAdd = buildCurrentUser(PERSONAS.user, {
        features: { 'displays.view': true, 'displays.add': false },
      });
      renderDisplaysPage({ initialEntries: [`/displays/all-displays${query}`], user: cannotAdd });

      await screen.findByRole('table');
      expect(queryAddDialog()).not.toBeInTheDocument();
    },
  );

  test('Cancel closes the wizard without calling the API', async () => {
    const user = userEvent.setup();
    await openAddModal(user);

    await user.click(screen.getByRole('button', { name: /cancel/i }));

    expect(queryAddDialog()).not.toBeInTheDocument();
    expect(addDisplayViaCode).not.toHaveBeenCalled();
  });

  // A code typed and abandoned must not be offered again for a different display.
  test('the code field is blank when the wizard is reopened after Cancel', async () => {
    const user = userEvent.setup();
    await openAddModal(user);

    await user.click(screen.getByRole('button', { name: /use activation code/i }));
    await user.type(screen.getByRole('textbox', { name: /activation code/i }), 'LEFTOVER');
    await user.click(screen.getByRole('button', { name: /cancel/i }));

    await user.click(screen.getByRole('button', { name: /add display/i }));
    await findAddDialog();
    await user.click(screen.getByRole('button', { name: /use activation code/i }));

    expect(screen.getByRole('textbox', { name: /activation code/i })).toHaveValue('');
  });
});
