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

import { buildUser, mockUser } from '../fixtures/user';

import SetHomeFolderModal from '@/pages/Administration/Users/components/SetHomeFolderModal';
import { setUserHomeFolder } from '@/services/userApi';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/userApi');

vi.mock('@/components/ui/modals/Modal');

vi.mock('@/components/ui/forms/SelectFolder', () => ({
  default: ({
    selectedId,
    onSelect,
  }: {
    selectedId?: number | null;
    onSelect: (folder: { id: number; text: string } | null) => void;
  }) => (
    <button onClick={() => onSelect({ id: 5, text: 'Marketing' })}>
      Select Folder (current: {selectedId ?? 'none'})
    </button>
  ),
}));

// =============================================================================
// Tests
// =============================================================================

describe('SetHomeFolderModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('title is singular for one user', async () => {
    render(<SetHomeFolderModal users={[mockUser]} onClose={vi.fn()} onSuccess={vi.fn()} />);

    await screen.findByRole('dialog', {
      name: new RegExp(`set home folder for ${mockUser.userName}`, 'i'),
    });
  });

  test('title is plural with a count for multiple users', async () => {
    const secondUser = buildUser({ userId: 4, userName: 'msmith' });
    render(
      <SetHomeFolderModal users={[mockUser, secondUser]} onClose={vi.fn()} onSuccess={vi.fn()} />,
    );

    await screen.findByRole('dialog', { name: /set home folder for 2 users/i });
  });

  test("the folder picker starts with the first user's home folder selected", async () => {
    const firstUser = buildUser({ homeFolderId: 7 });
    render(<SetHomeFolderModal users={[firstUser]} onClose={vi.fn()} onSuccess={vi.fn()} />);

    expect(await screen.findByText(/current: 7/i)).toBeInTheDocument();
  });

  test('save calls setUserHomeFolder once per selected user', async () => {
    const user = userEvent.setup();
    vi.mocked(setUserHomeFolder).mockResolvedValue(undefined);
    const secondUser = buildUser({ userId: 4, userName: 'msmith' });

    render(
      <SetHomeFolderModal users={[mockUser, secondUser]} onClose={vi.fn()} onSuccess={vi.fn()} />,
    );

    await user.click(await screen.findByRole('button', { name: /select folder/i }));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(setUserHomeFolder).toHaveBeenCalledTimes(2));
    expect(setUserHomeFolder).toHaveBeenCalledWith(mockUser.userId, 5);
    expect(setUserHomeFolder).toHaveBeenCalledWith(secondUser.userId, 5);
  });

  test('all succeeding calls onSuccess and onClose', async () => {
    const user = userEvent.setup();
    vi.mocked(setUserHomeFolder).mockResolvedValue(undefined);
    const onSuccess = vi.fn();
    const onClose = vi.fn();

    render(<SetHomeFolderModal users={[mockUser]} onClose={onClose} onSuccess={onSuccess} />);

    await user.click(await screen.findByRole('button', { name: /select folder/i }));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(onSuccess).toHaveBeenCalledTimes(1));
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  test('partial failure shows the error, calls onSuccess, but not onClose', async () => {
    const user = userEvent.setup();
    const secondUser = buildUser({ userId: 4, userName: 'msmith' });
    vi.mocked(setUserHomeFolder).mockImplementation((userId) =>
      userId === secondUser.userId
        ? Promise.reject({ isAxiosError: true, response: { data: { message: 'Update failed' } } })
        : Promise.resolve(undefined),
    );
    const onSuccess = vi.fn();
    const onClose = vi.fn();

    render(
      <SetHomeFolderModal users={[mockUser, secondUser]} onClose={onClose} onSuccess={onSuccess} />,
    );

    await user.click(await screen.findByRole('button', { name: /select folder/i }));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByText('Update failed')).toBeInTheDocument();
    expect(onSuccess).toHaveBeenCalledTimes(1);
    expect(onClose).not.toHaveBeenCalled();
  });

  test('Cancel closes without saving', async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();

    render(<SetHomeFolderModal users={[mockUser]} onClose={onClose} onSuccess={vi.fn()} />);

    await screen.findByRole('dialog', {
      name: new RegExp(`set home folder for ${mockUser.userName}`, 'i'),
    });
    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(setUserHomeFolder).not.toHaveBeenCalled();
  });

  test('Save button shows "Saving..." while pending', async () => {
    const user = userEvent.setup();
    let resolveSave!: () => void;
    vi.mocked(setUserHomeFolder).mockReturnValue(
      new Promise((res) => {
        resolveSave = res;
      }),
    );

    render(<SetHomeFolderModal users={[mockUser]} onClose={vi.fn()} onSuccess={vi.fn()} />);

    await user.click(await screen.findByRole('button', { name: /select folder/i }));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(screen.getByRole('button', { name: 'Saving...' })).toBeDisabled());

    resolveSave();
    await waitFor(() =>
      expect(screen.queryByRole('button', { name: 'Saving...' })).not.toBeInTheDocument(),
    );
  });
});
