<?php
// ═══════════════════════════════════════════
//  VK Travels — Admin Panel
//  Place this file at: /vk-admin/index.php
// ═══════════════════════════════════════════

// ─── CONFIG — Change these ────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'a1779ee7_madurakvk');      // Your cPanel DB name
define('DB_USER', 'a1779ee7_vkmadurai');      // cPanel DB username
define('DB_PASS', ';P(s,v5-vs2Q');  // cPanel DB password
define('ADMIN_PASS', 'vkt@admin2024');  // Admin login password
define('WA_NUMBER', '919894530074');    // WhatsApp number (no +)
// ──────────────────────────────────────────

session_start();

// ─── Simple Auth ──────────────────────────
if (isset($_POST['login_pass'])) {
  if ($_POST['login_pass'] === ADMIN_PASS) {
    $_SESSION['vkt_admin'] = true;
  } else {
    $login_error = 'Wrong password!';
  }
}
if (isset($_GET['logout'])) {
  session_destroy();
  header('Location: index.php');
  exit;
}
$logged_in = !empty($_SESSION['vkt_admin']);

// ─── DB Connect ───────────────────────────
$pdo = null;
if ($logged_in) {
  try {
    $pdo = new PDO(
      "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
      DB_USER, DB_PASS,
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
  } catch (Exception $e) {
    die('<div style="padding:40px;color:red;font-family:sans-serif">DB Error: '.$e->getMessage().'</div>');
  }
}

// ─── Actions ──────────────────────────────
$msg = '';
if ($logged_in && $pdo) {

  // ADD BOOKING
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $ref = 'VKT-'.date('Y').'-'.str_pad(rand(1,9999),4,'0',STR_PAD_LEFT);
    $stmt = $pdo->prepare("INSERT INTO bookings
      (booking_ref,customer_name,customer_phone,customer_email,passenger_count,
       trip_from,trip_to,trip_date,return_date,trip_type,vehicle_type,
       estimated_km,fare_per_km,total_fare,advance_paid,status,driver_name,source,notes)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $total = (floatval($_POST['estimated_km']) * floatval($_POST['fare_per_km']));
    $stmt->execute([
      $ref,
      $_POST['customer_name'], $_POST['customer_phone'],
      $_POST['customer_email'] ?: null,
      intval($_POST['passenger_count']),
      $_POST['trip_from'], $_POST['trip_to'],
      $_POST['trip_date'],
      $_POST['return_date'] ?: null,
      $_POST['trip_type'], $_POST['vehicle_type'],
      intval($_POST['estimated_km']) ?: null,
      floatval($_POST['fare_per_km']) ?: null,
      $total ?: null,
      floatval($_POST['advance_paid']) ?: 0,
      $_POST['status'], $_POST['driver_name'] ?: null,
      $_POST['source'], $_POST['notes'] ?: null
    ]);
    $new_id = $pdo->lastInsertId();
    $msg = "✅ Booking <strong>$ref</strong> added!";

    // WhatsApp notify
    $wa_text = urlencode(
      "🚕 *New Booking — VK Travels*\n".
      "Ref: $ref\n".
      "Customer: {$_POST['customer_name']} ({$_POST['customer_phone']})\n".
      "From: {$_POST['trip_from']} → {$_POST['trip_to']}\n".
      "Date: {$_POST['trip_date']}\n".
      "Vehicle: {$_POST['vehicle_type']}\n".
      "Fare: ₹".number_format($total,0)
    );
    $wa_url = "https://wa.me/".WA_NUMBER."?text=$wa_text";
    $msg .= " <a href='$wa_url' target='_blank' class='wa-notify-btn'>📲 Send WhatsApp</a>";
  }

  // UPDATE STATUS
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $pdo->prepare("UPDATE bookings SET status=?, driver_name=? WHERE id=?")
        ->execute([$_POST['status'], $_POST['driver_name'] ?: null, intval($_POST['id'])]);
    $msg = "✅ Booking updated!";
  }

  // DELETE
  if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("DELETE FROM bookings WHERE id=?")->execute([intval($_GET['delete'])]);
    $msg = "🗑️ Booking deleted.";
    header("Location: index.php?msg=deleted");
    exit;
  }

  // ─── Fetch Data ─────────────────────────
  $filter_status = $_GET['status'] ?? '';
  $search        = $_GET['q'] ?? '';

  $where = "WHERE 1=1";
  $params = [];
  if ($filter_status) { $where .= " AND status=?"; $params[] = $filter_status; }
  if ($search) {
    $where .= " AND (customer_name LIKE ? OR customer_phone LIKE ? OR booking_ref LIKE ? OR trip_from LIKE ? OR trip_to LIKE ?)";
    $s = "%$search%";
    array_push($params, $s, $s, $s, $s, $s);
  }

  $bookings = $pdo->prepare("SELECT * FROM bookings $where ORDER BY created_at DESC");
  $bookings->execute($params);
  $bookings = $bookings->fetchAll(PDO::FETCH_ASSOC);

  // Stats
  $stats = $pdo->query("SELECT
    COUNT(*) as total,
    SUM(status='pending')   as pending,
    SUM(status='confirmed') as confirmed,
    SUM(status='completed') as completed,
    SUM(status='cancelled') as cancelled,
    SUM(total_fare)         as revenue,
    SUM(advance_paid)       as collected
  FROM bookings")->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>VK Travels — Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f4f1ec;color:#1a120a;min-height:100vh}

/* ── Login ── */
.login-wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f4f1ec}
.login-box{background:#fff;padding:40px;border-radius:14px;box-shadow:0 8px 40px rgba(0,0,0,.12);width:320px;text-align:center}
.login-box .logo{width:56px;height:56px;background:#b83a10;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:22px;font-weight:900;margin-bottom:16px}
.login-box h2{font-size:20px;margin-bottom:6px}
.login-box p{font-size:13px;color:#888;margin-bottom:24px}
.login-box input{width:100%;padding:12px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;margin-bottom:12px}
.login-box button{width:100%;padding:12px;background:#b83a10;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer}
.err{color:#e53e3e;font-size:13px;margin-top:8px}

/* ── Layout ── */
.topbar{background:#b83a10;color:#fff;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between}
.topbar .brand{display:flex;align-items:center;gap:10px;font-weight:700;font-size:16px}
.topbar .brand-icon{width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:14px}
.topbar a{color:rgba(255,255,255,.8);font-size:13px;text-decoration:none}
.topbar a:hover{color:#fff}

.main{max-width:1300px;margin:0 auto;padding:24px 20px}

/* ── Stats ── */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:28px}
.stat-card{background:#fff;border-radius:12px;padding:18px 20px;border-left:4px solid #ddd}
.stat-card.pending{border-color:#f6ad55}
.stat-card.confirmed{border-color:#63b3ed}
.stat-card.completed{border-color:#68d391}
.stat-card.cancelled{border-color:#fc8181}
.stat-card.revenue{border-color:#b83a10}
.stat-card.collected{border-color:#c8900a}
.stat-card .s-label{font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#888;margin-bottom:6px}
.stat-card .s-val{font-size:26px;font-weight:700;color:#1a120a}
.stat-card .s-sub{font-size:11px;color:#aaa;margin-top:2px}

/* ── Toolbar ── */
.toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;align-items:center}
.toolbar input[type=text]{padding:9px 14px;border:1px solid #ddd;border-radius:8px;font-size:13px;width:220px}
.toolbar select{padding:9px 12px;border:1px solid #ddd;border-radius:8px;font-size:13px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none}
.btn-primary{background:#b83a10;color:#fff}
.btn-primary:hover{background:#8c2a08}
.btn-green{background:#38a169;color:#fff}
.btn-sm{padding:5px 12px;font-size:12px}
.btn-outline{background:#fff;color:#1a120a;border:1px solid #ddd}

/* ── Table ── */
.table-wrap{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.06)}
table{width:100%;border-collapse:collapse;font-size:13px}
thead th{background:#f9f6f2;padding:12px 14px;text-align:left;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.6px;color:#666;border-bottom:1px solid #eee;white-space:nowrap}
tbody tr{border-bottom:1px solid #f5f5f5;transition:background .15s}
tbody tr:hover{background:#faf8f5}
td{padding:11px 14px;vertical-align:middle}
.ref{font-family:monospace;font-size:12px;color:#b83a10;font-weight:700}
.phone a{color:#1a120a;text-decoration:none}
.phone a:hover{color:#b83a10}

/* ── Status badge ── */
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px}
.badge-pending{background:#fef3cd;color:#856404}
.badge-confirmed{background:#cfe2ff;color:#084298}
.badge-completed{background:#d1e7dd;color:#0a3622}
.badge-cancelled{background:#f8d7da;color:#842029}

/* ── Add Booking Modal ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:#fff;border-radius:16px;padding:28px;width:680px;max-width:95vw;max-height:90vh;overflow-y:auto}
.modal h3{font-size:18px;margin-bottom:20px;color:#b83a10}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-grid .full{grid-column:1/-1}
label{display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#666;margin-bottom:4px}
input[type=text],input[type=tel],input[type=email],input[type=date],input[type=number],select,textarea{
  width:100%;padding:9px 12px;border:1px solid #ddd;border-radius:8px;font-size:13px;font-family:inherit}
textarea{height:70px;resize:vertical}
.fare-preview{background:#faf6f0;border:1px solid #e6dace;border-radius:8px;padding:12px;margin-top:8px;font-size:14px;font-weight:700;color:#b83a10}

/* ── Edit modal ── */
.edit-modal{background:#fff;border-radius:14px;padding:24px;width:400px;max-width:95vw}
.edit-modal h3{font-size:16px;margin-bottom:16px}
.edit-row{margin-bottom:12px}

/* ── Message ── */
.msg-bar{background:#d1e7dd;color:#0a3622;padding:12px 18px;border-radius:8px;margin-bottom:18px;font-size:14px}
.msg-bar a.wa-notify-btn{display:inline-block;margin-left:12px;background:#25d366;color:#fff;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:700;text-decoration:none}

/* ── Responsive ── */
@media(max-width:700px){
  .form-grid{grid-template-columns:1fr}
  .stats-grid{grid-template-columns:repeat(2,1fr)}
  table{font-size:12px}
  td,th{padding:8px 10px}
  .hide-mobile{display:none}
}
</style>
</head>
<body>

<?php if (!$logged_in): ?>
<!-- ═══ LOGIN ═══ -->
<div class="login-wrap">
  <div class="login-box">
    <div class="logo">VK</div>
    <h2>VK Travels Admin</h2>
    <p>Enter your admin password to continue</p>
    <form method="POST">
      <input type="password" name="login_pass" placeholder="Admin Password" autofocus>
      <button type="submit">Login →</button>
    </form>
    <?php if (!empty($login_error)): ?>
      <p class="err"><?= htmlspecialchars($login_error) ?></p>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<!-- ═══ ADMIN PANEL ═══ -->

<!-- Topbar -->
<div class="topbar">
  <div class="brand">
    <div class="brand-icon">VK</div>
    VK Travels — Admin Panel
  </div>
  <div style="display:flex;gap:16px;align-items:center">
    <span style="font-size:12px;opacity:.7"><?= date('d M Y, h:i A') ?></span>
    <a href="?logout=1">Logout</a>
  </div>
</div>

<div class="main">

  <?php if ($msg): ?>
    <div class="msg-bar"><?= $msg ?></div>
  <?php endif; ?>

  <!-- ── Stats ── -->
  <div class="stats-grid">
    <div class="stat-card pending">
      <div class="s-label">Pending</div>
      <div class="s-val"><?= $stats['pending'] ?></div>
      <div class="s-sub">Awaiting confirmation</div>
    </div>
    <div class="stat-card confirmed">
      <div class="s-label">Confirmed</div>
      <div class="s-val"><?= $stats['confirmed'] ?></div>
      <div class="s-sub">Upcoming trips</div>
    </div>
    <div class="stat-card completed">
      <div class="s-label">Completed</div>
      <div class="s-val"><?= $stats['completed'] ?></div>
      <div class="s-sub">Trips done</div>
    </div>
    <div class="stat-card cancelled">
      <div class="s-label">Cancelled</div>
      <div class="s-val"><?= $stats['cancelled'] ?></div>
      <div class="s-sub">Cancelled bookings</div>
    </div>
    <div class="stat-card revenue">
      <div class="s-label">Total Revenue</div>
      <div class="s-val">₹<?= number_format($stats['revenue'] ?? 0) ?></div>
      <div class="s-sub">All bookings</div>
    </div>
    <div class="stat-card collected">
      <div class="s-label">Advance Collected</div>
      <div class="s-val">₹<?= number_format($stats['collected'] ?? 0) ?></div>
      <div class="s-sub">Payments received</div>
    </div>
  </div>

  <!-- ── Toolbar ── -->
  <div class="toolbar">
    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap">
      <input type="text" name="q" placeholder="Search name / phone / ref..." value="<?= htmlspecialchars($search) ?>">
      <select name="status" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="pending"   <?= $filter_status==='pending'   ?'selected':'' ?>>Pending</option>
        <option value="confirmed" <?= $filter_status==='confirmed' ?'selected':'' ?>>Confirmed</option>
        <option value="completed" <?= $filter_status==='completed' ?'selected':'' ?>>Completed</option>
        <option value="cancelled" <?= $filter_status==='cancelled' ?'selected':'' ?>>Cancelled</option>
      </select>
      <button type="submit" class="btn btn-outline">🔍 Search</button>
      <a href="index.php" class="btn btn-outline">✕ Clear</a>
    </form>
    <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('open')">
      ＋ New Booking
    </button>
  </div>

  <!-- ── Bookings Table ── -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Ref</th>
          <th>Customer</th>
          <th class="hide-mobile">Route</th>
          <th>Date</th>
          <th class="hide-mobile">Vehicle</th>
          <th>Fare</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($bookings)): ?>
          <tr><td colspan="8" style="text-align:center;padding:40px;color:#aaa">No bookings found</td></tr>
        <?php endif; ?>
        <?php foreach ($bookings as $b): ?>
        <tr>
          <td><span class="ref"><?= htmlspecialchars($b['booking_ref']) ?></span></td>
          <td>
            <div style="font-weight:600"><?= htmlspecialchars($b['customer_name']) ?></div>
            <div class="phone"><a href="tel:<?= $b['customer_phone'] ?>"><?= $b['customer_phone'] ?></a></div>
          </td>
          <td class="hide-mobile">
            <?= htmlspecialchars($b['trip_from']) ?> → <?= htmlspecialchars($b['trip_to']) ?>
          </td>
          <td><?= date('d M', strtotime($b['trip_date'])) ?></td>
          <td class="hide-mobile" style="font-size:12px"><?= htmlspecialchars($b['vehicle_type']) ?></td>
          <td>
            <?php if ($b['total_fare']): ?>
              <div style="font-weight:700">₹<?= number_format($b['total_fare']) ?></div>
              <?php if ($b['balance_due'] > 0): ?>
                <div style="font-size:11px;color:#e53e3e">Bal: ₹<?= number_format($b['balance_due']) ?></div>
              <?php endif; ?>
            <?php else: ?><span style="color:#aaa">—</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge badge-<?= $b['status'] ?>"><?= $b['status'] ?></span>
          </td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <!-- WhatsApp -->
              <?php
                $wa_msg = urlencode("Hi {$b['customer_name']}, your VK Travels booking {$b['booking_ref']} is {$b['status']}. Trip: {$b['trip_from']} to {$b['trip_to']} on ".date('d M Y',strtotime($b['trip_date'])).". Vehicle: {$b['vehicle_type']}. Call +91 98945 30074 for any queries.");
              ?>
              <a href="https://wa.me/<?= $b['customer_phone'] ?>?text=<?= $wa_msg ?>" target="_blank"
                 class="btn btn-green btn-sm" title="WhatsApp Customer">💬</a>
              <!-- Edit -->
              <button class="btn btn-outline btn-sm" onclick="openEdit(<?= htmlspecialchars(json_encode($b)) ?>)">✏️</button>
              <!-- Delete -->
              <a href="?delete=<?= $b['id'] ?>" class="btn btn-sm" style="background:#fee2e2;color:#b91c1c"
                 onclick="return confirm('Delete booking <?= $b['booking_ref'] ?>?')">🗑</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div style="margin-top:12px;font-size:12px;color:#aaa">
    Showing <?= count($bookings) ?> booking(s) · Total: <?= $stats['total'] ?>
  </div>
</div>

<!-- ═══ ADD BOOKING MODAL ═══ -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <h3>➕ New Booking</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-grid">
        <div>
          <label>Customer Name *</label>
          <input type="text" name="customer_name" required placeholder="Eg. Ramesh Kumar">
        </div>
        <div>
          <label>Phone *</label>
          <input type="tel" name="customer_phone" required placeholder="+91 98765 43210">
        </div>
        <div>
          <label>Email</label>
          <input type="email" name="customer_email" placeholder="optional">
        </div>
        <div>
          <label>Passengers</label>
          <input type="number" name="passenger_count" value="1" min="1" max="14">
        </div>
        <div>
          <label>From *</label>
          <input type="text" name="trip_from" required placeholder="Madurai">
        </div>
        <div>
          <label>To *</label>
          <input type="text" name="trip_to" required placeholder="Rameshwaram">
        </div>
        <div>
          <label>Trip Date *</label>
          <input type="date" name="trip_date" required>
        </div>
        <div>
          <label>Return Date</label>
          <input type="date" name="return_date">
        </div>
        <div>
          <label>Trip Type</label>
          <select name="trip_type">
            <option value="one_way">One Way</option>
            <option value="round_trip">Round Trip</option>
            <option value="package">Package</option>
          </select>
        </div>
        <div>
          <label>Vehicle</label>
          <select name="vehicle_type">
            <option>Swift Dzire</option>
            <option>Toyota Etios</option>
            <option>Innova</option>
            <option>Innova Crysta</option>
            <option>Tempo Traveller</option>
          </select>
        </div>
        <div>
          <label>Est. KM</label>
          <input type="number" name="estimated_km" id="est_km" placeholder="175" oninput="calcFare()">
        </div>
        <div>
          <label>Fare per KM (₹)</label>
          <input type="number" name="fare_per_km" id="fare_km" placeholder="12" step="0.5" oninput="calcFare()">
        </div>
        <div>
          <label>Advance Paid (₹)</label>
          <input type="number" name="advance_paid" value="0" step="100">
        </div>
        <div>
          <label>Status</label>
          <select name="status">
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div>
          <label>Driver Name</label>
          <input type="text" name="driver_name" placeholder="Vinoth / Thangapandi">
        </div>
        <div>
          <label>Source</label>
          <select name="source">
            <option value="whatsapp">WhatsApp</option>
            <option value="phone">Phone</option>
            <option value="website">Website</option>
            <option value="walkin">Walk-in</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="full">
          <div class="fare-preview" id="fare_preview" style="display:none">
            Total Fare: ₹<span id="fare_total">0</span>
          </div>
        </div>
        <div class="full">
          <label>Notes</label>
          <textarea name="notes" placeholder="Any special instructions..."></textarea>
        </div>
      </div>
      <div style="display:flex;gap:10px;margin-top:20px;justify-content:flex-end">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Booking</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ EDIT MODAL ═══ -->
<div class="modal-overlay" id="editModal">
  <div class="edit-modal">
    <h3>✏️ Update Booking</h3>
    <form method="POST">
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="id" id="edit_id">
      <div class="edit-row">
        <label>Booking Ref</label>
        <input type="text" id="edit_ref" disabled style="background:#f5f5f5">
      </div>
      <div class="edit-row">
        <label>Status</label>
        <select name="status" id="edit_status">
          <option value="pending">Pending</option>
          <option value="confirmed">Confirmed</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
      <div class="edit-row">
        <label>Driver Name</label>
        <input type="text" name="driver_name" id="edit_driver" placeholder="Assign driver">
      </div>
      <div style="display:flex;gap:10px;margin-top:16px;justify-content:flex-end">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('editModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>

<script>
function calcFare() {
  const km = parseFloat(document.getElementById('est_km').value) || 0;
  const rate = parseFloat(document.getElementById('fare_km').value) || 0;
  const total = km * rate;
  const preview = document.getElementById('fare_preview');
  if (total > 0) {
    document.getElementById('fare_total').textContent = total.toLocaleString('en-IN');
    preview.style.display = 'block';
  } else {
    preview.style.display = 'none';
  }
}

function openEdit(b) {
  document.getElementById('edit_id').value     = b.id;
  document.getElementById('edit_ref').value    = b.booking_ref;
  document.getElementById('edit_status').value = b.status;
  document.getElementById('edit_driver').value = b.driver_name || '';
  document.getElementById('editModal').classList.add('open');
}

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});
</script>

<?php endif; ?>
</body>
</html>
