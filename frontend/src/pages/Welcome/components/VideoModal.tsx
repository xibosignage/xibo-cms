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

import { useState } from 'react';

import type { VideoLink } from '../WelcomeConfig';

import Modal from '@/components/ui/modals/Modal';

interface VideoModalProps {
  videos: VideoLink[];
  onClose: () => void;
}

export default function VideoModal({ videos, onClose }: VideoModalProps) {
  const [activeIndex, setActiveIndex] = useState(0);
  const activeVideo = videos[activeIndex];

  if (!activeVideo) return null;

  return (
    <Modal
      isOpen
      onClose={onClose}
      title={activeVideo.title}
      size="lg"
      showCloseButton
      closeOnOverlay
    >
      {/* Video */}
      <div className="aspect-video w-full mt-5 px-4 pb-4">
        <iframe
          key={activeVideo.id}
          src={`https://www.youtube.com/embed/${activeVideo.id}?autoplay=1`}
          title={activeVideo.title}
          className="h-full w-full"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
          allowFullScreen
        />
      </div>

      {/* Thumbnails — only if multiple videos */}
      {videos.length > 1 && (
        <div className="flex gap-3 p-4">
          {videos.map((video, index) => (
            <button
              key={video.id}
              type="button"
              onClick={() => setActiveIndex(index)}
              className={`w-50 cursor-pointer rounded border-2 p-2 text-left text-sm ${
                index === activeIndex
                  ? 'border-xibo-blue-500 bg-xibo-blue-50'
                  : 'border-gray-200 hover:border-gray-300'
              }`}
            >
              <img
                src={`https://img.youtube.com/vi/${video.id}/mqdefault.jpg`}
                alt={video.title}
                className="mb-1 w-full rounded"
              />
              <span className="line-clamp-2">{video.title}</span>
            </button>
          ))}
        </div>
      )}
    </Modal>
  );
}
