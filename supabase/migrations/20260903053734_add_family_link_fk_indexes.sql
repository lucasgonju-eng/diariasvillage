create index if not exists idx_family_link_requests_requester_guardian
  on public.family_link_requests (requester_guardian_id)
  where requester_guardian_id is not null;

create index if not exists idx_family_link_requests_source_student
  on public.family_link_requests (source_student_id);

create index if not exists idx_family_link_requests_target_student
  on public.family_link_requests (target_student_id)
  where target_student_id is not null;

create index if not exists idx_family_link_requests_reviewed_by
  on public.family_link_requests (reviewed_by)
  where reviewed_by is not null;

create index if not exists idx_family_link_requests_linked_guardian
  on public.family_link_requests (linked_guardian_id)
  where linked_guardian_id is not null;
