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

import CopyDatasetColumnModal from '../subPages/Columns/components/CopyDatasetColumnModal';

import { renderWithProviders } from './DatasetSetup';

vi.mock('@/components/ui/modals/Modal');

const mockColumn = {
  dataSetColumnId: 10,
  dataSetId: 1,
  heading: 'ProductName',
  listType: '',
  columnOrder: 1,
  dataType: '',
  dataTypeId: 1 as const,
  dataSetColumnType: '',
  dataSetColumnTypeId: 1 as const,
  listContent: '',
  formula: '',
  remoteField: '',
  showFilter: false,
  showSort: false,
  tooltip: '',
  isRequired: false,
  dateFormat: '',
};

describe('CopyDatasetColumnModal', () => {
  const mockOnClose = vi.fn();
  const mockOnConfirm = vi.fn();
  const existingNames = ['ExistingColumn', 'AnotherColumn'];

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('pre-populates New Heading with an incremented column name', () => {
    renderWithProviders(
      <CopyDatasetColumnModal
        column={mockColumn}
        existingNames={existingNames}
        onClose={mockOnClose}
        onConfirm={mockOnConfirm}
      />,
    );
    expect(screen.getByLabelText('New Heading')).toHaveValue('ProductName (1)');
  });

  it('shows "Heading is required" error when submitted with an empty heading', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <CopyDatasetColumnModal
        column={null}
        existingNames={existingNames}
        onClose={mockOnClose}
        onConfirm={mockOnConfirm}
      />,
    );
    await user.click(screen.getByRole('button', { name: 'Save' }));
    expect(screen.getByText('Heading is required')).toBeInTheDocument();
    expect(mockOnConfirm).not.toHaveBeenCalled();
  });

  it('shows a space error when the heading contains whitespace', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <CopyDatasetColumnModal
        column={null}
        existingNames={existingNames}
        onClose={mockOnClose}
        onConfirm={mockOnConfirm}
      />,
    );
    await user.type(screen.getByLabelText('New Heading'), 'My Column');
    await user.click(screen.getByRole('button', { name: 'Save' }));
    expect(screen.getByText('You cannot use a column name with spaces.')).toBeInTheDocument();
    expect(mockOnConfirm).not.toHaveBeenCalled();
  });

  it('shows a duplicate error when the heading matches an existing name (case-insensitive)', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <CopyDatasetColumnModal
        column={null}
        existingNames={existingNames}
        onClose={mockOnClose}
        onConfirm={mockOnConfirm}
      />,
    );
    await user.type(screen.getByLabelText('New Heading'), 'existingcolumn');
    await user.click(screen.getByRole('button', { name: 'Save' }));
    expect(screen.getByText('A column with this heading already exists')).toBeInTheDocument();
    expect(mockOnConfirm).not.toHaveBeenCalled();
  });

  it('submits with the trimmed heading and calls onConfirm', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <CopyDatasetColumnModal
        column={null}
        existingNames={existingNames}
        onClose={mockOnClose}
        onConfirm={mockOnConfirm}
      />,
    );
    await user.type(screen.getByLabelText('New Heading'), 'NewUniqueColumn');
    await user.click(screen.getByRole('button', { name: 'Save' }));
    expect(mockOnConfirm).toHaveBeenCalledWith('NewUniqueColumn');
  });

  it('calls onClose when Cancel is clicked', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <CopyDatasetColumnModal
        column={null}
        existingNames={existingNames}
        onClose={mockOnClose}
        onConfirm={mockOnConfirm}
      />,
    );
    await user.click(screen.getByRole('button', { name: 'Cancel' }));
    expect(mockOnClose).toHaveBeenCalled();
    expect(mockOnConfirm).not.toHaveBeenCalled();
  });
});
