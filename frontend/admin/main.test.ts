import { beforeEach, describe, expect, it, vi } from 'vitest';

describe('boot do dashboard administrativo', () => {
  beforeEach(() => {
    vi.resetModules();
    document.head.innerHTML = '<meta name="admin-csrf-token" content="teste">';
    document.body.innerHTML = '';
    document.body.dataset.activeTab = 'charges';
    window.__adminDashboardBooted = false;
    window.__adminStudents = [];
    window.__monthlyStudents = [];
    window.fetch = vi.fn(async () => new Response('{}')) as typeof window.fetch;
  });

  it('marca o boot somente após inicializar os módulos', async () => {
    const info = vi.spyOn(console, 'info').mockImplementation(() => undefined);
    const module = await import('./main');

    expect(window.__adminDashboardBooted).toBe(true);
    expect(module.bootAdminDashboard()).toBe(false);
    expect(info).toHaveBeenCalledWith(
      '[admin-dashboard] bootstrap ok',
      { tabs: 0 },
    );
  });
});

