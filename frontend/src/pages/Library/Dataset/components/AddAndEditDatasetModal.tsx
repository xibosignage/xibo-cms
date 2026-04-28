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

import { Check, Copy } from 'lucide-react';
import { useEffect, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import Modal from '../../../../components/ui/modals/Modal';

import Button from '@/components/ui/Button';
import Checkbox from '@/components/ui/forms/Checkbox';
import NumberInput from '@/components/ui/forms/NumberInput';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import TextInput from '@/components/ui/forms/TextInput';
import { usePermissions } from '@/hooks/usePermissions';
import { getDatasetSchema } from '@/schema/dataset';
import type { UpdateDatasetRequest } from '@/services/datasetApi';
import {
  updateDataset,
  createDataset,
  testRemoteDataset,
  fetchDataConnectorSource,
} from '@/services/datasetApi';
import type {
  Dataset,
  DatasetConnectorAuth,
  DatasetConnectorMethod,
  DatasetLimitPolicy,
  DatasetSummarize,
} from '@/types/dataset';

interface AddAndEditDatasetModalProps {
  type: 'add' | 'edit';
  isOpen?: boolean;
  data?: Dataset | null;
  dataConnectorSources?: { id: string; name: string }[];
  onClose: () => void;
  onSave: (updated: Dataset) => void;
}

type DatasetFormErrors = {
  dataSet?: string;
  description?: string;
  code?: string;
  uri?: string;
  username?: string;
};

const DEFAULT_DRAFT: UpdateDatasetRequest = {
  testDataSetId: 0,
  dataSet: '',
  description: '',
  code: '',
  isRemote: false,
  isRealTime: false,
  dataConnectorSource: '',
  method: 'GET',
  uri: '',
  postData: '',
  authentication: 'none',
  username: '',
  password: '',
  customHeaders: '',
  userAgent: '',
  sourceId: '1',
  dataRoot: '',
  csvSeparator: ',',
  ignoreFirstRow: false,
  summarize: 'none',
  summarizeField: '',
  refreshRate: 0,
  clearRate: 1,
  truncateOnEmpty: 0,
  runsAfter: 0,
  rowLimit: 0,
  limitPolicy: 'stop',
};

const createDraftFromData = (data?: Dataset | null): UpdateDatasetRequest => {
  if (!data) {
    return { ...DEFAULT_DRAFT };
  }

  return {
    testDataSetId: data.dataSetId,
    dataSet: data.dataSet ?? '',
    description: data.description ?? '',
    code: data.code ?? '',
    folderId: data.folderId ?? null,
    isRemote: Boolean(data.isRemote),
    isRealTime: Boolean(data.isRealTime),
    ignoreFirstRow: Boolean(data.ignoreFirstRow),
    truncateOnEmpty: data.truncateOnEmpty ? 1 : 0,
    dataConnectorSource: data.dataConnectorSource ?? '',
    method: (data.method || 'GET') as DatasetConnectorMethod,
    uri: data.uri ?? '',
    postData: data.postData ?? '',
    authentication: (data.authentication || 'none') as DatasetConnectorAuth,
    username: data.username ?? '',
    password: data.password ?? '',
    customHeaders: data.customHeaders ?? '',
    userAgent: data.userAgent ?? '',
    sourceId: data.sourceId ? (String(data.sourceId) as '1' | '2') : '1',
    dataRoot: data.dataRoot ?? '',
    csvSeparator: data.csvSeparator ?? ',',
    summarize: (data.summarize || 'none') as DatasetSummarize,
    summarizeField: data.summarizeField ?? '',
    limitPolicy: (data.limitPolicy || 'stop') as DatasetLimitPolicy,
    refreshRate: data.refreshRate ?? 0,
    clearRate: data.clearRate ?? 1,
    runsAfter: data.runsAfter ?? 0,
    rowLimit: data.rowLimit ?? 0,
  };
};

export default function AddAndEditDatasetModal({
  type,
  isOpen = true,
  onClose,
  data,
  onSave,
}: AddAndEditDatasetModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [apiError, setApiError] = useState<string | undefined>();
  const [formErrors, setFormErrors] = useState<DatasetFormErrors>({});
  const canViewFolders = usePermissions()?.canViewFolders;

  const [isTesting, startTestTransition] = useTransition();
  const [testResult, setTestResult] = useState<string>('');
  const [isCopied, setIsCopied] = useState(false);

  const [activeTab, setActiveTab] = useState<'general' | 'remote' | 'auth' | 'data' | 'advanced'>(
    'general',
  );
  const [draft, setDraft] = useState<UpdateDatasetRequest>(() => createDraftFromData(data));
  const [dataConnectorSources, setDataConnectorSources] = useState<{ id: string; name: string }[]>(
    [],
  );

  useEffect(() => {
    fetchDataConnectorSource()
      .then((sources) => {
        setDataConnectorSources(sources);
      })
      .catch((err) => {
        console.error('Failed to fetch data connector sources:', err);
      });
  }, [isOpen]);

  const handleTestRemoteData = () => {
    setTestResult(t('Testing...'));

    startTestTransition(async () => {
      try {
        const response = await testRemoteDataset(draft);
        let displayData = response.data.data;

        if (!response.data.success || !response.data.data?.entries) {
          displayData = response.data.message || response.data;
        }

        setTestResult(JSON.stringify(displayData, null, 3));
      } catch (err: unknown) {
        console.error('Remote test failed:', err);
        setTestResult(t('An error occurred while testing the remote dataset.'));
      }
    });
  };

  const handleCopy = async () => {
    if (!testResult) {
      return;
    }

    try {
      await navigator.clipboard.writeText(testResult);
      setIsCopied(true);

      setTimeout(() => setIsCopied(false), 1000);
    } catch (err) {
      console.error('Failed to copy text: ', err);
    }
  };

  useEffect(() => {
    if (isOpen) {
      setDraft(createDraftFromData(data));
      setApiError(undefined);
      setFormErrors({});
      setActiveTab('general');
    }
  }, [data, isOpen]);

  const handleSave = () => {
    const schema = getDatasetSchema(t);
    const result = schema.safeParse(draft);

    if (!result.success) {
      const fieldErrors = result.error.flatten().fieldErrors;

      setFormErrors({
        dataSet: fieldErrors.dataSet?.[0],
        description: fieldErrors.description?.[0],
        code: fieldErrors.code?.[0],
        uri: fieldErrors.uri?.[0],
        username: fieldErrors.username?.[0],
      });

      if (fieldErrors.dataSet || fieldErrors.description || fieldErrors.code) {
        setActiveTab('general');
      } else if (draft.isRemote && (fieldErrors.uri || fieldErrors.username)) {
        setActiveTab(fieldErrors.uri ? 'remote' : 'auth');
      }

      return;
    }

    setFormErrors({});
    setApiError(undefined);

    startTransition(async () => {
      try {
        if (type === 'edit') {
          if (!data) {
            return;
          }
          const updatedDataset = await updateDataset(data.dataSetId, draft);
          onSave({ ...data, ...updatedDataset });
        } else {
          const newDataset = await createDataset(draft);
          onSave(newDataset);
        }
        onClose();
      } catch (err: unknown) {
        console.error('Failed to save Dataset:', err);
        const apiError = err as { response?: { data?: { message?: string } } };
        setApiError(apiError.response?.data?.message || t('An unexpected error occurred.'));
      }
    });
  };

  const clearError = (field: keyof DatasetFormErrors) => {
    setFormErrors((prev) => ({ ...prev, [field]: undefined }));
  };

  const updateDraft = <K extends keyof UpdateDatasetRequest>(
    field: K,
    value: UpdateDatasetRequest[K],
  ) => {
    setDraft((prev) => ({ ...prev, [field]: value }));
  };

  const getTabClass = (tabName: string) => {
    const isActive = activeTab === tabName;
    return `py-2 px-3 inline-flex items-center gap-2 border-b-2 text-sm font-semibold whitespace-nowrap focus:outline-none transition-all ${
      isActive
        ? 'border-blue-600 text-blue-500'
        : 'border-gray-200 text-gray-500 hover:text-blue-600'
    }`;
  };

  const modalTitle = type === 'add' ? t('Add Dataset') : t('Edit Dataset');

  return (
    <Modal
      title={modalTitle}
      onClose={onClose}
      isOpen={isOpen}
      isPending={isPending}
      scrollable={false}
      error={apiError}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isPending },
        { label: isPending ? t('Saving…') : t('Save'), onClick: handleSave, disabled: isPending },
      ]}
    >
      <div className="flex flex-col h-full overflow-y-hidden overflow-x-visible px-4">
        <nav className="flex px-4 overflow-x-auto shrink-0" aria-label="Tabs">
          <button
            type="button"
            className={getTabClass('general')}
            onClick={() => setActiveTab('general')}
          >
            {t('General')}
          </button>
          {!!draft.isRemote && (
            <>
              <button
                type="button"
                className={getTabClass('remote')}
                onClick={() => setActiveTab('remote')}
              >
                {t('Remote')}
              </button>
              <button
                type="button"
                className={getTabClass('auth')}
                onClick={() => setActiveTab('auth')}
              >
                {t('Authentication')}
              </button>
              <button
                type="button"
                className={getTabClass('data')}
                onClick={() => setActiveTab('data')}
              >
                {t('Data')}
              </button>
              <button
                type="button"
                className={getTabClass('advanced')}
                onClick={() => setActiveTab('advanced')}
              >
                {t('Advanced')}
              </button>
            </>
          )}
        </nav>

        <div className="flex-1 overflow-y-auto p-4 space-y-5">
          {/* GENERAL TAB */}
          {activeTab === 'general' && (
            <div className="space-y-4">
              {canViewFolders && (
                <div className="relative z-20">
                  <SelectFolder
                    selectedId={draft.folderId}
                    onSelect={(f) => updateDraft('folderId', f?.id || null)}
                  />
                </div>
              )}

              <TextInput
                name="name"
                label={t('Name')}
                placeholder={t('Enter Name')}
                value={draft.dataSet}
                error={formErrors.dataSet}
                onChange={(val) => {
                  updateDraft('dataSet', val);
                  clearError('dataSet');
                }}
              />

              <TextInput
                name="code"
                label={t('Code')}
                error={formErrors.code}
                placeholder={t('Enter Code')}
                value={draft.code}
                onChange={(val) => {
                  updateDraft('code', val);
                  clearError('code');
                }}
              />

              <TextInput
                name="description"
                label={t('Description')}
                placeholder={t('Enter a short description')}
                helpText={t('Up to 250 characters.')}
                value={draft.description}
                error={formErrors.description}
                multiline={true}
                onChange={(val) => {
                  updateDraft('description', val);
                  clearError('description');
                }}
              />

              <div className="flex flex-col gap-3 pt-2 p-4 rounded-lg">
                <Checkbox
                  id="isRemote"
                  title={t('Remote')}
                  label={t('Connect this DataSet to a remote data source.')}
                  checked={draft.isRemote}
                  onChange={(e) => {
                    updateDraft('isRemote', e.target.checked);
                    if (!e.target.checked) {
                      setActiveTab('general');
                    }
                  }}
                />
              </div>

              <div className="flex flex-col gap-3 pt-2  p-4 rounded-lg">
                <Checkbox
                  id="isRealTime"
                  title={t('Real-time')}
                  label={t('Enable live data streaming for this source.')}
                  checked={draft.isRealTime}
                  onChange={(e) => updateDraft('isRealTime', e.target.checked)}
                />
                {!!draft.isRealTime && (
                  <div className="pt-2 animate-in fade-in slide-in-from-top-1 duration-200">
                    <SelectDropdown
                      label={t('Data Connector Source')}
                      value={draft.dataConnectorSource}
                      options={dataConnectorSources.map((source) => ({
                        label: source.name,
                        value: String(source.id),
                      }))}
                      onSelect={(val) => {
                        updateDraft('dataConnectorSource', val);
                      }}
                      helpText={t('Select data connector source.')}
                    />
                  </div>
                )}

                {type === 'edit' && data && (
                  <div className="flex flex-col gap-3 pt-4">
                    {draft.isRemote
                      ? data.columns &&
                        data.columns.filter((col) => String(col.dataSetColumnTypeId) === '3')
                          .length === 0 && (
                          <div
                            className="p-4 text-sm text-yellow-800 rounded-lg bg-yellow-50"
                            role="alert"
                          >
                            {t(
                              'No remote columns have been configured for this dataset. Please configure your columns accordingly.',
                            )}
                          </div>
                        )
                      : data.columns &&
                        data.columns.filter((col) => String(col.dataSetColumnTypeId) === '1')
                          .length === 0 && (
                          <div
                            className="p-4 text-sm text-yellow-800 rounded-lg bg-yellow-50"
                            role="alert"
                          >
                            {t(
                              'No value columns have been configured for this dataset. Please configure your columns accordingly.',
                            )}
                          </div>
                        )}

                    {data.isActive && (
                      <div className="p-4 text-sm text-teal-800 rounded-lg bg-teal-50" role="alert">
                        {t(
                          'This DataSet has been accessed or updated recently, which means the CMS will keep it active.',
                        )}
                      </div>
                    )}
                  </div>
                )}
              </div>
            </div>
          )}

          {/* REMOTE TAB */}
          {activeTab === 'remote' && draft.isRemote && (
            <div className="space-y-4">
              <SelectDropdown
                label={t('Method')}
                value={draft.method}
                options={[
                  { label: 'GET', value: 'GET' },
                  { label: 'POST', value: 'POST' },
                ]}
                helpText={t('Select type of request needs to be made to get the remote data.')}
                onSelect={(val) => {
                  updateDraft('method', val as DatasetConnectorMethod);
                }}
              />

              <TextInput
                name="uri"
                label={t('URI')}
                placeholder={t('Enter URI of the Remote Dataset')}
                value={draft.uri}
                error={formErrors.uri}
                onChange={(val) => {
                  updateDraft('uri', val);
                  clearError('uri');
                }}
              />

              <TextInput
                name="postData"
                label={t('Replacements')}
                placeholder={t('Enter replacements')}
                helpText={t(
                  'Request date: {{DATE}}\nRequest time: {{TIME}}\nDependant fields: {{COL.NAME}} where NAME is a FieldName from the dependant DataSet\nData to add to this request. This should be URL encoded, e.g. paramA=1&paramB=2.',
                )}
                value={draft.postData}
                multiline
                rows={4}
                onChange={(val) => updateDraft('postData', val)}
              />
            </div>
          )}

          {/* AUTHENTICATION TAB */}
          {activeTab === 'auth' && draft.isRemote && (
            <div className="space-y-4">
              <SelectDropdown
                label={t('Authentication')}
                helpText={t(
                  'Select the authentication requirements for the remote data source. These will be added to the request.',
                )}
                value={draft.authentication}
                options={[
                  { label: t('None'), value: 'none' },
                  { label: t('Basic'), value: 'basic' },
                  { label: t('Digest'), value: 'digest' },
                  { label: t('NTLM'), value: 'ntlm' },
                  { label: t('Bearer'), value: 'bearer' },
                ]}
                onSelect={(val) => {
                  updateDraft('authentication', val as DatasetConnectorAuth);
                }}
              />

              {draft.authentication !== 'none' && draft.authentication !== 'bearer' && (
                <TextInput
                  name="username"
                  label={t('Username')}
                  helpText={t('Enter the authentication Username')}
                  value={draft.username}
                  error={formErrors.username}
                  onChange={(val) => {
                    updateDraft('username', val);
                    clearError('username');
                  }}
                />
              )}

              {draft.authentication !== 'none' && (
                <TextInput
                  name="password"
                  label={t('Password')}
                  helpText={t('Corresponding Password')}
                  type="password"
                  value={draft.password}
                  onChange={(val) => updateDraft('password', val)}
                />
              )}

              <TextInput
                name="customHeaders"
                label={t('Custom Headers')}
                helpText={t(
                  'Comma separated string of custom HTTP headers in headerName:headerValue format.',
                )}
                placeholder={t('Enter Custom Headers')}
                value={draft.customHeaders}
                onChange={(val) => updateDraft('customHeaders', val)}
              />

              <TextInput
                name="userAgent"
                label={t('User Agent')}
                helpText={t('Provide only the value, relevant header will be added automatically.')}
                placeholder={t('Enter User Agent')}
                value={draft.userAgent}
                onChange={(val) => updateDraft('userAgent', val)}
              />
            </div>
          )}

          {/* DATA TAB */}
          {activeTab === 'data' && draft.isRemote && (
            <div className="space-y-4">
              <SelectDropdown
                label={t('Source')}
                value={String(draft.sourceId)}
                options={[
                  { label: t('JSON'), value: '1' },
                  { label: t('CSV'), value: '2' },
                ]}
                onSelect={(val) => {
                  updateDraft('sourceId', val as '1' | '2');
                }}
              />

              {draft.sourceId == '1' && (
                <>
                  <TextInput
                    name="dataRoot"
                    label={t('Data Root')}
                    placeholder=" "
                    helpText={t(
                      'Please enter the element in your remote data which we should use as the starting point when we match the remote Columns. This should be an array or an object. You can use the test button below to see the structure that is returned.',
                    )}
                    value={draft.dataRoot}
                    onChange={(val) => updateDraft('dataRoot', val)}
                  />

                  <Button
                    className="h-11 rounded-0!"
                    name="dataSetRemoteTestButton"
                    variant="tertiary"
                    onClick={handleTestRemoteData}
                  >
                    {isTesting ? t('Testing...') : t('Test Data URL')}
                  </Button>

                  {testResult && (
                    <div className="relative bg-gray-100 rounded-lg p-3 h-35 overflow-y-auto w-full group">
                      <button
                        type="button"
                        onClick={handleCopy}
                        className="absolute top-2 right-2 p-3 text-gray-500 hover:text-gray-700 hover:bg-gray-200 rounded-md"
                        aria-label={t('Copy to clipboard')}
                        title={t('Copy to clipboard')}
                      >
                        {isCopied ? <Check size={14}></Check> : <Copy size={14}></Copy>}
                      </button>

                      <pre className="text-xs font-mono text-gray-700 whitespace-pre-wrap wrap-break-word pr-10">
                        {testResult}
                      </pre>
                    </div>
                  )}

                  <SelectDropdown
                    label={t('Aggregation')}
                    value={draft.summarize}
                    options={[
                      { label: t('None'), value: 'none' },
                      { label: t('Summarize'), value: 'sum' },
                      { label: t('Count'), value: 'count' },
                    ]}
                    onSelect={(val) => {
                      updateDraft('summarize', val as DatasetSummarize);
                    }}
                  />

                  {draft.summarize !== 'none' && (
                    <TextInput
                      name="summarizeField"
                      label={t('By Field')}
                      helpText={t(
                        'Using JSON syntax enter the path below the Data root by which the above aggregation should be applied.\nSummarise: Values in this field will be summarised and stored in one column.\nCount: All individual values in this field will be counted and stored in one Column for each value.',
                      )}
                      value={draft.summarizeField}
                      onChange={(val) => updateDraft('summarizeField', val)}
                    />
                  )}
                </>
              )}

              {draft.sourceId == '2' && (
                <>
                  <SelectDropdown
                    label={t('CSV Separator')}
                    value={draft.csvSeparator}
                    options={[
                      { label: t('Comma (,)'), value: ',' },
                      { label: t('Semicolon (;)'), value: ';' },
                      { label: t('Space ( )'), value: ' ' },
                      { label: t('Tab (\\t)'), value: '\t' },
                      { label: t('Pipe (|)'), value: '|' },
                    ]}
                    helpText={t('What separator should be used for CSV source?')}
                    onSelect={(val) => {
                      updateDraft('csvSeparator', val);
                    }}
                  />
                  <Checkbox
                    id="ignoreFirstRow"
                    label={t('For CSV source, should the first row be ignored?')}
                    title={t('Ignore first row?')}
                    checked={draft.ignoreFirstRow}
                    onChange={(e) => updateDraft('ignoreFirstRow', e.target.checked)}
                  />
                </>
              )}
            </div>
          )}

          {/* ADVANCED TAB */}
          {activeTab === 'advanced' && draft.isRemote && (
            <div className="space-y-4">
              <SelectDropdown
                label={t('Refresh')}
                helpText={t('Select how often the remote data is fetched and imported.')}
                value={draft.refreshRate.toString()}
                options={[
                  { label: t('Constantly'), value: '0' },
                  { label: t('Hourly'), value: '3600' },
                  { label: t('Daily'), value: '86400' },
                  { label: t('Weekly'), value: '604800' },
                  { label: t('Every two Weeks'), value: '1209600' },
                  { label: t('Monthly'), value: '2419200' },
                  { label: t('Quaterly'), value: '7257600' },
                  { label: t('Yearly'), value: '29030400' },
                ]}
                onSelect={(val) => {
                  updateDraft('refreshRate', Number(val));
                }}
              />

              <SelectDropdown
                label={t('Truncate DataSet')}
                value={draft.clearRate.toString()}
                options={[
                  { label: t('Never'), value: '0' },
                  { label: t('Always'), value: '1' },
                  { label: t('Hourly'), value: '3600' },
                  { label: t('Daily'), value: '86400' },
                  { label: t('Weekly'), value: '604800' },
                  { label: t('Every two Weeks'), value: '1209600' },
                  { label: t('Monthly'), value: '2419200' },
                  { label: t('Quaterly'), value: '7257600' },
                  { label: t('Yearly'), value: '29030400' },
                  { label: t('Every second Year'), value: '58060800' },
                ]}
                helpText={t(
                  'Select when you would like the Data to be truncated out of this DataSet.\nThe criteria is assessed when synchronisations occurs and is truncated before adding new data.',
                )}
                onSelect={(val) => {
                  updateDraft('clearRate', Number(val));
                }}
              />

              <Checkbox
                id="truncateOnEmpty"
                label={t('Truncate Dataset even if no new data is pulled from the source.')}
                title={t('Truncate with no new data?')}
                checked={draft.truncateOnEmpty === 1}
                onChange={(e) => updateDraft('truncateOnEmpty', e.target.checked ? 1 : 0)}
              />

              <SelectDropdown
                label={t('Depends on Dataset')}
                value={draft.runsAfter.toString()}
                options={[]}
                helpText={t(
                  'The DataSet you select here will be processed in advance and have its values available for substitution in the data to add to this request on the Remote tab.',
                )}
                onSelect={(val) => {
                  updateDraft('runsAfter', Number(val));
                }}
              />

              <NumberInput
                name="rowLimit"
                label={t('Row Limit')}
                helpText={t(
                  'Set a row limit for this DataSet. When left empty the DataSet row limit from CMS Settings will be used.',
                )}
                value={draft.rowLimit}
                onChange={(num) => updateDraft('rowLimit', num)}
              />

              <SelectDropdown
                label={t('Limit Policy')}
                value={draft.limitPolicy}
                options={[
                  { label: 'Stop Syncing', value: 'stop' },
                  { label: 'First In First Out', value: 'fifo' },
                  { label: 'Truncate', value: 'truncate' },
                ]}
                helpText={t('What should happen when this Dataset reaches the row limit?')}
                onSelect={(val) => {
                  updateDraft('limitPolicy', val as DatasetLimitPolicy);
                }}
              />
            </div>
          )}
        </div>
      </div>
    </Modal>
  );
}
