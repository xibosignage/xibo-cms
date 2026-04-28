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

import { useTemplateData } from '../hooks/useTemplatesData';

import { mockTemplate, renderTemplatesPage } from './templateTestUtils';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

// 3rd-party
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

// Services
vi.mock('@/services/folderApi', () => ({
  fetchFolderById: vi.fn().mockResolvedValue({ id: 1, text: 'Root' }),
  fetchFolderTree: vi.fn().mockResolvedValue([]),
  searchFolders: vi.fn().mockResolvedValue([]),
  fetchContextButtons: vi.fn().mockResolvedValue({ create: true }),
  selectFolder: vi.fn().mockResolvedValue({ success: true }),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

// Hooks
// Bypass the 500ms debounce delay so tests don't wait on real setTimeout.
vi.mock('@/hooks/useDebounce');
vi.mock('../hooks/useTemplatesData', () => ({ useTemplateData: vi.fn() }));
vi.mock('../hooks/useTemplateFilterOptions', () => ({
  useTemplateFilterOptions: vi.fn(() => ({
    filterOptions: [
      {
        label: 'Published Status',
        name: 'publishedStatusId',
        options: [
          { label: 'Any', value: null },
          { label: 'Published', value: '1' },
          { label: 'Draft', value: '2' },
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

// =============================================================================
// Fixtures
// =============================================================================

// 10 rows with totalCount: 25 so pagination controls render (Next/Previous).
const PAGINATED_TEMPLATES = {
  rows: Array.from({ length: 10 }).map((_, i) => ({
    ...mockTemplate,
    layoutId: i + 1,
    layout: `Template ${i + 1}`,
    campaignId: i + 10,
  })),
  totalCount: 25,
};

type UseTemplateDataReturn = ReturnType<typeof useTemplateData>;

const mockTemplatesData = (rawData: { rows: unknown[]; totalCount: number }) => {
  vi.mocked(useTemplateData).mockReturnValue({
    data: rawData,
    isFetching: false,
    isError: false,
    error: null,
  } as UseTemplateDataReturn);
};

// =============================================================================
// Tests
// =============================================================================

describe('Templates page - pagination', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockTemplatesData(PAGINATED_TEMPLATES);
  });

  // ---------------------------------------------------------------------------
  // Typing in search while on page 2 resets pageIndex to 0.
  // ---------------------------------------------------------------------------
  test('typing in search while on page 2 resets pageIndex to 0', async () => {
    const user = userEvent.setup({ delay: null });
    await act(async () => {
      renderTemplatesPage();
    });

    fireEvent.click(await screen.findByRole('button', { name: /Next/i }));

    await waitFor(() => {
      expect(useTemplateData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          pagination: expect.objectContaining({ pageIndex: 1 }),
        }),
      );
    });

    await user.type(screen.getByPlaceholderText('Search template...'), 'x');

    await waitFor(() => {
      expect(useTemplateData).toHaveBeenLastCalledWith(
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
      renderTemplatesPage();
    });

    fireEvent.click(await screen.findByRole('button', { name: /Next/i }));

    await waitFor(() => {
      expect(useTemplateData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          pagination: expect.objectContaining({ pageIndex: 1 }),
        }),
      );
    });

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Filters' }));
    });

    const statusLabel = screen.getByText('Published Status');
    const statusContainer = statusLabel.closest('div')!;
    await act(async () => {
      fireEvent.click(within(statusContainer).getByRole('button'));
    });
    await act(async () => {
      fireEvent.click(within(statusContainer).getByText('Published'));
    });

    await waitFor(() => {
      expect(useTemplateData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          pagination: expect.objectContaining({ pageIndex: 0 }),
        }),
      );
    });
  });
});
