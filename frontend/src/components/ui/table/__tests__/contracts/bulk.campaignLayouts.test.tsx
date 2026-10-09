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
import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, test, vi } from 'vitest';

import type * as TagInputModule from '@/components/ui/forms/TagInput';
import { UserProvider } from '@/context/UserContext';
import { mockCampaign, mockUser } from '@/pages/Design/Campaigns/__tests__/campaignTestUtils';
import EditCampaignModal from '@/pages/Design/Campaigns/components/EditCampaignModal';
import { updateCampaign } from '@/services/campaignApi';
import { fetchLayouts } from '@/services/layoutsApi';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import type { Layout } from '@/types/layout';

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

// The layout picker is replaced by a plain list with Add buttons, the same stub
// Campaigns.edit.modal.test.tsx uses, so the test is about what Save sends, not the picker.
vi.mock('@/components/ui/SearchAssignPanel', () => ({
  SearchAssignPanel: ({
    assignedItems,
    onAddItem,
    searchRows,
    getItemLabel,
    getItemId,
  }: {
    assignedItems: Layout[];
    onAddItem: (item: Layout) => void;
    searchRows?: Layout[];
    getItemLabel: (item: Layout) => string;
    getItemId: (item: Layout) => string | number;
  }) => (
    <div>
      <ul aria-label="Assigned layouts">
        {assignedItems.map((item) => (
          <li key={getItemId(item)}>{getItemLabel(item)}</li>
        ))}
      </ul>
      {searchRows?.map((item) => (
        <button key={getItemId(item)} onClick={() => onAddItem(item)}>
          Add {getItemLabel(item)}
        </button>
      ))}
    </div>
  ),
}));

const layout = (layoutId: number, name: string) =>
  ({ layoutId, layout: name, campaignId: layoutId + 100, tags: [] }) as unknown as Layout;
const ALPHA = layout(10, 'Alpha');
const BETA = layout(11, 'Beta');
const GAMMA = layout(12, 'Gamma');

const renderModal = async () => {
  testQueryClient.clear();
  await act(async () => {
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <EditCampaignModal isOpen campaign={mockCampaign} onClose={vi.fn()} onSuccess={vi.fn()} />
        </UserProvider>
      </QueryClientProvider>,
    );
  });
};

/** The layoutIds of every updateCampaign call that replaced the campaign's layouts. */
const savedLayoutLists = () =>
  vi
    .mocked(updateCampaign)
    .mock.calls.map(([, payload]) => payload)
    .filter((payload) => payload.manageLayouts === 1)
    .map((payload) => payload.layoutIds);

/** Open the modal, click Save straight away, and return the layout lists Save sent. */
const saveWithoutTouchingLayouts = async () => {
  const user = userEvent.setup();
  await renderModal();

  await user.click(screen.getByRole('button', { name: 'Save' }));

  await waitFor(() => expect(updateCampaign).toHaveBeenCalled());
  return savedLayoutLists();
};

beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(updateCampaign).mockResolvedValue(mockCampaign);
});

describe('saving a list campaign keeps every assigned layout', () => {
  test('Save sends every assigned layout, in display order, including one just added', async () => {
    vi.mocked(fetchLayouts).mockImplementation(async (params) =>
      params?.campaignId
        ? { rows: [ALPHA, BETA], totalCount: 2 }
        : { rows: [GAMMA], totalCount: 1 },
    );
    const user = userEvent.setup();
    await renderModal();

    await user.click(screen.getByRole('button', { name: 'Layouts' }));
    await user.click(await screen.findByRole('button', { name: 'Add Gamma' }));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(savedLayoutLists()).toEqual([[10, 11, 12]]));
  });

  // Until the campaign's layouts have loaded, the
  // modal's list is empty; saving then (e.g. after renaming on the General tab) sends
  // `manageLayouts: 1, layoutIds: []` and the server unassigns every layout.
  knownFailure(
    'saving before the campaign’s layouts have loaded does not unassign them',
    async () => {
      vi.mocked(fetchLayouts).mockImplementation((params) =>
        params?.campaignId
          ? new Promise(() => {}) // still loading
          : Promise.resolve({ rows: [], totalCount: 0 }),
      );
      return saveWithoutTouchingLayouts();
    },
    (saved) => expect(saved).not.toContainEqual([]),
  );

  // If loading the campaign's layouts fails, the list stays empty and Save
  // unassigns every layout.
  knownFailure(
    'if loading the campaign’s layouts fails, saving does not unassign them',
    async () => {
      vi.mocked(fetchLayouts).mockImplementation((params) =>
        params?.campaignId
          ? Promise.reject(new Error('network'))
          : Promise.resolve({ rows: [], totalCount: 0 }),
      );
      return saveWithoutTouchingLayouts();
    },
    (saved) => expect(saved).not.toContainEqual([]),
  );
});
