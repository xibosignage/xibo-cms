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

import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';

import { fetchMenuBoardCategories } from '@/services/menuBoardApi';

interface MenuBoardCategoryTabsProps {
  menuId: string;
  activeCategoryId: string;
}

export default function MenuBoardCategoryTabs({
  menuId,
  activeCategoryId,
}: MenuBoardCategoryTabsProps) {
  const navigate = useNavigate();

  const { data: categories } = useQuery({
    queryKey: ['menuBoardCategoryTabs', menuId],
    queryFn: async ({ signal }) => {
      const { rows } = await fetchMenuBoardCategories(menuId, {
        start: 0,
        length: 1000,
        signal,
      });
      return rows;
    },
    enabled: !!menuId,
    staleTime: 1000 * 60 * 5,
  });

  if (!categories || categories.length < 2) {
    return null;
  }

  return (
    <nav
      className="-mb-3 mt-2 flex justify-center overflow-x-auto bg-slate-50 rounded z-10"
      aria-label="Menu Board Categories"
    >
      {categories.map((category) => {
        const isActive = String(category.menuCategoryId) === activeCategoryId;

        return (
          <button
            key={category.menuCategoryId}
            type="button"
            title={category.name}
            className={`text-sm font-semibold rounded-t-md px-3 py-2 inline-flex items-center gap-x-2 border-b-2 whitespace-nowrap max-w-xs truncate focus:outline-none transition-colors cursor-pointer ${
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
    </nav>
  );
}
