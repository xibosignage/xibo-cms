// Root URI injected by PHP via <meta name="public-path"> in login-spa.twig.
// e.g. '/' for a root install, '/cms/' for a subfolder install.
export const publicPath: string =
  document.querySelector<HTMLMetaElement>('meta[name="public-path"]')?.content ?? '/';

/**
 * Validates a priorRoute value before navigation. Mirrors PHP's
 * sanitizePriorRouteForOutput() + getRedirect() logic on the client side.
 * Returns the safe URL, or the fallback if the value is rejected.
 */
export function getSafeRedirectUrl(
  priorRoute: string | undefined,
  fallback = `${publicPath}prototype/`,
): string {
  if (!priorRoute) return fallback;

  try {
    // Resolve against current origin to detect absolute URLs with a different origin
    const resolved = new URL(priorRoute, window.location.origin);
    if (resolved.origin !== window.location.origin) return fallback;

    const path = resolved.pathname;
    const loginPath = `${publicPath}login`;
    if (path === '' || path === '/' || path === publicPath || path.startsWith(loginPath)) {
      return fallback;
    }

    let safe = path;
    if (resolved.search) safe += resolved.search;
    if (resolved.hash) safe += resolved.hash;
    return safe;
  } catch {
    return fallback;
  }
}
