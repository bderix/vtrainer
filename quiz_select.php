<?php
/**
 * Quiz selection page - allows user to set up quiz parameters
 *
 * PHP version 8.0
 */

// Include configuration and database class
require_once 'config.php';
require_once 'VocabularyDatabase.php';

// Get database connection
$db = getDbConnection();
require_once 'auth_integration.php';


xlog($_GET);
xlog($_SESSION);

if (isset($_SESSION['errorMessage'])) {
    echo $_SESSION['errorMessage'];
    unset($_SESSION['errorMessage']);
    exit;
}
// exit;
// Create database handler
$vocabDB = new VocabularyDatabase($db);

// Handle form submissions
$direction = $_GET['direction'] ?? 'source_to_target';
$importance = isset($_GET['importance']) ? array_map('intval', (array)$_GET['importance']) : [1, 2, 3];
$filtered = isset($_GET['filtered']) && $_GET['filtered'] == 1;
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$recentLimit = $vtrequest->getRecentLimit();
$_SESSION['quiz_recent_limit'] = $recentLimit;

// Get list ID from GET, session, or default to 1 (standard list)
if (isset($_GET['list_id'])) {
	$listId = intval($_GET['list_id']);
	// Save to session
	$_SESSION['selected_list_id'] = $listId;
} else if (isset($_SESSION['selected_list_id'])) {
	$listId = $_SESSION['selected_list_id'];
}

// Get all vocabulary lists
// Save quiz parameters in session for self-evaluation redirects
$_SESSION['quiz_importance'] = $importance;
$_SESSION['quiz_search'] = $searchTerm;
$_SESSION['quiz_filtered'] = $filtered;
$_SESSION['quiz_list_id'] = $listId;

unset($_SESSION['quiz_vocab_batch']);

// Reset the session statistics
unset($_SESSION['quiz_session_stats']);
unset($_SESSION['quiz_session_stats_current']);

// if ($currentList['user_id'] == $_SESSION['user_id']) $ownList = true;
// else $ownList = false;

$lists = $vocabDB->getVocabularyListsByUser($_SESSION['user_id']); // alle Listen um die Listen auswaehlen zu koennen
xlog($listId);
xlog($lists);

if (empty($listId)) {
	$currentList = $lists[0];
}
else {
	$currentList = array_filter($lists, function ($item) use ($listId) {
		return $item['id'] === $listId;
	});
	$currentList = array_shift($currentList);
}
if (empty($currentList)) {
    $currentList = $lists[0];
	$_SESSION['selected_list_id'] = $lists[0]['id'];
}
xlog($currentList);


$sourceLanguage = $currentList['source_language'] ?? 'Quellwort';
$targetLanguage = $currentList['target_language'] ?? 'Zielwort';

// Get quiz statistics for this setup
$quizStats = $vocabDB->getQuizStats($direction, $importance, $searchTerm, $listId, $recentLimit);
xlog($quizStats);

// Include header
require_once 'header.php';
?>

    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card card-hover">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-gear"></i> Abfrageeinstellungen für Liste: <?= htmlspecialchars($currentList['name']) ?>
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Quiz setup form -->
                    <form method="get" action="quiz.php">
                        <div class="mb-4">
                            <label class="form-label">Liste auswählen</label>
                            <select class="form-select" id="quizListSelect" onchange="changeList(this.value)">
								<?php foreach ($lists as $list): ?>
                                    <option value="<?= $list['id'] ?>" <?= $listId === $list['id'] ? 'selected' : '' ?>
                                            data-source="<?= htmlspecialchars($list['source_language'] ?? 'Quellwort') ?>"
                                            data-target="<?= htmlspecialchars($list['target_language'] ?? 'Zielwort') ?>">
										<?= htmlspecialchars($list['name']) ?>
										<?php if ($list['id'] == 1): ?>(Standard)<?php endif; ?>
                                    </option>
								<?php endforeach; ?>
                            </select>
                            <input type="hidden" name="list_id" value="<?= $listId ?>">
                        </div>

                        <div class="mb-4">
                            <label for="recent_limit" class="form-label">Nur neueste Vokabeln abfragen</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="recent_limit" name="recent_limit"
                                       value="<?= $recentLimit ?>" min="0" max="50" placeholder="Anzahl eingeben">
                                <span class="input-group-text">Vokabeln</span>
                            </div>
                            <div class="form-text">Leer lassen oder 0 eingeben, um alle Vokabeln abzufragen. Maximal 50 möglich.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Abfragerichtung</label>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="form-check card p-3" id="directionCard1">
                                        <input class="form-check-input" type="radio" name="direction" id="direction1"
                                               value="source_to_target" <?= $direction === 'source_to_target' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="direction1" id="directionLabel1">
                                            <i class="bi bi-arrow-right"></i> <span id="sourceLabel"><?= htmlspecialchars($sourceLanguage) ?></span> → <span id="targetLabel"><?= htmlspecialchars($targetLanguage) ?></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check card p-3" id="directionCard2">
                                        <input class="form-check-input" type="radio" name="direction" id="direction2"
                                               value="target_to_source" <?= $direction === 'target_to_source' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="direction2" id="directionLabel2">
                                            <i class="bi bi-arrow-left"></i> <span id="targetLabel2"><?= htmlspecialchars($targetLanguage) ?></span> → <span id="sourceLabel2"><?= htmlspecialchars($sourceLanguage) ?></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Wichtigkeitsstufen</label>
                            <div class="row g-2">
								<?php for ($i = 1; $i <= 3; $i++): ?>
                                    <div class="col">
                                        <div class="form-check card p-3 text-center" id="importanceCard<?= $i ?>">
                                            <div class="d-flex justify-content-center">
                                                <input class="form-check-input" type="checkbox" name="importance[]" id="importance<?= $i ?>"
                                                       value="<?= $i ?>" <?= in_array($i, $importance) ? 'checked' : '' ?>>
                                            </div>
                                            <label class="form-check-label d-block mt-2" for="importance<?= $i ?>">
                                                <span class="badge bg-<?= Helper::getImportanceBadgeColor($i) ?>"><?= $i ?></span>
                                            </label>
                                        </div>
                                    </div>
								<?php endfor; ?>
                            </div>
                        </div>

						<?php if (!empty($searchTerm)): ?>
                            <div class="mb-4">
                                <div class="alert alert-info">
                                    <i class="bi bi-filter"></i>
                                    Filter aktiv: <strong><?= htmlspecialchars($searchTerm) ?></strong>
                                    <a href="quiz_select.php" class="float-end text-dark">
                                        <i class="bi bi-x-circle"></i>
                                    </a>
                                </div>
                                <input type="hidden" name="search" value="<?= htmlspecialchars($searchTerm) ?>">
                            </div>
						<?php endif; ?>

						<?php if ($filtered): ?>
                            <input type="hidden" name="filtered" value="1">
						<?php endif; ?>

						<?php include "quiz_statistics.php"; ?>

                        <div class="text-center">
                            <button type="submit" class="btn btn-success btn-lg px-4">
                                <i class="bi bi-play-circle"></i> Abfrage starten
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
		// Function to change selected list
		function changeList(listId) {
			window.location.href = 'quiz_select.php?list_id=' + listId;
		}

		// JavaScript-Code for die onClick-Funktionalitaet bei Abfragerichtung und Wichtigkeitsstufen
		document.addEventListener('DOMContentLoaded', function() {
			const listSelect = document.getElementById('quizListSelect');
			if (listSelect) {
				listSelect.addEventListener('change', function() {
					changeList(this.value);
				});
			}

			// Fuer Abfragerichtung
			const directionCard1 = document.getElementById('directionCard1');
			const directionCard2 = document.getElementById('directionCard2');
			const direction1 = document.getElementById('direction1');
			const direction2 = document.getElementById('direction2');

			// Initialen Zustand setzen
			if (direction1 && direction1.checked) {
				directionCard1.classList.add('bg-light');
			}
			if (direction2 && direction2.checked) {
				directionCard2.classList.add('bg-light');
			}

			// Event-Listener directionCard1
			if (directionCard1 && direction1) {
				directionCard1.addEventListener('click', function(e) {
					if (e.target !== direction1) {
						direction1.checked = true;
					}
					// Visuelles Feedback
					directionCard1.classList.add('bg-light');
					directionCard2.classList.remove('bg-light');
					updateQuizStats();
				});
			}

			// Event-Listener directionCard2
			if (directionCard2 && direction2) {
				directionCard2.addEventListener('click', function(e) {
					if (e.target !== direction2) {
						direction2.checked = true;
					}
					// Visuelles Feedback
					directionCard2.classList.add('bg-light');
					directionCard1.classList.remove('bg-light');
					updateQuizStats();
				});
			}

			// Für Wichtigkeitsstufen
			for (let i = 1; i <= 3; i++) {
				const importanceCard = document.getElementById('importanceCard' + i);
				const importanceCheck = document.getElementById('importance' + i);

				if (importanceCard && importanceCheck) {
					// Initialen Zustand setzen
					if (importanceCheck.checked) {
						importanceCard.classList.add('bg-light');
					}

					// Event-Listener
					importanceCard.addEventListener('click', function(e) {
						if (e.target !== importanceCheck) {
							importanceCheck.checked = !importanceCheck.checked;
						}
						// Visuelles Feedback
						if (importanceCheck.checked) {
							importanceCard.classList.add('bg-light');
						} else {
							importanceCard.classList.remove('bg-light');
						}
						updateQuizStats();
					});

					// Falls direkt auf Checkbox geklickt wird
					importanceCheck.addEventListener('change', function() {
						if (this.checked) {
							importanceCard.classList.add('bg-light');
							updateQuizStats();
						} else {
							importanceCard.classList.remove('bg-light');
							updateQuizStats();
						}
					});
				}
			}

			// Für recent_limit Input
			const recentLimitInput = document.getElementById('recent_limit');
			if (recentLimitInput) {
				recentLimitInput.addEventListener('change', updateQuizStats);
				recentLimitInput.addEventListener('input', updateQuizStats);
			}
		});

		// AJAX-Funktionalität für die dynamische Aktualisierung der Statistiken
		function updateQuizStats() {
			// Sammle alle Filterwerte
			const direction = document.querySelector('input[name="direction"]:checked').value;
			const listId = document.getElementById('quizListSelect').value;

			// Sammle alle ausgewählten Wichtigkeitsstufen
			const importanceValues = [];
			for (let i = 1; i <= 5; i++) {
				const checkbox = document.getElementById('importance' + i);
				if (checkbox && checkbox.checked) {
					importanceValues.push(i);
				}
			}

			// Erstelle den Query-String
			let queryParams = 'direction=' + direction + '&list_id=' + listId;

			// Füge Wichtigkeitsstufen hinzu
			importanceValues.forEach(imp => {
				queryParams += '&importance[]=' + imp;
			});

			// Füge Suchbegriff hinzu, falls vorhanden
			const searchInput = document.querySelector('input[name="search"]');
			if (searchInput && searchInput.value) {
				queryParams += '&search=' + encodeURIComponent(searchInput.value);
			}

			const recentLimitInput = document.getElementById('recent_limit');
			const recentLimit = recentLimitInput ? recentLimitInput.value : 0;
			// if (recentLimit > 0) {
			// 	queryParams += '&recent_limit=' + recentLimit;
			// }

			// AJAX-Request senden
			fetch('get_quiz_stats_ajax.php?' + queryParams, {
				method: 'GET',
				headers: {
					'X-Requested-With': 'XMLHttpRequest'
				}
			})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						// Aktualisiere die Statistiken im UI
						document.getElementById('statsTotal').textContent = data.stats.total_count;
						document.getElementById('statsAttempts').textContent = data.stats.attempt_count;
						document.getElementById('statsCorrect').textContent = data.stats.correct_count;
						document.getElementById('statsSuccessRate').textContent = data.stats.success_rate + '%';
					} else {
						console.error('Fehler beim Laden der Statistiken:', data.message);
					}
				})
				.catch(error => {
					console.error('AJAX-Fehler:', error);
				});
		}
    </script>

<?php

// Include footer
require_once 'footer.php';
?>