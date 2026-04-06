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

import { X, Folder } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import { StatusCell } from '@/components/ui/table/cells';
import type { Layout } from '@/types/layout';
import type { User } from '@/types/user';

type LayoutInfoPanelProps = {
  isOpen?: boolean;
  onClose: () => void;
  layoutData: Layout | null | undefined;
  owner: User | null;
  folderName: string;
  loading: boolean;
  applyVersionTwo?: boolean;
};

function formatSeconds(seconds: number): string {
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = seconds % 60;

  return [h, m, s].map((v) => String(v).padStart(2, '0')).join(':');
}

export function LayoutInfoPanel({
  isOpen = true,
  onClose,
  layoutData,
  owner,
  folderName,
  loading,
  applyVersionTwo = false,
}: LayoutInfoPanelProps) {
  const { t } = useTranslation();

  const renderDivider = () => (
    <hr
      className={twMerge('w-full my-2', applyVersionTwo ? 'border-gray-200' : 'border-gray-700')}
    />
  );

  const titleTextVersion = applyVersionTwo
    ? 'uppercase font-semibold text-gray-500 text-xs leading-[1.5]'
    : 'uppercase font-semibold text-gray-400 text-xs leading-[1.5]';

  const textVersion = applyVersionTwo ? 'text-gray-800 text-xs' : 'text-gray-200 text-xs';

  return (
    <aside
      className={twMerge(
        applyVersionTwo
          ? 'bg-white border border-gray-200'
          : 'bg-gray-800 border-l border-gray-700 rounded-xl',

        'transition-[max-width,opacity] duration-300 ease-in-out overflow-hidden',
        isOpen ? 'max-w-62 w-62 opacity-100' : 'max-w-0 opacity-0',
      )}
    >
      {/* Header */}
      <div
        className={twMerge(
          'flex w-full justify-between items-center px-3 py-2',
          applyVersionTwo ? 'bg-gray-100 text-gray-500' : 'text-gray-400',
        )}
      >
        <span className="uppercase font-semibold text-sm">{t('Layout Details')}</span>
        <button onClick={onClose} className="cursor-pointer rounded-lg">
          <X className="p-1 size-6" />
        </button>
      </div>

      {/* Content */}
      <div className="p-5 gap-2 flex flex-col h-full">
        {layoutData && (
          <>
            {/* Owner */}
            <div className="flex flex-col">
              <span className={titleTextVersion}>{t('OWNER')}</span>

              <div className="flex gap-1 items-start">
                <div
                  className={twMerge(
                    'uppercase h-6.5 w-6.5 text-[12px] center rounded-full font-semibold',
                    applyVersionTwo
                      ? 'bg-xibo-blue-100 text-xibo-blue-800'
                      : 'bg-xibo-blue-800/30 text-xibo-blue-500',
                  )}
                >
                  {owner?.userName?.slice(0, 1) || '-'}
                </div>

                <div className={twMerge('flex flex-col', textVersion)}>
                  {loading ? (
                    <div className="flex flex-col gap-1 animate-pulse">
                      <div className="h-3.5 w-24 rounded bg-gray-300/60" />
                      <div className="h-3 w-36 rounded bg-gray-300/50" />
                    </div>
                  ) : (
                    <>
                      <span className="font-semibold text-[14px]">{owner?.userName || '-'}</span>
                      <span>{owner?.email || '-'}</span>
                    </>
                  )}
                </div>
              </div>
            </div>

            {renderDivider()}

            {/* Location */}
            <div className="flex flex-col">
              <span className={titleTextVersion}>{t('LOCATION')}</span>

              <div
                className={twMerge(
                  'flex gap-1 items-center',
                  textVersion,
                  applyVersionTwo ? 'bg-slate-50 p-1 w-fit rounded-xs' : '',
                )}
              >
                <Folder size={16} />
                <span>{folderName || '-'}</span>
              </div>
            </div>

            {/* Status */}
            <div className="flex flex-col">
              <span className={titleTextVersion}>{t('STATUS')}</span>

              <div>
                <StatusCell
                  label={layoutData.publishedStatus}
                  type={layoutData.publishedStatus === 'Published' ? 'success' : 'neutral'}
                />
              </div>
            </div>

            {/* Tags */}
            <div className="flex flex-col">
              <span className={titleTextVersion}>{t('TAGS')}</span>

              <div className="flex gap-x-1 flex-wrap">
                {layoutData.tags?.length > 0 ? (
                  layoutData.tags.map((tag) => (
                    <span
                      key={tag.tag}
                      className="py-1 px-2 border border-xibo-blue-600 text-xibo-blue-600 text-xs mt-1 rounded-lg"
                    >
                      {tag.tag}
                    </span>
                  ))
                ) : (
                  <span className={textVersion}>{t('NO TAGS')}</span>
                )}
              </div>
            </div>

            {renderDivider()}

            {/* Layout Properties */}
            <div className="flex flex-col gap-2">
              <div>
                <span className={titleTextVersion}>{t('DURATION')}</span>
                <div className={textVersion}>{formatSeconds(layoutData.duration)}</div>
              </div>

              <div>
                <span className={titleTextVersion}>{t('RESOLUTION')}</span>
                <div className={textVersion}>
                  {layoutData.width || '-'} x {layoutData.height || '-'}
                </div>
              </div>

              <div>
                <span className={titleTextVersion}>{t('ORIENTATION')}</span>
                <div className={textVersion}>{layoutData.orientation || '-'}</div>
              </div>
            </div>

            {renderDivider()}

            {/* Metadata */}
            <div className="flex flex-col gap-2">
              <div>
                <span className={titleTextVersion}>{t('NAME')}</span>
                <div className={textVersion}>{layoutData.layout || '-'}</div>
              </div>

              <div>
                <span className={titleTextVersion}>{t('DESCRIPTION')}</span>
                <div className={textVersion}>
                  {layoutData.description || (
                    <span className="text-gray-300">{t('No description')}</span>
                  )}
                </div>
              </div>

              <div>
                <span className={titleTextVersion}>{t('LAST MODIFIED')}</span>
                <div className={textVersion}>{layoutData.modifiedDt || '-'}</div>
              </div>
            </div>
          </>
        )}
      </div>
    </aside>
  );
}
