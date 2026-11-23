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

import type { Session } from '@/types/session';

// Session builders shared by the Sessions tests. Kept free of vi.mock calls so a test that
// declares its own module mocks can import them without pulling in Setup.tsx's mocks.

export const createMockSession = (overrides?: Partial<Session>): Session => ({
  // Fixed, and clear of the 1-3 used by mockSessionsList
  userId: 100,
  userName: 'john_doe',
  remoteAddress: '192.168.1.1',
  userAgent: 'Chrome 120',
  isExpired: false,
  isCurrentSession: false,
  lastAccessed: '2024-01-01T10:00:00Z',
  expiresAt: '2024-01-02T10:00:00Z',
  ...overrides,
});

export const mockSessionsList: Session[] = [
  createMockSession({ userId: 1, userName: 'alice_admin', expiresAt: '2024-01-02T10:00:01Z' }),
  createMockSession({
    userId: 2,
    userName: 'bob_user',
    isExpired: true,
    expiresAt: '2024-01-02T10:00:02Z',
  }),
  createMockSession({ userId: 3, userName: 'charlie_guest', expiresAt: '2024-01-02T10:00:03Z' }),
];
