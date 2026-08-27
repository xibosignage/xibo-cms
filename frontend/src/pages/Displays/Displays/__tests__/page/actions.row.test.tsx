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
// Test type: Page integration — row action wiring
// Verifies that clicking each row action in the "More actions" dropdown opens
// the correct modal or triggers the correct side effect.
// Modal content (form fields, API calls, error handling) is tested in the
// dedicated modal test files.
// =============================================================================

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import type React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import DisplaysPage from '../../Displays';
import { mockDisplay, mockUser, queryKeys, SINGLE_DISPLAY } from '../fixtures/display';
import { renderDisplaysPage } from '../helpers/renderDisplaysPage';
import { mockFetchDisplays } from '../mocks/displaysApi';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { User } from '@/types/user';

// =============================================================================
// Module mocks
// =============================================================================

// Spy on navigate — must be declared before any test renders the component.
const mockNavigate = vi.fn();
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal();
  return { ...(actual as object), useNavigate: () => mockNavigate };
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

// Stub complex shared modals — their content is tested elsewhere.
vi.mock('@/components/ui/modals/MoveModal', () => ({
  default: () => <div role="dialog" aria-label="Move Displays" />,
}));
vi.mock('@/components/ui/modals/ShareModal', () => ({
  default: () => <div role="dialog" aria-label="Share Display" />,
}));
vi.mock('@/components/ui/modals/ScheduleEventModal', () => ({
  default: () => <div role="dialog" aria-label="Schedule Event" />,
}));
vi.mock('../../components/ManageGroupMembershipModal', () => ({
  default: () => <div role="dialog" aria-label="Manage Membership" />,
}));
vi.mock('../../components/ManageDisplayModal', () => ({
  default: () => <div role="dialog" aria-label="Manage Display" />,
}));

// =============================================================================
// Helpers
// =============================================================================

// Opens the "More actions" dropdown for the single rendered row.
const openMoreActions = async (user: UserEvent) => {
  await screen.findByText(mockDisplay.display);
  await user.click(screen.getByRole('button', { name: /more actions/i }));
};

// Renders the page with the schedule.add feature so the Schedule action fires.
const renderWithSchedulePermission = () => {
  const scheduleUser: User = {
    ...mockUser,
    features: { ...mockUser.features, 'schedule.add': true },
  };
  testQueryClient.setQueryData(queryKeys.displaysPage, null);
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={scheduleUser}>
        <MemoryRouter>
          <DisplaysPage />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};

// =============================================================================
// Tests
// =============================================================================

describe('Displays page — row action wiring', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplays(SINGLE_DISPLAY);
  });

  // ---------------------------------------------------------------------------
  // Move — opens MoveModal (shared component, content tested in MoveModal tests)
  // ---------------------------------------------------------------------------
  test('clicking Move opens the Move modal', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();

    await openMoreActions(user);
    await user.click(screen.getByRole('button', { name: /^move$/i }));

    await screen.findByRole('dialog', { name: /move displays/i });
  });

  // ---------------------------------------------------------------------------
  // Share — opens ShareModal with title "Share Display"
  // ---------------------------------------------------------------------------
  test('clicking Share opens the Share modal', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();

    await openMoreActions(user);
    await user.click(screen.getByRole('button', { name: /^share$/i }));

    await screen.findByRole('dialog', { name: /share display/i });
  });

  // ---------------------------------------------------------------------------
  // Schedule — requires schedule.add feature; opens ScheduleEventModal
  // ---------------------------------------------------------------------------
  test('clicking Schedule opens the Schedule Event modal', async () => {
    const user = userEvent.setup();
    renderWithSchedulePermission();

    await openMoreActions(user);
    await user.click(screen.getByRole('button', { name: /^schedule$/i }));

    await screen.findByRole('dialog', { name: /schedule event/i });
  });

  // ---------------------------------------------------------------------------
  // Add to Group — opens ManageGroupMembershipModal
  // ---------------------------------------------------------------------------
  test('clicking Add to Group opens the Manage Membership modal', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();

    await openMoreActions(user);
    await user.click(screen.getByRole('button', { name: /add to group/i }));

    await screen.findByRole('dialog', { name: /manage membership/i });
  });

  // ---------------------------------------------------------------------------
  // Manage — navigates to the newer Manage page. "Manage" is also a quick
  // action rendered directly in the row (see DisplaysConfig.tsx), so once the
  // dropdown is open there are two buttons named "Manage" — the menu item is
  // the one with visible text, the quick action is icon-only (aria-label).
  // ---------------------------------------------------------------------------
  test('clicking Manage navigates to the Manage page', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();

    await openMoreActions(user);
    const manageMenuItem = screen
      .getAllByRole('button', { name: /^manage$/i })
      .find((button) => button.textContent?.trim() === 'Manage');
    await user.click(manageMenuItem!);

    expect(mockNavigate).toHaveBeenCalledWith(`/displays/displays/${mockDisplay.displayId}`);
  });

  // ---------------------------------------------------------------------------
  // Manage — the row's quick action button (icon-only) also navigates to the
  // Manage page, without opening the "More actions" dropdown first.
  // ---------------------------------------------------------------------------
  test('clicking the Manage quick action navigates to the Manage page', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();

    await screen.findByText(mockDisplay.display);
    await user.click(screen.getByRole('button', { name: /^manage$/i }));

    expect(mockNavigate).toHaveBeenCalledWith(`/displays/displays/${mockDisplay.displayId}`);
  });

  // ---------------------------------------------------------------------------
  // Full Details — opens the full-detail ManageDisplayModal (Bandwidth,
  // Connectivity, Dependencies, Layouts, Media, Widgets, Faults).
  // ---------------------------------------------------------------------------
  test('clicking Full Details opens the Manage Display modal', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();

    await openMoreActions(user);
    await user.click(screen.getByRole('button', { name: /full details/i }));

    await screen.findByRole('dialog', { name: /manage display/i });
  });

  // ---------------------------------------------------------------------------
  // Scheduled Layouts — navigates to the layouts page filtered by display group
  // ---------------------------------------------------------------------------
  test('clicking Scheduled Layouts navigates to the layouts page', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();

    await openMoreActions(user);
    await user.click(screen.getByRole('button', { name: /scheduled layouts/i }));

    expect(mockNavigate).toHaveBeenCalledWith('/design/layout', {
      state: { activeDisplayGroupId: mockDisplay.displayGroupId },
    });
  });
});
