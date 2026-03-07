const form = document.querySelector('#register-form');
const message = document.querySelector('#form-message');
const openPendingButton = document.querySelector('#open-pending');
const pendingForm = document.querySelector('#pending-form');
const pendingMessage = document.querySelector('#pending-message');
const cpfInput = document.querySelector('#cpf');
const pendingCpfInput = document.querySelector('#pending-cpf');
const studentNameInput = document.querySelector('#student-name');
const searchStudentButton = document.querySelector('#search-student');
const studentConfirmWrap = document.querySelector('#student-confirm-wrap');
const studentCandidateSelect = document.querySelector('#student-candidate');
const studentConfirmDetails = document.querySelector('#student-confirm-details');
const studentIdInput = document.querySelector('#student-id');

let studentCandidates = [];

function applyCpfMask(value) {
  const digits = value.replace(/\D/g, '').slice(0, 11);
  let masked = digits;
  if (digits.length > 3) masked = `${digits.slice(0, 3)}.${digits.slice(3)}`;
  if (digits.length > 6) masked = `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;
  if (digits.length > 9) masked = `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
  return masked;
}

function sanitizeDigits(value) {
  return String(value || '').replace(/\D/g, '');
}

function resetStudentConfirmation() {
  studentCandidates = [];
  if (studentConfirmWrap) studentConfirmWrap.style.display = 'none';
  if (studentCandidateSelect) studentCandidateSelect.innerHTML = '';
  if (studentConfirmDetails) studentConfirmDetails.textContent = '';
  if (studentIdInput) studentIdInput.value = '';
}

function candidateLabel(candidate) {
  const serie = candidate.grade ? `${candidate.grade}º ano` : 'Série não informada';
  const turma = candidate.class_name || 'Turma não informada';
  const matricula = candidate.enrollment || 'Matrícula não informada';
  return `${candidate.name} • ${serie} • ${turma} • Matrícula ${matricula}`;
}

function updateStudentDetailsBySelectedOption() {
  if (!studentCandidateSelect || !studentConfirmDetails || !studentIdInput) return;
  const idx = Number(studentCandidateSelect.value);
  const candidate = Number.isInteger(idx) ? studentCandidates[idx] : null;
  if (!candidate) {
    studentConfirmDetails.textContent = '';
    studentIdInput.value = '';
    return;
  }
  studentIdInput.value = candidate.id || '';
  const serie = candidate.grade ? `${candidate.grade}º ano` : 'Série não informada';
  const turma = candidate.class_name || 'Turma não informada';
  const matricula = candidate.enrollment || 'Matrícula não informada';
  studentConfirmDetails.textContent = `Nome: ${candidate.name} | Série: ${serie} | Turma: ${turma} | Matrícula: ${matricula}`;
}

async function searchStudentsForFirstAccess() {
  if (!cpfInput || !studentNameInput) return;
  const cpfDigits = sanitizeDigits(cpfInput.value);
  const studentName = studentNameInput.value.trim();
  if (cpfDigits.length !== 11) {
    resetStudentConfirmation();
    if (message) {
      message.textContent = 'Informe um CPF válido antes de buscar o aluno(a).';
      message.className = 'error';
    }
    return;
  }
  if (studentName.length < 3) {
    resetStudentConfirmation();
    if (message) {
      message.textContent = 'Digite pelo menos 3 letras do nome do aluno(a).';
      message.className = 'error';
    }
    return;
  }

  if (searchStudentButton) {
    searchStudentButton.disabled = true;
    searchStudentButton.textContent = 'Buscando...';
  }
  if (message) {
    message.textContent = '';
    message.className = '';
  }

  try {
    const res = await fetch('/api/primeiro-acesso-students.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ cpf: cpfInput.value.trim(), student_name: studentName }),
    });
    const data = await res.json();
    if (!res.ok || !data.ok || !Array.isArray(data.candidates)) {
      resetStudentConfirmation();
      if (message) {
        message.textContent = (data && data.error) ? data.error : 'Não foi possível buscar os dados do aluno(a).';
        message.className = 'error';
      }
      return;
    }
    studentCandidates = data.candidates;
    if (!studentCandidates.length) {
      resetStudentConfirmation();
      if (message) {
        message.textContent = 'Nenhum aluno(a) encontrado com esse CPF e nome. Revise os dados.';
        message.className = 'error';
      }
      return;
    }
    if (studentCandidateSelect) {
      studentCandidateSelect.innerHTML = studentCandidates
        .map((candidate, idx) => `<option value="${idx}">${candidateLabel(candidate)}</option>`)
        .join('');
      studentCandidateSelect.value = '0';
    }
    if (studentConfirmWrap) studentConfirmWrap.style.display = 'block';
    updateStudentDetailsBySelectedOption();
    if (message) {
      message.textContent = 'Confira os dados do aluno(a) antes de concluir o cadastro.';
      message.className = 'success';
    }
  } catch (error) {
    resetStudentConfirmation();
    if (message) {
      message.textContent = 'Erro ao buscar aluno(a). Tente novamente.';
      message.className = 'error';
    }
  } finally {
    if (searchStudentButton) {
      searchStudentButton.disabled = false;
      searchStudentButton.textContent = 'Buscar aluno(a)';
    }
  }
}

if (cpfInput) {
  cpfInput.addEventListener('input', (event) => {
    event.target.value = applyCpfMask(event.target.value);
    resetStudentConfirmation();
  });
}

if (pendingCpfInput) {
  pendingCpfInput.addEventListener('input', (event) => {
    event.target.value = applyCpfMask(event.target.value);
  });
}

if (studentNameInput) {
  studentNameInput.addEventListener('input', () => {
    resetStudentConfirmation();
  });
}

if (searchStudentButton) {
  searchStudentButton.addEventListener('click', () => {
    searchStudentsForFirstAccess();
  });
}

if (studentCandidateSelect) {
  studentCandidateSelect.addEventListener('change', updateStudentDetailsBySelectedOption);
}

if (form) {
  if (message) {
    message.textContent = '';
    message.className = '';
  }
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    message.textContent = '';
    message.className = '';
    const submitBtn = form.querySelector('button[type="submit"]');
    const btnOriginalText = submitBtn ? submitBtn.textContent : '';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Criando...';
    }

    try {
      const payload = {
        cpf: document.querySelector('#cpf').value.trim(),
        student_id: document.querySelector('#student-id').value.trim(),
        email: document.querySelector('#email').value.trim(),
        password: document.querySelector('#password').value,
        password_confirm: document.querySelector('#password-confirm').value,
      };
      if (!payload.student_id) {
        message.textContent = 'Busque e confirme o aluno(a) antes de criar a conta.';
        message.className = 'error';
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = btnOriginalText;
        }
        return;
      }

      const res = await fetch('/api/register-primeiro-acesso.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      const text = await res.text();
      let data = {};
      try {
        data = JSON.parse(text);
      } catch (_) {
        message.textContent = 'Resposta inválida do servidor. Verifique o console (F12) para detalhes.';
        message.className = 'error';
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = btnOriginalText;
        }
        return;
      }

      if (!data.ok) {
        message.textContent = data.error || 'Não foi possível cadastrar. Tente novamente.';
        message.className = 'error';
        if (
          data.error &&
          (data.error.toLowerCase().includes('não encontrado') ||
            data.error.toLowerCase().includes('nao encontrado'))
        ) {
          if (pendingForm && openPendingButton) {
            pendingForm.style.display = 'block';
            openPendingButton.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const pendingCpf = document.querySelector('#pending-cpf');
            if (pendingCpf && cpfInput && !pendingCpf.value) {
              pendingCpf.value = cpfInput.value.trim();
            }
          }
        }
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = btnOriginalText;
        }
        return;
      }

      message.textContent = 'Conta criada! Você já pode fazer login com seu CPF e senha.';
      message.className = 'success';
      form.reset();
    } catch (err) {
      message.textContent = 'Erro ao enviar: ' + (err.message || 'Tente novamente.');
      message.className = 'error';
    }
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = btnOriginalText;
    }
  });
}

if (openPendingButton && pendingForm) {
  openPendingButton.addEventListener('click', () => {
    pendingForm.style.display = pendingForm.style.display === 'none' ? 'block' : 'none';
    if (pendingForm.style.display === 'block') {
      const pendingCpf = document.querySelector('#pending-cpf');
      const pendingEmail = document.querySelector('#pending-email');
      if (pendingCpf && cpfInput && !pendingCpf.value) {
        pendingCpf.value = cpfInput.value.trim();
      }
      if (pendingEmail) {
        const emailEl = document.querySelector('#email');
        if (emailEl && !pendingEmail.value) {
          pendingEmail.value = emailEl.value.trim();
        }
      }
      const pendingGuardian = document.querySelector('#pending-guardian');
      if (pendingGuardian && !pendingGuardian.value) {
        pendingGuardian.focus();
      }
    }
  });
}

if (pendingForm) {
  pendingForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (pendingMessage) {
      pendingMessage.textContent = '';
      pendingMessage.className = '';
    }

    const payload = {
      student_name: document.querySelector('#pending-student').value.trim(),
      guardian_name: document.querySelector('#pending-guardian').value.trim(),
      guardian_cpf: document.querySelector('#pending-cpf').value.trim(),
      guardian_email: document.querySelector('#pending-email').value.trim(),
      payment_date: document.querySelector('#pending-day-use-date')?.value || '',
    };
    if (
      !payload.student_name ||
      !payload.guardian_name ||
      !payload.guardian_cpf ||
      !payload.guardian_email ||
      !payload.payment_date
    ) {
      if (pendingMessage) {
        pendingMessage.textContent = 'Preencha nome do aluno, responsável, CPF, e-mail e data do day-use.';
        pendingMessage.className = 'error';
      }
      return;
    }

    try {
      const res = await fetch('/api/pendencia-cadastro.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      const data = await res.json();
      if (!data.ok) {
        if (pendingMessage) {
          pendingMessage.textContent = data.error || 'Falha ao enviar pendência.';
          pendingMessage.className = 'error';
        }
        return;
      }

      if (pendingMessage) {
        pendingMessage.textContent = 'Pendência enviada. Verifique seu e-mail para confirmar.';
        pendingMessage.className = 'success';
      }
      pendingForm.reset();
    } catch (error) {
      if (pendingMessage) {
        pendingMessage.textContent = 'Falha ao enviar pendência. Tente novamente.';
        pendingMessage.className = 'error';
      }
    }
  });
}
