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

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';

import { AddAndEditDatasetColumnModal } from '../subPages/Columns/components/AddAndEditDatasetColumnModal';

import { renderWithProviders } from './DatasetSetup';

import type { DatasetColumn } from '@/types/datasetColumn';

// -- Module mocks --

const mockCreateDatasetColumn = vi.fn();
const mockUpdateDatasetColumn = vi.fn();

vi.mock('@/services/datasetApi', () => ({
  createDatasetColumn: (...args: unknown[]) => mockCreateDatasetColumn(...args),
  updateDatasetColumn: (...args: unknown[]) => mockUpdateDatasetColumn(...args),
}));

vi.mock('@/components/ui/modals/Modal');

vi.mock('@/components/ui/forms/SelectDropdown', () => ({
  default: ({
    label,
    value,
    options,
    onSelect,
  }: {
    label: string;
    value: string;
    options: { label: string; value: string }[];
    onSelect: (v: string) => void;
  }) => (
    <div>
      {label && <label htmlFor={label}>{label}</label>}
      <select
        id={label}
        aria-label={label}
        value={value}
        onChange={(e) => onSelect(e.target.value)}
      >
        {options.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
    </div>
  ),
}));

// -- Fixtures --

const mockColumn: DatasetColumn = {
  dataSetColumnId: 5,
  dataSetId: 1,
  heading: 'ProductName',
  columnOrder: 2,
  dataTypeId: 1,
  dataSetColumnTypeId: 1,
  listContent: '',
  formula: '',
  remoteField: '',
  showFilter: 0,
  showSort: 0,
  tooltip: '',
  isRequired: 0,
  dateFormat: '',
};

// -- Tests --

describe('AddAndEditDatasetColumnModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockCreateDatasetColumn.mockResolvedValue({});
    mockUpdateDatasetColumn.mockResolvedValue({});
  });

  describe('Add mode', () => {
    it('renders "Add Column" dialog title', () => {
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="add"
          datasetId="1"
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      expect(screen.getByRole('dialog', { name: 'Add Column' })).toBeInTheDocument();
    });

    it('heading field is empty by default', () => {
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="add"
          datasetId="1"
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      expect(screen.getByLabelText('Heading')).toHaveValue('');
    });

    it('shows "Heading is required" error on empty submit', async () => {
      const user = userEvent.setup();
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="add"
          datasetId="1"
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(screen.getByText('Heading is required')).toBeInTheDocument();
      expect(mockCreateDatasetColumn).not.toHaveBeenCalled();
    });

    it('shows spaces error when heading contains a space', async () => {
      const user = userEvent.setup();
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="add"
          datasetId="1"
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      await user.type(screen.getByLabelText('Heading'), 'My Column');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(screen.getByText('You cannot use a column name with spaces.')).toBeInTheDocument();
      expect(mockCreateDatasetColumn).not.toHaveBeenCalled();
    });

    it('calls createDatasetColumn with correct heading on valid submit', async () => {
      const user = userEvent.setup();
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="add"
          datasetId="42"
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      await user.type(screen.getByLabelText('Heading'), 'ProductCode');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockCreateDatasetColumn).toHaveBeenCalledWith(
          '42',
          expect.objectContaining({ heading: 'ProductCode' }),
        );
      });
    });

    it('calls onSave and onClose after successful create', async () => {
      const user = userEvent.setup();
      const mockOnSave = vi.fn();
      const mockOnClose = vi.fn();
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="add"
          datasetId="1"
          onClose={mockOnClose}
          onSave={mockOnSave}
        />,
      );

      await user.type(screen.getByLabelText('Heading'), 'ValidHeading');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockOnSave).toHaveBeenCalledTimes(1);
        expect(mockOnClose).toHaveBeenCalledTimes(1);
      });
    });

    it('shows error alert when createDatasetColumn rejects', async () => {
      const user = userEvent.setup();
      mockCreateDatasetColumn.mockRejectedValue(new Error('Server error'));
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="add"
          datasetId="1"
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      await user.type(screen.getByLabelText('Heading'), 'ValidHeading');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(screen.getByRole('alert')).toBeInTheDocument();
      });
    });

    it('Save button shows "Saving…" while pending', async () => {
      const user = userEvent.setup();
      mockCreateDatasetColumn.mockImplementation(() => new Promise(() => {}));
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="add"
          datasetId="1"
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      await user.type(screen.getByLabelText('Heading'), 'ValidHeading');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(screen.getByRole('button', { name: 'Saving…' })).toBeInTheDocument();
      });
    });

    it('Cancel button calls onClose without submitting', async () => {
      const user = userEvent.setup();
      const mockOnClose = vi.fn();
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="add"
          datasetId="1"
          onClose={mockOnClose}
          onSave={vi.fn()}
        />,
      );

      await user.click(screen.getByRole('button', { name: 'Cancel' }));

      expect(mockOnClose).toHaveBeenCalledTimes(1);
      expect(mockCreateDatasetColumn).not.toHaveBeenCalled();
    });
  });

  describe('Edit mode', () => {
    it('renders "Edit Column" dialog title', () => {
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="edit"
          datasetId="1"
          column={mockColumn}
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      expect(screen.getByRole('dialog', { name: 'Edit Column' })).toBeInTheDocument();
    });

    it('pre-populates heading from column data', () => {
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="edit"
          datasetId="1"
          column={mockColumn}
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      expect(screen.getByLabelText('Heading')).toHaveValue('ProductName');
    });

    it('calls updateDatasetColumn with column ID on valid submit', async () => {
      const user = userEvent.setup();
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="edit"
          datasetId="1"
          column={mockColumn}
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockUpdateDatasetColumn).toHaveBeenCalledWith('1', 5, expect.any(Object));
      });
    });
  });

  describe('Column Type: Value (default)', () => {
    it('shows List Content field for Value type', () => {
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="add"
          datasetId="1"
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      expect(screen.getByLabelText('List Content')).toBeInTheDocument();
    });

    it('shows Tooltip field for Value type', () => {
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="add"
          datasetId="1"
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      expect(screen.getByLabelText('Tooltip')).toBeInTheDocument();
    });

    it('shows Required? checkbox for Value type', () => {
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="add"
          datasetId="1"
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      expect(screen.getByRole('checkbox', { name: /Required\?/i })).toBeInTheDocument();
    });
  });

  describe('Column Type: Formula (type=2)', () => {
    it('shows Formula field when Column Type is Formula', async () => {
      const user = userEvent.setup();
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="add"
          datasetId="1"
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      await user.selectOptions(screen.getByRole('combobox', { name: 'Column Type' }), '2');

      expect(screen.getByLabelText('Formula')).toBeInTheDocument();
    });

    it('hides List Content for Formula type', async () => {
      const user = userEvent.setup();
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="add"
          datasetId="1"
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      await user.selectOptions(screen.getByRole('combobox', { name: 'Column Type' }), '2');

      expect(screen.queryByLabelText('List Content')).not.toBeInTheDocument();
    });
  });

  describe('Column Type: Remote (type=3)', () => {
    it('shows Remote Data Path field when Column Type is Remote', async () => {
      const user = userEvent.setup();
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="add"
          datasetId="1"
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      await user.selectOptions(screen.getByRole('combobox', { name: 'Column Type' }), '3');

      expect(screen.getByLabelText('Remote Data Path')).toBeInTheDocument();
    });

    it('hides List Content for Remote type', async () => {
      const user = userEvent.setup();
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="add"
          datasetId="1"
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      await user.selectOptions(screen.getByRole('combobox', { name: 'Column Type' }), '3');

      expect(screen.queryByLabelText('List Content')).not.toBeInTheDocument();
    });
  });

  describe('Always-visible fields', () => {
    it('Column Order field is always visible', () => {
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="add"
          datasetId="1"
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      expect(screen.getByLabelText('Column Order')).toBeInTheDocument();
    });

    it('Filter? checkbox is always visible', () => {
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="add"
          datasetId="1"
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      expect(screen.getByRole('checkbox', { name: /Filter\?/i })).toBeInTheDocument();
    });

    it('Sort? checkbox is always visible', () => {
      renderWithProviders(
        <AddAndEditDatasetColumnModal
          type="add"
          datasetId="1"
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      expect(screen.getByRole('checkbox', { name: /Sort\?/i })).toBeInTheDocument();
    });
  });
});
