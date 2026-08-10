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

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockUser } from '../../../fixtures/user';
import { setupAddEditUserModalMocks } from '../../../mocks/addEditUserModal';
import { renderAddEditUserModal } from '../helpers/renderAddEditUserModal';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/userApi');

vi.mock('@/services/userGroupApi');

vi.mock('@/services/folderApi');

vi.mock('@/services/permissionsApi');

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Helpers
// =============================================================================

const openReferencesTab = async (user: ReturnType<typeof userEvent.setup>) => {
  await screen.findByRole('textbox', { name: /^username$/i });
  await user.click(screen.getByRole('tab', { name: /^references$/i }));
  return screen.findByRole('textbox', { name: /^phone number/i });
};

// =============================================================================
// Tests
// =============================================================================

describe('AddEditUserModal - References tab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setupAddEditUserModalMocks();
  });

  test('edit mode pre-fills Phone Number and Reference 1-5 from the user', async () => {
    const user = userEvent.setup();
    renderAddEditUserModal({
      mode: 'edit',
      user: {
        ...mockUser,
        phone: '0123456789',
        ref1: 'a',
        ref2: 'b',
        ref3: 'c',
        ref4: 'd',
        ref5: 'e',
      },
    });
    await openReferencesTab(user);

    expect(screen.getByRole('textbox', { name: /^phone number/i })).toHaveValue('0123456789');
    expect(screen.getByRole('textbox', { name: /^reference 1/i })).toHaveValue('a');
    expect(screen.getByRole('textbox', { name: /^reference 2/i })).toHaveValue('b');
    expect(screen.getByRole('textbox', { name: /^reference 3/i })).toHaveValue('c');
    expect(screen.getByRole('textbox', { name: /^reference 4/i })).toHaveValue('d');
    expect(screen.getByRole('textbox', { name: /^reference 5/i })).toHaveValue('e');
  });

  test('add mode renders all reference fields empty', async () => {
    const user = userEvent.setup();
    renderAddEditUserModal({ mode: 'add' });
    await openReferencesTab(user);

    expect(screen.getByRole('textbox', { name: /^phone number/i })).toHaveValue('');
    expect(screen.getByRole('textbox', { name: /^reference 1/i })).toHaveValue('');
    expect(screen.getByRole('textbox', { name: /^reference 5/i })).toHaveValue('');
  });

  test('typing into Reference 1 updates only that field', async () => {
    const user = userEvent.setup();
    renderAddEditUserModal({ mode: 'add' });
    await openReferencesTab(user);

    const ref1Input = screen.getByRole('textbox', { name: /^reference 1/i });
    await user.type(ref1Input, 'new-value');

    expect(ref1Input).toHaveValue('new-value');
    expect(screen.getByRole('textbox', { name: /^reference 2/i })).toHaveValue('');
    expect(screen.getByRole('textbox', { name: /^phone number/i })).toHaveValue('');
  });
});
