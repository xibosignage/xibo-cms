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

import CopyDatasetModal from '../components/CopyDatasetModal';

import { renderWithProviders, mockDataset } from './DatasetSetup';

describe('CopyDatasetModal', () => {
  const mockOnClose = vi.fn();
  const mockOnConfirm = vi.fn();
  const existingNames = ['ExistingDataset', 'AnotherOne'];

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('does not render when isOpen is false', () => {
    renderWithProviders(
      <CopyDatasetModal
        isOpen={false}
        onClose={mockOnClose}
        onConfirm={mockOnConfirm}
        dataset={null}
        existingNames={existingNames}
      />,
    );
    expect(screen.queryByText('Copy Dataset')).not.toBeInTheDocument();
  });

  it('initializes with incremented name and existing data when dataset is provided', () => {
    const dataset = mockDataset({ dataSet: 'Test Dataset', description: 'Desc', code: 'C1' });

    renderWithProviders(
      <CopyDatasetModal
        isOpen={true}
        onClose={mockOnClose}
        onConfirm={mockOnConfirm}
        dataset={dataset}
        existingNames={existingNames}
      />,
    );

    expect(screen.getByLabelText('Name')).toHaveValue('Test Dataset (1)');
    expect(screen.getByLabelText('Description')).toHaveValue('Desc');
    expect(screen.getByLabelText('Code')).toHaveValue('C1');

    expect(
      screen.getByRole('checkbox', {
        name: /Copy rows\?/i,
      }),
    ).not.toBeChecked();
  });

  it('shows error when submitting with an empty name', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <CopyDatasetModal
        isOpen={true}
        onClose={mockOnClose}
        onConfirm={mockOnConfirm}
        dataset={null}
        existingNames={existingNames}
      />,
    );

    const saveBtn = screen.getByRole('button', { name: 'Save' });
    await user.click(saveBtn);

    expect(screen.getByText('Name is required')).toBeInTheDocument();
    expect(mockOnConfirm).not.toHaveBeenCalled();
  });

  it('shows error when submitting with a duplicate name (case-insensitive)', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <CopyDatasetModal
        isOpen={true}
        onClose={mockOnClose}
        onConfirm={mockOnConfirm}
        dataset={null}
        existingNames={existingNames}
      />,
    );

    const nameInput = screen.getByLabelText('Name');
    await user.type(nameInput, 'existingdataset');

    const saveBtn = screen.getByRole('button', { name: 'Save' });
    await user.click(saveBtn);

    expect(screen.getByText('A dataset item with this name already exists')).toBeInTheDocument();
    expect(mockOnConfirm).not.toHaveBeenCalled();
  });

  it('submits correctly and trims the name', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <CopyDatasetModal
        isOpen={true}
        onClose={mockOnClose}
        onConfirm={mockOnConfirm}
        dataset={null}
        existingNames={existingNames}
      />,
    );

    await user.type(screen.getByLabelText('Name'), '  New Unique Dataset  ');
    await user.type(screen.getByLabelText('Description'), 'New Desc');

    await user.click(
      screen.getByRole('checkbox', {
        name: /Copy rows\?/i,
      }),
    );

    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(mockOnConfirm).toHaveBeenCalledWith('New Unique Dataset', 'New Desc', '', true);
  });

  it('disables inputs and shows loading state when isLoading is true', () => {
    renderWithProviders(
      <CopyDatasetModal
        isOpen={true}
        onClose={mockOnClose}
        onConfirm={mockOnConfirm}
        dataset={null}
        existingNames={existingNames}
        isLoading={true}
      />,
    );

    expect(screen.getByRole('button', { name: 'Saving…' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Cancel' })).toBeDisabled();
  });
});
