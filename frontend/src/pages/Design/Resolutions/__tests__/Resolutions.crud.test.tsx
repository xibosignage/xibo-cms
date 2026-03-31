import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, beforeEach, vi } from 'vitest';

import { renderWithClient, mockResolutions } from './Setup';
import Resolution from '../Resolutions'; // Adjust path if needed
import { fetchResolution, createResolution, updateResolution } from '@/services/resolutionApi';

describe('Resolutions Page - CRUD (Create & Update)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    (fetchResolution as ReturnType<typeof vi.fn>).mockResolvedValue({
      rows: mockResolutions,
      totalCount: 2,
    });
  });

  it('opens the Add modal, fills the form, and creates a resolution', async () => {
    const user = userEvent.setup();
    (createResolution as ReturnType<typeof vi.fn>).mockResolvedValue({
      resolutionId: 3, resolution: '4K', width: 3840, height: 2160, enabled: 1
    });

    renderWithClient(<Resolution />);
    await waitFor(() => expect(screen.getByText('1080p')).toBeInTheDocument());

    // Open Add Modal
    await user.click(screen.getByRole('button', { name: 'Add Resolution' }));
    
    // FIX: Target the heading specifically, to avoid matching the button text!
    expect(screen.getByRole('heading', { name: 'Add Resolution' })).toBeInTheDocument();

    // Fill Form
    await user.type(screen.getByLabelText('Name'), '4K');
    await user.type(screen.getByLabelText('Width'), '3840');
    await user.type(screen.getByLabelText('Height'), '2160');

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
    (updateResolution as ReturnType<typeof vi.fn>).mockResolvedValue({
      resolutionId: 1, resolution: '1080p Updated', width: 1920, height: 1080, enabled: 1
    });

    renderWithClient(<Resolution />);
    await waitFor(() => expect(screen.getByText('1080p')).toBeInTheDocument());

    // Click edit on the first row
    const editButtons = screen.getAllByRole('button', { name: 'Edit' });
    await user.click(editButtons[0]!);

    // Target the heading for the edit modal
    expect(screen.getByRole('heading', { name: 'Edit Resolution' })).toBeInTheDocument();
    
    // Check if data is populated
    const nameInput = screen.getByLabelText('Name');
    expect(nameInput).toHaveValue('1080p');

    // Change and Save
    await user.clear(nameInput);
    await user.type(nameInput, '1080p Updated');
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(updateResolution).toHaveBeenCalledWith(1, expect.objectContaining({
        resolution: '1080p Updated'
      }));
    });
  });
});