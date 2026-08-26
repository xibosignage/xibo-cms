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

import { ChevronDown, Loader2, Maximize2 } from 'lucide-react';
import { useState } from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';

interface AddDisplayDockProps {
  displayName: string;
  onMaximize: () => void;
}

export default function AddDisplayDock({ displayName, onMaximize }: AddDisplayDockProps) {
  const { t } = useTranslation();
  const [isExpanded, setIsExpanded] = useState(true);

  const content = (
    <div className="fixed bottom-0 right-21.25 w-72.5 z-40 shadow-lg shadow-black/15">
      <div className="rounded-t-xl overflow-hidden relative">
        {/* Header */}
        <div className="flex justify-between items-center bg-gray-100">
          <div className="text-sm font-semibold px-3 py-2 w-full truncate">
            {t('1 Display is connecting')}
          </div>

          <button
            onClick={(e) => {
              e.stopPropagation();
              setIsExpanded((prev) => !prev);
            }}
            className="min-w-9.5 size-9.5 p-2.75 flex-1 text-gray-500 rounded-lg hover:bg-gray-200"
          >
            <ChevronDown className={`size-4 ${isExpanded ? 'rotate-0' : 'rotate-180'}`} />
          </button>
        </div>

        {/* Content */}
        {isExpanded && (
          <div className="bg-white p-5 flex flex-col gap-1 max-h-40 overflow-y-auto">
            <div className="flex items-center justify-between gap-3">
              <div className="text-sm font-normal text-gray-800 w-full truncate">
                {displayName}
              </div>
              <div className="flex items-center gap-2 shrink-0">
                <Loader2 size={16} className="text-xibo-blue-500 animate-spin" />
                <button
                  type="button"
                  onClick={onMaximize}
                  className="text-xibo-blue-500 hover:text-xibo-blue-600 transition-colors"
                  aria-label={t('Maximize')}
                >
                  <Maximize2 size={16} />
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );

  return createPortal(content, document.body);
}
