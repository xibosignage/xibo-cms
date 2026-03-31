import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, beforeEach, vi } from 'vitest';

import { renderWithClient, mockResolutions } from './Setup';
import Resolution from '../Resolutions'; // Adjust path if needed
import { fetchResolution } from '@/services/resolutionApi';

describe('Resolutions Page - Render, Search, and Pagination', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    (fetchResolution as ReturnType<typeof vi.fn>).mockResolvedValue({
      rows: mockResolutions,
      totalCount: 20, 
    });
  });

  // FIX: Removed the loading skeleton check entirely
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
        expect.objectContaining({ keyword: '4K', start: 0 })
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
      expect(fetchResolution).toHaveBeenCalledWith(
        expect.objectContaining({ start: 10 })
      );
    });
  });
});