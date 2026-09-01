create unique index if not exists uq_payments_one_open_per_diaria
on public.payments (diaria_id)
where diaria_id is not null
  and paid_at is null
  and lower(status) in (
    'queued',
    'pending',
    'pending_asaas',
    'overdue',
    'awaiting_risk_analysis'
  );
