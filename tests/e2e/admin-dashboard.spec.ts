import { expect, test, type Page, type Request } from '@playwright/test';

const fixtureOrigin = 'http://127.0.0.1:4174';
const sharedTabs = ['Chamada', 'Famílias', 'Sem WhatsApp', 'Mensalistas', 'Entradas confirmadas'];
const restrictedTabs = [
  'Cobrança manual',
  'Cobranças em aberto',
  'Cobranças recebidas',
  'Pendência de cadastro',
  'Oficinas Modulares',
  'Exclusões',
  'Duplicados',
  'Resetar senha',
  'Acesso da Secretaria',
  'Fluxo de Caixa',
  'Dados do Asaas',
  'Enviar E-mails em Massa',
];

test.beforeEach(async ({ page }) => {
  await page.route('**/*', async (route) => {
    if (new URL(route.request().url()).origin !== fixtureOrigin) {
      await route.abort('blockedbyclient');
      return;
    }
    await route.continue();
  });
});

interface BrowserDiagnostics {
  mutations: Request[];
  externalRequests: string[];
  failedRequests: string[];
  errorResponses: string[];
  pageErrors: string[];
}

function collectDiagnostics(page: Page): BrowserDiagnostics {
  const diagnostics: BrowserDiagnostics = {
    mutations: [],
    externalRequests: [],
    failedRequests: [],
    errorResponses: [],
    pageErrors: [],
  };
  page.on('request', (request) => {
    if (new URL(request.url()).origin !== fixtureOrigin) {
      diagnostics.externalRequests.push(request.url());
    }
    if (!['GET', 'HEAD', 'OPTIONS'].includes(request.method())) {
      diagnostics.mutations.push(request);
    }
  });
  page.on('requestfailed', (request) => diagnostics.failedRequests.push(request.url()));
  page.on('response', (response) => {
    if (response.status() >= 400) {
      diagnostics.errorResponses.push(`${response.status()} ${response.url()}`);
    }
  });
  page.on('pageerror', (error) => diagnostics.pageErrors.push(error.message));
  return diagnostics;
}

async function expectBooted(page: Page): Promise<void> {
  await expect.poll(
    () => page.evaluate(() => window.__adminDashboardBooted),
  ).toBe(true);
}

test('admin principal recebe bundle real e todas as abas autorizadas', async ({ page }) => {
  const diagnostics = collectDiagnostics(page);

  await page.goto('/admin/dashboard.php?role=admin_principal');
  await expectBooted(page);

  await expect(page.locator('.admin-title')).toHaveText('DIÁRIAS VILLAGE • ADMIN');
  await expect(page.locator('script[type="module"]')).toHaveAttribute(
    'src',
    /\/assets\/admin-dist\/assets\/admin-[a-zA-Z0-9_-]+\.js$/,
  );
  await expect(page.locator('link[rel="stylesheet"][href*="/admin-dist/"]')).toHaveAttribute(
    'href',
    /\/assets\/admin-dist\/assets\/admin-[a-zA-Z0-9_-]+\.css$/,
  );

  for (const tab of [...sharedTabs, ...restrictedTabs]) {
    await expect(page.locator('.admin-tabs').getByRole('link', { name: tab, exact: true })).toHaveCount(1);
  }
  await expect(page.locator('#attendance-go-inadimplentes-btn')).toHaveCount(1);
  await expect(page.locator('img[data-e2e]')).toHaveCount(0);
  expect(await page.evaluate(() => Boolean((window as Window & { __e2eXss?: boolean }).__e2eXss))).toBe(false);
  expect(diagnostics).toEqual({
    mutations: [],
    externalRequests: [],
    failedRequests: [],
    errorResponses: [],
    pageErrors: [],
  });
});

test('secretaria recebe somente operação permitida e navega sem mutações', async ({ page }) => {
  const diagnostics = collectDiagnostics(page);
  let monthlyPayload: Record<string, unknown> | null = null;
  let monthlyCsrf = '';
  await page.route('**/api/admin-mensalistas.php', async (route) => {
    monthlyPayload = route.request().postDataJSON() as Record<string, unknown>;
    monthlyCsrf = await route.request().headerValue('x-csrf-token') ?? '';
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ ok: true, items: [] }),
    });
  });

  await page.goto('/admin/dashboard.php?role=secretaria');
  await expectBooted(page);

  const tabLabels = await page.locator('.admin-tabs [data-tab]').allTextContents();
  expect(tabLabels.map((label) => label.trim())).toEqual(sharedTabs);
  for (const tab of restrictedTabs) {
    await expect(page.locator('.admin-tabs').getByRole('link', { name: tab, exact: true })).toHaveCount(0);
  }

  await expect(page.locator('#tab-fluxo-caixa')).toHaveCount(0);
  await expect(page.locator('#tab-dados-asaas')).toHaveCount(0);
  await expect(page.locator('#tab-acesso-secretaria')).toHaveCount(0);
  await expect(page.locator('#attendance-go-inadimplentes-btn')).toHaveCount(0);

  await expect(page.locator('#monthly-students-list option')).toHaveCount(3);
  await expect(page.locator('#attendance-students-list option')).toHaveCount(3);
  expect(
    await page.evaluate(() => window.__adminStudents?.map((student) => student.id)),
  ).toEqual([
    '11111111-1111-4111-8111-111111111111',
    '22222222-2222-4222-8222-222222222222',
    '33333333-3333-4333-8333-333333333333',
  ]);
  await expect(page.locator('#monthly-students-list option').nth(0)).toHaveAttribute(
    'value',
    'Aluno Homônimo • Matrícula 10001',
  );
  await expect(page.locator('#monthly-students-list option').nth(1)).toHaveAttribute(
    'value',
    'Aluno Homônimo • Matrícula 10002',
  );
  await expect(page.locator('img[data-e2e]')).toHaveCount(0);
  expect(await page.evaluate(() => Boolean((window as Window & { __e2eXss?: boolean }).__e2eXss))).toBe(false);

  await Promise.all([
    page.waitForURL(/tab=mensalistas/),
    page.locator('.admin-tabs').getByRole('link', { name: 'Mensalistas', exact: true }).click(),
  ]);
  await expectBooted(page);
  await page.locator('#monthly-student').fill('Aluno Homônimo • Matrícula 10002');
  await page.locator('input[name="monthly-days"][value="2"]').check();
  await page.getByRole('button', { name: 'Salvar mensalista' }).click();
  await expect(page.locator('#monthly-message')).toContainText('definido como mensalista');
  expect(monthlyPayload).toEqual({
    action: 'set',
    student_id: '22222222-2222-4222-8222-222222222222',
    weekly_days: 2,
  });
  expect(monthlyCsrf).toBe('e2e-csrf-token');

  await Promise.all([
    page.waitForURL(/tab=chamada/),
    page.locator('.admin-tabs').getByRole('link', { name: 'Chamada', exact: true }).click(),
  ]);
  await expectBooted(page);
  await expect(page.locator('#tab-chamada')).toBeVisible();
  await expect(page.locator('.admin-tabs [data-tab]')).toHaveCount(5);
  await expect(page.getByRole('button', { name: 'Fechar dia de chamada' })).toBeVisible();
  expect(diagnostics.externalRequests).toEqual([]);
  expect(diagnostics.failedRequests).toEqual([]);
  expect(diagnostics.errorResponses).toEqual([]);
  expect(diagnostics.pageErrors).toEqual([]);
  expect(diagnostics.mutations).toHaveLength(1);
  expect(diagnostics.mutations[0]?.url()).toBe(`${fixtureOrigin}/api/admin-mensalistas.php`);
});

test('servidor isolado recusa métodos mutáveis', async ({ request }) => {
  const response = await request.post('/__e2e_health', {
    data: { forbidden: true },
  });
  expect(response.status()).toBe(405);
  expect(response.headers().allow).toBe('GET, HEAD');
});
