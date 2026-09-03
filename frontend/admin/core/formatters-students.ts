import type { AdminRuntime, RuntimeValue } from './runtime';

export function initializeCoreFormattersStudents(runtime: AdminRuntime): void {
  runtime.getCashflowDefaultFromDate = function getCashflowDefaultFromDate() {
      const now = new Date();
      const year = now.getFullYear();
      return `${year}-01-05`;
  };
  
  runtime.formatCurrency = function formatCurrency(value: RuntimeValue) {
      return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value || 0));
  };
  
  runtime.formatDateBR = function formatDateBR(value: RuntimeValue) {
      if (!value)
          return '-';
      const raw = String(value).trim();
      const isoPrefix = /^(\d{4})-(\d{2})-(\d{2})/.exec(raw);
      if (isoPrefix) {
          const [, year, month, day] = isoPrefix;
          return `${day}/${month}/${year}`;
      }
      const date = new Date(value);
      if (Number.isNaN(date.getTime()))
          return value;
      return date.toLocaleDateString('pt-BR');
  };
  
  runtime.getBulkMailVisualDocument = function getBulkMailVisualDocument() {
      return runtime.bulkMailVisualInput instanceof HTMLIFrameElement
          ? runtime.bulkMailVisualInput.contentDocument
          : null;
  };
  
  runtime.normalizeSearchText = function normalizeSearchText(value: RuntimeValue) {
      return String(value || '')
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '')
          .toLowerCase()
          .trim();
  };
  
  runtime.formatStudentIdentityLabel = function formatStudentIdentityLabel(student: RuntimeValue) {
      const name = String(student?.name || '').trim();
      const enrollment = String(student?.enrollment || '').trim();
      const id = String(student?.id || '').trim();
      if (enrollment)
          return `${name} • Matrícula ${enrollment}`;
      return id ? `${name} • ID ${id.slice(0, 8)}` : name;
  };
  
  runtime.updateViewUserAutocompleteOptions = function updateViewUserAutocompleteOptions(rawQuery: RuntimeValue) {
      if (!runtime.viewUserStudentsList)
          return;
      const query = runtime.normalizeSearchText(rawQuery);
      runtime.viewUserStudentsList.innerHTML = '';
      runtime.viewUserStudentLookupByLabel.clear();
      if (query.length < runtime.MIN_ADMIN_AUTOCOMPLETE_CHARS)
          return;
      const seen = new Set();
      const startsWith: RuntimeValue[] = [];
      const contains: RuntimeValue[] = [];
      runtime.adminStudents.forEach((student: RuntimeValue) => {
          const name = String(student.name || '').trim();
          const enrollment = String(student.enrollment || '').trim();
          const studentId = String(student.id || '').trim();
          if (!name || !studentId || seen.has(studentId))
              return;
          const nameKey = runtime.normalizeSearchText(name);
          const enrollmentKey = runtime.normalizeSearchText(enrollment);
          const label = runtime.formatStudentIdentityLabel(student);
          if (nameKey.startsWith(query) || enrollmentKey.startsWith(query)) {
              startsWith.push({ student, label });
              seen.add(studentId);
              return;
          }
          if (nameKey.includes(query) || enrollmentKey.includes(query)) {
              contains.push({ student, label });
              seen.add(studentId);
          }
      });
      [...startsWith, ...contains].slice(0, 40).forEach(({ student, label }: RuntimeValue) => {
          const option = document.createElement('option');
          option.value = label;
          runtime.viewUserStudentsList.appendChild(option);
          runtime.viewUserStudentLookupByLabel.set(label, student);
      });
  };
  
  runtime.resolveStudentIdentityForAdmin = function resolveStudentIdentityForAdmin(rawInput: RuntimeValue) {
      const input = String(rawInput || '').trim();
      if (!input) {
          return { ok: false, error: 'Digite o nome ou a matrícula do aluno.' };
      }
      const exactLabel = runtime.adminStudents.find((student: RuntimeValue) => runtime.formatStudentIdentityLabel(student) === input);
      if (exactLabel?.id) {
          return {
              ok: true,
              student: exactLabel,
              id: String(exactLabel.id),
              name: String(exactLabel.name || ''),
              label: runtime.formatStudentIdentityLabel(exactLabel),
          };
      }
      const normalizedInput = runtime.normalizeSearchText(input);
      const candidates = runtime.adminStudents.filter((student: RuntimeValue) => {
          const name = runtime.normalizeSearchText(student?.name);
          const enrollment = runtime.normalizeSearchText(student?.enrollment);
          return name === normalizedInput || enrollment === normalizedInput;
      });
      if (candidates.length !== 1 || !candidates[0]?.id) {
          return {
              ok: false,
              error: candidates.length > 1
                  ? 'Mais de um aluno corresponde ao texto. Selecione a opção com matrícula.'
                  : 'Selecione o aluno na lista com a matrícula correspondente.',
          };
      }
      const student = candidates[0];
      return {
          ok: true,
          student,
          id: String(student.id),
          name: String(student.name || ''),
          label: runtime.formatStudentIdentityLabel(student),
      };
  };
}
