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

import { withAssetBase } from '@/config/publicPath';

// Guard against HTML responses (404 rewrites)
async function fetchJson(url: string, init?: RequestInit) {
  const res = await fetch(url, { cache: 'no-cache', ...init });
  const text = await res.text();
  if (!res.ok || text.trim().startsWith('<!doctype') || text.trim().startsWith('<html')) {
    throw new Error(`Expected JSON but got HTML/HTTP ${res.status} at ${url}`);
  }
  return JSON.parse(text);
}

const FALLBACK_LANG = 'en_GB';

async function resolveLanguage() {
  const apiBase = import.meta.env.VITE_API_BASE_URL || '/json';
  try {
    const me = await fetchJson(`${window.location.origin}${apiBase}/user/me`, {
      credentials: 'include',
      headers: { Accept: 'application/json' },
    });
    return me?.settings?.translate?.locale || FALLBACK_LANG;
  } catch {
    return FALLBACK_LANG;
  }
}

async function loadTranslations(lang: string) {
  try {
    return { lang, translations: await fetchJson(withAssetBase(`locale/langs/${lang}.json`)) };
  } catch {
    if (lang === FALLBACK_LANG) {
      throw new Error(`Missing translations for fallback language ${FALLBACK_LANG}`);
    }
    return {
      lang: FALLBACK_LANG,
      translations: await fetchJson(withAssetBase(`locale/langs/${FALLBACK_LANG}.json`)),
    };
  }
}

const { lang, translations } = await loadTranslations(await resolveLanguage());

void i18n.use(initReactI18next).init({
  lng: lang,
  fallbackLng: FALLBACK_LANG,
  interpolation: { escapeValue: false },
  resources: {
    [lang]: { translation: translations },
  },
});

export async function changeAppLanguage(target: string) {
  const lang = target || FALLBACK_LANG;
  if (!i18n.hasResourceBundle(lang, 'translation')) {
    const loaded = await loadTranslations(lang);
    i18n.addResourceBundle(loaded.lang, 'translation', loaded.translations, true, true);
    await i18n.changeLanguage(loaded.lang);
    return loaded.lang;
  }
  await i18n.changeLanguage(lang);
  return lang;
}

export async function reloadAppLanguage() {
  return changeAppLanguage(await resolveLanguage());
}

export default i18n;
