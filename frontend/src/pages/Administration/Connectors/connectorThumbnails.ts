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

import alphaVantageImg from '@/assets/connectors/coins.svg';
import audienceReportingImg from '@/assets/connectors/magnifying-glass-chart.svg';
import canvaImg from '@/assets/connectors/canva.png';
import capImg from '@/assets/connectors/triangle-exclamation.svg';
import dashboardImg from '@/assets/connectors/magnifying-glass-chart.svg';
import exchangeImg from '@/assets/connectors/xibo-exchange.svg';
import nationalWeatherImg from '@/assets/connectors/cloud-sun.svg';
import openWeatherImg from '@/assets/connectors/location-dot.svg';
import pixabayImg from '@/assets/connectors/pixabay.png';
import sspImg from '@/assets/connectors/xibo-ssp.svg';

// Maps PHP connector className (as returned by the API) to a locally-bundled thumbnail.
// Keys are the fully-qualified PHP class name with single backslashes.
export const connectorThumbnails: Record<string, string> = {
  '\\Xibo\\Connector\\AlphaVantageConnector': alphaVantageImg,
  '\\Xibo\\Connector\\XiboAudienceReportingConnector': audienceReportingImg,
  '\\Xibo\\Connector\\CapConnector': capImg,
  '\\Xibo\\Connector\\XiboDashboardConnector': dashboardImg,
  '\\Xibo\\Connector\\XiboExchangeConnector': exchangeImg,
  '\\Xibo\\Connector\\NationalWeatherServiceConnector': nationalWeatherImg,
  '\\Xibo\\Connector\\OpenWeatherMapConnector': openWeatherImg,
  '\\Xibo\\Connector\\PixabayConnector': pixabayImg,
  '\\Xibo\\Connector\\XiboSspConnector': sspImg,
};

export { canvaImg };
