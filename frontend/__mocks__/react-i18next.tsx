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

import { createElement } from 'react';
import type { ReactNode } from 'react';

export const useTranslation = () => ({
  t: (key: string, opts?: Record<string, unknown>) => {
    if (!opts) {
      return key;
    }
    return key.replace(/\{\{(\w+)\}\}/g, (_, k) => String(opts[k] ?? ''));
  },
  i18n: { changeLanguage: () => Promise.resolve() },
});

export const Trans = ({
  i18nKey = '',
  values = {},
  children,
}: {
  i18nKey?: string;
  values?: Record<string, unknown>;
  components?: Record<string, unknown>;
  children?: ReactNode;
}) => {
  if (children !== undefined) {
    return children;
  }
  let s = String(i18nKey ?? '');
  for (const [k, v] of Object.entries(values)) {
    s = s.replace(new RegExp(`{{${k}}}`, 'g'), String(v ?? ''));
  }
  return s.split(/(<\w+>.*?<\/\w+>)/g).map((p, i) => {
    const m = p.match(/^<(\w+)>(.*?)<\/\1>$/);
    if (!m) {
      return p || null;
    }
    return createElement(m[1], { key: i }, m[2]);
  });
};
