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

import { redirect } from 'react-router-dom';

import http from '@/lib/api';
import type { User } from '@/types/user';

async function getUserSession(): Promise<User | null> {
  try {
    const res = await http.get('/user/me');
    return res.data;
  } catch {
    return null;
  }
}

export async function requireAuthLoader({ request }: { request: Request }) {
  const user = await getUserSession();
  if (!user) {
    const currentUrl = new URL(request.url);
    const returnTo = encodeURIComponent(currentUrl.pathname + currentUrl.search);

    throw redirect(`/login?priorRoute=${returnTo}`);
  }

  return { user };
}

export async function redirectIfAuthedLoader() {
  const user = await getUserSession();

  if (user) {
    throw redirect('/');
  }

  return null;
}
