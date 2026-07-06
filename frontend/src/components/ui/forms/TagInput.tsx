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
import { useEffect, useId, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import type { Tag } from '@/types/tag';

export function parseTag(raw: string): Tag | null {
  const trimmed = raw.trim();
  if (!trimmed) return null;

  const pipeIndex = trimmed.indexOf('|');
  const tag = pipeIndex === -1 ? trimmed : trimmed.slice(0, pipeIndex);
  const rawValue = pipeIndex === -1 ? undefined : trimmed.slice(pipeIndex + 1);

  if (!tag) return null;

  return {
    tag: tag.trim(),
    value:
      rawValue !== undefined && rawValue !== ''
        ? isNaN(Number(rawValue))
          ? rawValue.trim()
          : Number(rawValue)
        : '',
    tagId: 0,
  };
}

// Merges any uncommitted text from the tag input into the tag list.
export function collectTags(tags: Tag[], pendingInput: string): Tag[] {
  const pending = parseTag(pendingInput);
  if (!pending) return tags;
  if (tags.some((t) => t.tag === pending.tag)) return tags;
  return [...tags, pending];
}

// Serializes a Tag[] into the comma-separated string the API expects.
export function serializeTags(tags: Tag[]): string {
  return tags
    .map((t) => (t.value != null && t.value !== '' ? `${t.tag}|${t.value}` : t.tag))
    .join(',');
}

interface TagInputProps {
  value: Tag[];
  label?: string;
  placeholder?: string;
  helpText?: string;
  onChange: (tags: Tag[]) => void;
  className?: string;
  disabled?: boolean;
  prefix?: React.ReactNode;
  suffix?: React.ReactNode;
  error?: string;
  optional?: boolean;
  inputValue?: string;
  onInputChange?: (value: string) => void;
  allowValues?: boolean;
}

function TagInput({
  value = [],
  onChange,
  className,
  label,
  placeholder,
  helpText,
  disabled = false,
  prefix,
  suffix,
  error,
  optional = false,
  inputValue,
  onInputChange,
  allowValues = true,
}: TagInputProps) {
  const { t } = useTranslation();
  const inputId = useId();
  const valueInputId = useId();
  const [internalInput, setInternalInput] = useState('');
  const input = inputValue !== undefined ? inputValue : internalInput;
  const setInput = onInputChange ?? setInternalInput;
  const tags = Array.isArray(value) ? value : [];

  // Track which tag is pending a value entry
  const [pendingValueTag, setPendingValueTag] = useState<string | null>(null);
  const [valueInput, setValueInput] = useState('');
  const valueInputRef = useRef<HTMLInputElement>(null);

  // Reset pending value state when tags are cleared externally (e.g. modal close/reopen)
  useEffect(() => {
    if (tags.length === 0 && pendingValueTag !== null) {
      setPendingValueTag(null);
      setValueInput('');
    }
  }, [tags.length, pendingValueTag]);

  // Focus the value input when a pending tag is set
  useEffect(() => {
    if (pendingValueTag) {
      valueInputRef.current?.focus();
    }
  }, [pendingValueTag]);

  const addTag = (raw: string) => {
    const newTag = parseTag(raw);
    if (!newTag) return;

    const exists = tags.some((t) => t.tag === newTag.tag);
    if (exists) return;

    onChange([...tags, newTag]);
    setInput('');

    // If added without a value, show value input
    if (allowValues && newTag.value === '') {
      setPendingValueTag(newTag.tag);
      setValueInput('');
    }
  };

  const removeTag = (tag: string) => {
    if (disabled) {
      return;
    }

    if (pendingValueTag === tag) {
      setPendingValueTag(null);
      setValueInput('');
    }
    onChange(tags.filter((t) => t.tag !== tag));
  };

  const applyValue = () => {
    if (!pendingValueTag) return;

    const trimmedValue = valueInput.trim();
    if (trimmedValue) {
      const parsedValue = isNaN(Number(trimmedValue)) ? trimmedValue : Number(trimmedValue);
      onChange(tags.map((t) => (t.tag === pendingValueTag ? { ...t, value: parsedValue } : t)));
    }

    setPendingValueTag(null);
    setValueInput('');
  };

  return (
    <div className={twMerge('flex flex-col gap-1 relative w-full', className)}>
      <label
        htmlFor={inputId}
        className="flex items-center justify-between text-sm font-semibold text-gray-500 leading-5"
      >
        <span>{!label ? t('Tags') : label}</span>
        {optional && <span className="text-xs font-normal text-gray-500">{t('Optional')}</span>}
      </label>

      <div
        className={twMerge(
          'flex rounded-lg bg-white border border-gray-200 overflow-hidden transition-colors min-h-11.25',
          'focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500',
          disabled && 'opacity-50 pointer-events-none bg-gray-50',
        )}
      >
        {prefix && (
          <div className="flex items-center border-e border-gray-200 shrink-0">{prefix}</div>
        )}

        <div className="flex-1 p-2 flex flex-wrap gap-2 items-center min-w-0">
          {tags.map((tagObj) => (
            <span
              key={tagObj.tag}
              className="flex items-center gap-1 px-2 py-1 text-sm font-semibold border text-xibo-blue-600 border-xibo-blue-400 rounded-full"
            >
              {tagObj.value !== '' && tagObj.value != null
                ? `${tagObj.tag}|${tagObj.value}`
                : tagObj.tag}
              <button
                type="button"
                aria-label={t('Remove tag {{tag}}', { tag: tagObj.tag })}
                onClick={() => removeTag(tagObj.tag)}
                disabled={disabled}
                className="text-blue-600 w-3 rounded-full h-3 flex items-center justify-center bg-blue-200 hover:text-gray-600"
              >
                <X size={8} />
              </button>
            </span>
          ))}
          <input
            id={inputId}
            className="flex-1 min-w-10 text-sm p-1 bg-transparent border-none outline-none focus:outline-none focus:ring-0"
            value={input}
            disabled={disabled || pendingValueTag !== null}
            onChange={(e) => setInput(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                addTag(input);
              } else if (e.key === 'Backspace' && !input && tags.length > 0) {
                const lastTag = tags[tags.length - 1];
                if (lastTag) {
                  removeTag(lastTag.tag);
                }
              }
            }}
            onBlur={() => {
              if (!pendingValueTag) {
                addTag(input);
              }
            }}
            placeholder={tags.length === 0 ? placeholder || t('Add tags') : ''}
          />
        </div>

        {suffix && (
          <div className="flex items-center border-s border-gray-200 shrink-0">{suffix}</div>
        )}
      </div>

      {pendingValueTag && (
        <div className="flex flex-col gap-1">
          <label htmlFor={valueInputId} className="text-sm font-semibold text-gray-500 leading-5">
            {t('Tag Value')}
          </label>
          <input
            id={valueInputId}
            ref={valueInputRef}
            className="w-full text-sm px-3 py-2.5 rounded-lg border border-gray-200 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 min-h-11.25"
            value={valueInput}
            onChange={(e) => setValueInput(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault();
                applyValue();
              } else if (e.key === 'Escape') {
                setPendingValueTag(null);
                setValueInput('');
              }
            }}
            onBlur={applyValue}
          />
          <span className="text-xs text-gray-400">
            {t('Enter the value for this Tag and confirm by pressing enter on keyboard.')}
          </span>
        </div>
      )}

      {error ? (
        <p className="text-xs text-red-600 ml-2 mt-1">{error}</p>
      ) : (
        helpText && <span className="text-xs text-gray-400">{helpText}</span>
      )}
    </div>
  );
}

export default TagInput;
