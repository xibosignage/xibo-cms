/**
 * Validates a priorRoute value before navigation. Mirrors PHP's
 * sanitizePriorRouteForOutput() + getRedirect() logic on the client side.
 * Returns the safe URL, or the fallback if the value is rejected.
 */
export function getSafeRedirectUrl(
  priorRoute: string | undefined,
  fallback = '/prototype/',
): string {
  if (!priorRoute) return fallback;

  try {
    // Resolve against current origin to detect absolute URLs with a different origin
    const resolved = new URL(priorRoute, window.location.origin);
    if (resolved.origin !== window.location.origin) return fallback;

    const path = resolved.pathname;
    if (path === '' || path === '/' || path.startsWith('/login')) return fallback;

    let safe = path;
    if (resolved.search) safe += resolved.search;
    if (resolved.hash) safe += resolved.hash;
    return safe;
  } catch {
    return fallback;
  }
}
