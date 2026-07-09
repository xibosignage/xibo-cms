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

import type { TFunction } from 'i18next';
import { useEffect, useRef, useState } from 'react';

import { notify } from '@/components/ui/Notification';

export const MAX_FILES = 3;
export const MAX_FILE_SIZE = 15 * 1024 * 1024;
export const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'application/pdf', 'video/quicktime'];
export const ACCEPT_ATTR = '.jpg,.jpeg,.png,.mov,.pdf';

export interface Attachment {
  id: string;
  file: File;
  typeLabel: string;
  thumbUrl?: string;
}

function describe(file: File, t: TFunction): { typeLabel: string; isImage: boolean } {
  if (file.type.startsWith('image/')) {
    return { typeLabel: t('Image'), isImage: true };
  }
  if (file.type === 'application/pdf') {
    return { typeLabel: t('PDF'), isImage: false };
  }
  return { typeLabel: t('Video'), isImage: false };
}

export function useFeedbackAttachments(t: TFunction) {
  const [attachments, setAttachments] = useState<Attachment[]>([]);

  const urlsRef = useRef<string[]>([]);
  urlsRef.current = attachments.flatMap((a) => (a.thumbUrl ? [a.thumbUrl] : []));
  useEffect(() => () => urlsRef.current.forEach((url) => URL.revokeObjectURL(url)), []);

  const isMax = attachments.length >= MAX_FILES;

  const addFiles = (incoming: File[]) => {
    if (!incoming.length) {
      return;
    }

    setAttachments((current) => {
      if (current.length + incoming.length > MAX_FILES) {
        notify.error(t('Maximum file number exceeded'));
        return current;
      }

      const next = [...current];
      for (const file of incoming) {
        if (!ALLOWED_TYPES.includes(file.type)) {
          notify.error(`${file.name}: ${t('Invalid file type')}`);
          continue;
        }
        if (file.size > MAX_FILE_SIZE) {
          notify.error(`${file.name}: ${t('File too large')}`);
          continue;
        }
        if (next.some((a) => a.file.name === file.name && a.file.size === file.size)) {
          continue;
        }

        const { typeLabel, isImage } = describe(file, t);
        next.push({
          id: `${file.name}-${file.size}-${file.lastModified}`,
          file,
          typeLabel,
          thumbUrl: isImage ? URL.createObjectURL(file) : undefined,
        });
      }
      return next;
    });
  };

  const removeFile = (id: string) => {
    setAttachments((current) => {
      const target = current.find((a) => a.id === id);
      if (target?.thumbUrl) {
        URL.revokeObjectURL(target.thumbUrl);
      }
      return current.filter((a) => a.id !== id);
    });
  };

  return { attachments, addFiles, removeFile, isMax };
}
