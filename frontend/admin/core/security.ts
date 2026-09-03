export function escapeHtml(value: unknown): string {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

export function safeAsaasHttpsUrl(value: unknown): string {
  try {
    const parsed = new URL(String(value ?? '').trim());
    const host = parsed.hostname.toLowerCase();
    return parsed.protocol === 'https:'
      && (host === 'asaas.com' || host.endsWith('.asaas.com'))
      ? parsed.href
      : '';
  } catch {
    return '';
  }
}

export function safeSameOriginUrl(
  value: unknown,
  fallback = '/dashboard.php',
  origin = window.location.origin,
): string {
  try {
    const parsed = new URL(String(value ?? '').trim(), origin);
    if (!['http:', 'https:'].includes(parsed.protocol) || parsed.origin !== origin) {
      return fallback;
    }
    return `${parsed.pathname}${parsed.search}${parsed.hash}`;
  } catch {
    return fallback;
  }
}

export function isSafeBulkMailUrl(
  value: unknown,
  origin = window.location.origin,
): boolean {
  const raw = String(value ?? '').trim();
  if (!raw) return true;
  if (/^\{\{(?:LINK_PAGAMENTO|LINK_SUPORTE|URL_MASCOTE)\}\}$/.test(raw)) return true;
  if (raw.startsWith('#')) return true;

  try {
    const parsed = new URL(raw, origin);
    return ['http:', 'https:', 'mailto:', 'tel:', 'cid:'].includes(parsed.protocol)
      || /^data:image\/(?:png|gif|jpe?g|webp);base64,/i.test(raw);
  } catch {
    return false;
  }
}

export function sanitizeBulkMailHtml(value: unknown): string {
  const parser = new DOMParser();
  const doc = parser.parseFromString(String(value ?? ''), 'text/html');
  const forbiddenElements = [
    'script',
    'iframe',
    'object',
    'embed',
    'svg',
    'math',
    'form',
    'input',
    'button',
    'textarea',
    'select',
    'option',
    'base',
    'meta[http-equiv]',
    'link[rel="import"]',
    'template',
    'noscript',
    'noembed',
    'noframes',
    'xmp',
    'plaintext',
  ];

  doc.querySelectorAll(forbiddenElements.join(',')).forEach((node) => node.remove());
  doc.querySelectorAll('*').forEach((node) => {
    [...node.attributes].forEach((attribute) => {
      const name = attribute.name.toLowerCase();
      const attributeValue = attribute.value;
      if (
        name.startsWith('on')
        || ['srcdoc', 'action', 'formaction'].includes(name)
        || (
          name === 'style'
          && /(?:expression\s*\(|url\s*\(|@import|-moz-binding|behavior\s*:)/i.test(attributeValue)
        )
      ) {
        node.removeAttribute(attribute.name);
        return;
      }
      if (
        ['href', 'src', 'background', 'poster', 'xlink:href'].includes(name)
        && !isSafeBulkMailUrl(attributeValue)
      ) {
        node.removeAttribute(attribute.name);
      }
    });
  });

  return `<!doctype html>\n${doc.documentElement.outerHTML}`;
}

export function buildBulkMailPreviewHtml(value: unknown): string {
  const parser = new DOMParser();
  const doc = parser.parseFromString(sanitizeBulkMailHtml(value), 'text/html');
  const securityPolicy = doc.createElement('meta');
  securityPolicy.setAttribute('http-equiv', 'Content-Security-Policy');
  securityPolicy.setAttribute(
    'content',
    "default-src 'none'; img-src https: data:; style-src 'unsafe-inline'; font-src https: data:; form-action 'none'; base-uri 'none'",
  );
  securityPolicy.dataset.bulkMailPreviewSecurity = 'true';
  doc.head.prepend(securityPolicy);
  doc.body.contentEditable = 'true';
  doc.body.spellcheck = true;
  return `<!doctype html>\n${doc.documentElement.outerHTML}`;
}

