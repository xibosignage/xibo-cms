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

import { Download } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import DateFilter from '@/components/ui/DateFilter';
import Modal from '@/components/ui/modals/Modal';
import { exportAuditTrail } from '@/services/auditTrailApi';

interface AuditTrailExportModalProps {
  onClose: () => void;
}

export default function AuditTrailExportModal({ onClose }: AuditTrailExportModalProps) {
  const { t } = useTranslation();
  const [filterFromDt, setFilterFromDt] = useState('');
  const [filterToDt, setFilterToDt] = useState('');
  const [isExporting, setIsExporting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleExport = async () => {
    if (!filterFromDt || !filterToDt) {
      setError(t('Please provide a from and to date.'));
      return;
    }

    setError(null);
    setIsExporting(true);
    try {
      await exportAuditTrail({
        filterFromDt: `${filterFromDt} 00:00:00`,
        filterToDt: `${filterToDt} 23:59:59`,
      });
      onClose();
    } catch {
      setError(t('Export failed. Please try again.'));
    } finally {
      setIsExporting(false);
    }
  };

  return (
    <Modal
      isOpen
      onClose={onClose}
      title={t('Output Audit Trail as CSV')}
      size="sm"
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
        },
        {
          label: isExporting ? t('Exporting…') : t('Export'),
          onClick: handleExport,
          disabled: isExporting,
          leftIcon: Download,
        },
      ]}
    >
      <div className="flex flex-col gap-4 py-6 px-8">
        <DateFilter
          label={t('From Date')}
          name="filterFromDt"
          value={filterFromDt}
          onChange={(_, val) => setFilterFromDt(val ?? '')}
          showTimePicker={false}
        />
        <DateFilter
          label={t('To Date')}
          name="filterToDt"
          value={filterToDt}
          onChange={(_, val) => setFilterToDt(val ?? '')}
          showTimePicker={false}
        />
        {error && <p className="text-sm text-red-600">{error}</p>}
      </div>
    </Modal>
  );
}
