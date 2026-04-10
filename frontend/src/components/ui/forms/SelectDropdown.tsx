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
  useFloating,
  autoUpdate,
  offset,
  flip,
  shift,
  size,
  useClick,
  useDismiss,
  useInteractions,
  FloatingPortal,
} from '@floating-ui/react';
import { ChevronDown, Search } from 'lucide-react';
import { useState, useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

export type SelectOption = {
  label: string;
  value: string;
  disabled?: boolean;
};

interface SelectDropdownProps {
  label?: string;
  value?: string;
  placeholder?: string;
  options: SelectOption[];
  searchable?: boolean;
  searchPlaceholder?: string;
  onSelect: (value: string) => void;
  helpText?: string;
  addLeftLabel?: boolean;
  leftLabelContent?: ReactNode;
  optionLabel?: string;
  addOptionAvatar?: boolean;
  className?: string;
  error?: string;
  isLoading?: boolean;
  onLoadMore?: () => void;
  hasMore?: boolean;
  isLoadingMore?: boolean;
  clearable?: boolean;
}

export default function SelectDropdown({
  label,
  value,
  placeholder = 'Select',
  options,
  searchable,
  searchPlaceholder,
  onSelect,
  helpText,
  addLeftLabel,
  leftLabelContent,
  optionLabel,
  className,
  addOptionAvatar,
  error,
  isLoading,
  onLoadMore,
  hasMore,
  isLoadingMore,
  clearable,
}: SelectDropdownProps) {
  const { t } = useTranslation();

  const [isOpen, setIsOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const sentinelRef = useRef<HTMLDivElement>(null);
  const scrollContainerRef = useRef<HTMLDivElement>(null);

  const handleOpenChange = (open: boolean) => {
    setIsOpen(open);
    if (!open) {
      setSearchTerm('');
    }
  };

  const visibleOptions =
    searchable && searchTerm
      ? options.filter((o) => o.label.toLowerCase().includes(searchTerm.toLowerCase()))
      : options;

  useEffect(() => {
    if (!isOpen || !hasMore || !onLoadMore || !sentinelRef.current || !scrollContainerRef.current) {
      return;
    }

    const el = sentinelRef.current;
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && !isLoadingMore) {
          onLoadMore();
        }
      },
      { threshold: 0.1, root: scrollContainerRef.current },
    );

    observer.observe(el);
    return () => observer.disconnect();
  }, [isOpen, hasMore, onLoadMore, isLoadingMore]);

  const selectedLabel = options.find((o) => o.value === value)?.label ?? '';

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
        apply({ rects, elements }) {
          Object.assign(elements.floating.style, {
            width: `${rects.reference.width}px`,
          });
        },
      }),
    ],
  });

  const click = useClick(context);
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([click, dismiss]);

  return (
    <div className={twMerge('relative overflow-visible', className)}>
      {label && (
        <label className="text-sm font-semibold text-gray-500 leading-5">{label && t(label)}</label>
      )}

      <div
        ref={refs.setReference}
        {...getReferenceProps()}
        className="w-full border bg-white border-gray-200 rounded-lg flex items-center cursor-pointer h-11.25 hover:border-gray-400 focus-within:border-xibo-blue-600 focus-within:ring-1 focus-within:ring-xibo-blue-600/25 focus:outline-none transition-colors"
      >
        {addLeftLabel && leftLabelContent && (
          <div className="py-2 px-3 border-r text-sm border-gray-200 text-gray-500">
            {leftLabelContent}
          </div>
        )}
        <span
          className={twMerge(
            'py-2 px-3 flex-1 text-sm',
            isLoading ? 'text-gray-400 italic' : 'text-gray-800 capitalize',
          )}
        >
          {selectedLabel || t(placeholder)}
        </span>
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
            {optionLabel && (
              <span className="bg-gray-100 p-2 text-sm font-semibold text-gray-500 uppercase w-full flex">
                {t(optionLabel)}
              </span>
            )}
            {searchable && (
              <div className="p-2 border-b border-gray-100 flex items-center gap-2">
                <Search size={14} className="text-gray-400 shrink-0" />
                <input
                  autoFocus
                  type="text"
                  className="flex-1 text-sm outline-none border-none bg-transparent"
                  placeholder={searchPlaceholder ?? t('Search…')}
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  onClick={(e) => e.stopPropagation()}
                />
              </div>
            )}
            <div
              ref={scrollContainerRef}
              className="flex flex-col p-2 text-sm overflow-y-auto max-h-75"
            >
              {clearable && (
                <button
                  type="button"
                  className="text-left p-2 rounded-lg font-medium hover:bg-gray-100 cursor-pointer text-gray-400 italic"
                  onClick={() => {
                    onSelect('');
                    handleOpenChange(false);
                  }}
                >
                  {t(placeholder)}
                </button>
              )}
              {visibleOptions.length === 0 && !isLoadingMore && (
                <p className="text-sm text-gray-400 text-center py-2">{t('No results')}</p>
              )}
              {visibleOptions.map((option) => (
                <button
                  key={option.value}
                  type="button"
                  disabled={option.disabled}
                  className={twMerge(
                    'text-left p-2 rounded-lg font-medium flex gap-2 items-center',
                    option.disabled
                      ? 'text-gray-300 cursor-not-allowed'
                      : 'hover:bg-gray-100 cursor-pointer',
                  )}
                  onClick={() => {
                    if (option.disabled) {
                      return;
                    }

                    onSelect(option.value);
                    handleOpenChange(false);
                  }}
                >
                  {addOptionAvatar && (
                    <div className="bg-xibo-blue-100 h-6.5 w-6.5 text-[12px] center rounded-full text-xibo-blue-800 font-semibold flex items-center justify-center">
                      {option.label.slice(0, 1)}
                    </div>
                  )}
                  {t(option.label)}
                </button>
              ))}
              {hasMore && <div ref={sentinelRef} className="h-1" />}
              {isLoadingMore && (
                <div className="text-xs text-gray-400 text-center py-1">{t('Loading…')}</div>
              )}
            </div>
          </div>
        )}
      </FloatingPortal>

      {error ? (
        <p className="text-xs text-red-600 ml-2 mt-1">{error}</p>
      ) : (
        helpText && (
          <span className="text-xs text-gray-400 mt-1 whitespace-pre-line">{helpText}</span>
        )
      )}
    </div>
  );
}
