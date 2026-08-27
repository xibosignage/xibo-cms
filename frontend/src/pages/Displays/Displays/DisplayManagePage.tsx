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
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams } from 'react-router-dom';

import DisplayScreenshotPreviewer from './components/DisplayScreenshotPreviewer';
import ActiveFaultsPanel from './components/panels/ActiveFaultsPanel';
import HealthCheckCard from './components/panels/HealthCheckCard';
import ManageHeaderActions from './components/panels/ManageHeaderActions';
import ManagePageHeader from './components/panels/ManagePageHeader';
import ProofOfPlayModal from './components/panels/ProofOfPlayModal';
import ScreenshotCard from './components/panels/ScreenshotCard';
import TroubleshootingDiagnosticsModal from './components/panels/TroubleshootingDiagnosticsModal';
import { useManagePageActions } from './hooks/useManagePageActions';
import { useManagePageDisplay } from './hooks/useManagePageDisplay';

import Button from '@/components/ui/Button';
import { useUserContext } from '@/context/UserContext';
import { hasFeature } from '@/utils/permissions';

const DISPLAYS_PATH = '/displays/displays';

// The Manage page for a single display (`/displays/displays/:displayId`) — linkable/bookmarkable,
// so the display is re-fetched by ID here rather than carried over from the grid row.
export default function DisplayManagePage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { displayId: displayIdParam } = useParams<{ displayId: string }>();
  const displayId = Number(displayIdParam);
  const { user } = useUserContext();

  const {
    data: display,
    isLoading,
    isError,
  } = useManagePageDisplay(Number.isFinite(displayId) ? displayId : null);

  const { isClearingCache, isTogglingAuthorise, confirmPurgeAll, confirmToggleAuthorise } =
    useManagePageActions({ t });

  const [showScreenshots, setShowScreenshots] = useState(false);
  const [showProofOfPlay, setShowProofOfPlay] = useState(false);
  const [showDiagnostics, setShowDiagnostics] = useState(false);

  const handleBack = () => navigate(DISPLAYS_PATH);

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
          {t('Back to Displays')}
        </Button>
      </div>
    );
  }

  // Matches the Displays grid's own permission derivation exactly
  // (DisplaysConfig.tsx's getDisplayItemActions) — this page has zero
  // mutating actions today, so nothing to get wrong here previously, but
  // Request Screenshot and Clear Cache need the *same* gates the grid's
  // equivalent row actions use, not a simplified `edit`-only check:
  // "Clear cache" (purgeAll) is backend-enforced to super-admins only
  // (Display::purgeAll() in lib/Controller/Display.php), independent of
  // edit permission.
  const canModify = hasFeature(user, 'displays.modify');
  const canLimitedViewFeature = hasFeature(user, 'displays.limitedView');
  const canEditDisplay = !!display.userPermissions?.edit;
  const isSuperAdmin = user?.userTypeId === 1;
  const limitedEdit = canEditDisplay && (canModify || canLimitedViewFeature);

  const canClearCache = isSuperAdmin && limitedEdit;
  const canAuthorise = canModify && canEditDisplay;
  const canViewProofOfPlay = hasFeature(user, 'proof-of-play');

  return (
    <section className="flex h-full w-full min-h-0 flex-col overflow-y-auto">
      <ManagePageHeader
        display={display}
        onClose={handleBack}
        closeIcon={ArrowLeft}
        closeLabel={t('Back to Displays')}
        actions={
          <ManageHeaderActions
            onOpenProofOfPlay={() => setShowProofOfPlay(true)}
            canViewProofOfPlay={canViewProofOfPlay}
            onOpenDiagnostics={() => setShowDiagnostics(true)}
          />
        }
      />

      <div className="flex flex-col gap-6 p-6">
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
          <ScreenshotCard display={display} onOpen={() => setShowScreenshots(true)} />
          <HealthCheckCard
            display={display}
            onClearCache={() => confirmPurgeAll(display)}
            isClearingCache={isClearingCache}
            canClearCache={canClearCache}
            onToggleAuthorise={() => confirmToggleAuthorise(display)}
            isTogglingAuthorise={isTogglingAuthorise}
            canAuthorise={canAuthorise}
          />
        </div>

        <ActiveFaultsPanel displayId={display.displayId} />
      </div>

      {/* PARKED (screenshot history & interval): was ScreenshotGalleryModal, a grid of the last ten
          captures. With nothing recording history there is one screenshot to show, so this is the
          previewer for the current one. Swap the component back to restore the gallery. */}
      <DisplayScreenshotPreviewer
        display={showScreenshots ? display : null}
        onClose={() => setShowScreenshots(false)}
      />

      <ProofOfPlayModal
        display={display}
        isOpen={showProofOfPlay}
        onClose={() => setShowProofOfPlay(false)}
      />

      <TroubleshootingDiagnosticsModal
        display={display}
        isOpen={showDiagnostics}
        onClose={() => setShowDiagnostics(false)}
      />
    </section>
  );
}
