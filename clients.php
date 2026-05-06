<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers.php';

$pageTitle    = 'Clients';
$pageSubtitle = 'Manage client organizations';

// ── Process POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $company  = trim($_POST['company_name']   ?? '');
        $contact  = trim($_POST['contact_person'] ?? '');
        $email    = trim($_POST['email']          ?? '');
        $phone    = trim($_POST['phone']          ?? '');
        $industry = trim($_POST['industry']       ?? '');
        $cid      = ($_POST['consultant_id'] !== '') ? (int)$_POST['consultant_id'] : null;

        if ($company) {
            $pdo->prepare(
                "INSERT INTO clients (company_name,contact_person,email,phone,industry,consultant_id)
                 VALUES (?,?,?,?,?,?)"
            )->execute([$company,$contact,$email,$phone,$industry,$cid]);
            setFlash('success', "Client '$company' added.");
        } else {
            setFlash('error', 'Company name is required.');
        }

    } elseif ($action === 'update') {
        $id       = (int)($_POST['client_id']      ?? 0);
        $company  = trim($_POST['company_name']    ?? '');
        $contact  = trim($_POST['contact_person']  ?? '');
        $email    = trim($_POST['email']           ?? '');
        $phone    = trim($_POST['phone']           ?? '');
        $industry = trim($_POST['industry']        ?? '');
        $cid      = ($_POST['consultant_id'] !== '') ? (int)$_POST['consultant_id'] : null;

        if ($id && $company) {
            $pdo->prepare(
                "UPDATE clients
                 SET company_name=?, contact_person=?, email=?, phone=?, industry=?, consultant_id=?
                 WHERE client_id=?"
            )->execute([$company,$contact,$email,$phone,$industry,$cid,$id]);
            setFlash('success', 'Client updated.');
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['client_id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM clients WHERE client_id=?")->execute([$id]);
            setFlash('success', 'Client deleted (related leads/interactions also removed).');
        }
    }

    header('Location: clients.php');
    exit;
}

// ── Fetch ─────────────────────────────────────────────────
$clients = $pdo->query(
    "SELECT cl.*, co.name AS consultant_name
     FROM clients cl
     LEFT JOIN consultants co ON co.consultant_id = cl.consultant_id
     ORDER BY cl.company_name"
)->fetchAll();

$consultants = $pdo->query("SELECT consultant_id, name FROM consultants ORDER BY name")->fetchAll();

$flash = getFlash();
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
<div class="flash flash-<?= $flash['type'] ?>">
  <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<div class="section-header">
  <div class="section-title">All Clients</div>
  <button class="btn btn-primary" onclick="openModal('modal-add')">+ Add Client</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Company</th>
          <th>Contact Person</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Industry</th>
          <th>Consultant</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($clients)): ?>
        <tr><td class="no-data" colspan="8">No clients found.</td></tr>
      <?php else: ?>
        <?php foreach ($clients as $c): ?>
        <tr>
          <td style="color:var(--text-muted);font-size:.8rem;"><?= $c['client_id'] ?></td>
          <td><strong><?= htmlspecialchars($c['company_name']) ?></strong></td>
          <td><?= htmlspecialchars($c['contact_person'] ?? '—') ?></td>
          <td><?= htmlspecialchars($c['email'] ?? '—') ?></td>
          <td><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
          <td>
            <?php if ($c['industry']): ?>
              <span class="badge" style="background:var(--cream-dark);color:var(--navy);">
                <?= htmlspecialchars($c['industry']) ?>
              </span>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td><?= htmlspecialchars($c['consultant_name'] ?? '—') ?></td>
          <td style="white-space:nowrap;">
            <button class="btn btn-edit btn-sm" onclick="openEdit(
              <?= $c['client_id'] ?>,
              '<?= addslashes($c['company_name']) ?>',
              '<?= addslashes($c['contact_person']??'') ?>',
              '<?= addslashes($c['email']??'') ?>',
              '<?= addslashes($c['phone']??'') ?>',
              '<?= addslashes($c['industry']??'') ?>',
              <?= $c['consultant_id'] ?? 'null' ?>
            )">✏ Edit</button>
            <form method="POST" style="display:inline;"
                  onsubmit="return confirm('Delete <?= addslashes($c['company_name']) ?>?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="client_id" value="<?= $c['client_id'] ?>">
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
      <h3>Add New Client</h3>
      <button class="modal-close" onclick="closeModal('modal-add')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Company Name *</label>
          <input class="form-control" name="company_name" required placeholder="e.g. TechCorp Solutions">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Contact Person</label>
            <input class="form-control" name="contact_person" placeholder="Full name">
          </div>
          <div class="form-group">
            <label class="form-label">Industry</label>
            <input class="form-control" name="industry" placeholder="e.g. Technology">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email</label>
            <input class="form-control" name="email" type="email">
          </div>
          <div class="form-group">
            <label class="form-label">Phone</label>
            <input class="form-control" name="phone">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Assign Consultant</label>
          <select class="form-control" name="consultant_id">
            <option value="">— Unassigned —</option>
            <?php foreach ($consultants as $co): ?>
            <option value="<?= $co['consultant_id'] ?>"><?= htmlspecialchars($co['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-edit" onclick="closeModal('modal-add')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Client</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Edit Modal ─────────────────────────────────────────── -->
<div class="modal-backdrop" id="modal-edit">
  <div class="modal">
    <div class="modal-header">
      <h3>Edit Client</h3>
      <button class="modal-close" onclick="closeModal('modal-edit')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="client_id" id="edit-id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Company Name *</label>
          <input class="form-control" name="company_name" id="edit-company" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Contact Person</label>
            <input class="form-control" name="contact_person" id="edit-contact">
          </div>
          <div class="form-group">
            <label class="form-label">Industry</label>
            <input class="form-control" name="industry" id="edit-industry">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email</label>
            <input class="form-control" name="email" type="email" id="edit-email">
          </div>
          <div class="form-group">
            <label class="form-label">Phone</label>
            <input class="form-control" name="phone" id="edit-phone">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Assign Consultant</label>
          <select class="form-control" name="consultant_id" id="edit-consultant">
            <option value="">— Unassigned —</option>
            <?php foreach ($consultants as $co): ?>
            <option value="<?= $co['consultant_id'] ?>"><?= htmlspecialchars($co['name']) ?></option>
            <?php endforeach; ?>
          </select>
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
function openEdit(id, company, contact, email, phone, industry, consultantId) {
  document.getElementById('edit-id').value         = id;
  document.getElementById('edit-company').value    = company;
  document.getElementById('edit-contact').value    = contact;
  document.getElementById('edit-email').value      = email;
  document.getElementById('edit-phone').value      = phone;
  document.getElementById('edit-industry').value   = industry;
  const sel = document.getElementById('edit-consultant');
  sel.value = consultantId ?? '';
  openModal('modal-edit');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
