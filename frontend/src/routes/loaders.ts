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

// A real authenticated user always has a positive userId and a userName.
// Right after login, on a cold backend (first login of the day, after a long
// idle, or after a container restart), /user/me can transiently come back as
// either a 401 (session row not committed yet) or a 200 with a degenerate,
// under-populated user (empty features / no userName). Trusting the latter is
// what renders the broken half-authenticated shell (avatar "U", no nav).
function isValidUser(data: unknown): data is User {
  return (
    typeof data === 'object' &&
    data !== null &&
    typeof (data as User).userId === 'number' &&
    (data as User).userId > 0 &&
    !!(data as User).userName
  );
}

const sleep = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

// Fetch the current user, retrying briefly to ride out the cold-backend race
// described above. Since "a reload fixes it", a couple of short retries let the
// session commit / feature registry warm up before we give up and bounce to the
// login page — turning the broken shell into either a correct load or a clean
// redirect. A healthy session returns on the first attempt with no delay.
async function getUserSession(): Promise<User | null> {
  const maxAttempts = 3;
  const retryDelayMs = 250;

  for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
    try {
      const res = await http.get('/user/me');
      if (isValidUser(res.data)) {
        return res.data;
      }
    } catch {
      // 401 / network error — fall through to retry, then null.
    }

    if (attempt < maxAttempts) {
      await sleep(retryDelayMs);
    }
  }

  return null;
}

export async function requireAuthLoader({ request }: { request: Request }) {
  const user = await getUserSession();
  if (!user) {
    const currentUrl = new URL(request.url);
    const returnTo = encodeURIComponent(currentUrl.pathname + currentUrl.search);

    // redirect() targets are resolved against the router's basename (getRouterBasename())
    // automatically, so these must stay basename-relative — do not prefix with
    // withPublicPath()/publicPath, or the install root ends up applied twice.
    throw redirect(`/login?priorRoute=${returnTo}`);
  }

  if (user.isPasswordChangeRequired === 1) {
    throw redirect('/user/force-change-password');
  }

  return { user };
}

export async function requireAuthOnlyLoader() {
  const user = await getUserSession();
  if (!user) {
    throw redirect('/login');
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
