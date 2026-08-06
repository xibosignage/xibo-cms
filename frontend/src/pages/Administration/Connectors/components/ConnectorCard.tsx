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

import { CheckCircle, ExternalLink } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { connectorThumbnails } from '../connectorThumbnails';

import placeholderImg from '@/assets/connectors/placeholder.png';
import Button from '@/components/ui/Button';
import type { Connector } from '@/types/connector';

interface ConnectorCardProps {
  connector?: Connector;
  title?: string;
  description?: string;
  thumbnailSrc?: string;
  onConfigure?: () => void;
  externalLink?: { href: string; label: string };
}

export default function ConnectorCard({
  connector,
  title,
  description,
  thumbnailSrc,
  onConfigure,
  externalLink,
}: ConnectorCardProps) {
  const { t } = useTranslation();

  const cardTitle = connector?.title ?? title ?? '';
  const cardDescription = connector?.description ?? description ?? '';
  const imgSrc = connector
    ? (connectorThumbnails[connector.className] ?? placeholderImg)
    : (thumbnailSrc ?? '');

  return (
    <div className="flex flex-col h-81.5 justify-between relative bg-white rounded-xl p-5 pt-8 border border-slate-200 overflow-hidden hover:shadow-md transition-shadow">
      {connector && (
        <div className="absolute top-5 left-5">
          <div className="flex flex-col gap-1 shrink-0">
            {connector.isEnabled === 1 ? (
              <span className="inline-flex gap-px items-center rounded-full text-teal-800 bg-teal-100 border-transparent px-2 py-0.5 text-[10px] font-medium">
                <CheckCircle size={10} />
                <span className="px-0.5">{t('Enabled')}</span>
              </span>
            ) : (
              <span className="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium text-gray-800">
                {t('Disabled')}
              </span>
            )}
          </div>
        </div>
      )}

      <div className="flex flex-col gap-2 justify-center items-center">
        <div className="flex items-center justify-center bg-xibo-blue-100 rounded-full w-20 h-20">
          <img
            src={imgSrc}
            alt={cardTitle}
            className="max-h-full max-w-full object-contain"
            onError={(e) => {
              e.currentTarget.src = placeholderImg;
            }}
          />
        </div>

        <div className="flex justify-center">
          <h3 className="font-semibold text-gray-800">{cardTitle}</h3>
        </div>

        <p className="text-sm text-gray-500 leading-normal flex-1">{cardDescription}</p>
      </div>

      <div className="flex gap-3 justify-end">
        {externalLink ? (
          <a
            href={externalLink.href}
            target="_blank"
            rel="noreferrer"
            className="inline-flex items-center gap-1.5 text-sm font-normal text-xibo-blue-600 hover:underline"
          >
            {externalLink.label}
            <ExternalLink size={13} />
          </a>
        ) : (
          <Button variant="link" onClick={onConfigure} className="font-normal">
            {t('Configure')}
          </Button>
        )}
      </div>
    </div>
  );
}
