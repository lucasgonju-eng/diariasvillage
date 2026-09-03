import type { AdminRuntime, RuntimeValue } from './runtime';

export function initializeCoreTabsRuntime(runtime: AdminRuntime): void {
  runtime.setActiveTab = function setActiveTab(name: RuntimeValue) {
      const activeName = String(name || '').trim();
      if (!document.getElementById(`tab-${activeName}`)) {
          return;
      }
      document.querySelectorAll<HTMLElement>('section[id^="tab-"]').forEach((section) => {
          section.classList.toggle('hidden', section.id !== `tab-${activeName}`);
      });
      runtime.tabs.forEach((btn: RuntimeValue) => {
          const isActive = btn.dataset.tab === activeName;
          btn.classList.toggle('btn-primary', isActive);
          btn.classList.toggle('admin-tab', !isActive);
          btn.style.opacity = isActive ? '1' : '0.95';
      });
  };
  
  runtime.tabs.forEach((btn: RuntimeValue) => {
      btn.addEventListener('click', () => {
          runtime.setActiveTab(btn.dataset.tab);
          if (btn.dataset.tab === 'inadimplentes') {
              runtime.maybeAlertInadimplentesDuplicates();
              runtime.maybeAlertInadimplentesMonthly();
          }
          if (btn.dataset.tab === 'chamada') {
              runtime.loadAttendanceOffices();
              runtime.loadAttendanceCalls(true);
          }
          if (btn.dataset.tab === 'oficinas-modulares') {
              runtime.loadModularOffices();
          }
          if (btn.dataset.tab === 'fluxo-caixa' && !runtime.cashflowLoaded) {
              runtime.loadCashflow();
          }
          if (btn.dataset.tab === 'dados-asaas' && !runtime.asaasDataLoaded) {
              runtime.loadAsaasData();
          }
          if (btn.dataset.tab === 'email-massa') {
              runtime.loadBulkMailData();
          }
      });
  });
}
