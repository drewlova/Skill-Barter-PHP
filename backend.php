<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php'; // provides $pdo

// ------------------------------------------------------------
// MILESTONE UPDATE ATTACHMENTS
// Where screenshots / drafts / deliverables get saved when someone
// posts a progress update on an active session.
// ------------------------------------------------------------
define('MILESTONE_UPLOAD_DIR', __DIR__ . '/uploads/milestones/');
define('MILESTONE_UPLOAD_URL', 'uploads/milestones/'); // relative, for use in <a href>/<img src>
define('MILESTONE_UPLOAD_MAX_BYTES', 10 * 1024 * 1024); // 10MB
define('MILESTONE_ALLOWED_EXTENSIONS', [
    'jpg', 'jpeg', 'png', 'gif', 'webp',                 // screenshots
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',  // documents
    'txt', 'csv', 'zip',                                  // misc / updated files
]);

$backend_error = "";
$backend_success = "";

// Pick up a message left by a previous request (see the redirect-with-flash
// logic at the end of the POST handling block below).
if (!empty($_SESSION['flash'])) {
    if ($_SESSION['flash']['type'] === 'error') {
        $backend_error = $_SESSION['flash']['msg'];
    } else {
        $backend_success = $_SESSION['flash']['msg'];
    }
    unset($_SESSION['flash']);
}

// ------------------------------------------------------------
// HELPERS
// ------------------------------------------------------------

/**
 * Fetches the currently logged-in user fresh from the database
 * (so trust_score / bio / etc. are never stale), based on the
 * user id stored in the session at login time.
 */
function get_logged_in_user(PDO $pdo): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id, name, email, role, bio, skills_offer, skills_need, trust_score FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

$active_user = get_logged_in_user($pdo);

// ------------------------------------------------------------
// POST ACTIONS
// ------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {

    // ---------- SIGN UP ----------
    if ($action === 'signup') {
        $name         = trim($_POST['name'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $password     = $_POST['password'] ?? '';
        $role         = 'Member'; // roles are not self-assignable from the sign up form
        $skills_offer = trim($_POST['skills_offer'] ?? '') ?: 'None';
        $skills_need  = trim($_POST['skills_need'] ?? '') ?: 'None';

        if (empty($name) || empty($email) || empty($password)) {
            $backend_error = "Please fill in all required sign up fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $backend_error = "Please enter a valid email address.";
        } else {
            $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $check->execute([$email]);

            if ($check->fetch()) {
                $backend_error = "Email address is already registered.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insert = $pdo->prepare(
                    'INSERT INTO users (name, email, password_hash, role, bio, skills_offer, skills_need, trust_score)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 5.00)'
                );
                $insert->execute([
                    $name, $email, $hash, $role,
                    'New member exploring skill swaps.',
                    $skills_offer, $skills_need
                ]);
                $backend_success = "Sign up successful! You can now log in.";
            }
        }
    }

    // ---------- LOG IN ----------
    if ($action === 'login') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $backend_error = "Validation Error: Complete all input fields.";
        } else {
            $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                header("Location: index.php");
                exit();
            } else {
                $backend_error = "Authentication Error: Credentials not found. Please sign up first.";
            }
        }
    }

    // ---------- CREATE LISTING ----------
    if ($action === 'create_listing' && $active_user) {
        $title        = trim($_POST['title'] ?? '');
        $category     = $_POST['category'] ?? 'tech';
        $skills_offer = trim($_POST['skills_offer'] ?? '');
        $skills_need  = trim($_POST['skills_need'] ?? '');
        $description  = trim($_POST['description'] ?? '');

        if ($title && $skills_offer && $skills_need) {
            $insert = $pdo->prepare(
                'INSERT INTO listings (user_id, title, category, skills_offer, skills_need, description)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([$active_user['id'], $title, $category, $skills_offer, $skills_need, $description]);

            $notify = $pdo->prepare('INSERT INTO notifications (user_id, text, type) VALUES (?, ?, ?)');
            $notify->execute([$active_user['id'], "Listing posted: $title", 'success']);

            header("Location: index.php?status=posted");
            exit();
        } else {
            $backend_error = "Please fill in the title and both skill fields.";
        }
    }

    // ---------- PROPOSE EXCHANGE ----------
    // Creates a new Pending exchange_session between the current user
    // (requester) and the owner of the listing they're responding to.
    if ($action === 'propose_exchange' && $active_user) {
        $listing_id = (int)($_POST['listing_id'] ?? 0);

        $stmt = $pdo->prepare('SELECT id, user_id, title FROM listings WHERE id = ?');
        $stmt->execute([$listing_id]);
        $listing = $stmt->fetch();

        if (!$listing) {
            $backend_error = "That listing no longer exists.";
        } elseif ((int)$listing['user_id'] === $active_user['id']) {
            $backend_error = "You can't propose an exchange on your own listing.";
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO exchange_sessions (listing_id, requester_id, provider_id, title, status, milestone)
                 VALUES (?, ?, ?, ?, ?, 0)'
            );
            $insert->execute([$listing_id, $active_user['id'], $listing['user_id'], $listing['title'], 'Pending']);

            $notify = $pdo->prepare('INSERT INTO notifications (user_id, text, type) VALUES (?, ?, ?)');
            $notify->execute([$listing['user_id'], $active_user['name'] . " proposed an exchange: " . $listing['title'], 'info']);

            header("Location: index.php?status=proposal_sent");
            exit();
        }
    }

    // ---------- UPDATE PROFILE ----------
    if ($action === 'update_profile' && $active_user) {
        $name = trim($_POST['name'] ?? '');
        $bio  = trim($_POST['bio'] ?? '');

        if (!empty($name)) {
            $update = $pdo->prepare('UPDATE users SET name = ?, bio = ? WHERE id = ?');
            $update->execute([$name, $bio, $active_user['id']]);

            header("Location: index.php?status=profile_updated");
            exit();
        } else {
            $backend_error = "Display name cannot be empty.";
        }
    }

    // ---------- SUBMIT REVIEW ----------
    // Expects: session_id (which exchange session this review is for)
    // and rating (1-5). The reviewee is inferred as "whichever side
    // of the session isn't the current user."
    if ($action === 'submit_review' && $active_user) {
        $session_id = (int)($_POST['session_id'] ?? 0);
        $rating     = (int)($_POST['rating'] ?? 0);
        $comment    = trim($_POST['comment'] ?? '');

        if ($rating < 1 || $rating > 5) {
            $backend_error = "Rating must be between 1 and 5.";
        } else {
            $stmt = $pdo->prepare('SELECT requester_id, provider_id FROM exchange_sessions WHERE id = ?');
            $stmt->execute([$session_id]);
            $session = $stmt->fetch();

            if (!$session) {
                $backend_error = "That session could not be found.";
            } elseif (!in_array($active_user['id'], [$session['requester_id'], $session['provider_id']])) {
                $backend_error = "You're not part of that session.";
            } else {
                $reviewee_id = ($session['requester_id'] == $active_user['id'])
                    ? $session['provider_id']
                    : $session['requester_id'];

                try {
                    $insert = $pdo->prepare(
                        'INSERT INTO reviews (session_id, reviewer_id, reviewee_id, rating, comment)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    $insert->execute([$session_id, $active_user['id'], $reviewee_id, $rating, $comment]);

                    // Recalculate the reviewee's trust score as the average of all their ratings.
                    $avg = $pdo->prepare('SELECT AVG(rating) AS avg_rating FROM reviews WHERE reviewee_id = ?');
                    $avg->execute([$reviewee_id]);
                    $new_score = round((float)$avg->fetch()['avg_rating'], 2);

                    $update = $pdo->prepare('UPDATE users SET trust_score = ? WHERE id = ?');
                    $update->execute([$new_score, $reviewee_id]);

                    header("Location: index.php?status=review_logged");
                    exit();
                } catch (PDOException $e) {
                    // Most likely the unique (session_id, reviewer_id) constraint —
                    // i.e. this user already reviewed this session.
                    $backend_error = "You've already reviewed this session.";
                }
            }
        }
    }

    // ---------- POST MILESTONE UPDATE ----------
    // Lets either side of an *active* exchange post a progress note and/or
    // attach one file (a screenshot, a draft, an updated deliverable, etc).
    if ($action === 'post_milestone_update' && $active_user) {
        $session_id = (int)($_POST['session_id'] ?? 0);
        $note       = trim($_POST['note'] ?? '');

        $stmt = $pdo->prepare('SELECT * FROM exchange_sessions WHERE id = ?');
        $stmt->execute([$session_id]);
        $session = $stmt->fetch();

        if (!$session) {
            $backend_error = "That session could not be found.";
        } elseif (!in_array($active_user['id'], [$session['requester_id'], $session['provider_id']])) {
            $backend_error = "You're not part of that session.";
        } elseif ($session['status'] !== 'Active') {
            $backend_error = "Updates can only be posted on an active session.";
        } else {
            $has_file = isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE;

            if ($note === '' && !$has_file) {
                $backend_error = "Add a note or attach a file before posting an update.";
            } else {
                $file_path = null;
                $file_name = null;
                $file_size = null;

                if ($has_file) {
                    $file = $_FILES['attachment'];

                    if ($file['error'] !== UPLOAD_ERR_OK) {
                        $backend_error = "That file couldn't be uploaded — please try again.";
                    } elseif ($file['size'] > MILESTONE_UPLOAD_MAX_BYTES) {
                        $backend_error = "That file is too big — the limit is " . (int)(MILESTONE_UPLOAD_MAX_BYTES / 1024 / 1024) . "MB.";
                    } else {
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        if (!in_array($ext, MILESTONE_ALLOWED_EXTENSIONS, true)) {
                            $backend_error = "That file type isn't allowed. Allowed types: " . implode(', ', MILESTONE_ALLOWED_EXTENSIONS) . ".";
                        } else {
                            if (!is_dir(MILESTONE_UPLOAD_DIR)) {
                                mkdir(MILESTONE_UPLOAD_DIR, 0755, true);
                            }
                            // Random name on disk — never trust/reuse the client's filename,
                            // both to avoid collisions and to prevent path tricks.
                            $stored_name = bin2hex(random_bytes(16)) . '.' . $ext;
                            $dest = MILESTONE_UPLOAD_DIR . $stored_name;

                            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                                $backend_error = "That file couldn't be saved. Please try again.";
                            } else {
                                $file_path = MILESTONE_UPLOAD_URL . $stored_name;
                                $file_name = $file['name']; // original name, kept only for display
                                $file_size = $file['size'];
                            }
                        }
                    }
                }

                if (!$backend_error) {
                    $insert = $pdo->prepare(
                        'INSERT INTO milestone_updates (session_id, user_id, milestone_step, note, file_path, file_name, file_size)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $insert->execute([
                        $session_id,
                        $active_user['id'],
                        $session['milestone'],
                        $note !== '' ? $note : null,
                        $file_path,
                        $file_name,
                        $file_size,
                    ]);

                    $peer_id = ($session['requester_id'] == $active_user['id']) ? $session['provider_id'] : $session['requester_id'];
                    $notify = $pdo->prepare('INSERT INTO notifications (user_id, text, type) VALUES (?, ?, ?)');
                    $notify->execute([$peer_id, $active_user['name'] . " posted an update on \"" . $session['title'] . "\"", 'info']);

                    header("Location: index.php?status=update_posted");
                    exit();
                }
            }
        }
    }
    } catch (PDOException $e) {
        // Any unexpected database error lands here instead of crashing
        // the script with no output.
        $backend_error = "A database error occurred. Please try again.";
    }

    // Any branch above that didn't already redirect (signup success, or any
    // validation error) falls through to here. Stash the message in the
    // session and send the browser back to index.php so it's never left
    // staring at a blank backend.php response.
    if ($backend_error) {
        $_SESSION['flash'] = ['msg' => $backend_error, 'type' => 'error'];
        header("Location: index.php");
        exit();
    }
    if ($backend_success) {
        $_SESSION['flash'] = ['msg' => $backend_success, 'type' => 'success'];
        header("Location: index.php");
        exit();
    }

    // Safety net: if we reach this line, the submitted action didn't match
    // any handler above (e.g. the session had expired, so an "&& $active_user"
    // guard silently skipped the block). Don't leave the browser sitting on
    // a blank backend.php response — send it back to index.php, which will
    // show the login screen if the session really is gone.
    header("Location: index.php");
    exit();
}

// ------------------------------------------------------------
// GET ACTIONS — session workflow (confirm agreement / advance milestone)
// ------------------------------------------------------------

if (isset($_GET['workflow_action']) && $active_user) {
    $session_id = (int)($_GET['target_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT * FROM exchange_sessions WHERE id = ?');
    $stmt->execute([$session_id]);
    $session = $stmt->fetch();

    if ($session && in_array($active_user['id'], [$session['requester_id'], $session['provider_id']])) {
        if ($_GET['workflow_action'] === 'confirm_agreement') {
            $update = $pdo->prepare('UPDATE exchange_sessions SET status = ? WHERE id = ?');
            $update->execute(['Active', $session_id]);
        } elseif ($_GET['workflow_action'] === 'advance_milestone') {
            if ($session['milestone'] < 3) {
                $update = $pdo->prepare('UPDATE exchange_sessions SET milestone = milestone + 1 WHERE id = ?');
                $update->execute([$session_id]);
            } else {
                $update = $pdo->prepare('UPDATE exchange_sessions SET status = ? WHERE id = ?');
                $update->execute(['Completed', $session_id]);
            }
        } elseif ($_GET['workflow_action'] === 'decline' && $session['status'] === 'Pending') {
            $update = $pdo->prepare('UPDATE exchange_sessions SET status = ? WHERE id = ?');
            $update->execute(['Declined', $session_id]);
        }
    }

    header("Location: index.php?status=milestone_changed");
    exit();
}

// ------------------------------------------------------------
// GET ACTIONS — moderator enforcement
// ------------------------------------------------------------

if (isset($_GET['mod_action']) && $active_user && $active_user['role'] === 'Moderator') {
    $report_id = (int)($_GET['report_id'] ?? 0);
    $mod_action = $_GET['mod_action'];
    $new_status = 'Resolved (' . $mod_action . ')';

    $update = $pdo->prepare('UPDATE reports SET status = ? WHERE id = ?');
    $update->execute([$new_status, $report_id]);

    header("Location: index.php?status=moderation_enforced");
    exit();
}

// ------------------------------------------------------------
// LOGOUT
// ------------------------------------------------------------

if (isset($_GET['logout'])) {
    unset($_SESSION['user_id']);
    session_destroy();
    header("Location: index.php");
    exit();
}

// ------------------------------------------------------------
// DATA FOR THE PAGE
// Fetched fresh from the database on every request — this is
// what index.php should loop over instead of $_SESSION['db_*'].
// ------------------------------------------------------------

$listings = $pdo->query(
    'SELECT l.id, l.title, l.category, l.skills_offer, l.skills_need, l.description,
            u.name AS user_name, u.trust_score
     FROM listings l
     JOIN users u ON u.id = l.user_id
     ORDER BY l.created_at DESC'
)->fetchAll();

$sessions = [];
if ($active_user) {
    $stmt = $pdo->prepare(
        'SELECT s.id, s.title, s.status, s.milestone,
                s.requester_id, s.provider_id,
                ur.name AS requester_name, up.name AS provider_name
         FROM exchange_sessions s
         JOIN users ur ON ur.id = s.requester_id
         JOIN users up ON up.id = s.provider_id
         WHERE s.requester_id = ? OR s.provider_id = ?
         ORDER BY s.created_at DESC'
    );
    $stmt->execute([$active_user['id'], $active_user['id']]);
    $sessions = $stmt->fetchAll();

    // Attach a "peer_name" for whichever side isn't the current user,
    // matching the shape the old session-array version used.
    foreach ($sessions as &$s) {
        $s['peer_name'] = ($s['requester_id'] == $active_user['id']) ? $s['provider_name'] : $s['requester_name'];
    }
    unset($s);
}

// Progress updates (notes + attachments) for every session the user is part
// of, keyed by session_id so index.php can loop $milestone_updates[$s['id']].
$milestone_updates = [];
if ($active_user) {
    $stmt = $pdo->prepare(
        'SELECT mu.id, mu.session_id, mu.milestone_step, mu.note,
                mu.file_path, mu.file_name, mu.file_size, mu.created_at,
                u.id AS author_id, u.name AS author_name
         FROM milestone_updates mu
         JOIN users u ON u.id = mu.user_id
         WHERE mu.session_id IN (
             SELECT id FROM exchange_sessions WHERE requester_id = ? OR provider_id = ?
         )
         ORDER BY mu.created_at ASC'
    );
    $stmt->execute([$active_user['id'], $active_user['id']]);
    foreach ($stmt->fetchAll() as $row) {
        $milestone_updates[(int)$row['session_id']][] = $row;
    }
}

$notifications = [];
if ($active_user) {
    $stmt = $pdo->prepare(
        'SELECT id, text, type, is_read FROM notifications
         WHERE user_id = ? OR user_id IS NULL
         ORDER BY created_at DESC LIMIT 20'
    );
    $stmt->execute([$active_user['id']]);
    $notifications = $stmt->fetchAll();
} else {
    $stmt = $pdo->query('SELECT id, text, type FROM notifications WHERE user_id IS NULL ORDER BY created_at DESC LIMIT 20');
    $notifications = $stmt->fetchAll();
}

// One listing from someone else, for the dashboard's "Skill Match" card.
$suggested_match = null;
if ($active_user) {
    $stmt = $pdo->prepare(
        'SELECT l.id, l.title, l.skills_offer, l.skills_need, u.name AS user_name, u.trust_score
         FROM listings l JOIN users u ON u.id = l.user_id
         WHERE l.user_id != ?
         ORDER BY RAND() LIMIT 1'
    );
    $stmt->execute([$active_user['id']]);
    $suggested_match = $stmt->fetch() ?: null;
}

// The user's most recently active session, for the dashboard's "Active Session" card.
$dashboard_session = null;
foreach ($sessions as $s) {
    if ($s['status'] === 'Active') {
        $dashboard_session = $s;
        break;
    }
}

$my_reviews = [];
if ($active_user) {
    $stmt = $pdo->prepare(
        'SELECT r.rating, r.comment, r.created_at, u.name AS reviewer_name
         FROM reviews r JOIN users u ON u.id = r.reviewer_id
         WHERE r.reviewee_id = ?
         ORDER BY r.created_at DESC'
    );
    $stmt->execute([$active_user['id']]);
    $my_reviews = $stmt->fetchAll();
}

$reports = [];
if ($active_user && $active_user['role'] === 'Moderator') {
    $stmt = $pdo->query(
        "SELECT r.id, r.reason, r.status,
                ur.name AS reporter, ud.name AS reported
         FROM reports r
         JOIN users ur ON ur.id = r.reporter_id
         JOIN users ud ON ud.id = r.reported_id
         ORDER BY r.created_at DESC"
    );
    $reports = $stmt->fetchAll();
}
?>
