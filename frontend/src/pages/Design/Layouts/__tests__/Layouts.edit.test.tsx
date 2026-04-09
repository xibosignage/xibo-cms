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

import { screen, fireEvent, act } from '@testing-library/react';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { useLayoutActions } from '../hooks/useLayoutActions';

import {
  SINGLE_LAYOUT,
  defaultLayoutActions,
  mockFetchLayouts,
  mockLayout,
  renderLayoutsPage,
} from './layoutTestUtils';

import { fetchLayouts } from '@/services/layoutsApi';

import { testQueryClient } from '@/setupTests';

// -----------------------------------------------------------------------------
// Module mocks
// -----------------------------------------------------------------------------

// 3rd-party
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

// Services
vi.mock('@/services/folderApi');
vi.mock('@/services/layoutsApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

// Hooks
vi.mock('../hooks/useLayoutActions', () => ({ useLayoutActions: vi.fn() }));
vi.mock('../hooks/useLayoutFilterOptions', () => ({
  useLayoutFilterOptions: vi.fn(() => ({ filterOptions: [], isLoading: false })),
}));
vi.mock('@/hooks/useOwner', () => ({
  useOwner: vi.fn().mockReturnValue({ owner: null, loading: false }),
}));

// UI
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

// EditLayout stub - replaces the real form with a minimal dialog.
// These tests only check that the page opens the modal and updates the table
// row on save. For form field tests see Layouts.edit.form.test.tsx.
vi.mock('../components/EditLayout', () => ({
  default: ({
    isOpen = true,
    onSave,
  }: {
    isOpen?: boolean;
    onSave?: (layout: Record<string, unknown>) => void;
  }) =>
    isOpen ? (
      <div role="dialog" aria-label="Edit Layout">
        <button onClick={() => onSave?.(updatedLayout)}>Save Layout</button>
      </div>
    ) : null,
}));

// =============================================================================
// Fixtures
// =============================================================================

const updatedLayout = { ...mockLayout, layout: 'My Layout - Edited' };

// =============================================================================
// Tests
// =============================================================================

describe('Layouts page - edit', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useLayoutActions).mockReturnValue(defaultLayoutActions());
    mockFetchLayouts(SINGLE_LAYOUT);
  });

  // ---------------------------------------------------------------------------
  // Clicking Edit on a row opens the Edit Layout modal.
  // ---------------------------------------------------------------------------
  test('opens the Edit modal when the Edit action is clicked on a row', async () => {
    await act(async () => {
      renderLayoutsPage();
    });

    await act(async () => {
      fireEvent.click(await screen.findByTitle('Edit'));
    });

    expect(await screen.findByRole('dialog', { name: 'Edit Layout' })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Saving an edit replaces the row's name in the table immediately.
  // ---------------------------------------------------------------------------
  test('saving an edit updates the row name in the table', async () => {
    await act(async () => {
      renderLayoutsPage();
    });

    expect(await screen.findByText(mockLayout.layout)).toBeInTheDocument();

    await act(async () => {
      fireEvent.click(await screen.findByTitle('Edit'));
    });

    // When the data is refreshed, return the updated layout.
	// This happens because handleRefresh asks to reload the data.
    vi.mocked(fetchLayouts).mockResolvedValueOnce({
      rows: [updatedLayout],
      totalCount: 1,
    });

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Save Layout' }));
    });

    expect(await screen.findByText(updatedLayout.layout)).toBeInTheDocument();
  });
});
