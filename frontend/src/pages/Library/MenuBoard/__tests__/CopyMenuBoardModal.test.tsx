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

import CopyMenuBoardModal from '../components/CopyMenuBoardModal';

import { renderWithProviders, mockMenuBoard } from './MenuBoardSetup';

vi.mock('@/components/ui/modals/Modal');

describe('CopyMenuBoardModal', () => {
  const mockOnClose = vi.fn();
  const mockOnConfirm = vi.fn();
  const existingNames = ['Existing Board', 'Another Board'];

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('does not render when isOpen is false', () => {
    renderWithProviders(
      <CopyMenuBoardModal
        isOpen={false}
        onClose={mockOnClose}
        onConfirm={mockOnConfirm}
        menuBoard={null}
        existingNames={existingNames}
      />,
    );

    expect(screen.queryByText('Copy Menu Board')).not.toBeInTheDocument();
  });

  // Scenario 141 — copy creates new board; name is auto-incremented
  it('initializes with incremented name and pre-fills code and description from source board', () => {
    const board = mockMenuBoard({ name: 'Summer Menu', description: 'Summer desc', code: 'SM_01' });

    renderWithProviders(
      <CopyMenuBoardModal
        isOpen={true}
        onClose={mockOnClose}
        onConfirm={mockOnConfirm}
        menuBoard={board}
        existingNames={existingNames}
      />,
    );

    expect(screen.getByLabelText('Name')).toHaveValue('Summer Menu (1)');
    expect(screen.getByLabelText('Description', { exact: false })).toHaveValue('Summer desc');
    expect(screen.getByLabelText('Code', { exact: false })).toHaveValue('SM_01');
  });

  it('shows error when submitting with empty name', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <CopyMenuBoardModal
        isOpen={true}
        onClose={mockOnClose}
        onConfirm={mockOnConfirm}
        menuBoard={null}
        existingNames={existingNames}
      />,
    );

    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(screen.getByText('Name is required')).toBeInTheDocument();
    expect(mockOnConfirm).not.toHaveBeenCalled();
  });

  it('shows error when submitting with a duplicate name (case-insensitive)', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <CopyMenuBoardModal
        isOpen={true}
        onClose={mockOnClose}
        onConfirm={mockOnConfirm}
        menuBoard={null}
        existingNames={existingNames}
      />,
    );

    await user.type(screen.getByLabelText('Name'), 'existing board');
    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(screen.getByText('A menu board with this name already exists')).toBeInTheDocument();
    expect(mockOnConfirm).not.toHaveBeenCalled();
  });

  it('submits with trimmed name, description, and code', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <CopyMenuBoardModal
        isOpen={true}
        onClose={mockOnClose}
        onConfirm={mockOnConfirm}
        menuBoard={null}
        existingNames={existingNames}
      />,
    );

    await user.type(screen.getByLabelText('Name'), '  Unique Board Name  ');
    await user.type(screen.getByLabelText('Code', { exact: false }), 'UBN');
    await user.type(screen.getByLabelText('Description', { exact: false }), 'Copied description');
    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(mockOnConfirm).toHaveBeenCalledWith('Unique Board Name', 'Copied description', 'UBN');
  });

  it('clears error once user modifies the name', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <CopyMenuBoardModal
        isOpen={true}
        onClose={mockOnClose}
        onConfirm={mockOnConfirm}
        menuBoard={null}
        existingNames={existingNames}
      />,
    );

    await user.click(screen.getByRole('button', { name: 'Save' }));
    expect(screen.getByText('Name is required')).toBeInTheDocument();

    await user.type(screen.getByLabelText('Name'), 'X');
    expect(screen.queryByText('Name is required')).not.toBeInTheDocument();
  });

  it('shows loading state and disables buttons when isLoading is true', () => {
    renderWithProviders(
      <CopyMenuBoardModal
        isOpen={true}
        onClose={mockOnClose}
        onConfirm={mockOnConfirm}
        menuBoard={null}
        existingNames={existingNames}
        isLoading={true}
      />,
    );

    expect(screen.getByRole('button', { name: 'Saving…' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Cancel' })).toBeDisabled();
  });
});
