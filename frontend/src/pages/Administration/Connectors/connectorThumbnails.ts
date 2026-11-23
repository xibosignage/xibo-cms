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

import canvaImg from '@/assets/connectors/canva.png';
import nationalWeatherImg from '@/assets/connectors/cloud-sun.svg';
import alphaVantageImg from '@/assets/connectors/coins.svg';
import openWeatherImg from '@/assets/connectors/location-dot.svg';
import audienceReportingImg from '@/assets/connectors/magnifying-glass-chart.svg';
import dashboardImg from '@/assets/connectors/magnifying-glass-chart.svg';
import pixabayImg from '@/assets/connectors/pixabay.png';
import capImg from '@/assets/connectors/triangle-exclamation.svg';
import exchangeImg from '@/assets/connectors/xibo-exchange.svg';
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
