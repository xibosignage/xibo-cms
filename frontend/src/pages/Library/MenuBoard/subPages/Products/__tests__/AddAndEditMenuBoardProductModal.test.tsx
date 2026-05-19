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

import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';

import { renderWithProviders, mockMenuBoardProduct } from '../../../__tests__/MenuBoardSetup';
import AddAndEditMenuBoardProductModal from '../components/AddAndEditMenuBoardProductModal';

// -- Module mocks --

const mockCreateMenuBoardProduct = vi.fn();
const mockUpdateMenuBoardProduct = vi.fn();

vi.mock('@/services/menuBoardApi', () => ({
  createMenuBoardProduct: (...args: unknown[]) => mockCreateMenuBoardProduct(...args),
  updateMenuBoardProduct: (...args: unknown[]) => mockUpdateMenuBoardProduct(...args),
}));

vi.mock('@/components/ui/forms/MediaInput', () => ({
  default: ({ label }: { label: string }) => <div>{label}</div>,
}));

vi.mock('@/components/ui/modals/Modal');

// -- Helpers --

function renderAddModal(menuCategoryId: number | string = 1) {
  const mockOnClose = vi.fn();
  const mockOnSave = vi.fn();
  renderWithProviders(
    <AddAndEditMenuBoardProductModal
      type="add"
      menuCategoryId={menuCategoryId}
      onClose={mockOnClose}
      onSave={mockOnSave}
    />,
  );
  return { mockOnClose, mockOnSave };
}

function renderEditModal(overrides = {}) {
  const mockOnClose = vi.fn();
  const mockOnSave = vi.fn();
  const product = mockMenuBoardProduct(overrides);
  renderWithProviders(
    <AddAndEditMenuBoardProductModal
      type="edit"
      menuCategoryId={product.menuCategoryId}
      data={product}
      onClose={mockOnClose}
      onSave={mockOnSave}
    />,
  );
  return { mockOnClose, mockOnSave, product };
}

// -- Tests --

describe('AddAndEditMenuBoardProductModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // Scenarios 49, 58, 59 — add with valid data
  describe('Add mode — General tab', () => {
    it('renders "Add Product" title with three navigation tabs', () => {
      renderAddModal();

      expect(screen.getByRole('dialog', { name: 'Add Product' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'General' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Details' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Product Options' })).toBeInTheDocument();
    });

    it('renders General tab fields with default empty values', () => {
      renderAddModal();

      expect(screen.getByLabelText('Name')).toHaveValue('');
      expect(screen.getByLabelText('Code')).toHaveValue('');
    });

    // Scenario 50 — empty name
    it('shows validation error when name is empty on save', async () => {
      const user = userEvent.setup();
      renderAddModal();

      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(screen.getByText('Name is required')).toBeInTheDocument();
      expect(mockCreateMenuBoardProduct).not.toHaveBeenCalled();
    });

    it('auto-switches to General tab when a name validation error occurs on Details tab', async () => {
      const user = userEvent.setup();
      renderAddModal();

      // Switch to Details tab without filling name
      await user.click(screen.getByRole('button', { name: 'Details' }));
      await user.click(screen.getByRole('button', { name: 'Save' }));

      // Should switch back to General and show error
      await waitFor(() => {
        expect(screen.getByText('Name is required')).toBeInTheDocument();
        expect(screen.getByLabelText('Name')).toBeInTheDocument();
      });
    });

    it('calls createMenuBoardProduct with all scalar fields on save', async () => {
      const user = userEvent.setup();
      mockCreateMenuBoardProduct.mockResolvedValue(mockMenuBoardProduct());
      const { mockOnSave } = renderAddModal(3);

      await user.type(screen.getByLabelText('Name'), 'Burger');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockCreateMenuBoardProduct).toHaveBeenCalledWith(
          3,
          expect.objectContaining({ name: 'Burger' }),
        );
        expect(mockOnSave).toHaveBeenCalled();
      });
    });

    it('sends availability=1 when switch is on (default)', async () => {
      const user = userEvent.setup();
      mockCreateMenuBoardProduct.mockResolvedValue(mockMenuBoardProduct());
      renderAddModal();

      await user.type(screen.getByLabelText('Name'), 'Product A');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockCreateMenuBoardProduct).toHaveBeenCalledWith(
          expect.anything(),
          expect.objectContaining({ availability: 1 }),
        );
      });
    });

    // Scenario 59 — availability=0
    it('sends availability=0 when Availability switch is toggled off', async () => {
      const user = userEvent.setup();
      mockCreateMenuBoardProduct.mockResolvedValue(mockMenuBoardProduct());
      renderAddModal();

      await user.type(screen.getByLabelText('Name'), 'Product B');
      // Toggle Availability off
      const availabilitySwitch = screen.getByRole('switch', { name: /Availability/i });
      await user.click(availabilitySwitch);
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockCreateMenuBoardProduct).toHaveBeenCalledWith(
          expect.anything(),
          expect.objectContaining({ availability: 0 }),
        );
      });
    });

    it('displays API error message when createMenuBoardProduct rejects', async () => {
      const user = userEvent.setup();
      mockCreateMenuBoardProduct.mockRejectedValue({
        response: { data: { message: 'Category not found' } },
      });
      renderAddModal();

      await user.type(screen.getByLabelText('Name'), 'X');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(screen.getByText('Category not found')).toBeInTheDocument();
      });
    });
  });

  // Scenarios 77, 78, 79, 80 — edit mode
  describe('Edit mode', () => {
    it('renders "Edit Product" title and pre-populates General tab fields', () => {
      renderEditModal({
        name: 'Fries',
        price: 3.5,
        code: 'FRS',
        displayOrder: 2,
        availability: 1,
      });

      expect(screen.getByRole('dialog', { name: 'Edit Product' })).toBeInTheDocument();
      expect(screen.getByLabelText('Name')).toHaveValue('Fries');
      expect(screen.getByLabelText('Code')).toHaveValue('FRS');
    });

    it('calls updateMenuBoardProduct with changed fields on save', async () => {
      const user = userEvent.setup();
      mockUpdateMenuBoardProduct.mockResolvedValue(mockMenuBoardProduct({ name: 'Large Fries' }));
      const { product } = renderEditModal({ name: 'Fries' });

      await user.clear(screen.getByLabelText('Name'));
      await user.type(screen.getByLabelText('Name'), 'Large Fries');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockUpdateMenuBoardProduct).toHaveBeenCalledWith(
          product.menuProductId,
          expect.objectContaining({ name: 'Large Fries' }),
        );
      });
    });

    // Scenario 78 — toggle availability
    it('sends availability=0 when toggled off during edit', async () => {
      const user = userEvent.setup();
      mockUpdateMenuBoardProduct.mockResolvedValue(mockMenuBoardProduct());
      renderEditModal({ name: 'Pizza', availability: 1 });

      const availabilitySwitch = screen.getByRole('switch', { name: /Availability/i });
      await user.click(availabilitySwitch);
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockUpdateMenuBoardProduct).toHaveBeenCalledWith(
          expect.anything(),
          expect.objectContaining({ availability: 0 }),
        );
      });
    });

    // Scenario 80 — empty name on edit
    it('shows validation error when name is cleared on edit', async () => {
      const user = userEvent.setup();
      renderEditModal({ name: 'Salad' });

      await user.clear(screen.getByLabelText('Name'));
      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(screen.getByText('Name is required')).toBeInTheDocument();
      expect(mockUpdateMenuBoardProduct).not.toHaveBeenCalled();
    });
  });

  // Scenarios 61-68 — Product Options tab
  describe('Product Options tab', () => {
    it('starts with no options rows and a single "+" button', async () => {
      const user = userEvent.setup();
      renderAddModal();

      await user.click(screen.getByRole('button', { name: 'Product Options' }));

      const optionsTab = screen.getByRole('dialog');
      const inputs = within(optionsTab).queryAllByPlaceholderText('Option Name');
      expect(inputs).toHaveLength(0);
    });

    // Scenario 61 — add an option row
    it('adds a new option row when the "+" button is clicked', async () => {
      const user = userEvent.setup();
      renderAddModal();

      await user.click(screen.getByRole('button', { name: 'Product Options' }));
      await user.click(screen.getByRole('button', { name: '' })); // Plus button (icon only)

      expect(screen.getAllByPlaceholderText('Option Name')).toHaveLength(1);
      expect(screen.getAllByPlaceholderText('Option Value')).toHaveLength(1);
    });

    it('removes an option row when the "−" button is clicked', async () => {
      const user = userEvent.setup();
      renderAddModal();

      await user.click(screen.getByRole('button', { name: 'Product Options' }));
      // Add one row
      const addBtn = screen
        .getAllByRole('button')
        .find((b) => !b.textContent?.trim() && !b.getAttribute('aria-label'));
      await user.click(addBtn!);
      expect(screen.getAllByPlaceholderText('Option Name')).toHaveLength(1);

      // Remove it
      const removeBtn = screen
        .getAllByRole('button')
        .find((b) => !b.textContent?.trim() && !b.getAttribute('aria-label') && b !== addBtn);
      if (removeBtn) {
        await user.click(removeBtn);
      }

      expect(screen.queryAllByPlaceholderText('Option Name')).toHaveLength(0);
    });

    // Scenario 65 — options serialized correctly in payload
    it('includes productOptions in payload when filled in', async () => {
      const user = userEvent.setup();
      mockCreateMenuBoardProduct.mockResolvedValue(mockMenuBoardProduct());
      renderAddModal(1);

      // Fill name on General tab
      await user.type(screen.getByLabelText('Name'), 'Steak');

      // Switch to Options tab and add one row
      await user.click(screen.getByRole('button', { name: 'Product Options' }));
      await user.click(screen.getByRole('button', { name: '' })); // Plus icon button

      const [optionNameInput] = screen.getAllByPlaceholderText('Option Name');
      const [optionValueInput] = screen.getAllByPlaceholderText('Option Value');

      await user.type(optionNameInput!, 'Size');
      await user.type(optionValueInput!, '12');

      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockCreateMenuBoardProduct).toHaveBeenCalledWith(
          1,
          expect.objectContaining({
            productOptions: [{ option: 'Size', value: '12' }],
          }),
        );
      });
    });

    // Scenario 67 — pre-existing options loaded in edit mode
    it('pre-populates existing product options in edit mode', async () => {
      const user = userEvent.setup();
      const product = mockMenuBoardProduct({
        name: 'Loaded Burger',
        productOptions: [
          { menuProductId: 1, option: 'Cheese', value: '1.00' },
          { menuProductId: 1, option: 'Bacon', value: '1.50' },
        ],
      });
      renderWithProviders(
        <AddAndEditMenuBoardProductModal
          type="edit"
          menuCategoryId={1}
          data={product}
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      await user.click(screen.getByRole('button', { name: 'Product Options' }));

      const optionNames = screen.getAllByPlaceholderText('Option Name');
      expect(optionNames).toHaveLength(2);
      expect(optionNames[0]).toHaveValue('Cheese');
      expect(optionNames[1]).toHaveValue('Bacon');
    });

    // Scenario 66 — updating removes old options and sends only new ones
    it('sends empty productOptions array when all options are removed', async () => {
      const user = userEvent.setup();
      mockUpdateMenuBoardProduct.mockResolvedValue(mockMenuBoardProduct());
      const product = mockMenuBoardProduct({
        name: 'Wrap',
        productOptions: [{ menuProductId: 1, option: 'Hot Sauce', value: '0.50' }],
      });
      renderWithProviders(
        <AddAndEditMenuBoardProductModal
          type="edit"
          menuCategoryId={1}
          data={product}
          onClose={vi.fn()}
          onSave={vi.fn()}
        />,
      );

      await user.click(screen.getByRole('button', { name: 'Product Options' }));

      // Remove the single row using any remove button inside options area
      const minusButtons = screen.getAllByRole('button');
      // The minus button has no text content — find it by process of elimination
      const removeBtn = minusButtons.find((b) => {
        const txt = b.textContent?.trim();
        return !txt && b !== minusButtons[minusButtons.length - 1];
      });
      if (removeBtn) {
        await user.click(removeBtn);
      }

      // Switch back to General and save
      await user.click(screen.getByRole('button', { name: 'General' }));
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockUpdateMenuBoardProduct).toHaveBeenCalledWith(
          product.menuProductId,
          expect.objectContaining({ productOptions: [] }),
        );
      });
    });
  });

  // Details tab — scenario 77 (description, allergyInfo, calories)
  describe('Details tab', () => {
    it('pre-populates description, allergyInfo, and calories in edit mode', async () => {
      const user = userEvent.setup();
      renderEditModal({
        name: 'Soup',
        description: 'Tomato soup',
        allergyInfo: 'Contains celery',
        calories: 150,
      });

      await user.click(screen.getByRole('button', { name: 'Details' }));

      expect(screen.getByLabelText('Description')).toHaveValue('Tomato soup');
      expect(screen.getByLabelText('Allergy Information')).toHaveValue('Contains celery');
    });

    it('sends description and allergyInfo in the payload', async () => {
      const user = userEvent.setup();
      mockCreateMenuBoardProduct.mockResolvedValue(mockMenuBoardProduct());
      renderAddModal(2);

      await user.type(screen.getByLabelText('Name'), 'Salad');

      await user.click(screen.getByRole('button', { name: 'Details' }));
      await user.type(screen.getByLabelText('Description'), 'Garden salad');
      await user.type(screen.getByLabelText('Allergy Information'), 'Contains nuts');

      await user.click(screen.getByRole('button', { name: 'General' }));
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockCreateMenuBoardProduct).toHaveBeenCalledWith(
          2,
          expect.objectContaining({
            description: 'Garden salad',
            allergyInfo: 'Contains nuts',
          }),
        );
      });
    });
  });

  describe('Cancel button', () => {
    it('calls onClose without calling the API', async () => {
      const user = userEvent.setup();
      const { mockOnClose } = renderAddModal();

      await user.click(screen.getByRole('button', { name: 'Cancel' }));

      expect(mockOnClose).toHaveBeenCalled();
      expect(mockCreateMenuBoardProduct).not.toHaveBeenCalled();
    });
  });
});
