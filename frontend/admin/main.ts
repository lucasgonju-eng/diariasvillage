import './admin.css';
import { initializeBoot } from './boot';
import { installAdminFetchBridge } from './core/api';
import { initializeCoreDialogs } from './core/dialogs';
import { initializeCoreFormattersStudents } from './core/formatters-students';
import { createAdminRuntime } from './core/runtime';
import {
  buildBulkMailPreviewHtml,
  escapeHtml,
  isSafeBulkMailUrl,
  safeAsaasHttpsUrl,
  safeSameOriginUrl,
  sanitizeBulkMailHtml,
} from './core/security';
import {
  formatStudentIdentityLabel,
  normalizeSearchText,
  resolveStudentIdentity,
} from './core/students';
import { initializeCoreTabsRuntime } from './core/tabs-runtime';
import { initializeDomainsAccounts } from './domains/accounts';
import { initializeDomainsAsaas } from './domains/asaas';
import { initializeDomainsAttendance } from './domains/attendance';
import { initializeDomainsBulkMail } from './domains/bulk-mail';
import { initializeDomainsCashflow } from './domains/cashflow';
import { initializeDomainsFamilyLinks } from './domains/family-links';
import { initializeDomainsManualCharges } from './domains/manual-charges';
import { initializeDomainsModularOffices } from './domains/modular-offices';
import { initializeDomainsMonthly } from './domains/monthly';
import { initializeDomainsOpenCharges } from './domains/open-charges';
import { initializeDomainsPendingSync } from './domains/pending-sync';
import { initializeDomainsViewAsUser } from './domains/view-as-user';
import { initializeState } from './state';

export function bootAdminDashboard(): boolean {
  if (window.__adminDashboardBooted === true) {
    return false;
  }

  installAdminFetchBridge();
  const runtime = createAdminRuntime();

  initializeState(runtime);
  initializeCoreTabsRuntime(runtime);
  initializeCoreDialogs(runtime);
  initializeCoreFormattersStudents(runtime);
  runtime.escapeHtml = escapeHtml;
  runtime.safeAsaasHttpsUrl = safeAsaasHttpsUrl;
  runtime.safeSameOriginUrl = safeSameOriginUrl;
  runtime.isSafeBulkMailUrl = isSafeBulkMailUrl;
  runtime.sanitizeBulkMailHtml = sanitizeBulkMailHtml;
  runtime.buildBulkMailPreviewHtml = buildBulkMailPreviewHtml;
  runtime.normalizeSearchText = normalizeSearchText;
  runtime.formatStudentIdentityLabel = formatStudentIdentityLabel;
  runtime.resolveStudentIdentityForAdmin = (input: unknown) =>
    resolveStudentIdentity(input, runtime.adminStudents);
  initializeDomainsMonthly(runtime);
  initializeDomainsCashflow(runtime);
  initializeDomainsAsaas(runtime);
  initializeDomainsManualCharges(runtime);
  initializeDomainsBulkMail(runtime);
  initializeDomainsModularOffices(runtime);
  initializeDomainsAttendance(runtime);
  initializeDomainsOpenCharges(runtime);
  initializeDomainsPendingSync(runtime);
  initializeDomainsAccounts(runtime);
  initializeDomainsFamilyLinks(runtime);
  initializeDomainsViewAsUser(runtime);
  initializeBoot(runtime);

  window.__adminDashboardBooted = true;
  console.info('[admin-dashboard] bootstrap ok', { tabs: runtime.tabs.length });
  return true;
}

bootAdminDashboard();
