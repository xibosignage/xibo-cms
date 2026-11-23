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

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildUserGroup, mockPopulatedUserGroup } from '../../../fixtures/userGroup';
import { renderAddEditUserGroupModal } from '../../helpers/renderAddEditUserGroupModal';

import { createUserGroup } from '@/services/userGroupApi';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/userGroupApi');
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Helpers
// =============================================================================

const SWITCHES = [
  'System Notifications',
  'Display Notifications',
  'DataSet Notifications',
  'Layout Notifications',
  'Library Notifications',
  'Report Notifications',
  'Schedule Notifications',
  'Custom Notifications',
] as const;

// The payload field each switch writes.
const FIELD_FOR = {
  'System Notifications': 'isSystemNotification',
  'Display Notifications': 'isDisplayNotification',
  'DataSet Notifications': 'isDataSetNotification',
  'Layout Notifications': 'isLayoutNotification',
  'Library Notifications': 'isLibraryNotification',
  'Report Notifications': 'isReportNotification',
  'Schedule Notifications': 'isScheduleNotification',
  'Custom Notifications': 'isCustomNotification',
} as const;

// mockPopulatedUserGroup has System, Display and Schedule turned on.
const ON_IN_FIXTURE = new Set<string>([
  'System Notifications',
  'Display Notifications',
  'Schedule Notifications',
]);

const openNotificationsTab = async (opts: Parameters<typeof renderAddEditUserGroupModal>[0]) => {
  await renderAddEditUserGroupModal(opts);
  await userEvent.setup().click(screen.getByRole('tab', { name: 'Notifications' }));
  return screen.getByRole('tabpanel');
};

// =============================================================================
// Tests
// =============================================================================

describe('AddEditUserGroupModal - Notifications tab', () => {
  beforeEach(() => vi.clearAllMocks());

  test('every switch starts off in Add mode', async () => {
    await openNotificationsTab({});

    for (const name of SWITCHES) {
      expect.soft(screen.queryByRole('switch', { name }), name).not.toBeChecked();
    }
  });

  test('every switch is pre-populated in Edit mode', async () => {
    await openNotificationsTab({ mode: 'edit', userGroup: mockPopulatedUserGroup });

    for (const name of SWITCHES) {
      const sw = screen.queryByRole('switch', { name });
      if (ON_IN_FIXTURE.has(name)) expect.soft(sw, name).toBeChecked();
      else expect.soft(sw, name).not.toBeChecked();
    }
  });

  test.each(SWITCHES)('%s toggles when clicked', async (name) => {
    const user = userEvent.setup();
    await openNotificationsTab({});

    await user.click(screen.getByRole('switch', { name }));

    expect(screen.getByRole('switch', { name })).toBeChecked();
  });

  test.each(SWITCHES)('turning on %s saves only that notification', async (name) => {
    const user = userEvent.setup();
    vi.mocked(createUserGroup).mockResolvedValue(buildUserGroup());
    await openNotificationsTab({});

    await user.click(screen.getByRole('switch', { name }));
    await user.click(screen.getByRole('tab', { name: 'General' }));
    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'QA');
    await user.click(screen.getByRole('button', { name: 'Create Group' }));

    await waitFor(() => expect(createUserGroup).toHaveBeenCalledTimes(1));
    const sent = vi.mocked(createUserGroup).mock.calls[0]![0];
    for (const [label, field] of Object.entries(FIELD_FOR)) {
      expect.soft(sent[field], field).toBe(label === name ? 1 : 0);
    }
  });
});
