<?php
// Determine active page for nav highlighting
$current = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?? 'CRM' ?> — Strategic CRM</title>
  <link rel="stylesheet" href="/crm/css/style.css">
</head>
<body>

<!-- ── Sidebar ──────────────────────────────────────────── -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <h1>Strategic CRM</h1>
    <span>IT252M Project</span>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Overview</div>
    <a href="/crm/index.php" class="<?= $current==='index' ? 'active':'' ?>">
      <span class="nav-icon">⊞</span> Dashboard
    </a>

    <div class="nav-section-label">Manage</div>
    <a href="/crm/consultants.php" class="<?= $current==='consultants' ? 'active':'' ?>">
      <span class="nav-icon">👤</span> Consultants
    </a>
    <a href="/crm/clients.php" class="<?= $current==='clients' ? 'active':'' ?>">
      <span class="nav-icon">🏢</span> Clients
    </a>
    <a href="/crm/leads.php" class="<?= $current==='leads' ? 'active':'' ?>">
      <span class="nav-icon">🎯</span> Leads
    </a>
    <a href="/crm/interactions.php" class="<?= $current==='interactions' ? 'active':'' ?>">
      <span class="nav-icon">💬</span> Interactions
    </a>
  </nav>

  <div class="sidebar-footer">
    Juee · Emerin · Manas
  </div>
</aside>

<!-- ── Main ─────────────────────────────────────────────── -->
<div class="main">
  <div class="topbar">
    <div>
      <div class="topbar-title"><?= $pageTitle ?? '' ?></div>
      <div class="topbar-sub"><?= $pageSubtitle ?? '' ?></div>
    </div>
  </div>
  <div class="page-content">
