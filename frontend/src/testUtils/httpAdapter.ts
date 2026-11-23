/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import axios, {
  AxiosError,
  AxiosHeaders,
  CanceledError,
  type AxiosAdapter,
  type AxiosResponse,
  type InternalAxiosRequestConfig,
} from 'axios';

import http from '@/lib/api';

// Stubs the network at the axios adapter, below everything the service code
// and axios itself do to build a request. The service, the shared `http`
// instance, its base URL, axios's param serializer, request and response
// transforms and the interceptors all run for real.
// Use this instead of vi.mock('@/lib/api') when the test is about what is sent.
//
// What the stub does in place of the XHR adapter (tested in httpAdapter.test.ts):
// - answers at once, from the queue; there is no in-flight period to abort
//   during, so an aborted signal is checked when the request arrives (axios
//   itself also checks before and after the adapter);
// - settles with the request's own validateStatus, as axios's settle() does;
// - turns the data into a Blob for responseType 'blob'. Other response types
//   ('arraybuffer', 'stream') get the stubbed data as it is.

export interface StubResponse {
  data?: unknown;
  status?: number;
  headers?: Record<string, string>;
}

const requests: InternalAxiosRequestConfig[] = [];
const queue: StubResponse[] = [];
let fallback: StubResponse = { data: {} };

const asBlob = (data: unknown) =>
  data instanceof Blob
    ? data
    : new Blob([typeof data === 'string' ? data : JSON.stringify(data ?? '')]);

const adapter: AxiosAdapter = async (config) => {
  requests.push(config);
  if (config.signal?.aborted) {
    throw new CanceledError(undefined, undefined, config);
  }

  const stub = queue.shift() ?? fallback;
  const status = stub.status ?? 200;
  const response: AxiosResponse = {
    data: config.responseType === 'blob' ? asBlob(stub.data) : stub.data,
    status,
    statusText: String(status),
    headers: new AxiosHeaders(stub.headers ?? {}),
    config,
  };

  // Adapters settle their own promise. This mirrors axios's settle(): a falsy
  // validateStatus accepts everything, and the error is a real AxiosError so the
  // interceptors and the service's catch see what they would in production.
  const { validateStatus } = config;
  if (!status || !validateStatus || validateStatus(status)) {
    return response;
  }
  throw new AxiosError(
    `Request failed with status code ${status}`,
    status >= 400 && status < 500 ? AxiosError.ERR_BAD_REQUEST : AxiosError.ERR_BAD_RESPONSE,
    config,
    null,
    response,
  );
};

let saved: { http: unknown; axios: unknown } | null = null;

/** Install the stub on `http` and on the bare `axios` export. Call in beforeEach. */
export function installHttpStub() {
  requests.length = 0;
  queue.length = 0;
  fallback = { data: {} };
  // Installing twice without a restore must not save the stub as "the real
  // adapter", or restore would leave the stub in place.
  if (saved) {
    return;
  }
  saved = { http: http.defaults.adapter, axios: axios.defaults.adapter };
  http.defaults.adapter = adapter;
  axios.defaults.adapter = adapter;
}

/** Put the real adapters back. Call in afterEach. */
export function restoreHttpStub() {
  if (saved) {
    http.defaults.adapter = saved.http as typeof http.defaults.adapter;
    axios.defaults.adapter = saved.axios as typeof axios.defaults.adapter;
    saved = null;
  }
}

/** Queue the response for the next request. Unqueued requests get `fallback`. */
export function respondWith(...stubs: StubResponse[]) {
  queue.push(...stubs);
}

/** The response every request gets once the queue is empty. */
export function respondAlwaysWith(stub: StubResponse) {
  fallback = stub;
}

export function allRequests() {
  return requests.map(describeRequest);
}

export function lastRequest() {
  const config = requests[requests.length - 1];
  if (!config) {
    throw new Error('No request was sent');
  }
  return describeRequest(config);
}

function describeRequest(config: InternalAxiosRequestConfig) {
  // getUri runs the same baseURL join and param serializer the XHR adapter uses.
  const url = axios.getUri(config);
  const [path, search = ''] = url.split('?');
  return {
    method: (config.method ?? 'get').toUpperCase(),
    url,
    path,
    /** The query string as sent; read repeated `foo[]` keys with getAll. */
    query: new URLSearchParams(search),
    /** The body as sent, parsed as a form. Throws for a FormData body. */
    get body() {
      if (typeof config.data !== 'string') {
        throw new Error(`Body is not a string (${Object.prototype.toString.call(config.data)})`);
      }
      return new URLSearchParams(config.data);
    },
    /** The body as sent, when it is FormData. */
    get formData() {
      if (!(config.data instanceof FormData)) {
        throw new Error(`Body is not FormData (${typeof config.data})`);
      }
      return config.data;
    },
    rawBody: config.data as unknown,
    headers: config.headers,
  };
}
