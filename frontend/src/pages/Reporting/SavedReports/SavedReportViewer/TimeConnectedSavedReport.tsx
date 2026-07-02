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

import TimeConnectedRow from '@/pages/Reporting/Reports/TimeConnected/components/TimeConnectedRow';
import { transformData } from '@/pages/Reporting/Reports/TimeConnected/hooks/useTimeConnectedData';
import type { TimeConnectedTable } from '@/services/timeConnectedApi';

interface TimeConnectedSavedReportProps {
  table: TimeConnectedTable;
}

export default function TimeConnectedSavedReport({ table }: TimeConnectedSavedReportProps) {
  const { t } = useTranslation();
  const rows = transformData(table);

  if (rows.length === 0) {
    return (
      <div className="flex h-full min-h-40 items-center justify-center">
        <p className="text-gray-400 text-sm">{t('This report contains no data.')}</p>
      </div>
    );
  }

  return (
    <div className="flex flex-col divide-y divide-slate-100 rounded-lg overflow-hidden border border-slate-200">
      {rows.map((row) => (
        <TimeConnectedRow key={row.displayId} row={row} />
      ))}
    </div>
  );
}
