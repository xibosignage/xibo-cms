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
// Test type: Page integration — row and bulk menus for a user who is not a
// Super Admin.
// The menus used to offer actions the server then refused, and hide ones it
// allowed (issue xibo#3988, fixed in xibo-cms PR #3978). Every other Displays test
// runs as a Super Admin, who passes every feature check, so none of them can
// see this. Here each case is a non-admin, and the rows give the user full
// permissions, so a missing action can only come from the user's features.
// =============================================================================

import { screen } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildDisplay, mockUser } from '../fixtures/display';
import { renderDisplaysPage } from '../helpers/renderDisplaysPage';
import { mockFetchDisplays } from '../mocks/displaysApi';

import { testQueryClient } from '@/setupTests';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { User } from '@/types/user';

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
vi.mock('../../hooks/useDisplaysFilterOptions', () => ({
  useDisplaysFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// =============================================================================
// Users and rows
// =============================================================================

// A display manager with every display-changing feature switched off: it can
// only view displays. It also has no Layouts view, Add schedule or Sharing, so
// Scheduled Layouts, Schedule and Share stay out unless a case turns them on.
const displayViewer: User = buildCurrentUser(PERSONAS.displayManager, {
  userId: 30,
  userName: 'display.viewer',
  settings: mockUser.settings,
  features: {
    'displays.add': false,
    'displays.modify': false,
    'displaygroup.view': false,
    'displaygroup.modify': false,
    'command.view': false,
    'folder.view': false,
  },
});

const viewerWith = (...features: string[]): User =>
  buildCurrentUser(displayViewer, {
    features: Object.fromEntries(features.map((f) => [f, true])),
  });

// A display manager as shipped: every display feature, but not a Super Admin.
const displayManager: User = buildCurrentUser(PERSONAS.displayManager, {
  userId: 30,
  userName: 'display.manager',
  settings: mockUser.settings,
});

// licensed: 0, so the authorise action reads "Authorise". clientType stays
// null, so Check Licence never shows.
const fullRow = buildDisplay({
  licensed: 0,
  userPermissions: { view: 1, edit: 1, delete: 1, modifyPermissions: 1 },
});
const secondRow = buildDisplay({
  displayId: 2,
  display: 'Second Display',
  licensed: 0,
  userPermissions: { view: 1, edit: 1, delete: 1, modifyPermissions: 1 },
});

// Every row action that depends on what the user may do.
const GATED_ROW_ACTIONS = [
  'Edit',
  'Move',
  'Share',
  'Add to Group',
  'Authorise',
  'Assign Layouts',
  'Assign Files',
  'Collect Now',
  'Trigger a web hook',
  'Wake on LAN',
  'Send Command',
  'Default Layout',
  'Purge All Media',
  'Transfer to another CMS',
  'Delete',
  'Schedule',
  'Scheduled Layouts',
];

// Every bulk action that depends on what the user may do.
const GATED_BULK_ACTIONS = [
  'Toggle Authorise',
  'Set Default Layout',
  'Move',
  'Collect Now',
  'Trigger a web hook',
  'Share',
  'Send Command',
  'Transfer to another CMS',
  'Set Bandwidth',
  'Delete Selected',
];

const MODIFY_ROW = [
  'Edit',
  'Add to Group',
  'Wake on LAN',
  'Default Layout',
  'Transfer to another CMS',
  'Delete',
];
const GROUP_MODIFY_ROW = ['Assign Layouts', 'Assign Files', 'Trigger a web hook', 'Send Command'];
const MODIFY_BULK = [
  'Set Default Layout',
  'Transfer to another CMS',
  'Set Bandwidth',
  'Delete Selected',
];

// =============================================================================
// Helpers
// =============================================================================

const exactly = (label: string) =>
  new RegExp(`^${label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`, 'i');

// Edit is both a quick action and a menu item, so actions are counted with
// queryAllByRole rather than read with getByRole.
const actionsShown = (labels: string[]) =>
  labels.filter((label) => screen.queryAllByRole('button', { name: exactly(label) }).length > 0);

// Opens the row's "More actions" menu and waits for Manage, which every user
// gets, so an absent action means absent, not "the menu hasn't opened yet".
const openRowMenu = async (user: UserEvent) => {
  await screen.findByText(fullRow.display);
  await user.click(screen.getByRole('button', { name: /more actions/i }));
  await screen.findByRole('button', { name: /^manage$/i });
};

// Ticks both rows and waits for Request Screen Shot, which every user gets.
const selectBothRows = async (user: UserEvent) => {
  await screen.findByText(secondRow.display);
  for (const checkbox of screen.getAllByRole('checkbox', { name: /select row/i })) {
    await user.click(checkbox);
  }
  await screen.findByRole('button', { name: /^request screen shot$/i });
};

// =============================================================================
// Tests
// =============================================================================

describe('Displays page — row menu for a user who is not a Super Admin', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplays({ rows: [fullRow], totalCount: 1 });
  });

  test('a user who can only view displays gets only Manage and Request Screenshot', async () => {
    const user = userEvent.setup();
    renderDisplaysPage({ user: displayViewer });

    await openRowMenu(user);

    expect(screen.getByRole('button', { name: /^request screenshot$/i })).toBeInTheDocument();
    expect(actionsShown(GATED_ROW_ACTIONS)).toEqual([]);
  });

  test.each([
    { feature: 'displays.modify', features: ['displays.modify'], added: MODIFY_ROW },
    { feature: 'displays.add', features: ['displays.add'], added: ['Authorise'] },
    { feature: 'displaygroup.modify', features: ['displaygroup.modify'], added: GROUP_MODIFY_ROW },
    { feature: 'displaygroup.view', features: ['displaygroup.view'], added: ['Collect Now'] },
    { feature: 'command.view', features: ['command.view'], added: ['Send Command'] },
    {
      feature: 'displaygroup.modify + folder.view',
      features: ['displaygroup.modify', 'folder.view'],
      added: ['Move', ...GROUP_MODIFY_ROW],
    },
    {
      feature: 'displays.modify + user.sharing',
      features: ['displays.modify', 'user.sharing'],
      added: ['Share', ...MODIFY_ROW],
    },
    { feature: 'schedule.add', features: ['schedule.add'], added: ['Schedule'] },
    {
      feature: 'displays.modify + layout.view',
      features: ['displays.modify', 'layout.view'],
      added: ['Scheduled Layouts', ...MODIFY_ROW],
    },
  ])(
    'each display feature adds its own row actions and no others: $feature',
    async ({ features, added }) => {
      const user = userEvent.setup();
      renderDisplaysPage({ user: viewerWith(...features) });

      await openRowMenu(user);

      expect(actionsShown(GATED_ROW_ACTIONS).sort()).toEqual([...added].sort());
    },
  );

  test('a user who can edit displays gets no Edit or Delete on a display they may only view', async () => {
    mockFetchDisplays({
      rows: [
        { ...fullRow, userPermissions: { view: 1, edit: 0, delete: 0, modifyPermissions: 0 } },
      ],
      totalCount: 1,
    });
    const user = userEvent.setup();
    renderDisplaysPage({ user: viewerWith('displays.modify') });

    await openRowMenu(user);

    expect(actionsShown(['Edit', 'Delete'])).toEqual([]);
  });

  test('a user with every display feature still gets no Purge All Media', async () => {
    const user = userEvent.setup();
    renderDisplaysPage({ user: displayManager });

    await openRowMenu(user);

    // Control: this user does get the other display actions.
    expect(actionsShown(['Wake on LAN'])).toEqual(['Wake on LAN']);
    expect(screen.queryByRole('button', { name: /^purge all media$/i })).not.toBeInTheDocument();
  });
});

describe('Displays page — bulk menu for a user who is not a Super Admin', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplays({ rows: [fullRow, secondRow], totalCount: 2 });
  });

  test('a user who can only view displays gets no bulk changes', async () => {
    const user = userEvent.setup();
    renderDisplaysPage({ user: displayViewer });

    await selectBothRows(user);

    expect(actionsShown(GATED_BULK_ACTIONS)).toEqual([]);
  });

  test.each([
    { feature: 'displays.modify', features: ['displays.modify'], added: MODIFY_BULK },
    { feature: 'displays.add', features: ['displays.add'], added: ['Toggle Authorise'] },
    {
      feature: 'displaygroup.modify',
      features: ['displaygroup.modify'],
      added: ['Trigger a web hook', 'Send Command'],
    },
    { feature: 'displaygroup.view', features: ['displaygroup.view'], added: ['Collect Now'] },
    { feature: 'command.view', features: ['command.view'], added: ['Send Command'] },
    {
      feature: 'displaygroup.modify + folder.view',
      features: ['displaygroup.modify', 'folder.view'],
      added: ['Move', 'Trigger a web hook', 'Send Command'],
    },
    {
      feature: 'displays.modify + user.sharing',
      features: ['displays.modify', 'user.sharing'],
      added: ['Share', ...MODIFY_BULK],
    },
  ])('each display feature adds its own bulk actions: $feature', async ({ features, added }) => {
    const user = userEvent.setup();
    renderDisplaysPage({ user: viewerWith(...features) });

    await selectBothRows(user);

    expect(actionsShown(GATED_BULK_ACTIONS).sort()).toEqual([...added].sort());
  });
});
