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
.*/

import { redirect } from 'react-router-dom';

const API_USER = '/json/user/me';

async function validateToken(): Promise<boolean> {
  try {
    const res = await fetch(API_USER, {
      method: 'GET',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      cache: 'no-store',
    });

    if (res.ok) {
      return true;
    }

    return false;
  } catch (error) {
    console.error('Auth validation check failed:', error);
    return false;
  }
}

export async function requireAuthLoader({ request }: { request: Request }) {
  const isAuthenticated = await validateToken();
  if (!isAuthenticated) {
    const currentUrl = new URL(request.url);
    const returnTo = encodeURIComponent(currentUrl.pathname + currentUrl.search);

    window.location.href = `/login?priorRoute=${returnTo}`;
    return null;
  }

  return null;
}

export async function redirectIfAuthedLoader() {
  const isAuthenticated = await validateToken();
  if (isAuthenticated) {
    throw redirect('/dashboard');
  }
  return null;
}
