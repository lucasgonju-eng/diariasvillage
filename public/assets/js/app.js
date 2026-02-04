const form = document.querySelector('#register-form');
const studentsInput = document.querySelector('#student-name');
const studentsList = document.querySelector('#students-list');
const studentsSelect = document.querySelector('#student-select');
const message = document.querySelector('#form-message');

function isIOS() {
  return /iPad|iPhone|iPod/.test(navigator.userAgent);
}

async function loadStudents() {
  const res = await fetch('/api/students.php');
  const data = await res.json();
  if (!data.ok) {
    message.textContent = data.error || 'Falha ao carregar alunos.';
    message.className = 'error';
    return;
  }

  studentsList.innerHTML = '';
  if (studentsSelect) {
    studentsSelect.innerHTML = '';
  }
  data.students.forEach((student) => {
    const option = document.createElement('option');
    option.value = student.name;
    studentsList.appendChild(option);
    if (studentsSelect) {
      const selectOption = document.createElement('option');
      selectOption.value = student.name;
      selectOption.textContent = student.name;
      studentsSelect.appendChild(selectOption);
    }
  });
}

if (form) {
  loadStudents();
  if (studentsSelect && studentsInput && isIOS()) {
    studentsSelect.style.display = 'block';
    studentsInput.style.display = 'none';
    studentsSelect.addEventListener('change', () => {
      studentsInput.value = studentsSelect.value;
    });
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    message.textContent = '';
    const payload = {
      student_name: studentsInput.value.trim(),
      email: document.querySelector('#email').value.trim(),
      password: document.querySelector('#password').value,
      password_confirm: document.querySelector('#password-confirm').value,
    };

    const res = await fetch('/api/register.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    const data = await res.json();
    if (!data.ok) {
      message.textContent = data.error || 'Nao foi possivel cadastrar.';
      message.className = 'error';
      return;
    }

    message.textContent = 'Cadastro enviado. Confirme o e-mail para continuar.';
    message.className = 'success';
    form.reset();
  });
}
