<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

switch ($_GET['action'] ?? '') {
    case 'random_number':
        $number = getRandomAvailableNumber($conn);
        if ($number) {
            echo json_encode(['number' => $number]);
        } else {
            echo json_encode(['error' => t('ajax.no_numbers')]);
        }
        break;

    case 'verifiable_random':
        // Certified random draw following the global dashboard mode (no manual numbers).
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => t('ajax.post_required')]);
            break;
        }
        requireCsrf();
        $draw_mode = getDrawMode($conn);
        if ($draw_mode === 'manual') {
            echo json_encode(['error' => t('ajax.manual_active')]);
            break;
        }
        $prize_id = (int)($_POST['prize_id'] ?? 0);
        $prize_check = mysqli_query($conn, "SELECT id FROM prizes WHERE id = $prize_id AND is_active = 1 AND id NOT IN (SELECT prize_id FROM winners)");
        if (!$prize_check || mysqli_num_rows($prize_check) === 0) {
            echo json_encode(['error' => t('ajax.prize_unavailable')]);
            break;
        }
        if (!drawTableExists($conn)) {
            echo json_encode(['error' => t('ajax.db_pending')]);
            break;
        }
        $drawn = null;
        $provider = null;
        $raw = null;
        $digits = getNumberDigits($conn);
        if ($draw_mode === 'random_org') {
            $fetched = fetchRandomOrgNumber($digits);
            if (isset($fetched['error'])) {
                echo json_encode(['error' => t('ajax.random_failed', ['error' => $fetched['error']])]);
                break;
            }
            $drawn = $fetched['number'];
            $provider = 'random.org';
            $raw = $fetched['raw'];
        } else {
            $drawn = generateLocalRandomNumber($digits);
            $provider = 'local-csprng';
            $raw = $drawn;
        }
        $admin_id = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
        $audit_id = createDrawAudit($conn, $prize_id, $draw_mode, $drawn, $provider, $raw, $admin_id);
        if (!$audit_id) {
            echo json_encode(['error' => t('ajax.store_fail')]);
            break;
        }
        $found = findClosestParticipant($conn, $drawn);
        if (!$found) {
            echo json_encode(['error' => getDrawExhaustedMessage($conn), 'audit_id' => $audit_id, 'number' => $drawn]);
            break;
        }
        $audit = getDrawAuditById($conn, $audit_id);
        echo json_encode([
            'audit_id' => $audit_id,
            'number' => $drawn,
            'winning_number' => $found['winning_number'],
            'wrapped' => !empty($found['wrapped']),
            'participant' => ['id' => $found['participant']['id'], 'name' => $found['participant']['name']],
            'provider' => $provider,
            'proof' => substr($audit['proof_hash'], 0, 12),
        ]);
        break;

    default:
        echo json_encode(['error' => t('ajax.invalid')]);
}
