import { beforeEach, describe, expect, it } from 'vitest';
import { createAdminRuntime } from './runtime';
import { initializeCoreTabsRuntime } from './tabs-runtime';

describe('roteador de abas administrativas', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <a href="/admin/dashboard.php?tab=entries" data-tab="entries">Entradas</a>
      <a href="/admin/dashboard.php?tab=chamada" data-tab="chamada">Chamada</a>
      <section id="tab-entries"></section>
      <section id="tab-chamada" class="hidden"></section>
    `;
  });

  it('funciona com o DOM reduzido da secretaria', () => {
    const runtime = createAdminRuntime();
    runtime.tabs = document.querySelectorAll('[data-tab]');
    initializeCoreTabsRuntime(runtime);

    runtime.setActiveTab('chamada');

    expect(document.querySelector('#tab-entries')?.classList.contains('hidden')).toBe(true);
    expect(document.querySelector('#tab-chamada')?.classList.contains('hidden')).toBe(false);
  });

  it('mantém links reais para navegação sem JavaScript', () => {
    const link = document.querySelector<HTMLAnchorElement>('[data-tab="chamada"]');
    expect(link?.pathname).toBe('/admin/dashboard.php');
    expect(link?.search).toBe('?tab=chamada');
  });

  it('não oculta o painel quando a aba não pertence ao papel', () => {
    const runtime = createAdminRuntime();
    runtime.tabs = document.querySelectorAll('[data-tab]');
    initializeCoreTabsRuntime(runtime);

    runtime.setActiveTab('inadimplentes');

    expect(document.querySelector('#tab-entries')?.classList.contains('hidden')).toBe(false);
  });
});
