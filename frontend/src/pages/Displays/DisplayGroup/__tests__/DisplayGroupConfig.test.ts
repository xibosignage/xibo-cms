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

import type { TFunction } from 'i18next';
import { describe, expect, test, vi } from 'vitest';

import { getDisplayGroupItemActions } from '../DisplayGroupConfig';

import { buildDisplayGroup } from './fixtures/displayGroup';

import type { DisplayGroup } from '@/types/displayGroup';

const t = ((s: string) => s) as unknown as TFunction;

/** The row actions for `group`, as a user who may modify display groups. */
const actionsFor = (group: DisplayGroup) => {
  const props = {
    t,
    // Defaults to false, which hides Edit and Members alike.
    canModify: true,
    onDelete: vi.fn(),
    openEditModal: vi.fn(),
    openCopyModal: vi.fn(),
    openMembersModal: vi.fn(),
    openAssignFilesModal: vi.fn(),
    openAssignLayoutsModal: vi.fn(),
    openShareModal: vi.fn(),
    openSendCommandModal: vi.fn(),
    collectNow: vi.fn(),
    triggerWebhook: vi.fn(),
    formatDateTime: vi.fn(),
  };
  return { props, actions: getDisplayGroupItemActions(props)(group) };
};

const quickActionLabels = (group: DisplayGroup) =>
  actionsFor(group)
    .actions.filter((action) => !action.isSeparator && action.isQuickAction)
    .map((action) => action.label);

// Regression cover for xibosignage/xibo#3950: for a static group, the job
// people come to the row for is managing its members, so that is the button on
// the row. A dynamic group's members come from its criteria, so its button
// stays Edit.
describe('Display Groups row actions - the quick action', () => {
  test('a static group offers Members as its quick action', () => {
    expect(quickActionLabels(buildDisplayGroup({ isDynamic: 0 }))).toEqual(['Members']);
  });

  test('the Members quick action opens the members of that group', () => {
    const group = buildDisplayGroup({ isDynamic: 0 });
    const { props, actions } = actionsFor(group);

    const members = actions.find((action) => !action.isSeparator && action.isQuickAction);
    members?.onClick?.();

    expect(props.openMembersModal).toHaveBeenCalledWith(group);
  });

  test('a dynamic group keeps Edit as its quick action', () => {
    expect(quickActionLabels(buildDisplayGroup({ isDynamic: 1 }))).toEqual(['Edit']);
  });

  // Moving Members to the row must not take Edit away from a static group.
  test('a static group can still be edited from its More actions menu', () => {
    const labels = actionsFor(buildDisplayGroup({ isDynamic: 0 }))
      .actions.filter((action) => !action.isSeparator && !action.isQuickAction)
      .map((action) => action.label);

    expect(labels).toContain('Edit');
  });
});
