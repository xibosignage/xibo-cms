import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { DataTableOptions } from '../DataTableOptions';

// Mock translations
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (str: string) => str }),
}));

describe('DataTableOptions', () => {
  // FIX 1: Define specific mocks to track calls accurately
  const mockToggleName = vi.fn();
  const mockToggleAge = vi.fn();

  const mockTable = {
    getAllLeafColumns: () => [
      { 
        id: 'name', 
        getCanHide: () => true, 
        columnDef: { header: 'Name' }, 
        toggleVisibility: mockToggleName // FIX 2: Use the variable here
      },
      { 
        id: 'age', 
        getCanHide: () => true, 
        columnDef: { header: 'Age' }, 
        toggleVisibility: mockToggleAge // FIX 2: Use the variable here
      },
    ],
    getState: () => ({ 
      columnVisibility: { name: true, age: false } 
    }),
    getAllColumns: () => [], // satisfy types if needed
  } as any;

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

    // FIX 3: Single click to trigger the onChange logic
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