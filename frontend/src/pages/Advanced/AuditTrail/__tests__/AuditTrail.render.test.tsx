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

import AuditTrail from '../AuditTrail';
import { INITIAL_FILTER_STATE } from '../AuditTrailConfig';
import { useAuditTrailData } from '../hooks/useAuditTrailData';

import { mockAuditLogList, renderWithProviders } from './Setup';

import { useTableState } from '@/hooks/useTableState';

// --- Module Mocks ---

vi.mock('@/context/UserContext', () => ({
  useUserContext: vi.fn(() => ({ user: { userTypeId: 2 } })),
}));

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

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Audit Trail', path: '/advanced/audit-trail' }]),
}));

vi.mock('@/hooks/useTableState', () => ({
  useTableState: vi.fn(),
}));

vi.mock('../hooks/useAuditTrailData', () => ({
  useAuditTrailData: vi.fn(),
}));

// --- Default Table State ---

const defaultTableState = {
  pagination: { pageIndex: 0, pageSize: 10 },
  setPagination: vi.fn(),
  sorting: [{ id: 'logId', desc: true }],
  setSorting: vi.fn(),
  columnVisibility: {
    logId: true,
    logDate: true,
    userName: true,
    entity: true,
    entityId: true,
    ipAddress: true,
    message: true,
    objectAfter: true,
  },
  setColumnVisibility: vi.fn(),
  globalFilter: '',
  setGlobalFilter: vi.fn(),
  filterInputs: INITIAL_FILTER_STATE,
  setFilterInputs: vi.fn(),
  isHydrated: true,
};

// --- Tests ---

describe('AuditTrail Page - Rendering', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(useTableState).mockReturnValue(defaultTableState as never);
    vi.mocked(useAuditTrailData).mockReturnValue({
      data: { rows: mockAuditLogList, totalCount: 3 },
      isFetching: false,
      isError: false,
      error: null,
    } as never);
  });

  it('shows a loading pulse when the page is not yet hydrated', () => {
    vi.mocked(useTableState).mockReturnValue({
      ...defaultTableState,
      isHydrated: false,
    } as never);

    renderWithProviders(<AuditTrail />);

    expect(screen.getByText('Loading audit trail...')).toBeInTheDocument();
  });

  it('renders the DataTable with audit log rows when hydrated', { tags: ['smoke'] }, () => {
    renderWithProviders(<AuditTrail />);

    expect(screen.getByText('Layout saved')).toBeInTheDocument();
    expect(screen.getByText('User updated')).toBeInTheDocument();
    expect(screen.getByText('Campaign deleted')).toBeInTheDocument();
  });

  it('shows an error alert banner when the API call fails', () => {
    vi.mocked(useAuditTrailData).mockReturnValue({
      data: undefined,
      isFetching: false,
      isError: true,
      error: new Error('Server Error'),
    } as never);

    renderWithProviders(<AuditTrail />);

    expect(screen.getByRole('alert')).toHaveTextContent('Server Error');
  });

  it('does not show an error alert when data loads successfully', () => {
    renderWithProviders(<AuditTrail />);

    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('renders the Filters toggle button', () => {
    renderWithProviders(<AuditTrail />);

    expect(screen.getByRole('button', { name: /filters/i })).toBeInTheDocument();
  });

  it('disables the Filters toggle button when the page is not hydrated', () => {
    vi.mocked(useTableState).mockReturnValue({
      ...defaultTableState,
      isHydrated: false,
    } as never);

    renderWithProviders(<AuditTrail />);

    expect(screen.getByRole('button', { name: /filters/i })).toBeDisabled();
  });

  it('renders the Export button', () => {
    renderWithProviders(<AuditTrail />);

    expect(screen.getByRole('button', { name: /export/i })).toBeInTheDocument();
  });

  it('does not show the loading pulse when the page is hydrated', () => {
    renderWithProviders(<AuditTrail />);

    expect(screen.queryByText('Loading audit trail...')).not.toBeInTheDocument();
  });
});
