<?php

$bootstrapCandidates = [
    __DIR__ . '/src/Bootstrap.php',
    dirname(__DIR__) . '/src/Bootstrap.php',
];
foreach ($bootstrapCandidates as $bootstrapFile) {
    if (is_file($bootstrapFile)) {
        require_once $bootstrapFile;
        break;
    }
}

use App\Helpers;

Helpers::requireAuthWeb();
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Oficinas do mês • Diárias Village</title>
  <link rel="stylesheet" href="/assets/style.css?v=5">
  <style>
    .monthly-shell{max-width:1120px;margin:0 auto;padding:24px 16px 48px}
    .monthly-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:18px}
    .monthly-grid{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:18px;align-items:start}
    .monthly-card{background:#fff;border:1px solid #E2E8F0;border-radius:16px;padding:16px}
    .monthly-catalog{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}
    .monthly-office{border:1px solid #CBD5E1;border-radius:14px;padding:14px;background:#F8FAFC}
    .monthly-office h3{margin:0 0 6px;color:#0F172A}
    .monthly-office p{margin:0 0 10px;color:#475569;font-size:13px}
    .monthly-schedules{display:grid;gap:6px;margin:10px 0}
    .monthly-schedule{font-size:12px;color:#334155}
    .monthly-btn{width:100%;border:0;border-radius:10px;padding:10px 12px;font-weight:700;cursor:pointer;background:#163A7A;color:#fff}
    .monthly-btn.secondary{background:#E2E8F0;color:#0F172A}
    .monthly-btn.selected{background:#0F766E}
    .monthly-btn:disabled{opacity:.55;cursor:not-allowed}
    .monthly-board{display:grid;gap:8px}
    .monthly-day{border:1px solid #E2E8F0;border-radius:12px;padding:10px}
    .monthly-day strong{display:block;margin-bottom:6px}
    .monthly-slot{display:flex;justify-content:space-between;gap:8px;padding:6px 0;border-top:1px solid #F1F5F9;font-size:13px}
    .monthly-summary{position:sticky;top:12px}
    .monthly-progress{font-size:24px;font-weight:800;color:#163A7A;margin:4px 0 12px}
    .monthly-message{margin-top:10px;font-size:13px;white-space:pre-line}
    .monthly-message.error{color:#B91C1C}
    .monthly-message.success{color:#047857}
    .orientation-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px}
    @media(max-width:860px){.monthly-grid{grid-template-columns:1fr}.monthly-summary{position:static}.monthly-head{display:block}}
  </style>
</head>
<body>
  <main class="monthly-shell">
    <div class="monthly-head">
      <div>
        <div class="pill">Plano mensalista</div>
        <h1>Confirme as oficinas do mês</h1>
        <p class="muted">As escolhas se repetem semanalmente e já estão incluídas no plano. Nenhum PIX será criado.</p>
      </div>
      <a class="btn btn-ghost" href="/dashboard.php">Voltar</a>
    </div>

    <div id="monthly-loading" class="monthly-card">Carregando plano e oficinas...</div>
    <div id="monthly-app" class="monthly-grid" hidden>
      <section class="monthly-card">
        <h2>Oficinas disponíveis</h2>
        <p class="muted">Ao escolher uma oficina, todos os encontros obrigatórios dela são incluídos. Trilhas e Orientadora ocupam um horário escolhido.</p>
        <div id="monthly-catalog" class="monthly-catalog"></div>
        <div class="monthly-office" style="margin-top:12px">
          <h3>Escolha pela Orientadora</h3>
          <p>Use para deixar um encontro sob responsabilidade da Orientadora.</p>
          <div id="orientation-grid" class="orientation-grid"></div>
        </div>
      </section>

      <aside class="monthly-card monthly-summary">
        <h2 id="monthly-student">Resumo</h2>
        <div id="monthly-progress" class="monthly-progress">0 / 0 encontros</div>
        <p id="monthly-rule" class="muted"></p>
        <div id="monthly-board" class="monthly-board"></div>
        <button id="monthly-confirm" class="monthly-btn" type="button" style="margin-top:14px">Confirmar oficinas do mês</button>
        <div id="monthly-message" class="monthly-message"></div>
      </aside>
    </div>
  </main>

  <script>
  (() => {
    const days = {1:'Segunda',2:'Terça',3:'Quarta',4:'Quinta',5:'Sexta'};
    const loading = document.querySelector('#monthly-loading');
    const app = document.querySelector('#monthly-app');
    const catalogEl = document.querySelector('#monthly-catalog');
    const orientationEl = document.querySelector('#orientation-grid');
    const boardEl = document.querySelector('#monthly-board');
    const progressEl = document.querySelector('#monthly-progress');
    const ruleEl = document.querySelector('#monthly-rule');
    const studentEl = document.querySelector('#monthly-student');
    const confirmBtn = document.querySelector('#monthly-confirm');
    const messageEl = document.querySelector('#monthly-message');
    const selected = new Map();
    let state = null;
    let locked = false;

    const keyFor = (weekday, start) => `${Number(weekday)}|${String(start).slice(0,5)}`;
    const slotLabel = (slot) => `${days[Number(slot.weekday)] || 'Dia'} • ${String(slot.start).slice(0,5)}`;
    const setMessage = (text, error = false) => {
      messageEl.textContent = text || '';
      messageEl.className = `monthly-message${error ? ' error' : ' success'}`;
    };

    function choiceFromSchedule(office, schedule) {
      return {
        key: keyFor(schedule.weekday, schedule.start),
        workshopId: office.id,
        scheduleId: schedule.id,
        officeName: office.name,
        weekday: Number(schedule.weekday),
        start: String(schedule.start).slice(0,5),
        end: String(schedule.end).slice(0,5),
        orientation: false,
      };
    }

    function orientationChoice(weekday, start) {
      return {
        key: keyFor(weekday, start),
        workshopId: '',
        scheduleId: '',
        officeName: 'Escolha pela Orientadora',
        weekday,
        start,
        end: start === '14:00' ? '15:00' : '16:40',
        orientation: true,
      };
    }

    function canAdd(choices) {
      if (locked) return false;
      if ((selected.size + choices.length) > Number(state.plan.required_slots || 0)) {
        setMessage(`O plano permite exatamente ${state.plan.required_slots} encontros.`, true);
        return false;
      }
      const incoming = new Set();
      for (const choice of choices) {
        if (selected.has(choice.key) || incoming.has(choice.key)) {
          setMessage(`O horário ${slotLabel(choice)} já está ocupado.`, true);
          return false;
        }
        incoming.add(choice.key);
      }
      return true;
    }

    function toggleOffice(office, schedules) {
      const choices = schedules.map((schedule) => choiceFromSchedule(office, schedule));
      const allSelected = choices.every((choice) => selected.get(choice.key)?.scheduleId === choice.scheduleId);
      if (allSelected) {
        choices.forEach((choice) => selected.delete(choice.key));
      } else {
        if (!canAdd(choices)) return;
        choices.forEach((choice) => selected.set(choice.key, choice));
      }
      setMessage('');
      render();
    }

    function toggleOrientation(weekday, start) {
      const choice = orientationChoice(weekday, start);
      if (selected.get(choice.key)?.orientation) {
        selected.delete(choice.key);
      } else {
        if (!canAdd([choice])) return;
        selected.set(choice.key, choice);
      }
      setMessage('');
      render();
    }

    function validateExactSelection() {
      const required = Number(state.plan.required_slots || 0);
      const weeklyDays = Number(state.plan.weekly_days || 0);
      if (selected.size !== required) {
        return `Selecione exatamente ${required} encontros.`;
      }
      const counts = new Map();
      selected.forEach((choice) => counts.set(choice.weekday, (counts.get(choice.weekday) || 0) + 1));
      if (counts.size !== weeklyDays || [...counts.values()].some((count) => count !== 2)) {
        return `Distribua as escolhas em exatamente ${weeklyDays} dias, com 2 encontros por dia.`;
      }
      return '';
    }

    function renderCatalog() {
      catalogEl.textContent = '';
      (state.catalog || []).forEach((office) => {
        const card = document.createElement('article');
        card.className = 'monthly-office';
        const title = document.createElement('h3');
        title.textContent = office.name || 'Oficina';
        card.appendChild(title);
        if (office.description) {
          const description = document.createElement('p');
          description.textContent = office.description;
          card.appendChild(description);
        }
        const schedulesWrap = document.createElement('div');
        schedulesWrap.className = 'monthly-schedules';
        (office.schedules || []).forEach((schedule) => {
          const line = document.createElement('div');
          line.className = 'monthly-schedule';
          line.textContent = `${days[Number(schedule.weekday)] || 'Dia'} • ${schedule.start}–${schedule.end}`;
          schedulesWrap.appendChild(line);
        });
        card.appendChild(schedulesWrap);

        if (office.selection_mode === 'SINGLE_MEETING') {
          (office.schedules || []).forEach((schedule) => {
            const choice = choiceFromSchedule(office, schedule);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `monthly-btn secondary${selected.get(choice.key)?.scheduleId === schedule.id ? ' selected' : ''}`;
            button.textContent = selected.get(choice.key)?.scheduleId === schedule.id
              ? `Remover ${slotLabel(choice)}`
              : `Escolher ${slotLabel(choice)}`;
            button.disabled = locked;
            button.addEventListener('click', () => toggleOffice(office, [schedule]));
            card.appendChild(button);
          });
        } else {
          const choices = (office.schedules || []).map((schedule) => choiceFromSchedule(office, schedule));
          const allSelected = choices.length > 0 && choices.every((choice) => selected.get(choice.key)?.scheduleId === choice.scheduleId);
          const button = document.createElement('button');
          button.type = 'button';
          button.className = `monthly-btn${allSelected ? ' selected' : ''}`;
          button.textContent = allSelected
            ? `Remover oficina (${choices.length} encontro${choices.length === 1 ? '' : 's'})`
            : `Escolher oficina (${choices.length} encontro${choices.length === 1 ? '' : 's'})`;
          button.disabled = locked;
          button.addEventListener('click', () => toggleOffice(office, office.schedules || []));
          card.appendChild(button);
        }
        catalogEl.appendChild(card);
      });
    }

    function renderOrientation() {
      orientationEl.textContent = '';
      for (let weekday = 1; weekday <= 5; weekday += 1) {
        ['14:00', '15:40'].forEach((start) => {
          const key = keyFor(weekday, start);
          const button = document.createElement('button');
          button.type = 'button';
          button.className = `monthly-btn secondary${selected.get(key)?.orientation ? ' selected' : ''}`;
          button.textContent = `${days[weekday]} ${start}`;
          button.disabled = locked;
          button.addEventListener('click', () => toggleOrientation(weekday, start));
          orientationEl.appendChild(button);
        });
      }
    }

    function renderBoard() {
      boardEl.textContent = '';
      for (let weekday = 1; weekday <= 5; weekday += 1) {
        const dayChoices = [...selected.values()]
          .filter((choice) => choice.weekday === weekday)
          .sort((a, b) => a.start.localeCompare(b.start));
        if (!dayChoices.length) continue;
        const day = document.createElement('div');
        day.className = 'monthly-day';
        const title = document.createElement('strong');
        title.textContent = days[weekday];
        day.appendChild(title);
        dayChoices.forEach((choice) => {
          const line = document.createElement('div');
          line.className = 'monthly-slot';
          const time = document.createElement('span');
          time.textContent = choice.start;
          const name = document.createElement('span');
          name.textContent = choice.officeName;
          line.append(time, name);
          day.appendChild(line);
        });
        boardEl.appendChild(day);
      }
    }

    function render() {
      progressEl.textContent = `${selected.size} / ${state.plan.required_slots} encontros`;
      ruleEl.textContent = `${state.plan.weekly_days} dias por semana • 2 encontros por dia`;
      renderCatalog();
      renderOrientation();
      renderBoard();
      confirmBtn.disabled = locked || Boolean(validateExactSelection());
      confirmBtn.textContent = locked ? 'Oficinas confirmadas' : 'Confirmar oficinas do mês';
    }

    async function load() {
      try {
        const response = await fetch('/api/monthly-workshops.php', {credentials:'same-origin'});
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.error || 'Não foi possível carregar as oficinas.');
        state = data;
        locked = Boolean(data.submission && data.submission.status === 'CONFIRMED');
        studentEl.textContent = data.student?.name ? `Resumo de ${data.student.name}` : 'Resumo';
        (data.selected_slots || []).forEach((slot) => {
          const office = (data.catalog || []).find((item) => item.id === slot.workshop_id);
          const choice = slot.orientation
            ? orientationChoice(Number(slot.weekday), String(slot.start).slice(0,5))
            : {
                key: keyFor(slot.weekday, slot.start),
                workshopId: slot.workshop_id,
                scheduleId: slot.schedule_id,
                officeName: office?.name || 'Oficina',
                weekday: Number(slot.weekday),
                start: String(slot.start).slice(0,5),
                end: String(slot.end).slice(0,5),
                orientation: false,
              };
          selected.set(choice.key, choice);
        });
        loading.hidden = true;
        app.hidden = false;
        if (locked) setMessage('Confirmação concluída. Para alterar, solicite o desbloqueio à secretaria.');
        render();
      } catch (error) {
        loading.textContent = error instanceof Error ? error.message : 'Não foi possível carregar as oficinas.';
      }
    }

    confirmBtn.addEventListener('click', async () => {
      const validation = validateExactSelection();
      if (validation) {
        setMessage(validation, true);
        return;
      }
      confirmBtn.disabled = true;
      setMessage('Confirmando oficinas e gerando as entradas do mês...');
      const choices = [...selected.values()].map((choice) => choice.orientation
        ? {orientadora:true, dia_semana:choice.weekday, hora_inicio:choice.start}
        : {orientadora:false, horario_id:choice.scheduleId});
      try {
        const response = await fetch('/api/monthly-workshops.php', {
          method:'POST',
          credentials:'same-origin',
          headers:{'Content-Type':'application/json'},
          body:JSON.stringify({action:'confirm', month:state.reference_month, choices}),
        });
        const data = await response.json();
        if (!response.ok || !data.ok) {
          setMessage(data.error || 'Não foi possível confirmar as oficinas.', true);
          confirmBtn.disabled = false;
          return;
        }
        locked = true;
        setMessage('Oficinas confirmadas. As entradas do mês já estão liberadas.');
        render();
      } catch {
        setMessage('Erro de conexão ao confirmar as oficinas.', true);
        confirmBtn.disabled = false;
      }
    });

    load();
  })();
  </script>
</body>
</html>
