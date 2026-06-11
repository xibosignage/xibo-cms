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
import { Filter, FilterX } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { ConnectorFilterInput } from './ConnectorsConfig';
import { INITIAL_FILTER_STATE, getFilterKeys } from './ConnectorsConfig';
import ConnectorCard from './components/ConnectorCard';
import ConnectorConfigModal from './components/ConnectorConfigModal';
import { canvaImg } from './connectorThumbnails';
import { connectorQueryKeys, useConnectorsData } from './hooks/useConnectorsData';

import Button from '@/components/ui/Button';
import FilterInputs from '@/components/ui/FilterInputs';
import TabNav from '@/components/ui/TabNav';
import { useUserContext } from '@/context/UserContext';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import type { Connector } from '@/types/connector';

type ActiveModal = { type: 'configure'; connector: Connector } | null;

export default function Connectors() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const { user } = useUserContext();

  const { filterInputs, setFilterInputs, isHydrated } = useTableState<ConnectorFilterInput>(
    'connector_page',
    {
      pagination: { pageIndex: 0, pageSize: 10 },
      sorting: [],
      columnVisibility: {},
      viewMode: 'table',
      globalFilter: '',
      filterInputs: INITIAL_FILTER_STATE,
    },
  );

  const [openFilter, setOpenFilter] = useState(false);
  const [activeModal, setActiveModal] = useState<ActiveModal>(null);

  const { data: connectors, isFetching } = useConnectorsData(filterInputs, isHydrated);

  const tabs = useFilteredTabs('administration');
  const filterOptions = getFilterKeys(t);

  function handleResetFilters() {
    setFilterInputs(INITIAL_FILTER_STATE);
  }

  function handleSave() {
    void queryClient.invalidateQueries({ queryKey: connectorQueryKeys.list() });
  }

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5 overflow-y-auto">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab={t('Connectors')} navigation={tabs} />
        </div>

        <div className="flex flex-col items-end">
          <Button
            leftIcon={!openFilter ? Filter : FilterX}
            variant="secondary"
            onClick={() => setOpenFilter((prev) => !prev)}
            removeTextOnMobile
          >
            {t('Filters')}
          </Button>
        </div>

        <FilterInputs
          onChange={(name, value) => {
            setFilterInputs((prev) => ({ ...prev, [name]: value ?? '' }));
          }}
          isOpen={openFilter}
          values={filterInputs}
          options={filterOptions}
          onReset={handleResetFilters}
        />

        <div
          className={`grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 3xl:grid-cols-6 gap-5 mt-4 transition-opacity ${isFetching ? 'opacity-50' : ''}`}
        >
          {user?.branding?.appName === 'Xibo' &&
            (!filterInputs.name || 'canva'.includes(filterInputs.name.toLowerCase())) && (
              <ConnectorCard
                title={t('Canva')}
                description={t(
                  'Publish your designs from Canva to Xibo at the push of a button.' +
                    ' This connector is configured in Canva using the "Publish menu".',
                )}
                thumbnailSrc={canvaImg}
                externalLink={{ href: 'https://www.canva.com', label: t('Visit Canva') }}
              />
            )}

          {connectors.map((connector) => (
            <ConnectorCard
              key={connector.className}
              connector={connector}
              onConfigure={() => setActiveModal({ type: 'configure', connector })}
            />
          ))}

          {!isFetching && connectors.length === 0 && (
            <p className="col-span-full text-sm text-gray-500 text-center py-8">
              {t('No connectors found.')}
            </p>
          )}
        </div>
      </div>

      {activeModal?.type === 'configure' && (
        <ConnectorConfigModal
          connector={activeModal.connector}
          onClose={() => setActiveModal(null)}
          onSave={handleSave}
        />
      )}
    </section>
  );
}
