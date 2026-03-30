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

import { useInfiniteQuery } from '@tanstack/react-query';
import { Search, X, ChevronDown, Loader2, Check } from 'lucide-react';
import { useState, useRef, useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { fetchMedia } from '@/services/mediaApi';

interface MediaInputProps {
  label: string;
  value?: string | number;
  onChange: (value: string) => void;
  helpText?: string;
  isMulti?: boolean;
}

export default function MediaInput({
  label,
  value,
  onChange,
  helpText,
  isMulti = false,
}: MediaInputProps) {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');

  const [nameCache, setNameCache] = useState<Record<string, string>>({});

  const containerRef = useRef<HTMLDivElement>(null);
  const observerTarget = useRef<HTMLDivElement>(null);

  const selectedIds = value ? String(value).split(',').filter(Boolean) : [];

  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedSearch(searchTerm);
    }, 300);
    return () => clearTimeout(handler);
  }, [searchTerm]);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const { data, fetchNextPage, hasNextPage, isFetchingNextPage, isLoading } = useInfiniteQuery({
    queryKey: ['libraryMedia', debouncedSearch],
    queryFn: async ({ pageParam = 0, signal }) => {
      return fetchMedia({
        start: pageParam,
        length: 20,
        keyword: debouncedSearch || undefined,
        type: 'image',
        signal,
      });
    },
    initialPageParam: 0,
    getNextPageParam: (lastPage, allPages) => {
      const loadedItems = allPages.reduce((total, page) => total + page.rows.length, 0);
      return loadedItems < lastPage.totalCount ? loadedItems : undefined;
    },
    enabled: isOpen,
  });

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && hasNextPage && !isFetchingNextPage) {
          fetchNextPage();
        }
      },
      { threshold: 1.0 },
    );

    if (observerTarget.current) {
      observer.observe(observerTarget.current);
    }

    return () => observer.disconnect();
  }, [hasNextPage, isFetchingNextPage, fetchNextPage]);

  const mediaItems = useMemo(() => {
    return data?.pages.flatMap((page) => page.rows) || [];
  }, [data?.pages]);

  useEffect(() => {
    if (mediaItems.length > 0) {
      setNameCache((prev) => {
        const newCache = { ...prev };
        let hasChanges = false;
        mediaItems.forEach((m) => {
          const id = String(m.mediaId);
          if (newCache[id] !== m.name) {
            newCache[id] = m.name;
            hasChanges = true;
          }
        });
        return hasChanges ? newCache : prev;
      });
    }
  }, [mediaItems]);

  const handleSelect = (id: string) => {
    if (isMulti) {
      if (selectedIds.includes(id)) {
        onChange(selectedIds.filter((selectedId) => selectedId !== id).join(','));
      } else {
        onChange([...selectedIds, id].join(','));
      }
    } else {
      onChange(id);
      setIsOpen(false);
    }
  };

  const handleRemoveItem = (e: React.MouseEvent, idToRemove: string) => {
    e.stopPropagation();
    if (isMulti) {
      onChange(selectedIds.filter((id) => id !== idToRemove).join(','));
    } else {
      onChange('');
    }
  };

  return (
    <div className="flex flex-col gap-1.5 relative" ref={containerRef}>
      <label className="text-sm font-semibold text-gray-500">{label}</label>

      <div
        onClick={() => setIsOpen(!isOpen)}
        className={`flex flex-wrap items-center gap-1.5 py-1.5 px-2 bg-white border rounded-lg min-h-11.25 transition-colors ${
          isOpen
            ? 'border-xibo-blue-500 ring-1 ring-xibo-blue-500'
            : 'border-gray-200 hover:border-gray-300'
        }`}
      >
        {selectedIds.length === 0 ? (
          <span className="text-gray-400 text-sm px-1 select-none">{t('Select Media...')}</span>
        ) : (
          selectedIds.map((id) => (
            <span
              key={id}
              className="flex items-center gap-1.5 bg-gray-50 text-sm p-1.5 rounded-full"
              onClick={(e) => e.stopPropagation()}
            >
              <span className="truncate max-w-37.5 px-1">
                {nameCache[id] || `${t('Media ID:')} ${id}`}
              </span>
              <button
                type="button"
                onClick={(e) => handleRemoveItem(e, id)}
                className="text-gray-800 hover:text-red-500 hover:bg-red-50 cursor-pointer rounded-full bg-gray-200 p-0.5 transition-colors focus:outline-none"
                title={t('Remove')}
              >
                <X size={14} />
              </button>
            </span>
          ))
        )}

        <div className="ml-auto pl-1">
          <ChevronDown
            size={16}
            className={`text-gray-400 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
          />
        </div>
      </div>

      {helpText && <p className="text-xs text-gray-500">{helpText}</p>}

      {/* Dropdown Popover */}
      {isOpen && (
        <div className="absolute z-50 top-full left-0 mt-0.5 w-full bg-white border border-gray-200 rounded-lg shadow-lg flex flex-col overflow-hidden">
          <div className="px-3 py-2 text-sm bg-gray-100 text-gray-500 font-semibold uppercase">
            {t('Select Media')}
          </div>
          <div className="p-2">
            <div className="relative flex-1 flex">
              <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <Search className="w-4 h-4 text-gray-500" />
              </div>
              <input
                type="text"
                placeholder={t('Search')}
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="py-2 px-3 pl-10 block h-11.25 rounded-lg w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none disabled:bg-gray-200"
                autoFocus
              />
            </div>
          </div>

          <div className="max-h-60 overflow-y-auto p-2">
            {isLoading && mediaItems.length === 0 ? (
              <div className="flex justify-center items-center py-4">
                <Loader2 className="h-5 w-5 text-gray-400 animate-spin" />
              </div>
            ) : mediaItems.length === 0 ? (
              <div className="py-4 text-center text-sm text-gray-500">{t('No media found.')}</div>
            ) : (
              <ul className="flex flex-col gap-0.5">
                {mediaItems.map((media) => {
                  const idString = String(media.mediaId);
                  const isSelected = selectedIds.includes(idString);

                  return (
                    <li key={media.mediaId}>
                      <button
                        type="button"
                        onClick={() => handleSelect(idString)}
                        className={`w-full text-left px-3 py-2 text-sm font-semibold text-gray-800 rounded-md cursor-pointer flex items-center justify-between transition-colors ${
                          isSelected
                            ? 'bg-xibo-blue-50 text-xibo-blue-700 font-medium'
                            : 'text-gray-700 hover:bg-gray-50'
                        }`}
                      >
                        <div className="truncate pr-2">
                          <span className="truncate">{media.name}</span>
                        </div>
                        {isSelected && <Check size={16} className="text-xibo-blue-600 shrink-0" />}
                      </button>
                    </li>
                  );
                })}

                <div
                  ref={observerTarget}
                  className="h-4 w-full flex justify-center items-center mt-2"
                >
                  {isFetchingNextPage && <Loader2 className="h-4 w-4 text-gray-400 animate-spin" />}
                </div>
              </ul>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
