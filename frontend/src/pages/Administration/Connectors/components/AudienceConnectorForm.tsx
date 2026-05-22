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

import { useQuery, useQueryClient } from '@tanstack/react-query';
import type { TFunction } from 'i18next';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import type { TransitionStartFunction } from 'react';
import { useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import DisplayGroupSelect from './DisplayGroupSelect';

import Button from '@/components/ui/Button';
import GeoScheduleMap from '@/components/ui/GeoScheduleMap';
import InfoBanner from '@/components/ui/InfoBanner';
import Checkbox from '@/components/ui/forms/Checkbox';
import DatePickerInput from '@/components/ui/forms/DatePickerInput';
import MultiSelectDropdown from '@/components/ui/forms/MultiSelectDropdown';
import NumberInput from '@/components/ui/forms/NumberInput';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { useUserContext } from '@/context/UserContext';
import { fetchConnectorProxy, postConnectorProxy, updateConnector } from '@/services/connectorApi';
import type {
  AudienceImpressionSource,
  AudienceOptions,
  Connector,
  ConnectorField,
  Dma,
} from '@/types/connector';

const DEFAULT_LAT_FALLBACK = 51.5;
const DEFAULT_LNG_FALLBACK = 0.4;

function getDaysOfWeek(t: TFunction) {
  return [
    { value: '1', label: t('Monday') },
    { value: '2', label: t('Tuesday') },
    { value: '3', label: t('Wednesday') },
    { value: '4', label: t('Thursday') },
    { value: '5', label: t('Friday') },
    { value: '6', label: t('Saturday') },
    { value: '7', label: t('Sunday') },
  ];
}

interface DmaDraft {
  _id?: string;
  name: string;
  priority: string;
  displayGroupId: number | null;
  displayGroupLabel: string;
  costPerPlay: string;
  impressionSource: string;
  impressionsPerPlay: string;
  startDate: string;
  endDate: string;
  daysOfWeek: string[];
  startTime: string;
  endTime: string;
  geoFence: string;
}

function emptyDraft(): DmaDraft {
  return {
    name: '',
    priority: '0',
    displayGroupId: null,
    displayGroupLabel: '',
    costPerPlay: '',
    impressionSource: '',
    impressionsPerPlay: '',
    startDate: '',
    endDate: '',
    daysOfWeek: [],
    startTime: '',
    endTime: '',
    geoFence: '',
  };
}

function toPhpDate(iso: string): string {
  if (!iso) {
    return '';
  }
  const d = new Date(iso);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} 00:00:00`;
}

function fromPhpDate(phpDate: string): string {
  if (!phpDate) {
    return '';
  }
  return new Date(phpDate).toISOString();
}

function dmaToDate(dma: Dma): DmaDraft {
  return {
    _id: dma._id,
    name: dma.name,
    priority: String(dma.priority),
    displayGroupId: dma.displayGroupId ?? null,
    displayGroupLabel: '',
    costPerPlay: String(dma.costPerPlay ?? ''),
    impressionSource: dma.impressionSource ?? '',
    impressionsPerPlay: String(dma.impressionsPerPlay ?? ''),
    startDate: dma.startDate ?? '',
    endDate: dma.endDate ?? '',
    daysOfWeek: (dma.daysOfWeek ?? []).map(String),
    startTime: dma.startTime ?? '',
    endTime: dma.endTime ?? '',
    geoFence: dma.geoFence ? JSON.stringify(dma.geoFence) : '',
  };
}

interface DmaFormModalProps {
  connectorId: string;
  draft: DmaDraft;
  impressionSources: AudienceImpressionSource[];
  defaultLat: number;
  defaultLng: number;
  onClose: () => void;
  onSaved: () => void;
}

function DmaFormModal({
  connectorId,
  draft: initialDraft,
  impressionSources,
  defaultLat,
  defaultLng,
  onClose,
  onSaved,
}: DmaFormModalProps) {
  const { t } = useTranslation();
  const [activeTab, setActiveTab] = useState<'general' | 'datetime' | 'geofence'>('general');
  const [draft, setDraft] = useState<DmaDraft>(initialDraft);
  const [isSaving, startSaving] = useTransition();
  const [saveError, setSaveError] = useState<string | null>(null);

  const isEdit = Boolean(draft._id);
  const method = isEdit ? 'dmaEdit' : 'dmaAdd';

  function set(key: keyof DmaDraft, value: unknown) {
    setDraft((prev) => ({ ...prev, [key]: value }));
  }

  function handleSave() {
    startSaving(async () => {
      setSaveError(null);

      const params: Record<string, string | string[]> = {
        _id: draft._id ?? '',
        name: draft.name,
        priority: draft.priority,
        costPerPlay: draft.costPerPlay,
        impressionSource: draft.impressionSource,
        impressionsPerPlay: draft.impressionsPerPlay,
        startDate: draft.startDate,
        endDate: draft.endDate,
        startTime: draft.startTime,
        endTime: draft.endTime,
        geoFence: draft.geoFence,
        'daysOfWeek[]': draft.daysOfWeek,
      };

      if (draft.displayGroupId !== null) {
        params.displayGroupId = String(draft.displayGroupId);
      }

      try {
        await postConnectorProxy<void>(connectorId, method, params);
        onSaved();
        onClose();
      } catch {
        setSaveError(t('Failed to save DMA. Please try again.'));
      }
    });
  }

  const tabs: { id: 'general' | 'datetime' | 'geofence'; label: string }[] = [
    { id: 'general', label: t('General') },
    { id: 'datetime', label: t('Date / Time') },
    { id: 'geofence', label: t('Geofence') },
  ];

  const impressionSourceOptions = impressionSources.map((s) => ({
    value: s.value,
    label: s.name,
  }));

  return (
    <Modal
      isOpen
      onClose={onClose}
      title={isEdit ? t('Edit DMA') : t('Add DMA')}
      size="lg"
      showCloseButton
    >
      <div className="flex flex-col gap-0">
        <div role="tablist" className="flex px-4 overflow-x-auto shrink-0">
          {tabs.map((tab) => (
            <button
              key={tab.id}
              role="tab"
              type="button"
              aria-selected={activeTab === tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`py-2 px-3 inline-flex items-center gap-2 border-b-2 text-sm font-semibold whitespace-nowrap focus:outline-none transition-all ${
                activeTab === tab.id
                  ? 'border-blue-600 text-blue-500'
                  : 'border-gray-200 text-gray-500 hover:text-blue-600'
              }`}
            >
              {tab.label}
            </button>
          ))}
        </div>

        <div className="flex flex-col gap-4 px-8 py-4">
          {activeTab === 'general' && (
            <>
              <TextInput
                name="name"
                label={t('Name')}
                helpText={t('The Name of this DMA - (1 - 50 characters)')}
                value={draft.name}
                onChange={(val) => set('name', val)}
              />

              <NumberInput
                name="priority"
                label={t('Priority')}
                helpText={t('Set a priority for this DMA. Higher priorities take precedence.')}
                value={Number(draft.priority)}
                onChange={(val) => set('priority', String(val ?? 0))}
              />

              <DisplayGroupSelect
                label={t('Display Group')}
                helpText={t('Which displays would you like this DMA to apply to?')}
                value={draft.displayGroupId}
                valueLabel={draft.displayGroupLabel}
                onChange={(id, label) => {
                  set('displayGroupId', id);
                  set('displayGroupLabel', label);
                }}
              />

              <TextInput
                name="costPerPlay"
                label={t('Cost per Play')}
                helpText={t('The cost per play')}
                value={draft.costPerPlay}
                onChange={(val) => set('costPerPlay', val)}
              />

              {impressionSourceOptions.length > 0 && (
                <SelectDropdown
                  label={t('Impression Source')}
                  helpText={t('What is the source of this impression figure?')}
                  value={draft.impressionSource}
                  options={impressionSourceOptions}
                  onSelect={(val) => set('impressionSource', val)}
                />
              )}

              <NumberInput
                name="impressionsPerPlay"
                label={t('Impressions per play')}
                helpText={t('The impressions per play')}
                value={Number(draft.impressionsPerPlay) || undefined}
                onChange={(val) => set('impressionsPerPlay', String(val ?? ''))}
              />
            </>
          )}

          {activeTab === 'datetime' && (
            <>
              <DatePickerInput
                label={t('Start Date')}
                helpText={t('Select the start date for this DMA')}
                value={draft.startDate ? fromPhpDate(draft.startDate) : ''}
                onChange={(iso) => set('startDate', iso ? toPhpDate(iso) : '')}
                showTimePicker={false}
                optional
              />

              <DatePickerInput
                label={t('End Date')}
                helpText={t('Select the end date for this DMA')}
                value={draft.endDate ? fromPhpDate(draft.endDate) : ''}
                onChange={(iso) => set('endDate', iso ? toPhpDate(iso) : '')}
                showTimePicker={false}
                optional
              />

              <MultiSelectDropdown
                label={t('Days of the week')}
                helpText={t('Which days of the week should the DMA be active?')}
                value={draft.daysOfWeek}
                options={getDaysOfWeek(t)}
                onChange={(vals) => set('daysOfWeek', vals)}
                showTags
              />

              <TextInput
                name="startTime"
                label={t('Start Time')}
                helpText={t('Select the start time for this DMA')}
                value={draft.startTime}
                type="time"
                onChange={(val) => set('startTime', val)}
              />

              <TextInput
                name="endTime"
                label={t('End Time')}
                helpText={t('Select the end time for this DMA')}
                value={draft.endTime}
                type="time"
                onChange={(val) => set('endTime', val)}
              />
            </>
          )}

          {activeTab === 'geofence' && (
            <>
              <p className="text-sm text-gray-600">
                {t('Draw areas on the map where you want this DMA to be active.')}
              </p>
              <div className="flex flex-col h-96 rounded-lg overflow-hidden border border-gray-200">
                <GeoScheduleMap
                  geoLocation={draft.geoFence}
                  onChange={(json) => set('geoFence', json)}
                  defaultLat={defaultLat}
                  defaultLng={defaultLng}
                />
              </div>
            </>
          )}

          {saveError && <InfoBanner type="danger">{saveError}</InfoBanner>}

          <div className="flex justify-end gap-2 border-t border-gray-100 pt-4">
            <Button type="button" variant="secondary" onClick={onClose}>
              {t('Cancel')}
            </Button>
            <Button type="button" disabled={isSaving} onClick={() => void handleSave()}>
              {isSaving ? t('Saving…') : t('Save')}
            </Button>
          </div>
        </div>
      </div>
    </Modal>
  );
}

interface AudienceConnectorFormProps {
  connector: Connector;
  connectorId: string;
  fields: ConnectorField[];
  settings: Record<string, unknown>;
  enabledLabel: string;
  enabledDescription: string;
  enabledMessage?: string;
  onSave: () => void;
  startTransition: TransitionStartFunction;
}

export default function AudienceConnectorForm({
  connector,
  connectorId,
  fields,
  settings,
  enabledLabel,
  enabledDescription,
  enabledMessage,
  onSave,
  startTransition,
}: AudienceConnectorFormProps) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const { user } = useUserContext();

  const defaultLat = Number(user?.settings?.DEFAULT_LAT ?? DEFAULT_LAT_FALLBACK);
  const defaultLng = Number(user?.settings?.DEFAULT_LONG ?? DEFAULT_LNG_FALLBACK);

  const apiKeyField = fields.find((f) => f.name === 'apiKey');
  const apiKeyProviderOnly = apiKeyField?.providerOnly ?? false;
  const savedApiKey = Boolean(settings.apiKey);

  const [formValues, setFormValues] = useState<Record<string, string>>({
    isEnabled: String(connector.isEnabled),
    apiKey: String(settings.apiKey ?? ''),
  });
  const [saveError, setSaveError] = useState<string | null>(null);
  const [dmaModal, setDmaModal] = useState<DmaDraft | null>(null);
  const [deleteConfirmId, setDeleteConfirmId] = useState<string | null>(null);
  const [isDeleting, startDeleting] = useTransition();

  const dmaQueryKey = ['connectors', connectorId, 'proxy', 'dmaSearch'];

  const { data: axeOptions } = useQuery({
    queryKey: ['connectors', connectorId, 'proxy', 'getOptionsFromAxe'],
    queryFn: () =>
      fetchConnectorProxy<AudienceOptions>(String(connector.connectorId!), 'getOptionsFromAxe'),
    enabled: connector.connectorId !== null && savedApiKey,
  });

  const { data: dmaData, isLoading: dmaLoading } = useQuery({
    queryKey: dmaQueryKey,
    queryFn: () =>
      fetchConnectorProxy<{ data: Dma[]; recordsTotal: number }>(
        String(connector.connectorId!),
        'dmaSearch',
      ),
    enabled: connector.connectorId !== null && savedApiKey,
  });

  const dmas = dmaData?.data ?? [];
  const impressionSources = axeOptions?.impressionSource ?? [];

  function handleChange(name: string, value: string) {
    setFormValues((prev) => ({ ...prev, [name]: value }));
  }

  function handleSubmit(e: React.SyntheticEvent<HTMLFormElement>) {
    e.preventDefault();
    setSaveError(null);
    startTransition(async () => {
      try {
        await updateConnector(connectorId, formValues);
        onSave();
      } catch {
        setSaveError(t('Failed to save connector settings. Please try again.'));
      }
    });
  }

  function handleDeleteDma(id: string) {
    startDeleting(async () => {
      try {
        await fetchConnectorProxy(String(connector.connectorId!), 'dmaDelete', { _id: id });
        void queryClient.invalidateQueries({ queryKey: dmaQueryKey });
      } finally {
        setDeleteConfirmId(null);
      }
    });
  }

  function handleDmaSaved() {
    void queryClient.invalidateQueries({ queryKey: dmaQueryKey });
  }

  const dayLabels: Record<string, string> = {
    '1': t('Mon'),
    '2': t('Tue'),
    '3': t('Wed'),
    '4': t('Thu'),
    '5': t('Fri'),
    '6': t('Sat'),
    '7': t('Sun'),
  };

  return (
    <>
      <form
        id="audience-connector-form"
        onSubmit={handleSubmit}
        className="flex flex-col gap-4 px-8 py-4"
      >
        {!apiKeyProviderOnly && (
          <div className="flex flex-col gap-3">
            <h4 className="text-sm font-semibold text-gray-700">{t('Settings')}</h4>
            <p className="text-sm text-gray-600">
              {t(
                'Your API key allows for secure communication between the CMS and the Xibo' +
                  ' audience service. It is used to analyse your proof of play data for Ad' +
                  ' Campaigns and retrieve reports. It is never possible to retrieve credentials.',
              )}
            </p>
            <TextInput
              name="apiKey"
              label={t('API Key')}
              helpText={apiKeyField?.helpText}
              value={formValues.apiKey ?? ''}
              onChange={(val) => handleChange('apiKey', val)}
            />
          </div>
        )}

        <p className="text-sm text-gray-600">
          {t(
            'Once enabled additional reporting will be shown for your Ad Campaigns in the' +
              " 'All Reports' section. To vary your cost/impressions per play by date, time of" +
              ' day, day of week or geo location, add a DMA (designated market area) below.',
          )}
        </p>

        {savedApiKey && axeOptions && (
          <>
            {axeOptions.error ? (
              <InfoBanner type="danger">{axeOptions.message ?? t('Service error')}</InfoBanner>
            ) : (
              <InfoBanner type="info">
                {t('Your API key is authorised for {{n}} displays.', {
                  n: axeOptions.displays ?? 0,
                })}
              </InfoBanner>
            )}
          </>
        )}

        {savedApiKey && (
          <div className="border-t border-gray-100 pt-4 flex flex-col gap-3">
            <div className="flex items-center justify-between">
              <h4 className="text-sm font-semibold text-gray-700">
                {t('DMA (Designated Market Areas)')}
              </h4>
              <Button
                type="button"
                variant="secondary"
                leftIcon={Plus}
                onClick={() => setDmaModal(emptyDraft())}
              >
                {t('Add DMA')}
              </Button>
            </div>

            {dmaLoading ? (
              <div className="flex items-center gap-2 py-2">
                <div className="h-4 w-4 animate-spin rounded-full border-2 border-blue-600 border-t-transparent" />
                <span className="text-sm text-gray-500">{t('Loading…')}</span>
              </div>
            ) : dmas.length === 0 ? (
              <p className="text-sm text-gray-500 py-2">{t('No DMAs configured.')}</p>
            ) : (
              <div className="overflow-x-auto rounded-lg border border-gray-200">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="bg-gray-50 border-b border-gray-200">
                      <th className="px-3 py-2 text-left font-semibold text-gray-600">
                        {t('Name')}
                      </th>
                      <th className="px-3 py-2 text-left font-semibold text-gray-600">
                        {t('Cost/Play')}
                      </th>
                      <th className="px-3 py-2 text-left font-semibold text-gray-600">
                        {t('Impr./Play')}
                      </th>
                      <th className="px-3 py-2 text-left font-semibold text-gray-600">
                        {t('Source')}
                      </th>
                      <th className="px-3 py-2 text-left font-semibold text-gray-600">
                        {t('Dates')}
                      </th>
                      <th className="px-3 py-2 text-left font-semibold text-gray-600">
                        {t('Days')}
                      </th>
                      <th className="px-3 py-2 text-left font-semibold text-gray-600">
                        {t('Time')}
                      </th>
                      <th className="px-3 py-2 text-left font-semibold text-gray-600">
                        {t('Geo?')}
                      </th>
                      <th className="px-3 py-2 text-left font-semibold text-gray-600">
                        {t('Priority')}
                      </th>
                      <th className="px-3 py-2 text-left font-semibold text-gray-600">
                        {t('Displays')}
                      </th>
                      <th className="px-3 py-2" />
                    </tr>
                  </thead>
                  <tbody>
                    {dmas.map((dma) => (
                      <tr key={dma._id} className="border-b border-gray-100 last:border-0">
                        <td className="px-3 py-2 font-medium text-gray-700">{dma.name}</td>
                        <td className="px-3 py-2 text-gray-600">{dma.costPerPlay}</td>
                        <td className="px-3 py-2 text-gray-600">{dma.impressionsPerPlay}</td>
                        <td className="px-3 py-2 text-gray-600">{dma.impressionSource}</td>
                        <td className="px-3 py-2 text-gray-600 text-xs">
                          {dma.startDate && <span>{dma.startDate}</span>}
                          {dma.startDate && dma.endDate && <span> – </span>}
                          {dma.endDate && <span>{dma.endDate}</span>}
                        </td>
                        <td className="px-3 py-2 text-gray-600 text-xs">
                          {(dma.daysOfWeek ?? []).map((d) => dayLabels[String(d)] ?? d).join(', ')}
                        </td>
                        <td className="px-3 py-2 text-gray-600 text-xs">
                          {dma.startTime && dma.endTime
                            ? `${dma.startTime} – ${dma.endTime}`
                            : (dma.startTime ?? dma.endTime ?? '')}
                        </td>
                        <td className="px-3 py-2 text-gray-600">{dma.geoFence ? '✓' : '–'}</td>
                        <td className="px-3 py-2 text-gray-600">{dma.priority}</td>
                        <td className="px-3 py-2 text-gray-600">
                          {Array.isArray(dma.displays) ? dma.displays.length : 0}
                        </td>
                        <td className="px-3 py-2">
                          <div className="flex gap-1">
                            <button
                              type="button"
                              className="p-1 text-gray-400 hover:text-blue-600"
                              onClick={() => setDmaModal(dmaToDate(dma))}
                              aria-label={t('Edit')}
                            >
                              <Pencil size={14} />
                            </button>
                            <button
                              type="button"
                              className="p-1 text-gray-400 hover:text-red-600"
                              onClick={() => setDeleteConfirmId(dma._id)}
                              aria-label={t('Delete')}
                            >
                              <Trash2 size={14} />
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        )}

        <div className="border-t border-gray-100 pt-4 flex flex-col gap-2">
          <h4 className="text-sm font-semibold text-gray-700">{enabledLabel}</h4>
          {enabledMessage && <p className="text-sm text-gray-600">{enabledMessage}</p>}
          <Checkbox
            id="audience-isEnabled"
            label={enabledDescription}
            checked={Boolean(Number(formValues.isEnabled))}
            onChange={(e) => handleChange('isEnabled', e.target.checked ? '1' : '0')}
          />
        </div>

        {saveError && <InfoBanner type="danger">{saveError}</InfoBanner>}
      </form>

      {dmaModal !== null && (
        <DmaFormModal
          connectorId={String(connector.connectorId!)}
          draft={dmaModal}
          impressionSources={impressionSources}
          defaultLat={defaultLat}
          defaultLng={defaultLng}
          onClose={() => setDmaModal(null)}
          onSaved={handleDmaSaved}
        />
      )}

      {deleteConfirmId !== null && (
        <Modal
          isOpen
          onClose={() => setDeleteConfirmId(null)}
          title={t('Delete DMA')}
          size="sm"
          showCloseButton
        >
          <div className="flex flex-col gap-4 px-8 py-4">
            <p className="text-sm text-gray-700">
              {t('Are you sure you want to delete this DMA?')}
            </p>
            <div className="flex justify-end gap-2 border-t border-gray-100 pt-4">
              <Button type="button" variant="secondary" onClick={() => setDeleteConfirmId(null)}>
                {t('No')}
              </Button>
              <Button
                type="button"
                variant="secondary"
                className="border-red-300 text-red-600 hover:bg-red-50"
                disabled={isDeleting}
                onClick={() => void handleDeleteDma(deleteConfirmId)}
              >
                {isDeleting ? t('Deleting…') : t('Yes, delete')}
              </Button>
            </div>
          </div>
        </Modal>
      )}
    </>
  );
}
