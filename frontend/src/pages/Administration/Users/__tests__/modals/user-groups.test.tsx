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
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockUser, mockUserGroup } from '../fixtures/user';

import UserGroupsModal from '@/pages/Administration/Users/components/UserGroupsModal';
import { assignUserGroups } from '@/services/userApi';
import { fetchUserGroups } from '@/services/userGroupApi';
import { testQueryClient } from '@/setupTests';
import type { UserGroup } from '@/types/userGroup';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/userApi');

vi.mock('@/services/userGroupApi');

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Helpers
// =============================================================================

const renderModal = (props: Partial<React.ComponentProps<typeof UserGroupsModal>> = {}) => {
  return render(
    <QueryClientProvider client={testQueryClient}>
      <MemoryRouter>
        <UserGroupsModal user={mockUser} onClose={vi.fn()} onSuccess={vi.fn()} {...props} />
      </MemoryRouter>
    </QueryClientProvider>,
  );
};

const marketingGroup: UserGroup = {
  groupId: 9,
  group: 'Marketing',
  isUserSpecific: 0,
  isEveryone: 0,
};

const mockFetchUserGroupsByCall = (
  assigned: { rows: UserGroup[]; totalCount: number },
  search: { rows: UserGroup[]; totalCount: number },
) => {
  vi.mocked(fetchUserGroups).mockImplementation(async (params) => {
    if (params?.userIdMember !== undefined) return assigned;
    return search;
  });
};

// =============================================================================
// Tests
// =============================================================================

describe('UserGroupsModal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchUserGroupsByCall(
      { rows: [mockUserGroup], totalCount: 1 },
      { rows: [], totalCount: 0 },
    );
  });

  test('loads currently assigned groups, excluding user-specific groups', async () => {
    mockFetchUserGroupsByCall(
      {
        rows: [
          mockUserGroup,
          { ...mockUserGroup, groupId: 9, group: 'Personal', isUserSpecific: 1 },
        ],
        totalCount: 2,
      },
      { rows: [], totalCount: 0 },
    );

    renderModal();

    expect(await screen.findByText(mockUserGroup.group)).toBeInTheDocument();
    expect(screen.queryByText('Personal')).not.toBeInTheDocument();
  });

  test('search input filters available groups after a debounce', async () => {
    const user = userEvent.setup();
    renderModal();
    await screen.findByText(mockUserGroup.group);

    await user.type(screen.getByRole('textbox', { name: /search/i }), 'Market');

    await waitFor(
      () => {
        expect(fetchUserGroups).toHaveBeenCalledWith(
          expect.objectContaining({ userGroup: 'Market' }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('adding a searched group moves it into the assigned list', async () => {
    const user = userEvent.setup();
    mockFetchUserGroupsByCall(
      { rows: [mockUserGroup], totalCount: 1 },
      { rows: [marketingGroup], totalCount: 1 },
    );

    renderModal();
    await screen.findByText(mockUserGroup.group);
    await screen.findByText(marketingGroup.group);

    await user.click(screen.getByRole('button', { name: /^add$/i }));

    // "Marketing" now appears twice — once as an assigned chip, once in the
    // search results row (whose action icon flips from Add to Remove).
    expect(await screen.findByRole('button', { name: 'Remove Marketing' })).toBeInTheDocument();
    expect(await screen.findAllByText(marketingGroup.group)).toHaveLength(2);
  });

  test('removing an assigned group takes it out of the assigned list', async () => {
    const user = userEvent.setup();
    renderModal();
    await screen.findByText(mockUserGroup.group);

    await user.click(screen.getByRole('button', { name: `Remove ${mockUserGroup.group}` }));

    expect(screen.queryByText(mockUserGroup.group)).not.toBeInTheDocument();
    expect(await screen.findByText('No groups assigned.')).toBeInTheDocument();
  });

  test('"Clear" removes every assigned group', async () => {
    const user = userEvent.setup();
    renderModal();
    await screen.findByText(mockUserGroup.group);

    await user.click(screen.getByRole('button', { name: 'Clear' }));

    expect(await screen.findByText('No groups assigned.')).toBeInTheDocument();
  });

  test('save with no pending changes just closes the modal', async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();
    renderModal({ onClose });
    await screen.findByText(mockUserGroup.group);

    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(assignUserGroups).not.toHaveBeenCalled();
  });

  test('save with pending changes calls assignUserGroups with toAdd/toRemove', async () => {
    const user = userEvent.setup();
    mockFetchUserGroupsByCall(
      { rows: [mockUserGroup], totalCount: 1 },
      { rows: [marketingGroup], totalCount: 1 },
    );
    vi.mocked(assignUserGroups).mockResolvedValue(undefined);

    renderModal();
    await screen.findByText(mockUserGroup.group);
    await screen.findByText(marketingGroup.group);

    await user.click(screen.getByRole('button', { name: /^add$/i }));
    await screen.findAllByText(marketingGroup.group);
    await user.click(screen.getByRole('button', { name: `Remove ${mockUserGroup.group}` }));

    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(assignUserGroups).toHaveBeenCalledWith(
        mockUser.userId,
        [marketingGroup.groupId],
        [mockUserGroup.groupId],
      );
    });
  });

  test('successful save calls onSuccess and onClose', async () => {
    const user = userEvent.setup();
    vi.mocked(assignUserGroups).mockResolvedValue(undefined);
    const onSuccess = vi.fn();
    const onClose = vi.fn();

    renderModal({ onSuccess, onClose });
    await screen.findByText(mockUserGroup.group);

    await user.click(screen.getByRole('button', { name: /remove/i }));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(onSuccess).toHaveBeenCalledTimes(1));
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  test('failed save shows an error and keeps the modal open', async () => {
    const user = userEvent.setup();
    vi.mocked(assignUserGroups).mockRejectedValue({
      isAxiosError: true,
      response: { data: { message: 'Failed to update group membership.' } },
    });

    renderModal();
    await screen.findByText(mockUserGroup.group);

    await user.click(screen.getByRole('button', { name: /remove/i }));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByText('Failed to update group membership.')).toBeInTheDocument();
  });

  test('Save button shows "Saving…" while pending', async () => {
    const user = userEvent.setup();
    let resolveSave!: () => void;
    vi.mocked(assignUserGroups).mockReturnValue(
      new Promise((res) => {
        resolveSave = res;
      }),
    );

    renderModal();
    await screen.findByText(mockUserGroup.group);

    await user.click(screen.getByRole('button', { name: /remove/i }));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(screen.getByRole('button', { name: 'Saving…' })).toBeDisabled());

    resolveSave();
    await waitFor(() =>
      expect(screen.queryByRole('button', { name: 'Saving…' })).not.toBeInTheDocument(),
    );
  });
});
