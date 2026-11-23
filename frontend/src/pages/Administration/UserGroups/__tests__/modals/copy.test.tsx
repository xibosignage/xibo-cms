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

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import CopyUserGroupModal from '../../components/CopyUserGroupModal';
import { buildUserGroup, mockUserGroup } from '../fixtures/userGroup';

import { copyUserGroup } from '@/services/userGroupApi';
import { apiError } from '@/testUtils/apiErrors';
import { deferred } from '@/testUtils/deferred';
import type { UserGroup } from '@/types/userGroup';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/userGroupApi');
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Helpers
// =============================================================================

const renderCopyModal = async () => {
  const onClose = vi.fn();
  const onSuccess = vi.fn();
  render(<CopyUserGroupModal userGroup={mockUserGroup} onClose={onClose} onSuccess={onSuccess} />);
  await screen.findByRole('dialog', { name: 'Copy User Group' });
  return { onClose, onSuccess };
};

const nameInput = () => screen.getByRole('textbox', { name: /new group name/i });
const copyMembers = () => screen.getByRole('checkbox', { name: /^copy members$/i });
const copyFeatures = () => screen.getByRole('checkbox', { name: /^copy features$/i });

// =============================================================================
// Tests
// =============================================================================

describe('CopyUserGroupModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(copyUserGroup).mockResolvedValue(buildUserGroup());
  });

  test('name defaults to "{group} (Copy)"', async () => {
    await renderCopyModal();
    expect(nameInput()).toHaveValue('Editors (Copy)');
  });

  test('Copy Members starts unticked and Copy Features starts ticked', async () => {
    await renderCopyModal();
    expect(copyMembers()).not.toBeChecked();
    expect(copyFeatures()).toBeChecked();
  });

  test('a blank name shows "Group name is required" and does not copy', async () => {
    const user = userEvent.setup();
    await renderCopyModal();

    await user.clear(nameInput());
    await user.type(nameInput(), '   ');
    await user.click(screen.getByRole('button', { name: 'Copy' }));

    await screen.findByText('Group name is required');
    expect(copyUserGroup).not.toHaveBeenCalled();
  });

  test('Copy sends the trimmed name and both flags', async () => {
    const user = userEvent.setup();
    await renderCopyModal();

    await user.clear(nameInput());
    await user.type(nameInput(), '  Editors 2  ');
    await user.click(copyMembers());
    await user.click(copyFeatures());
    await user.click(screen.getByRole('button', { name: 'Copy' }));

    await waitFor(() => expect(copyUserGroup).toHaveBeenCalledTimes(1));
    expect(copyUserGroup).toHaveBeenCalledWith(mockUserGroup.groupId, {
      group: 'Editors 2',
      copyMembers: 1,
      copyFeatures: 0,
    });
  });

  test('success calls onSuccess then onClose', async () => {
    const user = userEvent.setup();
    const { onClose, onSuccess } = await renderCopyModal();

    await user.click(screen.getByRole('button', { name: 'Copy' }));

    await waitFor(() => expect(onClose).toHaveBeenCalledTimes(1));
    expect(onSuccess).toHaveBeenCalledTimes(1);
    expect(onSuccess.mock.invocationCallOrder[0]!).toBeLessThan(
      onClose.mock.invocationCallOrder[0]!,
    );
  });

  test('server error is shown and the modal stays open', async () => {
    const user = userEvent.setup();
    vi.mocked(copyUserGroup).mockRejectedValue(apiError('Name already in use', 409));
    const { onClose } = await renderCopyModal();

    await user.click(screen.getByRole('button', { name: 'Copy' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Name already in use');
    expect(copyUserGroup).toHaveBeenCalledTimes(1);
    expect(onClose).not.toHaveBeenCalled();
  });

  test('button shows "Copying..." and both buttons are disabled while copying', async () => {
    const user = userEvent.setup();
    const pending = deferred<UserGroup>();
    vi.mocked(copyUserGroup).mockReturnValue(pending.promise);
    const { onClose } = await renderCopyModal();

    await user.click(screen.getByRole('button', { name: 'Copy' }));

    expect(await screen.findByRole('button', { name: 'Copying...' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Cancel' })).toBeDisabled();

    pending.resolve(buildUserGroup());
    await waitFor(() => expect(onClose).toHaveBeenCalled());
  });

  test('Cancel closes without copying', async () => {
    const user = userEvent.setup();
    const { onClose } = await renderCopyModal();

    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(copyUserGroup).not.toHaveBeenCalled();
  });
});
