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

import { isAxiosError } from 'axios';
import type { ReactNode } from 'react';
import { useEffect, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import { notify } from '@/components/ui/Notification';
import Checkbox from '@/components/ui/forms/Checkbox';
import DatePickerInput from '@/components/ui/forms/DatePickerInput';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import type { CreateReportSchedulePayload } from '@/services/reportScheduleApi';
import { createReportSchedule } from '@/services/reportScheduleApi';

const FREQUENCY_OPTIONS = [
  { value: 'daily', label: 'Daily' },
  { value: 'weekly', label: 'Weekly' },
  { value: 'monthly', label: 'Monthly' },
  { value: 'yearly', label: 'Yearly' },
];

export interface BaseScheduleDraft {
  name: string;
  filter: string;
  fromDt: string;
  toDt: string;
  sendEmail: boolean;
  nonusers: string;
}

const INITIAL_DRAFT: BaseScheduleDraft = {
  name: '',
  filter: 'daily',
  fromDt: '',
  toDt: '',
  sendEmail: false,
  nonusers: '',
};

interface ReportScheduleModalShellProps {
  isOpen: boolean;
  title: string;
  onClose: () => void;
  onSuccess: () => void;
  buildPayload: (draft: BaseScheduleDraft) => CreateReportSchedulePayload;
  canSave?: boolean;
  warning?: ReactNode;
  children?: ReactNode;
}

export default function ReportScheduleModalShell({
  isOpen,
  title,
  onClose,
  onSuccess,
  buildPayload,
  canSave = true,
  warning,
  children,
}: ReportScheduleModalShellProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [apiError, setApiError] = useState('');
  const [draft, setDraft] = useState<BaseScheduleDraft>(INITIAL_DRAFT);

  useEffect(() => {
    if (isOpen) {
      setDraft(INITIAL_DRAFT);
      setApiError('');
    }
  }, [isOpen]);

  const handleSave = () => {
    if (isPending) {
      return;
    }
    startTransition(async () => {
      try {
        await createReportSchedule(buildPayload(draft));
        notify.success(t('Report schedule created.'));
        onSuccess();
        onClose();
      } catch (err: unknown) {
        if (isAxiosError(err) && err.response?.data?.message) {
          setApiError(err.response.data.message);
        } else if (err instanceof Error) {
          setApiError(err.message);
        } else {
          setApiError(t('An unexpected error occurred.'));
        }
      }
    });
  };

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      variant="standard"
      isOpen={isOpen}
      title={title}
      onClose={onClose}
      size="md"
      isPending={isPending}
      error={apiError}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isPending },
        {
          label: isPending ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isPending || !draft.name.trim() || !canSave,
        },
      ]}
    >
      <div className="flex flex-col gap-4 p-8 pt-0">
        {warning}

        <TextInput
          name="name"
          label={t('Name')}
          placeholder=" "
          value={draft.name}
          onChange={(val) => setDraft((prev) => ({ ...prev, name: val }))}
          helpText={t('The name for this report schedule')}
        />

        <div className="flex flex-col gap-1">
          <label className="text-sm font-semibold text-gray-500 leading-5">{t('Frequency')}</label>
          <SelectDropdown
            value={draft.filter}
            options={FREQUENCY_OPTIONS.map((o) => ({ value: o.value, label: t(o.label) }))}
            onSelect={(val) => setDraft((prev) => ({ ...prev, filter: val }))}
          />
          <span className="text-xs text-gray-400">
            {t('Select how frequently you would like this report to run')}
          </span>
        </div>

        {children}

        <DatePickerInput
          label={t('Start Time')}
          value={draft.fromDt}
          onChange={(val) => setDraft((prev) => ({ ...prev, fromDt: val }))}
          showTimePicker
          helpText={t(
            'Set a future date and time to run this report. Leave blank to run from the next collection point.',
          )}
        />

        <DatePickerInput
          label={t('End Time')}
          value={draft.toDt}
          onChange={(val) => setDraft((prev) => ({ ...prev, toDt: val }))}
          showTimePicker
          helpText={t(
            'Set a future date and time to end the schedule. Leave blank to run indefinitely.',
          )}
        />

        <Checkbox
          id="sendEmail"
          label={t('Should an email be sent?')}
          checked={draft.sendEmail}
          onChange={(e) => setDraft((prev) => ({ ...prev, sendEmail: e.target.checked }))}
        />

        {draft.sendEmail && (
          <TextInput
            name="nonusers"
            label={t('Email addresses')}
            placeholder=" "
            value={draft.nonusers}
            onChange={(val) => setDraft((prev) => ({ ...prev, nonusers: val }))}
            helpText={t('Additional emails separated by a comma.')}
          />
        )}
      </div>
    </Modal>
  );
}
