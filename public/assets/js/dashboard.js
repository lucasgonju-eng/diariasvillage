const paymentForm = document.querySelector('#payment-form');
const paymentMessage = document.querySelector('#payment-message');

if (paymentForm) {
  paymentForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    paymentMessage.textContent = '';

    const payload = {
      date: document.querySelector('#payment-date').value,
      billing_type: document.querySelector('#billing-type').value,
      document: document.querySelector('#billing-document').value.trim(),
    };

    const res = await fetch('/api/create-payment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    const data = await res.json();
    if (!data.ok) {
      paymentMessage.textContent = data.error || 'Falha ao criar pagamento.';
      paymentMessage.className = 'error';
      return;
    }

    paymentMessage.textContent = 'Pagamento criado. Redirecionando...';
    paymentMessage.className = 'success';
    if (data.invoice_url && data.invoice_url !== '#') {
      window.location.href = data.invoice_url;
    }
  });
}
