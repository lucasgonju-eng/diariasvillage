import type { AdminRuntime, RuntimeValue } from './core/runtime';

export function initializeBoot(runtime: AdminRuntime): void {
  runtime.initialTab = document.body?.dataset?.activeTab || 'charges';
  
  runtime.rebuildMonthlyMaps();
  
  runtime.renderMonthlyTable();
  
  runtime.renderAttendanceDayQueue();
  
  runtime.setActiveTab(runtime.initialTab);
  
  if (runtime.initialTab === 'inadimplentes') {
      runtime.maybeAlertInadimplentesDuplicates();
      runtime.maybeAlertInadimplentesMonthly();
  }
  
  if (runtime.initialTab === 'chamada') {
      runtime.loadAttendanceOffices();
      runtime.loadAttendanceCalls();
  }
  
  if (runtime.initialTab === 'oficinas-modulares') {
      runtime.loadModularOffices();
  }
  
  if (runtime.initialTab === 'dados-asaas') {
      runtime.loadAsaasData();
  }
  
  if (runtime.initialTab === 'email-massa') {
      runtime.loadBulkMailData();
  }
  
  runtime.loadStudents();
  
  runtime.buildInadimplentesStudentAutocomplete();
  
  runtime.applyInadimplentesStudentFilter();
  
  runtime.updateInadimplentesSummary();
}
