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

import { useTranslation } from 'react-i18next';

import EditorHost from '@/components/editor/EditorHost';

interface PlaylistEditorOverlayProps {
  playlistId: number;
  onClose: () => void;
}

export default function PlaylistEditorOverlay({ playlistId, onClose }: PlaylistEditorOverlayProps) {
  const { t } = useTranslation();

  return (
    <div className="fixed inset-0 z-50 bg-white/10">
      <EditorHost
        id={String(playlistId)}
        editorBasePath="/playlist/designer"
        stayPathPrefix="/playlist/designer"
        title={t('Playlist Editor')}
        onExit={onClose}
        readySelector="#playlist-editor"
      />
    </div>
  );
}
