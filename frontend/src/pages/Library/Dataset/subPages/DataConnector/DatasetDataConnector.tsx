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

import { javascript } from '@codemirror/lang-javascript';
import { useQuery, useMutation } from '@tanstack/react-query';
import CodeMirror from '@uiw/react-codemirror';
import { Slash, Save } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useParams, useNavigate } from 'react-router-dom';

import Button from '@/components/ui/Button';
import InfoBanner from '@/components/ui/InfoBanner';
import TabNav from '@/components/ui/TabNav';
import TextInput from '@/components/ui/forms/TextInput';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import {
  getDatasetById,
  getDataConnectorScript,
  saveDataConnectorScript,
  fetchDatasetColumns,
} from '@/services/datasetApi';

interface LogEntry {
  ts: string;
  text: string;
}

interface DataRow {
  [key: string]: string;
}

interface CriteriaEntry {
  value: string;
  ttl: number;
}

type ActiveTab = 'testParams' | 'logs' | 'dataSet' | 'otherData' | 'scheduleCriteria';

const DEFAULT_SCRIPT = 'window.onInit = function() {\n\n}';

function formatTs(): string {
  const now = new Date();
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${String(now.getFullYear()).slice(2)}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
}

export default function DatasetDataConnector() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { datasetId } = useParams<{ datasetId: string }>();

  const iframeRef = useRef<HTMLIFrameElement>(null);
  const channelRef = useRef<BroadcastChannel | null>(null);

  const [script, setScript] = useState('');
  const [activeTab, setActiveTab] = useState<ActiveTab>('logs');
  const [testParams, setTestParams] = useState('');
  const [iframeKey, setIframeKey] = useState(0);
  const [logs, setLogs] = useState<LogEntry[]>([]);
  const [dataSetRows, setDataSetRows] = useState<DataRow[]>([]);
  const [otherData, setOtherData] = useState<Record<string, string>>({});
  const [criteria, setCriteria] = useState<Record<string, CriteriaEntry>>({});

  const { data: dataset } = useQuery({
    queryKey: ['dataset', datasetId],
    queryFn: () => getDatasetById(datasetId!),
    enabled: !!datasetId,
  });

  const { data: connectorData, isLoading: isLoadingScript } = useQuery({
    queryKey: ['dataConnectorScript', datasetId],
    queryFn: () => getDataConnectorScript(datasetId!),
    enabled: !!datasetId,
  });

  const { data: columnsData } = useQuery({
    queryKey: ['datasetColumns', datasetId],
    queryFn: () => fetchDatasetColumns(datasetId!, { start: 0, length: 100 }),
    enabled: !!datasetId,
  });

  useEffect(() => {
    if (connectorData?.script != null) {
      setScript(connectorData.script || DEFAULT_SCRIPT);
    }
  }, [connectorData]);

  useEffect(() => {
    const saved = localStorage.getItem('dataSetRealtimeTestParams');
    if (saved) {
      setTestParams(saved);
    }
  }, []);

  useEffect(() => {
    channelRef.current = new BroadcastChannel('xiboPreview');
    return () => {
      channelRef.current?.close();
    };
  }, []);

  const reloadIframe = () => {
    setIframeKey((k) => k + 1);
    setLogs([]);
    setDataSetRows([]);
    setOtherData({});
    setCriteria({});
  };

  const postInit = () => {
    iframeRef.current?.contentWindow?.postMessage(
      { type: 'init', id: Number(datasetId), params: { data: testParams } },
      '*',
    );
  };

  useEffect(() => {
    const handler = (event: MessageEvent) => {
      const { type, data, dataKey } = event.data ?? {};

      if (type === 'loaded') {
        postInit();
      } else if (type === 'log') {
        setLogs((prev) => [
          { ts: formatTs(), text: Array.isArray(data) ? data.join(' ') : String(data) },
          ...prev,
        ]);
      } else if (type === 'set') {
        if (String(dataKey) === datasetId) {
          let parsed: unknown;
          try {
            parsed = JSON.parse(data);
          } catch {
            parsed = null;
          }
          if (Array.isArray(parsed)) {
            setDataSetRows(parsed as DataRow[]);
          } else {
            setOtherData((prev) => ({ ...prev, [dataKey]: data }));
          }
        } else {
          setOtherData((prev) => ({ ...prev, [dataKey]: data }));
        }
        channelRef.current?.postMessage({ type: 'xiboDC_data', dataKey, data });
      } else if (type === 'notify') {
        setLogs((prev) => [{ ts: formatTs(), text: `Notify for ${dataKey}` }, ...prev]);
        channelRef.current?.postMessage({ type: 'xiboDC_notify', dataKey });
      } else if (type === 'criteria') {
        setCriteria((prev) => ({ ...prev, [dataKey]: data }));
      }
    };

    window.addEventListener('message', handler);
    return () => window.removeEventListener('message', handler);
  }, [datasetId, postInit]);

  const saveMutation = useMutation({
    mutationFn: () => saveDataConnectorScript(datasetId!, script),
    onSuccess: () => {
      reloadIframe();
    },
  });

  const handleTestParamsChange = (value: string) => {
    setTestParams(value);
    localStorage.setItem('dataSetRealtimeTestParams', value);
  };

  const handleTestParamsBlur = () => {
    reloadIframe();
  };

  const isUserDefined = connectorData?.dataConnectorSource === 'user_defined';
  const datasetColumns = columnsData?.rows ?? [];
  const knownColumnKeys = new Set(datasetColumns.map((c) => c.heading));
  const libraryTabs = useFilteredTabs('library');

  const TABS: { id: ActiveTab; label: string }[] = [
    { id: 'testParams', label: t('Test Params') },
    { id: 'logs', label: t('Logs') },
    { id: 'dataSet', label: t('DataSet Data') },
    { id: 'otherData', label: t('Other Data') },
    { id: 'scheduleCriteria', label: t('Schedule Criteria') },
  ];

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        {/* Header */}
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="Datasets" navigation={libraryTabs} />
        </div>

        {/* Breadcrumb */}
        <div className="mb-3">
          <nav className="flex items-center gap-1 text-sm font-medium text-gray-500">
            <button
              className="px-3 py-2 hover:text-gray-900 transition-colors cursor-pointer"
              onClick={() => navigate('/library/datasets')}
            >
              {t('Datasets')}
            </button>
            <Slash size={24} className="p-1 text-gray-400" />
            <span
              className="px-3 py-2 text-xibo-blue-500 text-sm font-semibold truncate max-w-xs"
              title={dataset?.dataSet}
            >
              {dataset?.dataSet ? dataset.dataSet : `${t('Dataset')} #${datasetId}`} –{' '}
              {t('Data Connector')}
            </span>
          </nav>
        </div>

        {/* Main two-column content */}
        <div className="flex gap-4 flex-1 min-h-0">
          {/* Left: code editor (user_defined only) */}
          {!isLoadingScript && isUserDefined && (
            <div className="flex flex-col w-1/2 min-h-0 gap-3">
              <p className="text-sm text-gray-500">{t('Data Connector JavaScript')}</p>
              <div className="flex-1 border border-gray-200 rounded-lg overflow-hidden">
                <CodeMirror
                  value={script}
                  height="100%"
                  style={{ height: '100%' }}
                  extensions={[javascript()]}
                  onChange={setScript}
                  basicSetup={{ lineNumbers: true, foldGutter: true }}
                />
              </div>
              <div>
                <Button
                  variant="primary"
                  leftIcon={Save}
                  disabled={saveMutation.isPending}
                  onClick={() => saveMutation.mutate()}
                >
                  {saveMutation.isPending ? t('Saving…') : t('Save')}
                </Button>
                {saveMutation.isError && (
                  <p className="mt-2 text-sm text-red-600">{t('Failed to save.')}</p>
                )}
              </div>
            </div>
          )}

          {/* Right: tabs panel */}
          <div className={`flex flex-col min-h-0 ${isUserDefined ? 'w-1/2' : 'w-full'}`}>
            {/* Tab bar */}
            <div className="flex border-b border-gray-200 gap-0 flex-wrap">
              {TABS.map((tab) => (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id)}
                  className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
                    activeTab === tab.id
                      ? 'border-xibo-blue-500 text-xibo-blue-500'
                      : 'border-transparent text-gray-500 hover:text-gray-800'
                  }`}
                >
                  {tab.label}
                </button>
              ))}
            </div>

            {/* Tab content */}
            <div className="flex-1 overflow-y-auto pt-3">
              {activeTab === 'testParams' && (
                <div className="flex flex-col gap-3">
                  <InfoBanner type="info" className="w-full">
                    {t(
                      'You can test passing parameters that would otherwise be set when this Data Connector is scheduled.',
                    )}
                  </InfoBanner>
                  <div onBlur={handleTestParamsBlur}>
                    <TextInput
                      name="testParams"
                      label={t('Test Parameters')}
                      value={testParams}
                      onChange={handleTestParamsChange}
                    />
                  </div>
                </div>
              )}

              {activeTab === 'logs' && (
                <pre className="text-xs font-mono bg-gray-50 border border-gray-200 rounded p-3 overflow-auto h-full whitespace-pre-wrap">
                  {logs.length === 0 ? (
                    <span className="text-gray-400">{t('No logs yet…')}</span>
                  ) : (
                    logs.map((entry, i) => (
                      <span key={i} className="block">
                        [{entry.ts}] {entry.text}
                      </span>
                    ))
                  )}
                </pre>
              )}

              {activeTab === 'dataSet' && (
                <div className="overflow-x-auto rounded border border-gray-200">
                  <table className="w-full text-sm">
                    <thead className="bg-gray-50 border-b border-gray-200">
                      <tr>
                        {datasetColumns.map((col) => (
                          <th
                            key={col.heading}
                            className="px-3 py-2 text-left font-semibold text-gray-600"
                          >
                            {col.heading}
                          </th>
                        ))}
                        <th className="px-3 py-2 text-left font-semibold text-gray-600">
                          {t('Unmapped')}
                        </th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                      {dataSetRows.length === 0 ? (
                        <tr>
                          <td
                            colSpan={datasetColumns.length + 1}
                            className="px-3 py-4 text-center text-gray-400"
                          >
                            {t('No data yet…')}
                          </td>
                        </tr>
                      ) : (
                        dataSetRows.map((row, rowIdx) => {
                          const unmapped = Object.entries(row)
                            .filter(([k]) => !knownColumnKeys.has(k) && k !== 'id')
                            .map(([k, v]) => `${k}: ${v}`)
                            .join(', ');
                          return (
                            <tr key={rowIdx} className="hover:bg-gray-50">
                              {datasetColumns.map((col) => (
                                <td key={col.heading} className="px-3 py-2 text-gray-700">
                                  {row[col.heading] ?? ''}
                                </td>
                              ))}
                              <td className="px-3 py-2 text-gray-400 text-xs">{unmapped}</td>
                            </tr>
                          );
                        })
                      )}
                    </tbody>
                  </table>
                </div>
              )}

              {activeTab === 'otherData' && (
                <pre className="text-xs font-mono bg-gray-50 border border-gray-200 rounded p-3 overflow-auto h-full whitespace-pre-wrap">
                  {Object.keys(otherData).length === 0 ? (
                    <span className="text-gray-400">{t('No data yet…')}</span>
                  ) : (
                    JSON.stringify(otherData, null, 4)
                  )}
                </pre>
              )}

              {activeTab === 'scheduleCriteria' && (
                <div className="overflow-x-auto rounded border border-gray-200">
                  <table className="w-full text-sm">
                    <thead className="bg-gray-50 border-b border-gray-200">
                      <tr>
                        <th className="px-3 py-2 text-left font-semibold text-gray-600">
                          {t('Metric')}
                        </th>
                        <th className="px-3 py-2 text-left font-semibold text-gray-600">
                          {t('Value')}
                        </th>
                        <th className="px-3 py-2 text-left font-semibold text-gray-600">TTL</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                      {Object.keys(criteria).length === 0 ? (
                        <tr>
                          <td colSpan={3} className="px-3 py-4 text-center text-gray-400">
                            {t('No criteria yet…')}
                          </td>
                        </tr>
                      ) : (
                        Object.entries(criteria).map(([key, entry]) => (
                          <tr key={key} className="hover:bg-gray-50">
                            <td className="px-3 py-2 text-gray-700">{key}</td>
                            <td className="px-3 py-2 text-gray-700">{entry.value}</td>
                            <td className="px-3 py-2 text-gray-700">{entry.ttl}</td>
                          </tr>
                        ))
                      )}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Hidden test runner iframe */}
      <iframe
        key={iframeKey}
        ref={iframeRef}
        sandbox="allow-scripts"
        src={`/dataset/dataConnector/test/${datasetId}`}
        className="hidden"
        title="data-connector-test"
      />
    </section>
  );
}
