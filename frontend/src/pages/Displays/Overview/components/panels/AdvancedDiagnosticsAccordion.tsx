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

import {
  Clock,
  Cpu,
  Hash,
  Loader2,
  Maximize2,
  Network,
  ShieldCheck,
  Smartphone,
  Wrench,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { BandwidthPanel, DependenciesPanel, LayoutsPanel, WidgetsPanel } from './DiagnosticsPanels';
import { PanelField } from './PanelCard';

import Accordion from '@/components/ui/Accordion';
import Badge from '@/components/ui/Badge';
import { useUserContext } from '@/context/UserContext';
import { getClientTypeLabel } from '@/pages/Displays/Displays/DisplaysConfig';
import { useDisplayManageData } from '@/pages/Displays/Displays/hooks/useDisplayManageData';
import type { Display } from '@/types/display';
import { hasFeature } from '@/utils/permissions';

interface AdvancedDiagnosticsAccordionProps {
  display: Display;
}

// The device-info grid at the top of this accordion mirrors
// display-management.html's diag-grid exactly — it's the same "Status & Info"
// data that used to be its own always-visible panel, moved in here since the
// mock keeps it collapsed behind "Advanced diagnostics & device info" rather
// than surfaced by default.
export default function AdvancedDiagnosticsAccordion({
  display,
}: AdvancedDiagnosticsAccordionProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const showBandwidth = hasFeature(user, 'displays.reporting');
  const displayId = display.displayId;

  // Collapsed by default, and the (relatively heavy) diagnostics payload is
  // only fetched once the section is actually expanded.
  const [isOpen, setIsOpen] = useState(false);

  const { manageQuery } = useDisplayManageData(isOpen ? displayId : null);
  const data = manageQuery.data;
  const error = manageQuery.error instanceof Error ? manageQuery.error.message : null;

  const playerTypeModel =
    [getClientTypeLabel(t, display.clientType), display.model].filter(Boolean).join(' / ') || '-';
  const isAuthorised = display.licensed === 1;

  return (
    <Accordion
      title={t('Advanced Diagnostics')}
      icon={Wrench}
      open={isOpen}
      onOpenChange={setIsOpen}
    >
      <div className="p-4 flex flex-col gap-4">
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
          <PanelField icon={Hash} label={t('Display ID')} value={display.displayId} />
          <PanelField icon={Smartphone} label={t('Player Type / Model')} value={playerTypeModel} />
          <PanelField icon={Cpu} label={t('OS')} value={display.osVersion || '-'} />
          <PanelField icon={Network} label={t('IP Address')} value={display.clientAddress || '-'} />
          <PanelField icon={Maximize2} label={t('Resolution')} value={display.resolution || '-'} />
          <PanelField icon={Clock} label={t('Timezone')} value={display.timeZone || '-'} />
          <PanelField
            icon={ShieldCheck}
            label={t('Authorised')}
            value={
              <Badge type={isAuthorised ? 'success' : 'warning'} className="w-fit">
                {isAuthorised ? t('Authorised') : t('Unauthorised')}
              </Badge>
            }
          />
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
    </Accordion>
  );
}
