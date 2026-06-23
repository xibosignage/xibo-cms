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

import { useQueryClient } from '@tanstack/react-query';
import { isAxiosError } from 'axios';
import { ArrowLeft, CircleX } from 'lucide-react';
import { useRef, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router-dom';
import { twMerge } from 'tailwind-merge';

import { MODULE_TEMPLATE_TABS } from './ModuleTemplateEditConfig';
import CodeTab from './components/tabs/CodeTab';
import GeneralTab from './components/tabs/GeneralTab';
import PropertiesTab from './components/tabs/PropertiesTab';
import { useModuleTemplateEditData } from './hooks/useModuleTemplateEditData';
import { useModuleTemplateEditForm } from './hooks/useModuleTemplateEditForm';
import { moduleTemplateQueryKeys } from './hooks/useModuleTemplatesData';

import Button from '@/components/ui/Button';
import { notify } from '@/components/ui/Notification';
import SwitchRow from '@/pages/Administration/Users/components/SwitchRow';
import { editModuleTemplate } from '@/services/moduleTemplatesApi';

const CODE_TAB_CONFIG: Record<
  string,
  {
    field: 'twig' | 'hbs' | 'style' | 'head' | 'onTemplateRender' | 'onTemplateVisible';
    language: 'twig' | 'handlebars' | 'css' | 'html' | 'javascript';
  }
> = {
  Twig: { field: 'twig', language: 'twig' },
  HBS: { field: 'hbs', language: 'handlebars' },
  Style: { field: 'style', language: 'css' },
  Head: { field: 'head', language: 'html' },
  onTemplateRender: { field: 'onTemplateRender', language: 'javascript' },
  onTemplateVisible: { field: 'onTemplateVisible', language: 'javascript' },
};

export default function ModuleTemplateEdit() {
  const { t } = useTranslation();
  const { id } = useParams<{ id: string }>();
  const queryClient = useQueryClient();
  const numericId = parseInt(id ?? '', 10);

  const { template, dataTypes, isLoading, isError, error } = useModuleTemplateEditData(numericId);
  const { formValues, updateField, updateProperties, resetForm, markSaved, hasUnsavedChanges } =
    useModuleTemplateEditForm(template);

  const [activeTab, setActiveTab] = useState('General');
  const [isInvalidateWidget, setIsInvalidateWidget] = useState(true);
  const isInvalidateWidgetRef = useRef(true);
  isInvalidateWidgetRef.current = isInvalidateWidget;
  const [isPending, startTransition] = useTransition();
  const [saveError, setSaveError] = useState<string | null>(null);

  const handleSave = () => {
    if (!formValues) return;
    setSaveError(null);
    startTransition(async () => {
      try {
        await editModuleTemplate(numericId, {
          ...formValues,
          isInvalidateWidget: isInvalidateWidgetRef.current,
        });
        notify.success(t('Template saved'));
        markSaved();
        setIsInvalidateWidget(true);
        isInvalidateWidgetRef.current = true;
        queryClient.invalidateQueries({ queryKey: moduleTemplateQueryKeys.detail(numericId) });
        queryClient.invalidateQueries({ queryKey: moduleTemplateQueryKeys.all });
      } catch (err: unknown) {
        const message =
          (isAxiosError(err) && err.response?.data?.message) ||
          (err instanceof Error && err.message) ||
          t('Failed to save template');
        setSaveError(message);
      }
    });
  };

  if (isLoading) {
    return (
      <section className="flex h-full w-full min-h-0 items-center justify-center">
        <span className="text-gray-400 animate-pulse font-medium">{t('Loading...')}</span>
      </section>
    );
  }

  if (isError || !template) {
    return (
      <section className="flex h-full w-full min-h-0 items-center justify-center">
        <div className="bg-red-50 border border-red-200 text-red-800 p-4 rounded-lg">
          {error instanceof Error ? error.message : t('Failed to load template')}
        </div>
      </section>
    );
  }

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex items-center py-4">
          <Link
            to="/developer/template"
            className="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 font-medium transition-colors"
          >
            <ArrowLeft size={16} />
            {t('Back to Module Templates')}
          </Link>
        </div>

        <div className="flex-1 flex rounded-lg border border-gray-200 overflow-hidden min-h-0">
          {/* Left side-nav */}
          <div className="bg-gray-50 border-r border-gray-200 shrink-0">
            <div className="bg-white p-4 rounded-lg flex flex-col min-w-40">
              {MODULE_TEMPLATE_TABS.map((tab) => (
                <button
                  key={tab.key}
                  type="button"
                  className={twMerge(
                    'text-sm font-semibold px-3 py-2 text-left inline-flex items-center gap-x-2 whitespace-nowrap focus:outline-none transition-colors cursor-pointer rounded-md',
                    activeTab === tab.key
                      ? 'text-xibo-blue-600 bg-xibo-blue-50'
                      : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50',
                  )}
                  aria-current={activeTab === tab.key ? 'page' : undefined}
                  onClick={() => setActiveTab(tab.key)}
                >
                  {t(tab.labelKey)}
                </button>
              ))}
            </div>
          </div>

          {/* Right content panel */}
          <div className="flex-1 flex flex-col overflow-hidden min-h-0">
            <div className="flex-1 overflow-y-auto p-5 bg-gray-50">
              <h3 className="font-semibold text-xl mb-5">{t(activeTab)}</h3>

              <div className="flex flex-col gap-5">
                {formValues && (
                  <>
                    {activeTab === 'General' && (
                      <GeneralTab
                        formValues={formValues}
                        dataTypes={dataTypes}
                        updateField={updateField}
                        isInvalidateWidget={isInvalidateWidget}
                        onIsInvalidateWidgetChange={setIsInvalidateWidget}
                      />
                    )}

                    {activeTab === 'Properties' && (
                      <div className="bg-white rounded-lg p-5">
                        <PropertiesTab
                          properties={formValues.properties}
                          onChange={updateProperties}
                        />
                      </div>
                    )}

                    {Object.entries(CODE_TAB_CONFIG).map(([tabKey, config]) =>
                      activeTab === tabKey ? (
                        <div key={tabKey} className="bg-white rounded-lg p-5">
                          <CodeTab
                            value={formValues[config.field] ?? ''}
                            onChange={(v) => updateField(config.field, v)}
                            language={config.language}
                          />
                        </div>
                      ) : null,
                    )}

                    {activeTab !== 'General' && (
                      <div className="bg-white rounded-lg p-5">
                        <SwitchRow
                          title={t('Invalidate any widgets using this template')}
                          checked={isInvalidateWidget}
                          onChange={setIsInvalidateWidget}
                        />
                      </div>
                    )}
                  </>
                )}
              </div>
            </div>

            {/* Sticky footer */}
            <div className="p-5 bg-white flex items-center justify-between border-t border-gray-100 shrink-0">
              <div>
                {saveError && (
                  <div
                    className="bg-red-50 border border-red-200 text-sm text-red-800 rounded-lg p-3 flex items-center gap-3"
                    role="alert"
                  >
                    <CircleX size={18} className="shrink-0" />
                    <span className="font-semibold">{saveError}</span>
                  </div>
                )}
              </div>
              <div className="flex gap-x-3">
                <Button
                  variant="secondary"
                  onClick={resetForm}
                  disabled={(!hasUnsavedChanges && isInvalidateWidget) || isPending}
                >
                  {t('Cancel')}
                </Button>
                <Button
                  variant="primary"
                  onClick={handleSave}
                  disabled={isPending || (!hasUnsavedChanges && isInvalidateWidget)}
                >
                  {isPending ? t('Saving...') : t('Save')}
                </Button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
