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

import type { Media } from '@/types/media';
import type { User } from '@/types/user';

type MediaInfoPanelProps = {
  open: boolean;
  onClose: () => void;
  mediaData: Media | null | undefined;
  owner: User | null;
  users?: User[];
  applyVersionTwo?: boolean;
  folderName?: string;
  loading: boolean;
};

function formatSeconds(seconds: number): string {
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = seconds % 60;

  return [h, m, s].map((v) => String(v).padStart(2, '0')).join(':');
}

export function MediaInfoPanel({
  open,
  onClose,
  mediaData,
  owner,
  users,
  applyVersionTwo = false,
  folderName,
  loading,
}: MediaInfoPanelProps) {
  const { t } = useTranslation();

  const renderDivider = () => (
    <hr
      className={twMerge('w-full  my-2', applyVersionTwo ? 'border-gray-200' : 'border-gray-700')}
    />
  );

  const titleTextVersion = applyVersionTwo
    ? 'uppercase font-semibold text-gray-500 text-xs leading-[1.5]'
    : 'uppercase font-semiboldtext-gray-400 text-xs leading-[1.5]';

  const textVersion = applyVersionTwo ? 'text-gray-800 text-xs' : 'text-gray-200 text-xs';

  return (
    <aside
      className={twMerge(
        applyVersionTwo
          ? 'bg-white border border-gray-200'
          : 'bg-gray-800 border-l border-gray-700 rounded-xl',

        'transition-[max-width, opacity] duration-300 ease-in-out overflow-hidden',
        open ? 'max-w-[248px] w-[248px] opacity-100' : 'max-w-0 opacity-0',
      )}
    >
      {/* Header */}
      <div
        className={`flex w-full justify-between items-center px-3 py-2  ${applyVersionTwo ? 'bg-gray-100 text-gray-500' : 'text-gray-400'}`}
      >
        <span className="uppercase font-semibold text-sm">{t('File Details')}</span>
        <button onClick={onClose} className="cursor-pointer rounded-lg">
          <X className="p-1 size-6" />
        </button>
      </div>
      {/* Content */}
      <div className="p-5 text-gray-400 gap-2 flex flex-col h-full">
        {mediaData && (
          <>
            {/* Owner */}
            <div className="flex flex-col">
              <span className={titleTextVersion}>{t('OWNER')}</span>
              <div className="flex gap-1 items-start">
                <div
                  className={twMerge(
                    ' uppercase h-[26px] w-[26px] text-[12px] center rounded-full font-semibold',
                    applyVersionTwo
                      ? 'bg-xibo-blue-100 text-xibo-blue-800'
                      : 'bg-xibo-blue-800/30 text-xibo-blue-500',
                  )}
                >
                  {owner?.userName.slice(0, 1)}
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
            {/* TODO: Add sharing real data */}
            <hr className="w-full border-gray-700 my-2 hidden" />
            <div className="hidden flex-col">
              <span className={titleTextVersion}>{t('SHARING')}</span>
              <div className="flex gap-1 items-start">
                {users?.map((user) => (
                  <div
                    className="h-[38px] w-[38px] center flex"
                    key={`${user.userName}-${user.userId}`}
                  >
                    <div className="bg-xibo-blue-300 h-[26px] w-[26px] text-[12px] center rounded-full text-xibo-blue-800 font-semibold">
                      {user.userName.slice(0, 1)}
                    </div>
                  </div>
                )) || '-'}
              </div>
            </div>
            {renderDivider()}
            <div className="flex flex-col gap-2">
              <div>
                <span className={titleTextVersion}>{t('LOCATION')}</span>
                <div
                  className={twMerge(
                    'flex gap-1 items-center',
                    textVersion,
                    applyVersionTwo ? 'bg-slate-50 p-1 w-fit rounded-xs' : '',
                  )}
                >
                  <Folder size={16} />
                  <span>{folderName ? folderName : '-'}</span>
                </div>
              </div>
              <div className="flex flex-col">
                <span className={titleTextVersion}>{t('TYPE')}</span>
                <span
                  className={twMerge(
                    'py-1 px-2 text-xs mt-1 rounded-lg uppercase block w-fit',
                    applyVersionTwo ? 'bg-red-100 text-red-800 ' : 'bg-red-500/30 text-red-500 ',
                  )}
                >
                  {mediaData.mediaType || '-'}
                </span>
              </div>
              <div className="flex flex-col">
                <span className={titleTextVersion}>{t('TAGS')}</span>
                <div className="flex gap-x-1">
                  {mediaData.tags.length > 0 ? (
                    mediaData.tags.map((tag) => (
                      <span className="py-1 px-2 border border-xibo-blue-600 text-xibo-blue-600 text-xs mt-1 rounded-lg block w-fit">
                        {tag.tag}
                      </span>
                    ))
                  ) : (
                    <span className={textVersion}>{t('NO TAGS')}</span>
                  )}
                </div>
              </div>
            </div>
            {renderDivider()}
            <div className="flex flex-col gap-2">
              <div>
                <span className={titleTextVersion}>{t('DURATION')}</span>
                <div className={textVersion}>{formatSeconds(mediaData.duration)}</div>
              </div>
              <div>
                <span className={titleTextVersion}>{t('SIZE')}</span>
                <div className={textVersion}>{mediaData.fileSizeFormatted || '-'}</div>
              </div>
              <div>
                <span className={titleTextVersion}>{t(`SIZE (Bytes)`)}</span>
                <div className={textVersion}>{mediaData.fileSize || '-'} Bytes</div>
              </div>
              <div>
                <span className={titleTextVersion}>{t(`RESOLUTION`)}</span>
                <div className={textVersion}>
                  {mediaData.width || '-'}x{mediaData.height || '-'}
                </div>
              </div>
            </div>
            {renderDivider()}
            <div className="flex flex-col gap-2">
              <div>
                <span className={titleTextVersion}>{t('FILE NAME')}</span>
                <div className={textVersion}>{mediaData.fileName || '-'}</div>
              </div>
              <div>
                <span className={titleTextVersion}>{t('CREATED')}</span>
                <div className={textVersion}>{mediaData.createdDt}</div>
              </div>
              <div>
                <span className={titleTextVersion}>{t('LAST MODIFIED')}</span>
                <div className={textVersion}>{mediaData.modifiedDt || '-'}</div>
              </div>
              <div>
                <span className={titleTextVersion}>{t('EXPIRY DATE')}</span>
                {/* TODO: Apply expiration date */}
                <div className={textVersion}>{mediaData.expires || '-'}</div>
              </div>
            </div>
          </>
        )}
      </div>
    </aside>
  );
}
