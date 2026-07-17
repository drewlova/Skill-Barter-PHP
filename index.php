<?php
require_once __DIR__ . '/backend.php';

// ---- small display helpers ----
function initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $letters = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
    return $letters ?: '?';
}
$palette = ['#7c5ce7', '#ec4899', '#22c55e', '#f59e0b', '#3b82f6', '#ef4444'];
function avatar_color(int $seed, array $palette): string {
    return $palette[$seed % count($palette)];
}
function stars(float $score): string {
    $full = round($score);
    return str_repeat('★', (int)$full) . str_repeat('☆', 5 - (int)$full);
}
function is_image_ext(string $filename): bool {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
}
function file_icon(string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return match ($ext) {
        'pdf' => 'ti-file-type-pdf',
        'doc', 'docx' => 'ti-file-type-doc',
        'xls', 'xlsx', 'csv' => 'ti-file-spreadsheet',
        'ppt', 'pptx' => 'ti-presentation',
        'zip' => 'ti-file-zip',
        'txt' => 'ti-file-text',
        default => 'ti-file',
    };
}
function format_bytes(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

$status_messages = [
    'posted'             => ['Listing posted successfully! 🚀', 'success'],
    'proposal_sent'      => ['Proposal sent! 📨', 'success'],
    'profile_updated'    => ['Profile updated ✅', 'success'],
    'review_logged'      => ['Review submitted! 📈', 'success'],
    'milestone_changed'  => ['Session updated ✔', 'success'],
    'update_posted'      => ['Update posted 📎', 'success'],
    'moderation_enforced'=> ['Moderation action applied', 'info'],
];
$flash = null;
if (isset($_GET['status'], $status_messages[$_GET['status']])) {
    $flash = $status_messages[$_GET['status']];
}
if ($backend_error) {
    $flash = [$backend_error, 'error'];
} elseif ($backend_success) {
    $flash = [$backend_success, 'success'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SkillSwap</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@700;800;900&family=Inter:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg: #0f0f1a; --bg2: #16162a; --bg3: #1e1e38; --border: #2a2a45;
      --accent: #7c5ce7; --accent2: #a78bfa; --accent-glow: rgba(124,92,231,0.18);
      --green: #4ade80; --green-dim: rgba(74,222,128,0.13);
      --amber: #fbbf24; --amber-dim: rgba(251,191,36,0.13);
      --red: #f87171; --red-dim: rgba(248,113,113,0.13);
      --text: #e2e2f0; --text2: #8080aa; --text3: #44447a;
      --radius: 14px; --radius-sm: 9px;
    }
    html, body { height: 100%; }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; font-size: 14px; line-height: 1.5; }
    ::-webkit-scrollbar { width: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

    .sidebar { width: 220px; min-height: 100vh; background: var(--bg2); border-right: 1.5px solid var(--border); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; z-index: 100; padding-bottom: 14px; }
    .logo { padding: 20px 18px 16px; border-bottom: 1.5px solid var(--border); }
    .logo-text { font-family: 'Nunito', sans-serif; font-size: 20px; font-weight: 900; color: #fff; letter-spacing: -0.5px; }
    .logo-text span { color: var(--accent2); }
    .logo-sub { font-size: 10px; color: var(--text3); text-transform: uppercase; letter-spacing: 0.8px; margin-top: 3px; }
    .nav { padding: 12px 10px; flex: 1; display: flex; flex-direction: column; gap: 2px; }
    .nav-label { font-size: 9px; color: var(--text3); text-transform: uppercase; letter-spacing: 1.2px; padding: 10px 10px 5px; }
    .nav-btn { display: flex; align-items: center; gap: 9px; width: 100%; padding: 9px 11px; border-radius: 10px; font-size: 13px; font-weight: 600; color: var(--text2); background: none; border: none; cursor: pointer; text-align: left; transition: all 0.15s; font-family: 'Inter', sans-serif; }
    .nav-btn i { font-size: 16px; }
    .nav-btn:hover { background: var(--bg3); color: var(--text); }
    .nav-btn.active { background: var(--accent-glow); color: var(--accent2); }
    .sidebar-user { margin: 8px 10px 0; padding: 11px 12px; background: var(--bg3); border: 1.5px solid var(--border); border-radius: 11px; display: flex; align-items: center; gap: 9px; cursor: pointer; transition: border-color 0.15s; text-decoration: none; }
    .sidebar-user:hover { border-color: var(--accent2); }
    .sidebar-logout { margin: 8px 10px 0; padding: 9px 12px; font-size: 11px; color: var(--text3); text-align: center; text-decoration: none; border-radius: 9px; }
    .sidebar-logout:hover { background: var(--bg3); color: var(--red); }

    .main { margin-left: 220px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
    .topbar { height: 56px; background: var(--bg2); border-bottom: 1.5px solid var(--border); display: flex; align-items: center; padding: 0 24px; gap: 12px; position: sticky; top: 0; z-index: 50; }
    .topbar-title { font-family: 'Nunito', sans-serif; font-size: 17px; font-weight: 900; color: #fff; flex: 1; }
    .notif-wrap { position: relative; }
    .notif-btn { width: 34px; height: 34px; background: var(--bg3); border: 1.5px solid var(--border); border-radius: 9px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text2); font-size: 15px; position: relative; transition: border-color 0.15s; }
    .notif-btn:hover { border-color: var(--accent2); }
    .notif-dot { position: absolute; top: 6px; right: 6px; width: 6px; height: 6px; background: var(--red); border-radius: 50%; border: 1.5px solid var(--bg2); }
    .notif-dropdown { display: none; position: absolute; top: 42px; right: 0; width: 280px; max-height: 320px; overflow-y: auto; background: var(--bg2); border: 1.5px solid var(--border); border-radius: 12px; padding: 8px; z-index: 60; }
    .notif-dropdown.open { display: block; }
    .notif-item { padding: 9px 10px; border-radius: 8px; font-size: 11.5px; color: var(--text2); }
    .notif-item:hover { background: var(--bg3); }

    .content { flex: 1; padding: 22px 24px; }
    .page { display: none; }
    .page.active { display: block; }

    .avatar { border-radius: 50%; display: flex; align-items: center; justify-content: center; font-family: 'Nunito', sans-serif; font-weight: 900; color: #fff; flex-shrink: 0; }
    .av-sm { width: 34px; height: 34px; font-size: 12px; }
    .av-md { width: 40px; height: 40px; font-size: 14px; }
    .av-lg { width: 68px; height: 68px; font-size: 22px; border: 3px solid var(--accent2); }

    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 9px; font-size: 13px; font-weight: 700; cursor: pointer; border: none; transition: all 0.14s; font-family: 'Inter', sans-serif; text-decoration: none; }
    .btn:active { transform: scale(0.96); }
    .btn i { font-size: 15px; }
    .btn-primary { background: var(--accent); color: #fff; }
    .btn-primary:hover { background: #6b4fd0; }
    .btn-outline { background: transparent; border: 1.5px solid var(--border); color: var(--text2); }
    .btn-outline:hover { border-color: var(--accent2); color: var(--accent2); }
    .btn-green { background: var(--green-dim); border: 1.5px solid rgba(74,222,128,0.3); color: var(--green); }
    .btn-green:hover { background: rgba(74,222,128,0.22); }
    .btn-red { background: var(--red-dim); border: 1.5px solid rgba(248,113,113,0.3); color: var(--red); }
    .btn-red:hover { background: rgba(248,113,113,0.22); }
    .btn-amber { background: var(--amber-dim); border: 1.5px solid rgba(251,191,36,0.3); color: var(--amber); }
    .btn-amber:hover { background: rgba(251,191,36,0.22); }
    .btn-sm { padding: 5px 11px; font-size: 11px; }
    .btn[disabled] { opacity: 0.4; cursor: not-allowed; pointer-events: none; }

    .badge { display: inline-flex; align-items: center; font-size: 9px; font-weight: 800; padding: 3px 9px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.6px; }
    .badge-active { background: var(--green-dim); color: var(--green); border: 1px solid rgba(74,222,128,0.3); }
    .badge-pending { background: var(--amber-dim); color: var(--amber); border: 1px solid rgba(251,191,36,0.3); }
    .badge-done { background: var(--accent-glow); color: var(--accent2); border: 1px solid rgba(167,139,250,0.3); }
    .badge-declined { background: var(--red-dim); color: var(--red); border: 1px solid rgba(248,113,113,0.3); }

    .chip { display: inline-flex; align-items: center; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 20px; }
    .chip-purple { background: var(--accent-glow); color: var(--accent2); border: 1px solid rgba(124,92,231,0.3); }
    .chip-green { background: var(--green-dim); color: var(--green); border: 1px solid rgba(74,222,128,0.3); }

    .pill { display: inline-flex; align-items: center; padding: 5px 13px; border-radius: 20px; font-size: 11px; font-weight: 700; }
    .pill-purple { background: var(--accent-glow); color: var(--accent2); border: 1px solid rgba(124,92,231,0.3); }
    .pill-green { background: var(--green-dim); color: var(--green); border: 1px solid rgba(74,222,128,0.3); }

    .card { background: var(--bg2); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 16px; }
    .card-title { font-family: 'Nunito', sans-serif; font-size: 14px; font-weight: 900; color: #fff; display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
    .empty-note { font-size: 11px; color: var(--text3); padding: 6px 0; }

    .dash-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .match-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: var(--bg3); border: 1.5px solid var(--border); border-radius: 10px; }
    .match-info { flex: 1; min-width: 0; }
    .match-name { font-size: 12px; font-weight: 700; margin-bottom: 4px; }
    .match-chips { display: flex; gap: 5px; flex-wrap: wrap; }
    .session-item { padding: 14px; background: var(--bg3); border: 1.5px solid var(--border); border-radius: 10px; }
    .session-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
    .session-title { font-size: 13px; font-weight: 700; color: var(--text); }
    .milestones { display: flex; gap: 5px; margin-bottom: 10px; }
    .ms { flex: 1; height: 5px; border-radius: 3px; background: var(--border); }
    .ms.done { background: var(--green); }
    .ms.curr { background: var(--accent); }
    .session-foot { display: flex; align-items: center; justify-content: space-between; }
    .session-peer { font-size: 10px; color: var(--text3); }

    .filters { display: flex; gap: 7px; margin-bottom: 14px; flex-wrap: wrap; }
    .filter-btn { padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 700; cursor: pointer; border: 1.5px solid var(--border); background: transparent; color: var(--text2); transition: all 0.15s; font-family: 'Inter', sans-serif; }
    .filter-btn:hover, .filter-btn.active { background: var(--accent-glow); border-color: var(--accent); color: var(--accent2); }
    .offer-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 13px; }
    .offer-card { background: var(--bg2); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 15px; transition: all 0.15s; }
    .offer-card:hover { border-color: var(--accent); transform: translateY(-2px); }
    .offer-head { display: flex; align-items: center; gap: 9px; margin-bottom: 10px; }
    .offer-user .name { font-size: 12px; font-weight: 700; }
    .offer-user .sub { font-size: 10px; color: var(--text3); text-transform: capitalize; }
    .offer-title { font-size: 13px; font-weight: 700; color: #fff; margin-bottom: 5px; }
    .offer-desc { font-size: 11px; color: var(--text2); line-height: 1.5; margin-bottom: 11px; }
    .trade-box { background: var(--bg3); border-radius: 8px; padding: 9px 11px; display: flex; align-items: center; gap: 8px; font-size: 11px; margin-bottom: 11px; }
    .trade-box > div { flex: 1; }
    .trade-label { color: var(--text3); font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
    .trade-val { color: var(--text); font-weight: 600; }
    .trade-arrow { color: var(--accent2); font-size: 18px; font-weight: 900; }

    .session-full { padding: 18px; background: var(--bg2); border: 1.5px solid var(--border); border-radius: var(--radius); margin-bottom: 12px; }
    .ms-detailed { display: flex; gap: 8px; margin-bottom: 14px; flex-wrap: wrap; }
    .ms-box { flex: 1; min-width: 80px; border-radius: 9px; padding: 10px 8px; text-align: center; border: 1.5px solid var(--border); }
    .ms-box .ms-icon { font-size: 18px; margin-bottom: 3px; }
    .ms-box .ms-lbl { font-size: 10px; font-weight: 700; }
    .ms-box.done { background: var(--green-dim); border-color: rgba(74,222,128,0.3); }
    .ms-box.done .ms-icon, .ms-box.done .ms-lbl { color: var(--green); }
    .ms-box.curr { background: var(--accent-glow); border-color: rgba(124,92,231,0.3); }
    .ms-box.curr .ms-icon, .ms-box.curr .ms-lbl { color: var(--accent2); }
    .ms-box.pending-ms { background: var(--bg3); }
    .ms-box.pending-ms .ms-icon, .ms-box.pending-ms .ms-lbl { color: var(--text3); }
    .session-actions { display: flex; gap: 9px; flex-wrap: wrap; align-items: center; }

    .update-list { margin-top: 14px; display: flex; flex-direction: column; gap: 9px; }
    .update-item { padding: 10px 12px; background: var(--bg2); border: 1.5px solid var(--border); border-radius: 10px; }
    .update-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 5px; }
    .update-author { font-size: 11.5px; font-weight: 700; color: var(--text); }
    .update-meta { font-size: 10px; color: var(--text3); white-space: nowrap; }
    .update-note { font-size: 12px; color: var(--text2); margin-bottom: 6px; white-space: pre-wrap; }
    .update-note:last-child { margin-bottom: 0; }
    .update-thumb-link { display: inline-block; }
    .update-thumb { max-width: 220px; max-height: 160px; border-radius: 8px; border: 1.5px solid var(--border); display: block; object-fit: cover; }
    .update-file-chip { display: inline-flex; align-items: center; gap: 7px; padding: 6px 10px; background: var(--bg3); border: 1.5px solid var(--border); border-radius: 8px; font-size: 11.5px; color: var(--text2); text-decoration: none; max-width: 100%; }
    .update-file-chip:hover { border-color: var(--accent2); color: var(--accent2); }
    .update-file-chip span:first-of-type { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .update-file-size { color: var(--text3); font-size: 10px; flex-shrink: 0; }

    .update-form { margin-top: 14px; padding-top: 14px; border-top: 1.5px solid var(--border); }
    .update-form textarea.form-ctrl { min-height: 46px; margin-bottom: 9px; }
    .update-form-row { display: flex; align-items: center; gap: 10px; }
    .file-input-label { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; background: var(--bg3); border: 1.5px solid var(--border); border-radius: 8px; font-size: 11.5px; color: var(--text2); cursor: pointer; flex-shrink: 0; transition: border-color 0.15s; }
    .file-input-label:hover { border-color: var(--accent2); color: var(--accent2); }
    .file-input-hidden { display: none; }
    .file-input-name { font-size: 11px; color: var(--text3); flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .profile-hero { background: var(--bg2); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 22px; display: flex; gap: 18px; align-items: flex-start; margin-bottom: 14px; }
    .profile-info { flex: 1; }
    .profile-info h2 { font-family: 'Nunito', sans-serif; font-size: 20px; font-weight: 900; color: #fff; margin-bottom: 2px; }
    .profile-stars { color: var(--amber); font-size: 16px; }
    .profile-bio { font-size: 12px; color: var(--text2); margin: 8px 0 14px; line-height: 1.55; }
    .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .pills-wrap { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 10px; }
    .review-item { padding: 13px; background: var(--bg3); border: 1.5px solid var(--border); border-radius: 10px; margin-bottom: 8px; }
    .review-head { display: flex; align-items: center; gap: 9px; margin-bottom: 7px; }
    .review-meta { flex: 1; }
    .review-meta .rn { font-size: 12px; font-weight: 700; }
    .review-meta .rd { font-size: 10px; color: var(--text3); }
    .review-text { font-size: 11px; color: var(--text2); line-height: 1.55; }

    .report-item { display: flex; align-items: center; gap: 12px; padding: 14px; background: var(--bg3); border: 1.5px solid var(--border); border-radius: 10px; }
    .report-icon { font-size: 22px; flex-shrink: 0; }
    .report-info { flex: 1; min-width: 0; }
    .report-info h4 { font-size: 12px; font-weight: 700; margin-bottom: 2px; color: var(--text); }
    .report-info p { font-size: 11px; color: var(--text2); }
    .report-actions { display: flex; gap: 7px; flex-shrink: 0; }

    .section-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 16px; }
    .section-title { font-family: 'Nunito', sans-serif; font-size: 18px; font-weight: 900; color: #fff; }
    .section-sub { font-size: 12px; color: var(--text2); margin-top: 2px; }

    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.72); z-index: 300; display: none; align-items: center; justify-content: center; backdrop-filter: blur(5px); }
    .modal-overlay.open { display: flex; }
    .modal { background: var(--bg2); border: 1.5px solid var(--border); border-radius: 16px; padding: 24px; width: 440px; max-width: 92vw; max-height: 88vh; overflow-y: auto; }
    .modal-title { font-family: 'Nunito', sans-serif; font-size: 18px; font-weight: 900; color: #fff; margin-bottom: 18px; }
    .form-label { display: block; font-size: 10px; color: var(--text2); font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 5px; }
    .form-ctrl { width: 100%; background: var(--bg3); border: 1.5px solid var(--border); border-radius: 9px; padding: 9px 13px; color: var(--text); font-size: 13px; font-family: 'Inter', sans-serif; outline: none; margin-bottom: 13px; transition: border-color 0.15s; }
    .form-ctrl:focus { border-color: var(--accent); }
    textarea.form-ctrl { min-height: 80px; resize: vertical; }
    select.form-ctrl { appearance: auto; }
    .modal-footer { display: flex; justify-content: flex-end; gap: 9px; margin-top: 6px; }

    .star-pick { font-size: 28px; letter-spacing: 4px; cursor: pointer; color: var(--border); user-select: none; margin-bottom: 13px; }
    .star-pick span { transition: color 0.1s; }
    .star-pick span.lit { color: var(--amber); }

    #toast-wrap { position: fixed; bottom: 22px; right: 22px; z-index: 500; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
    .toast { background: var(--bg2); border: 1.5px solid var(--border); border-radius: 10px; padding: 11px 16px; font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 9px; min-width: 240px; max-width: 340px; box-shadow: 0 6px 28px rgba(0,0,0,0.5); pointer-events: all; animation: toastIn 0.22s ease; }
    .toast.t-success { border-left: 3px solid var(--green); }
    .toast.t-error   { border-left: 3px solid var(--red); }
    .toast.t-info    { border-left: 3px solid var(--accent2); }
    @keyframes toastIn { from { transform: translateX(110%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

    .flex-between { display: flex; align-items: center; justify-content: space-between; }
    .mb12 { margin-bottom: 12px; }
    .w100 { width: 100%; }

    /* Auth screen */
    .auth-wrap { width: 100%; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .auth-card { width: 380px; max-width: 92vw; background: var(--bg2); border: 1.5px solid var(--border); border-radius: 16px; padding: 30px; }
    .auth-tabs { display: flex; gap: 6px; margin-bottom: 20px; background: var(--bg3); border-radius: 10px; padding: 4px; }
    .auth-tab { flex: 1; text-align: center; padding: 8px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; color: var(--text2); }
    .auth-tab.active { background: var(--accent); color: #fff; }
    .auth-form { display: none; }
    .auth-form.active { display: block; }

    @media (max-width: 900px) {
      .sidebar { width: 180px; } .main { margin-left: 180px; }
      .offer-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 640px) {
      .sidebar { display: none; } .main { margin-left: 0; }
      .dash-grid, .profile-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<?php if (!$active_user): ?>

  <!-- ════════════════════ LOGGED OUT — LOGIN / SIGN UP ════════════════════ -->
  <div class="auth-wrap">
    <div class="auth-card">
      <div class="logo" style="border:none;padding:0 0 18px;text-align:center">
        <div class="logo-text">Skill<span>Swap</span></div>
        <div class="logo-sub">Trade skills, not cash</div>
      </div>

      <div class="auth-tabs">
        <div class="auth-tab active" id="tab-login" onclick="showAuthForm('login')">Log In</div>
        <div class="auth-tab" id="tab-signup" onclick="showAuthForm('signup')">Sign Up</div>
      </div>

      <form class="auth-form active" id="form-login" method="POST" action="backend.php">
        <input type="hidden" name="action" value="login">
        <label class="form-label">Email</label>
        <input class="form-ctrl" type="email" name="email" required>
        <label class="form-label">Password</label>
        <input class="form-ctrl" type="password" name="password" required>
        <button class="btn btn-primary w100" type="submit">Log In →</button>
      </form>

      <form class="auth-form" id="form-signup" method="POST" action="backend.php">
        <input type="hidden" name="action" value="signup">
        <label class="form-label">Display Name</label>
        <input class="form-ctrl" type="text" name="name" required>
        <label class="form-label">Email</label>
        <input class="form-ctrl" type="email" name="email" required>
        <label class="form-label">Password</label>
        <input class="form-ctrl" type="password" name="password" required>
        <label class="form-label">Skill You Can Offer</label>
        <input class="form-ctrl" type="text" name="skills_offer" placeholder="e.g. Python Debugging">
        <label class="form-label">Skill You're Looking For</label>
        <input class="form-ctrl" type="text" name="skills_need" placeholder="e.g. Graphic Design">
        <button class="btn btn-primary w100" type="submit">Create Account 🚀</button>
      </form>
    </div>
  </div>

<?php else: ?>

  <!-- ════════════════════ SIDEBAR ════════════════════ -->
  <aside class="sidebar">
    <div class="logo">
      <div class="logo-text">Skill<span>Swap</span></div>
      <div class="logo-sub">Proof of Concept</div>
    </div>
    <nav class="nav">
      <div class="nav-label">Main</div>
      <button class="nav-btn active" onclick="goTo('dashboard',this)"><i class="ti ti-bolt"></i> Dashboard</button>
      <button class="nav-btn" onclick="goTo('offers',this)"><i class="ti ti-arrows-exchange"></i> Offers &amp; Requests</button>
      <button class="nav-btn" onclick="goTo('sessions',this)"><i class="ti ti-handshake"></i> My Sessions</button>
      <div class="nav-label">Account</div>
      <button class="nav-btn" onclick="goTo('profile',this)"><i class="ti ti-user"></i> My Profile</button>
      <?php if ($active_user['role'] === 'Moderator'): ?>
        <button class="nav-btn" onclick="goTo('mod',this)"><i class="ti ti-shield"></i> Mod Panel</button>
      <?php endif; ?>
    </nav>
    <a class="sidebar-user" onclick="goTo('profile',null)">
      <div class="avatar av-sm" style="background:<?= avatar_color((int)$active_user['id'], $palette) ?>"><?= htmlspecialchars(initials($active_user['name'])) ?></div>
      <div>
        <div style="font-size:12px;font-weight:700;color:#e2e2f0"><?= htmlspecialchars($active_user['name']) ?></div>
        <div style="font-size:10px;color:#5a5a7a">⭐ <?= number_format((float)$active_user['trust_score'], 1) ?> · <?= htmlspecialchars($active_user['role']) ?></div>
      </div>
    </a>
    <a class="sidebar-logout" href="backend.php?logout=1"><i class="ti ti-logout"></i> Log Out</a>
  </aside>

  <!-- ════════════════════ MAIN ════════════════════ -->
  <main class="main">
    <div class="topbar">
      <div class="topbar-title" id="topbar-title">Dashboard</div>
      <div class="notif-wrap">
        <div class="notif-btn" onclick="document.getElementById('notif-dropdown').classList.toggle('open')">
          <i class="ti ti-bell"></i>
          <?php if (count($notifications) > 0): ?><span class="notif-dot"></span><?php endif; ?>
        </div>
        <div class="notif-dropdown" id="notif-dropdown">
          <?php if (empty($notifications)): ?>
            <div class="notif-item">No notifications yet.</div>
          <?php else: foreach ($notifications as $n): ?>
            <div class="notif-item"><?= htmlspecialchars($n['text']) ?></div>
          <?php endforeach; endif; ?>
        </div>
      </div>
      <button class="btn btn-primary" onclick="openModal('modal-offer')"><i class="ti ti-plus"></i> Post Offer</button>
    </div>

    <div class="content">

      <!-- ══ DASHBOARD ══ -->
      <div class="page active" id="page-dashboard">
        <div class="dash-grid">
          <div class="card">
            <div class="card-title">🎯 Skill Match</div>
            <?php if ($suggested_match): ?>
              <div class="match-item">
                <div class="avatar av-md" style="background:<?= avatar_color((int)$suggested_match['id'], $palette) ?>"><?= htmlspecialchars(initials($suggested_match['user_name'])) ?></div>
                <div class="match-info">
                  <div class="match-name"><?= htmlspecialchars($suggested_match['user_name']) ?> <span style="color:var(--amber);font-size:10px"><?= stars((float)$suggested_match['trust_score']) ?></span></div>
                  <div class="match-chips">
                    <span class="chip chip-green">Offers <?= htmlspecialchars($suggested_match['skills_offer']) ?></span>
                    <span class="chip chip-purple">Needs <?= htmlspecialchars($suggested_match['skills_need']) ?></span>
                  </div>
                </div>
                <button class="btn btn-outline btn-sm" onclick="goTo('offers',null)">View →</button>
              </div>
            <?php else: ?>
              <div class="empty-note">No other listings yet — check back soon.</div>
            <?php endif; ?>
          </div>

          <div class="card">
            <div class="card-title">🤝 Active Session
              <button class="btn btn-outline btn-sm" onclick="goTo('sessions',null)">View All →</button>
            </div>
            <?php if ($dashboard_session): ?>
              <div class="session-item">
                <div class="session-head">
                  <span class="session-title"><?= htmlspecialchars($dashboard_session['title']) ?></span>
                  <span class="badge badge-active">Active</span>
                </div>
                <div class="milestones">
                  <?php for ($i = 1; $i <= 3; $i++): ?>
                    <div class="ms <?= $i < $dashboard_session['milestone'] ? 'done' : ($i == $dashboard_session['milestone'] ? 'curr' : '') ?>"></div>
                  <?php endfor; ?>
                </div>
                <div class="session-foot">
                  <span class="session-peer">with <?= htmlspecialchars($dashboard_session['peer_name']) ?></span>
                  <a class="btn btn-green btn-sm" href="backend.php?workflow_action=advance_milestone&target_id=<?= (int)$dashboard_session['id'] ?>">✔ Confirm Milestone</a>
                </div>
              </div>
            <?php else: ?>
              <div class="empty-note">No active sessions right now.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ══ OFFERS ══ -->
      <div class="page" id="page-offers">
        <div class="section-header">
          <div>
            <div class="section-title">Skill Offers &amp; Requests</div>
            <div class="section-sub"><?= count($listings) ?> listing<?= count($listings) === 1 ? '' : 's' ?> from the community</div>
          </div>
          <button class="btn btn-primary" onclick="openModal('modal-offer')"><i class="ti ti-plus"></i> Post Offer</button>
        </div>
        <div class="filters">
          <button class="filter-btn active" onclick="filterOffers('all',this)">All</button>
          <button class="filter-btn" onclick="filterOffers('science',this)">🔬 Science</button>
          <button class="filter-btn" onclick="filterOffers('tech',this)">💻 Tech</button>
          <button class="filter-btn" onclick="filterOffers('other',this)">✨ Other</button>
        </div>
        <div class="offer-grid" id="offer-grid">
          <?php if (empty($listings)): ?>
            <div class="empty-note">No listings yet — be the first to post one.</div>
          <?php endif; ?>
          <?php foreach ($listings as $l): ?>
            <div class="offer-card" data-cat="<?= htmlspecialchars($l['category']) ?>">
              <div class="offer-head">
                <div class="avatar av-md" style="background:<?= avatar_color((int)$l['id'], $palette) ?>"><?= htmlspecialchars(initials($l['user_name'])) ?></div>
                <div class="offer-user"><div class="name"><?= htmlspecialchars($l['user_name']) ?></div><div class="sub"><?= htmlspecialchars($l['category']) ?></div></div>
                <span style="color:var(--amber);font-size:11px;font-weight:700;margin-left:auto">★ <?= number_format((float)$l['trust_score'], 1) ?></span>
              </div>
              <div class="offer-title"><?= htmlspecialchars($l['title']) ?></div>
              <div class="offer-desc"><?= htmlspecialchars($l['description']) ?></div>
              <div class="trade-box">
                <div><div class="trade-label">🟢 Offers</div><div class="trade-val"><?= htmlspecialchars($l['skills_offer']) ?></div></div>
                <div class="trade-arrow">⇄</div>
                <div><div class="trade-label">🔵 Needs</div><div class="trade-val"><?= htmlspecialchars($l['skills_need']) ?></div></div>
              </div>
              <?php if ($l['user_name'] === $active_user['name']): ?>
                <button class="btn btn-outline w100" disabled>This is your listing</button>
              <?php else: ?>
                <button class="btn btn-primary w100" onclick="openProposeModal(<?= (int)$l['id'] ?>, '<?= htmlspecialchars(addslashes($l['title'])) ?>')">🤝 Propose Exchange</button>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ══ SESSIONS ══ -->
      <div class="page" id="page-sessions">
        <div class="section-header">
          <div>
            <div class="section-title">My Sessions</div>
            <div class="section-sub">Every exchange you've proposed or received</div>
          </div>
        </div>

        <?php if (empty($sessions)): ?>
          <div class="empty-note">No sessions yet — propose an exchange from the Offers page to get started.</div>
        <?php endif; ?>

        <?php foreach ($sessions as $s): ?>
          <div class="session-full">
            <div class="flex-between mb12">
              <span class="session-title" style="font-size:15px"><?= htmlspecialchars($s['title']) ?></span>
              <?php
                $badge_class = ['Pending' => 'badge-pending', 'Active' => 'badge-active', 'Completed' => 'badge-done', 'Declined' => 'badge-declined'][$s['status']] ?? 'badge-pending';
              ?>
              <span class="badge <?= $badge_class ?>"><?= htmlspecialchars($s['status']) ?></span>
            </div>
            <p style="font-size:11px;color:var(--text2);margin-bottom:12px">Peer: <strong style="color:var(--text)"><?= htmlspecialchars($s['peer_name']) ?></strong></p>

            <div class="ms-detailed">
              <?php
                $labels = ['Agreement', 'Milestone 1', 'Milestone 2', 'Final Review'];
                foreach ($labels as $i => $label):
                    if ($s['status'] === 'Declined') { $cls = 'pending-ms'; $icon = '✗'; }
                    elseif ($i < $s['milestone']) { $cls = 'done'; $icon = '✔'; }
                    elseif ($i === $s['milestone']) { $cls = 'curr'; $icon = '⋯'; }
                    else { $cls = 'pending-ms'; $icon = '○'; }
              ?>
                <div class="ms-box <?= $cls ?>"><div class="ms-icon"><?= $icon ?></div><div class="ms-lbl"><?= $label ?></div></div>
              <?php endforeach; ?>
            </div>

            <div class="session-actions">
              <?php if ($s['status'] === 'Pending'): ?>
                <a class="btn btn-primary" href="backend.php?workflow_action=confirm_agreement&target_id=<?= (int)$s['id'] ?>">✔ Confirm Agreement</a>
                <a class="btn btn-outline" href="backend.php?workflow_action=decline&target_id=<?= (int)$s['id'] ?>">✗ Decline</a>
              <?php elseif ($s['status'] === 'Active'): ?>
                <a class="btn btn-green" href="backend.php?workflow_action=advance_milestone&target_id=<?= (int)$s['id'] ?>">✔ Confirm Milestone</a>
              <?php elseif ($s['status'] === 'Completed'): ?>
                <button class="btn btn-outline" onclick="openReviewModal(<?= (int)$s['id'] ?>)">⭐ Leave Review</button>
              <?php endif; ?>
            </div>

            <?php $updates = $milestone_updates[(int)$s['id']] ?? []; ?>
            <?php if ($updates): ?>
              <div class="update-list">
                <?php foreach ($updates as $u): ?>
                  <div class="update-item">
                    <div class="update-head">
                      <span class="update-author"><?= htmlspecialchars($u['author_name']) ?></span>
                      <span class="update-meta">Milestone <?= (int)$u['milestone_step'] + 1 ?> · <?= htmlspecialchars(date('M j, g:ia', strtotime($u['created_at']))) ?></span>
                    </div>
                    <?php if (!empty($u['note'])): ?>
                      <p class="update-note"><?= nl2br(htmlspecialchars($u['note'])) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($u['file_path'])): ?>
                      <?php if (is_image_ext($u['file_name'])): ?>
                        <a class="update-thumb-link" href="<?= htmlspecialchars($u['file_path']) ?>" target="_blank" rel="noopener">
                          <img class="update-thumb" src="<?= htmlspecialchars($u['file_path']) ?>" alt="<?= htmlspecialchars($u['file_name']) ?>">
                        </a>
                      <?php else: ?>
                        <a class="update-file-chip" href="<?= htmlspecialchars($u['file_path']) ?>" target="_blank" rel="noopener">
                          <i class="ti <?= file_icon($u['file_name']) ?>"></i>
                          <span><?= htmlspecialchars($u['file_name']) ?></span>
                          <span class="update-file-size"><?= format_bytes((int)$u['file_size']) ?></span>
                        </a>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if ($s['status'] === 'Active'): ?>
              <form class="update-form" method="POST" action="backend.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="post_milestone_update">
                <input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>">
                <textarea class="form-ctrl" name="note" placeholder="Post a progress update — what's done, what's next..." rows="2"></textarea>
                <div class="update-form-row">
                  <label class="file-input-label">
                    <i class="ti ti-paperclip"></i> Attach file
                    <input class="file-input-hidden" type="file" name="attachment" onchange="showFileName(this)">
                  </label>
                  <span class="file-input-name"></span>
                  <button type="submit" class="btn btn-primary btn-sm">Post Update</button>
                </div>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ══ PROFILE ══ -->
      <div class="page" id="page-profile">
        <div class="profile-hero">
          <div class="avatar av-lg" style="background:<?= avatar_color((int)$active_user['id'], $palette) ?>"><?= htmlspecialchars(initials($active_user['name'])) ?></div>
          <div class="profile-info">
            <h2><?= htmlspecialchars($active_user['name']) ?></h2>
            <div class="profile-stars"><?= stars((float)$active_user['trust_score']) ?> <span style="font-size:11px;color:var(--text2)"><?= number_format((float)$active_user['trust_score'], 1) ?> / 5 · <?= count($my_reviews) ?> review<?= count($my_reviews) === 1 ? '' : 's' ?></span></div>
            <p class="profile-bio"><?= htmlspecialchars($active_user['bio']) ?></p>
          </div>
          <button class="btn btn-outline" onclick="openModal('modal-edit-profile')">✏️ Edit Profile</button>
        </div>

        <div class="profile-grid">
          <div class="card">
            <div class="card-title">🟢 Skills I Offer</div>
            <div class="pills-wrap"><span class="pill pill-purple"><?= htmlspecialchars($active_user['skills_offer']) ?></span></div>
          </div>
          <div class="card">
            <div class="card-title">🔵 Skills I Need</div>
            <div class="pills-wrap"><span class="pill pill-green"><?= htmlspecialchars($active_user['skills_need']) ?></span></div>
          </div>
          <div class="card" style="grid-column:1/-1">
            <div class="card-title">⭐ Reviews</div>
            <?php if (empty($my_reviews)): ?>
              <div class="empty-note">No reviews yet.</div>
            <?php else: foreach ($my_reviews as $r): ?>
              <div class="review-item">
                <div class="review-head">
                  <div class="avatar av-sm" style="background:#7c5ce7"><?= htmlspecialchars(initials($r['reviewer_name'])) ?></div>
                  <div class="review-meta"><div class="rn"><?= htmlspecialchars($r['reviewer_name']) ?></div><div class="rd"><?= date('F j, Y', strtotime($r['created_at'])) ?></div></div>
                  <span style="color:var(--amber)"><?= stars((float)$r['rating']) ?></span>
                </div>
                <?php if ($r['comment']): ?><div class="review-text"><?= htmlspecialchars($r['comment']) ?></div><?php endif; ?>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>

      <!-- ══ MOD PANEL ══ -->
      <?php if ($active_user['role'] === 'Moderator'): ?>
      <div class="page" id="page-mod">
        <div class="section-header">
          <div>
            <div class="section-title">🛡️ Moderator Panel</div>
            <div class="section-sub">Open reports — warn / suspend / dismiss</div>
          </div>
        </div>
        <div class="card">
          <div class="card-title">📋 Reports</div>
          <?php if (empty($reports)): ?>
            <div class="empty-note">No reports on file.</div>
          <?php else: foreach ($reports as $r): $resolved = str_starts_with($r['status'], 'Resolved'); ?>
            <div class="report-item" style="<?= $resolved ? 'opacity:0.4' : '' ?>; margin-bottom:8px">
              <div class="report-icon">🚨</div>
              <div class="report-info">
                <h4>Report vs. <?= htmlspecialchars($r['reported']) ?></h4>
                <p><?= htmlspecialchars($r['reason']) ?> — <em><?= htmlspecialchars($r['status']) ?></em></p>
              </div>
              <div class="report-actions">
                <a class="btn btn-amber btn-sm" href="backend.php?mod_action=warn&report_id=<?= (int)$r['id'] ?>">⚠️ Warn</a>
                <a class="btn btn-red btn-sm" href="backend.php?mod_action=suspend&report_id=<?= (int)$r['id'] ?>">🔒 Suspend</a>
                <a class="btn btn-outline btn-sm" href="backend.php?mod_action=dismiss&report_id=<?= (int)$r['id'] ?>">✓ Dismiss</a>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /content -->
  </main>

  <!-- ════════════════════ MODALS ════════════════════ -->

  <div class="modal-overlay" id="modal-offer" onclick="if(event.target===this)closeModal('modal-offer')">
    <div class="modal">
      <div class="modal-title">📢 Post a Skill Offer</div>
      <form method="POST" action="backend.php">
        <input type="hidden" name="action" value="create_listing">
        <label class="form-label">Listing Title *</label>
        <input class="form-ctrl" type="text" name="title" placeholder="e.g. Physics Tutor — Mechanics" required>
        <label class="form-label">Category</label>
        <select class="form-ctrl" name="category">
          <option value="science">Science</option>
          <option value="tech">Tech</option>
          <option value="other">Other</option>
        </select>
        <label class="form-label">Skill You're Offering *</label>
        <input class="form-ctrl" type="text" name="skills_offer" placeholder="e.g. Python Debugging" required>
        <label class="form-label">Skill You Need in Return *</label>
        <input class="form-ctrl" type="text" name="skills_need" placeholder="e.g. Video Editing" required>
        <label class="form-label">Description</label>
        <textarea class="form-ctrl" name="description" placeholder="Tell people a bit more..."></textarea>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" onclick="closeModal('modal-offer')">Cancel</button>
          <button type="submit" class="btn btn-primary">🚀 Post Offer</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal-overlay" id="modal-request" onclick="if(event.target===this)closeModal('modal-request')">
    <div class="modal">
      <div class="modal-title">🤝 Propose Exchange</div>
      <form method="POST" action="backend.php">
        <input type="hidden" name="action" value="propose_exchange">
        <input type="hidden" name="listing_id" id="propose-listing-id" value="">
        <p style="font-size:12px;color:var(--text2);margin-bottom:16px">
          Send a proposal for "<strong id="propose-listing-title" style="color:var(--text)"></strong>"? The listing owner will get a notification and can confirm to start the session.
        </p>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" onclick="closeModal('modal-request')">Cancel</button>
          <button type="submit" class="btn btn-primary">Send Proposal →</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal-overlay" id="modal-review" onclick="if(event.target===this)closeModal('modal-review')">
    <div class="modal">
      <div class="modal-title">⭐ Leave a Review</div>
      <form method="POST" action="backend.php">
        <input type="hidden" name="action" value="submit_review">
        <input type="hidden" name="session_id" id="review-session-id" value="">
        <input type="hidden" name="rating" id="review-rating-input" value="5">
        <label class="form-label">Your Rating</label>
        <div class="star-pick" id="star-pick">
          <span onclick="setStars(1)">★</span>
          <span onclick="setStars(2)">★</span>
          <span onclick="setStars(3)">★</span>
          <span onclick="setStars(4)">★</span>
          <span onclick="setStars(5)">★</span>
        </div>
        <label class="form-label">Review</label>
        <textarea class="form-ctrl" name="comment" placeholder="How was the exchange?"></textarea>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" onclick="closeModal('modal-review')">Cancel</button>
          <button type="submit" class="btn btn-primary">Submit Review ⭐</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal-overlay" id="modal-edit-profile" onclick="if(event.target===this)closeModal('modal-edit-profile')">
    <div class="modal">
      <div class="modal-title">✏️ Edit Profile</div>
      <form method="POST" action="backend.php">
        <input type="hidden" name="action" value="update_profile">
        <label class="form-label">Display Name</label>
        <input class="form-ctrl" type="text" name="name" value="<?= htmlspecialchars($active_user['name']) ?>" required>
        <label class="form-label">Bio</label>
        <textarea class="form-ctrl" name="bio"><?= htmlspecialchars($active_user['bio']) ?></textarea>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" onclick="closeModal('modal-edit-profile')">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

<?php endif; ?>

<div id="toast-wrap"></div>

<script>
  const pageNames = { dashboard: 'Dashboard', offers: 'Skill Offers & Requests', sessions: 'My Sessions', profile: 'My Profile', mod: 'Moderator Panel' };
  function goTo(id, clickedBtn) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('page-' + id).classList.add('active');
    if (clickedBtn) {
      clickedBtn.classList.add('active');
    } else {
      document.querySelectorAll('.nav-btn').forEach(b => { if (b.getAttribute('onclick') && b.getAttribute('onclick').includes("'" + id + "'")) b.classList.add('active'); });
    }
    document.getElementById('topbar-title').textContent = pageNames[id] || id;
    window.scrollTo(0, 0);
  }

  function openModal(id) { document.getElementById(id).classList.add('open'); }
  function closeModal(id) { document.getElementById(id).classList.remove('open'); }

  function showFileName(input) {
    const label = input.closest('.update-form-row').querySelector('.file-input-name');
    label.textContent = input.files.length ? input.files[0].name : '';
  }

  function openProposeModal(listingId, listingTitle) {
    document.getElementById('propose-listing-id').value = listingId;
    document.getElementById('propose-listing-title').textContent = listingTitle;
    openModal('modal-request');
  }

  function openReviewModal(sessionId) {
    document.getElementById('review-session-id').value = sessionId;
    setStars(5);
    openModal('modal-review');
  }

  function setStars(n) {
    document.querySelectorAll('#star-pick span').forEach((s, i) => s.classList.toggle('lit', i < n));
    document.getElementById('review-rating-input').value = n;
  }

  function toast(msg, type) {
    const wrap = document.getElementById('toast-wrap');
    const el = document.createElement('div');
    el.className = 'toast t-' + type;
    const icons = { success: '✅', error: '❌', info: '💬' };
    el.innerHTML = '<span>' + (icons[type] || '💬') + '</span><span>' + msg + '</span>';
    wrap.appendChild(el);
    setTimeout(() => el.remove(), 3400);
  }

  function filterOffers(cat, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.offer-card').forEach(c => { c.style.display = (cat === 'all' || c.dataset.cat === cat) ? '' : 'none'; });
  }

  function showAuthForm(which) {
    document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
    document.getElementById('tab-' + which).classList.add('active');
    document.getElementById('form-' + which).classList.add('active');
  }

  document.addEventListener('click', (e) => {
    const dd = document.getElementById('notif-dropdown');
    const wrap = document.querySelector('.notif-wrap');
    if (dd && wrap && !wrap.contains(e.target)) dd.classList.remove('open');
  });

  <?php if ($flash): ?>
    window.addEventListener('DOMContentLoaded', () => toast(<?= json_encode($flash[0]) ?>, <?= json_encode($flash[1]) ?>));
  <?php endif; ?>
</script>
</body>
</html>

//
