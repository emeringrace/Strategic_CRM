<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers.php';

$pageTitle    = 'Dashboard';
$pageSubtitle = 'Overview of your CRM activity';

// ── Aggregate stats ───────────────────────────────────────
$totalConsultants = $pdo->query("SELECT COUNT(*) FROM consultants")->fetchColumn();
$totalClients     = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$totalLeads       = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
$totalInteractions= $pdo->query("SELECT COUNT(*) FROM interactions")->fetchColumn();
$activeLeads      = $pdo->query("SELECT COUNT(*) FROM leads WHERE status != 'Closed'")->fetchColumn();
$closedLeads      = $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'Closed'")->fetchColumn();
$totalDealValue   = $pdo->query("SELECT COALESCE(SUM(deal_value),0) FROM leads WHERE status != 'Closed'")->fetchColumn();

// ── Top leads by priority ─────────────────────────────────
$topLeads = $pdo->query(
  "SELECT l.*, c.company_name, co.name AS consultant_name
   FROM leads l
   JOIN clients c ON c.client_id = l.client_id
   LEFT JOIN consultants co ON co.consultant_id = l.consultant_id
   WHERE l.status != 'Closed'
   ORDER BY l.priority_score DESC
   LIMIT 5"
)->fetchAll();

// ── Consultant workload ───────────────────────────────────
$workload = $pdo->query(
  "SELECT c.consultant_id, c.name, c.active_projects,
          COUNT(l.lead_id) AS lead_count
   FROM consultants c
   LEFT JOIN leads l ON l.consultant_id = c.consultant_id AND l.status != 'Closed'
   GROUP BY c.consultant_id
   ORDER BY lead_count DESC"
)->fetchAll();

// ── Recent interactions ────────────────────────────────────
$recentInter = $pdo->query(
  "SELECT i.*, c.company_name, co.name AS consultant_name
   FROM interactions i
   JOIN clients c ON c.client_id = i.client_id
   LEFT JOIN consultants co ON co.consultant_id = i.consultant_id
   ORDER BY i.interaction_date DESC
   LIMIT 5"
)->fetchAll();

$flash = getFlash();
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
<div class="flash flash-<?= $flash['type'] ?>">
  <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<!-- ── Stats ──────────────────────────────────────────────── -->
<div class="stats-grid">
  <div class="stat-card accent-teal">
    <div class="stat-number"><?= $totalConsultants ?></div>
    <div class="stat-label">Consultants</div>
  </div>
  <div class="stat-card accent-navy">
    <div class="stat-number"><?= $totalClients ?></div>
    <div class="stat-label">Clients</div>
  </div>
  <div class="stat-card accent-rust">
    <div class="stat-number"><?= $activeLeads ?></div>
    <div class="stat-label">Active Leads</div>
  </div>
  <div class="stat-card accent-teal">
    <div class="stat-number"><?= $totalInteractions ?></div>
    <div class="stat-label">Interactions</div>
  </div>
  <div class="stat-card accent-navy">
    <div class="stat-number">₹<?= number_format($totalDealValue/100000, 1) ?>L</div>
    <div class="stat-label">Pipeline Value</div>
  </div>
</div>

<!-- ── Main Grid ──────────────────────────────────────────── -->
<div class="dash-grid">

  <!-- Top Priority Leads -->
  <div class="card">
    <div class="card-header">🎯 Top Priority Leads</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Client</th>
            <th>Consultant</th>
            <th>Status</th>
            <th>Deal Value</th>
            <th>Priority</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($topLeads)): ?>
          <tr><td class="no-data" colspan="5">No active leads yet.</td></tr>
        <?php else: ?>
          <?php foreach ($topLeads as $l): ?>
          <tr>
            <td><?= htmlspecialchars($l['company_name']) ?></td>
            <td><?= htmlspecialchars($l['consultant_name'] ?? '—') ?></td>
            <td><span class="badge <?= statusClass($l['status']) ?>"><?= $l['status'] ?></span></td>
            <td>₹<?= number_format($l['deal_value']) ?></td>
            <td>
              <div class="score-wrap">
                <div class="score-bar-bg">
                  <div class="score-bar-fill <?= $l['priority_score']>=70?'high':($l['priority_score']>=40?'mid':'low') ?>"
                       style="width:<?= min($l['priority_score'],100) ?>%"></div>
                </div>
                <span class="badge <?= scoreBadgeClass($l['priority_score']) ?>"><?= $l['priority_score'] ?></span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Consultant Workload -->
  <div class="card">
    <div class="card-header">👤 Consultant Workload</div>
    <div style="padding:1rem 1.2rem;">
      <?php foreach ($workload as $w): ?>
      <?php $pct = ($w['lead_count'] / 3) * 100; ?>
      <div style="margin-bottom:1.1rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.3rem;">
          <span style="font-weight:600;font-size:.88rem;"><?= htmlspecialchars($w['name']) ?></span>
          <span style="font-size:.78rem;color:var(--text-muted);">
            <?= $w['lead_count'] ?>/3
            <?php if ($w['lead_count'] >= 3): ?>
              <span class="badge" style="background:#fee2e2;color:#991b1b;margin-left:.3rem;">Full</span>
            <?php endif; ?>
          </span>
        </div>
        <div class="cap-bar-bg" style="width:100%;height:8px;">
          <div class="cap-bar-fill cap-<?= $w['lead_count'] ?>"
               style="width:<?= min($pct,100) ?>%;height:8px;border-radius:99px;"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<!-- ── Recent Interactions ────────────────────────────────── -->
<div class="card" style="margin-top:1.4rem;">
  <div class="card-header">💬 Recent Interactions</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Client</th>
          <th>Consultant</th>
          <th>Remarks</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($recentInter)): ?>
        <tr><td class="no-data" colspan="4">No interactions yet.</td></tr>
      <?php else: ?>
        <?php foreach ($recentInter as $i): ?>
        <tr>
          <td><?= htmlspecialchars($i['company_name']) ?></td>
          <td><?= htmlspecialchars($i['consultant_name'] ?? '—') ?></td>
          <td><?= htmlspecialchars(mb_strimwidth($i['remarks'] ?? '', 0, 70, '…')) ?></td>
          <td style="color:var(--text-muted);font-size:.82rem;"><?= date('d M Y, g:ia', strtotime($i['interaction_date'])) ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
