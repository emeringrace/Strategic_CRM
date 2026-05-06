<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers.php';

$pageTitle    = 'Leads';
$pageSubtitle = 'Track opportunities — auto-scored by deal value & recency';

// ── Process POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $clientId     = (int)($_POST['client_id']    ?? 0);
        $consultantId = ($_POST['consultant_id'] !== '') ? (int)$_POST['consultant_id'] : null;
        $status       = $_POST['status']      ?? 'New';
        $dealValue    = (float)($_POST['deal_value'] ?? 0);

        if (!$clientId) {
            setFlash('error', 'Please select a client.');
        } elseif ($consultantId && !consultantHasCapacity($pdo, $consultantId)) {
            // ── Capacity Guardrail ────────────────────────
            setFlash('error', '⚠ Capacity Full — this consultant already has 3 active leads. Assign someone else or close an existing lead.');
        } else {
            $score = calculatePriorityScore($dealValue, null);
            $pdo->prepare(
                "INSERT INTO leads (client_id, consultant_id, status, deal_value, priority_score)
                 VALUES (?,?,?,?,?)"
            )->execute([$clientId, $consultantId, $status, $dealValue, $score]);

            if ($consultantId) syncActiveProjects($pdo, $consultantId);
            setFlash('success', 'Lead created with priority score ' . $score . '.');
        }

    } elseif ($action === 'update') {
        $id           = (int)($_POST['lead_id']      ?? 0);
        $consultantId = ($_POST['consultant_id'] !== '') ? (int)$_POST['consultant_id'] : null;
        $status       = $_POST['status']      ?? 'New';
        $dealValue    = (float)($_POST['deal_value'] ?? 0);

        if ($id) {
            // Get old consultant so we can sync both
            $old = $pdo->prepare("SELECT consultant_id, last_interaction_date FROM leads WHERE lead_id=?");
            $old->execute([$id]);
            $oldLead = $old->fetch();

            // Check capacity only if assigning a NEW consultant
            if ($consultantId && $consultantId !== (int)($oldLead['consultant_id'] ?? 0)
                && $status !== 'Closed'
                && !consultantHasCapacity($pdo, $consultantId)) {
                setFlash('error', '⚠ Capacity Full — cannot reassign to this consultant.');
            } else {
                $score = calculatePriorityScore($dealValue, $oldLead['last_interaction_date']);
                $pdo->prepare(
                    "UPDATE leads SET consultant_id=?, status=?, deal_value=?, priority_score=?
                     WHERE lead_id=?"
                )->execute([$consultantId, $status, $dealValue, $score, $id]);

                if ($consultantId) syncActiveProjects($pdo, $consultantId);
                if ($oldLead['consultant_id'] && $oldLead['consultant_id'] != $consultantId)
                    syncActiveProjects($pdo, (int)$oldLead['consultant_id']);

                setFlash('success', 'Lead updated.');
            }
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['lead_id'] ?? 0);
        if ($id) {
            $lead = $pdo->prepare("SELECT consultant_id FROM leads WHERE lead_id=?");
            $lead->execute([$id]);
            $l = $lead->fetch();
            $pdo->prepare("DELETE FROM leads WHERE lead_id=?")->execute([$id]);
            if ($l['consultant_id']) syncActiveProjects($pdo, (int)$l['consultant_id']);
            setFlash('success', 'Lead deleted.');
        }
    }

    header('Location: leads.php');
    exit;
}

// ── Fetch ─────────────────────────────────────────────────
$leads = $pdo->query(
    "SELECT l.*,
            c.company_name, c.contact_person,
            co.name AS consultant_name
     FROM leads l
     JOIN clients c ON c.client_id = l.client_id
     LEFT JOIN consultants co ON co.consultant_id = l.consultant_id
     ORDER BY l.priority_score DESC, l.created_at DESC"
)->fetchAll();

$clients     = $pdo->query("SELECT client_id, company_name FROM clients ORDER BY company_name")->fetchAll();
$consultants = $pdo->query(
    "SELECT c.consultant_id, c.name,
            (SELECT COUNT(*) FROM leads l WHERE l.consultant_id=c.consultant_id AND l.status!='Closed') AS active_count
     FROM consultants c ORDER BY c.name"
)->fetchAll();

$flash = getFlash();
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
<div class="flash flash-<?= $flash['type'] ?>">
  <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<!-- Score explainer banner -->
<div class="score-explainer">
  <h4>🎯 Lead Health Scoring</h4>
  <p style="font-size:.82rem;opacity:.85;">
    Priority score auto-calculated on every save and interaction.
  </p>
  <div class="score-pills">
    <span class="score-pill">Deal Score: up to 50 pts (₹10L max)</span>
    <span class="score-pill">Recency ≤7 days: +50 pts</span>
    <span class="score-pill">Recency 8–14 days: +30 pts</span>
    <span class="score-pill">Recency 15–30 days: +10 pts</span>
    <span class="score-pill">No contact: +0 pts</span>
  </div>
</div>

<div class="section-header">
  <div class="section-title">All Leads</div>
  <button class="btn btn-primary" onclick="openModal('modal-add')">+ Add Lead</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Client</th>
          <th>Consultant</th>
          <th>Status</th>
          <th>Deal Value</th>
          <th>Priority Score</th>
          <th>Interactions</th>
          <th>Last Contact</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($leads)): ?>
        <tr><td class="no-data" colspan="9">No leads yet. Add one!</td></tr>
      <?php else: ?>
        <?php foreach ($leads as $l): ?>
        <?php
          $scoreClass = $l['priority_score'] >= 70 ? 'high' : ($l['priority_score'] >= 40 ? 'mid' : 'low');
        ?>
        <tr>
          <td style="color:var(--text-muted);font-size:.8rem;"><?= $l['lead_id'] ?></td>
          <td>
            <strong><?= htmlspecialchars($l['company_name']) ?></strong>
            <?php if ($l['contact_person']): ?>
            <div style="font-size:.76rem;color:var(--text-muted);"><?= htmlspecialchars($l['contact_person']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($l['consultant_name'] ?? '—') ?></td>
          <td><span class="badge <?= statusClass($l['status']) ?>"><?= $l['status'] ?></span></td>
          <td>₹<?= number_format($l['deal_value']) ?></td>
          <td>
            <div class="score-wrap">
              <div class="score-bar-bg">
                <div class="score-bar-fill <?= $scoreClass ?>"
                     style="width:<?= min($l['priority_score'],100) ?>%"></div>
              </div>
              <span class="badge <?= scoreBadgeClass($l['priority_score']) ?>"><?= $l['priority_score'] ?></span>
            </div>
          </td>
          <td style="text-align:center;"><?= $l['num_interactions'] ?></td>
          <td style="font-size:.82rem;color:var(--text-muted);">
            <?= $l['last_interaction_date'] ? date('d M Y', strtotime($l['last_interaction_date'])) : '—' ?>
          </td>
          <td style="white-space:nowrap;">
            <button class="btn btn-edit btn-sm" onclick="openEdit(
              <?= $l['lead_id'] ?>,
              <?= $l['client_id'] ?>,
              <?= $l['consultant_id'] ?? 'null' ?>,
              '<?= addslashes($l['status']) ?>',
              <?= $l['deal_value'] ?>
            )">✏ Edit</button>
            <form method="POST" style="display:inline;"
                  onsubmit="return confirm('Delete this lead and all its interactions?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="lead_id" value="<?= $l['lead_id'] ?>">
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
      <h3>Add New Lead</h3>
      <button class="modal-close" onclick="closeModal('modal-add')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Client *</label>
          <select class="form-control" name="client_id" required>
            <option value="">— Select Client —</option>
            <?php foreach ($clients as $c): ?>
            <option value="<?= $c['client_id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Assign Consultant</label>
            <select class="form-control" name="consultant_id">
              <option value="">— Unassigned —</option>
              <?php foreach ($consultants as $co): ?>
              <option value="<?= $co['consultant_id'] ?>"
                <?= $co['active_count'] >= 3 ? 'disabled' : '' ?>>
                <?= htmlspecialchars($co['name']) ?>
                <?= $co['active_count'] >= 3 ? ' (Full)' : " ({$co['active_count']}/3)" ?>
              </option>
              <?php endforeach; ?>
            </select>
            <div class="form-hint">Consultants at capacity (3 active leads) are disabled.</div>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select class="form-control" name="status">
              <option>New</option>
              <option>Contacted</option>
              <option>Qualified</option>
              <option>Closed</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Deal Value (₹)</label>
          <input class="form-control" name="deal_value" type="number" min="0" step="1000" placeholder="e.g. 500000">
          <div class="form-hint">Priority score is auto-calculated from this value + interaction recency.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-edit" onclick="closeModal('modal-add')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Lead</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Edit Modal ─────────────────────────────────────────── -->
<div class="modal-backdrop" id="modal-edit">
  <div class="modal">
    <div class="modal-header">
      <h3>Edit Lead</h3>
      <button class="modal-close" onclick="closeModal('modal-edit')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="lead_id" id="edit-id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Client</label>
          <select class="form-control" name="client_id" id="edit-client">
            <?php foreach ($clients as $c): ?>
            <option value="<?= $c['client_id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Assign Consultant</label>
            <select class="form-control" name="consultant_id" id="edit-consultant">
              <option value="">— Unassigned —</option>
              <?php foreach ($consultants as $co): ?>
              <option value="<?= $co['consultant_id'] ?>"><?= htmlspecialchars($co['name']) ?> (<?= $co['active_count'] ?>/3)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select class="form-control" name="status" id="edit-status">
              <option>New</option><option>Contacted</option>
              <option>Qualified</option><option>Closed</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Deal Value (₹)</label>
          <input class="form-control" name="deal_value" id="edit-deal" type="number" min="0" step="1000">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-edit" onclick="closeModal('modal-edit')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEdit(id, clientId, consultantId, status, dealValue) {
  document.getElementById('edit-id').value         = id;
  document.getElementById('edit-client').value     = clientId;
  document.getElementById('edit-consultant').value = consultantId ?? '';
  document.getElementById('edit-status').value     = status;
  document.getElementById('edit-deal').value       = dealValue;
  openModal('modal-edit');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
