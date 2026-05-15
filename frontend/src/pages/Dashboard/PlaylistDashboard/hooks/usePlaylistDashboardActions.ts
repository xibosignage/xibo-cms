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

import type { QueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { playlistDashboardQueryKeys } from './usePlaylistSpots';

import { notify } from '@/components/ui/Notification';
import { deleteSpotWidget, uploadSpotMedia } from '@/services/dashboardApi';
import type { PlaylistSpotsResponse, SpotWidget } from '@/types/dashboard';

export interface SpotUploadState {
  fileName: string;
  fileSize: number;
  progress: number;
  status: 'uploading' | 'completed' | 'error';
  error?: string;
  blobUrl?: string;
}

export function usePlaylistDashboardActions(queryClient: QueryClient, playlistId: number | null) {
  const { t } = useTranslation();
  const [uploadStates, setUploadStates] = useState<Map<number, SpotUploadState>>(new Map());
  const [isDeleting, setIsDeleting] = useState(false);

  // Revoke any active blob URLs on unmount to prevent memory leaks
  useEffect(() => {
    return () => {
      uploadStates.forEach((state) => {
        if (state.blobUrl) {
          URL.revokeObjectURL(state.blobUrl);
        }
      });
    };
  }, [uploadStates]);

  const invalidateSpots = () => {
    if (playlistId !== null) {
      queryClient.invalidateQueries({
        queryKey: playlistDashboardQueryKeys.spots(playlistId),
      });
    }
  };

  const updateSpot = (spotIndex: number, update: Partial<SpotUploadState>) => {
    setUploadStates((prev) => {
      const next = new Map(prev);
      const current = next.get(spotIndex);
      if (current) {
        next.set(spotIndex, { ...current, ...update });
      }
      return next;
    });
  };

  const clearUpload = (spotIndex: number) => {
    setUploadStates((prev) => {
      const next = new Map(prev);
      const current = next.get(spotIndex);
      if (current?.blobUrl) {
        URL.revokeObjectURL(current.blobUrl);
      }
      next.delete(spotIndex);
      return next;
    });
  };

  const startUpload = async (spotIndex: number, file: File, widget?: SpotWidget) => {
    if (playlistId === null) return;

    const blobUrl = file.type.startsWith('image/') ? URL.createObjectURL(file) : undefined;

    setUploadStates((prev) => {
      const next = new Map(prev);
      next.set(spotIndex, {
        fileName: file.name,
        fileSize: file.size,
        progress: 0,
        status: 'uploading',
        blobUrl,
      });
      return next;
    });

    try {
      const result = await uploadSpotMedia({
        file,
        playlistId,
        widgetId: widget?.widgetId,
        oldMediaId: widget?.mediaIds[0],
        onProgress: (progress) => updateSpot(spotIndex, { progress }),
      });

      const uploaded = result.files[0];
      if (uploaded?.error) {
        updateSpot(spotIndex, { status: 'error', error: uploaded.error });
        return;
      }

      updateSpot(spotIndex, { status: 'completed', progress: 100 });

      await queryClient.refetchQueries({
        queryKey: playlistDashboardQueryKeys.spots(playlistId),
      });
      clearUpload(spotIndex);
    } catch {
      updateSpot(spotIndex, { status: 'error', error: t('Upload failed') });
    }
  };

  const removeWidgetsFromCache = (widgetIds: number[]) => {
    if (playlistId === null) return;
    const queryKey = playlistDashboardQueryKeys.spots(playlistId);
    queryClient.setQueryData<PlaylistSpotsResponse>(queryKey, (old) => {
      if (!old) return old;
      return {
        ...old,
        playlist: {
          ...old.playlist,
          widgets: old.playlist.widgets.filter((w) => !widgetIds.includes(w.widgetId)),
        },
      };
    });
  };

  const handleDeleteWidget = async (widgetId: number, deleteMedia = false) => {
    setIsDeleting(true);
    removeWidgetsFromCache([widgetId]);
    try {
      await deleteSpotWidget(widgetId, deleteMedia);
      notify.success(t('Widget removed'));
      invalidateSpots();
    } catch {
      notify.error(t('Failed to remove widget'));
      invalidateSpots();
    } finally {
      setIsDeleting(false);
    }
  };

  const handleRemoveAll = async (widgets: SpotWidget[], deleteMedia = false) => {
    setIsDeleting(true);
    removeWidgetsFromCache(widgets.map((w) => w.widgetId));
    try {
      for (const widget of widgets) {
        await deleteSpotWidget(widget.widgetId, deleteMedia);
      }
      notify.success(t('All widgets removed'));
      invalidateSpots();
    } catch {
      notify.error(t('Failed to remove all widgets'));
      invalidateSpots();
    } finally {
      setIsDeleting(false);
    }
  };

  return {
    uploadStates,
    startUpload,
    clearUpload,
    handleDeleteWidget,
    handleRemoveAll,
    isDeleting,
  };
}
