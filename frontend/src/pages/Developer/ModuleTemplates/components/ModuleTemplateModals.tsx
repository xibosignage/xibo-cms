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

import { useQuery } from '@tanstack/react-query';
import { isAxiosError } from 'axios';
import { Info, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Trans, useTranslation } from 'react-i18next';

import type { ModalType } from '../ModuleTemplatesConfig';
import { getShowInOptions } from '../ModuleTemplatesConfig';

import type { SelectOption } from '@/components/ui/forms/SelectDropdown';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import {
  addModuleTemplate,
  copyModuleTemplate,
  deleteModuleTemplate,
  fetchDataTypes,
  fetchTemplatesByDataType,
} from '@/services/moduleTemplatesApi';
import type { ModuleTemplate } from '@/types/moduleTemplates';

interface ModuleTemplateModalsProps {
  activeModal: ModalType;
  selectedTemplate: ModuleTemplate | null;
  onClose: () => void;
  onSuccess: () => void;
}

// ── Add Modal ────────────────────────────────────────────────────────────────

function AddModal({ onClose, onSuccess }: { onClose: () => void; onSuccess: () => void }) {
  const { t } = useTranslation();
  const [templateId, setTemplateId] = useState('custom_');
  const [title, setTitle] = useState('Custom Template');
  const [dataType, setDataType] = useState('');
  const [showIn, setShowIn] = useState('layout');
  const [copyTemplateId, setCopyTemplateId] = useState('');
  const [isPending, setIsPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const { data: dataTypes = [], isLoading: isLoadingDataTypes } = useQuery({
    queryKey: ['moduleTemplateDataTypes'],
    queryFn: fetchDataTypes,
    staleTime: Infinity,
  });

  const { data: copyTemplates = [], isLoading: isLoadingCopyTemplates } = useQuery({
    queryKey: ['moduleTemplateCopyOptions', dataType],
    queryFn: () => fetchTemplatesByDataType(dataType),
    enabled: !!dataType,
    staleTime: Infinity,
  });

  const dataTypeOptions: SelectOption[] = dataTypes.map((dt) => ({
    label: dt.name,
    value: dt.id,
  }));

  const copyTemplateOptions: SelectOption[] = copyTemplates.map((t) => ({
    label: t.title,
    value: t.templateId,
  }));

  const handleSave = async () => {
    setError(null);
    if (!templateId.trim()) {
      setError(t('Please supply a unique template ID'));
      return;
    }
    if (!title.trim()) {
      setError(t('Please supply a title'));
      return;
    }
    if (!dataType) {
      setError(t('Please supply a data type'));
      return;
    }
    if (!showIn) {
      setError(t('Please select relevant editor which should show this Template'));
      return;
    }
    setIsPending(true);
    try {
      await addModuleTemplate({
        templateId,
        title,
        dataType,
        showIn,
        copyTemplateId: copyTemplateId || undefined,
      });
      onSuccess();
    } catch (err: unknown) {
      setError(
        (isAxiosError(err) && err.response?.data?.message) ||
          (err instanceof Error && err.message) ||
          t('Failed to add template'),
      );
    } finally {
      setIsPending(false);
    }
  };

  return (
    <Modal
      isOpen
      onClose={onClose}
      title={t('Add Module Template')}
      error={error ?? undefined}
      isPending={isPending}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary' },
        { label: t('Save'), onClick: handleSave, variant: 'primary', disabled: isPending },
      ]}
    >
      <div className="flex flex-col gap-4 px-8 py-4">
        <TextInput
          name="templateId"
          label={t('ID')}
          helpText={t('A unique ID for the module template')}
          value={templateId}
          onChange={setTemplateId}
        />
        <TextInput
          name="title"
          label={t('Title')}
          helpText={t('A title for the module template')}
          value={title}
          onChange={setTitle}
        />
        <SelectDropdown
          label={t('Data Type')}
          value={dataType}
          options={dataTypeOptions}
          placeholder={isLoadingDataTypes ? t('Loading...') : t('Select data type...')}
          isLoading={isLoadingDataTypes}
          onSelect={(v: string) => {
            setDataType(v);
            setCopyTemplateId('');
          }}
        />
        {dataType && (
          <SelectDropdown
            label={t('Template')}
            value={copyTemplateId}
            options={copyTemplateOptions}
            placeholder={
              isLoadingCopyTemplates
                ? t('Loading...')
                : t('Optional — use existing template as base')
            }
            isLoading={isLoadingCopyTemplates}
            onSelect={setCopyTemplateId}
          />
        )}
        <SelectDropdown
          label={t('Show In')}
          value={showIn}
          options={getShowInOptions(t)}
          onSelect={setShowIn}
        />
      </div>
    </Modal>
  );
}

// ── Copy Modal ───────────────────────────────────────────────────────────────

function CopyModal({
  template,
  onClose,
  onSuccess,
}: {
  template: ModuleTemplate;
  onClose: () => void;
  onSuccess: () => void;
}) {
  const { t } = useTranslation();
  const [templateId, setTemplateId] = useState(`${template.templateId}_copy`);
  const [isPending, setIsPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleCopy = async () => {
    setError(null);
    if (!templateId.trim()) {
      setError(t('Please supply a unique template ID'));
      return;
    }
    setIsPending(true);
    try {
      await copyModuleTemplate(template.id, { templateId });
      onSuccess();
    } catch (err: unknown) {
      setError(
        (isAxiosError(err) && err.response?.data?.message) ||
          (err instanceof Error && err.message) ||
          t('Failed to copy template'),
      );
    } finally {
      setIsPending(false);
    }
  };

  return (
    <Modal
      isOpen
      onClose={onClose}
      title={t('Copy {{name}}', { name: template.templateId })}
      error={error ?? undefined}
      isPending={isPending}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary' },
        { label: t('Copy'), onClick: handleCopy, variant: 'primary', disabled: isPending },
      ]}
    >
      <div className="flex flex-col gap-4 px-8 py-4">
        <TextInput
          name="templateId"
          label={t('ID')}
          helpText={t('A unique ID for the module template')}
          value={templateId}
          onChange={setTemplateId}
        />
      </div>
    </Modal>
  );
}

// ── Delete Modal ─────────────────────────────────────────────────────────────

function DeleteModal({
  template,
  onClose,
  onSuccess,
}: {
  template: ModuleTemplate;
  onClose: () => void;
  onSuccess: () => void;
}) {
  const { t } = useTranslation();
  const [isPending, setIsPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleDelete = async () => {
    setError(null);
    setIsPending(true);
    try {
      await deleteModuleTemplate(template.id);
      onSuccess();
    } catch (err: unknown) {
      setError(
        (isAxiosError(err) && err.response?.data?.message) ||
          (err instanceof Error && err.message) ||
          t('Failed to delete template'),
      );
    } finally {
      setIsPending(false);
    }
  };

  return (
    <Modal
      isOpen
      onClose={onClose}
      isPending={isPending}
      variant="confirmation"
      size="md"
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary' },
        {
          label: isPending ? t('Deleting…') : t('Yes, Delete'),
          onClick: handleDelete,
          disabled: isPending,
        },
      ]}
    >
      <div className="flex flex-col p-5 gap-3">
        <div>
          <div className="flex justify-center mb-4">
            <div className="bg-red-100 w-15.5 h-15.5 text-red-800 border-red-50 border-[7px] rounded-full p-3">
              <Trash2 size={26} />
            </div>
          </div>
          <h2 className="text-center text-lg font-semibold mb-2 text-red-800">
            {t('Delete Template?')}
          </h2>
        </div>

        <p className="text-center text-gray-500">
          <Trans
            i18nKey='Are you sure you want to delete "<strong>{{name}}</strong>"?'
            values={{ name: template.templateId }}
            components={{ strong: <strong /> }}
          />
        </p>

        <span className="flex justify-center gap-px rounded-md bg-gray-50 p-1.5">
          <Info size={12} />
          <span className="text-[12px] px-1 font-medium text-center">
            {t('This template will be removed permanently. This cannot be undone.')}
          </span>
        </span>

        {error && (
          <div className="mt-2 text-center">
            <p className="text-sm font-medium text-red-600">{error}</p>
          </div>
        )}
      </div>
    </Modal>
  );
}

// ── Composite ────────────────────────────────────────────────────────────────

export function ModuleTemplateModals({
  activeModal,
  selectedTemplate,
  onClose,
  onSuccess,
}: ModuleTemplateModalsProps) {
  if (activeModal === 'add') {
    return <AddModal onClose={onClose} onSuccess={onSuccess} />;
  }

  if (!selectedTemplate) return null;

  if (activeModal === 'copy') {
    return <CopyModal template={selectedTemplate} onClose={onClose} onSuccess={onSuccess} />;
  }

  if (activeModal === 'delete') {
    return <DeleteModal template={selectedTemplate} onClose={onClose} onSuccess={onSuccess} />;
  }

  return null;
}
