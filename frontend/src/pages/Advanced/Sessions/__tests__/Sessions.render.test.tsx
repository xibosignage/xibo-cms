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

import { screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi, beforeEach } from 'vitest';

import Sessions from '../Sessions';
import { useSessionData } from '../hooks/useSessionData';

import { renderWithProviders, mockSessionsList } from './Setup';

import { useTableState } from '@/hooks/useTableState';

// --- MOCKS ---
vi.mock('@/context/UserContext', () => ({
  useUserContext: vi.fn(() => ({ user: null })),
}));

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Sessions', path: '/advanced/sessions' }]),
}));

vi.mock('@/hooks/useTableState', () => ({
  useTableState: vi.fn(),
}));

// Mock the React Query hook to bypass async rendering issues
vi.mock('../hooks/useSessionData', () => ({
  useSessionData: vi.fn(),
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

describe('Sessions Page - Rendering', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(useTableState).mockReturnValue(defaultTableState as never);

    // Default Successful State
    vi.mocked(useSessionData).mockReturnValue({
      data: { rows: mockSessionsList, totalCount: 3 },
      isFetching: false,
      isError: false,
      error: null,
    } as never);
  });

  it('displays a loading state initially', () => {
    // Force hydrated to false to check the pulse loading UI
    vi.mocked(useTableState).mockReturnValue({ ...defaultTableState, isHydrated: false } as never);

    renderWithProviders(<Sessions />);

    expect(screen.getByText('Loading your sessions...')).toBeInTheDocument();
  });

  it('renders table with data upon successful fetch', { tags: ['smoke'] }, async () => {
    renderWithProviders(<Sessions />);

    await waitFor(() => {
      expect(screen.getByText('alice_admin')).toBeInTheDocument();
    });
    expect(screen.getByText('bob_user')).toBeInTheDocument();
    expect(screen.getByText('charlie_guest')).toBeInTheDocument();
  });

  it('displays a top-level error banner if the API fails entirely', async () => {
    // Override the mock to simulate a server error
    vi.mocked(useSessionData).mockReturnValue({
      data: undefined,
      isFetching: false,
      isError: true,
      error: new Error('Network Error'),
    } as never);

    renderWithProviders(<Sessions />);

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Network Error');
    });
  });
});
