const SAFE_METHODS = new Set(['GET', 'HEAD', 'OPTIONS']);

export function readAdminCsrfToken(doc: Document = document): string {
  return doc.querySelector<HTMLMetaElement>('meta[name="admin-csrf-token"]')?.content ?? '';
}

export function withAdminCsrf(
  input: RequestInfo | URL,
  init: RequestInit = {},
  token = readAdminCsrfToken(),
  location: Location = window.location,
): RequestInit {
  const method = String(
    init.method ?? (input instanceof Request ? input.method : 'GET'),
  ).toUpperCase();
  const target = input instanceof Request ? input.url : String(input);
  const targetUrl = new URL(target, location.href);

  if (targetUrl.origin !== location.origin || SAFE_METHODS.has(method)) {
    return init;
  }

  const headers = new Headers(init.headers ?? {});
  headers.set('X-CSRF-Token', token);
  return { ...init, headers };
}

export function installAdminFetchBridge(
  targetWindow: Window = window,
  token = readAdminCsrfToken(targetWindow.document),
): () => void {
  const nativeFetch = targetWindow.fetch.bind(targetWindow);
  targetWindow.fetch = (input: RequestInfo | URL, init: RequestInit = {}) =>
    nativeFetch(input, withAdminCsrf(input, init, token, targetWindow.location));

  return () => {
    targetWindow.fetch = nativeFetch;
  };
}

