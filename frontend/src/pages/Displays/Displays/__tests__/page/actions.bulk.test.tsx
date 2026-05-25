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

// =============================================================================
// Test type: Page integration — bulk action wiring
// Verifies that selecting a row and clicking each bulk action opens the correct
// modal. Modal content (form fields, API calls) is tested in the dedicated
// modal test files.
// =============================================================================

import { screen } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test } from 'vitest';

import { mockDisplay, SINGLE_DISPLAY } from '../fixtures/display';
import { renderDisplaysPage } from '../helpers/renderDisplaysPage';
import { mockFetchDisplays } from '../mocks/displaysApi';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next', () => {
  const t = (key: string) => key;
  return {
    useTranslation: () => ({ t, i18n: { changeLanguage: vi.fn() } }),
    Trans: ({ children }: { children: React.ReactNode }) => children,
  };
});

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

// Stub shared modals — content is tested in their own files.
vi.mock('@/components/ui/modals/MoveModal', () => ({
  default: () => <div role="dialog" aria-label="Move Displays" />,
}));
vi.mock('@/components/ui/modals/ShareModal', () => ({
  default: () => <div role="dialog" aria-label="Share Display" />,
}));
vi.mock('../../components/SetDefaultLayoutModal', () => ({
  default: () => <div role="dialog" aria-label="Set Default Layout" />,
}));
vi.mock('../../components/TriggerWebhookModal', () => ({
  default: () => <div role="dialog" aria-label="Trigger Webhook" />,
}));
vi.mock('../../components/SendCommandModal', () => ({
  default: () => <div role="dialog" aria-label="Send Command" />,
}));
vi.mock('../../components/TransferCmsModal', () => ({
  default: () => <div role="dialog" aria-label="Transfer CMS" />,
}));

// =============================================================================
// Helpers
// =============================================================================

// Selects the single rendered row so the bulk action bar appears.
const selectRow = async (user: UserEvent) => {
  await screen.findByText(mockDisplay.display);
  const checkboxes = screen.getAllByRole('checkbox', { name: /select row/i });
  await user.click(checkboxes[1]!);
};

// =============================================================================
// Tests
// =============================================================================

describe('Displays page — bulk action wiring', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplays(SINGLE_DISPLAY);
  });

  // ---------------------------------------------------------------------------
  // Bulk Move — opens MoveModal
  // Requires folder.view feature (mockUser has it by default via renderDisplaysPage).
  // ---------------------------------------------------------------------------
  test('selecting a row and clicking bulk Move opens the Move modal', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();

    await selectRow(user);
    await user.click(screen.getByRole('button', { name: /^move$/i }));

    await screen.findByRole('dialog', { name: /move displays/i });
  });

  // ---------------------------------------------------------------------------
  // Bulk Share — opens ShareModal
  // ---------------------------------------------------------------------------
  test('selecting a row and clicking bulk Share opens the Share modal', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();

    await selectRow(user);
    await user.click(screen.getByRole('button', { name: /^share$/i }));

    await screen.findByRole('dialog', { name: /share display/i });
  });

  // ---------------------------------------------------------------------------
  // Bulk Set Default Layout — opens SetDefaultLayoutModal
  // ---------------------------------------------------------------------------
  test('selecting a row and clicking Set Default Layout opens the Set Default Layout modal', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();

    await selectRow(user);
    await user.click(screen.getByRole('button', { name: /set default layout/i }));

    await screen.findByRole('dialog', { name: /set default layout/i });
  });

  // ---------------------------------------------------------------------------
  // Bulk Trigger Webhook — opens TriggerWebhookModal with bulk items
  // ---------------------------------------------------------------------------
  test('selecting a row and clicking Trigger a web hook opens the Trigger Webhook modal', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();

    await selectRow(user);
    await user.click(screen.getByRole('button', { name: /trigger a web hook/i }));

    await screen.findByRole('dialog', { name: /trigger webhook/i });
  });

  // ---------------------------------------------------------------------------
  // Bulk Send Command — opens SendCommandModal with bulk items
  // ---------------------------------------------------------------------------
  test('selecting a row and clicking Send Command opens the Send Command modal', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();

    await selectRow(user);
    await user.click(screen.getByRole('button', { name: /^send command$/i }));

    await screen.findByRole('dialog', { name: /^send command$/i });
  });

  // ---------------------------------------------------------------------------
  // Bulk Transfer CMS — opens TransferCmsModal (no display prop, bulk path)
  // ---------------------------------------------------------------------------
  test('selecting a row and clicking Transfer to another CMS opens the Transfer CMS modal', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();

    await selectRow(user);
    await user.click(screen.getByRole('button', { name: /transfer to another cms/i }));

    await screen.findByRole('dialog', { name: /transfer cms/i });
  });
});
