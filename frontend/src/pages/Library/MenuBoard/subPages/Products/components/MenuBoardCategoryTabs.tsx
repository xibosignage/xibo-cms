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

import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';

import { fetchMenuBoardCategories } from '@/services/menuBoardApi';
import type { MenuBoardCategory } from '@/types/menuBoardCategory';

const PAGE_SIZE = 100;

interface MenuBoardCategoryTabsProps {
  menuId: string;
  activeCategoryId: string;
}

export default function MenuBoardCategoryTabs({
  menuId,
  activeCategoryId,
}: MenuBoardCategoryTabsProps) {
  const navigate = useNavigate();

  const [categories, setCategories] = useState<MenuBoardCategory[]>([]);
  const [totalCount, setTotalCount] = useState(0);
  const [page, setPage] = useState(0);
  const [isLoadingMore, setIsLoadingMore] = useState(false);

  const scrollContainerRef = useRef<HTMLElement>(null);
  const sentinelRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!menuId) {
      return;
    }

    const controller = new AbortController();
    setCategories([]);
    setTotalCount(0);
    setPage(0);

    fetchMenuBoardCategories(menuId, {
      start: 0,
      length: PAGE_SIZE,
      signal: controller.signal,
    })
      .then((response) => {
        setCategories(response.rows);
        setTotalCount(response.totalCount);
      })
      .catch(() => {});

    return () => controller.abort();
  }, [menuId]);

  const hasMore = categories.length < totalCount;

  useEffect(() => {
    if (!hasMore || !sentinelRef.current || !scrollContainerRef.current) {
      return;
    }

    const el = sentinelRef.current;
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && !isLoadingMore) {
          const nextPage = page + 1;
          setIsLoadingMore(true);
          fetchMenuBoardCategories(menuId, {
            start: nextPage * PAGE_SIZE,
            length: PAGE_SIZE,
          })
            .then((response) => {
              setCategories((prev) => prev.concat(response.rows));
              setTotalCount(response.totalCount);
              setPage(nextPage);
            })
            .catch(() => {})
            .finally(() => setIsLoadingMore(false));
        }
      },
      // Load the next page a bit before the sentinel actually reaches the
      // visible edge, so scrolling feels continuous rather than stalling.
      { threshold: 0.1, root: scrollContainerRef.current, rootMargin: '0px 200px 0px 0px' },
    );
    observer.observe(el);
    return () => observer.disconnect();
  }, [hasMore, isLoadingMore, page, menuId]);

  if (categories.length < 2 && !hasMore) {
    return null;
  }

  return (
    <nav
      ref={scrollContainerRef}
      className="-mb-3 mt-2 flex justify-start overflow-x-auto bg-slate-50 rounded z-10"
      aria-label="Menu Board Categories"
    >
      {categories.map((category) => {
        const isActive = String(category.menuCategoryId) === activeCategoryId;

        return (
          <button
            key={category.menuCategoryId}
            type="button"
            title={category.name}
            className={`text-sm font-semibold rounded-t-md px-3 py-2 inline-flex items-center gap-x-2 border-b-2 whitespace-nowrap shrink-0 max-w-xs truncate focus:outline-none transition-colors cursor-pointer ${
              isActive
                ? 'text-xibo-blue-600 border-xibo-blue-600 bg-slate-100'
                : 'border-transparent text-gray-500 hover:text-gray-600 hover:border-gray-300'
            }`}
            aria-current={isActive ? 'page' : undefined}
            onClick={() =>
              navigate(
                `/library/menu-boards/${menuId}/categories/${category.menuCategoryId}/products`,
              )
            }
          >
            {category.name}
          </button>
        );
      })}
      {hasMore && (
        <div ref={sentinelRef} className="shrink-0 w-1 self-stretch" aria-hidden="true" />
      )}
    </nav>
  );
}
