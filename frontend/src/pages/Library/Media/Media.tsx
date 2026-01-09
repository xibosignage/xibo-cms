import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import TableWithFilter from '@/components/ui/TableWithFilter';
import type { Column } from '@/components/ui/TableWithFilter';
import { fetchMedia } from '@/services/mediaApi';
import type { MediaRow } from '@/types/media';

export default function Media() {
  const { t } = useTranslation();
  const [data, setData] = useState<MediaRow[]>([]);
  const [error, setError] = useState<string>('');

  useEffect(() => {
    async function load() {
      try {
        const json = await fetchMedia();
        setData(json);
      } catch (err) {
        console.error('Failed to load media', err);
        if (err instanceof Error) {
          setError(err.message);
        } else {
          setError('An unknown error occurred');
        }
      }
    }
    load();
  }, []);

  const columns: Column<MediaRow>[] = [
    { key: 'mediaId', label: t('ID') },
    { key: 'name', label: t('Name') },
    {
      key: 'thumbnail',
      label: t('Thumbnail'),
      render: (row: MediaRow) =>
        row.thumbnail ? (
          <div className="h-12 w-12 overflow-hidden rounded-md border bg-white">
            <img
              src={row.thumbnail || undefined} // undefined prevents the empty string warning
              alt={row.name ?? 'thumbnail'}
              className="h-full w-full object-cover"
              loading="lazy"
              onError={(e) => (e.currentTarget.style.visibility = 'hidden')}
            />
          </div>
        ) : (
          <div className="h-12 w-12 rounded-md bg-gray-100 flex items-center justify-center text-gray-400 text-xs">
            N/A
          </div>
        ),
    },
    { key: 'mediaType', label: t('Type') },
    { key: 'owner', label: t('Owner') },
    {
      key: 'valid',
      label: t('Valid'),
      render: (row: MediaRow) =>
        row.valid ? (
          <span className="text-green-600" aria-label={t('Valid')}>
            ✔
          </span>
        ) : (
          <span className="text-red-600" aria-label={t('Invalid')}>
            ✘
          </span>
        ),
    },
    { key: 'createdDt', label: t('Created') },
  ];

  return (
    <section className="space-y-2">
      <div>
        {error && <div className="text-red-600 mb-4">{error}</div>}
        <TableWithFilter data={data} columns={columns} />
      </div>
    </section>
  );
}
