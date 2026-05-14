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

import AddAndEditMenuBoardModal from '../components/AddAndEditMenuBoardModal';

import { renderWithProviders, mockMenuBoard } from './MenuBoardSetup';

// -- Module mocks --

const mockCreateMenuBoard = vi.fn();
const mockUpdateMenuBoard = vi.fn();

vi.mock('@/services/menuBoardApi', () => ({
  createMenuBoard: (...args: unknown[]) => mockCreateMenuBoard(...args),
  updateMenuBoard: (...args: unknown[]) => mockUpdateMenuBoard(...args),
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
    <AddAndEditMenuBoardModal type="add" onClose={mockOnClose} onSave={mockOnSave} />,
  );
  return { mockOnClose, mockOnSave };
}

function renderEditModal(dataOverrides = {}) {
  const mockOnClose = vi.fn();
  const mockOnSave = vi.fn();
  const board = mockMenuBoard(dataOverrides);
  renderWithProviders(
    <AddAndEditMenuBoardModal
      type="edit"
      data={board}
      onClose={mockOnClose}
      onSave={mockOnSave}
    />,
  );
  return { mockOnClose, mockOnSave, board };
}

// -- Tests --

describe('AddAndEditMenuBoardModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // Scenarios 1, 2 — add with valid data
  describe('Add mode', () => {
    it('renders "Add Menu Board" title with empty fields', () => {
      renderAddModal();

      expect(screen.getByRole('dialog', { name: 'Add Menu Board' })).toBeInTheDocument();
      expect(screen.getByLabelText('Name')).toHaveValue('');
      expect(screen.getByLabelText(/^Code/, { exact: false })).toHaveValue('');
      expect(screen.getByLabelText(/^Description/, { exact: false })).toHaveValue('');
    });

    it('calls createMenuBoard with name, description, and code on save', async () => {
      const user = userEvent.setup();
      mockCreateMenuBoard.mockResolvedValue(mockMenuBoard());
      const { mockOnSave, mockOnClose } = renderAddModal();

      await user.type(screen.getByLabelText('Name'), 'New Board');
      await user.type(screen.getByLabelText('Code', { exact: false }), 'NB_01');
      await user.type(screen.getByLabelText('Description', { exact: false }), 'A description');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockCreateMenuBoard).toHaveBeenCalledWith(
          expect.objectContaining({ name: 'New Board', code: 'NB_01', description: 'A description' }),
        );
        expect(mockOnSave).toHaveBeenCalled();
        expect(mockOnClose).toHaveBeenCalled();
      });
    });

    // Scenario 3 — empty name
    it('shows validation error when name is empty on save', async () => {
      const user = userEvent.setup();
      renderAddModal();

      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(screen.getByText('Name is required')).toBeInTheDocument();
      expect(mockCreateMenuBoard).not.toHaveBeenCalled();
    });

    // Scenario 4 — whitespace-only name
    it('shows validation error when name is only whitespace', async () => {
      const user = userEvent.setup();
      renderAddModal();

      await user.type(screen.getByLabelText('Name'), '   ');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(screen.getByText('Name is required')).toBeInTheDocument();
      expect(mockCreateMenuBoard).not.toHaveBeenCalled();
    });

    it('clears the name error once the user types a valid name', async () => {
      const user = userEvent.setup();
      renderAddModal();

      await user.click(screen.getByRole('button', { name: 'Save' }));
      expect(screen.getByText('Name is required')).toBeInTheDocument();

      await user.type(screen.getByLabelText('Name'), 'B');
      expect(screen.queryByText('Name is required')).not.toBeInTheDocument();
    });

    it('displays API error message when createMenuBoard rejects', async () => {
      const user = userEvent.setup();
      mockCreateMenuBoard.mockRejectedValue({
        response: { data: { message: 'Server error from API' } },
      });
      renderAddModal();

      await user.type(screen.getByLabelText('Name'), 'Bad Board');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(screen.getByText('Server error from API')).toBeInTheDocument();
      });
    });
  });

  // Scenarios 19, 22, 23 — edit mode
  describe('Edit mode', () => {
    it('renders "Edit Menu Board" title and pre-populates fields', () => {
      renderEditModal({ name: 'Existing Board', description: 'Existing desc', code: 'EB_01' });

      expect(screen.getByRole('dialog', { name: 'Edit Menu Board' })).toBeInTheDocument();
      expect(screen.getByLabelText('Name')).toHaveValue('Existing Board');
      expect(screen.getByLabelText('Code', { exact: false })).toHaveValue('EB_01');
      expect(screen.getByLabelText('Description', { exact: false })).toHaveValue('Existing desc');
    });

    it('calls updateMenuBoard with changed name on save', async () => {
      const user = userEvent.setup();
      mockUpdateMenuBoard.mockResolvedValue(mockMenuBoard({ name: 'Updated Board' }));
      const { mockOnSave, board } = renderEditModal({ name: 'Old Name' });

      await user.clear(screen.getByLabelText('Name'));
      await user.type(screen.getByLabelText('Name'), 'Updated Board');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockUpdateMenuBoard).toHaveBeenCalledWith(
          board.menuId,
          expect.objectContaining({ name: 'Updated Board' }),
        );
        expect(mockOnSave).toHaveBeenCalled();
      });
    });

    it('shows validation error when name is cleared on edit save', async () => {
      const user = userEvent.setup();
      renderEditModal({ name: 'Has A Name' });

      await user.clear(screen.getByLabelText('Name'));
      await user.click(screen.getByRole('button', { name: 'Save' }));

      expect(screen.getByText('Name is required')).toBeInTheDocument();
      expect(mockUpdateMenuBoard).not.toHaveBeenCalled();
    });

    it('updates description and code fields', async () => {
      const user = userEvent.setup();
      mockUpdateMenuBoard.mockResolvedValue(mockMenuBoard());
      const { board } = renderEditModal({ name: 'Board', description: '', code: '' });

      await user.type(screen.getByLabelText('Description', { exact: false }), 'New Desc');
      await user.type(screen.getByLabelText('Code', { exact: false }), 'NEW_CODE');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(mockUpdateMenuBoard).toHaveBeenCalledWith(
          board.menuId,
          expect.objectContaining({ description: 'New Desc', code: 'NEW_CODE' }),
        );
      });
    });
  });

  describe('Cancel button', () => {
    it('calls onClose when Cancel is clicked', async () => {
      const user = userEvent.setup();
      const { mockOnClose } = renderAddModal();

      await user.click(screen.getByRole('button', { name: 'Cancel' }));

      expect(mockOnClose).toHaveBeenCalled();
      expect(mockCreateMenuBoard).not.toHaveBeenCalled();
    });
  });
});
