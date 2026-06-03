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
import { useEffect, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import DatePickerInput from '@/components/ui/forms/DatePickerInput';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { updateReportSchedule } from '@/services/reportScheduleApi';
import type { ReportSchedule } from '@/types/reportSchedule';
import { formatDateTime } from '@/utils/date';

interface EditReportScheduleModalProps {
  isOpen?: boolean;
  schedule: ReportSchedule | null;
  onClose: () => void;
  onSuccess: () => void;
}

interface EditDraft {
  name: string;
  fromDt: string;
  toDt: string;
}

export default function EditReportScheduleModal({
  isOpen = true,
  schedule,
  onClose,
  onSuccess,
}: EditReportScheduleModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [apiError, setApiError] = useState('');

  const [draft, setDraft] = useState<EditDraft>({
    name: '',
    fromDt: '',
    toDt: '',
  });

  useEffect(() => {
    if (isOpen && schedule) {
      setDraft({
        name: schedule.name,
        fromDt: schedule.fromDt !== 0 ? formatDateTime(new Date(schedule.fromDt * 1000)) : '',
        toDt: schedule.toDt !== 0 ? formatDateTime(new Date(schedule.toDt * 1000)) : '',
      });
      setApiError('');
    }
  }, [isOpen, schedule]);

  const handleSave = () => {
    if (!schedule) {
      return;
    }

    startTransition(async () => {
      try {
        await updateReportSchedule(schedule.reportScheduleId, {
          name: draft.name,
          fromDt: draft.fromDt,
          toDt: draft.toDt,
        });
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

  if (!isOpen || !schedule) {
    return null;
  }

  return (
    <Modal
      variant="standard"
      isOpen={isOpen}
      title={t('Edit Report Schedule')}
      onClose={onClose}
      size="md"
      isPending={isPending}
      error={apiError}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isPending },
        {
          label: isPending ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isPending || !draft.name.trim(),
        },
      ]}
    >
      <div className="flex flex-col gap-4 p-5">
        <TextInput
          name="name"
          label={t('Schedule Name')}
          placeholder=" "
          value={draft.name}
          onChange={(val) => setDraft((prev) => ({ ...prev, name: val }))}
        />

        <DatePickerInput
          label={t('Start Time')}
          value={draft.fromDt}
          onChange={(val) => setDraft((prev) => ({ ...prev, fromDt: val }))}
          showTimePicker
          optional
          helpText={t(
            'Set a future date and time to run this report. Leave blank to run from the next collection point.',
          )}
        />

        <DatePickerInput
          label={t('End Time')}
          value={draft.toDt}
          onChange={(val) => setDraft((prev) => ({ ...prev, toDt: val }))}
          showTimePicker
          optional
          helpText={t(
            'Set a future date and time to end the schedule. Leave blank to run indefinitely.',
          )}
        />
      </div>
    </Modal>
  );
}
