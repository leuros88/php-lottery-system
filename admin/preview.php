<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$ranges = getNumbersByRange($conn);
$news = getActiveNews($conn);
$header_info = getCustomText($conn, 'header_info');
$footer_info = getCustomText($conn, 'footer_info');

$total_participants = getParticipantCount($conn);
$available_numbers = getAvailableNumbers($conn);
$available_count = count($available_numbers);
$lottery_status = getCustomText($conn, 'lottery_status');
$has_winners = getWinnerCount($conn) > 0;
$maintenance = getCustomText($conn, 'maintenance_mode');
$maintenance_msg = getCustomText($conn, 'maintenance_message');
$logo_path = getCustomText($conn, 'logo_path');
$sponsors_result = mysqli_query($conn, "SELECT * FROM sponsors WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
$sponsors = [];
while ($row = mysqli_fetch_assoc($sponsors_result)) {
    $sponsors[] = $row;
}
$prizes = getActivePrizes($conn);
$winners = getWinnersWithDetails($conn);
$winners_by_prize = [];

$last_participant_row = null;
$lp_result = mysqli_query($conn, "SELECT name, created_at FROM participants ORDER BY id DESC LIMIT 1");
if ($lp_result) {
    $last_participant_row = mysqli_fetch_assoc($lp_result);
}
foreach ($winners as $w) {
    $winners_by_prize[$w['prize_name']] = $w;
}

require_once __DIR__ . '/header.php';
?>
<link rel="stylesheet" href="/assets/css/style.css">

<div class="beach-bg">
    
</div>

<div class="page-header">
    <h1><?= htmlspecialchars(t('prev.title')) ?></h1>
    <p class="page-description"><?= htmlspecialchars(t('prev.desc')) ?></p>
</div>

<div style="max-width:1200px; margin:0 auto;">

<?php if ($maintenance === 'on'): ?>
<div class="maintenance-layout" style="min-height:auto;padding:40px 0;">
    <div class="maintenance-page">
        <div class="container">
            <?= $maintenance_msg ?>
        </div>
    </div>
</div>
<?php else: ?>

    <section class="stats-bar" style="margin:0 0 25px;border-radius:12px;">
        <div class="container" style="padding:0;">
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-value"><?= $total_participants ?></span>
                    <span class="stat-label"><?= htmlspecialchars(t('pub.participants')) ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?= $available_count ?></span>
                    <span class="stat-label"><?= htmlspecialchars(t('pub.available')) ?></span>
                </div>
                <?php if ($last_participant_row): ?>
                <div class="stat-item">
                    <span class="stat-value"><?= htmlspecialchars($last_participant_row['name']) ?></span>
                    <span class="stat-label"><?= htmlspecialchars(t('pub.last_participant')) ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?= date('Y/m/d H:i', strtotime($last_participant_row['created_at'])) ?> UTC</span>
                    <span class="stat-label"><?= htmlspecialchars(t('pub.last_update')) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if (count($sponsors) > 0):
        $total_s = count($sponsors);
        $s_duration = $total_s * 5;
    ?>
    <style>
    .sp-slide { opacity: 0; visibility: hidden; }
    <?php foreach ($sponsors as $i => $s):
        $seg = 100 / $total_s;
        $show_start = $i * $seg + 0.01;
        $show_end = $i * $seg + $seg * 0.95;
        $hide_at = ($i + 1) * $seg;
    ?>
    @keyframes spFade<?= $i ?> {
        0% { opacity: 0; visibility: hidden; }
        <?= round($i * $seg, 2) ?>% { opacity: 0; visibility: hidden; }
        <?= round($show_start, 2) ?>% { opacity: 1; visibility: visible; }
        <?= round($show_end, 2) ?>% { opacity: 1; visibility: visible; }
        <?= round($hide_at, 2) ?>% { opacity: 0; visibility: hidden; }
        100% { opacity: 0; visibility: hidden; }
    }
    .sp-slide:nth-child(<?= $i + 1 ?>) { animation: spFade<?= $i ?> <?= $s_duration ?>s infinite both; }
    <?php endforeach; ?>
    <?php foreach ($sponsors as $i => $s): ?>
    #sp-<?= $i ?>:checked ~ .sp-slider .sp-slide:nth-child(<?= $i + 1 ?>) {
        opacity: 1 !important;
        animation: none !important;
        visibility: visible !important;
    }
    #sp-<?= $i ?>:checked ~ .sp-dots .sp-dot:nth-child(<?= $i + 1 ?>) {
        background: #fff;
        border-color: #fff;
    }
    <?php endforeach; ?>
    </style>
    <div class="sp-section">
    <h2 class="sp-heading"><?= htmlspecialchars(t('pub.sponsors')) ?></h2>
    <div class="sp-wrap">
        <?php foreach ($sponsors as $i => $s): ?>
        <input type="radio" name="sp" id="sp-<?= $i ?>" class="sp-radio">
        <?php endforeach; ?>
        <div class="sp-slider">
            <?php foreach ($sponsors as $s): ?>
            <div class="sp-slide">
                <?php if ($s['image_path']): ?>
                    <img src="/<?= htmlspecialchars($s['image_path']) ?>" alt="" class="sp-img">
                <?php endif; ?>
                <div class="sp-overlay">
                    <?php if ($s['text']): ?>
                        <div class="sp-text"><?= htmlspecialchars($s['text']) ?></div>
                    <?php endif; ?>
                    <?php if ($s['link']): ?>
                        <a href="<?= htmlspecialchars($s['link']) ?>" target="_blank" rel="noopener" class="sp-btn"><?= htmlspecialchars(t('pub.visit')) ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="sp-dots">
            <?php foreach ($sponsors as $i => $s): ?>
            <label for="sp-<?= $i ?>" class="sp-dot"></label>
            <?php endforeach; ?>
        </div>
    </div>
    </div>
    <?php endif; ?>

    <?php
    $status_open_msg = getCustomText($conn, 'status_open_message');
    $status_closed_msg = getCustomText($conn, 'status_closed_message');
    if ($has_winners) {
        $status_class = 'status-finished';
        $status_icon = '&#10007;';
        $status_label = t('pub.status_finished');
        $status_desc = t('pub.status_finished_desc');
    } elseif ($lottery_status === 'closed') {
        $status_class = 'status-closed';
        $status_icon = '&#128274;';
        $status_label = t('pub.status_closed');
        $status_desc = $status_closed_msg ?: t('pub.status_closed');
    } else {
        $status_class = 'status-open';
        $status_icon = '&#9989;';
        $status_label = t('pub.status_open');
        $status_desc = $status_open_msg ?: t('pub.status_open');
    }
    ?>
    <div class="status-card <?= $status_class ?>">
        <div class="container">
            <div class="status-icon-wrap">
                <span class="status-card-icon"><?= $status_icon ?></span>
            </div>
            <div class="status-card-body">
                <span class="status-card-label"><?= $status_label ?></span>
                <span class="status-card-desc"><?= $status_desc ?></span>
            </div>
        </div>
    </div>

    <?php if (!empty($prizes)): ?>
    <section class="prizes-section" style="border-radius:12px;margin-bottom:25px;">
        <div class="container">
            <h2 class="prizes-title"><?= htmlspecialchars(t('pub.prizes_title')) ?></h2>
            <div class="prizes-grid">
                <?php foreach ($prizes as $i => $prize): ?>
                <?php $pos = $i + 1; ?>
                <div class="prize-card <?= isset($winners_by_prize[$prize['name']]) ? 'won' : '' ?>">
                    <div class="prize-card-header">
                        <span class="prize-card-badge"><?= htmlspecialchars(t('pub.prize_badge', ['pos' => $pos])) ?></span>
                    </div>
                    <div class="prize-card-body">
                        <h3 class="prize-card-name"><?= htmlspecialchars($prize['name']) ?></h3>
                        <?php if (isset($winners_by_prize[$prize['name']])): ?>
                        <div class="prize-card-winner">
                            <span class="winner-label"><?= htmlspecialchars(t('pub.winner')) ?></span>
                            <span class="winner-name"><?= htmlspecialchars($winners_by_prize[$prize['name']]['participant_name']) ?></span>
                            <span class="winner-number"><?= htmlspecialchars($winners_by_prize[$prize['name']]['winning_number']) ?></span>
                        </div>
                        <?php else: ?>
                        <div class="prize-card-pending"><?= htmlspecialchars(t('pub.pending_winner')) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($header_info): ?>
    <section class="custom-text section" style="border-radius:12px;">
        <div class="container">
            <?= $header_info ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($news)): ?>
    <section class="news-section section" style="border-radius:12px;">
        <div class="container">
            <h2 class="section-title"><?= htmlspecialchars(t('pub.staff_notice')) ?></h2>
            <div class="news-list">
                <?php foreach ($news as $item): ?>
                <article class="news-item">
                    <h3 class="news-title"><?= htmlspecialchars($item['title']) ?></h3>
                    <div class="news-content"><?= $item['content'] ?></div>
                    <time class="news-date" datetime="<?= $item['created_at'] ?>"><?= date('Y-m-d', strtotime($item['created_at'])) ?></time>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="numbers-section section" id="number-grid" style="border-radius:12px;border:none;">
        <div class="container">
            <h2 class="section-title"><?= htmlspecialchars(t('pub.grid_title')) ?></h2>
            <p class="section-desc"><span class="taken-legend"><?= htmlspecialchars(t('pub.taken')) ?></span> <span class="free-legend"><?= htmlspecialchars(t('pub.free')) ?></span></p>

            <div class="number-tabs">
                <span class="tabs-hint"><?= htmlspecialchars(t('pub.jump')) ?></span>
                <?php foreach ($ranges as $b => $block): ?>
                <a href="#block-<?= $b ?>" class="tab-link"><?= htmlspecialchars($block['start']) ?>-<?= htmlspecialchars($block['end']) ?></a>
                <?php endforeach; ?>
            </div>

            <?php foreach ($ranges as $block_index => $block):
                $numbers = $block['numbers'];
                $taken = 0;
                foreach ($numbers as $n) { if ($n) $taken++; }
                $available = count($numbers) - $taken;
            ?>
            <div class="number-block" id="block-<?= $block_index ?>">
                <div class="block-header">
                    <span class="block-range"><?= htmlspecialchars($block['start']) ?> - <?= htmlspecialchars($block['end']) ?></span>
                    <span class="block-stats"><?= htmlspecialchars(t('pub.block_stats', ['taken' => $taken, 'available' => $available])) ?></span>
                    <a href="#number-grid" class="block-grid-link"><?= htmlspecialchars(t('pub.grid_up')) ?></a>
                </div>
                <div class="number-grid">
                    <?php foreach ($numbers as $num => $name): ?>
                    <div class="number-cell <?= $name ? 'taken' : 'free' ?>">
                        <?php if ($name): ?>
                        <span class="number-link"><?= $num ?><br><span class="participant-name" title="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></span></span>
                        <?php else: ?>
                        <span class="number-link"><?= $num ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($footer_info): ?>
    <section class="custom-text section" style="border-radius:12px;">
        <div class="container">
            <?= $footer_info ?>
        </div>
    </section>
    <?php endif; ?>

<?php endif; ?>

</div>

<div class="modal-overlay" id="name-modal" onclick="closeNameModal(event)">
    <div class="modal-box">
        <div class="modal-header">
            <h2><?= htmlspecialchars(t('pub.participant')) ?></h2>
        </div>
        <div class="modal-body" id="modal-body-content">
            <p id="modal-participant-name"></p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="closeNameModal()"><?= htmlspecialchars(t('common.close')) ?></button>
        </div>
    </div>
</div>

<script>
function closeNameModal(e) {
    if (e && e.target !== e.currentTarget) return;
    document.getElementById('name-modal').style.display = 'none';
}
document.querySelectorAll('.number-cell.taken').forEach(function(cell) {
    cell.addEventListener('click', function() {
        var name = this.querySelector('.participant-name');
        if (!name) return;
        document.getElementById('modal-participant-name').textContent = name.textContent;
        document.getElementById('name-modal').style.display = 'flex';
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
