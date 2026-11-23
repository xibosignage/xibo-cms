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

import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockPopulatedUserGroup } from '../../../fixtures/userGroup';
import { renderAddEditUserGroupModal } from '../../helpers/renderAddEditUserGroupModal';

import { knownFailure } from '@/testUtils/knownFailure';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/userGroupApi');
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Helpers
// =============================================================================

const field = {
  name: () => screen.getByRole('textbox', { name: /^name$/i }),
  description: () => screen.getByRole('textbox', { name: /^description/i }),
  quota: () => screen.getByRole('spinbutton', { name: /library quota/i }),
  onboarding: () => screen.getByRole('checkbox', { name: /show when onboarding a new user/i }),
  homepage: () => screen.getByRole('combobox', { name: /default homepage/i }),
};

const renderEdit = async () => {
  const utils = await renderAddEditUserGroupModal({
    mode: 'edit',
    userGroup: mockPopulatedUserGroup,
  });
  await userEvent.setup().click(screen.getByRole('tab', { name: 'General' }));
  return utils;
};

// =============================================================================
// Tests
// =============================================================================

describe('AddEditUserGroupModal - General tab (add mode)', () => {
  beforeEach(() => vi.clearAllMocks());

  test('every field starts empty and the quota is unlimited', async () => {
    const user = userEvent.setup();
    await renderAddEditUserGroupModal();
    await user.click(screen.getByRole('tab', { name: 'General' }));

    expect(field.name()).toHaveValue('');
    expect(field.description()).toHaveValue('');
    expect(field.quota()).toHaveValue(0);
    expect(field.onboarding()).not.toBeChecked();
    expect(field.homepage()).toHaveTextContent('Select a homepage');
  });

  test('the homepage dropdown lists the four dashboards', async () => {
    const user = userEvent.setup();
    await renderAddEditUserGroupModal();

    await user.click(field.homepage());

    const listbox = await screen.findByRole('listbox');
    expect(
      within(listbox)
        .getAllByRole('option')
        .map((o) => o.textContent),
    ).toEqual([
      'Icon Dashboard',
      'Status Dashboard',
      'Media Manager Dashboard',
      'Playlist Dashboard',
    ]);
  });
});

describe('AddEditUserGroupModal - General tab (edit mode)', () => {
  beforeEach(() => vi.clearAllMocks());

  test('Name, Description, onboarding and homepage are pre-populated', async () => {
    await renderEdit();

    expect
      .soft(screen.queryByRole('textbox', { name: /^name$/i }))
      .toHaveValue(mockPopulatedUserGroup.group);
    expect
      .soft(screen.queryByRole('textbox', { name: /^description/i }))
      .toHaveValue(mockPopulatedUserGroup.description);
    expect
      .soft(screen.queryByRole('checkbox', { name: /show when onboarding a new user/i }))
      .toBeChecked();
    expect
      .soft(screen.queryByRole('combobox', { name: /default homepage/i }))
      .toHaveTextContent('Status Dashboard');
  });

  test('Name is editable', async () => {
    const user = userEvent.setup();
    await renderEdit();
    await user.clear(field.name());
    await user.type(field.name(), 'Ops Team');
    expect(field.name()).toHaveValue('Ops Team');
  });

  test('Description is editable', async () => {
    const user = userEvent.setup();
    await renderEdit();
    await user.clear(field.description());
    await user.type(field.description(), 'New description');
    expect(field.description()).toHaveValue('New description');
  });

  // Known issue: xibosignage/xibo#4125
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4125 is fixed', () => {
    // The unit is picked on mount, before the edit values arrive, so 1 GiB shows as 1048576 KiB.
    knownFailure(
      'Library Quota is pre-populated in the largest whole unit',
      async () => {
        await renderEdit();
        // Control: the quota field is there.
        return field.quota();
      },
      (quota) => expect(quota).toHaveValue(1),
    );
  });

  test('Library Quota is editable', async () => {
    const user = userEvent.setup();
    await renderEdit();
    await user.clear(field.quota());
    await user.type(field.quota(), '5');
    expect(field.quota()).toHaveValue(5);
  });

  test('"Show when onboarding" can be unticked', async () => {
    const user = userEvent.setup();
    await renderEdit();
    await user.click(field.onboarding());
    expect(field.onboarding()).not.toBeChecked();
  });

  test('Default Homepage can be changed and cleared', async () => {
    const user = userEvent.setup();
    await renderEdit();

    await user.click(field.homepage());
    await user.click(await screen.findByRole('option', { name: 'Icon Dashboard' }));
    expect(field.homepage()).toHaveTextContent('Icon Dashboard');

    await user.click(field.homepage());
    await user.click(await screen.findByRole('button', { name: 'Select a homepage' }));
    expect(field.homepage()).toHaveTextContent('Select a homepage');
  });
});
