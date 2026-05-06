<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers.php';

$pageTitle    = 'Consultants';
$pageSubtitle = 'Manage your consulting team';

// ── Process POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($name && $email) {
            try {
                $pdo->prepare(
                    "INSERT INTO consultants (name, email, phone) VALUES (?,?,?)"
                )->execute([$name, $email, $phone]);
                setFlash('success', "Consultant '$name' added successfully.");
            } catch (PDOException $e) {
                setFlash('error', 'Email already exists or DB error: ' . $e->getMessage());
            }
        } else {
            setFlash('error', 'Name and email are required.');
        }

    } elseif ($action === 'update') {
        $id    = (int)($_POST['consultant_id'] ?? 0);
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($id && $name && $email) {
            try {
                $pdo->prepare(
                    "UPDATE consultants SET name=?, email=?, phone=? WHERE consultant_id=?"
                )->execute([$name, $email, $phone, $id]);
                setFlash('success', 'Consultant updated.');
            } catch (PDOException $e) {
                setFlash('error', 'Update failed: ' . $e->getMessage());
            }
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['consultant_id'] ?? 0);
        if ($id) {
            $active = $pdo->prepare(
                "SELECT COUNT(*) FROM leads WHERE consultant_id=? AND status != 'Closed'"
            );
            $active->execute([$id]);
            if ((int)$active->fetchColumn() > 0) {
                setFlash('warning', 'Cannot delete: consultant has active leads. Close or reassign them first.');
            } else {
                $pdo->prepare("DELETE FROM consultants WHERE consultant_id=?")->execute([$id]);
                setFlash('success', 'Consultant deleted.');
            }
        }
    }

    header('Location: consultants.php');
    exit;
}

// ── Fetch ─────────────────────────────────────────────────
$consultants = $pdo->query(
    "SELECT c.*,
            (SELECT COUNT(*) FROM leads l
             WHERE l.consultant_id = c.consultant_id AND l.status != 'Closed') AS active_count
     FROM consultants c
     ORDER BY c.name"
)->fetchAll();

$flash = getFlash();
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
<div class="flash flash-<?= $flash['type'] ?>">
  <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<div class="section-header">
  <div class="section-title">All Consultants</div>
  <button class="btn btn-primary" onclick="openModal('modal-add')">+ Add Consultant</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Active Leads</th>
          <th>Capacity</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($consultants)): ?>
        <tr><td class="no-data" colspan="7">No consultants found. Add one!</td></tr>
      <?php else: ?>
        <?php foreach ($consultants as $c): ?>
        <tr>
          <td style="color:var(--text-muted);font-size:.8rem;"><?= $c['consultant_id'] ?></td>
          <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
          <td><?= htmlspecialchars($c['email']) ?></td>
          <td><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
          <td><?= $c['active_count'] ?> / 3</td>
          <td>
            <?php $pct = ($c['active_count']/3)*100; ?>
            <div style="display:flex;align-items:center;gap:.4rem;">
              <div class="cap-bar-bg" style="width:60px;height:7px;">
                <div class="cap-bar-fill cap-<?= $c['active_count'] ?>"
                     style="width:<?= min($pct,100) ?>%;height:7px;border-radius:99px;"></div>
              </div>
              <?php if ($c['active_count'] >= 3): ?>
                <span class="badge" style="background:#fee2e2;color:#991b1b;">Full</span>
              <?php else: ?>
                <span class="badge" style="background:#d1fae5;color:#065f46;">Available</span>
              <?php endif; ?>
            </div>
          </td>
          <td style="white-space:nowrap;">
            <button class="btn btn-edit btn-sm"
              onclick="openEdit(<?= $c['consultant_id'] ?>, '<?= addslashes($c['name']) ?>', '<?= addslashes($c['email']) ?>', '<?= addslashes($c['phone']??'') ?>')">
              ✏ Edit
            </button>
            <form method="POST" style="display:inline;"
                  onsubmit="return confirm('Delete <?= addslashes($c['name']) ?>? This cannot be undone.')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="consultant_id" value="<?= $c['consultant_id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit">🗑 Delete</button>
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
      <h3>Add New Consultant</h3>
      <button class="modal-close" onclick="closeModal('modal-add')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input class="form-control" name="name" required placeholder="e.g. Anita Sharma">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email *</label>
            <input class="form-control" name="email" type="email" required placeholder="email@example.com">
          </div>
          <div class="form-group">
            <label class="form-label">Phone</label>
            <input class="form-control" name="phone" placeholder="+91 98765 43210">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-edit" onclick="closeModal('modal-add')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Consultant</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Edit Modal ─────────────────────────────────────────── -->
<div class="modal-backdrop" id="modal-edit">
  <div class="modal">
    <div class="modal-header">
      <h3>Edit Consultant</h3>
      <button class="modal-close" onclick="closeModal('modal-edit')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="consultant_id" id="edit-id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input class="form-control" name="name" id="edit-name" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email *</label>
            <input class="form-control" name="email" id="edit-email" type="email" required>
          </div>
          <div class="form-group">
            <label class="form-label">Phone</label>
            <input class="form-control" name="phone" id="edit-phone">
          </div>
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
function openEdit(id, name, email, phone) {
  document.getElementById('edit-id').value    = id;
  document.getElementById('edit-name').value  = name;
  document.getElementById('edit-email').value = email;
  document.getElementById('edit-phone').value = phone;
  openModal('modal-edit');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
