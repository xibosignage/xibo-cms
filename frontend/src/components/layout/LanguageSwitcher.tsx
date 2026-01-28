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

import { useEffect, useState } from 'react';

import i18n from '@/lib/i18n';

function withBase(p: string) {
  return new URL(p, window.location.origin + import.meta.env.BASE_URL).toString();
}

// Map for known labels
const PRETTY: Record<string, string> = {
  en: 'English',
  en_GB: 'English (United Kingdom)',
  en_US: 'English (United States)',
  pt: 'Português',
  pt_PT: 'Português (Portugal)',
  pt_BR: 'Português (Brasil)',
  es: 'Español',
  es_ES: 'Español (España)',
  fr: 'Français',
  de: 'Deutsch',
};

// Try to generate a friendly name
function labelFor(code: string): string {
  if (PRETTY[code]) return PRETTY[code];

  const norm = code.replace('_', '-');
  const [langPartRaw, regionPartRaw] = norm.split('-');
  const langPart: string = langPartRaw || code;
  const regionPart: string | undefined = regionPartRaw || undefined;

  try {
    const dnLang = new Intl.DisplayNames([norm], { type: 'language' });
    const base = dnLang.of(langPart) ?? langPart;

    if (regionPart) {
      const dnReg = new Intl.DisplayNames([norm], { type: 'region' });
      const region = dnReg.of(regionPart);
      if (region) return `${base} (${region})`;
    }

    return base;
  } catch {
    return code;
  }
}

export default function LanguageSwitcher() {
  const [codes, setCodes] = useState<string[]>([]);
  const [current, setCurrent] = useState<string>(i18n.language || 'en_GB');

  useEffect(() => {
    (async () => {
      try {
        const url = withBase('locale/available.json');
        const list: string[] = await fetch(url).then((r) => r.json());
        setCodes(list);
        // Initialise from storage if available and in list
        const saved = localStorage.getItem('lang');
        if (saved && list.includes(saved)) setCurrent(saved);
      } catch (e) {
        console.error('Failed to load available languages:', e);
      }
    })();
  }, []);

  const changeLang = async (code: string) => {
    if (!i18n.hasResourceBundle(code, 'translation')) {
      const url = withBase(`locale/langs/${code}.json`);
      const res = await fetch(url);
      const msgs = await res.json();
      i18n.addResourceBundle(code, 'translation', msgs, true, true);
    }
    await i18n.changeLanguage(code);
    setCurrent(code);
    localStorage.setItem('lang', code);
  };

  if (!codes.length) return null;

  return (
    <select
      value={current}
      onChange={(e) => changeLang(e.target.value)}
      className="py-3 px-4 pe-9 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
      aria-label="Language"
    >
      {codes.map((code) => (
        <option key={code} value={code}>
          {labelFor(code)}
        </option>
      ))}
    </select>
  );
}
