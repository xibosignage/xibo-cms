/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, beforeEach, vi } from 'vitest';

import Resolution from '../Resolutions';

import { renderWithClient, mockResolutions } from './Setup';

import { fetchResolution, deleteResolution } from '@/services/resolutionApi';

vi.mock('@/services/resolutionApi', () => ({
  fetchResolution: vi.fn(),
  createResolution: vi.fn(),
  updateResolution: vi.fn(),
  deleteResolution: vi.fn(),
}));

vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

describe('Resolutions Page - Delete and Error Handling', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(fetchResolution).mockResolvedValue({
      rows: mockResolutions,
      totalCount: 2,
    });
  });

  it('successfully deletes a resolution and closes the modal', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteResolution).mockResolvedValue(undefined);

    renderWithClient(<Resolution />);
    await waitFor(() => expect(screen.getByText('1080p')).toBeInTheDocument());

    // Click delete on the first row
    const deleteButtons = screen.getAllByRole('button', { name: 'Delete' });
    await user.click(deleteButtons[0]!);

    // Verify modal is open and asks for confirmation
    expect(screen.getByText('Delete Resolution?')).toBeInTheDocument();

    // Confirm delete
    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => {
      expect(deleteResolution).toHaveBeenCalledWith(1);
      // Modal should close on success
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });

  it('keeps the modal open and shows the API error message if deletion fails', async () => {
    const user = userEvent.setup();

    // Mock a rejected promise with an Axios-like error structure
    const mockAxiosError = {
      isAxiosError: true,
      response: { data: { message: 'Cannot delete resolution. It is currently in use.' } },
    };
    vi.mocked(deleteResolution).mockRejectedValue(mockAxiosError);

    renderWithClient(<Resolution />);
    await waitFor(() => expect(screen.getByText('1080p')).toBeInTheDocument());

    // Click delete on the first row
    const deleteButtons = screen.getAllByRole('button', { name: 'Delete' });
    await user.click(deleteButtons[0]!);

    // Confirm delete
    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => {
      // The API was called
      expect(deleteResolution).toHaveBeenCalledWith(1);

      // CRITICAL: The modal should still be open
      expect(screen.getByText('Delete Resolution?')).toBeInTheDocument();

      // CRITICAL: The API error message should be displayed inside the modal
      expect(
        screen.getByText('Cannot delete resolution. It is currently in use.'),
      ).toBeInTheDocument();
    });
  });
});
