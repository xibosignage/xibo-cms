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

import Logs from '../Logs';
import { INITIAL_FILTER_STATE } from '../LogsConfig';

import { createMockLogsQuery, renderWithProviders } from './Setup';

import { useTableState } from '@/hooks/useTableState';

vi.mock('@/context/UserContext', () => ({
  useUserContext: vi.fn(() => ({ user: { userTypeId: 2 } })),
}));

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Log', path: '/advanced/log' }]),
}));

vi.mock('@/hooks/useTableState', () => ({
  useTableState: vi.fn(),
}));

const { mockUseLogsData } = vi.hoisted(() => ({ mockUseLogsData: vi.fn() }));
vi.mock('../hooks/useLogsData', () => ({ useLogsData: mockUseLogsData }));

// --- Default Table State ---

const defaultTableState = {
  pagination: { pageIndex: 0, pageSize: 10 },
  setPagination: vi.fn(),
  sorting: [{ id: 'logId', desc: true }],
  setSorting: vi.fn(),
  columnVisibility: {},
  setColumnVisibility: vi.fn(),
  globalFilter: '',
  setGlobalFilter: vi.fn(),
  filterInputs: INITIAL_FILTER_STATE,
  setFilterInputs: vi.fn(),
  isHydrated: true,
};

// --- Tests ---

describe('Logs Page - Filters', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(useTableState).mockReturnValue(defaultTableState as never);
    mockUseLogsData.mockReturnValue(createMockLogsQuery());
  });

  // FilterInputs renders its children in the DOM at all times but hides them with
  // aria-hidden when closed. getByRole respects aria-hidden, so the Apply Filter
  // button is only accessible when the panel is open.
  // The panel starts open by default; the Filters button toggles it closed/open.

  it('the Apply Filter action is accessible when the page loads', () => {
    renderWithProviders(<Logs />);

    expect(screen.getByRole('button', { name: /apply filter/i })).toBeInTheDocument();
  });

  it('the Apply Filter action is not accessible after the Filters button is clicked', async () => {
    const { user } = renderWithProviders(<Logs />);

    await user.click(screen.getByRole('button', { name: /filters/i }));

    expect(screen.queryByRole('button', { name: /apply filter/i })).not.toBeInTheDocument();
  });

  it('the Apply Filter action is accessible again after Filters is clicked a second time', async () => {
    const { user } = renderWithProviders(<Logs />);

    await user.click(screen.getByRole('button', { name: /filters/i })); // close
    await user.click(screen.getByRole('button', { name: /filters/i })); // open

    expect(screen.getByRole('button', { name: /apply filter/i })).toBeInTheDocument();
  });

  it('shows the Date label when the page loads', () => {
    renderWithProviders(<Logs />);

    expect(screen.getByText('Date')).toBeInTheDocument();
  });

  it('shows the Duration Back label when the page loads', () => {
    renderWithProviders(<Logs />);

    expect(screen.getByText('Duration Back')).toBeInTheDocument();
  });

  it('shows the Level label when the page loads', () => {
    renderWithProviders(<Logs />);

    expect(screen.getByText('Level')).toBeInTheDocument();
  });

  it('shows the Run label when the page loads', () => {
    renderWithProviders(<Logs />);

    expect(screen.getByText('Run')).toBeInTheDocument();
  });

  it('shows the Display label when the page loads', () => {
    renderWithProviders(<Logs />);

    expect(screen.getByText('Display')).toBeInTheDocument();
  });

  it('resets pagination to page 0 when Apply Filter is clicked', async () => {
    const { user } = renderWithProviders(<Logs />);

    await user.click(screen.getByRole('button', { name: /apply filter/i }));

    expect(defaultTableState.setPagination).toHaveBeenCalled();
  });

  it('removes the no-filter prompt when Apply Filter is clicked', async () => {
    const { user } = renderWithProviders(<Logs />);

    expect(
      screen.getByText('Set your filters above and click Apply Filter to view logs.'),
    ).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: /apply filter/i }));

    expect(
      screen.queryByText('Set your filters above and click Apply Filter to view logs.'),
    ).not.toBeInTheDocument();
  });

  it('closes the filter panel after Apply Filter is clicked', async () => {
    const { user } = renderWithProviders(<Logs />);

    await user.click(screen.getByRole('button', { name: /apply filter/i }));

    expect(screen.queryByRole('button', { name: /apply filter/i })).not.toBeInTheDocument();
  });

  it('calls setFilterInputs when Reset is clicked', async () => {
    const { user } = renderWithProviders(<Logs />);

    await user.click(screen.getByRole('button', { name: /reset/i }));

    expect(defaultTableState.setFilterInputs).toHaveBeenCalled();
  });

  it('resets pagination when Reset is clicked', async () => {
    const { user } = renderWithProviders(<Logs />);

    await user.click(screen.getByRole('button', { name: /reset/i }));

    expect(defaultTableState.setPagination).toHaveBeenCalled();
  });
});
