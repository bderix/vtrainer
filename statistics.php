<?php
/**
 * Statistics page
 *
 * PHP version 8.0
 */

// Include header
require_once 'config.php';
require_once 'Helper.php';
require_once 'VocabularyDatabase.php';

// Get database connection
$db = getDbConnection();
require_once 'auth_integration.php';

$vocabDB = new VocabularyDatabase($db);

// Get list ID from GET parameter
$listId = isset($_GET['list_id']) ? intval($_GET['list_id']) : 0;

// Get all vocabulary lists
$lists = $vocabDB->getAllLists();

// Get selected list name (for display)
$selectedListName = "Alle Listen";
if ($listId > 0) {
	foreach ($lists as $list) {
		if ($list['id'] == $listId) {
			$selectedListName = $list['name'];
			if ($list['id'] == 1) {
				$selectedListName .= " (Standard)";
			}
			break;
		}
	}
}

// Get general statistics
$stats = [
	'total_vocabulary' => $db->query('SELECT COUNT(*) FROM vocabulary' .
		($listId > 0 ? ' WHERE list_id = ' . $listId : ''))->fetchColumn(),

	'total_attempts' => $db->query('SELECT COUNT(*) FROM quiz_attempts qa' .
		($listId > 0 ? ' JOIN vocabulary v ON qa.vocabulary_id = v.id WHERE v.list_id = ' . $listId : ''))->fetchColumn(),

	'total_correct' => $db->query('SELECT SUM(qa.is_correct) FROM quiz_attempts qa' .
		($listId > 0 ? ' JOIN vocabulary v ON qa.vocabulary_id = v.id WHERE v.list_id = ' . $listId : ''))->fetchColumn() ?: 0,

	'source_to_target_attempts' => $db->query("SELECT COUNT(*) FROM quiz_attempts qa 
        " . ($listId > 0 ? "JOIN vocabulary v ON qa.vocabulary_id = v.id " : "") . "
        WHERE qa.direction = 'source_to_target'" .
		($listId > 0 ? " AND v.list_id = " . $listId : ""))->fetchColumn(),

	'target_to_source_attempts' => $db->query("SELECT COUNT(*) FROM quiz_attempts qa 
        " . ($listId > 0 ? "JOIN vocabulary v ON qa.vocabulary_id = v.id " : "") . "
        WHERE qa.direction = 'target_to_source'" .
		($listId > 0 ? " AND v.list_id = " . $listId : ""))->fetchColumn(),
];

// Calculate success rates
$stats['success_rate'] = ($stats['total_attempts'] > 0)
	? round(($stats['total_correct'] / $stats['total_attempts']) * 100, 1)
	: 0;

$stats['source_to_target_correct'] = $db->query("
    SELECT SUM(qa.is_correct) FROM quiz_attempts qa
    " . ($listId > 0 ? "JOIN vocabulary v ON qa.vocabulary_id = v.id " : "") . "
    WHERE qa.direction = 'source_to_target'
    " . ($listId > 0 ? "AND v.list_id = " . $listId : "")
)->fetchColumn() ?: 0;

$stats['target_to_source_correct'] = $db->query("
    SELECT SUM(qa.is_correct) FROM quiz_attempts qa
    " . ($listId > 0 ? "JOIN vocabulary v ON qa.vocabulary_id = v.id " : "") . "
    WHERE qa.direction = 'target_to_source'
    " . ($listId > 0 ? "AND v.list_id = " . $listId : "")
)->fetchColumn() ?: 0;

$stats['source_to_target_rate'] = ($stats['source_to_target_attempts'] > 0)
	? round(($stats['source_to_target_correct'] / $stats['source_to_target_attempts']) * 100, 1)
	: 0;

$stats['target_to_source_rate'] = ($stats['target_to_source_attempts'] > 0)
	? round(($stats['target_to_source_correct'] / $stats['target_to_source_attempts']) * 100, 1)
	: 0;

// Get statistics by importance
$importanceStats = $db->query("
    SELECT 
        v.importance,
        COUNT(DISTINCT v.id) as vocab_count,
        COUNT(qa.id) as attempt_count,
        SUM(qa.is_correct) as correct_count,
        CASE WHEN COUNT(qa.id) > 0 THEN ROUND(SUM(qa.is_correct) * 100.0 / COUNT(qa.id), 1) ELSE 0 END as success_rate
    FROM vocabulary v
    LEFT JOIN quiz_attempts qa ON v.id = qa.vocabulary_id
    " . ($listId > 0 ? "WHERE v.list_id = " . $listId : "") . "
    GROUP BY v.importance
    ORDER BY v.importance
")->fetchAll(PDO::FETCH_ASSOC);

// Get most difficult vocabulary (lowest success rate, minimum 3 attempts)
$difficultVocab = $db->query("
    SELECT 
        v.id, v.word_source, v.word_target, v.importance,
        COUNT(qa.id) as attempt_count,
        SUM(qa.is_correct) as correct_count,
        ROUND(SUM(qa.is_correct) * 100.0 / COUNT(qa.id), 1) as success_rate
    FROM vocabulary v
    JOIN quiz_attempts qa ON v.id = qa.vocabulary_id
    " . ($listId > 0 ? "WHERE v.list_id = " . $listId : "") . "
    GROUP BY v.id
    HAVING COUNT(qa.id) >= 3
    ORDER BY success_rate ASC, attempt_count DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get most practiced vocabulary
$mostPracticedVocab = $db->query("
    SELECT 
        v.id, v.word_source, v.word_target, v.importance,
        COUNT(qa.id) as attempt_count,
        SUM(qa.is_correct) as correct_count,
        ROUND(SUM(qa.is_correct) * 100.0 / COUNT(qa.id), 1) as success_rate
    FROM vocabulary v
    JOIN quiz_attempts qa ON v.id = qa.vocabulary_id
    " . ($listId > 0 ? "WHERE v.list_id = " . $listId : "") . "
    GROUP BY v.id
    ORDER BY attempt_count DESC, success_rate ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get recently practiced vocabulary
$recentlyPracticedVocab = $db->query("
    SELECT 
        v.id, v.word_source, v.word_target, v.importance,
        COUNT(qa.id) as attempt_count,
        SUM(qa.is_correct) as correct_count,
        ROUND(SUM(qa.is_correct) * 100.0 / COUNT(qa.id), 1) as success_rate,
        MAX(qa.attempted_at) as last_attempt
    FROM vocabulary v
    JOIN quiz_attempts qa ON v.id = qa.vocabulary_id
    " . ($listId > 0 ? "WHERE v.list_id = " . $listId : "") . "
    GROUP BY v.id
    ORDER BY last_attempt DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get quiz attempts over time
$attemptsOverTime = $db->query("
    SELECT 
        date(qa.attempted_at) as quiz_date,
        COUNT(*) as attempt_count,
        SUM(qa.is_correct) as correct_count
    FROM quiz_attempts qa
    " . ($listId > 0 ? "JOIN vocabulary v ON qa.vocabulary_id = v.id " : "") . "
    " . ($listId > 0 ? "WHERE v.list_id = " . $listId : "") . "
    GROUP BY date(qa.attempted_at)
    ORDER BY quiz_date
")->fetchAll(PDO::FETCH_ASSOC);

// Get importance distribution
$importanceDistribution = $db->query("
    SELECT importance, COUNT(*) as count
    FROM vocabulary
    " . ($listId > 0 ? "WHERE list_id = " . $listId : "") . "
    GROUP BY importance
    ORDER BY importance
")->fetchAll(PDO::FETCH_ASSOC);

// Prepare chart data
$chartData = [
	'dates' => [],
	'attempts' => [],
	'correct' => [],
	'rates' => []
];

foreach ($attemptsOverTime as $day) {
	$chartData['dates'][] = date('d.m.Y', strtotime($day['quiz_date']));
	$chartData['attempts'][] = $day['attempt_count'];
	$chartData['correct'][] = $day['correct_count'];
	$chartData['rates'][] = ($day['attempt_count'] > 0)
		? round(($day['correct_count'] / $day['attempt_count']) * 100, 1)
		: 0;
}

// Prepare importance distribution data
$importanceChartData = [
	'labels' => [],
	'counts' => []
];

foreach ($importanceDistribution as $dist) {
	$importanceChartData['labels'][] = "Wichtigkeit " . $dist['importance'];
	$importanceChartData['counts'][] = $dist['count'];
}

include_once 'header.php';
?>

    <!-- Listen-Auswahl -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card card-hover">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-filter"></i> Listenfilter</h5>
					<?php if ($listId > 0): ?>
                        <span class="badge bg-light text-dark">
                    Aktiv: <?= htmlspecialchars($selectedListName) ?>
                </span>
					<?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="get" action="statistics.php" class="row align-items-end">
                        <div class="col-md-9">
                            <label for="list_id" class="form-label">Liste auswählen</label>
                            <select class="form-select" id="list_id" name="list_id">
                                <option value="0" <?= $listId === 0 ? 'selected' : '' ?>>Alle Listen</option>
								<?php foreach ($lists as $list): ?>
                                    <option value="<?= $list['id'] ?>" <?= $listId === $list['id'] ? 'selected' : '' ?>>
										<?= htmlspecialchars($list['name']) ?>
										<?php if ($list['id'] == 1): ?>(Standard)<?php endif; ?>
                                    </option>
								<?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-funnel"></i> Filter anwenden
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card card-hover mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="bi bi-bar-chart"></i> Gesamtstatistik</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Gesamt</h6>
                                    <h2 class="card-title mb-3"><?= $stats['success_rate'] ?>%</h2>
                                    <div class="progress mb-2" style="height: 20px;">
                                        <div class="progress-bar bg-success" role="progressbar"
                                             style="width: <?= $stats['success_rate'] ?>%;"
                                             aria-valuenow="<?= $stats['success_rate'] ?>" aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                    <p class="card-text">
										<?= $stats['total_correct'] ?> richtig von <?= $stats['total_attempts'] ?> Versuchen
                                    </p>
                                </div>
                                <div class="card-footer bg-white">
                                    <small class="text-muted">
                                        Insgesamt <?= $stats['total_vocabulary'] ?> Vokabeln <?= $listId > 0 ? 'in dieser Liste' : 'im System' ?>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Englisch → Deutsch</h6>
                                    <h2 class="card-title mb-3"><?= $stats['source_to_target_rate'] ?>%</h2>
                                    <div class="progress mb-2" style="height: 20px;">
                                        <div class="progress-bar bg-primary" role="progressbar"
                                             style="width: <?= $stats['source_to_target_rate'] ?>%;"
                                             aria-valuenow="<?= $stats['source_to_target_rate'] ?>" aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                    <p class="card-text">
										<?= $stats['source_to_target_correct'] ?> richtig von <?= $stats['source_to_target_attempts'] ?> Versuchen
                                    </p>
                                </div>
                                <div class="card-footer bg-white">
                                    <small class="text-muted">
										<?= round(($stats['source_to_target_attempts'] / max(1, $stats['total_attempts'])) * 100) ?>%
                                        aller Abfragen
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Deutsch → Englisch</h6>
                                    <h2 class="card-title mb-3"><?= $stats['target_to_source_rate'] ?>%</h2>
                                    <div class="progress mb-2" style="height: 20px;">
                                        <div class="progress-bar bg-info" role="progressbar"
                                             style="width: <?= $stats['target_to_source_rate'] ?>%;"
                                             aria-valuenow="<?= $stats['target_to_source_rate'] ?>" aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                    <p class="card-text">
										<?= $stats['target_to_source_correct'] ?> richtig von <?= $stats['target_to_source_attempts'] ?> Versuchen
                                    </p>
                                </div>
                                <div class="card-footer bg-white">
                                    <small class="text-muted">
										<?= round(($stats['target_to_source_attempts'] / max(1, $stats['total_attempts'])) * 100) ?>%
                                        aller Abfragen
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card card-hover mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="card-title mb-0"><i class="bi bi-exclamation-triangle"></i> Schwierigste Vokabeln</h5>
                </div>
                <div class="card-body">
					<?php if (count($difficultVocab) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                <tr>
                                    <th>Vokabel</th>
                                    <th class="text-center">Wichtigkeit</th>
                                    <th class="text-center">Versuche</th>
                                    <th class="text-center">Erfolgsrate</th>
                                    <th class="text-center">Aktionen</th>
                                </tr>
                                </thead>
                                <tbody>
								<?php foreach ($difficultVocab as $vocab): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($vocab['word_source']) ?> → <?= htmlspecialchars($vocab['word_target']) ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= Helper::getImportanceBadgeColor($vocab['importance']) ?>">
                                                <?= $vocab['importance'] ?>
                                            </span>
                                        </td>
                                        <td class="text-center"><?= $vocab['attempt_count'] ?></td>
                                        <td class="text-center">
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar <?= Helper::getProgressBarColor($vocab['success_rate']) ?>"
                                                     role="progressbar" style="width: <?= $vocab['success_rate'] ?>%;"
                                                     aria-valuenow="<?= $vocab['success_rate'] ?>" aria-valuemin="0" aria-valuemax="100">
													<?= $vocab['success_rate'] ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <a href="quiz.php?vocab_id=<?= $vocab['id'] ?>&direction=source_to_target"
                                               class="btn btn-sm btn-outline-primary">Üben</a>
                                        </td>
                                    </tr>
								<?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
<?php else: ?>
    <p class="text-center">Noch nicht genügend Daten vorhanden.</p>
<?php endif; ?>
    </div>
    </div>
    </div>

<div class="col-md-6">
    <div class="card card-hover mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="card-title mb-0"><i class="bi bi-repeat"></i> Am häufigsten geübte Vokabeln</h5>
        </div>
        <div class="card-body">
			<?php if (count($mostPracticedVocab) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                    <tr>
                        <th>Vokabel</th>
                        <th class="text-center">Wichtigkeit</th>
                        <th class="text-center">Versuche</th>
                        <th class="text-center">Erfolgsrate</th>
                        <th class="text-center">Aktionen</th>
                    </tr>
                    </thead>
                    <tbody>
					<?php foreach ($mostPracticedVocab as $vocab): ?>
                    <tr>
                        <td><?= htmlspecialchars($vocab['word_source']) ?> → <?= htmlspecialchars($vocab['word_target']) ?></td>
                        <td class="text-center">
                                            <span class="badge bg-<?= Helper::getImportanceBadgeColor($vocab['importance']) ?>">
                                                <?= $vocab['importance'] ?>
                                            </span>
                        </td>
                        <td class="text-center"><?= $vocab['attempt_count'] ?></td>
                        <td class="text-center">
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar <?= Helper::getProgressBarColor($vocab['success_rate']) ?>"
                                     role="progressbar" style="width: <?= $vocab['success_rate'] ?>%;"
                                     aria-valuenow="<?= $vocab['success_rate'] ?>" aria-valuemin="0" aria-valuemax="100">
									<?= $vocab['success_rate'] ?>%
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <a href="quiz.php?vocab_id=<?= $vocab['id'] ?>&direction=target_to_source"
                               class="btn btn-sm btn-outline-primary">Üben</a>
                        </td>
                    </tr>
					<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
			<?php else: ?>
                <p class="text-center">Noch nicht genügend Daten vorhanden.</p>
			<?php endif; ?>
        </div>
    </div>
</div>
    </div>

<div class="row">
    <div class="col-md-12">
        <div class="card card-hover mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0"><i class="bi bi-graph-up"></i> Erfolgsrate über Zeit</h5>
            </div>
            <div class="card-body">
				<?php if (count($attemptsOverTime) > 0): ?>
                    <canvas id="successRateChart" height="100"></canvas>
				<?php else: ?>
                    <p class="text-center">Noch nicht genügend Daten vorhanden.</p>
				<?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card card-hover mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-pie-chart"></i> Verteilung nach Wichtigkeit</h5>
            </div>
            <div class="card-body">
                <canvas id="importanceChart" height="200"></canvas>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card card-hover mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-table"></i> Statistik nach Wichtigkeit</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th class="text-center">Wichtigkeit</th>
                            <th class="text-center">Vokabeln</th>
                            <th class="text-center">Versuche</th>
                            <th class="text-center">Erfolgsrate</th>
                        </tr>
                        </thead>
                        <tbody>
						<?php foreach ($importanceStats as $stat): ?>
                        <tr>
                            <td class="text-center">
                                        <span class="badge bg-<?= Helper::getImportanceBadgeColor($stat['importance']) ?>">
                                            <?= $stat['importance'] ?>
                                        </span>
                            </td>
                            <td class="text-center"><?= $stat['vocab_count'] ?></td>
                            <td class="text-center"><?= $stat['attempt_count'] ?></td>
                            <td class="text-center">
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar <?= Helper::getProgressBarColor($stat['success_rate']) ?>"
                                         role="progressbar" style="width: <?= $stat['success_rate'] ?>%;"
                                         aria-valuenow="<?= $stat['success_rate'] ?>" aria-valuemin="0" aria-valuemax="100">
										<?= $stat['success_rate'] ?>%
                                    </div>
                                </div>
                            </td>
                        </tr>
						<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
	// Initialize charts when DOM is ready
	document.addEventListener('DOMContentLoaded', function() {
		// Success Rate Over Time Chart
		<?php if (count($attemptsOverTime) > 0): ?>
		var ctx = document.getElementById('successRateChart').getContext('2d');
		var successRateChart = new Chart(ctx, {
			type: 'line',
			data: {
				labels: <?= json_encode($chartData['dates']) ?>,
				datasets: [
					{
						label: 'Erfolgsrate (%)',
						data: <?= json_encode($chartData['rates']) ?>,
						backgroundColor: 'rgba(23, 162, 184, 0.2)',
						borderColor: 'rgba(23, 162, 184, 1)',
						borderWidth: 2,
						yAxisID: 'y-rate',
						tension: 0.2
					},
					{
						label: 'Versuche',
						data: <?= json_encode($chartData['attempts']) ?>,
						backgroundColor: 'rgba(108, 117, 125, 0.2)',
						borderColor: 'rgba(108, 117, 125, 1)',
						borderWidth: 2,
						yAxisID: 'y-count',
						tension: 0.2
					},
					{
						label: 'Richtig',
						data: <?= json_encode($chartData['correct']) ?>,
						backgroundColor: 'rgba(40, 167, 69, 0.2)',
						borderColor: 'rgba(40, 167, 69, 1)',
						borderWidth: 2,
						yAxisID: 'y-count',
						tension: 0.2
					}
				]
			},
			options: {
				responsive: true,
				scales: {
					x: {
						grid: {
							display: false
						}
					},
					'y-rate': {
						type: 'linear',
						display: true,
						position: 'left',
						title: {
							display: true,
							text: 'Erfolgsrate (%)'
						},
						min: 0,
						max: 100,
						ticks: {
							stepSize: 20
						}
					},
					'y-count': {
						type: 'linear',
						display: true,
						position: 'right',
						title: {
							display: true,
							text: 'Anzahl'
						},
						min: 0,
						grid: {
							drawOnChartArea: false
						}
					}
				},
				plugins: {
					legend: {
						position: 'bottom'
					}
				}
			}
		});
		<?php endif; ?>

		// Importance Distribution Chart
		var ctxImportance = document.getElementById('importanceChart').getContext('2d');
		var importanceChart = new Chart(ctxImportance, {
			type: 'pie',
			data: {
				labels: <?= json_encode($importanceChartData['labels']) ?>,
				datasets: [{
					data: <?= json_encode($importanceChartData['counts']) ?>,
					backgroundColor: [
						'rgba(220, 53, 69, 0.7)',   // Wichtigkeit 1
						'rgba(255, 193, 7, 0.7)',   // Wichtigkeit 2
						'rgba(13, 110, 253, 0.7)',  // Wichtigkeit 3
						'rgba(40, 167, 69, 0.7)',   // Wichtigkeit 4
						'rgba(23, 162, 184, 0.7)'   // Wichtigkeit 5
					],
					borderColor: [
						'rgba(220, 53, 69, 1)',
						'rgba(255, 193, 7, 1)',
						'rgba(13, 110, 253, 1)',
						'rgba(40, 167, 69, 1)',
						'rgba(23, 162, 184, 1)'
					],
					borderWidth: 1
				}]
			},
			options: {
				responsive: true,
				plugins: {
					legend: {
						position: 'bottom'
					},
					tooltip: {
						callbacks: {
							label: function(context) {
								const label = context.label || '';
								const value = context.raw || 0;
								const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
								const percentage = Math.round((value / total) * 100);
								return `${label}: ${value} (${percentage}%)`;
							}
						}
					}
				}
			}
		});
	});
</script>

<?php

// Include footer
require_once 'footer.php';
?>