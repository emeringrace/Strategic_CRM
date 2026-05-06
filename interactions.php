<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers.php';

$pageTitle    = 'Interactions';
$pageSubtitle = 'Log every touchpoint — scores auto-update on each entry';

// ── Process POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $leadId       = (int)($_POST['lead_id']      ?? 0);
        $clientId     = (int)($_POST['client_id']    ?? 0);
        $consultantId = ($_POST['consultant_id'] !== '') ? (int)$_POST['consultant_id'] : null;
        $remarks      = trim($_POST['remarks'] ?? '');
        $date         = $_POST['interaction_date'] ?: date('Y-m-d H:i:s');

        if ($leadId && $clientId) {
            // Insert interaction
            $pdo->prepare(
                "INSERT INTO interactions (lead_id, client_id, consultant_id, remarks, interaction_date)
                 VALUES (?,?,?,?,?)"
            )->execute([$leadId, $clientId, $consultantId, $remarks, $date]);

            // ── Update lead: increment count, set last_interaction_date ──
            $pdo->prepare(
                "UPDATE leads
                 SET num_interactions = num_interactions + 1,
                     last_interaction_date = DATE(?)
                 WHERE lead_id = ?"
            )->execute([$date, $leadId]);

            // ── Recalculate priority score ────────────────────────────
            refreshPriorityScore($pdo, $leadId);

            setFlash('success', 'Interaction logged and priority score recalculated.');
        } else {
            setFlash('error', 'Please select a lead and client.');
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['interaction_id'] ?? 0);
        if ($id) {
            // Get the lead_id before deleting so we can recalculate
            $row = $pdo->prepare("SELECT lead_id FROM interactions WHERE interaction_id=?");
            $row->execute([$id]);
            $inter = $row->fetch();

            $pdo->prepare("DELETE FROM interactions WHERE interaction_id=?")->execute([$id]);

            if ($inter) {
                // Recalculate: find latest remaining interaction date
                $latestDate = $pdo->prepare(
                    "SELECT MAX(interaction_date) FROM interactions WHERE lead_id=?"
                );
                $latestDate->execute([$inter['lead_id']]);
                $ld = $latestDate->fetchColumn();

                $pdo->prepare(
                    "UPDATE leads SET num_interactions = num_interactions - 1,
                     last_interaction_date = DATE(?) WHERE lead_id=?"
                )->execute([$ld, $inter['lead_id']]);

                refreshPriorityScore($pdo, $inter['lead_id']);
            }

            setFlash('success', 'Interaction deleted and score recalculated.');
        }
    }

    header('Location: interactions.php');
    exit;
}

// ── Fetch ─────────────────────────────────────────────────
$interactions = $pdo->query(
    "SELECT i.*,
            c.company_name,
            co.name AS consultant_name,
            l.status AS lead_status, l.priority_score
     FROM interactions i
     JOIN clients c     ON c.client_id     = i.client_id
     LEFT JOIN consultants co ON co.consultant_id = i.consultant_id
     JOIN leads l        ON l.lead_id       = i.lead_id
     ORDER BY i.interaction_date DESC"
)->fetchAll();

// For dropdowns
$leads = $pdo->query(
    "SELECT l.lead_id, c.company_name, l.status
     FROM leads l JOIN clients c ON c.client_id = l.client_id
     WHERE l.status != 'Closed'
     ORDER BY c.company_name"
)->fetchAll();

$consultants = $pdo->query("SELECT consultant_id, name FROM consultants ORDER BY name")->fetchAll();

// Build lead→client map for JS
$leadClientMap = [];
foreach ($pdo->query(
    "SELECT l.lead_id, l.client_id FROM leads l"
)->fetchAll() as $row) {
    $leadClientMap[$row['lead_id']] = $row['client_id'];
}

$flash = getFlash();
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
<div class="flash flash-<?= $flash['type'] ?>">
  <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<!-- Info banner -->
<div style="background:linear-gradient(135deg,var(--teal) 0%,var(--teal-light) 100%);
            color:#fff;border-radius:var(--radius);padding:1rem 1.4rem;margin-bottom:1.2rem;
            font-size:.85rem;">
  💡 <strong>Auto-scoring:</strong> Every time you log an interaction, the lead's priority score is
  automatically recalculated based on the new recency value.
</div>

<div class="section-header">
  <div class="section-title">All Interactions</div>
  <button class="btn btn-primary" onclick="openModal('modal-add')">+ Log Interaction</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Client</th>
          <th>Lead Status</th>
          <th>Consultant</th>
          <th>Remarks</th>
          <th>New Score</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($interactions)): ?>
        <tr><td class="no-data" colspan="8">No interactions logged yet.</td></tr>
      <?php else: ?>
        <?php foreach ($interactions as $i): ?>
        <tr>
          <td style="color:var(--text-muted);font-size:.8rem;"><?= $i['interaction_id'] ?></td>
          <td><strong><?= htmlspecialchars($i['company_name']) ?></strong></td>
          <td><span class="badge <?= statusClass($i['lead_status']) ?>"><?= $i['lead_status'] ?></span></td>
          <td><?= htmlspecialchars($i['consultant_name'] ?? '—') ?></td>
          <td style="max-width:260px;">
            <span title="<?= htmlspecialchars($i['remarks'] ?? '') ?>">
              <?= htmlspecialchars(mb_strimwidth($i['remarks'] ?? '', 0, 80, '…')) ?>
            </span>
          </td>
          <td>
            <span class="badge <?= scoreBadgeClass($i['priority_score']) ?>">
              <?= $i['priority_score'] ?>
            </span>
          </td>
          <td style="font-size:.82rem;color:var(--text-muted);white-space:nowrap;">
            <?= date('d M Y', strtotime($i['interaction_date'])) ?><br>
            <span style="font-size:.76rem;"><?= date('g:i a', strtotime($i['interaction_date'])) ?></span>
          </td>
          <td>
            <form method="POST" style="display:inline;"
                  onsubmit="return confirm('Delete this interaction? The lead score will be recalculated.')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="interaction_id" value="<?= $i['interaction_id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit">🗑</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Add Modal ──────────────────────────────────────────── -->
<div class="modal-backdrop" id="modal-add">
  <div class="modal">
    <div class="modal-header">
      <h3>Log New Interaction</h3>
      <button class="modal-close" onclick="closeModal('modal-add')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="client_id" id="hidden-client-id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Lead (Active Only) *</label>
          <select class="form-control" name="lead_id" id="lead-select" required onchange="autoFillClient(this)">
            <option value="">— Select Lead —</option>
            <?php foreach ($leads as $l): ?>
            <option value="<?= $l['lead_id'] ?>">
              <?= htmlspecialchars($l['company_name']) ?> — <?= $l['status'] ?>
            </option>
            <?php endforeach; ?>
          </select>
          <div class="form-hint">Only active (non-Closed) leads shown.</div>
        </div>
        <div class="form-group">
          <label class="form-label">Consultant</label>
          <select class="form-control" name="consultant_id">
            <option value="">— None —</option>
            <?php foreach ($consultants as $co): ?>
            <option value="<?= $co['consultant_id'] ?>"><?= htmlspecialchars($co['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Date & Time</label>
          <input class="form-control" name="interaction_date" type="datetime-local"
                 value="<?= date('Y-m-d\TH:i') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Remarks</label>
          <textarea class="form-control" name="remarks" rows="4"
                    placeholder="What was discussed? Any next steps?"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-edit" onclick="closeModal('modal-add')">Cancel</button>
        <button type="submit" class="btn btn-primary">Log Interaction</button>
      </div>
    </form>
  </div>
</div>

<script>
// Auto-fill hidden client_id when a lead is selected
const leadClientMap = <?= json_encode($leadClientMap) ?>;

function autoFillClient(sel) {
  const leadId = parseInt(sel.value);
  document.getElementById('hidden-client-id').value = leadClientMap[leadId] ?? '';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
