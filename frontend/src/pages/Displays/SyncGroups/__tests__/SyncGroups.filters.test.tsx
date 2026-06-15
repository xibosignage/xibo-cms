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

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { EMPTY_SYNC_GROUP_TABLE, mockSyncGroup, SINGLE_SYNC_GROUP } from './fixtures/syncGroup';
import { renderSyncGroupsPage } from './helpers/renderSyncGroupsPage';
import { mockFetchSyncGroups } from './mocks/syncGroupApi';

import { fetchSyncGroups } from '@/services/syncGroupApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next');

vi.mock('@/services/syncGroupApi');
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

// =============================================================================
// Helpers
// =============================================================================

// Both the Filters button and the search input are disabled while isHydrated
// is false. isHydrated only becomes true after the page preferences load AND
// useSyncGroupData fires its first fetch. Waiting for the row to appear
// guarantees both preconditions are satisfied before we click anything.
const waitForPageReady = () => screen.findByText(mockSyncGroup.name);

// =============================================================================
// Tests — Sync Groups page filters
// =============================================================================

describe('Sync Groups page - filters', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchSyncGroups(SINGLE_SYNC_GROUP);
  });

  test('filter panel is hidden by default', async () => {
    renderSyncGroupsPage();
    await waitForPageReady();

    expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Clicking Filters opens the panel — Reset becomes accessible.
  // ---------------------------------------------------------------------------
  test('clicking Filters opens the panel', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));

    await screen.findByRole('button', { name: /reset/i });
  });

  // ---------------------------------------------------------------------------
  // Clicking Filters a second time collapses the panel — Reset goes back to
  // being aria-hidden and disappears from the accessibility tree.
  // ---------------------------------------------------------------------------
  test('clicking Filters again closes the panel', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();
    await waitForPageReady();

    const filtersButton = screen.getByRole('button', { name: /filters/i });
    await user.click(filtersButton);
    await screen.findByRole('button', { name: /reset/i });

    await user.click(filtersButton);

    await waitFor(() => {
      expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
    });
  });

  test('typing in the search box fetches results with that keyword', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();
    await waitForPageReady();

    await user.type(screen.getByPlaceholderText('Search sync groups...'), 'Alpha');

    await waitFor(
      () => {
        expect(fetchSyncGroups).toHaveBeenCalledWith(expect.objectContaining({ keyword: 'Alpha' }));
      },
      { timeout: 2000 },
    );
  });

  test('typing in the search box resets pagination to page 1', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();
    await waitForPageReady();

    await user.type(screen.getByPlaceholderText('Search sync groups...'), 'screen');

    await waitFor(
      () => {
        expect(fetchSyncGroups).toHaveBeenCalledWith(
          expect.objectContaining({ start: 0, keyword: 'screen' }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('clearing the search box restores the full list', async () => {
    const user = userEvent.setup();
    // Conditional mock: unfiltered returns the row, 'Alpha' returns empty.
    vi.mocked(fetchSyncGroups).mockImplementation(async (opts) =>
      opts?.keyword === 'Alpha' ? EMPTY_SYNC_GROUP_TABLE : SINGLE_SYNC_GROUP,
    );

    renderSyncGroupsPage();
    await waitForPageReady();

    const searchInput = screen.getByPlaceholderText('Search sync groups...') as HTMLInputElement;

    // Type a keyword that matches no rows — the table flips to empty state.
    await user.type(searchInput, 'Alpha');
    expect(await screen.findByText('No results found.')).toBeInTheDocument();

    // Clear via backspaces — the same event path used elsewhere in this file.
    await user.type(searchInput, '{Backspace}{Backspace}{Backspace}{Backspace}{Backspace}');
    expect(searchInput.value).toBe('');

    // The row should reappear once the debounced filter clears.
    expect(await screen.findByText(mockSyncGroup.name)).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Entering a Lead Display ID into the filter triggers a fetchSyncGroups
  // call with that filter value AND resets pagination to page 1.
  // ---------------------------------------------------------------------------
  test('entering a Lead Display ID updates the query and resets to page 1', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    // InputFilter's <label> is not associated to its <input> via htmlFor /
    // aria-*. We instead find the label text and walk to the sibling input
    // inside the same wrapper div.
    const leadDisplayInput = await waitFor(() => {
      const label = screen.getByText('Lead Display ID');
      const input = label.parentElement?.querySelector<HTMLInputElement>('input[type="number"]');
      if (!input) throw new Error('leadDisplayId input not found');
      return input;
    });
    await user.type(leadDisplayInput, '42');

    await waitFor(
      () => {
        expect(fetchSyncGroups).toHaveBeenCalledWith(
          expect.objectContaining({ leadDisplayId: 42, start: 0 }),
        );
      },
      { timeout: 2000 },
    );
  });

  // ---------------------------------------------------------------------------
  // The Name filter is one of three advanced filters (ID, Name, Lead Display
  // ID). Typing into it should also reset pagination to page 1
  // ---------------------------------------------------------------------------
  test('changing a filter value (Name) resets to page 1', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const nameInput = (await screen.findByRole('textbox', {
      name: /^name$/i,
    })) as HTMLInputElement;
    await user.type(nameInput, 'Alpha');

    await waitFor(
      () => {
        expect(fetchSyncGroups).toHaveBeenCalledWith(
          expect.objectContaining({ start: 0, name: 'Alpha' }),
        );
      },
      { timeout: 2000 },
    );
  });

  // ---------------------------------------------------------------------------
  // The Reset button clears every advanced filter input. We type a value
  // into Lead Display ID first, then assert it is cleared after Reset.
  // ---------------------------------------------------------------------------
  test('clicking Reset clears all filter inputs', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const leadDisplayInput = await waitFor(() => {
      const label = screen.getByText('Lead Display ID');
      const input = label.parentElement?.querySelector<HTMLInputElement>('input[type="number"]');
      if (!input) throw new Error('leadDisplayId input not found');
      return input;
    });
    await user.type(leadDisplayInput, '42');

    await waitFor(
      () => {
        expect(fetchSyncGroups).toHaveBeenCalledWith(
          expect.objectContaining({ leadDisplayId: 42 }),
        );
      },
      { timeout: 2000 },
    );

    await user.click(screen.getByRole('button', { name: /reset/i }));

    await waitFor(() => expect(leadDisplayInput.value).toBe(''));
  });

  // ---------------------------------------------------------------------------
  // Reset resets filterInputs state but does NOT close the panel
  // ---------------------------------------------------------------------------
  test('Reset keeps the filter panel open', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await user.click(await screen.findByRole('button', { name: /reset/i }));

    // Panel stays open — Reset is still accessible.
    screen.getByRole('button', { name: /reset/i });
  });
});
