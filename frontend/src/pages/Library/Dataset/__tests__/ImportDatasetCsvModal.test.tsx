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

import { act, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';

import ImportDatasetCsvModal from '../components/ImportDatasetCsvModal';

import { renderWithProviders } from './DatasetSetup';

// -- Module mocks --

let capturedOnDrop: ((files: File[]) => void) | undefined;

vi.mock('react-dropzone', () => ({
  useDropzone: ({ onDrop }: { onDrop: (files: File[]) => void }) => {
    capturedOnDrop = onDrop;
    return {
      getRootProps: () => ({ 'data-testid': 'dropzone' }),
      getInputProps: () => ({}),
      isDragActive: false,
    };
  },
}));

const mockFetchDatasetColumns = vi.fn();
const mockImportDatasetCsv = vi.fn();

vi.mock('@/services/datasetApi', () => ({
  fetchDatasetColumns: (...args: unknown[]) => mockFetchDatasetColumns(...args),
  importDatasetCsv: (...args: unknown[]) => mockImportDatasetCsv(...args),
}));

const mockNotifySuccess = vi.fn();
const mockNotifyError = vi.fn();

vi.mock('@/components/ui/Notification', () => ({
  notify: {
    success: (...args: unknown[]) => mockNotifySuccess(...args),
    error: (...args: unknown[]) => mockNotifyError(...args),
  },
}));

vi.mock('@/components/ui/modals/Modal');

// -- Helpers --

const defaultProps = {
  datasetId: 5,
  onClose: vi.fn(),
  onSuccess: vi.fn(),
};

function makeFile(name = 'data.csv', content = 'col1,col2') {
  return new File([content], name, { type: 'text/csv' });
}

// -- Tests --

describe('ImportDatasetCsvModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    capturedOnDrop = undefined;
    mockFetchDatasetColumns.mockResolvedValue({ rows: [], recordsTotal: 0 });
    mockImportDatasetCsv.mockResolvedValue({});
  });

  describe('Initial render', () => {
    it('renders "CSV Import" dialog title', () => {
      renderWithProviders(<ImportDatasetCsvModal {...defaultProps} />);

      expect(screen.getByRole('dialog', { name: 'CSV Import' })).toBeInTheDocument();
    });

    it('shows the "Add CSV File" dropzone area initially', () => {
      renderWithProviders(<ImportDatasetCsvModal {...defaultProps} />);

      expect(screen.getByText('Add CSV File')).toBeInTheDocument();
    });

    it('Done button is disabled when no file is selected', () => {
      renderWithProviders(<ImportDatasetCsvModal {...defaultProps} />);

      expect(screen.getByRole('button', { name: 'Done' })).toBeDisabled();
    });

    it('"Overwrite existing data?" checkbox is unchecked by default', () => {
      renderWithProviders(<ImportDatasetCsvModal {...defaultProps} />);

      const checkbox = screen.getByRole('checkbox', { name: /Overwrite existing data/i });
      expect(checkbox).not.toBeChecked();
    });

    it('"Ignore first row?" checkbox is checked by default', () => {
      renderWithProviders(<ImportDatasetCsvModal {...defaultProps} />);

      const checkbox = screen.getByRole('checkbox', { name: /Ignore first row/i });
      expect(checkbox).toBeChecked();
    });
  });

  describe('Column fetching', () => {
    it('calls fetchDatasetColumns on mount', async () => {
      renderWithProviders(<ImportDatasetCsvModal {...defaultProps} />);

      await waitFor(() => {
        expect(mockFetchDatasetColumns).toHaveBeenCalledWith(5, { start: 0, length: 100 });
      });
    });

    it('renders mapping inputs for Value-type columns returned', async () => {
      mockFetchDatasetColumns.mockResolvedValue({
        rows: [
          { dataSetColumnId: 1, heading: 'Name', dataSetColumnTypeId: 1 },
          { dataSetColumnId: 2, heading: 'Price', dataSetColumnTypeId: 1 },
        ],
        recordsTotal: 2,
      });

      renderWithProviders(<ImportDatasetCsvModal {...defaultProps} />);

      await waitFor(() => {
        expect(screen.getByText('Name')).toBeInTheDocument();
        expect(screen.getByText('Price')).toBeInTheDocument();
      });
    });

    it('does not render mapping section when no columns', () => {
      mockFetchDatasetColumns.mockResolvedValue({ rows: [], recordsTotal: 0 });

      renderWithProviders(<ImportDatasetCsvModal {...defaultProps} />);

      expect(screen.queryByText(/enter the column number/i)).not.toBeInTheDocument();
    });
  });

  describe('File drop', () => {
    it('shows file info after a file is dropped', async () => {
      renderWithProviders(<ImportDatasetCsvModal {...defaultProps} />);

      const file = makeFile('products.csv');
      act(() => capturedOnDrop?.([file]));

      await screen.findByText('products.csv');
    });

    it('Done button is enabled after a file is dropped', async () => {
      renderWithProviders(<ImportDatasetCsvModal {...defaultProps} />);

      const file = makeFile('products.csv');
      act(() => capturedOnDrop?.([file]));

      await waitFor(() => {
        expect(screen.getByRole('button', { name: 'Done' })).not.toBeDisabled();
      });
    });

    it('hides the dropzone after a file is selected', async () => {
      renderWithProviders(<ImportDatasetCsvModal {...defaultProps} />);

      act(() => capturedOnDrop?.([makeFile()]));

      await waitFor(() => {
        expect(screen.queryByText('Add CSV File')).not.toBeInTheDocument();
      });
    });
  });

  describe('Import action', () => {
    it('calls importDatasetCsv with the selected file on Done click', async () => {
      const user = userEvent.setup();
      renderWithProviders(<ImportDatasetCsvModal {...defaultProps} />);

      const file = makeFile('data.csv');
      act(() => capturedOnDrop?.([file]));

      await waitFor(() => {
        expect(screen.getByRole('button', { name: 'Done' })).not.toBeDisabled();
      });

      await user.click(screen.getByRole('button', { name: 'Done' }));

      await waitFor(() => {
        expect(mockImportDatasetCsv).toHaveBeenCalledWith(
          5,
          expect.objectContaining({ file }),
          expect.any(Function),
        );
      });
    });

    it('shows success notification on successful import', async () => {
      const user = userEvent.setup();
      renderWithProviders(<ImportDatasetCsvModal {...defaultProps} />);

      act(() => capturedOnDrop?.([makeFile()]));

      await waitFor(() => expect(screen.getByRole('button', { name: 'Done' })).not.toBeDisabled());

      await user.click(screen.getByRole('button', { name: 'Done' }));

      await waitFor(() => {
        expect(mockNotifySuccess).toHaveBeenCalledWith('CSV Imported successfully');
      });
    });

    it('shows error notification when import fails', async () => {
      const user = userEvent.setup();
      mockImportDatasetCsv.mockRejectedValue(new Error('Upload failed'));
      renderWithProviders(<ImportDatasetCsvModal {...defaultProps} />);

      act(() => capturedOnDrop?.([makeFile()]));

      await waitFor(() => expect(screen.getByRole('button', { name: 'Done' })).not.toBeDisabled());

      await user.click(screen.getByRole('button', { name: 'Done' }));

      await waitFor(() => {
        expect(mockNotifyError).toHaveBeenCalled();
      });
    });
  });

  describe('Cancel / close', () => {
    it('Cancel button calls onClose', async () => {
      const user = userEvent.setup();
      const mockOnClose = vi.fn();
      renderWithProviders(<ImportDatasetCsvModal {...defaultProps} onClose={mockOnClose} />);

      await user.click(screen.getByRole('button', { name: 'Cancel' }));

      expect(mockOnClose).toHaveBeenCalledTimes(1);
    });

    it('passes overwrite: true when checkbox is checked', async () => {
      const user = userEvent.setup();
      renderWithProviders(<ImportDatasetCsvModal {...defaultProps} />);

      await user.click(screen.getByRole('checkbox', { name: /Overwrite existing data/i }));

      act(() => capturedOnDrop?.([makeFile()]));

      await waitFor(() => expect(screen.getByRole('button', { name: 'Done' })).not.toBeDisabled());

      await user.click(screen.getByRole('button', { name: 'Done' }));

      await waitFor(() => {
        expect(mockImportDatasetCsv).toHaveBeenCalledWith(
          5,
          expect.objectContaining({ overwrite: true }),
          expect.any(Function),
        );
      });
    });
  });
});
