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

import { Navigate, Outlet } from 'react-router-dom';

import { type AppRoute } from '@/config/appRoutes';
import { useUserContext } from '@/context/UserContext';
import Unauthorized from '@/pages/Unauthorized/Unauthorized';

interface ProtectedRouteProps {
  route: AppRoute;
}

export default function ProtectedRoute({ route }: ProtectedRouteProps) {
  const { user } = useUserContext();

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  let isAuthorized = true;

  if (route.validator) {
    isAuthorized = route.validator(user);
  } else if (route.feature) {
    isAuthorized = !!user.features?.[route.feature];
  }

  if (!isAuthorized) {
    return <Unauthorized />;
  }

  return <Outlet />;
}
