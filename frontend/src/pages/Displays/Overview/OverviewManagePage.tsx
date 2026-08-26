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

import { ArrowLeft, Loader2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams } from 'react-router-dom';

import ProofOfPlayPlaceholderPanel from './components/ProofOfPlayPlaceholderPanel';
import ActiveFaultsPanel from './components/panels/ActiveFaultsPanel';
import AdvancedDiagnosticsAccordion from './components/panels/AdvancedDiagnosticsAccordion';
import ManageHeroStatus from './components/panels/ManageHeroStatus';
import ManageModalHeader from './components/panels/ManageModalHeader';
import QuickStatsRow from './components/panels/QuickStatsRow';
import TroubleshootingGuidePanel from './components/panels/TroubleshootingGuidePanel';
import { useOverviewDisplayDetail } from './hooks/useOverviewDisplayDetail';

import Button from '@/components/ui/Button';
import { useUserContext } from '@/context/UserContext';
import { hasFeature } from '@/utils/permissions';

const OVERVIEW_PATH = '/displays/overview';

/**
 * The Manage page for a single display (`/displays/overview/:displayId`) —
 * the same content that used to live in `OverviewManageModal.tsx`, now a
 * standalone, linkable/bookmarkable page rather than a modal reachable only
 * from the card grid. The display is re-fetched by ID here rather than
 * carried over from the grid row, since a direct navigation (refresh,
 * bookmark, shared link) won't have that row in memory.
 */
export default function OverviewManagePage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { displayId: displayIdParam } = useParams<{ displayId: string }>();
  const displayId = Number(displayIdParam);
  const { user } = useUserContext();
  const canViewProofOfPlay = hasFeature(user, 'proof-of-play');

  const {
    data: display,
    isLoading,
    isError,
  } = useOverviewDisplayDetail(Number.isFinite(displayId) ? displayId : null);

  const troubleshootingRef = useRef<HTMLDivElement>(null);
  const [forceShowGuide, setForceShowGuide] = useState(false);

  // Reset the "show the guide anyway" override if the :displayId in the URL
  // changes (e.g. navigating between displays without going back to the grid).
  useEffect(() => {
    setForceShowGuide(false);
  }, [displayId]);

  const handleBack = () => navigate(OVERVIEW_PATH);

  const handleProofOfPlay = () => {
    if (!display) {
      return;
    }
    navigate('/reporting/proof-of-play', { state: { displayId: display.displayId } });
  };

  const handleViewTroubleshooting = () => {
    setForceShowGuide(true);
    troubleshootingRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  if (isLoading) {
    return (
      <div className="flex h-full w-full items-center justify-center">
        <Loader2 className="size-8 animate-spin text-gray-400" />
      </div>
    );
  }

  if (isError || !display) {
    return (
      <div className="flex h-full w-full flex-col items-center justify-center gap-3">
        <p className="text-gray-500">{t('This display could not be found.')}</p>
        <Button variant="secondary" leftIcon={ArrowLeft} onClick={handleBack}>
          {t('Back to Overview')}
        </Button>
      </div>
    );
  }

  return (
    <section className="flex h-full w-full min-h-0 flex-col overflow-y-auto">
      <ManageModalHeader
        display={display}
        onClose={handleBack}
        closeIcon={ArrowLeft}
        closeLabel={t('Back to Overview')}
      />

      <div className="flex flex-col gap-6 p-6">
        {/* Same content order as the modal it replaced — see git history of
            OverviewManageModal.tsx for the original rationale. */}
        <ManageHeroStatus display={display} />
        <QuickStatsRow display={display} />
        <ActiveFaultsPanel displayId={display.displayId} />
        <ProofOfPlayPlaceholderPanel
          title={t('Proof of Play — Last 7 Days')}
          canViewProofOfPlay={canViewProofOfPlay}
          onViewReport={handleProofOfPlay}
          onViewTroubleshooting={handleViewTroubleshooting}
        />
        <div ref={troubleshootingRef}>
          <TroubleshootingGuidePanel display={display} forceShow={forceShowGuide} />
        </div>
        <AdvancedDiagnosticsAccordion display={display} />
      </div>
    </section>
  );
}
