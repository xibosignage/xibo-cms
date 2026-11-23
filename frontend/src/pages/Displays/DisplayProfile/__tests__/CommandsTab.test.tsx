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

import { render, screen } from '@testing-library/react';
import { describe, expect, test, vi } from 'vitest';

import CommandsTab, { buildCommandDrafts } from '../components/CommandsTab';

import type { DisplayProfileCommand } from '@/types/displayProfile';

const command = (overrides: Partial<DisplayProfileCommand>) =>
  ({
    commandId: 1,
    command: 'Reboot',
    code: 'reboot',
    description: null,
    commandString: null,
    validationString: null,
    createAlertOn: undefined,
    // No override on this profile, so the profile's own field stays empty and
    // can't be mistaken for the default.
    commandStringDisplayProfile: null,
    validationStringDisplayProfile: null,
    createAlertOnDisplayProfile: null,
    ...overrides,
  }) as DisplayProfileCommand;

const renderTab = (commands: DisplayProfileCommand[]) => {
  const { drafts } = buildCommandDrafts(commands);
  render(
    <CommandsTab
      commandDrafts={drafts}
      onCommandDraftsChange={vi.fn()}
      // Panels without an override start collapsed.
      initialExpandedIds={new Set(commands.map((c) => c.commandId))}
    />,
  );
};

// Regression cover for xibosignage/xibo#3874: the panel said "This Command has
// a default Command String." but never showed it, so an admin overriding it
// couldn't see what they were overriding.
describe('Display Profile Commands tab - default strings', () => {
  test('shows the default command string of a command that has one', () => {
    renderTab([command({ commandString: 'reboot -f' })]);

    expect(screen.getByText('This Command has a default Command String.')).toBeInTheDocument();
    expect(screen.getByText('reboot -f')).toBeInTheDocument();
  });

  test('shows the default validation string of a command that has one', () => {
    renderTab([command({ commandString: 'reboot -f', validationString: 'rebooted' })]);

    expect(screen.getByText('rebooted')).toBeInTheDocument();
  });

  test('says nothing about a default for a command without one', () => {
    renderTab([command({ commandString: null })]);

    expect(
      screen.queryByText('This Command has a default Command String.'),
    ).not.toBeInTheDocument();
  });
});
