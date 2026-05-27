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

import Checkbox from '@/components/ui/forms/Checkbox';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import type { SelectOption } from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { getTaskSchema } from '@/schema/task';
import { createTask, fetchAvailableTasks, updateTask } from '@/services/taskApi';
import type { Task, TaskAvailable } from '@/types/task';

interface AddEditTaskModalProps {
  isOpen?: boolean;
  mode: 'add' | 'edit';
  task: Task | null;
  onClose: () => void;
  onSuccess: () => void;
}

interface TaskDraft {
  selectedFile: string;
  name: string;
  schedule: string;
  isActive: boolean;
  options: Record<string, string>;
}

const DEFAULT_DRAFT: TaskDraft = {
  selectedFile: '',
  name: '',
  schedule: '* * * * *',
  isActive: false,
  options: {},
};

type Tab = 'general' | 'options';

function tabClass(activeTab: Tab, tab: Tab): string {
  const isActive = activeTab === tab;
  return `py-2 px-3 inline-flex items-center gap-2 border-b-2 text-sm font-semibold whitespace-nowrap focus:outline-none transition-all 
          ${isActive ? 'border-blue-600 text-blue-500' : 'border-gray-200 text-gray-500 hover:text-blue-600'}`;
}

export default function AddEditTaskModal({
  isOpen = true,
  mode,
  task,
  onClose,
  onSuccess,
}: AddEditTaskModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [activeTab, setActiveTab] = useState<Tab>('general');
  const [apiError, setApiError] = useState<string | undefined>();
  const [fieldErrors, setFieldErrors] = useState<Record<string, string | undefined>>({});

  const [draft, setDraft] = useState<TaskDraft>({ ...DEFAULT_DRAFT });

  const [availableTasks, setAvailableTasks] = useState<TaskAvailable[]>([]);
  const [isLoadingTasks, setIsLoadingTasks] = useState(false);

  const isEdit = mode === 'edit';

  useEffect(() => {
    if (!isOpen || isEdit) return;

    setIsLoadingTasks(true);
    const controller = new AbortController();
    fetchAvailableTasks(controller.signal)
      .then((tasks) => {
        if (controller.signal.aborted) return;
        setAvailableTasks(tasks);
      })
      .catch(() => {})
      .finally(() => {
        if (!controller.signal.aborted) setIsLoadingTasks(false);
      });

    return () => controller.abort();
  }, [isOpen, isEdit]);

  useEffect(() => {
    if (!isOpen) return;

    setActiveTab('general');
    setApiError(undefined);
    setFieldErrors({});

    if (isEdit && task) {
      setDraft({
        selectedFile: '',
        name: task.name,
        schedule: task.schedule,
        isActive: !!task.isActive,
        options: task.options ?? {},
      });
    } else {
      setDraft({ ...DEFAULT_DRAFT });
    }
  }, [isOpen, isEdit, task?.taskId]);

  const taskOptions: SelectOption[] = availableTasks.map((t) => ({
    label: t.name,
    value: t.file,
  }));

  const handleSave = () => {
    setFieldErrors({});
    setApiError(undefined);

    startTransition(async () => {
      const schema = getTaskSchema(t);
      const parseData = isEdit
        ? { name: draft.name, schedule: draft.schedule }
        : { name: draft.name, file: draft.selectedFile, schedule: draft.schedule };
      const result = schema.safeParse(parseData);

      if (!result.success) {
        const errors = result.error.flatten().fieldErrors;
        setFieldErrors({
          name: errors.name?.[0],
          file: errors.file?.[0],
          schedule: errors.schedule?.[0],
        });
        if (isEdit) setActiveTab('general');
        return;
      }

      try {
        if (isEdit && task) {
          await updateTask(task.taskId, {
            name: draft.name,
            schedule: draft.schedule,
            isActive: draft.isActive ? 1 : 0,
            options: draft.options,
          });
        } else {
          await createTask({
            name: draft.name,
            file: draft.selectedFile,
            schedule: draft.schedule,
          });
        }
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

  const optionKeys = Object.keys(draft.options);

  return (
    <Modal
      title={isEdit ? t('Edit Task') : t('Add Task')}
      isOpen={isOpen}
      variant={isEdit ? 'tabbed' : 'standard'}
      onClose={onClose}
      isPending={isPending}
      error={apiError}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isPending },
        { label: isPending ? t('Saving…') : t('Save'), onClick: handleSave, disabled: isPending },
      ]}
    >
      {isEdit && (
        <nav className="flex px-6 pt-2">
          <button
            type="button"
            className={tabClass(activeTab, 'general')}
            onClick={() => setActiveTab('general')}
          >
            {t('General')}
          </button>
          <button
            type="button"
            className={tabClass(activeTab, 'options')}
            onClick={() => setActiveTab('options')}
          >
            {t('Options')}
          </button>
        </nav>
      )}

      <div className="flex flex-col gap-3 p-6">
        {/* Add mode: task selector */}
        {!isEdit && (
          <>
            <SelectDropdown
              label={t('Task')}
              options={taskOptions}
              value={draft.selectedFile}
              onSelect={(val) => setDraft((prev) => ({ ...prev, selectedFile: val }))}
              placeholder={t('Select a task...')}
              isLoading={isLoadingTasks}
              searchable
            />
            {fieldErrors.file && <p className="text-sm text-red-600 -mt-2">{fieldErrors.file}</p>}
          </>
        )}

        {/* General fields (always shown in add mode, tab-gated in edit mode) */}
        {(!isEdit || activeTab === 'general') && (
          <>
            <TextInput
              name="name"
              label={t('Name')}
              placeholder={t('Enter task name')}
              value={draft.name}
              onChange={(name) => setDraft((prev) => ({ ...prev, name }))}
              error={fieldErrors.name}
            />

            <TextInput
              name="schedule"
              label={t('Schedule')}
              placeholder={t('CRON expression')}
              helpText={t('The schedule for this task in CRON syntax')}
              value={draft.schedule}
              onChange={(schedule) => setDraft((prev) => ({ ...prev, schedule }))}
              error={fieldErrors.schedule}
            />

            {isEdit && (
              <Checkbox
                id="isActive"
                label={t('Active')}
                checked={draft.isActive}
                onChange={(e) => setDraft((prev) => ({ ...prev, isActive: e.target.checked }))}
              />
            )}
          </>
        )}

        {/* Options tab (edit mode only) */}
        {isEdit && activeTab === 'options' && (
          <>
            {optionKeys.length === 0 ? (
              <p className="text-sm text-gray-500">{t('This task has no configurable options.')}</p>
            ) : (
              optionKeys.map((key) => (
                <TextInput
                  key={key}
                  name={key}
                  label={key}
                  value={String(draft.options[key] ?? '')}
                  onChange={(value) =>
                    setDraft((prev) => ({
                      ...prev,
                      options: { ...prev.options, [key]: value },
                    }))
                  }
                />
              ))
            )}
          </>
        )}
      </div>
    </Modal>
  );
}
