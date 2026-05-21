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

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import type EditDisplayModalComponent from '../../components/EditDisplayModal';
import { mockDisplay, SINGLE_DISPLAY } from '../fixtures/display';
import { renderDisplaysPage } from '../helpers/renderDisplaysPage';
import { mockFetchDisplays } from '../mocks/displaysApi';

import { fetchDisplays } from '@/services/displaysApi';
import { testQueryClient } from '@/setupTests';
import type { Display } from '@/types/display';

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

// These tests verify page-level behaviour only: does clicking Edit open the
// modal, and does clicking Save refresh the table? Form field logic belongs in
// a separate form test, so the real modal is replaced with a minimal stub that
// acts purely as a behavioural trigger.
//
// The stub calls onSave() to simulate the user completing a save. The page's
// onSave handler ignores the argument — it only calls handleRefresh(), which
// invalidates the React Query cache. The updated row comes from the queued
// mockResolvedValueOnce response, not from anything passed to onSave.
vi.mock('../../components/EditDisplayModal', () => ({
  default: ({ data, onClose, onSave }: React.ComponentProps<typeof EditDisplayModalComponent>) => (
    <div role="dialog" aria-label="Edit Display">
      <button onClick={() => onClose()}>Cancel</button>
      <button onClick={() => onSave(data as Display)}>Save Display</button>
    </div>
  ),
}));

// =============================================================================
// Fixtures
// =============================================================================

const updatedDisplay = { ...mockDisplay, display: 'Test Display - Edited' };

// =============================================================================
// Tests
// =============================================================================

describe('Displays page - edit', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplays(SINGLE_DISPLAY);
  });

  // ---------------------------------------------------------------------------
  // Clicking the Edit quick action on a row opens the Edit modal.
  // ---------------------------------------------------------------------------
  test('opens the Edit modal when the Edit action is clicked on a row', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();

    // Wait for the row to be rendered before looking for the action button.
    await screen.findByText(mockDisplay.display);
    const editButton = await screen.findByRole('button', { name: /edit/i });
    await user.click(editButton);

    expect(await screen.findByRole('dialog', { name: /edit display/i })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // After the user saves their changes, the table should reload and show the
  // updated name. We also verify the page actually asked the server for fresh
  // data rather than just patching the display locally.
  // ---------------------------------------------------------------------------
  test('saving an edit refreshes the table and shows the updated row name', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();

    // Wait for the original row to appear before interacting.
    expect(await screen.findByText(mockDisplay.display)).toBeInTheDocument();

    const editButton = await screen.findByRole('button', { name: /edit/i });
    await user.click(editButton);

    // Queue the updated data so the table gets it when it re-fetches after save.
    vi.mocked(fetchDisplays).mockResolvedValueOnce({
      rows: [updatedDisplay],
      totalCount: 1,
    });

    await user.click(screen.getByRole('button', { name: 'Save Display' }));

    // Await the new name first — this confirms the refetch completed.
    // Only then check the old name is gone, because keepPreviousData keeps the
    // old row visible during the fetch, so a synchronous negative check before
    // this point would fail.
    expect(await screen.findByText(updatedDisplay.display)).toBeInTheDocument();
    expect(screen.queryByText(mockDisplay.display)).not.toBeInTheDocument();
  });
});
