import { describe, expect, it } from 'vitest';
import {
  escapeHtml,
  isSafeBulkMailUrl,
  safeAsaasHttpsUrl,
  safeSameOriginUrl,
  sanitizeBulkMailHtml,
} from './security';

describe('segurança de saída e URLs', () => {
  it('escapa os caracteres HTML perigosos', () => {
    expect(escapeHtml(`<img src=x onerror="x">'&`)).toBe(
      '&lt;img src=x onerror=&quot;x&quot;&gt;&#39;&amp;',
    );
  });

  it('aceita somente HTTPS do Asaas e navegação same-origin', () => {
    expect(safeAsaasHttpsUrl('https://sandbox.asaas.com/i/123')).toBe(
      'https://sandbox.asaas.com/i/123',
    );
    expect(safeAsaasHttpsUrl('http://asaas.com/i/123')).toBe('');
    expect(safeAsaasHttpsUrl('https://asaas.com.evil.example/i/123')).toBe('');

    const origin = 'https://admin.example.test';
    expect(safeSameOriginUrl('/dashboard.php?tab=x#y', '/fallback', origin)).toBe(
      '/dashboard.php?tab=x#y',
    );
    expect(safeSameOriginUrl('https://evil.example/x', '/fallback', origin)).toBe(
      '/fallback',
    );
  });

  it('mantém apenas protocolos permitidos no e-mail', () => {
    const origin = 'https://admin.example.test';
    expect(isSafeBulkMailUrl('{{LINK_PAGAMENTO}}', origin)).toBe(true);
    expect(isSafeBulkMailUrl('mailto:responsavel@example.test', origin)).toBe(true);
    expect(isSafeBulkMailUrl('javascript:alert(1)', origin)).toBe(false);
  });

  it('remove execução ativa da prévia de e-mail', () => {
    const sanitized = sanitizeBulkMailHtml(
      '<p onclick="alert(1)" style="background:url(javascript:x)">Oi</p>'
      + '<script>alert(1)</script><a href="javascript:alert(1)">link</a>',
    );

    expect(sanitized).not.toContain('<script');
    expect(sanitized).not.toContain('onclick');
    expect(sanitized).not.toContain('background:');
    expect(sanitized).not.toContain('javascript:');
    expect(sanitized).toContain('<p>Oi</p>');
  });
});

