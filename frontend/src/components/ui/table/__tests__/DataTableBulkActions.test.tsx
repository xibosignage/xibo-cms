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

import { render, screen, fireEvent } from '@testing-library/react';
import { Trash2, Download } from 'lucide-react';
import { describe, it, expect, vi } from 'vitest';

import { DataTableBulkActions } from '../DataTableBulkActions';

// Mock translation

describe('DataTableBulkActions', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });
  const mockActions = [
    { label: 'Delete', icon: Trash2, onClick: vi.fn(), variant: 'danger' as const },
    { label: 'Export', icon: Download, onClick: vi.fn() },
    { label: 'No Handler', onClick: undefined as unknown as (rows: object[]) => void }, // Should not render
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
    const clearBtn = screen.getByRole('button', { name: /clear selection/i });
    fireEvent.click(clearBtn);
    expect(defaultProps.onClearSelection).toHaveBeenCalled();
  });

  it('renders only actions that have an onClick handler', () => {
    render(<DataTableBulkActions {...defaultProps} />);
    expect(screen.getByRole('button', { name: 'Delete' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Export' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'No Handler' })).not.toBeInTheDocument();
  });

  it('triggers the action with selected rows when clicked', () => {
    render(<DataTableBulkActions {...defaultProps} />);
    fireEvent.click(screen.getByRole('button', { name: 'Export' }));
    expect(mockActions[1]?.onClick).toHaveBeenCalledWith(defaultProps.selectedRows);
  });

  it('applies red styling to danger-type actions', () => {
    render(<DataTableBulkActions {...defaultProps} />);
    const deleteBtn = screen.getByRole('button', { name: 'Delete' });
    expect(deleteBtn).toHaveClass('text-red-600');
  });
});
