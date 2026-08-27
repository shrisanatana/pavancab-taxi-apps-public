<?php
require_once __DIR__ . '/../../db.php';

if (empty($_SESSION['user'])) jsonResponse(['error' => 'Authentication required'], 401);

$conn = db();
$b = getBody();

// Normalize action aliases (accept both styles)
if ($action === 'team' || $action === 'team-members') $action = 'list';
if ($action === 'add-team' || $action === 'add-team-member') $action = 'add';
if ($action === 'remove-team' || $action === 'remove-team-member' || $action === 'delete-team-member') $action = 'remove';
if ($action === 'toggle-team' || $action === 'toggle-team-member') $action = 'toggle';
if ($action === 'update-team-role' || $action === 'update-team-member-role') $action = 'role';

// Canonical table = app_team_members (same table auth/login/admin-FCM use).
// Ensure columns exist (defensive for older installs)
try {
    @$conn->query("ALTER TABLE app_team_members ADD COLUMN IF NOT EXISTS member_name VARCHAR(255) NULL");
    @$conn->query("ALTER TABLE app_team_members ADD COLUMN IF NOT EXISTS member_phone VARCHAR(50) NULL");
    @$conn->query("ALTER TABLE app_team_members ADD COLUMN IF NOT EXISTS member_email VARCHAR(255) NULL");
    @$conn->query("ALTER TABLE app_team_members ADD COLUMN IF NOT EXISTS role VARCHAR(50) DEFAULT 'team'");
    @$conn->query("ALTER TABLE app_team_members ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1");
    @$conn->query("ALTER TABLE app_team_members ADD COLUMN IF NOT EXISTS added_by VARCHAR(255) NULL");
    @$conn->query("ALTER TABLE app_team_members ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
} catch (Exception $e) {}

// Rows are returned with BOTH key styles so old/new app builds both work.
function teamRow($r) {
    if ($r === null) return null;
    $name  = $r['member_name'] ?? $r['name'] ?? '';
    $phone = $r['member_phone'] ?? $r['phone'] ?? '';
    $email = $r['member_email'] ?? $r['email'] ?? '';
    return [
        'id' => intval($r['id'] ?? 0),
        // new-style keys
        'member_name' => $name, 'member_phone' => $phone, 'member_email' => $email,
        // legacy keys
        'name' => $name, 'phone' => $phone, 'email' => $email,
        'role' => $r['role'] ?? 'team',
        'is_active' => intval($r['is_active'] ?? 1),
        'added_by' => $r['added_by'] ?? '',
        'added_by_email' => $r['added_by'] ?? '',
        'created_at' => $r['created_at'] ?? '',
        'invited_at' => $r['created_at'] ?? ''
    ];
}

if ($action === 'list') {
    $rows = dbRows("SELECT * FROM app_team_members ORDER BY id DESC");
    jsonResponse(['members' => array_map('teamRow', $rows), 'total' => count($rows)]);
}

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept BOTH field-name styles from clients
    $name  = trim($b['member_name'] ?? $b['name'] ?? '');
    $phone = trim($b['member_phone'] ?? $b['phone'] ?? '');
    $email = trim($b['member_email'] ?? $b['email'] ?? '');
    $role  = trim($b['role'] ?? 'team');
    if (!in_array($role, ['team', 'admin', 'operator'], true)) $role = 'team';
    if (in_array($role, ['operator'], true)) $role = 'team';

    if (!$name || !$phone) jsonResponse(['error' => 'Name and phone are required'], 400);

    $clean10 = substr(preg_replace('/\D/', '', $phone), -10);
    $existing = dbRows("SELECT id FROM app_team_members WHERE RIGHT(REPLACE(REPLACE(REPLACE(member_phone,'+',''),' ',''),'-',''),10) = ? LIMIT 1", 's', [$clean10]);
    if (!empty($existing)) jsonResponse(['error' => 'A team member with this phone already exists'], 409);
    if ($email) {
        $existingE = dbRows("SELECT id FROM app_team_members WHERE LOWER(member_email) = LOWER(?) LIMIT 1", 's', [$email]);
        if (!empty($existingE)) jsonResponse(['error' => 'A team member with this email already exists'], 409);
    }

    $addedBy = $_SESSION['user']['email'] ?? $_SESSION['user']['mobile'] ?? '';
    // member_email is NOT NULL + UNIQUE — synthesize an address when none given (same pattern as passenger wa_* emails)
    if ($email === '') {
        $clean10e = substr(preg_replace('/\D/', '', $phone), -10);
        $email = 'wa_' . $clean10e . '@pavancab.com';
    }
    dbExec("INSERT INTO app_team_members (member_name, member_phone, member_email, role, is_active, added_by) VALUES (?, ?, ?, ?, 1, ?)",
        'sssss', [$name, $phone, $email, $role, $addedBy]);
    $newId = intval(dbRows("SELECT id FROM app_team_members WHERE RIGHT(REPLACE(REPLACE(REPLACE(member_phone,'+',''),' ',''),'-',''),10) = ? ORDER BY id DESC LIMIT 1", 's', [$clean10])[0]['id']);
    $row = dbRows("SELECT * FROM app_team_members WHERE id = ?", 'i', [$newId]);
    try { sendFCMPushToAdmins("Team Member Added", "$name ($phone) added as " . strtoupper($role) . ".", ['type' => 'TEAM_ADDED']); } catch (Exception $e) {}
    jsonResponse(['success' => true, 'message' => 'Team member added', 'member' => teamRow($row[0] ?? null)]);
}

if ($action === 'remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($b['id'] ?? $b['member_id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'id is required'], 400);
    dbExec("DELETE FROM app_team_members WHERE id = ?", 'i', [$id]);
    jsonResponse(['success' => true, 'message' => 'Team member removed']);
}

if ($action === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($b['id'] ?? $b['member_id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'id is required'], 400);
    $cur = dbRows("SELECT is_active FROM app_team_members WHERE id = ?", 'i', [$id]);
    if (empty($cur)) jsonResponse(['error' => 'Team member not found'], 404);
    $newVal = intval($cur[0]['is_active'] ?? 1) === 1 ? 0 : 1;
    dbExec("UPDATE app_team_members SET is_active = ? WHERE id = ?", 'ii', [$newVal, $id]);
    jsonResponse(['success' => true, 'message' => 'Status updated', 'is_active' => $newVal]);
}

if ($action === 'role' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($b['id'] ?? $b['member_id'] ?? 0);
    $role = trim($b['role'] ?? '');
    if (!$id || !$role) jsonResponse(['error' => 'id and role are required'], 400);
    if (!in_array($role, ['team', 'admin'], true)) jsonResponse(['error' => 'Invalid role'], 400);
    dbExec("UPDATE app_team_members SET role = ? WHERE id = ?", 'si', [$role, $id]);
    $row = dbRows("SELECT * FROM app_team_members WHERE id = ?", 'i', [$id]);
    jsonResponse(['success' => true, 'message' => 'Role updated', 'member' => teamRow($row[0] ?? null)]);
}
