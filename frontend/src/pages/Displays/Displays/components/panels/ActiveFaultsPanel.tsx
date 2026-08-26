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

import { Loader2, OctagonAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { PanelCard, SimpleDataTable, type SimpleDataTableColumn } from './PanelCard';

import { useActivePlayerFaults } from '@/pages/Displays/Displays/hooks/useOverviewManageModalData';
import type { PlayerFault } from '@/types/displayManage';

interface ActiveFaultsPanelProps {
  displayId: number;
}

export default function ActiveFaultsPanel({ displayId }: ActiveFaultsPanelProps) {
  const { t } = useTranslation();
  const { data, isLoading, error } = useActivePlayerFaults(displayId);
  const faults = data ?? [];

  const columns: SimpleDataTableColumn<PlayerFault>[] = [
    { key: 'code', header: t('Code'), cellClassName: 'text-gray-700', cell: (fault) => fault.code },
    { key: 'reason', header: t('Reason'), cell: (fault) => fault.reason },
    { key: 'date', header: t('Date'), cell: (fault) => fault.incidentDt },
    { key: 'expires', header: t('Expires'), cell: (fault) => fault.expires || '-' },
  ];

  // Only take up space in the modal when there's actually something to
  // report — no active faults means nothing actionable to show here.
  if (!isLoading && !error && faults.length === 0) {
    return null;
  }

  return (
    <PanelCard title={t('Active Faults')} icon={OctagonAlert}>
      {isLoading ? (
        <div className="flex items-center justify-center py-6">
          <Loader2 className="w-5 h-5 animate-spin text-gray-400" />
        </div>
      ) : error ? (
        <p className="px-4 py-3 text-sm text-red-600">
          {error instanceof Error ? error.message : t('Unable to load faults')}
        </p>
      ) : (
        <SimpleDataTable columns={columns} rows={faults} rowKey={(fault) => fault.playerFaultId} />
      )}
    </PanelCard>
  );
}
