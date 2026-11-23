/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import { X, Check, ChevronDown, AlertCircle } from 'lucide-react';
import { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';

import { useUploadContext } from '@/context/UploadContext';
import { hasFailed, isStoring, type UploadItem } from '@/hooks/useUploadQueue';

const isActive = (item: UploadItem) => item.status === 'uploading' || item.status === 'pending';

export function UploadProgressDock() {
  const { t } = useTranslation();
  const { queue, clearFinished, uploadSession } = useUploadContext();
  const [isExpanded, setIsExpanded] = useState(true);

  const [hasDismissed, setHasDismissed] = useState(false);

  // Items finished before the Add Media dialog last opened are hidden unless retried.
  const [staleIds, setStaleIds] = useState<ReadonlySet<string>>(new Set());
  const [seenSession, setSeenSession] = useState(uploadSession);

  if (uploadSession !== seenSession) {
    setSeenSession(uploadSession);
    setStaleIds(new Set(queue.filter((i) => !isActive(i)).map((i) => i.id)));
  } else if (queue.some((i) => staleIds.has(i.id) && isActive(i))) {
    setStaleIds(
      new Set([...staleIds].filter((id) => !queue.some((i) => i.id === id && isActive(i)))),
    );
  }

  const items = queue.filter((i) => !staleIds.has(i.id) || isActive(i));
  const total = items.length;
  const inProgress = items.filter(isActive).length;
  const completed = items.filter((i) => i.status === 'completed').length;
  const hasErrors = items.some(hasFailed);
  const isFinished = total > 0 && inProgress === 0;

  const isVisible = total > 0 && (!isFinished || !hasDismissed);

  // A thumbnail can fail after the dock was dismissed, so a new failure shows it again.
  const failedCount = items.filter(hasFailed).length;
  const [seenFailedCount, setSeenFailedCount] = useState(failedCount);
  if (failedCount !== seenFailedCount) {
    setSeenFailedCount(failedCount);
    if (failedCount > seenFailedCount && hasDismissed) {
      setHasDismissed(false);
    }
  }

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
      // Keeps failed items, so they can be retried.
      clearFinished();
      setHasDismissed(true);
    }
  };

  const headerTitle = isFinished
    ? hasErrors
      ? t('Uploads completed with errors')
      : t('Uploads completed')
    : t('{{completed}}/{{total}} items are still uploading', { completed, total });

  const content = (
    <div className="fixed bottom-0 right-5 w-72.5 z-40 shadow-lg shadow-black/15">
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
            aria-label={isExpanded ? t('Collapse') : t('Expand')}
            className="min-w-9.5 size-9.5 p-2.75 flex-1 text-gray-500 rounded-lg hover:bg-gray-200"
          >
            <ChevronDown className={`size-4 ${isExpanded ? 'rotate-0' : 'rotate-180'}`} />
          </button>

          {isFinished && (
            <button
              onClick={(e) => {
                e.stopPropagation();
                handleDismiss();
              }}
              aria-label={t('Dismiss')}
              className="min-w-9.5 size-9.5 p-2.75 flex-1 text-gray-500 rounded-lg hover:bg-gray-200"
            >
              <X className="size-4" />
            </button>
          )}
        </div>

        {/* Content */}
        {isExpanded && (
          <div className="bg-white p-5 flex flex-col gap-1 max-h-40 overflow-y-auto">
            {items.map((item) => (
              <div key={item.id} className="flex items-center justify-between gap-3">
                <div className="text-sm font-normal text-gray-800 items-center w-full truncate">
                  {item.displayName || item.file?.name || t('Unknown File')}
                </div>

                <div className="flex-1">
                  {item.status === 'completed' && !item.thumbnailFailed ? (
                    <div className="flex items-center justify-center size-6.5 bg-teal-100 text-teal-800 rounded-full">
                      <Check className="size-4" strokeWidth={3} />
                    </div>
                  ) : hasFailed(item) ? (
                    <div className="flex items-center justify-center size-6.5 bg-red-100 text-red-800 rounded-full">
                      <AlertCircle className="size-3" strokeWidth={3} />
                    </div>
                  ) : (
                    <span className="text-xs font-semibold text-gray-500 tabular-nums whitespace-nowrap">
                      {isStoring(item) ? t('Saving…') : `${Math.round(item.progress)}%`}
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
