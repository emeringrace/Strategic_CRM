<?php
// ── Helper Functions ───────────────────────────────────────

/**
 * Calculate lead priority score (0–100)
 *
 * Deal Score  (max 50): Proportional to deal value, capped at ₹10 lakh → 50 pts
 * Recency Score (max 50): Based on days since last interaction
 *   0–7 days  → 50 pts
 *   8–14 days → 30 pts
 *  15–30 days → 10 pts
 *     > 30    →  0 pts
 *   No interactions → 0 pts
 */
function calculatePriorityScore(float $dealValue, ?string $lastInteractionDate): float
{
    // Deal score — ₹10 lakh = max 50 points
    $dealScore = min(($dealValue / 1000000) * 50, 50);

    // Recency score
    $recencyScore = 0;
    if ($lastInteractionDate) {
        $today = new DateTime('today');
        $last  = new DateTime($lastInteractionDate);
        $days  = (int)$today->diff($last)->days;

        if ($days <= 7)       $recencyScore = 50;
        elseif ($days <= 14)  $recencyScore = 30;
        elseif ($days <= 30)  $recencyScore = 10;
        else                  $recencyScore = 0;
    }

    return round($dealScore + $recencyScore, 2);
}

/**
 * Recalculate and persist priority score for a lead after a new interaction.
 */
function refreshPriorityScore(PDO $pdo, int $leadId): void
{
    $stmt = $pdo->prepare(
        "SELECT deal_value, last_interaction_date, num_interactions FROM leads WHERE lead_id = ?"
    );
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch();

    if (!$lead) return;

    $score = calculatePriorityScore(
        (float)$lead['deal_value'],
        $lead['last_interaction_date']
    );

    $pdo->prepare(
        "UPDATE leads SET priority_score = ? WHERE lead_id = ?"
    )->execute([$score, $leadId]);
}

/**
 * Check if a consultant has capacity for one more active lead.
 * Returns true if they can take the assignment, false if at max (3).
 */
function consultantHasCapacity(PDO $pdo, int $consultantId): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM leads
         WHERE consultant_id = ? AND status != 'Closed'"
    );
    $stmt->execute([$consultantId]);
    return (int)$stmt->fetchColumn() < 3;
}

/**
 * Sync a consultant's active_projects counter.
 */
function syncActiveProjects(PDO $pdo, int $consultantId): void
{
    $pdo->prepare(
        "UPDATE consultants
         SET active_projects = (
             SELECT COUNT(*) FROM leads
             WHERE consultant_id = ? AND status != 'Closed'
         )
         WHERE consultant_id = ?"
    )->execute([$consultantId, $consultantId]);
}

/**
 * Flash message helper — store in session, read once.
 */
function setFlash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/**
 * Score badge color class
 */
function scoreBadgeClass(float $score): string
{
    if ($score >= 70) return 'score-high';
    if ($score >= 40) return 'score-mid';
    return 'score-low';
}

/**
 * Status badge class
 */
function statusClass(string $status): string
{
    return match($status) {
        'New'       => 'status-new',
        'Contacted' => 'status-contacted',
        'Qualified' => 'status-qualified',
        'Closed'    => 'status-closed',
        default     => ''
    };
}
