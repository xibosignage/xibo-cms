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

import {
  FloatingPortal,
  autoUpdate,
  flip,
  offset,
  shift,
  size,
  useDismiss,
  useFloating,
  useInteractions,
} from '@floating-ui/react';
import { X } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import SelectDropdown from './SelectDropdown';
import { useTagSuggestions } from './hooks/useTagSuggestions';

import { fetchTags } from '@/services/tagApi';
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

function parseOptions(raw: string | null | undefined): string[] | null {
  if (!raw) return null;
  try {
    const parsed = JSON.parse(raw);
    if (Array.isArray(parsed) && parsed.length > 0) {
      return parsed.map(String);
    }
  } catch {
    return null;
  }
  return null;
}

interface PendingTag {
  tag: string;
  options: string[] | null;
  isRequired: number;
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
  suggestions?: boolean;
  onPendingValueChange?: (isPending: boolean) => void;
  compact?: boolean;
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
  suggestions = true,
  onPendingValueChange,
  compact = false,
}: TagInputProps) {
  const { t } = useTranslation();
  const inputId = useId();
  const valueInputId = useId();
  const listboxId = useId();
  const [internalInput, setInternalInput] = useState('');
  const input = inputValue !== undefined ? inputValue : internalInput;
  const setInput = onInputChange ?? setInternalInput;
  const tags = Array.isArray(value) ? value : [];

  // Track which tag is pending a value entry
  const [pendingValueTag, setPendingValueTag] = useState<PendingTag | null>(null);
  const [valueInput, setValueInput] = useState('');
  const [valueError, setValueError] = useState(false);
  const valueInputRef = useRef<HTMLInputElement>(null);

  const [isFocused, setIsFocused] = useState(false);
  const [isOpen, setIsOpen] = useState(false);
  const [highlightIndex, setHighlightIndex] = useState(-1);

  const { suggestions: tagSuggestions } = useTagSuggestions(
    input,
    suggestions && !disabled && pendingValueTag === null,
  );

  const filteredSuggestions = tagSuggestions.filter((s) => !tags.some((t) => t.tag === s.tag));

  const showSuggestions =
    suggestions &&
    isOpen &&
    isFocused &&
    !disabled &&
    pendingValueTag === null &&
    input.trim().length > 0 &&
    filteredSuggestions.length > 0;

  const { refs, floatingStyles, context } = useFloating({
    open: showSuggestions,
    onOpenChange: setIsOpen,
    placement: 'bottom-start',
    whileElementsMounted: autoUpdate,
    middleware: [
      offset(4),
      flip(),
      shift(),
      size({
        apply({ rects, availableHeight, elements }) {
          Object.assign(elements.floating.style, {
            minWidth: `${rects.reference.width}px`,
            maxHeight: `${Math.min(availableHeight, 240)}px`,
          });
        },
        padding: 8,
      }),
    ],
  });

  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([dismiss]);

  useEffect(() => {
    setHighlightIndex(-1);
  }, [input]);

  // Reset pending value state when tags are cleared externally (e.g. modal close/reopen)
  useEffect(() => {
    if (tags.length === 0 && pendingValueTag !== null) {
      setPendingValueTag(null);
      setValueInput('');
      setValueError(false);
    }
  }, [tags.length, pendingValueTag]);

  // Focus the value input when a pending tag is set
  useEffect(() => {
    if (pendingValueTag) {
      valueInputRef.current?.focus();
    }
  }, [pendingValueTag]);

  // Notify parent when pending value entry is active/inactive
  useEffect(() => {
    onPendingValueChange?.(pendingValueTag !== null);
  }, [pendingValueTag, onPendingValueChange]);

  const startValueEntry = (name: string, known?: Tag) => {
    const source = known ?? tagSuggestions.find((s) => s.tag === name);
    setPendingValueTag({
      tag: name,
      options: parseOptions(source?.options),
      isRequired: source?.isRequired ?? 0,
    });
    setValueInput('');
    setValueError(false);

    if (!source) {
      fetchTags({ tag: name, allTags: 1, length: 5 })
        .then(({ rows }) => {
          const match = rows.find((r) => r.tag === name);
          if (!match) return;
          setPendingValueTag((prev) =>
            prev && prev.tag === name
              ? { ...prev, options: parseOptions(match.options), isRequired: match.isRequired ?? 0 }
              : prev,
          );
        })
        .catch(() => {});
    }
  };

  const addTag = (raw: string, known?: Tag) => {
    const newTag = parseTag(raw);
    if (!newTag) return;

    const exists = tags.some((t) => t.tag === newTag.tag);
    if (exists) return;

    onChange([...tags, newTag]);
    setInput('');

    // If added without a value, show value input
    if (allowValues && newTag.value === '') {
      startValueEntry(newTag.tag, known);
    }
  };

  const selectSuggestion = (tag: Tag) => {
    addTag(tag.tag, tag);
    setIsOpen(false);
    setHighlightIndex(-1);
  };

  const removeTag = (tag: string) => {
    if (disabled) {
      return;
    }

    if (pendingValueTag?.tag === tag) {
      setPendingValueTag(null);
      setValueInput('');
    }
    onChange(tags.filter((t) => t.tag !== tag));
  };

  const applyValue = (explicit?: string) => {
    if (!pendingValueTag) return;

    const raw = explicit !== undefined ? explicit : valueInput;
    const trimmedValue = raw.trim();

    if (!trimmedValue && pendingValueTag.isRequired) {
      setValueError(true);
      return;
    }

    if (trimmedValue) {
      const parsedValue = isNaN(Number(trimmedValue)) ? trimmedValue : Number(trimmedValue);
      onChange(tags.map((t) => (t.tag === pendingValueTag.tag ? { ...t, value: parsedValue } : t)));
    }

    setPendingValueTag(null);
    setValueInput('');
    setValueError(false);
  };

  const cancelValueEntry = () => {
    if (!pendingValueTag) return;

    if (pendingValueTag.isRequired) {
      removeTag(pendingValueTag.tag);
    } else {
      setPendingValueTag(null);
      setValueInput('');
    }
    setValueError(false);
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
        ref={refs.setReference}
        {...getReferenceProps()}
        className={twMerge(
          'flex rounded-lg bg-white border border-gray-200 overflow-hidden transition-colors',
          compact ? 'h-11.25' : 'min-h-11.25',
          'focus-within:border-xibo-blue-500 focus-within:ring-1 focus-within:ring-xibo-blue-500',
          disabled && 'opacity-50 pointer-events-none bg-gray-50',
        )}
      >
        {prefix && (
          <div className="flex items-center border-e border-gray-200 shrink-0">{prefix}</div>
        )}

        <div
          className={twMerge(
            'flex-1 p-2 flex flex-wrap gap-2 items-center min-w-0',
            compact && 'overflow-y-auto overflow-x-hidden h-full',
          )}
        >
          {tags.map((tagObj) => {
            const label =
              tagObj.value !== '' && tagObj.value != null
                ? `${tagObj.tag}|${tagObj.value}`
                : tagObj.tag;

            return (
              <span
                key={label}
                className={twMerge(
                  'flex items-center gap-1 px-2 py-1 font-semibold border text-xibo-blue-600 border-xibo-blue-400 rounded-full',
                  compact ? 'text-xs min-w-0 max-w-full' : 'text-sm',
                )}
              >
                {compact ? (
                  <span className="block min-w-0 max-w-32 truncate" title={label}>
                    {label}
                  </span>
                ) : (
                  label
                )}
                <button
                  type="button"
                  aria-label={t('Remove tag {{tag}}', { tag: tagObj.tag })}
                  onClick={() => removeTag(tagObj.tag)}
                  disabled={disabled}
                  className="text-xibo-blue-600 w-3 rounded-full h-3 flex items-center justify-center bg-xibo-blue-200 hover:text-gray-600 shrink-0"
                >
                  <X size={8} />
                </button>
              </span>
            );
          })}
          <input
            id={inputId}
            role="combobox"
            aria-expanded={showSuggestions}
            aria-controls={listboxId}
            aria-autocomplete="list"
            aria-activedescendant={
              showSuggestions && highlightIndex >= 0
                ? `${listboxId}-option-${highlightIndex}`
                : undefined
            }
            className={twMerge(
              'flex-1 min-w-10 p-1 bg-transparent border-none outline-none focus:outline-none focus:ring-0',
              compact ? 'text-xs' : 'text-sm',
            )}
            value={input}
            disabled={disabled || pendingValueTag !== null}
            onChange={(e) => {
              setInput(e.target.value);
              setIsOpen(true);
            }}
            onFocus={() => setIsFocused(true)}
            onKeyDown={(e) => {
              if (showSuggestions && e.key === 'ArrowDown') {
                e.preventDefault();
                setHighlightIndex((i) => (i + 1) % filteredSuggestions.length);
              } else if (showSuggestions && e.key === 'ArrowUp') {
                e.preventDefault();
                setHighlightIndex((i) => (i <= 0 ? filteredSuggestions.length - 1 : i - 1));
              } else if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                if (e.key === 'Enter' && showSuggestions && highlightIndex >= 0) {
                  const picked = filteredSuggestions[highlightIndex];
                  if (picked) {
                    selectSuggestion(picked);
                    return;
                  }
                }
                addTag(input);
              } else if (e.key === 'Escape' && showSuggestions) {
                e.preventDefault();
                setIsOpen(false);
                setHighlightIndex(-1);
              } else if (e.key === 'Backspace' && !input && tags.length > 0) {
                const lastTag = tags[tags.length - 1];
                if (lastTag) {
                  removeTag(lastTag.tag);
                }
              }
            }}
            onBlur={() => {
              setIsFocused(false);
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

      <FloatingPortal>
        {showSuggestions && (
          <div
            ref={refs.setFloating}
            style={floatingStyles}
            {...getFloatingProps()}
            role="listbox"
            id={listboxId}
            className="z-9999 bg-white shadow-md rounded-lg overflow-y-auto border border-gray-200 py-1"
          >
            {filteredSuggestions.map((s, index) => (
              <button
                key={s.tag}
                type="button"
                role="option"
                id={`${listboxId}-option-${index}`}
                aria-selected={index === highlightIndex}
                onMouseDown={(e) => e.preventDefault()}
                onMouseEnter={() => setHighlightIndex(index)}
                onClick={() => selectSuggestion(s)}
                className={twMerge(
                  'w-full text-left px-3 py-2 text-sm text-gray-800 cursor-pointer',
                  index === highlightIndex ? 'bg-gray-100' : 'hover:bg-gray-100',
                )}
              >
                {s.tag}
              </button>
            ))}
          </div>
        )}
      </FloatingPortal>

      {pendingValueTag && (
        <div className="flex flex-col gap-1">
          <label htmlFor={valueInputId} className="text-sm font-semibold text-gray-500 leading-5">
            {t('Tag Value')}
          </label>
          {pendingValueTag.options && pendingValueTag.options.length > 0 ? (
            <SelectDropdown
              value={pendingValueTag.options.includes(valueInput) ? valueInput : ''}
              placeholder={t('Select a value')}
              options={pendingValueTag.options.map((opt) => ({ label: opt, value: opt }))}
              onSelect={(val) => applyValue(val)}
              clearable
            />
          ) : (
            <input
              id={valueInputId}
              ref={valueInputRef}
              className={twMerge(
                'w-full text-sm px-3 py-2.5 rounded-lg border border-gray-200 outline-none focus:border-xibo-blue-500 focus:ring-1 focus:ring-xibo-blue-500 min-h-11.25',
                valueError && 'border-red-500 focus:border-red-500 focus:ring-red-500',
              )}
              aria-invalid={valueError}
              aria-describedby={valueError ? `${valueInputId}-err` : undefined}
              value={valueInput}
              onChange={(e) => {
                setValueInput(e.target.value);
                if (valueError) setValueError(false);
              }}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.preventDefault();
                  applyValue();
                } else if (e.key === 'Escape') {
                  cancelValueEntry();
                }
              }}
              onBlur={() => applyValue()}
            />
          )}
          {valueError ? (
            <span id={`${valueInputId}-err`} className="text-xs text-red-600">
              {t('This Tag requires a value.')}
            </span>
          ) : (
            <span className="text-xs text-gray-400">
              {pendingValueTag.options && pendingValueTag.options.length > 0
                ? pendingValueTag.isRequired
                  ? t('Select a value for this Tag (required).')
                  : t('Select a value for this Tag, or leave blank.')
                : pendingValueTag.isRequired
                  ? t('Enter a value for this Tag (required) and confirm by pressing enter.')
                  : t('Enter the value for this Tag and confirm by pressing enter on keyboard.')}
            </span>
          )}
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
