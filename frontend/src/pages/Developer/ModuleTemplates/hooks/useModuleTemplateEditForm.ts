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

import { useEffect, useState } from 'react';

import type {
  ModuleTemplate,
  ModuleTemplateEditFormValues,
  ModuleTemplateProperty,
} from '@/types/moduleTemplates';

function buildInitialValues(template: ModuleTemplate): ModuleTemplateEditFormValues {
  return {
    templateId: template.templateId ?? '',
    title: template.title ?? '',
    dataType: template.dataType ?? '',
    showIn: template.showIn ?? 'both',
    enabled: template.isEnabled ?? true,
    twig: template.stencil?.twig ?? '',
    hbs: template.stencil?.hbs ?? '',
    style: template.stencil?.style ?? '',
    head: template.stencil?.head ?? '',
    onTemplateRender: template.onTemplateRender ?? '',
    onTemplateVisible: template.onTemplateVisible ?? '',
    properties: template.properties ?? [],
  };
}

export const useModuleTemplateEditForm = (template: ModuleTemplate | null) => {
  const [formValues, setFormValues] = useState<ModuleTemplateEditFormValues | null>(null);
  const [originalValues, setOriginalValues] = useState<ModuleTemplateEditFormValues | null>(null);

  useEffect(() => {
    if (template) {
      const initial = buildInitialValues(template);
      setFormValues(initial);
      setOriginalValues(initial);
    }
  }, [template?.id]);

  const updateField = <K extends keyof ModuleTemplateEditFormValues>(
    key: K,
    value: ModuleTemplateEditFormValues[K],
  ) => {
    setFormValues((prev) => (prev ? { ...prev, [key]: value } : prev));
  };

  const updateProperties = (properties: ModuleTemplateProperty[]) => {
    setFormValues((prev) => (prev ? { ...prev, properties } : prev));
  };

  const resetForm = () => {
    if (originalValues) setFormValues({ ...originalValues });
  };

  const markSaved = () => {
    if (formValues) setOriginalValues({ ...formValues });
  };

  const hasUnsavedChanges =
    formValues !== null &&
    originalValues !== null &&
    JSON.stringify(formValues) !== JSON.stringify(originalValues);

  return { formValues, updateField, updateProperties, resetForm, markSaved, hasUnsavedChanges };
};
