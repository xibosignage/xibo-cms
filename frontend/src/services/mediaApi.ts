import http from '@/lib/api';
import type { MediaRow } from '@/types/media';

export interface FetchMediaParams {
  start: number;
  length: number;
  media?: string;
  sortBy?: string;
  sortDir?: 'asc' | 'desc' | string;
  signal?: AbortSignal;
}

export interface MediaResponse {
  rows: MediaRow[];
  totalCount: number;
}

export async function fetchMedia(
  options: FetchMediaParams = { start: 0, length: 10 },
): Promise<MediaResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/library', {
    params: queryParams,
    signal,
  });

  const rows = response.data;

  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : 0;

  return {
    rows,
    totalCount,
  };
}
