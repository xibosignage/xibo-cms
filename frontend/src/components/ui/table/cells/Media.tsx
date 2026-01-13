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

import { Image as ImageIcon, Film, Music, FileText, Archive, File, Play } from 'lucide-react';
import { useState } from 'react';

interface MediaProps {
  id: number;
  thumb?: string;
  alt?: string;
  mediaType: 'image' | 'video' | 'audio' | 'pdf' | 'archive' | 'other';
}

export function Media({ id, thumb, alt, mediaType }: MediaProps) {
  const [hasError, setHasError] = useState(false);

  const handlePreview = (e: React.MouseEvent) => {
    e.stopPropagation();
    console.log(`Previewing ${mediaType}:`, id);
  };

  const isPlayable = mediaType === 'video' || mediaType === 'audio';
  const showThumbnail = thumb && !hasError;

  const baseClass =
    'relative group h-10 w-10 shrink-0 flex items-center justify-center bg-gray-50 text-gray-800 cursor-pointer overflow-hidden';

  const getIcon = () => {
    switch (mediaType) {
      case 'image':
        return <ImageIcon className="w-5 h-5" />;
      case 'video':
        return <Film className="w-5 h-5" />;
      case 'audio':
        return <Music className="w-5 h-5" />;
      case 'pdf':
        return <FileText className="w-5 h-5" />;
      case 'archive':
        return <Archive className="w-5 h-5" />;
      default:
        return <File className="w-5 h-5" />;
    }
  };

  return (
    <button type="button" className={baseClass} onClick={handlePreview}>
      {showThumbnail ? (
        <>
          <img
            src={thumb}
            alt={alt}
            className="h-full w-full object-cover"
            onError={() => setHasError(true)}
          />
          {isPlayable && (
            <div className="absolute flex items-center justify-center">
              <Play className="w-4 h-4 text-white fill-white" />
            </div>
          )}
        </>
      ) : (
        getIcon()
      )}
    </button>
  );
}
