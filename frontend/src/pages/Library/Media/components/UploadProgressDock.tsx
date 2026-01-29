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

import { X, Check, ChevronDown, AlertCircle } from 'lucide-react';
import { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';

import { useUploadContext } from '@/context/UploadContext';

interface UploadProgressDockProps {
  isModalOpen: boolean;
}

export function UploadProgressDock({ isModalOpen }: UploadProgressDockProps) {
  const { queue, clearQueue } = useUploadContext();
  const [isExpanded, setIsExpanded] = useState(true);

  const [hasDismissed, setHasDismissed] = useState(false);

  const total = queue.length;
  const inProgress = queue.filter((i) => i.status === 'uploading' || i.status === 'pending').length;
  const completed = queue.filter((i) => i.status === 'completed').length;
  const hasErrors = queue.some((i) => i.status === 'error');
  const isFinished = total > 0 && inProgress === 0;

  const isVisible = total > 0 && !isModalOpen && (!isFinished || !hasDismissed);

  useEffect(() => {
    if (inProgress > 0 && hasDismissed) {
      setHasDismissed(false);
    }
  }, [inProgress, hasDismissed]);

  if (!isVisible) {
    return null;
  }

  const handleDismiss = () => {
    if (isFinished) {
      clearQueue();
      setHasDismissed(true);
    }
  };

  const headerTitle = isFinished
    ? hasErrors
      ? 'Uploads completed with errors'
      : 'Uploads completed'
    : `${completed}/${total} items are still uploading`;

  const content = (
    <div className="fixed bottom-0 right-[85px] w-[290px] z-60 shadow-lg shadow-black/15">
      <div className="rounded-t-xl overflow-hidden relative">
        {/* Header */}
        <div className="flex justify-between items-center bg-gray-100">
          <div title={headerTitle} className="text-sm font-semibold px-3 py-2 w-full truncate">
            {headerTitle}
          </div>

          <button
            onClick={(e) => {
              e.stopPropagation();
              setIsExpanded((prev) => !prev);
            }}
            className="min-w-[38px] size-[38px] p-[11px] flex-1 text-gray-500 rounded-lg hover:bg-gray-200"
          >
            <ChevronDown className={`size-4 ${isExpanded ? 'rotate-0' : 'rotate-180'}`} />
          </button>

          {isFinished && (
            <button
              onClick={(e) => {
                e.stopPropagation();
                handleDismiss();
              }}
              className="min-w-[38px] size-[38px] p-[11px] flex-1 text-gray-500 rounded-lg hover:bg-gray-200"
            >
              <X className="size-4" />
            </button>
          )}
        </div>

        {/* Content */}
        {isExpanded && (
          <div className="bg-white p-5 flex flex-col gap-1 max-h-40 overflow-y-auto">
            {queue.map((item) => (
              <div key={item.id} className="flex items-center justify-between gap-3">
                <div className="text-sm font-normal text-gray-800 items-center w-full truncate">
                  {item.displayName || item.file?.name || 'Unknown File'}
                </div>

                <div className="flex-1">
                  {item.status === 'completed' ? (
                    <div className="flex items-center justify-center size-6.5 bg-teal-100 text-teal-800 rounded-full">
                      <Check className="size-4" strokeWidth={3} />
                    </div>
                  ) : item.status === 'error' ? (
                    <div className="flex items-center justify-center size-6.5 bg-red-100 text-red-800 rounded-full">
                      <AlertCircle className="size-3" strokeWidth={3} />
                    </div>
                  ) : (
                    <span className="text-xs font-semibold text-gray-500 tabular-nums">
                      {Math.round(item.progress)}%
                    </span>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );

  return createPortal(content, document.body);
}
