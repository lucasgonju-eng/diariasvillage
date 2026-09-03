import { describe, expect, it } from 'vitest';
import { formatStudentIdentityLabel, resolveStudentIdentity } from './students';
import type { AdminStudent } from './types';

const students: AdminStudent[] = [
  {
    id: '11111111-1111-4111-8111-111111111111',
    name: 'Ana Souza',
    enrollment: 'MAT-101',
  },
  {
    id: '22222222-2222-4222-8222-222222222222',
    name: 'Ana Souza',
    enrollment: 'MAT-202',
  },
];

describe('resolução explícita de aluno', () => {
  it('bloqueia nome homônimo sem matrícula', () => {
    expect(resolveStudentIdentity('Ana Souza', students)).toEqual({
      ok: false,
      error: 'Mais de um aluno corresponde ao texto. Selecione a opção com matrícula.',
    });
  });

  it('resolve o rótulo escolhido para o UUID correto', () => {
    const selected = students[1]!;
    const result = resolveStudentIdentity(
      formatStudentIdentityLabel(selected),
      students,
    );

    expect(result.ok).toBe(true);
    if (result.ok) {
      expect(result.id).toBe('22222222-2222-4222-8222-222222222222');
      expect(result.student).toBe(selected);
    }
  });
});

