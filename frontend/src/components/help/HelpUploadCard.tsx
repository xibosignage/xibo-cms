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

import { FileText, Film, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import type { Attachment } from './useFeedbackAttachments';

interface HelpUploadCardProps {
  attachment: Attachment;
  onRemove: () => void;
}

export default function HelpUploadCard({ attachment, onRemove }: HelpUploadCardProps) {
  const { t } = useTranslation();
  const isPdf = attachment.file.type === 'application/pdf';

  return (
    <div className="flex items-center gap-3 rounded-lg border border-gray-200 p-2">
      <div className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded bg-gray-100 text-gray-500">
        {attachment.thumbUrl ? (
          <img
            src={attachment.thumbUrl}
            alt={attachment.file.name}
            className="size-full object-cover"
          />
        ) : isPdf ? (
          <FileText className="size-5" />
        ) : (
          <Film className="size-5" />
        )}
      </div>
      <div className="min-w-0 flex-1">
        <div className="truncate text-sm text-gray-800" title={attachment.file.name}>
          {attachment.file.name}
        </div>
        <div className="text-xs text-gray-500">{attachment.typeLabel}</div>
      </div>
      <button
        type="button"
        aria-label={t('Remove')}
        onClick={onRemove}
        className="flex size-6 shrink-0 items-center justify-center text-gray-400 hover:text-gray-700 cursor-pointer"
      >
        <X className="size-4" />
      </button>
    </div>
  );
}
