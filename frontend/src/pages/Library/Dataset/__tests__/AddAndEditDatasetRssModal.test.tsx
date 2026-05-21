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

import { AddAndEditDatasetRssModal } from '../subPages/Rss/components/AddAndEditDatasetRssModal';

import { renderWithProviders } from './DatasetSetup';

import type { DatasetRss } from '@/types/datasetRss';

// -- Module mocks --

const mockCreateDatasetRss = vi.fn();
const mockUpdateDatasetRss = vi.fn();
const mockFetchDatasetColumns = vi.fn();

vi.mock('@/services/datasetApi', () => ({
  createDatasetRss: (...args: unknown[]) => mockCreateDatasetRss(...args),
  updateDatasetRss: (...args: unknown[]) => mockUpdateDatasetRss(...args),
  fetchDatasetColumns: (...args: unknown[]) => mockFetchDatasetColumns(...args),
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

const mockRss: DatasetRss = {
  id: 10,
  dataSetId: 1,
  title: 'My RSS Feed',
  author: 'Author Name',
};

// -- Tests --

describe('AddAndEditDatasetRssModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockCreateDatasetRss.mockResolvedValue({});
    mockUpdateDatasetRss.mockResolvedValue({});
    mockFetchDatasetColumns.mockResolvedValue({ rows: [], recordsTotal: 0 });
  });

  describe('Add mode', () => {
    it('renders "Add RSS" dialog title', () => {
      renderWithProviders(
        <AddAndEditDatasetRssModal type="add" datasetId="1" onClose={vi.fn()} onSave={vi.fn()} />,
      );

      expect(screen.getByRole('dialog', { name: 'Add RSS' })).toBeInTheDocument();
    });

    it('shows General, Order, and Filter tab buttons', () => {
      renderWithProviders(
        <AddAndEditDatasetRssModal type="add" datasetId="1" onClose={vi.fn()} onSave={vi.fn()} />,
      );

      expect(screen.getByRole('button', { name: 'General' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Order' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Filter' })).toBeInTheDocument();
    });

    it('Title and Author inputs are visible on General tab', () => {
      renderWithProviders(
        <AddAndEditDatasetRssModal type="add" datasetId="1" onClose={vi.fn()} onSave={vi.fn()} />,
      );

      expect(screen.getByLabelText('Title')).toBeInTheDocument();
      expect(screen.getByLabelText('Author')).toBeInTheDocument();
    });

    it('shows title error when title is empty on save', async () => {
      const user = userEvent.setup();
      renderWithProviders(
        <AddAndEditDatasetRssModal type="add" datasetId="1" onClose={vi.fn()} onSave={vi.fn()} />,
      );

      await user.type(screen.getByLabelText('Author'), 'SomeAuthor');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(screen.getByText('Please enter title')).toBeInTheDocument();
      expect(mockCreateDatasetRss).not.toHaveBeenCalled();
    });

    it('shows author error when author is empty on save', async () => {
      const user = userEvent.setup();
      renderWithProviders(
        <AddAndEditDatasetRssModal type="add" datasetId="1" onClose={vi.fn()} onSave={vi.fn()} />,
      );

      await user.type(screen.getByLabelText('Title'), 'Some Title');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(screen.getByText('Please enter author name')).toBeInTheDocument();
      expect(mockCreateDatasetRss).not.toHaveBeenCalled();
    });

    it('calls createDatasetRss on valid submit', async () => {
      const user = userEvent.setup();
      renderWithProviders(
        <AddAndEditDatasetRssModal type="add" datasetId="1" onClose={vi.fn()} onSave={vi.fn()} />,
      );

      await user.type(screen.getByLabelText('Title'), 'My Feed');
      await user.type(screen.getByLabelText('Author'), 'Feed Author');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockCreateDatasetRss).toHaveBeenCalledWith(
          '1',
          expect.objectContaining({ title: 'My Feed', author: 'Feed Author' }),
        );
      });
    });

    it('calls onSave and onClose after successful create', async () => {
      const user = userEvent.setup();
      const mockOnSave = vi.fn();
      const mockOnClose = vi.fn();
      renderWithProviders(
        <AddAndEditDatasetRssModal
          type="add"
          datasetId="1"
          onClose={mockOnClose}
          onSave={mockOnSave}
        />,
      );

      await user.type(screen.getByLabelText('Title'), 'My Feed');
      await user.type(screen.getByLabelText('Author'), 'Feed Author');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockOnSave).toHaveBeenCalledTimes(1);
        expect(mockOnClose).toHaveBeenCalledTimes(1);
      });
    });

    it('Cancel button calls onClose without submitting', async () => {
      const user = userEvent.setup();
      const mockOnClose = vi.fn();
      renderWithProviders(
        <AddAndEditDatasetRssModal
          type="add"
          datasetId="1"
          onClose={mockOnClose}
          onSave={vi.fn()}
        />,
      );

      await user.click(screen.getByRole('button', { name: 'Cancel' }));

      expect(mockOnClose).toHaveBeenCalledTimes(1);
      expect(mockCreateDatasetRss).not.toHaveBeenCalled();
    });

    it('shows error alert when createDatasetRss rejects', async () => {
      const user = userEvent.setup();
      mockCreateDatasetRss.mockRejectedValue(new Error('Server error'));
      renderWithProviders(
        <AddAndEditDatasetRssModal type="add" datasetId="1" onClose={vi.fn()} onSave={vi.fn()} />,
      );

      await user.type(screen.getByLabelText('Title'), 'My Feed');
      await user.type(screen.getByLabelText('Author'), 'Feed Author');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(screen.getByRole('alert')).toBeInTheDocument();
      });
    });
  });

  describe('Edit mode', () => {
    it('renders "Edit RSS" dialog title', () => {
      renderWithProviders(
        <AddAndEditDatasetRssModal
          type="edit"
          datasetId="1"
          rss={mockRss}
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      expect(screen.getByRole('dialog', { name: 'Edit RSS' })).toBeInTheDocument();
    });

    it('pre-populates title and author from rss data', () => {
      renderWithProviders(
        <AddAndEditDatasetRssModal
          type="edit"
          datasetId="1"
          rss={mockRss}
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      expect(screen.getByLabelText('Title')).toHaveValue('My RSS Feed');
      expect(screen.getByLabelText('Author')).toHaveValue('Author Name');
    });

    it('shows regeneratePsk checkbox in edit mode', () => {
      renderWithProviders(
        <AddAndEditDatasetRssModal
          type="edit"
          datasetId="1"
          rss={mockRss}
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      expect(screen.getByRole('checkbox', { name: /Security/i })).toBeInTheDocument();
    });

    it('does NOT show regeneratePsk checkbox in add mode', () => {
      renderWithProviders(
        <AddAndEditDatasetRssModal type="add" datasetId="1" onClose={vi.fn()} onSave={vi.fn()} />,
      );

      expect(screen.queryByRole('checkbox', { name: /Security/i })).not.toBeInTheDocument();
    });

    it('calls updateDatasetRss with rss id on valid submit', async () => {
      const user = userEvent.setup();
      renderWithProviders(
        <AddAndEditDatasetRssModal
          type="edit"
          datasetId="1"
          rss={mockRss}
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockUpdateDatasetRss).toHaveBeenCalledWith('1', 10, expect.any(Object));
      });
    });
  });

  describe('Tab navigation', () => {
    it('clicking Order tab shows order-related content', async () => {
      const user = userEvent.setup();
      renderWithProviders(
        <AddAndEditDatasetRssModal type="add" datasetId="1" onClose={vi.fn()} onSave={vi.fn()} />,
      );

      await user.click(screen.getByRole('button', { name: 'Order' }));

      expect(screen.getByRole('checkbox', { name: /Advanced Order Clause/i })).toBeInTheDocument();
    });

    it('clicking Filter tab shows filter-related content', async () => {
      const user = userEvent.setup();
      renderWithProviders(
        <AddAndEditDatasetRssModal type="add" datasetId="1" onClose={vi.fn()} onSave={vi.fn()} />,
      );

      await user.click(screen.getByRole('button', { name: 'Filter' }));

      expect(screen.getByRole('checkbox', { name: /Advanced Filter Clause/i })).toBeInTheDocument();
    });

    it('Order tab shows SQL input when Advanced Order Clause is checked', async () => {
      const user = userEvent.setup();
      renderWithProviders(
        <AddAndEditDatasetRssModal type="add" datasetId="1" onClose={vi.fn()} onSave={vi.fn()} />,
      );

      await user.click(screen.getByRole('button', { name: 'Order' }));
      await user.click(screen.getByRole('checkbox', { name: /Advanced Order Clause/i }));

      expect(screen.getByLabelText('Order (SQL)')).toBeInTheDocument();
    });

    it('Filter tab shows SQL input when Advanced Filter Clause is checked', async () => {
      const user = userEvent.setup();
      renderWithProviders(
        <AddAndEditDatasetRssModal type="add" datasetId="1" onClose={vi.fn()} onSave={vi.fn()} />,
      );

      await user.click(screen.getByRole('button', { name: 'Filter' }));
      await user.click(screen.getByRole('checkbox', { name: /Advanced Filter Clause/i }));

      expect(screen.getByLabelText('Filter (SQL)')).toBeInTheDocument();
    });
  });
});
