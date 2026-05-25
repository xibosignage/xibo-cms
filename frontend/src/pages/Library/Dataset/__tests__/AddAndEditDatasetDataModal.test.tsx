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

import { AddAndEditDataModal } from '../subPages/Data/components/AddAndEditDatasetDataModal';

import { renderWithProviders } from './DatasetSetup';

import type { DatasetColumn } from '@/types/datasetColumn';

// -- Module mocks --

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (str: string, opts?: Record<string, unknown>) => {
      if (!opts) return str;
      return Object.entries(opts).reduce(
        (acc, [k, v]) => acc.replace(new RegExp(`{{${k}}}`, 'g'), String(v)),
        str,
      );
    },
    i18n: { changeLanguage: () => new Promise(() => {}) },
  }),
}));

const mockCreateDatasetRow = vi.fn();
const mockUpdateDatasetRow = vi.fn();

vi.mock('@/services/datasetApi', () => ({
  createDatasetRow: (...args: unknown[]) => mockCreateDatasetRow(...args),
  updateDatasetRow: (...args: unknown[]) => mockUpdateDatasetRow(...args),
}));

vi.mock('@/components/ui/modals/Modal');

vi.mock('@/components/ui/forms/DatePickerInput', () => ({
  default: ({ label, onChange }: { label: string; onChange: (v: string) => void }) => (
    <div>
      <label>{label}</label>
      <input aria-label={label} type="date" onChange={(e) => onChange(e.target.value)} />
    </div>
  ),
}));

vi.mock('@/components/ui/forms/MediaInput', () => ({
  default: ({ label }: { label: string }) => <div aria-label={label}>Media Input</div>,
}));

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
      <label htmlFor={label}>{label}</label>
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

// -- Helpers --

const makeColumn = (
  id: number,
  heading: string,
  dataTypeId: number,
  opts: Partial<DatasetColumn> = {},
): DatasetColumn => ({
  dataSetColumnId: id,
  dataSetId: 1,
  heading,
  listType: '',
  columnOrder: id,
  dataType: '',
  dataTypeId: dataTypeId as DatasetColumn['dataTypeId'],
  dataSetColumnType: '',
  dataSetColumnTypeId: 1,
  listContent: '',
  formula: '',
  remoteField: '',
  showFilter: false,
  showSort: false,
  tooltip: '',
  isRequired: false,
  dateFormat: '',
  ...opts,
});

const defaultProps = {
  datasetId: '1',
  columnsSchema: [] as DatasetColumn[],
  onClose: vi.fn(),
  onSave: vi.fn(),
};

// -- Tests --

describe('AddAndEditDataModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockCreateDatasetRow.mockResolvedValue({});
    mockUpdateDatasetRow.mockResolvedValue({});
  });

  describe('Modal title', () => {
    it('renders "Add Data" dialog in add mode', () => {
      renderWithProviders(<AddAndEditDataModal {...defaultProps} type="add" />);

      expect(screen.getByRole('dialog', { name: 'Add Data' })).toBeInTheDocument();
    });

    it('renders "Edit Data" dialog in edit mode', () => {
      renderWithProviders(<AddAndEditDataModal {...defaultProps} type="edit" />);

      expect(screen.getByRole('dialog', { name: 'Edit Data' })).toBeInTheDocument();
    });
  });

  describe('Column filtering', () => {
    it('renders only Value-type columns (dataSetColumnTypeId=1)', () => {
      const columns: DatasetColumn[] = [
        makeColumn(1, 'ValueCol', 1, { dataSetColumnTypeId: 1 }),
        makeColumn(2, 'FormulaCol', 1, { dataSetColumnTypeId: 2 }),
      ];

      renderWithProviders(
        <AddAndEditDataModal {...defaultProps} type="add" columnsSchema={columns} />,
      );

      expect(screen.getByLabelText('ValueCol')).toBeInTheDocument();
      expect(screen.queryByLabelText('FormulaCol')).not.toBeInTheDocument();
    });
  });

  describe('Field rendering by data type', () => {
    it('String column (dataTypeId=1) renders a text input', () => {
      const columns = [makeColumn(1, 'ProductName', 1)];

      renderWithProviders(
        <AddAndEditDataModal {...defaultProps} type="add" columnsSchema={columns} />,
      );

      expect(screen.getByLabelText('ProductName')).toBeInTheDocument();
    });

    it('String column with listContent renders a SelectDropdown', () => {
      const columns = [makeColumn(1, 'Status', 1, { listContent: 'Active,Inactive,Pending' })];

      renderWithProviders(
        <AddAndEditDataModal {...defaultProps} type="add" columnsSchema={columns} />,
      );

      expect(screen.getByRole('combobox', { name: 'Status' })).toBeInTheDocument();
      expect(screen.getByRole('option', { name: 'Active' })).toBeInTheDocument();
      expect(screen.getByRole('option', { name: 'Inactive' })).toBeInTheDocument();
    });

    it('Number column (dataTypeId=2) renders a number input', () => {
      const columns = [makeColumn(1, 'Quantity', 2)];

      renderWithProviders(
        <AddAndEditDataModal {...defaultProps} type="add" columnsSchema={columns} />,
      );

      expect(screen.getByRole('spinbutton', { name: 'Quantity' })).toBeInTheDocument();
    });

    it('Date column (dataTypeId=3) renders a DatePickerInput', () => {
      const columns = [makeColumn(1, 'StartDate', 3)];

      renderWithProviders(
        <AddAndEditDataModal {...defaultProps} type="add" columnsSchema={columns} />,
      );

      expect(screen.getByLabelText('StartDate')).toBeInTheDocument();
    });

    it('Library Image column (dataTypeId=5) renders a MediaInput', () => {
      const columns = [makeColumn(1, 'Banner', 5)];

      renderWithProviders(
        <AddAndEditDataModal {...defaultProps} type="add" columnsSchema={columns} />,
      );

      expect(screen.getByText('Media Input')).toBeInTheDocument();
    });
  });

  describe('Column ordering and required indicator', () => {
    it('sorts columns by columnOrder ascending', () => {
      const columns: DatasetColumn[] = [
        makeColumn(10, 'LastCol', 1, { columnOrder: 10 }),
        makeColumn(1, 'FirstCol', 1, { columnOrder: 1 }),
      ];

      renderWithProviders(
        <AddAndEditDataModal {...defaultProps} type="add" columnsSchema={columns} />,
      );

      const textboxes = screen.getAllByRole('textbox');
      const names = textboxes.map((el) => el.getAttribute('name'));
      expect(names.indexOf('FirstCol')).toBeLessThan(names.indexOf('LastCol'));
    });

    it('appends " *" to the label of required columns', () => {
      const columns = [makeColumn(1, 'RequiredField', 1, { isRequired: true })];

      renderWithProviders(
        <AddAndEditDataModal {...defaultProps} type="add" columnsSchema={columns} />,
      );

      expect(screen.getByLabelText('RequiredField *')).toBeInTheDocument();
    });
  });

  describe('Validation', () => {
    it('shows error alert when required field is empty on save', async () => {
      const user = userEvent.setup();
      const columns = [makeColumn(1, 'ColName', 1, { isRequired: true })];

      renderWithProviders(
        <AddAndEditDataModal {...defaultProps} type="add" columnsSchema={columns} />,
      );

      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(screen.getByRole('alert')).toHaveTextContent('"ColName" is required');
      expect(mockCreateDatasetRow).not.toHaveBeenCalled();
    });
  });

  describe('Edit mode data pre-population', () => {
    it('pre-populates input value from rowData using heading key', () => {
      const columns = [makeColumn(1, 'Title', 1)];
      const rowData = { Title: 'Hello World', id: 42 };

      renderWithProviders(
        <AddAndEditDataModal
          {...defaultProps}
          type="edit"
          columnsSchema={columns}
          rowData={rowData}
        />,
      );

      expect(screen.getByDisplayValue('Hello World')).toBeInTheDocument();
    });
  });

  describe('API interaction', () => {
    it('calls createDatasetRow with column ID key in add mode', async () => {
      const user = userEvent.setup();
      const columns = [makeColumn(1, 'Name', 1)];

      renderWithProviders(
        <AddAndEditDataModal {...defaultProps} type="add" columnsSchema={columns} />,
      );

      await user.type(screen.getByRole('textbox', { name: 'Name' }), 'Test Value');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockCreateDatasetRow).toHaveBeenCalledWith('1', { '1': 'Test Value' });
      });
    });

    it('calls updateDatasetRow with row ID in edit mode', async () => {
      const user = userEvent.setup();
      const columns = [makeColumn(1, 'Name', 1)];
      const rowData = { Name: 'Old Value', id: 99 };

      renderWithProviders(
        <AddAndEditDataModal
          {...defaultProps}
          type="edit"
          columnsSchema={columns}
          rowData={rowData}
        />,
      );

      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockUpdateDatasetRow).toHaveBeenCalledWith('1', 99, expect.any(Object));
      });
    });

    it('calls onSave and onClose after successful save', async () => {
      const user = userEvent.setup();
      const mockOnSave = vi.fn();
      const mockOnClose = vi.fn();
      const columns = [makeColumn(1, 'Name', 1)];

      renderWithProviders(
        <AddAndEditDataModal
          type="add"
          datasetId="1"
          columnsSchema={columns}
          onClose={mockOnClose}
          onSave={mockOnSave}
        />,
      );

      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockOnSave).toHaveBeenCalledTimes(1);
        expect(mockOnClose).toHaveBeenCalledTimes(1);
      });
    });

    it('shows error alert when createDatasetRow rejects', async () => {
      const user = userEvent.setup();
      mockCreateDatasetRow.mockRejectedValue(new Error('Server error'));
      const columns = [makeColumn(1, 'Name', 1)];

      renderWithProviders(
        <AddAndEditDataModal {...defaultProps} type="add" columnsSchema={columns} />,
      );

      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(screen.getByRole('alert')).toBeInTheDocument();
      });
    });

    it('Save button shows "Saving…" while pending', async () => {
      const user = userEvent.setup();
      mockCreateDatasetRow.mockImplementation(() => new Promise(() => {}));
      const columns = [makeColumn(1, 'Name', 1)];

      renderWithProviders(
        <AddAndEditDataModal {...defaultProps} type="add" columnsSchema={columns} />,
      );

      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(screen.getByRole('button', { name: 'Saving…' })).toBeInTheDocument();
      });
    });

    it('Cancel button calls onClose without submitting', async () => {
      const user = userEvent.setup();
      const mockOnClose = vi.fn();
      const mockOnSave = vi.fn();
      const columns = [makeColumn(1, 'Name', 1)];

      renderWithProviders(
        <AddAndEditDataModal
          type="add"
          datasetId="1"
          columnsSchema={columns}
          onClose={mockOnClose}
          onSave={mockOnSave}
        />,
      );

      await user.click(screen.getByRole('button', { name: 'Cancel' }));

      expect(mockOnClose).toHaveBeenCalledTimes(1);
      expect(mockCreateDatasetRow).not.toHaveBeenCalled();
    });
  });
});
