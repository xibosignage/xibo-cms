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
.*/

import { X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

interface TagInputProps {
  value: string[];
  onChange: (tags: string[]) => void;
}

function TagInput({ value, onChange }: TagInputProps) {
  const { t } = useTranslation();
  const [input, setInput] = useState('');

  const addTag = (raw: string) => {
    const tag = raw.trim();
    if (!tag || value.includes(tag)) return;

    onChange([...value, tag]);
    setInput('');
  };

  const removeTag = (tag: string) => {
    onChange(value.filter((t) => t !== tag));
  };

  return (
    <div className="flex flex-col gap-1 relative">
      <label className="text-xs font-semibold text-gray-500 leading-5">{t('Tags')}</label>

      <div className="border border-gray-200 rounded-lg p-2 flex flex-wrap gap-2 items-center">
        {value.map((tag) => (
          <span
            key={tag}
            className="flex items-center gap-1 px-2 py-1 text-sm font-semibold border text-xibo-blue-600 border-xibo-blue-400 rounded-full"
          >
            {tag}
            <button
              type="button"
              onClick={() => removeTag(tag)}
              className="text-blue-600 w-3 rounded-full h-3 center bg-blue-200 hover:text-gray-600"
            >
              <X size={8} />
            </button>
          </span>
        ))}
        <input
          className="flex-1 min-w-[120px] text-sm p-1 border-none outline-none focus:outline-none focus:ring-0 focus:shadow-non"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter' || e.key === ',') {
              e.preventDefault();
              addTag(input);
            }
          }}
          placeholder={value.length === 0 ? t('Add tags') : ''}
        />
      </div>

      <span className="text-xs text-gray-400">{t('Tags (Comma-separated: Tag or Tag|Value)')}</span>
    </div>
  );
}

export default TagInput;
