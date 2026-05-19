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

import { renderWithProviders, mockMenuBoardCategory } from '../../../__tests__/MenuBoardSetup';
import AddAndEditMenuBoardCategoryModal from '../components/AddAndEditMenuBoardCategoryModal';

// -- Module mocks --

const mockCreateMenuBoardCategory = vi.fn();
const mockUpdateMenuBoardCategory = vi.fn();

vi.mock('@/services/menuBoardApi', () => ({
  createMenuBoardCategory: (...args: unknown[]) => mockCreateMenuBoardCategory(...args),
  updateMenuBoardCategory: (...args: unknown[]) => mockUpdateMenuBoardCategory(...args),
}));

vi.mock('@/components/ui/forms/MediaInput', () => ({
  default: ({ label }: { label: string }) => <div>{label}</div>,
}));

vi.mock('@/components/ui/modals/Modal');

// -- Helpers --

function renderAddModal(menuId: number | string = 1) {
  const mockOnClose = vi.fn();
  const mockOnSave = vi.fn();
  renderWithProviders(
    <AddAndEditMenuBoardCategoryModal
      type="add"
      menuId={menuId}
      onClose={mockOnClose}
      onSave={mockOnSave}
    />,
  );
  return { mockOnClose, mockOnSave };
}

function renderEditModal(overrides = {}) {
  const mockOnClose = vi.fn();
  const mockOnSave = vi.fn();
  const category = mockMenuBoardCategory(overrides);
  renderWithProviders(
    <AddAndEditMenuBoardCategoryModal
      type="edit"
      menuId={category.menuId}
      data={category}
      onClose={mockOnClose}
      onSave={mockOnSave}
    />,
  );
  return { mockOnClose, mockOnSave, category };
}

// -- Tests --

describe('AddAndEditMenuBoardCategoryModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // Scenarios 34, 35 — add with valid data
  describe('Add mode', () => {
    it('renders "Add Category" title with empty name and code fields', () => {
      renderAddModal();

      expect(screen.getByRole('dialog', { name: 'Add Category' })).toBeInTheDocument();
      expect(screen.getByLabelText('Name')).toHaveValue('');
      expect(screen.getByLabelText('Code', { exact: false })).toHaveValue('');
      expect(screen.getByLabelText('Description', { exact: false })).toHaveValue('');
    });

    it('calls createMenuBoardCategory with all provided fields on save', async () => {
      const user = userEvent.setup();
      mockCreateMenuBoardCategory.mockResolvedValue(mockMenuBoardCategory());
      const { mockOnSave } = renderAddModal(5);

      await user.type(screen.getByLabelText('Name'), 'Starters');
      await user.type(screen.getByLabelText('Code', { exact: false }), 'STR');
      await user.type(screen.getByLabelText('Description', { exact: false }), 'Starter dishes');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockCreateMenuBoardCategory).toHaveBeenCalledWith(
          5,
          expect.objectContaining({
            name: 'Starters',
            code: 'STR',
            description: 'Starter dishes',
          }),
        );
        expect(mockOnSave).toHaveBeenCalled();
      });
    });

    // Scenario 36 — empty name
    it('shows validation error when name is empty on save', async () => {
      const user = userEvent.setup();
      renderAddModal();

      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(screen.getByText('Name is required')).toBeInTheDocument();
      expect(mockCreateMenuBoardCategory).not.toHaveBeenCalled();
    });

    it('shows validation error when name is only whitespace', async () => {
      const user = userEvent.setup();
      renderAddModal();

      await user.type(screen.getByLabelText('Name'), '   ');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(screen.getByText('Name is required')).toBeInTheDocument();
      expect(mockCreateMenuBoardCategory).not.toHaveBeenCalled();
    });

    it('clears name error when user types a valid name', async () => {
      const user = userEvent.setup();
      renderAddModal();

      await user.click(screen.getByRole('button', { name: 'Save' }));
      expect(screen.getByText('Name is required')).toBeInTheDocument();

      await user.type(screen.getByLabelText('Name'), 'M');
      expect(screen.queryByText('Name is required')).not.toBeInTheDocument();
    });

    it('displays API error message when createMenuBoardCategory rejects', async () => {
      const user = userEvent.setup();
      mockCreateMenuBoardCategory.mockRejectedValue({
        response: { data: { message: 'Permission denied' } },
      });
      renderAddModal();

      await user.type(screen.getByLabelText('Name'), 'New Category');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(screen.getByText('Permission denied')).toBeInTheDocument();
      });
    });
  });

  // Scenarios 43, 44 — edit mode
  describe('Edit mode', () => {
    it('renders "Edit Category" title and pre-populates fields', () => {
      renderEditModal({ name: 'Mains', description: 'Main dishes', code: 'MN' });

      expect(screen.getByRole('dialog', { name: 'Edit Category' })).toBeInTheDocument();
      expect(screen.getByLabelText('Name')).toHaveValue('Mains');
      expect(screen.getByLabelText('Code', { exact: false })).toHaveValue('MN');
      expect(screen.getByLabelText('Description', { exact: false })).toHaveValue('Main dishes');
    });

    it('calls updateMenuBoardCategory with changed name on save', async () => {
      const user = userEvent.setup();
      mockUpdateMenuBoardCategory.mockResolvedValue(mockMenuBoardCategory({ name: 'Desserts' }));
      const { mockOnSave, category } = renderEditModal({ name: 'Old Name' });

      await user.clear(screen.getByLabelText('Name'));
      await user.type(screen.getByLabelText('Name'), 'Desserts');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockUpdateMenuBoardCategory).toHaveBeenCalledWith(
          category.menuCategoryId,
          expect.objectContaining({ name: 'Desserts' }),
        );
        expect(mockOnSave).toHaveBeenCalled();
      });
    });

    // Scenario 45 — view-only user gets 403 at the API level; the modal shows the error
    it('shows validation error when name is cleared during edit', async () => {
      const user = userEvent.setup();
      renderEditModal({ name: 'Drinks' });

      await user.clear(screen.getByLabelText('Name'));
      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(screen.getByText('Name is required')).toBeInTheDocument();
      expect(mockUpdateMenuBoardCategory).not.toHaveBeenCalled();
    });
  });

  describe('Cancel button', () => {
    it('calls onClose without calling the API', async () => {
      const user = userEvent.setup();
      const { mockOnClose } = renderAddModal();

      await user.click(screen.getByRole('button', { name: 'Cancel' }));

      expect(mockOnClose).toHaveBeenCalled();
      expect(mockCreateMenuBoardCategory).not.toHaveBeenCalled();
    });
  });
});
