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

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockUser } from '../fixtures/user';

import FeaturesModal from '@/pages/Administration/Users/components/FeaturesModal';
import { fetchUserGroupById, fetchUserGroups, updateGroupFeatures } from '@/services/userGroupApi';
import type { User } from '@/types/user';
import type { UserGroup } from '@/types/userGroup';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/userGroupApi');

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Helpers
// =============================================================================

const setupFetches = (
  ownFeatures: string[] = [],
  allGroups: { rows: { groupId: number; isUserSpecific: number; features: string[] }[] } = {
    rows: [],
  },
) => {
  vi.mocked(fetchUserGroupById).mockResolvedValue({
    groupId: mockUser.groupId!,
    group: 'Users',
    isUserSpecific: 0,
    isEveryone: 0,
    features: ownFeatures,
  });
  vi.mocked(fetchUserGroups).mockResolvedValue({
    rows: allGroups.rows.map((g) => ({
      groupId: g.groupId,
      group: `Group ${g.groupId}`,
      isUserSpecific: g.isUserSpecific,
      isEveryone: 0,
      features: g.features,
    })),
    totalCount: allGroups.rows.length,
  });
};

const expandGroup = async (user: ReturnType<typeof userEvent.setup>, groupLabel: string) => {
  await user.click(screen.getByRole('button', { name: new RegExp(`^${groupLabel}`, 'i') }));
};

// =============================================================================
// Tests
// =============================================================================

describe('FeaturesModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setupFetches();
  });

  test("loads the user's group features and inherited features on mount", async () => {
    const memberGroupUser: User = { ...mockUser, groupId: 2, groups: [{ groupId: 5 } as never] };
    setupFetches(['folder.view'], {
      rows: [{ groupId: 5, isUserSpecific: 0, features: ['folder.add'] }],
    });

    render(<FeaturesModal user={memberGroupUser} onClose={vi.fn()} onSuccess={vi.fn()} />);

    await waitFor(() => {
      expect(fetchUserGroupById).toHaveBeenCalledWith(2);
      expect(fetchUserGroups).toHaveBeenCalledWith({ start: 0, length: 1000 });
    });
  });

  test('shows "Loading..." while fetching', async () => {
    let resolveFetch!: (v: UserGroup) => void;
    vi.mocked(fetchUserGroupById).mockReturnValue(
      new Promise((res) => {
        resolveFetch = res;
      }),
    );

    render(<FeaturesModal user={mockUser} onClose={vi.fn()} onSuccess={vi.fn()} />);

    await screen.findByText('Loading...');

    resolveFetch({
      groupId: mockUser.groupId!,
      group: 'Users',
      isUserSpecific: 0,
      isEveryone: 0,
      features: [],
    });
    await waitFor(() => expect(screen.queryByText('Loading...')).not.toBeInTheDocument());
  });

  test("category tabs render; switching tabs shows that category's groups", async () => {
    const user = userEvent.setup();
    render(<FeaturesModal user={mockUser} onClose={vi.fn()} onSuccess={vi.fn()} />);

    await screen.findByRole('button', { name: /^folders/i });

    await user.click(screen.getByRole('tab', { name: /^displays/i }));

    expect(screen.getByRole('button', { name: /^displays/i })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^folders/i })).not.toBeInTheDocument();
  });

  test('toggling a single feature checkbox updates enabledFeatures', async () => {
    const user = userEvent.setup();
    render(<FeaturesModal user={mockUser} onClose={vi.fn()} onSuccess={vi.fn()} />);
    await screen.findByRole('button', { name: /^folders/i });

    await expandGroup(user, 'Folders');

    const checkbox = screen.getByRole('checkbox', {
      name: /enable view folder tree on grids and forms/i,
    });
    expect(checkbox).not.toBeChecked();

    await user.click(checkbox);

    expect(checkbox).toBeChecked();
  });

  test('toggling the group header checkbox enables every feature in the group', async () => {
    const user = userEvent.setup();
    render(<FeaturesModal user={mockUser} onClose={vi.fn()} onSuccess={vi.fn()} />);
    await screen.findByRole('button', { name: /^folders/i });

    await user.click(screen.getByRole('checkbox', { name: /enable all in folders/i }));
    await expandGroup(user, 'Folders');

    expect(
      screen.getByRole('checkbox', { name: /enable view folder tree on grids and forms/i }),
    ).toBeChecked();
    expect(
      screen.getByRole('checkbox', { name: /enable set a home folder for a user/i }),
    ).toBeChecked();
  });

  test('group header checkbox is indeterminate when only some features are enabled', async () => {
    setupFetches(['folder.view']);
    render(<FeaturesModal user={mockUser} onClose={vi.fn()} onSuccess={vi.fn()} />);
    await screen.findByRole('button', { name: /^folders/i });

    const groupCheckbox = screen.getByRole('checkbox', {
      name: /enable all in folders/i,
    }) as HTMLInputElement;

    expect(groupCheckbox.indeterminate).toBe(true);
  });

  test('inherited checkbox is always disabled and reflects inheritedFeatures', async () => {
    const user = userEvent.setup();
    const memberGroupUser: User = { ...mockUser, groupId: 2, groups: [{ groupId: 5 } as never] };
    setupFetches([], { rows: [{ groupId: 5, isUserSpecific: 0, features: ['folder.add'] }] });

    render(<FeaturesModal user={memberGroupUser} onClose={vi.fn()} onSuccess={vi.fn()} />);
    await screen.findByRole('button', { name: /^folders/i });
    await expandGroup(user, 'Folders');

    const inheritedCheckbox = await screen.findByRole('checkbox', {
      name: /allow users to create sub-folders under folders they have access to.*inherited/i,
    });

    expect(inheritedCheckbox).toBeChecked();
    expect(inheritedCheckbox).toBeDisabled();
  });

  test('expanding/collapsing a group shows/hides its individual feature rows', async () => {
    const user = userEvent.setup();
    render(<FeaturesModal user={mockUser} onClose={vi.fn()} onSuccess={vi.fn()} />);
    await screen.findByRole('button', { name: /^folders/i });

    expect(screen.queryByText('View Folder Tree on Grids and Forms')).not.toBeInTheDocument();

    await expandGroup(user, 'Folders');
    expect(screen.getByText('View Folder Tree on Grids and Forms')).toBeInTheDocument();

    await expandGroup(user, 'Folders');
    expect(screen.queryByText('View Folder Tree on Grids and Forms')).not.toBeInTheDocument();
  });

  test('save calls updateGroupFeatures with the group id and enabled features', async () => {
    const user = userEvent.setup();
    vi.mocked(updateGroupFeatures).mockResolvedValue(undefined);
    render(<FeaturesModal user={mockUser} onClose={vi.fn()} onSuccess={vi.fn()} />);
    await screen.findByRole('button', { name: /^folders/i });

    await expandGroup(user, 'Folders');
    await user.click(
      screen.getByRole('checkbox', { name: /enable view folder tree on grids and forms/i }),
    );
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(updateGroupFeatures).toHaveBeenCalledWith(mockUser.groupId, ['folder.view']);
    });
  });

  test('successful save calls onSuccess and onClose', async () => {
    const user = userEvent.setup();
    vi.mocked(updateGroupFeatures).mockResolvedValue(undefined);
    const onSuccess = vi.fn();
    const onClose = vi.fn();

    render(<FeaturesModal user={mockUser} onClose={onClose} onSuccess={onSuccess} />);
    await screen.findByRole('button', { name: /^folders/i });

    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(onSuccess).toHaveBeenCalledTimes(1));
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  test('API error displays in the modal', async () => {
    const user = userEvent.setup();
    vi.mocked(updateGroupFeatures).mockRejectedValue({
      isAxiosError: true,
      response: { data: { message: 'Failed to update features' } },
    });

    render(<FeaturesModal user={mockUser} onClose={vi.fn()} onSuccess={vi.fn()} />);
    await screen.findByRole('button', { name: /^folders/i });

    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByText('Failed to update features')).toBeInTheDocument();
  });

  test('Save button shows "Saving..." while pending', async () => {
    const user = userEvent.setup();
    let resolveSave!: () => void;
    vi.mocked(updateGroupFeatures).mockReturnValue(
      new Promise((res) => {
        resolveSave = res;
      }),
    );

    render(<FeaturesModal user={mockUser} onClose={vi.fn()} onSuccess={vi.fn()} />);
    await screen.findByRole('button', { name: /^folders/i });

    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(screen.getByRole('button', { name: 'Saving...' })).toBeDisabled());

    resolveSave();
    await waitFor(() =>
      expect(screen.queryByRole('button', { name: 'Saving...' })).not.toBeInTheDocument(),
    );
  });

  test('no groupId skips the fetch and clears loading immediately', async () => {
    const noGroupUser: User = { ...mockUser, groupId: undefined };
    render(<FeaturesModal user={noGroupUser} onClose={vi.fn()} onSuccess={vi.fn()} />);

    await screen.findByRole('button', { name: /^folders/i });
    expect(fetchUserGroupById).not.toHaveBeenCalled();
  });
});
