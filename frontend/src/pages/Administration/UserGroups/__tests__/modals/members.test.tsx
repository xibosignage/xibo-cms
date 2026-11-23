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

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, waitForElementToBeRemoved, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import ManageMembersModal from '../../components/ManageMembersModal';
import { buildMember, mockUserGroup } from '../fixtures/userGroup';

import { fetchUsers } from '@/services/userApi';
import type { FetchUsersRequest } from '@/services/userApi';
import { assignGroupMembers } from '@/services/userGroupApi';
import { testQueryClient } from '@/setupTests';
import { apiError } from '@/testUtils/apiErrors';
import { deferred } from '@/testUtils/deferred';
import { knownFailure } from '@/testUtils/knownFailure';
import type { User } from '@/types/user';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/components/ui/modals/Modal');
// Identity debounce so keyword changes reach the query immediately.
vi.mock('@/hooks/useDebounce', () => ({ useDebounce: vi.fn((v: unknown) => v) }));
vi.mock('@/services/userApi', () => ({ fetchUsers: vi.fn() }));
vi.mock('@/services/userGroupApi', () => ({ assignGroupMembers: vi.fn() }));

// Minimal SearchAssignPanel stand-in: drives the modal's add/remove/clear state
// without rendering a TanStack table.
vi.mock('@/components/ui/SearchAssignPanel', () => ({
  SearchAssignPanel: <TItem,>({
    assignedItems,
    isLoadingAssigned,
    onAddItem,
    onRemoveItem,
    onClearAll,
    assignedLabel,
    noAssignedText,
    getItemId,
    getItemLabel,
    keyword,
    onKeywordChange,
    searchLabel,
    searchRows,
  }: {
    assignedItems: TItem[];
    isLoadingAssigned?: boolean;
    onAddItem: (item: TItem) => void;
    onRemoveItem: (item: TItem) => void;
    onClearAll?: () => void;
    assignedLabel?: string;
    noAssignedText?: string;
    getItemId: (item: TItem) => number | string;
    getItemLabel: (item: TItem) => string;
    keyword: string;
    onKeywordChange: (v: string) => void;
    searchLabel?: string;
    searchRows: TItem[];
  }) => (
    <div>
      <input
        aria-label={searchLabel}
        value={keyword}
        onChange={(e) => onKeywordChange(e.target.value)}
      />
      <ul aria-label="Search results">
        {searchRows.map((item) => (
          <li key={getItemId(item)}>
            <button type="button" onClick={() => onAddItem(item)}>
              {`Add ${getItemLabel(item)}`}
            </button>
          </li>
        ))}
      </ul>
      {isLoadingAssigned ? (
        <p>Loading assigned</p>
      ) : assignedItems.length === 0 ? (
        <p>{noAssignedText}</p>
      ) : (
        <ul aria-label={assignedLabel}>
          {assignedItems.map((item) => (
            <li key={getItemId(item)}>
              {getItemLabel(item)}
              <button type="button" onClick={() => onRemoveItem(item)}>
                {`Remove ${getItemLabel(item)}`}
              </button>
            </li>
          ))}
        </ul>
      )}
      {onClearAll && (
        <button type="button" onClick={onClearAll}>
          Clear All
        </button>
      )}
    </div>
  ),
}));

// =============================================================================
// Fixtures / helpers
// =============================================================================

const alice = buildMember({ userId: 2, userName: 'alice' });
const bob = buildMember({ userId: 3, userName: 'bob' });
const carol = buildMember({ userId: 4, userName: 'carol' });

// fetchUsers serves two purposes — branch on userGroupIdMembers.
const mockUsers = ({
  members = [] as User[],
  search = [] as User[],
}: { members?: User[]; search?: User[] } = {}) => {
  // Branch needed: the members load (useEffect) and search (useQuery) call fetchUsers in no fixed order.
  vi.mocked(fetchUsers).mockImplementation(async (opts?: FetchUsersRequest) =>
    opts?.userGroupIdMembers
      ? { rows: members, totalCount: members.length }
      : { rows: search, totalCount: search.length },
  );
};

const memberCalls = () =>
  vi.mocked(fetchUsers).mock.calls.filter(([o]) => o?.userGroupIdMembers !== undefined);

const assignedList = () => screen.getByRole('list', { name: 'Group Members' });

const renderMembersModal = async () => {
  const onClose = vi.fn();
  const onSuccess = vi.fn();
  render(
    <QueryClientProvider client={testQueryClient}>
      <ManageMembersModal userGroup={mockUserGroup} onClose={onClose} onSuccess={onSuccess} />
    </QueryClientProvider>,
  );
  await waitForElementToBeRemoved(() => screen.queryByText('Loading assigned'));
  return { onClose, onSuccess, dialog: screen.getByRole('dialog') };
};

// =============================================================================
// Tests
// =============================================================================

describe('ManageMembersModal - loading', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('title names the group', async () => {
    mockUsers();
    await renderMembersModal();

    expect(screen.getByRole('dialog')).toHaveAccessibleName('Manage Members for Editors');
  });

  test('current members are loaded for this group and listed', async () => {
    mockUsers({ members: [alice, bob] });
    await renderMembersModal();

    expect(memberCalls()[0]![0]).toEqual(
      expect.objectContaining({ start: 0, length: 200, userGroupIdMembers: mockUserGroup.groupId }),
    );
    expect(within(assignedList()).getByText('alice')).toBeInTheDocument();
    expect(within(assignedList()).getByText('bob')).toBeInTheDocument();
  });

  test('groups with more than 200 members load every page', async () => {
    const page1 = Array.from({ length: 200 }, (_, i) =>
      buildMember({ userId: 1000 + i, userName: `u${i}` }),
    );
    const page2 = Array.from({ length: 50 }, (_, i) =>
      buildMember({ userId: 2000 + i, userName: `v${i}` }),
    );
    // Branch needed: members load and search share fetchUsers with no fixed call order.
    vi.mocked(fetchUsers).mockImplementation(async (opts?: FetchUsersRequest) => {
      if (!opts?.userGroupIdMembers) return { rows: [], totalCount: 0 };
      return opts.start === 0 ? { rows: page1, totalCount: 250 } : { rows: page2, totalCount: 250 };
    });

    await renderMembersModal();

    expect(memberCalls()).toHaveLength(2);
    expect(memberCalls()[1]![0]).toEqual(expect.objectContaining({ start: 200 }));
    expect(within(assignedList()).getAllByRole('listitem')).toHaveLength(250);
  });

  // Known issue: xibosignage/xibo#4122
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4122 is fixed', () => {
    // A failed load looks identical to an empty group.
    knownFailure(
      'a failed members load says the members could not be loaded',
      async () => {
        // Branch needed: members load and search share fetchUsers with no fixed call order.
        vi.mocked(fetchUsers).mockImplementation(async (opts?: FetchUsersRequest) => {
          if (opts?.userGroupIdMembers) throw new Error('boom');
          return { rows: [], totalCount: 0 };
        });
        await renderMembersModal();
        // Control: the load finished and the list is empty.
        expect(screen.getByText('No users assigned.')).toBeInTheDocument();
      },
      () =>
        expect(
          screen.getByText(/couldn.t be loaded|could not be loaded|failed to load/i),
        ).toBeInTheDocument(),
    );
  });
});

describe('ManageMembersModal - editing members', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(assignGroupMembers).mockResolvedValue(undefined);
  });

  test('typing in the search box searches users by name', async () => {
    const user = userEvent.setup();
    mockUsers({ search: [carol] });
    await renderMembersModal();

    await user.type(screen.getByRole('textbox', { name: 'Search' }), 'car');

    await waitFor(() => {
      expect(vi.mocked(fetchUsers).mock.lastCall?.[0]).toEqual(
        expect.objectContaining({ userName: 'car', start: 0, length: 5 }),
      );
    });
    await screen.findByRole('button', { name: 'Add carol' });
  });

  test('adding a user puts them in the member list', async () => {
    const user = userEvent.setup();
    mockUsers({ search: [carol] });
    await renderMembersModal();

    await user.click(await screen.findByRole('button', { name: 'Add carol' }));

    expect(within(assignedList()).getByText('carol')).toBeInTheDocument();
  });

  test('adding an existing member does nothing', async () => {
    const user = userEvent.setup();
    mockUsers({ members: [alice], search: [alice] });
    const { onClose } = await renderMembersModal();

    await user.click(await screen.findByRole('button', { name: 'Add alice' }));
    expect(within(assignedList()).getAllByRole('listitem')).toHaveLength(1);

    await user.click(screen.getByRole('button', { name: 'Save' }));
    expect(assignGroupMembers).not.toHaveBeenCalled();
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  test('Save with no changes closes without contacting the server', async () => {
    const user = userEvent.setup();
    mockUsers({ members: [alice] });
    const { onClose, onSuccess } = await renderMembersModal();

    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(onSuccess).not.toHaveBeenCalled();
    expect(assignGroupMembers).not.toHaveBeenCalled();
  });

  test('Save sends the added and removed users', async () => {
    const user = userEvent.setup();
    mockUsers({ members: [alice], search: [carol] });
    await renderMembersModal();

    await user.click(await screen.findByRole('button', { name: 'Add carol' }));
    await user.click(screen.getByRole('button', { name: 'Remove alice' }));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() =>
      expect(assignGroupMembers).toHaveBeenCalledWith(mockUserGroup.groupId, [4], [2]),
    );
  });

  // Known issue: xibosignage/xibo#4124
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4124 is fixed', () => {
    // Removing a user added this session still queues them for removal.
    knownFailure(
      'adding then removing a non-member sends nothing for them',
      async () => {
        const user = userEvent.setup();
        mockUsers({ search: [carol] });
        const { onClose } = await renderMembersModal();
        await user.click(await screen.findByRole('button', { name: 'Add carol' }));
        await user.click(screen.getByRole('button', { name: 'Remove carol' }));
        await user.click(screen.getByRole('button', { name: 'Save' }));
        // Control: Save finished and closed the modal.
        await waitFor(() => expect(onClose).toHaveBeenCalled());
      },
      () => expect(assignGroupMembers).not.toHaveBeenCalled(),
    );
  });

  test('Clear All removes original members and drops unsaved additions', async () => {
    const user = userEvent.setup();
    mockUsers({ members: [alice], search: [carol] });
    await renderMembersModal();

    await user.click(await screen.findByRole('button', { name: 'Add carol' }));
    await user.click(screen.getByRole('button', { name: 'Clear All' }));
    expect(screen.getByText('No users assigned.')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() =>
      expect(assignGroupMembers).toHaveBeenCalledWith(mockUserGroup.groupId, [], [2]),
    );
  });
});

describe('ManageMembersModal - save outcome', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockUsers({ members: [alice] });
  });

  test('success calls onSuccess then onClose', async () => {
    const user = userEvent.setup();
    vi.mocked(assignGroupMembers).mockResolvedValue(undefined);
    const { onClose, onSuccess } = await renderMembersModal();

    await user.click(screen.getByRole('button', { name: 'Remove alice' }));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(onClose).toHaveBeenCalledTimes(1));
    expect(onSuccess).toHaveBeenCalledTimes(1);
    expect(onSuccess.mock.invocationCallOrder[0]!).toBeLessThan(
      onClose.mock.invocationCallOrder[0]!,
    );
  });

  test('Save shows "Saving…" and is disabled while in progress', async () => {
    const user = userEvent.setup();
    const pending = deferred<void>();
    vi.mocked(assignGroupMembers).mockReturnValue(pending.promise);
    const { onClose } = await renderMembersModal();

    await user.click(screen.getByRole('button', { name: 'Remove alice' }));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByRole('button', { name: 'Saving…' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Cancel' })).toBeDisabled();

    pending.resolve();
    await waitFor(() => expect(onClose).toHaveBeenCalled());
  });

  test('server error message is shown and the modal stays open', async () => {
    const user = userEvent.setup();
    vi.mocked(assignGroupMembers).mockRejectedValue(apiError('Not allowed', 403));
    const { onClose } = await renderMembersModal();

    await user.click(screen.getByRole('button', { name: 'Remove alice' }));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Not allowed');
    expect(assignGroupMembers).toHaveBeenCalledTimes(1);
    expect(onClose).not.toHaveBeenCalled();
  });

  test('error without a server message shows the generic text', async () => {
    const user = userEvent.setup();
    vi.mocked(assignGroupMembers).mockRejectedValue(new Error('boom'));
    await renderMembersModal();

    await user.click(screen.getByRole('button', { name: 'Remove alice' }));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Failed to update group members.');
    expect(assignGroupMembers).toHaveBeenCalledWith(mockUserGroup.groupId, [], [2]);
  });

  test('Cancel closes without saving', async () => {
    const user = userEvent.setup();
    const { onClose } = await renderMembersModal();

    await user.click(screen.getByRole('button', { name: 'Remove alice' }));
    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(assignGroupMembers).not.toHaveBeenCalled();
  });
});
