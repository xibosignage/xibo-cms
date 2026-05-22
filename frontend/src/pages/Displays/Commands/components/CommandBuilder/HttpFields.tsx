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

import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import KeyValueBuilder from './KeyValueBuilder';
import type { KeyValueEntry } from './KeyValueBuilder';
import type { HttpRequestOptions } from './commandStringUtils';

import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import Switch from '@/components/ui/forms/Switch';
import TextInput from '@/components/ui/forms/TextInput';

const METHOD_OPTIONS = [
  { value: 'GET', label: 'GET' },
  { value: 'POST', label: 'POST' },
  { value: 'PUT', label: 'PUT' },
  { value: 'DELETE', label: 'DELETE' },
  { value: 'CONNECT', label: 'CONNECT' },
  { value: 'HEAD', label: 'HEAD' },
  { value: 'OPTIONS', label: 'OPTIONS' },
  { value: 'PATCH', label: 'PATCH' },
  { value: 'TRACE', label: 'TRACE' },
];

const CONTENT_TYPE_OPTIONS = [
  { value: 'application/x-www-form-urlencoded', label: 'urlencoded' },
  { value: 'application/json', label: 'json' },
  { value: 'text/plain', label: 'text/plain' },
];

interface HttpFieldsProps {
  url: string;
  contenttype: string;
  requestOptions: HttpRequestOptions;
  onUrlChange: (url: string) => void;
  onContentTypeChange: (contenttype: string) => void;
  onRequestOptionsChange: (options: HttpRequestOptions) => void;
}

function parseJsonToEntries(json: string): KeyValueEntry[] {
  try {
    const obj = JSON.parse(json) as Record<string, string>;
    return Object.entries(obj).map(([key, value]) => ({ key, value: String(value) }));
  } catch {
    return [];
  }
}

function entriesToJson(entries: KeyValueEntry[]): string {
  const obj: Record<string, string> = {};
  entries.forEach((e) => {
    if (e.key) obj[e.key] = e.value;
  });
  return JSON.stringify(obj);
}

function parseUrlencodedToEntries(encoded: string): KeyValueEntry[] {
  if (!encoded) return [];
  return encoded.split('&').map((pair) => {
    const [key, ...rest] = pair.split('=');
    return { key: decodeURIComponent(key || ''), value: decodeURIComponent(rest.join('=') || '') };
  });
}

function entriesToUrlencoded(entries: KeyValueEntry[]): string {
  return entries
    .filter((e) => e.key)
    .map((e) => `${encodeURIComponent(e.key)}=${encodeURIComponent(e.value)}`)
    .join('&');
}

function parseUrlQueryParams(url: string): { baseUrl: string; params: KeyValueEntry[] } {
  const idx = url.indexOf('?');
  if (idx === -1) return { baseUrl: url, params: [] };
  const baseUrl = url.substring(0, idx);
  const qs = url.substring(idx + 1);
  if (!qs) return { baseUrl, params: [] };
  const params = qs
    .split('&')
    .map((pair) => {
      const [key, ...rest] = pair.split('=');
      return {
        key: decodeURIComponent(key || ''),
        value: decodeURIComponent(rest.join('=') || ''),
      };
    })
    .filter((p) => p.key);
  return { baseUrl, params };
}

function buildUrlWithParams(baseUrl: string, params: KeyValueEntry[]): string {
  const filtered = params.filter((p) => p.key);
  if (filtered.length === 0) return baseUrl;
  const qs = filtered
    .map((p) => `${encodeURIComponent(p.key)}=${encodeURIComponent(p.value)}`)
    .join('&');
  return `${baseUrl}?${qs}`;
}

export default function HttpFields({
  url,
  contenttype,
  requestOptions,
  onUrlChange,
  onContentTypeChange,
  onRequestOptionsChange,
}: HttpFieldsProps) {
  const { t } = useTranslation();
  const [showQueryBuilder, setShowQueryBuilder] = useState(false);
  const [showRawHeaders, setShowRawHeaders] = useState(false);
  const [showRawBody, setShowRawBody] = useState(false);

  const { baseUrl, params: queryParams } = showQueryBuilder
    ? parseUrlQueryParams(url)
    : { baseUrl: url, params: [] as KeyValueEntry[] };

  const headerEntries = parseJsonToEntries(requestOptions.headers || '{}');
  const bodyEntries =
    contenttype === 'application/json'
      ? parseJsonToEntries(requestOptions.body || '{}')
      : contenttype === 'application/x-www-form-urlencoded'
        ? parseUrlencodedToEntries(requestOptions.body || '')
        : [];

  const updateOptions = (patch: Partial<HttpRequestOptions>) => {
    onRequestOptionsChange({ ...requestOptions, ...patch });
  };

  const handleBaseUrlChange = (newBaseUrl: string) => {
    if (showQueryBuilder) {
      onUrlChange(buildUrlWithParams(newBaseUrl, queryParams));
    } else {
      onUrlChange(newBaseUrl);
    }
  };

  const handleQueryParamsChange = (entries: KeyValueEntry[]) => {
    onUrlChange(buildUrlWithParams(baseUrl, entries));
  };

  const handleHeaderEntriesChange = (entries: KeyValueEntry[]) => {
    updateOptions({ headers: entriesToJson(entries) });
  };

  const handleBodyEntriesChange = (entries: KeyValueEntry[]) => {
    if (contenttype === 'application/json') {
      updateOptions({ body: entriesToJson(entries) });
    } else if (contenttype === 'application/x-www-form-urlencoded') {
      updateOptions({ body: entriesToUrlencoded(entries) });
    }
  };

  return (
    <div className="flex flex-col gap-4">
      <TextInput
        name="httpUrl"
        label={t('URL')}
        placeholder={t('Enter URL')}
        value={showQueryBuilder ? baseUrl : url}
        onChange={handleBaseUrlChange}
      />

      {/* Query Builder */}
      <div className="rounded-lg border border-gray-200 bg-white p-4 flex flex-col gap-3">
        <div className="flex items-center justify-between">
          <h4 className="font-bold text-gray-800 text-base">{t('Query Builder')}</h4>
          <div className="shrink-0">
            <Switch size="sm" checked={showQueryBuilder} onChange={setShowQueryBuilder} hideOnOff />
          </div>
        </div>
        {showQueryBuilder && (
          <KeyValueBuilder
            label={t('Query Params')}
            entries={queryParams}
            onChange={handleQueryParamsChange}
            keyPlaceholder={t('Key')}
            valuePlaceholder={t('Value')}
          />
        )}
      </div>

      <SelectDropdown
        label={t('Request Method')}
        value={requestOptions.method}
        options={METHOD_OPTIONS}
        onSelect={(val) => updateOptions({ method: val || 'GET' })}
      />

      {/* Headers */}
      <div className="rounded-lg border border-gray-200 bg-white p-4 flex flex-col gap-3">
        <div className="flex items-center justify-between">
          <h4 className="font-bold text-gray-800 text-base">{t('Raw Headers')}</h4>
          <div className="shrink-0">
            <Switch size="sm" checked={showRawHeaders} onChange={setShowRawHeaders} hideOnOff />
          </div>
        </div>
        {showRawHeaders ? (
          <TextInput
            name="rawHeaders"
            label={t('Headers')}
            placeholder={t('JSON headers')}
            value={requestOptions.headers || '{}'}
            onChange={(val) => updateOptions({ headers: val })}
            multiline
            rows={3}
          />
        ) : (
          <KeyValueBuilder
            label={t('Headers')}
            entries={headerEntries}
            onChange={handleHeaderEntriesChange}
            keyPlaceholder={t('Header Name')}
            valuePlaceholder={t('Header Value')}
          />
        )}
      </div>

      <SelectDropdown
        label={t('Content Type')}
        value={contenttype}
        options={CONTENT_TYPE_OPTIONS}
        onSelect={(val) => onContentTypeChange(val || 'application/x-www-form-urlencoded')}
      />

      {/* Body Data */}
      <div className="rounded-lg border border-gray-200 bg-white p-4 flex flex-col gap-3">
        <div className="flex items-center justify-between">
          <h4 className="font-bold text-gray-800 text-base">{t('Raw Body')}</h4>
          <div className="shrink-0">
            <Switch size="sm" checked={showRawBody} onChange={setShowRawBody} hideOnOff />
          </div>
        </div>
        {showRawBody || contenttype === 'text/plain' ? (
          <TextInput
            name="rawBody"
            label={t('Raw data')}
            placeholder={t('Body data')}
            value={requestOptions.body || ''}
            onChange={(val) => updateOptions({ body: val })}
            multiline
            rows={4}
          />
        ) : (
          <KeyValueBuilder
            label={t('Body data')}
            entries={bodyEntries}
            onChange={handleBodyEntriesChange}
            keyPlaceholder={t('Key')}
            valuePlaceholder={t('Value')}
          />
        )}
      </div>
    </div>
  );
}
