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

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key }),
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
