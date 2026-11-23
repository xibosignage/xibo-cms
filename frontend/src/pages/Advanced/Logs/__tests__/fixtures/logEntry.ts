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

import type { LogEntry } from '@/types/log';

// Log entry builders shared by the Logs tests. Kept free of vi.mock calls so a test that
// declares its own module mocks can import them without pulling in Setup.tsx's mocks.

export const createMockLogEntry = (overrides?: Partial<LogEntry>): LogEntry => ({
  logId: 1,
  runNo: 'run-001',
  logDate: '2024-01-01 10:00:00',
  channel: 'WEB',
  page: '/layout',
  function: 'GET',
  message: 'Test log message',
  displayId: 0,
  type: 'INFO',
  display: '',
  sessionHistoryId: 1,
  userId: 1,
  ...overrides,
});

export const mockLogsList: LogEntry[] = [
  createMockLogEntry({ logId: 1, type: 'INFO', message: 'Info log message', channel: 'WEB' }),
  createMockLogEntry({ logId: 2, type: 'ERROR', message: 'Error log message', function: 'POST' }),
  createMockLogEntry({
    logId: 3,
    type: 'DEBUG',
    message: 'Debug log message',
    display: 'Screen 1',
  }),
];
