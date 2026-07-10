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

import { Lightbulb, PlaySquare, Smile } from 'lucide-react';
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
}

const TRAINING_URL = 'https://xibosignage.com/training';

export default function HelpMainPanel({
  appName,
  isXiboThemed,
  links,
  landingPage,
  onOpenFeedback,
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

      {isXiboThemed && (
        <>
          <HelpHeader title={t('Resources')} />
          <div className="flex flex-col py-2">
            <HelpCard
              icon={Smile}
              title={t('Feedback')}
              desc={t('Help us improve')}
              onClick={onOpenFeedback}
            />
            <HelpCard
              icon={PlaySquare}
              title={t('Training')}
              desc={t('Access guides and tutorials')}
              href={TRAINING_URL}
              target="_blank"
            />
          </div>
        </>
      )}
    </div>
  );
}
