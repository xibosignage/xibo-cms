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
  useClick,
  useDismiss,
  useFloating,
  useInteractions,
} from '@floating-ui/react';
import { ChevronDown, Search, X } from 'lucide-react';
import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import Checkbox from '@/components/ui/forms/Checkbox';
import type { MultiSelectOption } from '@/components/ui/forms/MultiSelectDropdown';

interface PaginatedMultiSelectDropdownProps {
  label: string;
  value: string[];
  options: MultiSelectOption[];
  onChange: (values: string[]) => void;
  placeholder?: string;
  searchPlaceholder?: string;
  showTags?: boolean;
  isLoading: boolean;
  isLoadingMore: boolean;
  hasMore: boolean;
  onSearch: (term: string) => void;
  onLoadMore: () => void;
}

/**
 * Multi-select for option lists loaded a page at a time from the server (search + infinite
 * scroll), e.g. `usePaginatedOptions`-backed hooks. Kept local to ProofOfPlay (its only consumer)
 * rather than folded into the shared `MultiSelectDropdown` (used across unrelated pages) so this
 * reporting-only pagination logic can't affect other flows.
 */
export default function PaginatedMultiSelectDropdown({
  label,
  value,
  options,
  onChange,
  placeholder,
  searchPlaceholder,
  showTags = false,
  isLoading,
  isLoadingMore,
  hasMore,
  onSearch,
  onLoadMore,
}: PaginatedMultiSelectDropdownProps) {
  const { t } = useTranslation();
  const id = useId();
  const [isOpen, setIsOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const sentinelRef = useRef<HTMLDivElement>(null);
  const listboxRef = useRef<HTMLDivElement>(null);
  const isLoadingMoreRef = useRef(isLoadingMore);
  isLoadingMoreRef.current = isLoadingMore;
  const onLoadMoreRef = useRef(onLoadMore);
  onLoadMoreRef.current = onLoadMore;

  const handleOpenChange = (open: boolean) => {
    setIsOpen(open);
    if (open) {
      onSearch('');
    } else {
      setSearchTerm('');
    }
  };

  useEffect(() => {
    if (!isOpen || !hasMore || !sentinelRef.current || !listboxRef.current) {
      return;
    }

    const el = sentinelRef.current;
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && !isLoadingMoreRef.current) {
          onLoadMoreRef.current();
        }
      },
      { threshold: 0.1, root: listboxRef.current },
    );

    observer.observe(el);
    return () => observer.disconnect();
  }, [isOpen, hasMore]);

  const { refs, floatingStyles, context } = useFloating({
    open: isOpen,
    onOpenChange: handleOpenChange,
    placement: 'bottom-start',
    whileElementsMounted: autoUpdate,
    middleware: [
      offset(4),
      flip(),
      shift(),
      size({
        apply({ rects, availableHeight, elements }) {
          Object.assign(elements.floating.style, {
            width: `${rects.reference.width}px`,
            maxHeight: `${availableHeight}px`,
          });
        },
        padding: 8,
      }),
    ],
  });

  const click = useClick(context);
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([click, dismiss]);

  const toggle = (optValue: string) => {
    if (value.includes(optValue)) {
      onChange(value.filter((v) => v !== optValue));
    } else {
      onChange([...value, optValue]);
    }
  };

  const labelSpanRef = useRef<HTMLSpanElement>(null);
  const [selectedLabel, setSelectedLabel] = useState(
    value.length === 0
      ? (placeholder ?? t('None selected'))
      : t('{{n}} selected', { n: value.length }),
  );

  useLayoutEffect(() => {
    const el = labelSpanRef.current;
    if (!el) {
      return;
    }

    const compute = () => {
      if (value.length === 0) {
        setSelectedLabel(placeholder ?? t('None selected'));
        return;
      }

      const labels = value.map((v) => options.find((o) => o.value === v)?.label ?? v);
      const style = getComputedStyle(el);
      const availableWidth =
        el.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);

      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      if (!ctx) {
        setSelectedLabel(labels.join(', '));
        return;
      }
      ctx.font = `${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;

      for (let fitted = labels.length; fitted > 0; fitted--) {
        const remaining = labels.length - fitted;
        const text =
          remaining > 0
            ? `${labels.slice(0, fitted).join(', ')} +${remaining} more`
            : labels.join(', ');

        if (ctx.measureText(text).width <= availableWidth) {
          setSelectedLabel(text);
          return;
        }
      }

      setSelectedLabel(t('{{n}} selected', { n: value.length }));
    };

    compute();

    const ro = new ResizeObserver(compute);
    ro.observe(el);
    return () => ro.disconnect();
  }, [value, options, placeholder, t]);

  return (
    <div className="flex flex-col gap-1 relative w-full">
      <label
        id={`${id}-label`}
        className="flex items-center justify-between text-sm font-semibold text-gray-500 leading-5"
      >
        <span>{label}</span>
      </label>

      <div
        ref={refs.setReference}
        {...getReferenceProps()}
        role="combobox"
        aria-expanded={isOpen}
        aria-haspopup="listbox"
        aria-labelledby={`${id}-label`}
        aria-controls={`${id}-listbox`}
        tabIndex={0}
        className={twMerge(
          'w-full border bg-white border-gray-200 rounded-lg flex items-center cursor-pointer hover:border-gray-400 focus-within:border-xibo-blue-600 focus-within:ring-1 focus-within:ring-xibo-blue-600/25 focus:outline-none transition-colors',
          showTags && value.length > 0 ? 'min-h-11.25 py-2 px-2' : 'h-11.25',
        )}
      >
        {showTags && value.length > 0 ? (
          <div className="flex-1 flex flex-wrap gap-1.5 items-center min-w-0">
            {value.map((v) => {
              const opt = options.find((o) => o.value === v);
              return (
                <span
                  key={v}
                  className="flex items-center gap-1.5 px-2 py-0.5 text-sm border text-gray-800 border-gray-400 rounded-full"
                >
                  {opt?.label ?? v}
                  <button
                    type="button"
                    onClick={(e) => {
                      e.stopPropagation();
                      onChange(value.filter((val) => val !== v));
                    }}
                    className="text-gray-700 rounded-full bg-gray-300 p-1 cursor-pointer"
                  >
                    <X size={8} />
                  </button>
                </span>
              );
            })}
          </div>
        ) : (
          <span
            ref={labelSpanRef}
            className={twMerge(
              'py-2 px-3 flex-1 text-sm truncate',
              value.length === 0 ? 'text-gray-400' : 'text-gray-800',
            )}
          >
            {selectedLabel}
          </span>
        )}
        <span
          className={twMerge(
            'p-3 text-gray-500 transition-transform duration-300 ease-in-out shrink-0',
            isOpen ? 'rotate-180' : 'rotate-0',
          )}
        >
          <ChevronDown size={14} />
        </span>
      </div>

      <FloatingPortal>
        {isOpen && (
          <div
            ref={refs.setFloating}
            style={floatingStyles}
            {...getFloatingProps()}
            className="z-9999 bg-white shadow-md rounded-lg overflow-clip border border-gray-200 flex flex-col"
          >
            <div className="p-2 border-b border-gray-100 flex items-center gap-2">
              <Search size={14} className="text-gray-400 shrink-0" />
              <input
                autoFocus
                type="text"
                className="flex-1 text-sm outline-none border-none bg-transparent"
                placeholder={searchPlaceholder ?? t('Search…')}
                value={searchTerm}
                onChange={(e) => {
                  setSearchTerm(e.target.value);
                  onSearch(e.target.value);
                }}
                onClick={(e) => e.stopPropagation()}
              />
            </div>
            <div
              id={`${id}-listbox`}
              role="listbox"
              aria-multiselectable="true"
              ref={listboxRef}
              className="flex flex-col p-2 text-sm overflow-y-auto flex-1 min-h-0"
            >
              {options.length === 0 && !isLoading && (
                <p className="text-sm text-gray-400 text-center py-2">{t('No results')}</p>
              )}
              {isLoading && options.length === 0 && (
                <p className="text-sm text-gray-400 text-center py-2">{t('Loading...')}</p>
              )}
              {options.map((option) => {
                const isSelected = value.includes(option.value);
                return (
                  <Checkbox
                    key={option.value}
                    id={`${id}-option-${option.value}`}
                    label={option.label}
                    checked={isSelected}
                    className="px-2 py-2 gap-4 rounded-lg hover:bg-gray-100"
                    classNameLabel="m-0 font-semibold text-gray-800"
                    onChange={() => toggle(option.value)}
                  />
                );
              })}
              {hasMore && <div ref={sentinelRef} className="h-1" />}
              {isLoadingMore && (
                <div className="text-xs text-gray-400 text-center py-1">{t('Loading…')}</div>
              )}
            </div>
          </div>
        )}
      </FloatingPortal>
    </div>
  );
}
