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

import AddAndEditDatasetModal from '../components/AddAndEditDatasetModal';

import {
  renderWithProviders,
  mockDataset,
  mockRealTimeDataset,
  mockDataConnectorSources,
} from './DatasetSetup';

// -- Module mocks --

const mockFetchDataConnectorSource = vi.fn();
const mockCreateDataset = vi.fn();
const mockUpdateDataset = vi.fn();

vi.mock('@/services/datasetApi', () => ({
  fetchDataConnectorSource: (...args: unknown[]) => mockFetchDataConnectorSource(...args),
  createDataset: (...args: unknown[]) => mockCreateDataset(...args),
  updateDataset: (...args: unknown[]) => mockUpdateDataset(...args),
  testRemoteDataset: vi.fn(),
}));

vi.mock('@/hooks/usePermissions', () => ({
  usePermissions: () => ({ canViewFolders: false }),
}));

vi.mock('@/components/ui/forms/SelectFolder', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

// -- Helpers --

function renderAddModal() {
  const mockOnClose = vi.fn();
  const mockOnSave = vi.fn();
  renderWithProviders(
    <AddAndEditDatasetModal type="add" onClose={mockOnClose} onSave={mockOnSave} />,
  );
  return { mockOnClose, mockOnSave };
}

function renderEditModal(dataOverrides = {}) {
  const mockOnClose = vi.fn();
  const mockOnSave = vi.fn();
  const dataset = mockDataset(dataOverrides);
  renderWithProviders(
    <AddAndEditDatasetModal type="edit" data={dataset} onClose={mockOnClose} onSave={mockOnSave} />,
  );
  return { mockOnClose, mockOnSave, dataset };
}

// -- Tests --

describe('AddAndEditDatasetModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockFetchDataConnectorSource.mockResolvedValue([]);
  });

  describe('Add mode', () => {
    it('renders "Add Dataset" title with empty general tab fields', () => {
      renderAddModal();

      expect(screen.getByRole('dialog', { name: 'Add Dataset' })).toBeInTheDocument();
      expect(screen.getByLabelText('Name')).toHaveValue('');
      expect(screen.getByLabelText('Code')).toHaveValue('');
    });

    it('shows validation error for empty name on save', async () => {
      const user = userEvent.setup();
      renderAddModal();

      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(screen.getByText('Name is required')).toBeInTheDocument();
      expect(mockCreateDataset).not.toHaveBeenCalled();
    });

    it('calls fetchDataConnectorSource on mount', async () => {
      renderAddModal();

      await waitFor(() => {
        expect(mockFetchDataConnectorSource).toHaveBeenCalledTimes(1);
      });
    });
  });

  describe('Edit mode', () => {
    it('renders "Edit Dataset" title and pre-populates fields from data', () => {
      renderEditModal({
        dataSet: 'Sales Data',
        description: 'Monthly sales',
        code: 'SALES_01',
      });

      expect(screen.getByRole('dialog', { name: 'Edit Dataset' })).toBeInTheDocument();
      expect(screen.getByLabelText('Name')).toHaveValue('Sales Data');
      expect(screen.getByLabelText('Description')).toHaveValue('Monthly sales');
      expect(screen.getByLabelText('Code')).toHaveValue('SALES_01');
    });
  });

  describe('Real-time dataset', () => {
    it('hides Data Connector Source dropdown by default', () => {
      renderAddModal();

      expect(screen.queryByText('Data Connector Source')).not.toBeInTheDocument();
    });

    it('shows Data Connector Source dropdown when Real-time is checked', async () => {
      const user = userEvent.setup();
      mockFetchDataConnectorSource.mockResolvedValue(mockDataConnectorSources);

      renderAddModal();

      await user.click(screen.getByRole('checkbox', { name: /Real-time/i }));

      expect(screen.getByText('Data Connector Source')).toBeInTheDocument();
    });

    it('hides Data Connector Source dropdown when Real-time is unchecked again', async () => {
      const user = userEvent.setup();
      renderAddModal();

      const checkbox = screen.getByRole('checkbox', {
        name: /Real-time/i,
      });
      await user.click(checkbox);
      await user.click(checkbox);

      expect(screen.queryByText('Data Connector Source')).not.toBeInTheDocument();
    });

    it('pre-populates Data Connector Source for an existing real-time dataset', async () => {
      mockFetchDataConnectorSource.mockResolvedValue(mockDataConnectorSources);
      const dataset = mockRealTimeDataset();

      const mockOnClose = vi.fn();
      const mockOnSave = vi.fn();
      renderWithProviders(
        <AddAndEditDatasetModal
          type="edit"
          data={dataset}
          onClose={mockOnClose}
          onSave={mockOnSave}
        />,
      );

      await waitFor(() => {
        expect(screen.getByText('Data Connector Source')).toBeInTheDocument();
      });
    });
  });

  describe('Remote dataset', () => {
    it('hides remote tabs when Remote is unchecked', () => {
      renderAddModal();

      expect(screen.queryByRole('button', { name: 'Remote' })).not.toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Authentication' })).not.toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Data' })).not.toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Advanced' })).not.toBeInTheDocument();
    });

    it('shows Remote, Authentication, Data, and Advanced tabs when Remote is checked', async () => {
      const user = userEvent.setup();
      renderAddModal();

      await user.click(screen.getByRole('checkbox', { name: /^Remote$/i }));

      expect(screen.getByRole('button', { name: 'Remote' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Authentication' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Data' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Advanced' })).toBeInTheDocument();
    });

    it('hides extra tabs when Remote is unchecked again', async () => {
      const user = userEvent.setup();
      renderAddModal();

      const remoteCheckbox = screen.getByRole('checkbox', {
        name: /^Remote$/i,
      });
      await user.click(remoteCheckbox);
      await user.click(remoteCheckbox);

      expect(screen.queryByRole('button', { name: 'Remote' })).not.toBeInTheDocument();
    });

    it('shows URI validation error when Remote is checked and URI is empty on save', async () => {
      const user = userEvent.setup();
      renderAddModal();

      await user.type(screen.getByLabelText('Name'), 'My Remote Dataset');
      await user.click(screen.getByRole('checkbox', { name: /^Remote$/i }));

      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(screen.getByText('URI is required for remote datasets.')).toBeInTheDocument();
      });
      expect(mockCreateDataset).not.toHaveBeenCalled();
    });
  });
});
