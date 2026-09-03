import { describe, expect, it } from 'vitest';
import { withAdminCsrf } from './api';

const locationStub = {
  href: 'https://admin.example.test/admin/dashboard.php',
  origin: 'https://admin.example.test',
} as Location;

describe('withAdminCsrf', () => {
  it('injeta o token em mutações same-origin', () => {
    const init = withAdminCsrf(
      '/api/admin-charge.php',
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
      },
      'csrf-seguro',
      locationStub,
    );

    const headers = new Headers(init.headers);
    expect(headers.get('X-CSRF-Token')).toBe('csrf-seguro');
    expect(headers.get('Content-Type')).toBe('application/json');
  });

  it('não envia o token para outra origem nem em leitura', () => {
    const external = withAdminCsrf(
      'https://evil.example/api',
      { method: 'POST' },
      'csrf-seguro',
      locationStub,
    );
    const read = withAdminCsrf(
      '/api/students.php',
      { method: 'GET' },
      'csrf-seguro',
      locationStub,
    );

    expect(new Headers(external.headers).has('X-CSRF-Token')).toBe(false);
    expect(new Headers(read.headers).has('X-CSRF-Token')).toBe(false);
  });
});

