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

import { Clock, Cpu, Hash, Loader2, Maximize2, Network, Smartphone, Wrench } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { BandwidthPanel, DependenciesPanel, LayoutsPanel, WidgetsPanel } from './DiagnosticsPanels';
import { PanelCard, PanelField } from './PanelCard';
import TroubleshootingGuidePanel from './TroubleshootingGuidePanel';

import Modal from '@/components/ui/modals/Modal';
import { useUserContext } from '@/context/UserContext';
import { useDisplayManageData } from '@/pages/Displays/Displays/hooks/useDisplayManageData';
import type { Display } from '@/types/display';
import { hasFeature } from '@/utils/permissions';

interface TroubleshootingDiagnosticsModalProps {
  display: Display;
  isOpen: boolean;
  onClose: () => void;
}

// Combines what used to be two independent in-page accordions
// (AdvancedDiagnosticsAccordion.tsx's device-info grid + sub-panels, and
// TroubleshootingGuidePanel.tsx) into one modal, opened from the Manage
// page's header gear icon. Modal unmounts its children entirely while
// closed (see Modal.tsx's `if (!isOpen) return null`), which is what makes
// TroubleshootingGuidePanel's `defaultOpen` accordion re-evaluate fresh on
// every open — no extra remount-via-key trick needed.
export default function TroubleshootingDiagnosticsModal({
  display,
  isOpen,
  onClose,
}: TroubleshootingDiagnosticsModalProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const showBandwidth = hasFeature(user, 'displays.reporting');
  const displayId = display.displayId;

  // The (relatively heavy) diagnostics payload is only fetched once the
  // modal is actually open — mirrors the lazy-enable pattern this replaces.
  const { manageQuery } = useDisplayManageData(isOpen ? displayId : null);
  const data = manageQuery.data;
  const error = manageQuery.error instanceof Error ? manageQuery.error.message : null;

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={t('Troubleshooting & Diagnostics: {{name}}', { name: display.display })}
      size="xl"
      showCloseButton
      actions={[{ label: t('Close'), onClick: onClose, variant: 'secondary' }]}
    >
      <div className="flex flex-col gap-4 p-6">
        <PanelCard title={t('Display Info')} icon={Wrench}>
          <div className="p-4 flex flex-col gap-4">
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
              <PanelField icon={Hash} label={t('Display ID')} value={display.displayId} />
              <PanelField
                icon={Smartphone}
                label={t('Player Version')}
                value={display.clientVersion || '-'}
              />
              <PanelField icon={Cpu} label={t('OS')} value={display.osVersion || '-'} />
              <PanelField
                icon={Network}
                label={t('MAC Address')}
                value={display.macAddress || '-'}
              />
              <PanelField
                icon={Maximize2}
                label={t('Resolution')}
                value={display.resolution || '-'}
              />
              <PanelField icon={Clock} label={t('Timezone')} value={display.timeZone || '-'} />
            </div>

            {manageQuery.isLoading && (
              <div className="flex items-center justify-center py-8">
                <Loader2 className="w-6 h-6 animate-spin text-gray-400" />
              </div>
            )}

            {error && <p className="text-sm text-red-600">{error}</p>}

            {data && (
              <>
                <DependenciesPanel data={data.inventory?.dependencies ?? []} />
                <LayoutsPanel data={data.inventory?.layouts ?? []} />
                <WidgetsPanel data={data.inventory?.widgets ?? []} />
                {showBandwidth && <BandwidthPanel displayId={displayId} defaults={data.defaults} />}
              </>
            )}
          </div>
        </PanelCard>

        <TroubleshootingGuidePanel display={display} forceShow={false} />
      </div>
    </Modal>
  );
}
