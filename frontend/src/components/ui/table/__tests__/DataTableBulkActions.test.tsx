import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { DataTableBulkActions } from '../DataTableBulkActions';
import { Trash2, Download } from 'lucide-react';

// Mock translation
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (str: string) => str }),
}));

describe('DataTableBulkActions', () => {
  const mockActions = [
    { label: 'Delete', icon: Trash2, onClick: vi.fn(), variant: 'danger' as const },
    { label: 'Export', icon: Download, onClick: vi.fn() },
    { label: 'No Handler', onClick: undefined as any }, // Should not render
  ];

  const defaultProps = {
    selectedCount: 3,
    actions: mockActions,
    onClearSelection: vi.fn(),
    selectedRows: [{ id: 1 }, { id: 2 }, { id: 3 }],
  };

  it('does not render when nothing is selected', () => {
    const { container } = render(<DataTableBulkActions {...defaultProps} selectedCount={0} />);
    expect(container.firstChild).toBeNull();
  });

  it('shows the correct count of selected items', () => {
    render(<DataTableBulkActions {...defaultProps} />);
    expect(screen.getByText('3 Selected')).toBeInTheDocument();
  });

  it('calls onClearSelection when the X button is clicked', () => {
    render(<DataTableBulkActions {...defaultProps} />);
    const clearBtn = screen.getByTitle('Clear selection');
    fireEvent.click(clearBtn);
    expect(defaultProps.onClearSelection).toHaveBeenCalled();
  });

  it('renders only actions that have an onClick handler', () => {
    render(<DataTableBulkActions {...defaultProps} />);
    expect(screen.getByTitle('Delete')).toBeInTheDocument();
    expect(screen.getByTitle('Export')).toBeInTheDocument();
    expect(screen.queryByTitle('No Handler')).not.toBeInTheDocument();
  });

  it('triggers the action with selected rows when clicked', () => {
    render(<DataTableBulkActions {...defaultProps} />);
    fireEvent.click(screen.getByTitle('Export'));
    expect(mockActions[1]?.onClick).toHaveBeenCalledWith(defaultProps.selectedRows);
  });

  it('applies red styling to danger-type actions', () => {
    render(<DataTableBulkActions {...defaultProps} />);
    const deleteBtn = screen.getByTitle('Delete');
    expect(deleteBtn).toHaveClass('text-red-600');
  });
});