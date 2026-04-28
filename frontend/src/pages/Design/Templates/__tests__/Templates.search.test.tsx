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

vi.mock('i18next', () => {
  const t = (key: string) => key;
  return { default: { t, language: 'en', isInitialized: true }, t };
});

// Services
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/services/folderApi');

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
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Fixtures
// =============================================================================

// 10 rows with totalCount: 25 so pagination controls render.
const PAGINATED_TEMPLATES = {
  rows: Array.from({ length: 10 }).map((_, i) => ({
    ...mockTemplate,
    layoutId: i + 1,
    layout: `Template ${i + 1}`,
    campaignId: i + 10,
  })),
  totalCount: 25,
};

const EMPTY_TEMPLATE_DATA = { rows: [], totalCount: 0 };

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

describe('Templates page - search and pagination', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockTemplatesData(EMPTY_TEMPLATE_DATA);
  });

  // -------------------------------------------------------------------------
  // The search input is present and accepts text.
  // -------------------------------------------------------------------------
  test('search input accepts typed text', async () => {
    const user = userEvent.setup({ delay: null });
    renderTemplatesPage();

    const searchInput = await screen.findByPlaceholderText('Search template...');
    await user.type(searchInput, 'splash');

    expect(searchInput).toHaveValue('splash');
  });

  // -------------------------------------------------------------------------
  // Clearing the search resets the input to empty.
  // -------------------------------------------------------------------------
  test('clearing search resets the input to empty', async () => {
    const user = userEvent.setup({ delay: null });
    renderTemplatesPage();

    const searchInput = await screen.findByPlaceholderText('Search template...');
    await user.type(searchInput, 'splash');
    expect(searchInput).toHaveValue('splash');

    await user.clear(searchInput);
    expect(searchInput).toHaveValue('');
  });

  // -------------------------------------------------------------------------
  // The typed value is wired through to the data hook's filter param.
  // The useDebounce mock returns the value immediately so there is no delay.
  // -------------------------------------------------------------------------
  test('typing in search passes filter param to useTemplateData', async () => {
    const user = userEvent.setup({ delay: null });
    renderTemplatesPage();

    await user.type(await screen.findByPlaceholderText('Search template...'), 'splash');

    await waitFor(() => {
      expect(useTemplateData).toHaveBeenLastCalledWith(
        expect.objectContaining({ filter: 'splash' }),
      );
    });
  });

  // -------------------------------------------------------------------------
  // Pagination: clicking Next increments pageIndex.
  // totalCount: 25 with default page size of 10 means two more pages exist.
  // -------------------------------------------------------------------------
  test('clicking Next passes pageIndex 1 to useTemplateData', async () => {
    mockTemplatesData(PAGINATED_TEMPLATES);

    renderTemplatesPage();

    fireEvent.click(await screen.findByRole('button', { name: /Next/i }));

    await waitFor(() => {
      expect(useTemplateData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          pagination: expect.objectContaining({ pageIndex: 1 }),
        }),
      );
    });
  });

  // -------------------------------------------------------------------------
  // Pagination controls render only when there is more than one page.
  // -------------------------------------------------------------------------
  test('pagination controls are visible when totalCount exceeds page size', async () => {
    mockTemplatesData(PAGINATED_TEMPLATES);

    renderTemplatesPage();

    expect(await screen.findByRole('button', { name: /Next/i })).toBeInTheDocument();
  });

  // -------------------------------------------------------------------------
  // Selecting Published Status = Published passes publishedStatusId: '1'.
  // -------------------------------------------------------------------------
  test('selecting Published Status = Published passes publishedStatusId to useTemplateData', async () => {
    // Open the filter panel, then pick "Published" from the Published Status dropdown.
    renderTemplatesPage();

    await act(async () => {
      fireEvent.click(await screen.findByRole('button', { name: 'Filters' }));
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
          advancedFilters: expect.objectContaining({ publishedStatusId: '1' }),
        }),
      );
    });
  });

  // -------------------------------------------------------------------------
  // Clicking Reset clears all active filter values back to their defaults.
  // -------------------------------------------------------------------------
  test('clicking Reset clears all advanced filter values', async () => {
    // Open the filter panel, select a filter value, then click Reset.
    renderTemplatesPage();

    await act(async () => {
      fireEvent.click(await screen.findByRole('button', { name: 'Filters' }));
    });

    // Select Published Status = Published to set a non-default filter value.
    const statusLabel = screen.getByText('Published Status');
    const statusContainer = statusLabel.closest('div')!;
    await act(async () => {
      fireEvent.click(within(statusContainer).getByRole('button'));
    });
    await act(async () => {
      fireEvent.click(within(statusContainer).getByText('Published'));
    });

    // Now reset - the filter values should return to the initial empty state.
    await act(async () => {
      fireEvent.click(await screen.findByRole('button', { name: 'Reset' }));
    });

    await waitFor(() => {
      expect(useTemplateData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          advancedFilters: expect.objectContaining({ name: '', tags: [] }),
        }),
      );
    });
  });
});
