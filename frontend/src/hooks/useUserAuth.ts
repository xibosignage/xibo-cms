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

import { useState } from 'react';

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
    setUser(null);
    globalLogout();
  };

  const updateUser = (data: Partial<User>) => {
    if (!user) return;
    setUser({ ...user, ...data });
  };

  return { user, isAuthenticated: !!user, logout, updateUser };
}
