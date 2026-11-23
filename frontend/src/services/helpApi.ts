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

import http from '@/lib/api';
import type { HelpPageLinks } from '@/types/help';

const FEEDBACK_URL = 'https://api.xibosignage.com/api/cms/feedback';

export async function getHelpPageLinks(
  page?: string,
  signal?: AbortSignal,
): Promise<HelpPageLinks> {
  const response = await http.get<HelpPageLinks>('/help/page-links', {
    params: { page },
    signal,
  });
  return response.data;
}

function randomId(length = 32): string {
  let out = '';
  while (out.length < length) {
    out += Math.floor(Math.random() * 36).toString(36);
  }
  return out.slice(0, length);
}

// Cross-origin POST to the Xibo feedback service, so this deliberately bypasses
// the /json axios instance and uses fetch.
export async function submitFeedback(formData: FormData, files: File[]): Promise<void> {
  formData.append('id', randomId());
  files.forEach((file) => formData.append('files[]', file));

  let res: Response;
  try {
    res = await fetch(FEEDBACK_URL, { method: 'POST', body: formData });
  } catch {
    throw new Error('request');
  }

  if (res.ok) {
    return;
  }

  let message = '';
  try {
    const data = await res.clone().json();
    message = data?.message ?? '';
  } catch {
    try {
      message = await res.text();
    } catch {
      message = '';
    }
  }

  throw new Error(message || 'request');
}
