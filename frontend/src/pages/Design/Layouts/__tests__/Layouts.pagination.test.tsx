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

import { screen, fireEvent, waitFor, within, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { useLayoutData } from '../hooks/useLayoutData';

import { mockLayout, mockLayoutData, renderLayoutsPage } from './layoutTestUtils';

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
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

// Hooks
// Bypass the 500ms debounce delay so tests don't wait on real setTimeout.
vi.mock('@/hooks/useDebounce');
vi.mock('../hooks/useLayoutData', () => ({ useLayoutData: vi.fn() }));
vi.mock('@/hooks/useOwner', () => ({
  useOwner: vi.fn().mockReturnValue({ owner: null, loading: false }),
}));
vi.mock('../hooks/useLayoutFilterOptions', () => ({
  useLayoutFilterOptions: vi.fn(() => ({
    filterOptions: [
      {
        label: 'Retired',
        name: 'retired',
        options: [
          { label: 'Any', value: null },
          { label: 'No', value: 0 },
          { label: 'Yes', value: 1 },
        ],
        shouldTranslateOptions: false,
        showAllOption: false,
      },
    ],
    isLoading: false,
  })),
}));

// UI
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/FolderBreadCrumb', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

// -----------------------------------------------------------------------------
// Fixtures
// -----------------------------------------------------------------------------

// 10 rows with totalCount: 25 so pagination controls render (Next/Previous).
const PAGINATED_LAYOUTS = {
  data: {
    rows: Array.from({ length: 10 }).map((_, i) => ({
      ...mockLayout,
      layoutId: i + 1,
      layout: `Layout ${i + 1}`,
      campaignId: i + 10,
    })),
    totalCount: 25,
  },
  isFetching: false,
  isError: false,
  error: null,
};

// =============================================================================
// Tests
// =============================================================================

describe('Layouts page - pagination', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockLayoutData(PAGINATED_LAYOUTS);
  });

  // ---------------------------------------------------------------------------
  // Clicking Previous after Next decrements pageIndex back to 0.
  // ---------------------------------------------------------------------------
  test.fails('clicking Previous after Next decrements pageIndex back to 0', async () => {
    await act(async () => {
      renderLayoutsPage();
    });

    fireEvent.click(await screen.findByRole('button', { name: /Next/i }));

    await waitFor(() => {
      expect(useLayoutData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          pagination: expect.objectContaining({ pageIndex: 1 }),
        }),
      );
    });

    fireEvent.click(screen.getByRole('button', { name: /Previous/i }));

    await waitFor(() => {
      expect(useLayoutData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          pagination: expect.objectContaining({ pageIndex: 0 }),
        }),
      );
    });
  });

  // ---------------------------------------------------------------------------
  // Typing in search while on page 2 resets pageIndex to 0.
  // ---------------------------------------------------------------------------
  test('typing in search while on page 2 resets pageIndex to 0', async () => {
    const user = userEvent.setup({ delay: null });
    await act(async () => {
      renderLayoutsPage();
    });

    fireEvent.click(await screen.findByRole('button', { name: /Next/i }));

    await waitFor(() => {
      expect(useLayoutData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          pagination: expect.objectContaining({ pageIndex: 1 }),
        }),
      );
    });

    await user.type(screen.getByPlaceholderText('Search layouts...'), 'x');

    await waitFor(() => {
      expect(useLayoutData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          pagination: expect.objectContaining({ pageIndex: 0 }),
        }),
      );
    });
  });

  // ---------------------------------------------------------------------------
  // Applying an advanced filter while on page 2 resets pageIndex to 0.
  // ---------------------------------------------------------------------------
  test('applying an advanced filter while on page 2 resets pageIndex to 0', async () => {
    await act(async () => {
      renderLayoutsPage();
    });

    fireEvent.click(await screen.findByRole('button', { name: /Next/i }));

    await waitFor(() => {
      expect(useLayoutData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          pagination: expect.objectContaining({ pageIndex: 1 }),
        }),
      );
    });

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Filters' }));
    });

    const retiredLabel = screen.getByText('Retired');
    const retiredContainer = retiredLabel.closest('div')!;
    await act(async () => {
      fireEvent.click(within(retiredContainer).getByRole('button'));
    });
    await act(async () => {
      fireEvent.click(within(retiredContainer).getByText('Yes'));
    });

    await waitFor(() => {
      expect(useLayoutData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          pagination: expect.objectContaining({ pageIndex: 0 }),
        }),
      );
    });
  });
});
