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
