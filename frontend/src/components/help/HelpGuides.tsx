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

import HelpHeader from './HelpHeader';

import type { HelpLink } from '@/types/help';

interface HelpGuidesProps {
  links: HelpLink[];
  landingPage?: string;
  isXiboThemed: boolean;
}

function GuideLink({ title, url, summary }: { title: string; url: string; summary?: string }) {
  return (
    <a
      href={url}
      target="_blank"
      rel="noopener noreferrer"
      className="block px-3 py-2 transition-colors hover:bg-gray-50 cursor-pointer"
    >
      <div className="text-sm font-semibold text-gray-800">{title}</div>
      {summary && (
        <div className="text-xs text-xibo-blue-600" dangerouslySetInnerHTML={{ __html: summary }} />
      )}
    </a>
  );
}

export default function HelpGuides({ links, landingPage, isXiboThemed }: HelpGuidesProps) {
  const { t } = useTranslation();

  const visibleLinks = links.filter((link) => link.isAllowWhiteLabel || isXiboThemed);

  if (!landingPage && visibleLinks.length === 0) {
    return null;
  }

  return (
    <div>
      <HelpHeader title={t('Guides')} />
      <div className="py-2">
        {visibleLinks.map((link) => (
          <GuideLink key={link.url} title={link.title} url={link.url} summary={link.summary} />
        ))}
        {landingPage && (
          <GuideLink
            title={t('User Manual')}
            summary={t('Visit the user manual')}
            url={landingPage}
          />
        )}
      </div>
    </div>
  );
}
