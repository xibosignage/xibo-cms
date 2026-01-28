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

import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

// Build URLs that respect Vite's base (e.g. /react/)
function withBase(p: string) {
  return new URL(p, window.location.origin + import.meta.env.BASE_URL).toString();
}

// Guard against HTML responses (404 rewrites)
async function fetchJson(url: string) {
  const res = await fetch(url, { cache: 'no-cache' });
  const text = await res.text();
  if (!res.ok || text.trim().startsWith('<!doctype') || text.trim().startsWith('<html')) {
    throw new Error(`Expected JSON but got HTML/HTTP ${res.status} at ${url}`);
  }
  return JSON.parse(text);
}

// CHANGE THIS to match your generated filenames: 'en', 'en_GB', 'pt_PT', etc...
// or "en" if your script outputs en.json
const DEFAULT_LANG = 'en_GB';

const langUrl = withBase(`locale/langs/${DEFAULT_LANG}.json`);
const translations = await fetchJson(langUrl);

void i18n.use(initReactI18next).init({
  lng: DEFAULT_LANG,
  fallbackLng: DEFAULT_LANG,
  interpolation: { escapeValue: false },
  resources: {
    [DEFAULT_LANG]: { translation: translations },
  },
});

export default i18n;
