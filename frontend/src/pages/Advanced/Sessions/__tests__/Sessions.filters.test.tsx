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
import { describe, expect, it, vi, beforeEach } from 'vitest';

import Sessions from '../Sessions';

import { renderWithProviders, mockSessionsList } from './Setup';

import { useTableState } from '@/hooks/useTableState';

vi.mock('@/context/UserContext', () => ({
  useUserContext: vi.fn(() => ({ user: null })),
}));

// 1. Mock Table State
vi.mock('@/hooks/useTableState', () => ({
  useTableState: vi.fn(),
}));

// 2. Mock Session Data directly
const { mockUseSessionData } = vi.hoisted(() => ({ mockUseSessionData: vi.fn() }));
vi.mock('../hooks/useSessionData', () => ({ useSessionData: mockUseSessionData }));

// 3. Mock Hooks
vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Sessions', path: '/advanced/sessions' }]),
}));

const defaultTableState = {
  pagination: { pageIndex: 0, pageSize: 10 },
  setPagination: vi.fn(),
  sorting: [],
  setSorting: vi.fn(),
  columnVisibility: {},
  setColumnVisibility: vi.fn(),
  globalFilter: '',
  setGlobalFilter: vi.fn(),
  filterInputs: { type: '', lastModified: '' },
  setFilterInputs: vi.fn(),
  isHydrated: true,
};

describe('Sessions Page - Filters', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(useTableState).mockReturnValue(defaultTableState as never);

    mockUseSessionData.mockReturnValue({
      data: { rows: mockSessionsList, totalCount: 50 },
      isFetching: false,
      isError: false,
      error: null,
    } as never);
  });

  it('toggles the filter inputs panel', async () => {
    const { user } = renderWithProviders(<Sessions />);

    const filterBtn = screen.getByRole('button', { name: 'Filters' });
    await user.click(filterBtn);

    expect(screen.getByText('From Date')).toBeInTheDocument();
  });

  it('resets filters and pagination when Reset is clicked', async () => {
    const { user } = renderWithProviders(<Sessions />);

    // Open filters
    await user.click(screen.getByRole('button', { name: 'Filters' }));

    // Click Reset
    const resetBtn = screen.getByRole('button', { name: 'Reset' });
    await user.click(resetBtn);

    // Verify state setters were successfully called to clear things out
    expect(defaultTableState.setFilterInputs).toHaveBeenCalled();
    expect(defaultTableState.setPagination).toHaveBeenCalled();
  });
});
