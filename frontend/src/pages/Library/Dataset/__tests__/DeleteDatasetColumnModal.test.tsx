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
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';

import { DeleteDatasetColumnModal } from '../subPages/Columns/components/DeleteDatasetColumnModal';

import { renderWithProviders } from './DatasetSetup';

vi.mock('@/components/ui/modals/Modal');

describe('DeleteDatasetColumnModal', () => {
  const mockOnClose = vi.fn();
  const mockOnDelete = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the column name in the confirmation message for a single item', () => {
    renderWithProviders(
      <DeleteDatasetColumnModal
        itemCount={1}
        columnName="ProductName"
        onClose={mockOnClose}
        onDelete={mockOnDelete}
      />,
    );
    expect(screen.getByText('ProductName')).toBeInTheDocument();
    expect(screen.getByText('Delete Column?')).toBeInTheDocument();
  });

  it('renders "Delete Columns?" and item count for multiple items', () => {
    renderWithProviders(
      <DeleteDatasetColumnModal itemCount={3} onClose={mockOnClose} onDelete={mockOnDelete} />,
    );
    expect(screen.getByText('Delete Columns?')).toBeInTheDocument();
    expect(screen.getByText('3')).toBeInTheDocument();
  });

  it('calls onDelete when the confirm button is clicked', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <DeleteDatasetColumnModal itemCount={1} onClose={mockOnClose} onDelete={mockOnDelete} />,
    );
    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));
    expect(mockOnDelete).toHaveBeenCalledTimes(1);
  });

  it('calls onClose when Cancel is clicked', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <DeleteDatasetColumnModal itemCount={1} onClose={mockOnClose} onDelete={mockOnDelete} />,
    );
    await user.click(screen.getByRole('button', { name: 'Cancel' }));
    expect(mockOnClose).toHaveBeenCalledTimes(1);
  });

  it('shows "Deleting…" and disables the confirm button while loading', () => {
    renderWithProviders(
      <DeleteDatasetColumnModal
        itemCount={1}
        onClose={mockOnClose}
        onDelete={mockOnDelete}
        isLoading={true}
      />,
    );
    expect(screen.getByRole('button', { name: 'Deleting…' })).toBeDisabled();
  });

  it('renders the error message when the error prop is provided', () => {
    renderWithProviders(
      <DeleteDatasetColumnModal
        itemCount={1}
        onClose={mockOnClose}
        onDelete={mockOnDelete}
        error="Column deletion failed"
      />,
    );
    expect(screen.getByText('Column deletion failed')).toBeInTheDocument();
  });
});
