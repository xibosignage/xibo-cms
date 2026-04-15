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

import { fetchResolution } from '@/services/resolutionApi';

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

describe('Resolutions Page - Render, Search, and Pagination', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(fetchResolution).mockResolvedValue({
      rows: mockResolutions,
      totalCount: 20,
    });
  });

  it('renders the data table successfully', async () => {
    renderWithClient(<Resolution />);

    // Wait for data to populate right away
    await waitFor(() => {
      expect(screen.getByText('1080p')).toBeInTheDocument();
      expect(screen.getByText('720p')).toBeInTheDocument();
    });
  });

  it('triggers a new API call when searching', async () => {
    const user = userEvent.setup();
    renderWithClient(<Resolution />);

    await waitFor(() => expect(screen.getByText('1080p')).toBeInTheDocument());

    const searchInput = screen.getByPlaceholderText('Search resolution...');
    await user.type(searchInput, '4K');

    // Wait for debounce and refetch
    await waitFor(() => {
      expect(fetchResolution).toHaveBeenCalledWith(
        expect.objectContaining({ keyword: '4K', start: 0 }),
      );
    });
  });

  it('handles pagination correctly', async () => {
    const user = userEvent.setup();
    renderWithClient(<Resolution />);

    await waitFor(() => expect(screen.getByText('1080p')).toBeInTheDocument());

    // Click next page
    const nextButton = screen.getByRole('button', { name: /next/i });
    await user.click(nextButton);

    await waitFor(() => {
      expect(fetchResolution).toHaveBeenCalledWith(expect.objectContaining({ start: 10 }));
    });
  });
});
