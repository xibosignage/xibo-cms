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

import { useMemo } from 'react';

interface Tag {
  id: string | number;
  label: string;
}

interface TagsProps {
  tags: Tag[];
  limit?: number;
}

export function Tags({ tags, limit = 2 }: TagsProps) {
  const { visibleTags, remainingTags, remainingCount } = useMemo(() => {
    const safeTags = tags || [];
    return {
      visibleTags: safeTags.slice(0, limit),
      // Hidden tags
      remainingTags: safeTags.slice(limit),
      remainingCount: safeTags.length - limit,
    };
  }, [tags, limit]);

  // Show empty tag placeholder
  if (!tags?.length) {
    return <span className="text-gray-800">-</span>;
  }

  return (
    <div className="flex flex-wrap gap-1 items-center overflow-visible">
      {visibleTags.map((tag) => (
        <span
          key={tag.id}
          className="inline-flex items-center px-1 py-1 bg-gray-100 text-gray-800 whitespace-nowrap"
        >
          {tag.label}
        </span>
      ))}

      {remainingCount > 0 && (
        <div className="relative group inline-block">
          <span className="cursor-pointer inline-flex items-center px-1 py-1 bg-gray-100 text-gray-800 hover:bg-gray-200">
            +{remainingCount}
          </span>

          <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block z-50 w-max max-w-[200px]">
            <div className="bg-white border border-gray-200 shadow-xl p-2">
              <div className="flex flex-wrap gap-1">
                {remainingTags.map((tag) => (
                  <span
                    key={tag.id}
                    className="inline-flex items-center px-1 py-1 bg-gray-100 text-gray-800 whitespace-nowrap"
                  >
                    {tag.label}
                  </span>
                ))}
              </div>
            </div>
            <div className="w-2.5 h-2.5 bg-white border-r border-b border-gray-200 transform rotate-45 absolute left-1/2 -translate-x-1/2 -bottom-1.5"></div>
          </div>
        </div>
      )}
    </div>
  );
}
