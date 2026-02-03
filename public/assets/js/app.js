const form = document.querySelector('#register-form');
const studentsInput = document.querySelector('#student-name');
const studentsList = document.querySelector('#students-list');
const message = document.querySelector('#form-message');

async function loadStudents() {
  const res = await fetch('/api/students.php');
  const data = await res.json();
  if (!data.ok) {
    message.textContent = data.error || 'Falha ao carregar alunos.';
    message.className = 'error';
    return;
  }

  studentsList.innerHTML = '';
  data.students.forEach((student) => {
    const option = document.createElement('option');
    option.value = student.name;
    studentsList.appendChild(option);
  });
}

if (form) {
  loadStudents();

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
