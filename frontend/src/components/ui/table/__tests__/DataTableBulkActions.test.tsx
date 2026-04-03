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

import { render, screen, fireEvent } from '@testing-library/react';
import { Trash2, Download } from 'lucide-react';
import { describe, it, expect, vi } from 'vitest';

import { DataTableBulkActions } from '../DataTableBulkActions';

// Mock translation
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (str: string) => str }),
}));

describe('DataTableBulkActions', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });
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
