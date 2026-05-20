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

import DeleteMenuBoardModal from '../components/DeleteMenuBoardModal';

import { renderWithProviders } from './MenuBoardSetup';

vi.mock('@/components/ui/modals/Modal');

describe('DeleteMenuBoardModal', () => {
  const mockOnClose = vi.fn();
  const mockOnDelete = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
  });

  // Scenario 26 — single board deletion message
  it('shows single-board confirmation message with board name', () => {
    renderWithProviders(
      <DeleteMenuBoardModal
        onClose={mockOnClose}
        onDelete={mockOnDelete}
        itemCount={1}
        menuBoardName="Summer Menu"
      />,
    );

    expect(screen.getByText('Delete Menu Board?')).toBeInTheDocument();
    expect(screen.getByText('Summer Menu')).toBeInTheDocument();
  });

  // Scenario 27 — bulk delete confirmation
  it('shows plural confirmation message with count when deleting multiple boards', () => {
    renderWithProviders(
      <DeleteMenuBoardModal onClose={mockOnClose} onDelete={mockOnDelete} itemCount={3} />,
    );

    expect(screen.getByText('Delete Menu Boards?')).toBeInTheDocument();
    expect(screen.getByText('3')).toBeInTheDocument();
  });

  it('calls onDelete when "Yes, Delete" is clicked', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <DeleteMenuBoardModal
        onClose={mockOnClose}
        onDelete={mockOnDelete}
        itemCount={1}
        menuBoardName="Board X"
      />,
    );

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    expect(mockOnDelete).toHaveBeenCalled();
    expect(mockOnClose).not.toHaveBeenCalled();
  });

  it('calls onClose when Cancel is clicked', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <DeleteMenuBoardModal onClose={mockOnClose} onDelete={mockOnDelete} itemCount={1} />,
    );

    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(mockOnClose).toHaveBeenCalled();
    expect(mockOnDelete).not.toHaveBeenCalled();
  });

  it('displays the error message when error prop is provided', () => {
    renderWithProviders(
      <DeleteMenuBoardModal
        onClose={mockOnClose}
        onDelete={mockOnDelete}
        itemCount={1}
        error="Could not delete: board is in use"
      />,
    );

    expect(screen.getByText('Could not delete: board is in use')).toBeInTheDocument();
  });

  it('shows "Deleting…" label and disables button while loading', () => {
    renderWithProviders(
      <DeleteMenuBoardModal
        onClose={mockOnClose}
        onDelete={mockOnDelete}
        itemCount={1}
        isLoading={true}
      />,
    );

    expect(screen.getByRole('button', { name: 'Deleting…' })).toBeDisabled();
  });

  it('does not render when isOpen is false', () => {
    renderWithProviders(
      <DeleteMenuBoardModal
        isOpen={false}
        onClose={mockOnClose}
        onDelete={mockOnDelete}
        itemCount={1}
        menuBoardName="Hidden Board"
      />,
    );

    expect(screen.queryByText('Delete Menu Board?')).not.toBeInTheDocument();
  });
});
