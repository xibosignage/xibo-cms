/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import { Lightbulb, PlaySquare, Smile } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

import HelpCard from './HelpCard';
import HelpGuides from './HelpGuides';
import HelpHeader from './HelpHeader';

import type { HelpLink } from '@/types/help';

interface HelpMainPanelProps {
  appName: string;
  isXiboThemed: boolean;
  links: HelpLink[];
  landingPage?: string;
  onOpenFeedback: () => void;
  extraResources?: ReactNode;
}

const TRAINING_URL = 'https://xibosignage.com/training';

export default function HelpMainPanel({
  appName,
  isXiboThemed,
  links,
  landingPage,
  onOpenFeedback,
  extraResources,
}: HelpMainPanelProps) {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col pb-2">
      <div className="py-2">
        <HelpCard
          icon={Lightbulb}
          title={t('Introduction to {{appName}}', { appName })}
          desc={t('Get to know your CMS')}
          to="/welcome"
        />
      </div>

      <HelpGuides links={links} landingPage={landingPage} isXiboThemed={isXiboThemed} />

      {(isXiboThemed || extraResources) && (
        <>
          <HelpHeader title={t('Resources')} />
          <div className="flex flex-col py-2">
            {isXiboThemed && (
              <HelpCard
                icon={Smile}
                title={t('Feedback')}
                desc={t('Help us improve')}
                onClick={onOpenFeedback}
              />
            )}
            {extraResources}
            {isXiboThemed && (
              <HelpCard
                icon={PlaySquare}
                title={t('Training')}
                desc={t('Access guides and tutorials')}
                href={TRAINING_URL}
                target="_blank"
              />
            )}
          </div>
        </>
      )}
    </div>
  );
}
