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

import axios, { type AxiosError } from 'axios';

import { triggerSessionExpired } from './auth-events';

import { withPublicPath } from '@/config/publicPath';

// The JSON API is served by the CMS under its install root, so prefix with publicPath
// (rootUri) — '/json' at a root install, '/cms/json' under an alias.
const BASE_URL = withPublicPath(import.meta.env.VITE_API_BASE_URL || 'json');

const http = axios.create({
  baseURL: BASE_URL,
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Shared 401/403 handling, also attached to the bare `axios` default instance below. A few
// service files (e.g. reporting's `/report/data/{name}`, `notificationApi`'s file upload) call
// `axios`/`axiosLib` directly instead of `http` — they hit root web routes outside `/json`, or
// need to skip `http`'s default JSON Content-Type — but still need the same session/suspension
// handling, so this is centralized here rather than touched per call site.
// Excludes any hypothetical absolute-URL request (e.g. a future dependency calling a
// third-party API through the bare `axios` export below) from this app's own session/
// suspension handling — every first-party call in this codebase uses a relative URL.
function isFirstPartyRequest(err: AxiosError): boolean {
  const url = err.config?.url ?? '';
  return !/^https?:\/\//i.test(url);
}

function handleAuthErrors(err: AxiosError<{ message?: string }>) {
  if (!isFirstPartyRequest(err)) {
    return Promise.reject(err);
  }
  if (err.response?.status === 401) {
    console.warn('Unauthorized access. Redirecting to login...');
    triggerSessionExpired();
  } else if (err.response?.status === 403 && err.response?.data?.message === 'Instance Suspended') {
    // The instance was suspended after this page loaded. A hard reload re-requests the
    // current URL as a full page load, which server-side already renders the correct
    // "Instance Suspended" screen (lib/Middleware/Handlers.php) — reusing that render
    // path instead of building a second, SPA-side copy of the same screen.
    window.location.reload();
  }
  return Promise.reject(err);
}

http.interceptors.response.use((res) => res, handleAuthErrors);
axios.interceptors.response.use((res) => res, handleAuthErrors);

export default http;
