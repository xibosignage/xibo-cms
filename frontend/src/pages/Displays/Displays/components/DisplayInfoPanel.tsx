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

import { X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import { fetchDisplayStatusWindow, type DisplayStatusWindow } from '@/services/displaysApi';
import type { Display } from '@/types/display';

type DisplayInfoPanelProps = {
  isOpen?: boolean;
  onClose: () => void;
  display: Display | null | undefined;
};

function formatTimestamp(ts: number | null): string {
  if (!ts) {
    return '-';
  }
  return new Date(ts * 1000).toLocaleString();
}

function StatusWindowContent({ data }: { data: DisplayStatusWindow }) {
  if (typeof data === 'string') {
    const text = data.replace(/<br\s*\/?>/gi, '\n');
    return (
      <pre className="text-gray-300 text-xs whitespace-pre-wrap wrap-break-word font-mono">
        {text}
      </pre>
    );
  }

  if (Array.isArray(data) || Object.keys(data).length === 0) {
    return null;
  }

  return (
    <ul className="flex flex-col gap-1">
      {Object.entries(data as Record<string, string | number>).map(([key, value]) => (
        <li key={key} className="flex flex-col">
          <span className="uppercase font-semibold text-gray-400 text-xs leading-snug">{key}</span>
          <span className="text-gray-200 text-xs wrap-break-word">{String(value)}</span>
        </li>
      ))}
    </ul>
  );
}

export default function DisplayInfoPanel({
  isOpen = true,
  onClose,
  display,
}: DisplayInfoPanelProps) {
  const { t } = useTranslation();

  const [statusWindow, setStatusWindow] = useState<DisplayStatusWindow | null>(null);

  useEffect(() => {
    if (!display || !isOpen) {
      return;
    }

    let isMounted = true;

    fetchDisplayStatusWindow(display.displayId)
      .then((data) => {
        if (isMounted) {
          setStatusWindow(data);
        }
      })
      .catch(() => {
        if (isMounted) {
          setStatusWindow(null);
        }
      });

    return () => {
      isMounted = false;
    };
  }, [display?.displayId, isOpen]);

  useEffect(() => {
    if (!display) {
      setStatusWindow(null);
    }
  }, [display]);

  const titleText = 'uppercase font-semibold text-gray-400 text-xs leading-normal';
  const valueText = 'text-gray-200 text-xs';

  const renderDivider = () => <hr className="w-full border-gray-700 my-2" />;

  const hasStatusData =
    statusWindow !== null &&
    !Array.isArray(statusWindow) &&
    (typeof statusWindow === 'string'
      ? statusWindow.trim().length > 0
      : Object.keys(statusWindow).length > 0);

  return (
    <aside
      className={twMerge(
        'bg-gray-800 border-l border-gray-700 rounded-xl flex flex-col',
        'transition-[max-width,opacity] duration-300 ease-in-out overflow-hidden',
        isOpen ? 'max-w-72 w-72 opacity-100' : 'max-w-0 opacity-0',
      )}
    >
      <div className="flex w-full justify-between items-center px-3 py-2 text-gray-400 shrink-0">
        <span className="uppercase font-semibold text-sm">{t('Display Details')}</span>
        <button onClick={onClose} className="cursor-pointer rounded-lg">
          <X className="p-1 size-6" />
        </button>
      </div>

      <div className="p-5 text-gray-400 gap-2 flex flex-col overflow-y-auto flex-1 min-h-0">
        {display && (
          <>
            <div className="flex flex-col gap-2">
              <div className="flex flex-col">
                <span className={titleText}>{t('STATUS')}</span>
                <div className="flex gap-2 mt-1">
                  <span
                    className={twMerge(
                      'py-1 px-2 text-xs rounded-lg uppercase block w-fit',
                      display.loggedIn
                        ? 'bg-green-500/30 text-green-400'
                        : 'bg-red-500/30 text-red-400',
                    )}
                  >
                    {display.loggedIn ? t('Online') : t('Offline')}
                  </span>
                  <span
                    className={twMerge(
                      'py-1 px-2 text-xs rounded-lg uppercase block w-fit',
                      display.licensed
                        ? 'bg-blue-500/30 text-blue-400'
                        : 'bg-yellow-500/30 text-yellow-400',
                    )}
                  >
                    {display.licensed ? t('Authorised') : t('Unauthorised')}
                  </span>
                </div>
              </div>
            </div>

            {renderDivider()}

            <div className="flex flex-col gap-2">
              <div className="flex flex-col">
                <span className={titleText}>{t('PLAYER TYPE')}</span>
                <span className={valueText}>{display.clientType ?? '-'}</span>
              </div>
              <div className="flex flex-col">
                <span className={titleText}>{t('VERSION')}</span>
                <span className={valueText}>{display.clientVersion ?? '-'}</span>
              </div>
              <div className="flex flex-col">
                <span className={titleText}>{t('IP ADDRESS')}</span>
                <span className={valueText}>{display.clientAddress ?? '-'}</span>
              </div>
            </div>

            {renderDivider()}

            <div className="flex flex-col gap-2">
              <div className="flex flex-col">
                <span className={titleText}>{t('CURRENT LAYOUT')}</span>
                <span className={valueText}>{display.currentLayout ?? '-'}</span>
              </div>
              <div className="flex flex-col">
                <span className={titleText}>{t('RESOLUTION')}</span>
                <span className={valueText}>{display.resolution ?? '-'}</span>
              </div>
              <div className="flex flex-col">
                <span className={titleText}>{t('ORIENTATION')}</span>
                <span className={valueText}>{display.orientation ?? '-'}</span>
              </div>
            </div>

            {renderDivider()}

            <div className="flex flex-col gap-2">
              <div className="flex flex-col">
                <span className={titleText}>{t('LAST ACCESSED')}</span>
                <span className={valueText}>{formatTimestamp(display.lastAccessed)}</span>
              </div>
              {display.tags.length > 0 && (
                <div className="flex flex-col">
                  <span className={titleText}>{t('TAGS')}</span>
                  <div className="flex flex-wrap gap-1 mt-1">
                    {display.tags.map((tag) => (
                      <span
                        key={tag.tag}
                        className="py-1 px-2 border border-xibo-blue-600 text-xibo-blue-400 text-xs rounded-lg block w-fit"
                      >
                        {tag.tag}
                      </span>
                    ))}
                  </div>
                </div>
              )}
            </div>

            {hasStatusData && (
              <>
                {renderDivider()}
                <div className="flex flex-col gap-2">
                  <span className={titleText}>{t('PLAYER STATUS')}</span>
                  <StatusWindowContent data={statusWindow!} />
                </div>
              </>
            )}
          </>
        )}
      </div>
    </aside>
  );
}
