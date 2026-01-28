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

import { X, Loader2, Download, UserPlus2, Info, FolderInput } from 'lucide-react';
import { useEffect, useState, useRef } from 'react';
import { useTranslation } from 'react-i18next';

import { useKeydown } from '@/hooks/useKeydown';
import { fetchMediaBlob } from '@/services/mediaApi';
import type { MediaRow } from '@/types/media';

interface MediaPreviewerProps {
  mediaId: number | string | null;
  mediaType?: string | null;
  fileName?: string | undefined;
  onMove?: () => void;
  onShare?: () => void;
  onDownload: () => void;
  onClose: () => void;
  mediaData?: MediaRow | null;
}

export default function MediaPreviewer({
  mediaId,
  mediaType,
  fileName,
  onMove,
  onShare,
  onDownload,
  onClose,
  mediaData,
}: MediaPreviewerProps) {
  const { t } = useTranslation();

  const [url, setUrl] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [showInfoPanel, setShowInfoPanel] = useState(false);

  const activeUrlRef = useRef<string | null>(null);

  const revokeUrl = () => {
    if (activeUrlRef.current) {
      window.URL.revokeObjectURL(activeUrlRef.current);
      activeUrlRef.current = null;
    }
    setUrl(null);
  };

  useEffect(() => {
    if (!mediaId) {
      revokeUrl();
      setShowInfoPanel(false);
      return;
    }

    let isMounted = true;

    const load = async () => {
      revokeUrl();
      setLoading(true);
      setError(null);

      try {
        const rawBlob = await fetchMediaBlob(mediaId);

        if (!isMounted) return;

        let mimeType = rawBlob.type;

        if (mediaType === 'pdf') {
          mimeType = 'application/pdf';
        } else if (mediaType === 'video') {
          mimeType = 'video/mp4';
        }
        const finalBlob = new Blob([rawBlob], { type: mimeType });
        const objectUrl = window.URL.createObjectURL(finalBlob);
        activeUrlRef.current = objectUrl;
        setUrl(objectUrl);
      } catch (err) {
        if (isMounted) {
          console.error(err);
          setError(t('Failed to load media preview'));
        }
      } finally {
        if (isMounted) setLoading(false);
      }
    };

    load();

    return () => {
      isMounted = false;
      revokeUrl();
    };
  }, [mediaId, mediaType, t]);

  useKeydown('Escape', onClose, !!mediaId);

  if (!mediaId) {
    return null;
  }

  return (
    <div className="fixed inset-0 z-50 flex flex-col items-center justify-center bg-black/80">
      <div className="flex w-full px-5 py-3 text-white justify-between">
        <div className="flex w-full items-center gap-3">
          <button onClick={onClose} className="cursor-pointer rounded-lg hover:bg-white/10">
            <X className="p-1" />
          </button>
          <h3 className="font-semibold text-sm truncate">{fileName}</h3>
        </div>
        <div className="flex items-center gap-3">
          <button
            onClick={onMove}
            className="flex justify-center items-center cursor-pointer rounded-lg hover:bg-white/10"
            title={t('Move')}
          >
            <FolderInput className="p-1" />
          </button>
          <button
            onClick={onShare}
            className="flex justify-center items-center cursor-pointer rounded-lg hover:bg-white/10"
            title={t('Share')}
          >
            <UserPlus2 className="p-1" />
          </button>
          <button
            onClick={onDownload}
            className="flex justify-center items-center cursor-pointer rounded-lg hover:bg-white/10"
            title={t('Download')}
          >
            <Download className="p-1" />
          </button>
          <button
            onClick={() => setShowInfoPanel((prev) => !prev)}
            className="flex justify-center items-center cursor-pointer rounded-lg hover:bg-white/10"
            title={t('Details')}
          >
            <Info className="p-1" />
          </button>
        </div>
      </div>

      <div className="flex-1 w-full p-4 flex justify-center overflow-auto">
        {loading ? (
          <div className="flex flex-col items-center gap-2 text-gray-500">
            <Loader2 className="w-10 h-10 animate-spin" />
            <span>{t('Loading Media...')}</span>
          </div>
        ) : error ? (
          <div className="text-red-500 bg-red-50 p-4 rounded">{error}</div>
        ) : url ? (
          <>
            {mediaType === 'video' ? (
              <video src={url} controls className="max-h-full max-w-full shadow-md" autoPlay />
            ) : mediaType === 'image' ? (
              <img
                src={url}
                alt={fileName}
                className="max-h-full max-w-full object-contain shadow-md"
              />
            ) : mediaType === 'audio' ? (
              <div className="flex items-center align-middle justify-center w-2/3 max-w-xl h-full flex-col gap-4">
                <audio src={url} controls autoPlay className="w-full" />
              </div>
            ) : mediaType === 'pdf' ? (
              <iframe
                src={url}
                title={fileName}
                className="w-full h-full min-h-[600px] rounded shadow-md border-0"
              />
            ) : (
              <div className="text-center p-8 text-gray-500">
                <p>{t('Preview not available for this file type!')}</p>
              </div>
            )}
          </>
        ) : null}
      </div>

      {showInfoPanel && (
        <div className="absolute top-14 right-5 bottom-5 w-[248px] rounded-xl bg-gray-800 border border-gray-700">
          <div className="flex justify-between text-gray-400 items-center px-3 py-2">
            <span className="uppercase text-sm">{t('File Details')}</span>
            <button
              onClick={() => setShowInfoPanel(false)}
              className="cursor-pointer rounded-lg hover:bg-white/10"
            >
              <X className="p-1 size-6" />
            </button>
          </div>
          <div className="p-5 text-gray-400">
            {/* TODO: Add file info here */}

            <p className="mb-2 font-bold">TODO: Add file info here</p>

            {mediaData ? (
              <>
                <div className="flex gap-2">
                  <span className="text-sm uppercase text-gray-500">{t('Type')}</span>
                  <span className="text-sm text-gray-300">{mediaData.mediaType || '-'}</span>
                </div>

                <div className="flex gap-2">
                  <span className="text-sm uppercase text-gray-500">{t('Owner')}</span>
                  <span className="text-sm text-gray-300">{mediaData.owner || '-'}</span>
                </div>

                <div className="flex gap-2">
                  <span className="text-sm uppercase text-gray-500">{t('Created')}</span>{' '}
                  <span className="text-sm text-gray-300">{mediaData.createdDt || '-'}</span>
                </div>
              </>
            ) : (
              <p className="text-sm italic opacity-50">{t('No details available')}</p>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
