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

import type { Table } from '@tanstack/react-table';
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';

import { DataTableOptions } from '../DataTableOptions';

// Mock translations
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (str: string) => str }),
}));

describe('DataTableOptions', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });
  const mockToggleName = vi.fn();
  const mockToggleAge = vi.fn();

  const mockTable = {
    getAllLeafColumns: () => [
      {
        id: 'name',
        getCanHide: () => true,
        columnDef: { header: 'Name' },
        toggleVisibility: mockToggleName, // FIX 2: Use the variable here
      },
      {
        id: 'age',
        getCanHide: () => true,
        columnDef: { header: 'Age' },
        toggleVisibility: mockToggleAge, // FIX 2: Use the variable here
      },
    ],
    getState: () => ({
      columnVisibility: { name: true, age: false },
    }),
    getAllColumns: () => [], // satisfy types if needed
  } as unknown as Table<object>;

  const defaultProps = {
    table: mockTable,
    onPrint: vi.fn(),
    onCSVExport: vi.fn(),
    onRefresh: vi.fn(),
    onViewModeChange: vi.fn(),
    viewMode: 'table' as const,
  };

  it('shows Columns, Print, and CSV buttons in table view', () => {
    render(<DataTableOptions {...defaultProps} />);
    expect(screen.getByText('Columns')).toBeInTheDocument();
    expect(screen.getByText('Print')).toBeInTheDocument();
    expect(screen.getByText('CSV')).toBeInTheDocument();
  });

  it('hides Columns, Print, and CSV buttons in grid view', () => {
    render(<DataTableOptions {...defaultProps} viewMode="grid" />);
    expect(screen.queryByText('Columns')).not.toBeInTheDocument();
    expect(screen.queryByText('Print')).not.toBeInTheDocument();
    expect(screen.queryByText('CSV')).not.toBeInTheDocument();
  });

  it('toggles the column dropdown and displays visibility states', () => {
    render(<DataTableOptions {...defaultProps} />);

    // Open dropdown
    fireEvent.click(screen.getByText('Columns'));
    expect(screen.getByText('Visible Columns')).toBeInTheDocument();

    // Check visibility states (Checkbox components)
    const nameCheckbox = screen.getByLabelText('Name') as HTMLInputElement;
    const ageCheckbox = screen.getByLabelText('Age') as HTMLInputElement;

    expect(nameCheckbox.checked).toBe(true);
    expect(ageCheckbox.checked).toBe(false);

    fireEvent.click(ageCheckbox);

    // Verify the mock tied to the table column was called
    expect(mockToggleAge).toHaveBeenCalledWith(true);
  });

  it('always shows the Refresh button and triggers reload', () => {
    const { rerender } = render(<DataTableOptions {...defaultProps} />);
    fireEvent.click(screen.getByText('Refresh'));
    expect(defaultProps.onRefresh).toHaveBeenCalled();

    rerender(<DataTableOptions {...defaultProps} viewMode="grid" />);
    expect(screen.getByText('Refresh')).toBeInTheDocument();
  });

  it('highlights the active view mode button', () => {
    render(<DataTableOptions {...defaultProps} viewMode="table" />);
    const tableViewBtn = screen.getByTitle('Table View');
    const gridViewBtn = screen.getByTitle('Grid View');

    // Using the classes defined in your getToggleButtonStyle helper
    expect(tableViewBtn).toHaveClass('bg-gray-100');
    expect(gridViewBtn).toHaveClass('bg-transparent');
  });

  it('switches view mode when clicking toggle buttons', () => {
    render(<DataTableOptions {...defaultProps} />);
    fireEvent.click(screen.getByTitle('Grid View'));
    expect(defaultProps.onViewModeChange).toHaveBeenCalledWith('grid');
  });
});
