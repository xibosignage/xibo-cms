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

// =============================================================================
// Test type: Component test — payload
// ShareModal's "Select Owner" picker: where its options come from, how the
// current owner is named, paging through owners, and the ownerId it saves.
// The real SelectDropdown is used, so its own search and load-more paths run
// (stubbing it is how four dropdown pagination faults went unseen).
//
// JSDOM has no IntersectionObserver; the stub keeps SelectDropdown's callback
// so a test can report "the end of the owner list is visible".
// The owner search box has no accessible name: it is the textbox that
// appears when the picker opens, next to the (also unnamed) name search.
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, afterEach, describe, test, expect } from 'vitest';

import {
  EMPTY_OWNER_LIST,
  FULL_OWNER_PAGE,
  mockOwnerBob,
  OWNER_LIST,
  PERMISSION_TABLE,
} from './fixtures/permissions';
import {
  findPermissionRow,
  nameSearch,
  renderShareModal,
  saveButton,
} from './helpers/renderShareModal';

import { fetchPermissions, savePermissions } from '@/services/permissionsApi';
import { fetchUsers } from '@/services/userApi';

vi.mock('@/components/ui/modals/Modal');
vi.mock('@/hooks/useDebounce');
vi.mock('@/components/ui/Notification', () => ({
  notify: { info: vi.fn(), error: vi.fn(), success: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/services/permissionsApi', () => ({
  fetchPermissions: vi.fn(),
  fetchMultiPermissions: vi.fn(),
  savePermissions: vi.fn(),
  saveMultiPermissions: vi.fn(),
}));
vi.mock('@/services/userApi', () => ({
  fetchUsers: vi.fn(),
}));

let reportEndOfList: () => void = () => {};

class IntersectionObserverStub {
  constructor(callback: (entries: Partial<IntersectionObserverEntry>[]) => void) {
    reportEndOfList = () => callback([{ isIntersecting: true }]);
  }
  observe() {}
  unobserve() {}
  disconnect() {}
}

const ownerPicker = () => screen.getByRole('combobox', { name: 'Select Owner' });

const openOwnerPicker = async (user: UserEvent) => {
  await user.click(ownerPicker());
  return screen.findByRole('listbox');
};

// Call with the name search captured before the picker opened.
const ownerSearch = (nameBox: HTMLElement) =>
  screen.getAllByRole('textbox').find((box) => box !== nameBox)!;

const lastSavePayload = () => {
  const calls = vi.mocked(savePermissions).mock.calls;
  return calls[calls.length - 1]![0];
};

beforeEach(() => {
  vi.clearAllMocks();
  vi.stubGlobal('IntersectionObserver', IntersectionObserverStub);
  reportEndOfList = () => {};
  vi.mocked(fetchPermissions).mockResolvedValue(PERMISSION_TABLE);
  vi.mocked(fetchUsers).mockResolvedValue(OWNER_LIST);
  vi.mocked(savePermissions).mockResolvedValue(undefined);
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('ShareModal — owner', () => {
  test('the owner list is filled from the first 10 users', async () => {
    const user = userEvent.setup();
    renderShareModal();
    await findPermissionRow('Alice');

    await openOwnerPicker(user);

    expect(fetchUsers).toHaveBeenCalledWith({ start: 0, length: 10, userName: undefined });
    await screen.findByRole('option', { name: /admin/ });
    expect(screen.getByRole('option', { name: /Bob/ })).toBeInTheDocument();
  });

  test('an item that already has an owner shows that owner by name', async () => {
    vi.mocked(fetchUsers).mockImplementation(async (options) =>
      options?.userId === mockOwnerBob.userId
        ? { rows: [mockOwnerBob], totalCount: 1 }
        : EMPTY_OWNER_LIST,
    );
    renderShareModal({ currentOwnerId: 2 });

    await waitFor(() => expect(ownerPicker()).toHaveTextContent('Bob'));
    expect(fetchUsers).toHaveBeenCalledWith({ start: 0, length: 1, userId: 2 });
  });

  test('choosing a different owner and saving sends that owner id as a number', async () => {
    const user = userEvent.setup();
    renderShareModal({ currentOwnerId: 1 });
    await findPermissionRow('Alice');

    await openOwnerPicker(user);
    await user.click(await screen.findByRole('option', { name: /Bob/ }));
    await user.click(saveButton());

    await waitFor(() => expect(savePermissions).toHaveBeenCalledTimes(1));
    expect(savePermissions).toHaveBeenCalledWith(expect.objectContaining({ id: 7, ownerId: 2 }));
  });

  test('saving without any owner chosen sends no owner', async () => {
    const user = userEvent.setup();
    renderShareModal();
    await findPermissionRow('Alice');

    await user.click(saveButton());

    await waitFor(() => expect(savePermissions).toHaveBeenCalledTimes(1));
    expect(lastSavePayload().ownerId).toBeUndefined();
  });

  test('typing in the owner search reloads owners by that name', async () => {
    const user = userEvent.setup();
    renderShareModal();
    await findPermissionRow('Alice');

    const nameBox = nameSearch();
    await openOwnerPicker(user);
    await user.type(ownerSearch(nameBox), 'bo');

    await waitFor(() =>
      expect(fetchUsers).toHaveBeenLastCalledWith({ start: 0, length: 10, userName: 'bo' }),
    );
  });

  test('when a full page of owners is shown, reaching the end loads the next page', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchUsers).mockImplementation(async (options) =>
      options?.start === 10 ? { rows: [mockOwnerBob], totalCount: 11 } : FULL_OWNER_PAGE,
    );
    renderShareModal();
    await findPermissionRow('Alice');

    await openOwnerPicker(user);
    await screen.findByRole('option', { name: /owner10/ });
    expect(screen.queryByRole('option', { name: /Bob/ })).not.toBeInTheDocument();

    reportEndOfList();

    await screen.findByRole('option', { name: /Bob/ });
    expect(fetchUsers).toHaveBeenCalledWith({ start: 10, length: 10, userName: undefined });
    expect(screen.getAllByRole('option')).toHaveLength(11);
  });

  test('if the owner list fails to load, the picker is empty and the modal still works', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchUsers).mockRejectedValue(new Error('500'));
    renderShareModal();
    await findPermissionRow('Alice');

    await openOwnerPicker(user);

    await screen.findByText('No results');
    expect(fetchUsers).toHaveBeenCalled();
    expect(screen.queryAllByRole('option')).toHaveLength(0);
    expect(saveButton()).toBeEnabled();
  });
});
