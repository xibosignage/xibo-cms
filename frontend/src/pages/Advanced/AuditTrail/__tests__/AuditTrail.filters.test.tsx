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
import { describe, expect, it, vi, beforeEach } from 'vitest';

import AuditTrail from '../AuditTrail';
import { INITIAL_FILTER_STATE } from '../AuditTrailConfig';

import { mockAuditLogList, renderWithProviders } from './Setup';

import { useTableState } from '@/hooks/useTableState';

vi.mock('@/context/UserContext', () => ({
  useUserContext: vi.fn(() => ({ user: { userTypeId: 2 } })),
}));

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Audit Trail', path: '/advanced/audit-trail' }]),
}));

// DateFilter renders DatePicker (react-day-picker) which schedules async mount effects
// that keep userEvent waiting. A lightweight shim avoids these effects while still
// rendering the filter label so the panel-open assertions can check for it.
vi.mock('@/components/ui/DateFilter', () => ({
  default: ({
    label,
    name,
  }: {
    label: string;
    name: string;
    value: string;
    onChange: (name: string, val: string | null) => void;
  }) => (
    <div>
      <label htmlFor={name}>{label}</label>
    </div>
  ),
}));

vi.mock('@/hooks/useTableState', () => ({
  useTableState: vi.fn(),
}));

const { mockUseAuditTrailData } = vi.hoisted(() => ({ mockUseAuditTrailData: vi.fn() }));
vi.mock('../hooks/useAuditTrailData', () => ({ useAuditTrailData: mockUseAuditTrailData }));

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

// FilterInputs renders its children in the DOM at all times but hides them with
// aria-hidden when closed. getByRole and getByText respect aria-hidden, so filter
// labels and the Reset button are only accessible when the panel is open.
//
// "User", "Entity", "Entity ID", "IP Address", and "Message" also appear as
// DataTable column headers. Assertions on those labels use { selector: 'label' }
// to target the <label> elements inside the filter panel rather than the <th> headers.

describe('AuditTrail Page - Filters', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(useTableState).mockReturnValue(defaultTableState as never);
    mockUseAuditTrailData.mockReturnValue({
      data: { rows: mockAuditLogList, totalCount: 3 },
      isFetching: false,
      isError: false,
      error: null,
    });
  });

  it('does not show an Apply Filter button (filters apply immediately on change)', async () => {
    const { user } = renderWithProviders(<AuditTrail />);

    await user.click(screen.getByRole('button', { name: /filters/i }));

    expect(screen.queryByRole('button', { name: /apply filter/i })).not.toBeInTheDocument();
  });

  it('the Reset button is not accessible before the Filters panel is opened', () => {
    renderWithProviders(<AuditTrail />);

    expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
  });

  it('the Reset button is accessible after the Filters button is clicked', async () => {
    const { user } = renderWithProviders(<AuditTrail />);

    await user.click(screen.getByRole('button', { name: /filters/i }));

    expect(screen.getByRole('button', { name: /reset/i })).toBeInTheDocument();
  });

  it('the Reset button is hidden again after Filters is clicked a second time', async () => {
    const { user } = renderWithProviders(<AuditTrail />);

    await user.click(screen.getByRole('button', { name: /filters/i })); // open
    await user.click(screen.getByRole('button', { name: /filters/i })); // close

    expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
  });

  it('shows "From Date" label when the filter panel is open', async () => {
    const { user } = renderWithProviders(<AuditTrail />);

    await user.click(screen.getByRole('button', { name: /filters/i }));

    expect(screen.getByText('From Date')).toBeInTheDocument();
  });

  it('shows "To Date" label when the filter panel is open', async () => {
    const { user } = renderWithProviders(<AuditTrail />);

    await user.click(screen.getByRole('button', { name: /filters/i }));

    expect(screen.getByText('To Date')).toBeInTheDocument();
  });

  it('shows the "User" filter label when the filter panel is open', async () => {
    const { user } = renderWithProviders(<AuditTrail />);

    await user.click(screen.getByRole('button', { name: /filters/i }));

    expect(screen.getByText('User', { selector: 'label' })).toBeInTheDocument();
  });

  it('shows the "Entity" filter label when the filter panel is open', async () => {
    const { user } = renderWithProviders(<AuditTrail />);

    await user.click(screen.getByRole('button', { name: /filters/i }));

    expect(screen.getByText('Entity', { selector: 'label' })).toBeInTheDocument();
  });

  it('shows the "Entity ID" filter label when the filter panel is open', async () => {
    const { user } = renderWithProviders(<AuditTrail />);

    await user.click(screen.getByRole('button', { name: /filters/i }));

    expect(screen.getByText('Entity ID', { selector: 'label' })).toBeInTheDocument();
  });

  it('calls setFilterInputs when Reset is clicked', async () => {
    const { user } = renderWithProviders(<AuditTrail />);

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await user.click(screen.getByRole('button', { name: /reset/i }));

    expect(defaultTableState.setFilterInputs).toHaveBeenCalled();
  });

  it('resets pagination when Reset is clicked', async () => {
    const { user } = renderWithProviders(<AuditTrail />);

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await user.click(screen.getByRole('button', { name: /reset/i }));

    expect(defaultTableState.setPagination).toHaveBeenCalled();
  });
});
