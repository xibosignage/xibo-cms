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

import axios from 'axios';
import { Download, Loader2 } from 'lucide-react';
import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

import DatePickerInput from '@/components/ui/forms/DatePickerInput';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import Modal from '@/components/ui/modals/Modal';
import { fetchDisplays } from '@/services/displaysApi';

interface ExportStatisticsModalProps {
  isOpen: boolean;
  onClose: () => void;
}

const PAGE_SIZE = 20;

const toPhpDatetime = (iso: string): string =>
  new Date(iso).toISOString().slice(0, 19).replace('T', ' ');

export default function ExportStatisticsModal({ isOpen, onClose }: ExportStatisticsModalProps) {
  const { t } = useTranslation();

  const [fromDt, setFromDt] = useState('');
  const [toDt, setToDt] = useState('');
  const [displayId, setDisplayId] = useState('');
  const [isOutputUtc, setIsOutputUtc] = useState(true);

  const [displayOptions, setDisplayOptions] = useState<{ label: string; value: string }[]>([]);
  const [displaySearchTerm, setDisplaySearchTerm] = useState('');
  const [displayStart, setDisplayStart] = useState(0);
  const [displayHasMore, setDisplayHasMore] = useState(false);
  const [displayIsLoading, setDisplayIsLoading] = useState(false);
  const [displayIsLoadingMore, setDisplayIsLoadingMore] = useState(false);

  const [countLoading, setCountLoading] = useState(false);
  const [recordCount, setRecordCount] = useState<number | null>(null);
  const [countError, setCountError] = useState<string | null>(null);

  const [errors, setErrors] = useState<{ fromDt?: string; toDt?: string }>({});

  useEffect(() => {
    if (!isOpen) {
      return;
    }
    setFromDt('');
    setToDt('');
    setDisplayId('');
    setIsOutputUtc(true);
    setErrors({});
    setRecordCount(null);
    setCountError(null);
    const controller = new AbortController();
    void loadDisplays('', 0, false, controller.signal);
    return () => controller.abort();
  }, [isOpen]);

  async function loadDisplays(search: string, start: number, append: boolean, signal?: AbortSignal) {
    if (start === 0) {
      setDisplayIsLoading(true);
    } else {
      setDisplayIsLoadingMore(true);
    }
    try {
      const result = await fetchDisplays({ start, length: PAGE_SIZE, display: search, signal });
      const options = result.rows.map((d) => ({
        label: d.display,
        value: String(d.displayId),
      }));
      setDisplayOptions((prev) => (append ? [...prev, ...options] : options));
      const loaded = start + options.length;
      setDisplayHasMore(loaded < result.totalCount);
      setDisplayStart(loaded);
    } catch {
      // silently ignore load errors for display list
    } finally {
      setDisplayIsLoading(false);
      setDisplayIsLoadingMore(false);
    }
  }

  function handleDisplaySearch(term: string) {
    setDisplaySearchTerm(term);
    void loadDisplays(term, 0, false);
  }

  function handleDisplayLoadMore() {
    void loadDisplays(displaySearchTerm, displayStart, true);
  }

  useEffect(() => {
    if (!fromDt || !toDt) {
      setRecordCount(null);
      setCountError(null);
      return;
    }

    const controller = new AbortController();
    let cancelled = false;

    (async () => {
      setCountLoading(true);
      setRecordCount(null);
      setCountError(null);
      try {
        const params: Record<string, string> = {
          fromDt: toPhpDatetime(fromDt),
          toDt: toPhpDatetime(toDt),
        };
        if (displayId) {
          params.displayId = displayId;
        }
        const response = await axios.get<{ data: { total: number } }>(
          '/stats/getExportStatsCount',
          {
            params,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller.signal,
            withCredentials: true,
          },
        );
        if (!cancelled) {
          setRecordCount(response.data.data.total);
        }
      } catch (err) {
        if (!cancelled && !(err instanceof Error && err.name === 'CanceledError')) {
          setCountError(t('Failed to get record count.'));
        }
      } finally {
        if (!cancelled) {
          setCountLoading(false);
        }
      }
    })();

    return () => {
      cancelled = true;
      controller.abort();
    };
  }, [fromDt, toDt, displayId, t]);

  function handleExport() {
    const newErrors: { fromDt?: string; toDt?: string } = {};
    if (!fromDt) {
      newErrors.fromDt = t('This field is required.');
    }
    if (!toDt) {
      newErrors.toDt = t('This field is required.');
    }
    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      return;
    }
    setErrors({});

    const params = new URLSearchParams({
      fromDt: toPhpDatetime(fromDt),
      toDt: toPhpDatetime(toDt),
      isOutputUtc: isOutputUtc ? '1' : '0',
    });
    if (displayId) {
      params.set('displayId', displayId);
    }
    window.location.href = `/stats/export?${params.toString()}`;
    onClose();
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={t('Export Statistics')}
      size="sm"
      showCloseButton
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary' },
        {
          label: t('Export'),
          leftIcon: Download,
          onClick: handleExport,
          disabled: countLoading,
        },
      ]}
    >
      <div className="flex flex-col gap-5 px-8 py-6">
        <DatePickerInput
          label={t('From Date')}
          value={fromDt}
          onChange={(v) => {
            setFromDt(v);
            setErrors((e) => ({ ...e, fromDt: undefined }));
          }}
          error={errors.fromDt}
          showTimePicker={false}
        />
        <DatePickerInput
          label={t('To Date')}
          value={toDt}
          onChange={(v) => {
            setToDt(v);
            setErrors((e) => ({ ...e, toDt: undefined }));
          }}
          error={errors.toDt}
          showTimePicker={false}
        />
        <SelectDropdown
          label={t('Display')}
          value={displayId}
          placeholder={t('All Displays')}
          options={displayOptions}
          searchable
          clearable
          optional
          isLoading={displayIsLoading}
          onLoadMore={handleDisplayLoadMore}
          hasMore={displayHasMore}
          isLoadingMore={displayIsLoadingMore}
          onSearch={handleDisplaySearch}
          onSelect={setDisplayId}
        />
        <div className="flex items-start gap-3">
          <input
            type="checkbox"
            id="isOutputUtc"
            checked={isOutputUtc}
            onChange={(e) => setIsOutputUtc(e.target.checked)}
            className="mt-0.5 shrink-0 border-gray-200 rounded-sm cursor-pointer text-blue-600 focus:ring-blue-500 checked:border-blue-500"
          />
          <label htmlFor="isOutputUtc" className="text-sm text-gray-600 cursor-pointer">
            {t('Output dates as UTC? Leave unchecked for local CMS time.')}
          </label>
        </div>

        <div className="min-h-10">
          {countLoading && (
            <div className="flex items-center gap-2 text-sm text-gray-500">
              <Loader2 className="animate-spin" size={16} />
              <span>{t('Counting records...')}</span>
            </div>
          )}
          {!countLoading && recordCount !== null && (
            <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
              {t('Total number of records to be exported: ')}
              <strong>{recordCount}</strong>
            </div>
          )}
          {!countLoading && countError && (
            <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
              {countError}
            </div>
          )}
        </div>
      </div>
    </Modal>
  );
}
