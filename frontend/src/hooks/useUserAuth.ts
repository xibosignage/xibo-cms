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

import { useState } from 'react';

import { withPublicPath } from '@/config/publicPath';
import { logout as globalLogout } from '@/lib/logout';
import type { User } from '@/types/user';

export interface UserAuthType {
  user: User | null;
  isAuthenticated: boolean;
  logout: () => void;
  updateUser: (data: Partial<User>) => void;
}

export function useUserAuth(initialUser: User | null): UserAuthType {
  const [user, setUser] = useState<User | null>(initialUser);

  const logout = () => {
    // When SAML is active, /saml/logout performs the correct flow (real IdP
    // Single Logout when supported, or falls back to the local logout otherwise).
    const redirectUrl = user?.samlEnabled ? withPublicPath('saml/logout') : undefined;
    globalLogout(redirectUrl);
  };

  const updateUser = (data: Partial<User>) => {
    if (!user) return;
    setUser({ ...user, ...data });
  };

  return { user, isAuthenticated: !!user, logout, updateUser };
}
