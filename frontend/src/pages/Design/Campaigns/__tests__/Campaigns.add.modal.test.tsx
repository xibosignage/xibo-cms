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

import { QueryClientProvider } from '@tanstack/react-query';
import { screen, fireEvent, waitFor, act } from '@testing-library/react';
import { render } from '@testing-library/react';
import { AxiosError } from 'axios';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import AddCampaignModal from '../components/AddCampaignModal';

import { createCampaign } from '@/services/campaignApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next');

vi.mock('@/services/campaignApi');
vi.mock('@/services/folderApi', () => ({
  fetchFolderById: vi.fn().mockResolvedValue({ id: 1, text: 'Root' }),
  fetchFolderTree: vi.fn().mockResolvedValue([]),
  searchFolders: vi.fn().mockResolvedValue([]),
}));

vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/forms/SelectFolder', () => ({ default: () => null }));
vi.mock('@/components/ui/forms/TagInput', () => ({ default: () => null }));

// =============================================================================
// Helpers
// =============================================================================

interface RenderModalProps {
  isOpen?: boolean;
  onClose?: () => void;
  onSuccess?: () => void;
  defaultFolderId?: number;
}

const renderModal = ({
  isOpen = true,
  onClose = vi.fn(),
  onSuccess = vi.fn(),
  defaultFolderId = 1,
}: RenderModalProps = {}) => {
  testQueryClient.clear();
  return render(
    <QueryClientProvider client={testQueryClient}>
      <AddCampaignModal
        isOpen={isOpen}
        onClose={onClose}
        onSuccess={onSuccess}
        defaultFolderId={defaultFolderId}
      />
    </QueryClientProvider>,
  );
};

// =============================================================================
// Tests
// =============================================================================

describe('AddCampaignModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // Modal open / close
  // ---------------------------------------------------------------------------
  test('renders the modal when isOpen is true', () => {
    renderModal();
    expect(screen.getByRole('dialog', { name: 'Add Campaign' })).toBeInTheDocument();
  });

  test('Cancel closes the modal without calling createCampaign', async () => {
    const onClose = vi.fn();
    renderModal({ onClose });

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(createCampaign).not.toHaveBeenCalled();
  });

  test('form resets on close — reopening shows an empty name field', async () => {
    const onClose = vi.fn();
    const { rerender } = renderModal({ onClose });

    // Type something in the Name field.
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Temp Name' } });
    expect(screen.getByLabelText('Name')).toHaveValue('Temp Name');

    // Close then reopen.
    rerender(
      <QueryClientProvider client={testQueryClient}>
        <AddCampaignModal isOpen={false} onClose={onClose} onSuccess={vi.fn()} />
      </QueryClientProvider>,
    );
    rerender(
      <QueryClientProvider client={testQueryClient}>
        <AddCampaignModal isOpen={true} onClose={onClose} onSuccess={vi.fn()} />
      </QueryClientProvider>,
    );

    expect(screen.getByLabelText('Name')).toHaveValue('');
  });

  // ---------------------------------------------------------------------------
  // Campaign type selection
  // ---------------------------------------------------------------------------
  test('default campaign type is "Layout list"', () => {
    renderModal();
    // List-type fields are visible; ad-type target fields are not.
    expect(screen.getByRole('checkbox', { name: /cycle based playback/i })).toBeInTheDocument();
    expect(screen.queryByLabelText('Target')).not.toBeInTheDocument();
  });

  test('list type shows Cycle Playback checkbox and hides Target Type / Target fields', () => {
    renderModal();
    expect(screen.getByRole('checkbox', { name: /cycle based playback/i })).toBeInTheDocument();
    expect(screen.queryByLabelText('Target Type')).not.toBeInTheDocument();
    expect(screen.queryByLabelText('Target')).not.toBeInTheDocument();
  });

  test('switching to "Ad Campaign" type shows Target Type and Target fields', async () => {
    renderModal();

    // Open the Type dropdown (first combobox is the Type selector).
    const typeCombobox = screen.getAllByRole('combobox')[0]!;
    fireEvent.click(typeCombobox);
    fireEvent.click(await screen.findByRole('option', { name: 'Ad Campaign' }));

    expect(await screen.findByLabelText('Target')).toBeInTheDocument();
    expect(
      screen.queryByRole('checkbox', { name: /cycle based playback/i }),
    ).not.toBeInTheDocument();
  });

  test('switching back to "Layout list" from "Ad Campaign" restores Cycle Playback fields', async () => {
    renderModal();

    const typeCombobox = screen.getAllByRole('combobox')[0]!;
    fireEvent.click(typeCombobox);
    fireEvent.click(await screen.findByRole('option', { name: 'Ad Campaign' }));

    // Now switch back.
    fireEvent.click(typeCombobox);
    fireEvent.click(await screen.findByRole('option', { name: 'Layout list' }));

    expect(
      await screen.findByRole('checkbox', { name: /cycle based playback/i }),
    ).toBeInTheDocument();
    expect(screen.queryByLabelText('Target')).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // List campaign — Cycle Playback toggle
  // ---------------------------------------------------------------------------
  test('Cycle Playback unchecked by default — "List play order" dropdown is shown', () => {
    renderModal();
    expect(screen.getByRole('checkbox', { name: /cycle based playback/i })).not.toBeChecked();
    // List play order dropdown should be present (second combobox after Type).
    expect(screen.getAllByRole('combobox').length).toBeGreaterThan(1);
  });

  test('checking Cycle Playback replaces the play-order dropdown with the Play count input', async () => {
    renderModal();

    fireEvent.click(screen.getByRole('checkbox', { name: /cycle based playback/i }));

    expect(await screen.findByLabelText('Play count')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Ad campaign — Target Type options
  // ---------------------------------------------------------------------------
  test('Target Type dropdown has Plays, Budget, and Impressions options', async () => {
    renderModal();

    const typeCombobox = screen.getAllByRole('combobox')[0]!;
    fireEvent.click(typeCombobox);
    fireEvent.click(await screen.findByRole('option', { name: 'Ad Campaign' }));

    // Open Target Type dropdown.
    const targetTypeCombobox = screen.getAllByRole('combobox')[1]!;
    fireEvent.click(targetTypeCombobox);

    expect(await screen.findByRole('option', { name: 'Plays' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Budget' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Impressions' })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Validation
  // ---------------------------------------------------------------------------
  test('submitting with an empty name shows a name validation error', async () => {
    renderModal();

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Save' }));
    });

    expect(await screen.findByText('Name is required')).toBeInTheDocument();
    expect(createCampaign).not.toHaveBeenCalled();
  });

  test('submitting ad campaign with empty target shows a target validation error', async () => {
    renderModal();

    const typeCombobox = screen.getAllByRole('combobox')[0]!;
    fireEvent.click(typeCombobox);
    fireEvent.click(await screen.findByRole('option', { name: 'Ad Campaign' }));

    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'My Ad' } });
    // Leave Target empty.

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Save' }));
    });

    expect(await screen.findByText('Target is required for Ad Campaigns')).toBeInTheDocument();
    expect(createCampaign).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // API calls — happy path (list campaign)
  // ---------------------------------------------------------------------------
  test('successful list campaign creation calls createCampaign with correct payload', async () => {
    vi.mocked(createCampaign).mockResolvedValueOnce({ campaignId: 1 });
    const onSuccess = vi.fn();
    const onClose = vi.fn();
    renderModal({ onSuccess, onClose });

    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'New List Campaign' } });

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Save' }));
    });

    await waitFor(() => {
      expect(createCampaign).toHaveBeenCalledWith(
        expect.objectContaining({
          name: 'New List Campaign',
          type: 'list',
          cyclePlaybackEnabled: false,
          listPlayOrder: 'round',
        }),
      );
    });

    expect(onSuccess).toHaveBeenCalledTimes(1);
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  test('list campaign with cycle playback enabled calls createCampaign with playCount', async () => {
    vi.mocked(createCampaign).mockResolvedValueOnce({ campaignId: 2 });
    renderModal({ onSuccess: vi.fn(), onClose: vi.fn() });

    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Cycle Campaign' } });
    fireEvent.click(screen.getByRole('checkbox', { name: /cycle based playback/i }));

    const playCountInput = await screen.findByLabelText('Play count');
    fireEvent.change(playCountInput, { target: { value: '3' } });

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Save' }));
    });

    await waitFor(() => {
      expect(createCampaign).toHaveBeenCalledWith(
        expect.objectContaining({
          name: 'Cycle Campaign',
          type: 'list',
          cyclePlaybackEnabled: true,
          playCount: 3,
        }),
      );
    });
  });

  test('list campaign with Block play order calls createCampaign with listPlayOrder: "block"', async () => {
    vi.mocked(createCampaign).mockResolvedValueOnce({ campaignId: 3 });
    renderModal({ onSuccess: vi.fn(), onClose: vi.fn() });

    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Block Campaign' } });

    // Select Block from the List play order dropdown.
    const playOrderCombobox = screen.getAllByRole('combobox')[1]!;
    fireEvent.click(playOrderCombobox);
    fireEvent.click(await screen.findByRole('option', { name: 'Block' }));

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Save' }));
    });

    await waitFor(() => {
      expect(createCampaign).toHaveBeenCalledWith(
        expect.objectContaining({
          listPlayOrder: 'block',
        }),
      );
    });
  });

  // ---------------------------------------------------------------------------
  // API calls — happy path (ad campaign)
  // ---------------------------------------------------------------------------
  test('successful ad campaign creation calls createCampaign with type, targetType, and target', async () => {
    vi.mocked(createCampaign).mockResolvedValueOnce({ campaignId: 4 });
    renderModal({ onSuccess: vi.fn(), onClose: vi.fn() });

    const typeCombobox = screen.getAllByRole('combobox')[0]!;
    fireEvent.click(typeCombobox);
    fireEvent.click(await screen.findByRole('option', { name: 'Ad Campaign' }));

    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'My Ad Campaign' } });
    fireEvent.change(screen.getByLabelText('Target'), { target: { value: '500' } });

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Save' }));
    });

    await waitFor(() => {
      expect(createCampaign).toHaveBeenCalledWith(
        expect.objectContaining({
          name: 'My Ad Campaign',
          type: 'ad',
          targetType: 'plays',
          target: 500,
        }),
      );
    });
  });

  // ---------------------------------------------------------------------------
  // API error
  // ---------------------------------------------------------------------------
  test('API error response message is displayed inside the modal', async () => {
    const axiosError = new AxiosError('Request failed');
    axiosError.response = {
      data: { message: 'Campaign name already exists' },
      status: 422,
      statusText: 'Unprocessable Entity',
      headers: {},
      config: {} as never,
    };
    vi.mocked(createCampaign).mockRejectedValueOnce(axiosError);

    renderModal();
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Duplicate Name' } });

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Save' }));
    });

    expect(await screen.findByRole('alert')).toHaveTextContent('Campaign name already exists');
  });
});
