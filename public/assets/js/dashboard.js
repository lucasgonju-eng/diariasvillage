const paymentForm = document.querySelector('#payment-form');
const paymentMessage = document.querySelector('#payment-message');
const plannedCountdown = document.querySelector('#planned-countdown');

function startPlannedCountdown() {
  if (!plannedCountdown) return;
  const nowAttr = plannedCountdown.dataset.now;
  const baseNow = nowAttr ? new Date(nowAttr) : new Date();

  function updateCountdown() {
    const now = new Date();
    const target = new Date(baseNow);
    target.setHours(10, 0, 0, 0);

    const diff = target.getTime() - now.getTime();
    if (diff <= 0) {
      plannedCountdown.textContent = 'Diária emergencial em vigor a partir das 10h.';
      return;
    }

    const totalSeconds = Math.floor(diff / 1000);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    const pad = (value) => String(value).padStart(2, '0');
    plannedCountdown.textContent = `Diária planejada encerra em ${pad(hours)}:${pad(minutes)}:${pad(seconds)}.`;
  }

  updateCountdown();
  setInterval(updateCountdown, 1000);
}

startPlannedCountdown();

if (paymentForm) {
  paymentForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    paymentMessage.textContent = '';

    try {
      const payload = {
        date: document.querySelector('#payment-date').value,
      };

      const res = await fetch('/api/diaria-iniciar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
      });

      const raw = await res.text();
      let data = {};
      try {
        data = JSON.parse(raw);
      } catch (e) {
        data = {};
      }

      if (!res.ok || !data.ok) {
        if (data && data.error) {
          paymentMessage.textContent = data.error;
          paymentMessage.className = 'error';
          return;
        }
        // Fallback robusto: se CDN/WAF devolver HTML, envia formulário padrão.
        paymentForm.submit();
        return;
      }

      paymentMessage.textContent = 'Diária iniciada. Redirecionando para a grade...';
      paymentMessage.className = 'success';
      if (data.redirect_url) {
        window.location.href = data.redirect_url;
      }
    } catch (err) {
      // Em falha de rede/script, mantém fluxo via submit tradicional.
      paymentForm.submit();
    }
  });
}
