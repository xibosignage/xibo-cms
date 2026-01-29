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

import { X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import type { Tag } from '@/types/tag';

interface TagInputProps {
  value: Tag[];
  label?: string;
  placeholder?: string;
  helpText?: string;
  onChange: (tags: Tag[]) => void;
  className?: string;
  disabled?: boolean;
}

function TagInput({
  value,
  onChange,
  className,
  label,
  placeholder,
  helpText,
  disabled = false,
}: TagInputProps) {
  const { t } = useTranslation();
  const [input, setInput] = useState('');

  const parseTag = (raw: string): Tag | null => {
    const trimmed = raw.trim();
    if (!trimmed) return null;

    const [tag, rawValue] = trimmed.split('|');

    if (!tag) return null;

    return {
      tag: tag.trim(),
      value:
        rawValue !== undefined && rawValue !== ''
          ? isNaN(Number(rawValue))
            ? rawValue.trim()
            : Number(rawValue)
          : '',
      tagId: 0, // temporary ID (backend can replace this)
    };
  };

  const addTag = (raw: string) => {
    const newTag = parseTag(raw);
    if (!newTag) return;

    const exists = value.some((t) => t.tag === newTag.tag);
    if (exists) return;

    onChange([...value, newTag]);
    setInput('');
  };

  const removeTag = (tag: string) => {
    if (disabled) {
      return;
    }

    onChange(value.filter((t) => t.tag !== tag));
  };

  return (
    <div className="flex flex-col gap-1 relative w-full">
      <label className="text-xs font-semibold text-gray-500 leading-5">
        {!label ? t('Tags') : label}
      </label>

      <div
        className={twMerge(
          'border border-gray-200 rounded-lg p-2 flex flex-wrap gap-2 items-center bg-white',
          'transition-colors duration-200 ease-in-out',
          'focus-within:border-blue-500 focus-within:ring-blue-500 focus-within:ring-1',
          disabled && 'opacity-50 pointer-events-none bg-gray-50',
          className,
        )}
      >
        {value.map((tagObj) => (
          <span
            key={tagObj.tag}
            className="flex items-center gap-1 px-2 py-1 text-sm font-semibold border text-xibo-blue-600 border-xibo-blue-400 rounded-full"
          >
            {tagObj.tag}
            <button
              type="button"
              onClick={() => removeTag(tagObj.tag)}
              disabled={disabled}
              className="text-blue-600 w-3 rounded-full h-3 center bg-blue-200 hover:text-gray-600"
            >
              <X size={8} />
            </button>
          </span>
        ))}
        <input
          className="flex-1 min-w-[120px] text-sm p-1 border-none outline-none focus:outline-none focus:ring-0 focus:shadow-none bg-transparent"
          value={input}
          disabled={disabled}
          onChange={(e) => setInput(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter' || e.key === ',') {
              e.preventDefault();
              addTag(input);
            } else if (e.key === 'Backspace' && !input && value.length > 0) {
              const lastTag = value[value.length - 1];
              if (lastTag) {
                removeTag(lastTag.tag);
              }
            }
          }}
          placeholder={value.length === 0 ? placeholder || t('Add tags') : ''}
        />
      </div>

      {helpText && <span className="text-xs text-gray-400">{helpText}</span>}
    </div>
  );
}

export default TagInput;
