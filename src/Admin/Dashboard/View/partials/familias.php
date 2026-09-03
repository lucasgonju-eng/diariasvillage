<?php
declare(strict_types=1);

if (!defined('ADMIN_DASHBOARD_COMPOSING')) {
    http_response_code(404);
    exit;
}
if (!in_array('familias', $context['allowedTabs'], true)) {
    return;
}
$activeTab = $context['activeTab'];
$familyLinkRequests = $context['familyLinkRequests'];
$guardiansById = $context['guardiansById'];
$studentsByEnrollment = $context['studentsByEnrollment'];
$studentsById = $context['studentsById'];
?>
<section id="tab-familias" class="<?php echo $activeTab === 'familias' ? '' : 'hidden'; ?>">
        <h2>Vínculos familiares</h2>
        <p class="muted">
          O responsável informou a matrícula de outro filho. Confirme no cadastro oficial antes de aprovar.
          A solicitação, sozinha, nunca libera acesso.
        </p>
        <div class="info-note" style="margin-bottom:14px;">
          Ao aprovar, o aluno passa a aparecer no menu obrigatório daquela conta. Nome ou semelhança não bastam:
          confira que a pessoa é realmente responsável pelos dois alunos.
        </div>
        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Responsável</th>
                <th>Aluno já vinculado</th>
                <th>Aluno solicitado</th>
                <th>Solicitado em</th>
                <th>Ação humana</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($familyLinkRequests === []): ?>
                <tr>
                  <td colspan="5">Nenhuma solicitação familiar pendente.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($familyLinkRequests as $request): ?>
                  <?php
                    $requester = $guardiansById[(string) ($request['requester_guardian_id'] ?? '')] ?? [];
                    $sourceStudent = $studentsById[(string) ($request['source_student_id'] ?? '')] ?? [];
                    $requestedEnrollment = mb_strtoupper(trim((string) ($request['requested_enrollment'] ?? '')), 'UTF-8');
                    $targetCandidates = $studentsByEnrollment[$requestedEnrollment] ?? [];
                    $targetStudent = count($targetCandidates) === 1 ? $targetCandidates[0] : [];
                    $document = preg_replace('/\D+/', '', (string) ($requester['parent_document'] ?? '')) ?? '';
                    $documentLast = strlen($document) >= 4 ? substr($document, -4) : '----';
                    $requestedAt = !empty($request['requested_at'])
                        ? date('d/m/Y H:i', strtotime((string) $request['requested_at']))
                        : '-';
                  ?>
                  <tr data-family-link-request="<?php echo htmlspecialchars((string) ($request['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <td>
                      <strong><?php echo htmlspecialchars((string) ($requester['parent_name'] ?? 'Responsável'), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                      <span class="muted">CPF final <?php echo htmlspecialchars($documentLast, ENT_QUOTES, 'UTF-8'); ?></span>
                    </td>
                    <td>
                      <?php echo htmlspecialchars(
                        (string) ($sourceStudent['name'] ?? 'Aluno')
                          . (!empty($sourceStudent['enrollment']) ? ' • ' . (string) $sourceStudent['enrollment'] : ''),
                        ENT_QUOTES,
                        'UTF-8'
                      ); ?>
                    </td>
                    <td>
                      <strong><?php echo htmlspecialchars(
                        $targetStudent !== []
                          ? (string) ($targetStudent['name'] ?? 'Aluno') . ' • ' . $requestedEnrollment
                          : 'Matrícula não localizada ou duplicada • ' . $requestedEnrollment,
                        ENT_QUOTES,
                        'UTF-8'
                      ); ?></strong>
                    </td>
                    <td><?php echo htmlspecialchars($requestedAt, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                      <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button
                          class="btn btn-primary btn-sm js-family-link-review"
                          type="button"
                          data-decision="APPROVE"
                          data-request-id="<?php echo htmlspecialchars((string) ($request['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        >Aprovar vínculo</button>
                        <button
                          class="btn btn-danger btn-sm js-family-link-review"
                          type="button"
                          data-decision="REJECT"
                          data-request-id="<?php echo htmlspecialchars((string) ($request['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        >Rejeitar</button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div id="family-link-review-message" class="charge-message"></div>
      </section>
