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

import { vi } from 'vitest';

import { mockUser } from '../fixtures/userMenu';

import type { User } from '@/types/user';

// Kept free of component imports so vi.mock factories can load it without cycles.

// Shared UserContext value; tests swap the user with setMenuUser().
export const mockUserContext = {
  user: mockUser as User | null,
  logout: vi.fn(),
  updateUser: vi.fn(),
};

export const setMenuUser = (user: User | null) => {
  mockUserContext.user = user;
};

// Server clock; returns null (no badge) unless a test sets a time.
export const mockUseClock = vi.fn<() => string | null>(() => null);
