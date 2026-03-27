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

import { Plus, Trash2 } from 'lucide-react';
import { useEffect, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '@/components/ui/Button';
import Checkbox from '@/components/ui/forms/Checkbox';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import TimePickerInput from '@/components/ui/forms/TimePickerInput';
import Modal from '@/components/ui/modals/Modal';
import { getDaypartSchema } from '@/schema/daypart';
import { updateDaypart, createDaypart } from '@/services/daypartApi';
import type { Daypart, DaypartException } from '@/types/daypart';

interface AddAndEditDaypartModalProps {
  type: 'add' | 'edit';
  openModal: boolean;
  data?: Daypart | null;
  onClose: () => void;
  onSave: (updated: Daypart) => void;
}

interface DaypartDraft {
  name: string;
  description: string;
  isRetired: boolean;
  startTime: string;
  endTime: string;
  exceptions: DaypartException[];
}

const DEFAULT_DRAFT: DaypartDraft = {
  name: '',
  description: '',
  isRetired: false,
  startTime: '',
  endTime: '',
  exceptions: [],
};

type DaypartFormErrors = Partial<
  Record<keyof Omit<DaypartDraft, 'exceptions' | 'isRetired'>, string>
>;

const createDraftFromData = (data?: Daypart | null): DaypartDraft => {
  if (!data) return { ...DEFAULT_DRAFT };
  return {
    name: data.name,
    description: data.description ?? '',
    isRetired: Boolean(data.isRetired),
    startTime: data.startTime,
    endTime: data.endTime,
    exceptions: data.exceptions ?? [],
  };
};

export default function AddAndEditDaypartModal({
  type,
  openModal,
  onClose,
  data,
  onSave,
}: AddAndEditDaypartModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [formErrors, setFormErrors] = useState<DaypartFormErrors>({});
  const [apiError, setApiError] = useState<string | undefined>();
  const [activeTab, setActiveTab] = useState<'general' | 'description' | 'exceptions'>('general');

  const [draft, setDraft] = useState<DaypartDraft>(() => createDraftFromData(data));

  const DAYS_OF_WEEK = [
    { value: 'Mon', label: t('Monday') },
    { value: 'Tue', label: t('Tuesday') },
    { value: 'Wed', label: t('Wednesday') },
    { value: 'Thu', label: t('Thursday') },
    { value: 'Fri', label: t('Friday') },
    { value: 'Sat', label: t('Saturday') },
    { value: 'Sun', label: t('Sunday') },
  ];

  useEffect(() => {
    if (openModal) {
      setDraft(createDraftFromData(data));
      setFormErrors({});
      setApiError(undefined);
      setActiveTab('general');
    }
  }, [data, openModal]);

  const updateDraftField = <K extends keyof DaypartDraft>(field: K, value: DaypartDraft[K]) => {
    setDraft((prev) => ({ ...prev, [field]: value }));
  };

  const addException = () => {
    setDraft((prev) => ({
      ...prev,
      exceptions: [...prev.exceptions, { day: '', start: '', end: '' }],
    }));
  };

  const removeException = (index: number) => {
    setDraft((prev) => ({
      ...prev,
      exceptions: prev.exceptions.filter((_, i) => i !== index),
    }));
  };

  const updateException = (index: number, field: keyof DaypartException, value: string) => {
    setDraft((prev) => {
      const updated = prev.exceptions.map((ex, i) =>
        i === index ? { ...ex, [field]: value } : ex,
      );
      return { ...prev, exceptions: updated };
    });
  };

  const handleSave = () => {
    startTransition(async () => {
      const schema = getDaypartSchema(t);
      const result = schema.safeParse(draft);
      if (!result.success) {
        setApiError(undefined);
        const fieldErrors = result.error.flatten().fieldErrors as Partial<
          Record<keyof DaypartDraft, string[]>
        >;
        const mappedErrors: DaypartFormErrors = {};

        Object.entries(fieldErrors).forEach(([key, errors]) => {
          if (errors?.[0]) {
            mappedErrors[key as keyof DaypartFormErrors] = errors[0];
          }
        });

        setFormErrors(mappedErrors);

        if (mappedErrors.name || mappedErrors.startTime || mappedErrors.endTime) {
          setActiveTab('general');
        }

        return;
      }

      setFormErrors({});
      try {
        const payload = {
          name: draft.name,
          description: draft.description || undefined,
          isRetired: draft.isRetired ? 1 : 0,
          startTime: draft.startTime,
          endTime: draft.endTime,
          exceptionDays: draft.exceptions.map((ex) => ex.day),
          exceptionStartTimes: draft.exceptions.map((ex) => ex.start),
          exceptionEndTimes: draft.exceptions.map((ex) => ex.end),
        };

        if (type === 'edit') {
          if (!data) {
            console.error('Daypart data is missing.');
            return;
          }

          const updatedDaypart = await updateDaypart(data.dayPartId, payload);
          onSave({ ...data, ...updatedDaypart });
        } else {
          const newDaypart = await createDaypart(payload);
          onSave(newDaypart);
        }

        onClose();
      } catch (err: unknown) {
        console.error('Failed to save daypart:', err);

        const apiError = err as { response?: { data?: { message?: string } } };

        if (apiError.response?.data?.message) {
          setApiError(apiError.response.data.message);
        } else if (err instanceof Error) {
          setApiError(err.message);
        } else {
          setApiError(t('An unexpected error occurred while saving the daypart.'));
        }
      }
    });
  };

  const getTabClass = (tabName: string) => {
    const isActive = activeTab === tabName;
    return `py-2 px-3 inline-flex items-center gap-2 border-b-2 text-sm font-semibold whitespace-nowrap focus:outline-none transition-all ${
      isActive
        ? 'border-blue-600 text-blue-500'
        : 'border-gray-200 text-gray-500 hover:text-blue-600'
    }`;
  };

  const modalTitle = type === 'add' ? t('Add Daypart') : t('Edit Daypart');

  return (
    <Modal
      title={modalTitle}
      onClose={onClose}
      isOpen={openModal}
      isPending={isPending}
      scrollable={false}
      error={apiError}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
          disabled: isPending,
        },
        {
          label: isPending ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isPending,
        },
      ]}
    >
      <div className="flex flex-col h-full overflow-hidden">
        <nav className="flex px-4 overflow-x-auto shrink-0" aria-label="Tabs">
          <button
            type="button"
            className={getTabClass('general')}
            onClick={() => setActiveTab('general')}
          >
            {t('General')}
          </button>
          <button
            type="button"
            className={getTabClass('description')}
            onClick={() => setActiveTab('description')}
          >
            {t('Description')}
          </button>
          <button
            type="button"
            className={getTabClass('exceptions')}
            onClick={() => setActiveTab('exceptions')}
          >
            {t('Exceptions')}
          </button>
        </nav>

        <div className="flex-1 overflow-y-auto py-4 px-8 space-y-5">
          {/* GENERAL TAB */}
          {activeTab === 'general' && (
            <div className="space-y-4">
              <TextInput
                name="name"
                label={t('Name')}
                placeholder={t('Enter name')}
                value={draft.name}
                onChange={(name) => updateDraftField('name', name)}
                error={formErrors.name}
              />

              <Checkbox
                id="isRetired"
                title={t('Retired')}
                label={t('Retire? It will no longer be visible when scheduling')}
                checked={draft.isRetired}
                onChange={(e) => updateDraftField('isRetired', e.target.checked)}
              />

              <TimePickerInput
                label={t('Start Time')}
                value={draft.startTime}
                onChange={(startTime) => updateDraftField('startTime', startTime)}
                error={formErrors.startTime}
              />

              <TimePickerInput
                label={t('End Time')}
                value={draft.endTime}
                onChange={(endTime) => updateDraftField('endTime', endTime)}
                error={formErrors.endTime}
              />

              {type === 'edit' && (
                <p className="text-sm text-gray-500">
                  {t(
                    'If this daypart is already in use, the events will be adjusted to use the new times provided. If used on a recurring event and that event has already recurred, the event will be split in two and the future event time adjusted.',
                  )}
                </p>
              )}
            </div>
          )}

          {/* DESCRIPTION TAB */}
          {activeTab === 'description' && (
            <div className="space-y-4">
              <TextInput
                name="description"
                label={t('Description')}
                placeholder={t('Enter description')}
                value={draft.description}
                onChange={(description) => updateDraftField('description', description)}
                error={formErrors.description}
                multiline
              />
            </div>
          )}

          {/* EXCEPTIONS TAB */}
          {activeTab === 'exceptions' && (
            <>
              <div className="space-y-4">
                <p className="text-sm text-gray-500">
                  {t(
                    'If there are any exceptions enter them below by selecting the Day from the list and entering a start/end time.',
                  )}
                </p>

                {draft.exceptions.map((ex, index) => {
                  const usedDays = new Set(
                    draft.exceptions
                      .filter((_, i) => i !== index)
                      .map((e) => e.day)
                      .filter(Boolean),
                  );
                  const dayOptions = DAYS_OF_WEEK.map((d) => ({
                    ...d,
                    disabled: usedDays.has(d.value),
                  }));
                  return (
                    <div key={index} className="flex justify-between gap-2 items-end">
                      <div className="flex-1">
                        <SelectDropdown
                          label={index === 0 ? t('Day') : ''}
                          value={ex.day}
                          options={dayOptions}
                          onSelect={(val) => updateException(index, 'day', val)}
                        />
                      </div>
                      <TimePickerInput
                        className="max-w-50"
                        label={index === 0 ? t('Start Time') : ''}
                        value={ex.start}
                        onChange={(val) => updateException(index, 'start', val)}
                      />
                      <TimePickerInput
                        className="max-w-50"
                        label={index === 0 ? t('End Time') : ''}
                        value={ex.end}
                        onChange={(val) => updateException(index, 'end', val)}
                      />
                      <Button
                        variant="secondary"
                        onClick={() => removeException(index)}
                        className="shrink-0 mb-0.5 text-red-500 hover:text-red-700"
                      >
                        <Trash2 size={16} />
                      </Button>
                    </div>
                  );
                })}
              </div>

              <Button
                variant="secondary"
                leftIcon={Plus}
                onClick={addException}
                disabled={draft.exceptions.filter((ex) => ex.day).length >= DAYS_OF_WEEK.length}
              >
                {t('Add Exception')}
              </Button>
            </>
          )}
        </div>
      </div>
    </Modal>
  );
}
