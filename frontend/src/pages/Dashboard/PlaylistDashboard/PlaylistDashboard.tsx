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

import { useQueryClient } from '@tanstack/react-query';
import { Info } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import DeleteSpotModal from './components/DeleteSpotModal';
import PlaylistDropdown from './components/PlaylistDropdown';
import SpotRow from './components/SpotRow';
import { usePlaylistDashboardActions } from './hooks/usePlaylistDashboardActions';
import { usePlaylistSpots } from './hooks/usePlaylistSpots';

import type { SpotWidget } from '@/types/dashboard';

export default function PlaylistDashboard() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();

  const [selectedPlaylistId, setSelectedPlaylistId] = useState<number | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<
    | {
        type: 'single';
        widget: SpotWidget;
      }
    | {
        type: 'all';
        widgets: SpotWidget[];
      }
    | null
  >(null);

  const { data, isLoading } = usePlaylistSpots(selectedPlaylistId);
  const { uploadStates, startUpload, handleDeleteWidget, handleRemoveAll, isDeleting } =
    usePlaylistDashboardActions(queryClient, selectedPlaylistId);

  const widgets = data?.playlist.widgets ?? [];
  const spotsFound = data?.spotsFound ?? 0;
  const availableSpots = spotsFound - widgets.length;

  const handleSelectFile = (spotIndex: number, file: File) => {
    const widget = widgets[spotIndex];
    startUpload(spotIndex, file, widget);
  };

  const isDynamic = data?.playlist.isDynamic === 1;

  const handleConfirmDelete = async (deleteMedia: boolean) => {
    if (!deleteTarget) return;
    if (deleteTarget.type === 'single') {
      await handleDeleteWidget(deleteTarget.widget.widgetId, deleteMedia);
    } else {
      await handleRemoveAll(deleteTarget.widgets, deleteMedia);
    }
    setDeleteTarget(null);
  };

  return (
    <section className="flex flex-col mx-auto w-203.75 gap-3 p-5">
      {/* Playlist Dropdown */}
      <PlaylistDropdown value={selectedPlaylistId} onSelect={setSelectedPlaylistId} />
      <h2 className="text-lg font-semibold text-gray-800">{t('Playlist Content')}</h2>
      <div className="flex gap-x-1 w-fit bg-gray-50 p-2">
        <p className="flex items-center gap-1.5 text-sm text-gray-800 text-[12px]">
          <Info className="h-4 w-4 shrink-0" />
          {t('Click')}
          <strong>{t('Select File')}</strong>
          {t('to fill an empty spot or select an existing spot to replace its media.')}{' '}
          <strong>{t('Maximum file size is 2GB.')}</strong>
        </p>
      </div>

      {selectedPlaylistId !== null && !isLoading && data && (
        <>
          <div className="flex items-center justify-between">
            <span className="text-sm font-semibold text-gray-700">
              {availableSpots}/{spotsFound} {t('Spots Available')}
            </span>
            {widgets.length > 0 && (
              <button
                type="button"
                onClick={() => setDeleteTarget({ type: 'all', widgets })}
                disabled={isDeleting}
                className="text-sm font-medium text-red-500 hover:text-red-700 disabled:opacity-50"
              >
                {t('Remove All')}
              </button>
            )}
          </div>
          {spotsFound <= 0 && (
            <div className="rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-700">
              {t('This Playlist does not have any Spots for you to manage. Please choose another.')}
            </div>
          )}

          {/* Spot rows */}
          <div className="flex flex-col gap-3">
            {Array.from({ length: spotsFound }, (_, i) => (
              <SpotRow
                key={i}
                spotIndex={i}
                widget={widgets[i]}
                uploadState={uploadStates.get(i)}
                onSelectFile={handleSelectFile}
                onDeleteWidget={(widgetId) => {
                  const widget = widgets.find((w) => w.widgetId === widgetId);
                  if (widget) setDeleteTarget({ type: 'single', widget });
                }}
              />
            ))}
          </div>
        </>
      )}

      {/* Loading state */}
      {selectedPlaylistId !== null && isLoading && (
        <div className="flex items-center justify-center py-10 text-gray-400">
          <span className="animate-pulse">{t('Loading...')}</span>
        </div>
      )}

      {deleteTarget && (
        <DeleteSpotModal
          onClose={() => setDeleteTarget(null)}
          onConfirm={handleConfirmDelete}
          isLoading={isDeleting}
          isRemoveAll={deleteTarget.type === 'all'}
          itemCount={deleteTarget.type === 'all' ? deleteTarget.widgets.length : 1}
          widgetName={deleteTarget.type === 'single' ? deleteTarget.widget.name : undefined}
          hasLibraryMedia={
            deleteTarget.type === 'single'
              ? deleteTarget.widget.regionSpecific === 0
              : deleteTarget.widgets.some((w) => w.regionSpecific === 0)
          }
          isDynamic={isDynamic}
        />
      )}
    </section>
  );
}
