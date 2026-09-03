import type { AdminRuntime, RuntimeValue } from './core/runtime';

export function initializeState(runtime: AdminRuntime): void {
  runtime.tabs = document.querySelectorAll('[data-tab]');
  
  runtime.adminCsrfToken = document.querySelector<HTMLMetaElement>('meta[name="admin-csrf-token"]')?.content || '';
  
  runtime.tabEntries = document.querySelector('#tab-entries');
  
  runtime.tabCharges = document.querySelector('#tab-charges');
  
  runtime.tabChamada = document.querySelector('#tab-chamada');
  
  runtime.tabInadimplentes = document.querySelector('#tab-inadimplentes');
  
  runtime.tabRecebidas = document.querySelector('#tab-recebidas');
  
  runtime.tabSemWhatsapp = document.querySelector('#tab-sem-whatsapp');
  
  runtime.tabDuplicados = document.querySelector('#tab-duplicados');
  
  runtime.tabPendencias = document.querySelector('#tab-pendencias');
  
  runtime.tabFamilias = document.querySelector('#tab-familias');
  
  runtime.tabMensalistas = document.querySelector('#tab-mensalistas');
  
  runtime.tabOficinasModulares = document.querySelector('#tab-oficinas-modulares');
  
  runtime.tabExclusoes = document.querySelector('#tab-exclusoes');
  
  runtime.tabResetSenha = document.querySelector('#tab-reset-senha');
  
  runtime.tabAcessoSecretaria = document.querySelector('#tab-acesso-secretaria');
  
  runtime.tabFluxoCaixa = document.querySelector('#tab-fluxo-caixa');
  
  runtime.tabDadosAsaas = document.querySelector('#tab-dados-asaas');
  
  runtime.tabEmailMassa = document.querySelector('#tab-email-massa');
  
  runtime.studentInput = document.querySelector('#charge-student');
  
  runtime.studentList = document.querySelector('#students-list');
  
  runtime.chargeList = document.querySelector('#charge-list');
  
  runtime.sendChargesButton = document.querySelector('#send-charges');
  
  runtime.chargeMessage = document.querySelector('#charge-message');
  
  runtime.sendSelectedPendingButton = document.querySelector('#send-selected-pending');
  
  runtime.selectAllPendingInput = document.querySelector('#select-all-pending');
  
  runtime.sendPendingMessage = document.querySelector('#send-pending-message');
  
  runtime.inadimplentesStudentFilterInput = document.querySelector('#inadimplentes-student-filter');
  
  runtime.inadimplentesStudentFilterList = document.querySelector('#inadimplentes-students-list');
  
  runtime.inadimplentesStudentFilterClearButton = document.querySelector('#inadimplentes-student-filter-clear');
  
  runtime.syncChargesPaymentsInadimplentesButton = document.querySelector('#sync-charges-payments-inadimplentes-btn');
  
  runtime.syncChargesPaymentsInadimplentesMessage = document.querySelector('#sync-charges-payments-inadimplentes-message');
  
  runtime.inadimplentesSummary = document.querySelector('#inadimplentes-summary');
  
  runtime.pendingDeleteButtons = document.querySelectorAll('.js-delete-payment');
  
  runtime.isabelVoucherButtons = document.querySelectorAll('.js-isabel-voucher');
  
  runtime.resendFebChargeButtons = document.querySelectorAll('.js-resend-feb-charge');
  
  runtime.inadimplentesDuplicatesPopupShown = false;
  
  runtime.inadimplentesMonthlyPopupShown = false;
  
  runtime.syncRecebidasButton = document.querySelector('#sync-recebidas-btn');
  
  runtime.syncRecebidasMessage = document.querySelector('#sync-recebidas-message');
  
  runtime.viewUserStudentInput = document.querySelector('#admin-view-user-student');
  
  runtime.viewUserStudentsList = document.querySelector('#admin-students-list');
  
  runtime.viewUserGuardianSelect = document.querySelector('#admin-view-user-guardian');
  
  runtime.pendenciaStudentsList = document.querySelector('#pendencia-students-list');
  
  runtime.viewUserButton = document.querySelector('#admin-view-user-btn');
  
  runtime.addGuardianButton = document.querySelector('#admin-add-guardian-btn');
  
  runtime.viewUserForm = document.querySelector('#admin-view-user-form');
  
  runtime.viewUserStudentNameInput = document.querySelector('#view-user-student-name');
  
  runtime.viewUserStudentIdInput = document.querySelector('#view-user-student-id');
  
  runtime.viewUserParentNameInput = document.querySelector('#view-user-parent-name');
  
  runtime.viewUserParentEmailInput = document.querySelector('#view-user-parent-email');
  
  runtime.viewUserParentPhoneInput = document.querySelector('#view-user-parent-phone');
  
  runtime.viewUserParentDocumentInput = document.querySelector('#view-user-parent-document');
  
  runtime.viewUserForceCreateInput = document.querySelector('#view-user-force-create');
  
  runtime.viewUserSaveGuardianButton = document.querySelector('#view-user-save-guardian');
  
  runtime.viewUserCancelGuardianButton = document.querySelector('#view-user-cancel-guardian');
  
  runtime.viewUserFormMessage = document.querySelector('#view-user-form-message');
  
  runtime.monthlyStudentInput = document.querySelector('#monthly-student');

  runtime.monthlyStudentsList = document.querySelector('#monthly-students-list');
  
  runtime.monthlySaveButton = document.querySelector('#monthly-save-btn');
  
  runtime.monthlyRemoveButton = document.querySelector('#monthly-remove-btn');
  
  runtime.monthlyMessage = document.querySelector('#monthly-message');
  
  runtime.monthlyTableBody = document.querySelector('#monthly-table-body');
  
  runtime.attendanceDateInput = document.querySelector('#attendance-date');
  
  runtime.attendanceStudentInput = document.querySelector('#attendance-student');
  
  runtime.attendanceOfficeInput = document.querySelector('#attendance-office');
  
  runtime.attendanceAddButton = document.querySelector('#attendance-add-btn');
  
  runtime.attendanceCloseDayButton = document.querySelector('#attendance-close-day-btn');
  
  runtime.attendanceGoInadimplentesButton = document.querySelector('#attendance-go-inadimplentes-btn');
  
  runtime.attendanceMessage = document.querySelector('#attendance-message');
  
  runtime.attendanceTbody = document.querySelector('#attendance-tbody');
  
  runtime.attendanceDayList = document.querySelector('#attendance-day-list');
  
  runtime.attendanceFilterFromInput = document.querySelector('#attendance-filter-from');
  
  runtime.attendanceFilterToInput = document.querySelector('#attendance-filter-to');
  
  runtime.attendancePendingOnlyInput = document.querySelector('#attendance-pending-only');
  
  runtime.attendanceFilterButton = document.querySelector('#attendance-filter-btn');
  
  runtime.attendanceClearButton = document.querySelector('#attendance-clear-btn');
  
  runtime.attendanceExportButton = document.querySelector('#attendance-export-btn');
  
  runtime.attendanceStudentsList = document.querySelector('#attendance-students-list');
  
  runtime.attendanceOfficesList = document.querySelector('#attendance-offices-list');
  
  runtime.modularCatalogList = document.querySelector('#modular-catalog-list');
  
  runtime.modularTeachersList = document.querySelector('#modular-teachers-list');
  
  runtime.modularCreateMonthInput = document.querySelector('#modular-create-month');
  
  runtime.modularCreateYearInput = document.querySelector('#modular-create-year');
  
  runtime.modularCreateNameInput = document.querySelector('#modular-create-name');
  
  runtime.modularCreateTeacherInput = document.querySelector('#modular-create-teacher');
  
  runtime.modularCreateWeekSlotInputs = document.querySelectorAll('input[name="modular-create-week-slot"]');
  
  runtime.modularCreateButton = document.querySelector('#modular-create-btn');
  
  runtime.modularCreateMessage = document.querySelector('#modular-create-message');
  
  runtime.modularPreviewDayInput = document.querySelector('#modular-preview-day');
  
  runtime.modularPreviewAluno1400 = document.querySelector('#modular-preview-aluno-1400');
  
  runtime.modularPreviewAluno1540 = document.querySelector('#modular-preview-aluno-1540');
  
  runtime.modularPreviewSecretariaBody = document.querySelector('#modular-preview-secretaria-body');
  
  runtime.modularPreviewAdminBody = document.querySelector('#modular-preview-admin-body');
  
  runtime.selectedStudents = new Set();
  
  runtime.guardianCache = new Map();
  
  runtime.studentLookupByLabel = new Map();
  
  runtime.viewUserStudentLookupByLabel = new Map();
  
  runtime.MIN_ADMIN_AUTOCOMPLETE_CHARS = 1;
  
  runtime.adminStudents = [];
  
  runtime.monthlyStudents = Array.isArray(window.__monthlyStudents) ? window.__monthlyStudents : [];
  
  runtime.monthlyByStudentId = new Map();
  
  runtime.adminCanApproveAttendance = window.__adminCanApproveAttendance === true;
  
  runtime.attendanceOfficeById = new Map();
  
  runtime.attendanceOfficeByLabel = new Map();
  
  runtime.attendanceLoaded = false;
  
  runtime.attendanceOfficesLoaded = false;
  
  runtime.attendanceDayQueue = [];
  
  runtime.modularOfficesLoaded = false;
  
  runtime.modularOffices = [];
  
  runtime.cashflowFromInput = document.querySelector('#cashflow-from');
  
  runtime.cashflowToInput = document.querySelector('#cashflow-to');
  
  runtime.cashflowStudentInput = document.querySelector('#cashflow-student');
  
  runtime.cashflowEnrollmentInput = document.querySelector('#cashflow-enrollment');
  
  runtime.cashflowDayTypeInput = document.querySelector('#cashflow-day-type');
  
  runtime.cashflowStatusInput = document.querySelector('#cashflow-status');
  
  runtime.cashflowBillingTypeInput = document.querySelector('#cashflow-billing-type');
  
  runtime.cashflowMonthlyModeInput = document.querySelector('#cashflow-monthly-mode');
  
  runtime.cashflowExcludeStudentInput = document.querySelector('#cashflow-exclude-student');
  
  runtime.cashflowExcludeTermInput = document.querySelector('#cashflow-exclude-term');
  
  runtime.cashflowSearchButton = document.querySelector('#cashflow-search');
  
  runtime.cashflowClearButton = document.querySelector('#cashflow-clear');
  
  runtime.cashflowMessage = document.querySelector('#cashflow-message');
  
  runtime.cashflowSummary = document.querySelector('#cashflow-summary');
  
  runtime.cashflowTbody = document.querySelector('#cashflow-tbody');
  
  runtime.cashflowTotalAmountCell = document.querySelector('#cashflow-total-amount');
  
  runtime.cashflowTotalPaidCell = document.querySelector('#cashflow-total-paid');
  
  runtime.cashflowTotalCountCell = document.querySelector('#cashflow-total-count');
  
  runtime.cashflowLoaded = false;
  
  runtime.asaasDataRefreshButton = document.querySelector('#asaas-data-refresh');
  
  runtime.asaasDataExportButton = document.querySelector('#asaas-data-export');
  
  runtime.asaasDataMessage = document.querySelector('#asaas-data-message');
  
  runtime.asaasDataSummary = document.querySelector('#asaas-data-summary');
  
  runtime.asaasPaidTbody = document.querySelector('#asaas-paid-tbody');
  
  runtime.asaasPendingTbody = document.querySelector('#asaas-pending-tbody');
  
  runtime.asaasOverdueTbody = document.querySelector('#asaas-overdue-tbody');
  
  runtime.asaasKpis = document.querySelector('#asaas-kpis');
  
  runtime.asaasDailyBars = document.querySelector('#asaas-daily-bars');
  
  runtime.asaasCompositionBars = document.querySelector('#asaas-composition-bars');
  
  runtime.asaasTopAdimplentes = document.querySelector('#asaas-top-adimplentes');
  
  runtime.asaasTopInadimplentes = document.querySelector('#asaas-top-inadimplentes');
  
  runtime.asaasDataLoaded = false;
  
  runtime.asaasDataLastPayload = null;
  
  runtime.bulkMailFilterInput = document.querySelector('#bulk-mail-filter');
  
  runtime.bulkMailGradeFilterInput = document.querySelector('#bulk-mail-grade-filter');
  
  runtime.bulkMailTypeFilterInput = document.querySelector('#bulk-mail-type-filter');
  
  runtime.bulkMailSelectAllInput = document.querySelector('#bulk-mail-select-all');
  
  runtime.bulkMailRecipientsBody = document.querySelector('#bulk-mail-recipients-body');
  
  runtime.bulkMailCounter = document.querySelector('#bulk-mail-counter');
  
  runtime.bulkMailTemplateSelect = document.querySelector('#bulk-mail-template-select');
  
  runtime.bulkMailTemplateLoadButton = document.querySelector('#bulk-mail-template-load');
  
  runtime.bulkMailTemplateSaveButton = document.querySelector('#bulk-mail-template-save');
  
  runtime.bulkMailSubjectInput = document.querySelector('#bulk-mail-subject');
  
  runtime.bulkMailHtmlInput = document.querySelector('#bulk-mail-html');
  
  runtime.bulkMailVisualInput = document.querySelector('#bulk-mail-visual');
  
  runtime.bulkMailSendButton = document.querySelector('#bulk-mail-send');
  
  runtime.bulkMailSendTestButton = document.querySelector('#bulk-mail-send-test');
  
  runtime.bulkMailMessage = document.querySelector('#bulk-mail-message');
  
  runtime.bulkMailLoaded = false;
  
  runtime.bulkMailStudents = [];
  
  runtime.bulkMailTemplates = [];
  
  runtime.bulkMailSelectedIds = new Set();
  
  runtime.bulkMailSyncingEditors = false;
  
  runtime.bulkMailVisualBoundDocuments = new WeakSet();
  
  runtime.adminDialogInstance = null;
  
  runtime.mergeMessage = document.querySelector('#merge-message');
  
  runtime.pendenciaMessage = document.querySelector('#pendencia-message');
  
  runtime.pendenciaButtons = document.querySelectorAll('.js-check-pendencia');
  
  runtime.pendenciaCpfInput = document.querySelector('#pendencia-cpf');
  
  runtime.pendenciaCpfButton = document.querySelector('#check-pendencia-cpf');
  
  runtime.pendenciaAsaasInput = document.querySelector('#pendencia-asaas-id');
  
  runtime.pendenciaAsaasButton = document.querySelector('#check-pendencia-asaas');
  
  runtime.pendenciaLinkButtons = document.querySelectorAll('.js-link-asaas');
  
  runtime.pendenciaSettleButtons = document.querySelectorAll('.js-settle-pendencia');
  
  runtime.pendenciaDeleteButtons = document.querySelectorAll('.js-delete-pendencia');
  
  runtime.pendenciaLinkStudentButtons = document.querySelectorAll('.js-pendencia-link-student');
  
  runtime.pendenciaCreateStudentButtons = document.querySelectorAll('.js-pendencia-create-student');
  
  runtime.syncChargesPaymentsButton = document.querySelector('#sync-charges-payments-btn');
  
  runtime.syncChargesPaymentsMessage = document.querySelector('#sync-charges-payments-message');
  
  runtime.mergeButtons = document.querySelectorAll('.js-merge-duplicates');
  
  runtime.resetCpfInput = document.querySelector('#reset-cpf');
  
  runtime.resetLookupBtn = document.querySelector('#reset-lookup-btn');
  
  runtime.resetGuardianSelect = document.querySelector('#reset-guardian');
  
  runtime.resetSenhaNovaInput = document.querySelector('#reset-senha-nova');
  
  runtime.resetSenhaConfirmInput = document.querySelector('#reset-senha-confirm');
  
  runtime.resetSenhaBtn = document.querySelector('#reset-senha-btn');
  
  runtime.resetSenhaMessage = document.querySelector('#reset-senha-message');
  
  runtime.secretariaPasswordInput = document.querySelector('#secretaria-password');
  
  runtime.secretariaPasswordConfirmInput = document.querySelector('#secretaria-password-confirm');
  
  runtime.secretariaPasswordSaveButton = document.querySelector('#secretaria-password-save');
  
  runtime.secretariaPasswordMessage = document.querySelector('#secretaria-password-message');
}
