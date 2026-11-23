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

import type { ForgotResponse, LoginMethodsResponse, LoginResponse } from './types';
import { publicPath } from './utils';

function getCsrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="token"]')?.content ?? '';
}

async function postJson<T>(url: string, body: Record<string, string>): Promise<T> {
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-XSRF-TOKEN': getCsrfToken(),
    },
    body: JSON.stringify(body),
  });

  if (!res.ok && res.status !== 401 && res.status !== 429) {
    throw new Error(`Unexpected HTTP ${res.status}`);
  }

  return (await res.json()) as T;
}

export function submitLogin(username: string, password: string): Promise<LoginResponse> {
  return postJson<LoginResponse>(`${publicPath}login`, { username, password });
}

export function fetchLoginMethods(
  identifier: string,
  priorRoute: string,
): Promise<LoginMethodsResponse> {
  return postJson<LoginMethodsResponse>(`${publicPath}login/methods`, { identifier, priorRoute });
}

export function submitTwoFactor(code: string): Promise<LoginResponse> {
  return postJson<LoginResponse>(`${publicPath}tfa`, { code });
}

export function submitRecoveryCode(recoveryCode: string): Promise<LoginResponse> {
  return postJson<LoginResponse>(`${publicPath}tfa`, { recoveryCode });
}

export function submitForgotPassword(username: string): Promise<ForgotResponse> {
  return postJson<ForgotResponse>(`${publicPath}login/forgotten`, { username });
}
