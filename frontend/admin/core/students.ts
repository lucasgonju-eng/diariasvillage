import type {
  AdminStudent,
  StudentIdentityResolution,
} from './types';

export function normalizeSearchText(value: unknown): string {
  return String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();
}

export function formatStudentIdentityLabel(student: AdminStudent): string {
  const name = String(student.name ?? '').trim();
  const enrollment = String(student.enrollment ?? '').trim();
  const id = String(student.id ?? '').trim();
  if (enrollment) return `${name} • Matrícula ${enrollment}`;
  return id ? `${name} • ID ${id.slice(0, 8)}` : name;
}

export function resolveStudentIdentity(
  rawInput: unknown,
  students: readonly AdminStudent[],
): StudentIdentityResolution {
  const input = String(rawInput ?? '').trim();
  if (!input) {
    return { ok: false, error: 'Digite o nome ou a matrícula do aluno.' };
  }

  const exactLabel = students.find(
    (student) => formatStudentIdentityLabel(student) === input,
  );
  if (exactLabel?.id) {
    return {
      ok: true,
      student: exactLabel,
      id: String(exactLabel.id),
      name: String(exactLabel.name ?? ''),
      label: formatStudentIdentityLabel(exactLabel),
    };
  }

  const normalizedInput = normalizeSearchText(input);
  const candidates = students.filter((student) => {
    const name = normalizeSearchText(student.name);
    const enrollment = normalizeSearchText(student.enrollment);
    return name === normalizedInput || enrollment === normalizedInput;
  });

  const student = candidates[0];
  if (candidates.length !== 1 || !student?.id) {
    return {
      ok: false,
      error: candidates.length > 1
        ? 'Mais de um aluno corresponde ao texto. Selecione a opção com matrícula.'
        : 'Selecione o aluno na lista com a matrícula correspondente.',
    };
  }

  return {
    ok: true,
    student,
    id: String(student.id),
    name: String(student.name ?? ''),
    label: formatStudentIdentityLabel(student),
  };
}

