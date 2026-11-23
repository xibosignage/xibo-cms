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

import { fetchResolution, createResolution, updateResolution } from '@/services/resolutionApi';

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

describe('Resolutions Page - CRUD (Create & Update)', { tags: ['smoke'] }, () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(fetchResolution).mockResolvedValue({
      rows: mockResolutions,
      totalCount: 2,
    });
  });

  it('opens the Add modal, fills the form, and creates a resolution', async () => {
    const user = userEvent.setup();
    vi.mocked(createResolution).mockResolvedValue({
      resolutionId: 3,
      resolution: '4K',
      width: 3840,
      height: 2160,
      enabled: true,
    });

    renderWithClient(<Resolution />);
    await waitFor(() => expect(screen.getByText('1080p')).toBeInTheDocument());

    // Open Add Modal
    await user.click(screen.getByRole('button', { name: 'Add Resolution' }));

    expect(screen.getByRole('heading', { name: 'Add Resolution' })).toBeInTheDocument();

    // Fill Form
    await user.type(screen.getByRole('textbox', { name: 'Name' }), '4K');
    await user.type(screen.getByRole('spinbutton', { name: 'Width' }), '3840');
    await user.type(screen.getByRole('spinbutton', { name: 'Height' }), '2160');

    // Save
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(createResolution).toHaveBeenCalledWith({
        resolution: '4K',
        width: 3840,
        height: 2160,
        enabled: true,
      });
      // Verifies modal closes after successful save
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });

  it('opens the Edit modal, populates data, and updates a resolution', async () => {
    const user = userEvent.setup();
    vi.mocked(updateResolution).mockResolvedValue({
      resolutionId: 1,
      resolution: '1080p Updated',
      width: 1920,
      height: 1080,
      enabled: true,
    });

    renderWithClient(<Resolution />);
    await waitFor(() => expect(screen.getByText('1080p')).toBeInTheDocument());

    // Click edit on the first row
    const editButtons = screen.getAllByRole('button', { name: 'Edit' });
    await user.click(editButtons[0]!);

    // Target the heading for the edit modal
    expect(screen.getByRole('heading', { name: 'Edit Resolution' })).toBeInTheDocument();

    // Check if data is populated
    const nameInput = screen.getByRole('textbox', { name: 'Name' });
    expect(nameInput).toHaveValue('1080p');

    // Change and Save
    await user.clear(nameInput);
    await user.type(nameInput, '1080p Updated');
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(updateResolution).toHaveBeenCalledWith(
        1,
        expect.objectContaining({
          resolution: '1080p Updated',
        }),
      );
    });
  });
});
