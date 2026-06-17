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

import { useQuery } from '@tanstack/react-query';
import { Loader2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Modal from '@/components/ui/modals/Modal';
import { fetchFontBlob, fetchFontDetails } from '@/services/fontApi';
import type { Font } from '@/types/font';
import { formatFileSize } from '@/utils/formatters';

interface FontDetailsModalProps {
  isOpen?: boolean;
  onClose: () => void;
  fontId: number;
  font: Font | null;
}

export default function FontDetailsModal({
  isOpen = true,
  onClose,
  fontId,
  font,
}: FontDetailsModalProps) {
  const { t } = useTranslation();
  const [fontFaceFamily, setFontFaceFamily] = useState<string | null>(null);
  const [isFontLoading, setIsFontLoading] = useState(false);
  const fontFaceRef = useRef<FontFace | null>(null);

  const { data: details, isLoading } = useQuery({
    queryKey: ['font', 'details', fontId],
    queryFn: () => fetchFontDetails(fontId),
    enabled: isOpen && fontId > 0,
  });

  useEffect(() => {
    if (!isOpen || !fontId) return;

    let blobUrl: string | null = null;
    const familyName = `xibo-preview-${fontId}`;

    setIsFontLoading(true);
    fetchFontBlob(fontId)
      .then((blob) => {
        blobUrl = URL.createObjectURL(blob);
        const fontFace = new FontFace(familyName, `url(${blobUrl})`);
        return fontFace.load();
      })
      .then((loadedFace) => {
        fontFaceRef.current = loadedFace;
        document.fonts.add(loadedFace);
        setFontFaceFamily(familyName);
      })
      .catch(() => {
        // Font preview not available
      })
      .finally(() => {
        setIsFontLoading(false);
      });

    return () => {
      if (fontFaceRef.current) {
        document.fonts.delete(fontFaceRef.current);
        fontFaceRef.current = null;
      }
      if (blobUrl) {
        URL.revokeObjectURL(blobUrl);
      }
      setFontFaceFamily(null);
    };
  }, [isOpen, fontId]);

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={font?.name ?? t('Font Details')}
      size="md"
      actions={[
        {
          label: t('Close'),
          onClick: onClose,
          variant: 'secondary',
        },
      ]}
    >
      <div className="p-6 flex flex-col gap-6">
        {isLoading ? (
          <div className="flex flex-col items-center justify-center py-8">
            <Loader2 className="h-5 w-5 text-gray-400 animate-spin" />
            <span className="text-xs text-gray-400">{t('Loading...')}</span>
          </div>
        ) : details ? (
          <>
            <div className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-3">
              {Object.entries(details).map(([key, value]) => (
                <div key={key} className="contents">
                  <dt className="text-sm font-medium text-gray-500 whitespace-nowrap">{key}</dt>
                  <dd className="text-sm text-gray-900">{value || '—'}</dd>
                </div>
              ))}
            </div>

            {font && (
              <div className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-3 border-t border-gray-200 pt-4">
                <dt className="text-sm font-medium text-gray-500">{t('File Name')}</dt>
                <dd className="text-sm text-gray-900">{font.fileName}</dd>
                <dt className="text-sm font-medium text-gray-500">{t('Size')}</dt>
                <dd className="text-sm text-gray-900">{formatFileSize(font.size)}</dd>
              </div>
            )}

            <div className="border-t border-gray-200 pt-4">
              <h3 className="text-sm font-medium text-gray-500 mb-3">{t('Preview')}</h3>
              {isFontLoading ? (
                <div className="flex flex-col items-center justify-center py-8">
                  <Loader2 className="h-5 w-5 text-gray-400 animate-spin" />
                  <span className="text-xs text-gray-400">{t('Loading...')}</span>
                </div>
              ) : fontFaceFamily ? (
                <div
                  className="p-4 bg-gray-50 rounded-lg border border-gray-200"
                  style={{ fontFamily: `'${fontFaceFamily}', sans-serif` }}
                >
                  <p className="text-[48px] mb-2">The quick brown fox jumps over the lazy dog</p>
                </div>
              ) : (
                <p className="text-sm text-gray-400 text-center py-4">
                  {t('Preview not available')}
                </p>
              )}
            </div>
          </>
        ) : (
          <p className="text-center text-gray-500 py-4">{t('Failed to load font details.')}</p>
        )}
      </div>
    </Modal>
  );
}
