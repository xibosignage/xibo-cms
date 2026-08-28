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

import { mockSuperAdmin, mockGroupAdmin, mockUser } from '../../../fixtures/user';
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
// Tests
// =============================================================================

describe('AddEditUserModal - General tab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setupAddEditUserModalMocks();
  });

  test('add mode: Username, Email, and Password fields are empty', async () => {
    renderAddEditUserModal({ mode: 'add' });
    await screen.findByRole('textbox', { name: /^username$/i });

    expect(screen.getByRole('textbox', { name: /^username$/i })).toHaveValue('');
    expect(screen.getByRole('textbox', { name: /^email/i })).toHaveValue('');
    expect(screen.getByLabelText(/^password$/i)).toHaveValue('');
  });

  test('add mode: "Initial User Group" dropdown is present', async () => {
    renderAddEditUserModal({ mode: 'add' });

    await screen.findByRole('combobox', { name: /initial user group/i });
  });

  test('edit mode: "Initial User Group" dropdown is not present', async () => {
    renderAddEditUserModal({ mode: 'edit', user: mockUser });
    await screen.findByRole('textbox', { name: /^username$/i });

    expect(screen.queryByRole('combobox', { name: /initial user group/i })).not.toBeInTheDocument();
  });

  test('edit mode: Username and Email pre-fill from the user', async () => {
    renderAddEditUserModal({
      mode: 'edit',
      user: { ...mockUser, userName: 'jbloggs', email: 'j@example.com' },
    });

    expect(await screen.findByRole('textbox', { name: /^username$/i })).toHaveValue('jbloggs');
    expect(screen.getByRole('textbox', { name: /^email/i })).toHaveValue('j@example.com');
  });

  test('edit mode: "Retype New Password" field is present', async () => {
    renderAddEditUserModal({ mode: 'edit', user: mockUser });

    await screen.findByLabelText(/retype new password/i);
  });

  test("edit mode: Retired checkbox reflects the user's retired value", async () => {
    renderAddEditUserModal({ mode: 'edit', user: { ...mockUser, retired: 1 } });

    expect(await screen.findByRole('checkbox', { name: 'Retired' })).toBeChecked();
  });

  test('User Type dropdown is shown for a super admin', async () => {
    renderAddEditUserModal({ mode: 'add', currentUser: mockSuperAdmin });

    await screen.findByRole('combobox', { name: /^user type$/i });
  });

  test('User Type dropdown is hidden for a non-superAdmin', async () => {
    renderAddEditUserModal({ mode: 'add', currentUser: mockGroupAdmin });
    await screen.findByRole('textbox', { name: /^username$/i });

    expect(screen.queryByRole('combobox', { name: /^user type$/i })).not.toBeInTheDocument();
  });

  test('the eye icon toggles the password field between hidden and visible text', async () => {
    const user = userEvent.setup();
    renderAddEditUserModal({ mode: 'add' });
    await screen.findByRole('textbox', { name: /^username$/i });

    const passwordInput = screen.getByLabelText(/^password$/i);
    expect(passwordInput).toHaveAttribute('type', 'password');

    await user.click(screen.getByRole('button', { name: /show password/i }));

    expect(passwordInput).toHaveAttribute('type', 'text');
  });

  test("the Library Quota field reflects the draft's current quota value", async () => {
    renderAddEditUserModal({ mode: 'edit', user: { ...mockUser, libraryQuota: 1024 } });

    // The unit dropdown is fixed at 'KiB' on mount (derived from the initial
    // draft value, 0) and does not re-derive when the value prop changes
    // later, so a 1024 KiB quota displays as "1024", not "1" MiB.
    const quotaInput = await screen.findByLabelText('Library Quota');
    expect(quotaInput).toHaveValue(1024);
  });
});
