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

import type { FetchCommandsResponse } from '@/services/commandApi';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { Command } from '@/types/command';
import type { User } from '@/types/user';

export const buildCommand = (overrides: Partial<Command> = {}): Command => ({
  commandId: 1,
  command: 'Test Command',
  code: 'TEST_CMD',
  description: null,
  userId: 1,
  commandString: null,
  validationString: null,
  availableOn: null,
  createAlertOn: 'never',
  groupsWithPermissions: null,
  userPermissions: { view: 1, edit: 1, delete: 1, modifyPermissions: 1 },
  ...overrides,
});

export const mockCommand = buildCommand();

export const SINGLE_COMMAND: FetchCommandsResponse = {
  rows: [mockCommand],
  totalCount: 1,
};

export const EMPTY_COMMAND_TABLE: FetchCommandsResponse = {
  rows: [],
  totalCount: 0,
};

// Two-row fixture for bulk-action / delete tests.
export const MULTIPLE_COMMANDS: FetchCommandsResponse = {
  rows: [
    buildCommand({ commandId: 1, command: 'Command Alpha', code: 'CMD_ALPHA' }),
    buildCommand({ commandId: 2, command: 'Command Beta', code: 'CMD_BETA' }),
  ],
  totalCount: 2,
};

// Sourced from the shared PERSONAS registry rather than hand-typed. Super
// Admin, so every hasFeature() check passes via its bypass regardless of
// the features map.
export const mockUser: User = buildCurrentUser(PERSONAS.superAdmin, {
  userId: 1,
  userName: 'TestUser',
  groupId: 1,
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
});

export const queryKeys = {
  commandsPage: ['userPref', 'command_page'] as const,
};
