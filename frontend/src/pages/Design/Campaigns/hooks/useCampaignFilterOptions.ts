import type { TFunction } from 'i18next';
import { useEffect, useState } from 'react';

import { getCampaignFilterKeys } from '../CampaignConfig';

export function useCampaignFilterOptions(t: TFunction) {
  const [filterOptions, setFilterOptions] = useState(() => getCampaignFilterKeys(t));

  useEffect(() => {
    setFilterOptions(getCampaignFilterKeys(t));
  }, [t]);

  return { filterOptions, isLoading: false };
}
