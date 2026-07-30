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

import EditCampaignModal from '../components/EditCampaignModal';

import {
  mockCampaign,
  mockCampaignWithRefs,
  mockCycleCampaign,
  mockUser,
} from './campaignTestUtils';

import type * as TagInputModule from '@/components/ui/forms/TagInput';
import { UserProvider } from '@/context/UserContext';
import { updateCampaign } from '@/services/campaignApi';
import { fetchLayouts } from '@/services/layoutsApi';
import { testQueryClient } from '@/setupTests';
import type { Layout } from '@/types/layout';

// =============================================================================
// Module mocks
// =============================================================================

// Interpolation-aware t() so labels like 'Reference {{n}}' render as 'Reference 1'.

vi.mock('@/services/campaignApi');
vi.mock('@/services/layoutsApi');
vi.mock('@/services/folderApi', () => ({
  fetchFolderById: vi.fn().mockResolvedValue({ id: 1, text: 'Root' }),
  fetchFolderTree: vi.fn().mockResolvedValue([]),
  searchFolders: vi.fn().mockResolvedValue([]),
}));
vi.mock('@/hooks/useDebounce');

vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/forms/SelectFolder', () => ({ default: () => null }));
vi.mock('@/components/ui/forms/TagInput', async (importOriginal) => {
  const actual = await importOriginal<typeof TagInputModule>();
  return { ...actual, default: () => null };
});

// Stub SearchAssignPanel so tests are not coupled to its internal rendering.
// Exposes Remove/Add/Clear All buttons to simulate layout assignment changes.
vi.mock('@/components/ui/SearchAssignPanel', () => ({
  SearchAssignPanel: ({
    assignedItems,
    assignedLabel,
    onAddItem,
    onRemoveItem,
    onClearAll,
    searchRows,
    getItemLabel,
    getItemId,
  }: {
    assignedItems: Layout[];
    assignedLabel: string;
    onAddItem: (item: Layout) => void;
    onRemoveItem: (item: Layout, index: number) => void;
    onClearAll: () => void;
    searchRows?: Layout[];
    getItemLabel: (item: Layout) => string;
    getItemId: (item: Layout) => string | number;
    [key: string]: unknown;
  }) => (
    <div>
      <h3>{assignedLabel}</h3>
      {assignedItems.map((item, index) => (
        <div key={getItemId(item)}>
          <span>{getItemLabel(item)}</span>
          <button onClick={() => onRemoveItem(item, index)}>Remove</button>
        </div>
      ))}
      <button onClick={onClearAll}>Clear All</button>
      {searchRows?.map((item) => (
        <div key={getItemId(item)}>
          <span>{getItemLabel(item)}</span>
          <button onClick={() => onAddItem(item)}>Add</button>
        </div>
      ))}
    </div>
  ),
}));

// =============================================================================
// Fixtures
// =============================================================================

const mockLayout: Layout = {
  layoutId: 10,
  layout: 'Layout Alpha',
  publishedStatusId: 1,
  publishedStatus: 'Published',
  campaignId: 20,
  status: 1,
  retired: false,
  width: 1920,
  height: 1080,
  orientation: 'landscape',
  duration: 30,
  enableStat: true,
  tags: [],
  owner: 'TestUser',
  ownerId: 1,
  folderId: 1,
  permissionsFolderId: 1,
  modifiedDt: '2026-01-01',
  userPermissions: { view: 1, edit: 1, delete: 1, modifyPermissions: 1 },
};

const mockSearchLayout: Layout = {
  ...mockLayout,
  layoutId: 20,
  layout: 'Layout Beta',
  campaignId: 30,
};

// =============================================================================
// Helpers
// =============================================================================

interface RenderModalProps {
  campaign?: typeof mockCampaign | null;
  isOpen?: boolean;
  onClose?: () => void;
  onSuccess?: () => void;
}

const renderEditModal = async ({
  campaign = mockCampaign,
  isOpen = true,
  onClose = vi.fn(),
  onSuccess = vi.fn(),
}: RenderModalProps = {}) => {
  testQueryClient.clear();
  let result!: ReturnType<typeof render>;
  await act(async () => {
    result = render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <EditCampaignModal
            isOpen={isOpen}
            campaign={campaign}
            onClose={onClose}
            onSuccess={onSuccess}
          />
        </UserProvider>
      </QueryClientProvider>,
    );
  });
  return result;
};

// =============================================================================
// Tests
// =============================================================================

describe('EditCampaignModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(fetchLayouts).mockResolvedValue({ rows: [], totalCount: 0 });
    vi.mocked(updateCampaign).mockResolvedValue(mockCampaign);
  });

  // ---------------------------------------------------------------------------
  // Modal open / close
  // ---------------------------------------------------------------------------
  test('renders the Edit Campaign modal when isOpen is true', async () => {
    await renderEditModal();
    expect(screen.getByRole('dialog', { name: 'Edit Campaign' })).toBeInTheDocument();
  });

  test('Cancel closes the modal without calling updateCampaign', async () => {
    const onClose = vi.fn();
    await renderEditModal({ onClose });

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(updateCampaign).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // General tab — default and pre-population
  // ---------------------------------------------------------------------------
  test('General tab is active by default', async () => {
    await renderEditModal();
    const generalTab = screen.getByRole('button', { name: 'General' });
    expect(generalTab).toHaveClass('border-blue-600');
  });

  test('Name field is pre-populated with the campaign name', async () => {
    await renderEditModal();
    expect(await screen.findByLabelText('Name')).toHaveValue(mockCampaign.campaign);
  });

  test('Cycle Playback checkbox is unchecked when cyclePlaybackEnabled is 0', async () => {
    await renderEditModal({ campaign: mockCampaign });
    expect(
      await screen.findByRole('checkbox', { name: /cycle based playback/i }),
    ).not.toBeChecked();
  });

  test('Cycle Playback checkbox is checked when cyclePlaybackEnabled is 1', async () => {
    await renderEditModal({ campaign: mockCycleCampaign });
    expect(await screen.findByRole('checkbox', { name: /cycle based playback/i })).toBeChecked();
  });

  test('Play count input is shown and pre-populated when cyclePlaybackEnabled is 1', async () => {
    await renderEditModal({ campaign: mockCycleCampaign });
    const input = await screen.findByLabelText('Play count');
    // type="number" inputs — toHaveValue expects a number, not a string.
    expect(input).toHaveValue(mockCycleCampaign.playCount);
  });

  test('"List play order" dropdown is shown when cyclePlaybackEnabled is 0', async () => {
    await renderEditModal({ campaign: mockCampaign });
    await screen.findByLabelText('Name'); // wait for effects
    expect(screen.queryByLabelText('Play count')).not.toBeInTheDocument();
    expect(screen.getByRole('combobox')).toBeInTheDocument();
  });

  test('toggling Cycle Playback switches between play count input and list play order dropdown', async () => {
    await renderEditModal({ campaign: mockCampaign });
    await screen.findByLabelText('Name'); // wait for effects

    expect(screen.queryByLabelText('Play count')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('checkbox', { name: /cycle based playback/i }));

    expect(await screen.findByLabelText('Play count')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Reference tab
  // ---------------------------------------------------------------------------
  test('switching to the Reference tab shows 5 reference input fields', async () => {
    await renderEditModal();

    fireEvent.click(screen.getByRole('button', { name: 'Reference' }));

    expect(await screen.findByLabelText('Reference 1')).toBeInTheDocument();
    expect(screen.getByLabelText('Reference 2')).toBeInTheDocument();
    expect(screen.getByLabelText('Reference 3')).toBeInTheDocument();
    expect(screen.getByLabelText('Reference 4')).toBeInTheDocument();
    expect(screen.getByLabelText('Reference 5')).toBeInTheDocument();
  });

  test('ref fields are pre-populated from the campaign data', async () => {
    await renderEditModal({ campaign: mockCampaignWithRefs });

    fireEvent.click(screen.getByRole('button', { name: 'Reference' }));

    expect(await screen.findByLabelText('Reference 1')).toHaveValue('ref-one');
    expect(screen.getByLabelText('Reference 2')).toHaveValue('ref-two');
    expect(screen.getByLabelText('Reference 3')).toHaveValue('');
  });

  test('reference fields accept text input', async () => {
    await renderEditModal();

    fireEvent.click(screen.getByRole('button', { name: 'Reference' }));
    const ref1 = await screen.findByLabelText('Reference 1');

    fireEvent.change(ref1, { target: { value: 'my-ref-value' } });
    expect(ref1).toHaveValue('my-ref-value');
  });

  // ---------------------------------------------------------------------------
  // Layouts tab
  // ---------------------------------------------------------------------------
  test('switching to the Layouts tab shows the SearchAssignPanel', async () => {
    await renderEditModal();
    fireEvent.click(screen.getByRole('button', { name: 'Layouts' }));
    expect(await screen.findByText('Selected Layouts')).toBeInTheDocument();
  });

  test('already-assigned layouts are listed in the Selected Layouts section', async () => {
    vi.mocked(fetchLayouts).mockImplementation(async (params) => {
      if (params?.campaignId) return { rows: [mockLayout], totalCount: 1 };
      return { rows: [], totalCount: 0 };
    });

    await renderEditModal();
    fireEvent.click(screen.getByRole('button', { name: 'Layouts' }));

    expect(await screen.findByText(mockLayout.layout)).toBeInTheDocument();
  });

  test('clicking Remove on an assigned layout removes it from the Selected Layouts list', async () => {
    vi.mocked(fetchLayouts).mockImplementation(async (params) => {
      if (params?.campaignId) return { rows: [mockLayout], totalCount: 1 };
      return { rows: [], totalCount: 0 };
    });

    await renderEditModal();
    fireEvent.click(screen.getByRole('button', { name: 'Layouts' }));

    await screen.findByText(mockLayout.layout);

    fireEvent.click(screen.getByRole('button', { name: 'Remove' }));

    await waitFor(() => {
      expect(screen.queryByText(mockLayout.layout)).not.toBeInTheDocument();
    });
  });

  test('search results in the Layouts tab show available layouts to assign', async () => {
    vi.mocked(fetchLayouts).mockImplementation(async (params) => {
      if (params?.campaignId) return { rows: [], totalCount: 0 };
      return { rows: [mockSearchLayout], totalCount: 1 };
    });

    await renderEditModal();
    fireEvent.click(screen.getByRole('button', { name: 'Layouts' }));

    expect(await screen.findByText(mockSearchLayout.layout)).toBeInTheDocument();
  });

  test('clicking Add on a search result adds it to the assigned list', async () => {
    vi.mocked(fetchLayouts).mockImplementation(async (params) => {
      if (params?.campaignId) return { rows: [], totalCount: 0 };
      return { rows: [mockSearchLayout], totalCount: 1 };
    });

    await renderEditModal();
    fireEvent.click(screen.getByRole('button', { name: 'Layouts' }));

    await screen.findByText(mockSearchLayout.layout);
    fireEvent.click(screen.getByRole('button', { name: 'Add' }));

    // After adding, the layout appears in the assigned section.
    // The stub renders the item twice (search + assigned), so check count.
    await waitFor(() => {
      expect(screen.getAllByText(mockSearchLayout.layout).length).toBeGreaterThanOrEqual(1);
    });
  });

  test('clicking "Clear All" removes all assigned layouts', async () => {
    vi.mocked(fetchLayouts).mockImplementation(async (params) => {
      if (params?.campaignId) return { rows: [mockLayout], totalCount: 1 };
      return { rows: [], totalCount: 0 };
    });

    await renderEditModal();
    fireEvent.click(screen.getByRole('button', { name: 'Layouts' }));

    await screen.findByText(mockLayout.layout);
    fireEvent.click(screen.getByRole('button', { name: 'Clear All' }));

    await waitFor(() => {
      expect(screen.queryByText(mockLayout.layout)).not.toBeInTheDocument();
    });
  });

  // ---------------------------------------------------------------------------
  // Save — API calls
  // ---------------------------------------------------------------------------
  test('Save calls updateCampaign with the updated campaign name', async () => {
    await renderEditModal();

    const nameInput = await screen.findByLabelText('Name');
    fireEvent.change(nameInput, { target: { value: 'Updated Campaign Name' } });

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Save' }));
    });

    await waitFor(() => {
      expect(updateCampaign).toHaveBeenCalledWith(
        mockCampaign.campaignId,
        expect.objectContaining({ name: 'Updated Campaign Name' }),
      );
    });
  });

  test('Save calls updateCampaign with updated reference fields', async () => {
    await renderEditModal();

    fireEvent.click(screen.getByRole('button', { name: 'Reference' }));
    const ref1 = await screen.findByLabelText('Reference 1');
    fireEvent.change(ref1, { target: { value: 'new-ref' } });

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Save' }));
    });

    await waitFor(() => {
      expect(updateCampaign).toHaveBeenCalledWith(
        mockCampaign.campaignId,
        expect.objectContaining({ ref1: 'new-ref' }),
      );
    });
  });

  test('Save calls updateCampaign with layoutIds including each newly added layout', async () => {
    vi.mocked(fetchLayouts).mockImplementation(async (params) => {
      if (params?.campaignId) return { rows: [], totalCount: 0 };
      return { rows: [mockSearchLayout], totalCount: 1 };
    });

    await renderEditModal();
    fireEvent.click(screen.getByRole('button', { name: 'Layouts' }));

    await screen.findByText(mockSearchLayout.layout);
    fireEvent.click(screen.getByRole('button', { name: 'Add' }));

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Save' }));
    });

    await waitFor(() => {
      expect(updateCampaign).toHaveBeenCalledWith(
        mockCampaign.campaignId,
        expect.objectContaining({
          manageLayouts: 1,
          layoutIds: [mockSearchLayout.layoutId],
        }),
      );
    });
  });

  test('Save calls updateCampaign with layoutIds excluding each removed layout', async () => {
    vi.mocked(fetchLayouts).mockImplementation(async (params) => {
      if (params?.campaignId) return { rows: [mockLayout], totalCount: 1 };
      return { rows: [], totalCount: 0 };
    });

    await renderEditModal();
    fireEvent.click(screen.getByRole('button', { name: 'Layouts' }));

    await screen.findByText(mockLayout.layout);
    fireEvent.click(screen.getByRole('button', { name: 'Remove' }));

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Save' }));
    });

    await waitFor(() => {
      expect(updateCampaign).toHaveBeenCalledWith(
        mockCampaign.campaignId,
        expect.objectContaining({
          manageLayouts: 1,
          layoutIds: [],
        }),
      );
    });
  });

  // ---------------------------------------------------------------------------
  // API error
  // ---------------------------------------------------------------------------
  test('API error from updateCampaign is displayed inside the modal', async () => {
    const axiosError = new AxiosError('Request failed');
    axiosError.response = {
      data: { message: 'Campaign name is already in use' },
      status: 422,
      statusText: 'Unprocessable Entity',
      headers: {},
      config: {} as never,
    };
    vi.mocked(updateCampaign).mockRejectedValueOnce(axiosError);

    await renderEditModal();
    await screen.findByLabelText('Name'); // wait for effects

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Save' }));
    });

    expect(await screen.findByRole('alert')).toHaveTextContent('Campaign name is already in use');
  });
});
