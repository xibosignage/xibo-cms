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

import DeleteDatasetModal from '../components/DeleteDatasetModal';

import { renderWithProviders } from './DatasetSetup';

vi.mock('@/components/ui/modals/Modal');

describe('DeleteDatasetModal', () => {
  const mockOnClose = vi.fn();
  const mockOnDelete = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders "Delete Dataset?" heading for a single item', () => {
    renderWithProviders(
      <DeleteDatasetModal
        itemCount={1}
        datasetName="My Dataset"
        onClose={mockOnClose}
        onDelete={mockOnDelete}
      />,
    );
    expect(screen.getByText('Delete Dataset?')).toBeInTheDocument();
  });

  it('renders "Delete Datasets?" heading for multiple items', () => {
    renderWithProviders(
      <DeleteDatasetModal itemCount={3} onClose={mockOnClose} onDelete={mockOnDelete} />,
    );
    expect(screen.getByText('Delete Datasets?')).toBeInTheDocument();
  });

  it('displays the dataset name in the confirmation message for a single delete', () => {
    renderWithProviders(
      <DeleteDatasetModal
        itemCount={1}
        datasetName="Sales Data"
        onClose={mockOnClose}
        onDelete={mockOnDelete}
      />,
    );
    expect(screen.getByText('Sales Data')).toBeInTheDocument();
  });

  it('displays the item count in the confirmation message for multiple deletes', () => {
    renderWithProviders(
      <DeleteDatasetModal itemCount={5} onClose={mockOnClose} onDelete={mockOnDelete} />,
    );
    expect(screen.getByText('5')).toBeInTheDocument();
  });

  it('"Delete any associated data?" checkbox is unchecked by default', () => {
    renderWithProviders(
      <DeleteDatasetModal itemCount={1} onClose={mockOnClose} onDelete={mockOnDelete} />,
    );
    expect(
      screen.getByRole('checkbox', { name: /Delete any associated data\?/i }),
    ).not.toBeChecked();
  });

  it('passes { deleteData: false } to onDelete when checkbox is unchecked', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <DeleteDatasetModal itemCount={1} onClose={mockOnClose} onDelete={mockOnDelete} />,
    );
    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));
    expect(mockOnDelete).toHaveBeenCalledWith({ deleteData: false });
  });

  it('passes { deleteData: true } to onDelete when checkbox is checked before confirm', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <DeleteDatasetModal itemCount={1} onClose={mockOnClose} onDelete={mockOnDelete} />,
    );
    await user.click(screen.getByRole('checkbox', { name: /Delete any associated data\?/i }));
    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));
    expect(mockOnDelete).toHaveBeenCalledWith({ deleteData: true });
  });

  it('calls onClose when Cancel is clicked', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <DeleteDatasetModal itemCount={1} onClose={mockOnClose} onDelete={mockOnDelete} />,
    );
    await user.click(screen.getByRole('button', { name: 'Cancel' }));
    expect(mockOnClose).toHaveBeenCalled();
  });

  it('shows "Deleting…" and disables the confirm button while loading', () => {
    renderWithProviders(
      <DeleteDatasetModal
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
      <DeleteDatasetModal
        itemCount={1}
        onClose={mockOnClose}
        onDelete={mockOnDelete}
        error="Could not delete dataset"
      />,
    );
    expect(screen.getByText('Could not delete dataset')).toBeInTheDocument();
  });
});
