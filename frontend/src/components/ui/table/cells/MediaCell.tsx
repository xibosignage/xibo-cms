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

import { Play } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { getMediaIcon } from '@/pages/Library/Media/MediaConfig';

interface MediaProps {
  thumb?: string;
  alt?: string;
  mediaType: 'image' | 'video' | 'audio' | 'pdf' | 'archive' | 'other';
  onPreview: () => void;
}

export function MediaCell({ thumb, alt, mediaType, onPreview }: MediaProps) {
  const { t } = useTranslation();
  const [hasError, setHasError] = useState(false);

  const handlePreview = (e: React.MouseEvent) => {
    e.stopPropagation();
    onPreview();
  };

  const isPlayable = mediaType === 'video' || mediaType === 'audio';
  const showThumbnail = thumb && !hasError;
  const Icon = getMediaIcon(mediaType);

  return (
    <div className="flex w-full justify-center items-center">
      <button
        type="button"
        title={t('Preview media')}
        aria-label={t('Preview media')}
        className="cursor-pointer rounded-sm w-16 h-[47px] bg-gray-400 hover:bg-gray-300 focus:bg-gray-300 overflow-hidden"
        onClick={handlePreview}
      >
        {showThumbnail ? (
          <div className="flex h-full justify-center items-center">
            <img
              src={thumb}
              alt={alt}
              className="h-full w-full object-contain"
              onError={() => setHasError(true)}
            />
            {isPlayable && (
              <div className="absolute flex items-center justify-center">
                <Play className="w-4 z-20 text-white fill-white" />
              </div>
            )}
          </div>
        ) : (
          <div className="flex justify-center items-center">
            <Icon className="size-6 text-gray-500" />
          </div>
        )}
      </button>
    </div>
  );
}
