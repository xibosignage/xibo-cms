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

import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

interface CodeTabProps {
  value: string;
  onChange: (value: string) => void;
  language: 'twig' | 'handlebars' | 'css' | 'html' | 'javascript';
  label: string;
}

export default function CodeTab({ value, onChange, language, label }: CodeTabProps) {
  const { t } = useTranslation();
  const [copied, setCopied] = useState(false);

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(value);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch (err) {
      console.warn('Clipboard copy failed: ', err);
    }
  };

  return (
    <div className="flex flex-col gap-2 h-full">
      <p className="text-xs text-gray-500 font-bold uppercase">{t(label)}</p>
      <div className="relative flex-1">
        <textarea
          value={value}
          onChange={(e) => onChange(e.target.value)}
          data-code-language={language}
          spellCheck={false}
          className="w-full min-h-[60vh] p-3 pr-20 font-mono text-sm border border-gray-100 rounded-lg resize-y focus:outline-none focus:ring-2 focus:ring-xibo-blue-500 focus:border-transparent"
        />
        <button
          type="button"
          onClick={handleCopy}
          className="absolute top-2 right-2 flex items-center gap-1 text-xs text-gray-400 hover:text-gray-700 hover:bg-gray-100 hover:cursor-pointer transition-colors px-2 py-1 rounded"
        >
          {copied ? (
            <>
              <Check size={12} className="text-green-600 shrink-0" />
              <span className="text-green-600">{t('Copied')}</span>
            </>
          ) : (
            <>
              <Copy size={12} className="shrink-0" />
              <span>{t('Copy')}</span>
            </>
          )}
        </button>
      </div>
    </div>
  );
}
