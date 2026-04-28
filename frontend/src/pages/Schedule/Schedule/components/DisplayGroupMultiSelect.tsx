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
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import Checkbox from '@/components/ui/forms/Checkbox';
import { useDebounce } from '@/hooks/useDebounce';
import { fetchDisplayGroups } from '@/services/displayGroupApi';
import { fetchDisplays } from '@/services/displaysApi';

const PAGE_SIZE = 10;

const DISPLAY_PREFIX = 'd:';
const GROUP_PREFIX = 'g:';

interface Option {
  value: string;
  label: string;
}

export interface DisplayGroupMultiSelectValue {
  displaySpecificGroupIds: number[];
  displayGroupIds: number[];
}

interface DisplayGroupMultiSelectProps {
  value: DisplayGroupMultiSelectValue;
  onChange: (value: DisplayGroupMultiSelectValue) => void;
  disabled?: boolean;
  className?: string;
  triggerClassName?: string;
}

export function DisplayGroupMultiSelect({
  value,
  onChange,
  disabled = false,
  className,
  triggerClassName,
}: DisplayGroupMultiSelectProps) {
  const { t } = useTranslation();

  const [isOpen, setIsOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const debouncedSearch = useDebounce(searchTerm, 300);

  const [displayOptions, setDisplayOptions] = useState<Option[]>([]);
  const [displayPage, setDisplayPage] = useState(0);
  const [hasMoreDisplays, setHasMoreDisplays] = useState(false);
  const [isLoadingDisplays, setIsLoadingDisplays] = useState(false);
  const [isLoadingMoreDisplays, setIsLoadingMoreDisplays] = useState(false);

  const [groupOptions, setGroupOptions] = useState<Option[]>([]);
  const [groupPage, setGroupPage] = useState(0);
  const [hasMoreGroups, setHasMoreGroups] = useState(false);
  const [isLoadingGroups, setIsLoadingGroups] = useState(false);
  const [isLoadingMoreGroups, setIsLoadingMoreGroups] = useState(false);

  const displaySentinelRef = useRef<HTMLDivElement>(null);
  const groupSentinelRef = useRef<HTMLDivElement>(null);
  const scrollContainerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    setIsLoadingDisplays(true);
    setDisplayOptions([]);
    setDisplayPage(0);
    fetchDisplays({ start: 0, length: PAGE_SIZE, keyword: debouncedSearch || undefined })
      .then((res) => {
        setDisplayOptions(
          res.rows.map((d) => ({
            label: d.display,
            value: `${DISPLAY_PREFIX}${d.displayGroupId}`,
          })),
        );
        setHasMoreDisplays(res.rows.length === PAGE_SIZE);
      })
      .catch(() => {})
      .finally(() => setIsLoadingDisplays(false));
  }, [debouncedSearch]);

  useEffect(() => {
    setIsLoadingGroups(true);
    setGroupOptions([]);
    setGroupPage(0);
    fetchDisplayGroups({
      start: 0,
      length: PAGE_SIZE,
      isDisplaySpecific: 0,
      keyword: debouncedSearch || undefined,
    })
      .then((res) => {
        setGroupOptions(
          res.rows.map((g) => ({
            label: g.displayGroup,
            value: `${GROUP_PREFIX}${g.displayGroupId}`,
          })),
        );
        setHasMoreGroups(res.rows.length === PAGE_SIZE);
      })
      .catch(() => {})
      .finally(() => setIsLoadingGroups(false));
  }, [debouncedSearch]);

  useEffect(() => {
    if (!isOpen || !hasMoreDisplays || !displaySentinelRef.current || !scrollContainerRef.current) {
      return;
    }
    const el = displaySentinelRef.current;
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && !isLoadingMoreDisplays) {
          const nextPage = displayPage + 1;
          setIsLoadingMoreDisplays(true);
          fetchDisplays({
            start: nextPage * PAGE_SIZE,
            length: PAGE_SIZE,
            keyword: debouncedSearch || undefined,
          })
            .then((res) => {
              setDisplayOptions((prev) => [
                ...prev,
                ...res.rows.map((d) => ({
                  label: d.display,
                  value: `${DISPLAY_PREFIX}${d.displayGroupId}`,
                })),
              ]);
              setDisplayPage(nextPage);
              setHasMoreDisplays(res.rows.length === PAGE_SIZE);
            })
            .catch(() => {})
            .finally(() => setIsLoadingMoreDisplays(false));
        }
      },
      { threshold: 0.1, root: scrollContainerRef.current },
    );
    observer.observe(el);
    return () => observer.disconnect();
  }, [isOpen, hasMoreDisplays, isLoadingMoreDisplays, displayPage, debouncedSearch]);

  useEffect(() => {
    if (!isOpen || !hasMoreGroups || !groupSentinelRef.current || !scrollContainerRef.current) {
      return;
    }
    const el = groupSentinelRef.current;
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && !isLoadingMoreGroups) {
          const nextPage = groupPage + 1;
          setIsLoadingMoreGroups(true);
          fetchDisplayGroups({
            start: nextPage * PAGE_SIZE,
            length: PAGE_SIZE,
            isDisplaySpecific: 0,
            keyword: debouncedSearch || undefined,
          })
            .then((res) => {
              setGroupOptions((prev) => [
                ...prev,
                ...res.rows.map((g) => ({
                  label: g.displayGroup,
                  value: `${GROUP_PREFIX}${g.displayGroupId}`,
                })),
              ]);
              setGroupPage(nextPage);
              setHasMoreGroups(res.rows.length === PAGE_SIZE);
            })
            .catch(() => {})
            .finally(() => setIsLoadingMoreGroups(false));
        }
      },
      { threshold: 0.1, root: scrollContainerRef.current },
    );
    observer.observe(el);
    return () => observer.disconnect();
  }, [isOpen, hasMoreGroups, isLoadingMoreGroups, groupPage, debouncedSearch]);

  const selectedValues = new Set([
    ...value.displaySpecificGroupIds.map((id) => `${DISPLAY_PREFIX}${id}`),
    ...value.displayGroupIds.map((id) => `${GROUP_PREFIX}${id}`),
  ]);

  const toggle = (optValue: string) => {
    const next = new Set(selectedValues);
    if (next.has(optValue)) {
      next.delete(optValue);
    } else {
      next.add(optValue);
    }

    const displaySpecificGroupIds: number[] = [];
    const displayGroupIds: number[] = [];
    for (const v of next) {
      if (v.startsWith(DISPLAY_PREFIX)) {
        displaySpecificGroupIds.push(Number(v.slice(DISPLAY_PREFIX.length)));
      } else if (v.startsWith(GROUP_PREFIX)) {
        displayGroupIds.push(Number(v.slice(GROUP_PREFIX.length)));
      }
    }
    onChange({ displaySpecificGroupIds, displayGroupIds });
  };

  const handleOpenChange = (open: boolean) => {
    setIsOpen(open);
    if (!open) {
      setSearchTerm('');
    }
  };

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
            minWidth: `${rects.reference.width}px`,
          });
        },
      }),
    ],
  });

  const click = useClick(context, { enabled: !disabled });
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([click, dismiss]);

  const totalSelected = value.displaySpecificGroupIds.length + value.displayGroupIds.length;

  const visibleDisplays = displayOptions;
  const visibleGroups = groupOptions;
  const isLoading = isLoadingDisplays || isLoadingGroups;

  return (
    <div className={twMerge('relative flex-1 flex', className)}>
      <div
        ref={refs.setReference}
        {...getReferenceProps()}
        className={twMerge(
          'w-full border bg-gray-100 border-gray-200 rounded-lg flex items-center min-h-11.25 transition-colors gap-2 px-3 py-2',
          triggerClassName,
          disabled
            ? 'opacity-50 pointer-events-none bg-gray-200'
            : 'cursor-pointer hover:border-gray-400 focus-within:border-xibo-blue-600 focus-within:ring-1 focus-within:ring-xibo-blue-600/25',
        )}
      >
        {totalSelected === 0 ? (
          <span className="flex-1 text-sm text-gray-400">
            {t('Search Display and Display Groups')}
          </span>
        ) : isLoading ? (
          <span className="flex-1 text-sm text-gray-400">{t('Loading…')}</span>
        ) : (
          <div className="flex flex-wrap gap-1.5 flex-1 max-h-17 overflow-y-auto">
            {value.displaySpecificGroupIds.map((id) => {
              const label =
                displayOptions.find((o) => o.value === `${DISPLAY_PREFIX}${id}`)?.label ?? `${id}`;
              return (
                <span
                  key={`${DISPLAY_PREFIX}${id}`}
                  className="inline-flex items-center gap-1 rounded-full border border-gray-400 p-1.5"
                >
                  <span className="px-1 text-xs text-gray-800">{label}</span>
                  <button
                    type="button"
                    onClick={(e) => {
                      e.stopPropagation();
                      toggle(`${DISPLAY_PREFIX}${id}`);
                    }}
                    className="flex justify-center items-center size-3.75 bg-gray-200 text-gray-800 hover:text-gray-600 hover:bg-gray-300 rounded-full"
                  >
                    <X size={10} />
                  </button>
                </span>
              );
            })}
            {value.displayGroupIds.map((id) => {
              const label =
                groupOptions.find((o) => o.value === `${GROUP_PREFIX}${id}`)?.label ?? `${id}`;
              return (
                <span
                  key={`${GROUP_PREFIX}${id}`}
                  className="inline-flex items-center gap-1 rounded-full border border-gray-400 p-1.5"
                >
                  <span className="px-1 text-[12px] text-gray-800">{label}</span>
                  <button
                    type="button"
                    onClick={(e) => {
                      e.stopPropagation();
                      toggle(`${GROUP_PREFIX}${id}`);
                    }}
                    className="flex justify-center items-center size-3.75 bg-gray-200 text-gray-800 hover:text-gray-600 hover:bg-gray-300 rounded-full"
                  >
                    <X size={10} />
                  </button>
                </span>
              );
            })}
          </div>
        )}
        {totalSelected > 0 && (
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              onChange({ displaySpecificGroupIds: [], displayGroupIds: [] });
            }}
            className="shrink-0 flex items-center justify-center text-gray-500 hover:text-gray-600"
          >
            {t('Clear')}
          </button>
        )}
        <span
          className={twMerge(
            'text-gray-500 transition-transform duration-300 ease-in-out shrink-0',
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
                placeholder={t('Search…')}
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                onClick={(e) => e.stopPropagation()}
              />
            </div>

            <div
              ref={scrollContainerRef}
              className="flex flex-col p-2 text-sm overflow-y-auto max-h-96"
            >
              {isLoading ? (
                <p className="text-sm text-gray-400 text-center py-2">{t('Loading…')}</p>
              ) : (
                <>
                  <span className="px-2 py-1 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                    {t('Displays')}
                  </span>
                  {visibleDisplays.length === 0 ? (
                    <p className="text-sm text-gray-400 px-2 py-1">{t('No results')}</p>
                  ) : (
                    visibleDisplays.map((opt) => (
                      <Checkbox
                        key={opt.value}
                        id={`dg-ms-${opt.value}`}
                        label={opt.label}
                        checked={selectedValues.has(opt.value)}
                        className="px-2 py-2 gap-4 rounded-lg hover:bg-gray-100"
                        classNameLabel="m-0 font-semibold text-gray-800"
                        onChange={() => toggle(opt.value)}
                      />
                    ))
                  )}
                  {hasMoreDisplays && <div ref={displaySentinelRef} className="h-1" />}
                  {isLoadingMoreDisplays && (
                    <div className="text-xs text-gray-400 text-center py-1">{t('Loading…')}</div>
                  )}

                  <span className="px-2 py-1 mt-2 text-xs font-semibold text-gray-500 uppercase tracking-wide border-t border-gray-100 pt-3">
                    {t('Display Groups')}
                  </span>
                  {visibleGroups.length === 0 ? (
                    <p className="text-sm text-gray-400 px-2 py-1">{t('No results')}</p>
                  ) : (
                    visibleGroups.map((opt) => (
                      <Checkbox
                        key={opt.value}
                        id={`dg-ms-${opt.value}`}
                        label={opt.label}
                        checked={selectedValues.has(opt.value)}
                        className="px-2 py-2 gap-4 rounded-lg hover:bg-gray-100"
                        classNameLabel="m-0 font-semibold text-gray-800"
                        onChange={() => toggle(opt.value)}
                      />
                    ))
                  )}
                  {hasMoreGroups && <div ref={groupSentinelRef} className="h-1" />}
                  {isLoadingMoreGroups && (
                    <div className="text-xs text-gray-400 text-center py-1">{t('Loading…')}</div>
                  )}
                </>
              )}
            </div>
          </div>
        )}
      </FloatingPortal>
    </div>
  );
}
