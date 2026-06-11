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

import type { FetchCommandsResponse } from '@/services/commandApi';
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

export const mockUser: User = {
  userId: 1,
  userName: 'TestUser',
  userTypeId: 1,
  groupId: 1,
  features: { 'command.view': true },
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
};

export const queryKeys = {
  commandsPage: ['userPref', 'command_page'] as const,
};
