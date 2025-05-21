<?php
/**
 * Edit vocabulary page
 *
 * PHP version 8.0
 */


exit;








// Include configuration and database class
require_once 'config.php';
require_once 'VocabularyDatabase.php';

// Get database connection
$db = getDbConnection();
require_once 'auth_integration.php';


// Initialize variables
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$wordSource = '';
$wordTarget = '';
$exampleSentence = '';
$importance = 3;
$listId = 1; // Default list
$successMessage = '';
$errorMessage = '';

$vocabDB = new VocabularyDatabase($db);

// Get all vocabulary lists
$lists = $vocabDB->getAllLists();

// Check if ID is valid
if ($id <= 0) {
	$errorMessage = 'Ungültige Vokabel-ID.';
	header('Location: list.php');
	exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// Get form data
	$wordSource = trim($_POST['word_source'] ?? '');
	$wordTarget = trim($_POST['word_target'] ?? '');
	$exampleSentence = trim($_POST['example_sentence'] ?? '');
	$importance = intval($_POST['importance'] ?? 3);
	$listId = intval($_POST['list_id'] ?? 1);
	$listId = $app->getListId();

	// Validate input
	$errors = [];

	if (empty($wordSource)) {
		$errors[] = 'Bitte gib das Englisch ein.';
	}

	if (empty($wordTarget)) {
		$errors[] = 'Bitte gib das Deutsch ein.';
	}

	if ($importance < 1 || $importance > 5) {
		$errors[] = 'Die Wichtigkeit muss zwischen 1 und 5 liegen.';
	}

	// Check if vocabulary with same words already exists (excluding current one)
	if ($vocabDB->vocabExistsExcept($wordSource, $wordTarget, $id)) {
		$errors[] = 'Eine Vokabel mit diesen Wörtern existiert bereits.';
	}

	// Check if list exists
	$list = $vocabDB->getListById($listId);
	if (!$list) {
		$errors[] = 'Die ausgewählte Liste existiert nicht.';
		$listId = 1; // Fallback to default list
	}

	// If no errors, update database
	if (empty($errors)) {
		if ($vocabDB->updateVocabulary($id, $wordSource, $wordTarget, $exampleSentence, $importance, $listId)) {
			$successMessage = 'Vokabel erfolgreich aktualisiert!';
		} else {
			$errorMessage = 'Fehler beim Aktualisieren der Vokabel.';
		}
	} else {
		$errorMessage = implode('<br>', $errors);
	}
} else {
	// Load vocabulary data from database
	$vocab = $vocabDB->getVocabularyById($id);

	if (!$vocab) {
		$errorMessage = 'Vokabel nicht gefunden.';
		header('Location: list.php');
		exit;
	}

	// Populate form fields
	$wordSource = $vocab['word_source'];
	$wordTarget = $vocab['word_target'];
	$exampleSentence = $vocab['example_sentence'];
	$importance = $vocab['importance'];
	$listId = $vocab['list_id'] ?? 1;
}

// Get quiz statistics for this vocabulary
$stats = $vocabDB->getVocabularyQuizStats($id);

// Include header
require_once 'header.php';
?>
    ?>

    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card card-hover">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="bi bi-pencil"></i> Vokabel bearbeiten</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="edit.php?id=<?= $id ?>">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="word_source" class="form-label">Englisch <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="word_source" name="word_source"
                                       value="<?= htmlspecialchars($wordSource) ?>" required>
                                <div class="form-text">Das Wort in der Ausgangssprache</div>
                            </div>
                            <div class="col-md-6">
                                <label for="word_target" class="form-label">Deutsch <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="word_target" name="word_target"
                                       value="<?= htmlspecialchars($wordTarget) ?>" required>
                                <div class="form-text">Das Wort in der Zielsprache</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="example_sentence" class="form-label">Beispielsatz</label>
                            <textarea class="form-control" id="example_sentence" name="example_sentence"
                                      rows="3"><?= htmlspecialchars($exampleSentence) ?></textarea>
                            <div class="form-text">Ein Beispielsatz, der den Kontext oder die Verwendung zeigt</div>
                        </div>

                        <div class="mb-3">
                            <label for="list_id" class="form-label">Liste <span class="text-danger">*</span></label>
                            <select class="form-select" id="list_id" name="list_id" required>
								<?php foreach ($lists as $list): ?>
                                    <option value="<?= $list['id'] ?>" <?= $list['id'] == $listId ? 'selected' : '' ?>>
										<?= htmlspecialchars($list['name']) ?>
										<?php if ($list['id'] == 1): ?>(Standard)<?php endif; ?>
                                    </option>
								<?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="importance" class="form-label">Wichtigkeit <span class="text-danger">*</span></label>
                            <div class="d-flex">
                                <input type="range" class="form-range flex-grow-1 me-2" id="importance" name="importance"
                                       min="1" max="5" step="1" value="<?= $importance ?>">
                                <span id="importance_display" class="badge bg-primary"><?= $importance ?></span>
                            </div>
                            <div class="d-flex justify-content-between text-muted small">
                                <span>Niedrig (1)</span>
                                <span>Mittel (3)</span>
                                <span>Hoch (5)</span>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Änderungen speichern
                            </button>
                            <a href="list.php" class="btn btn-secondary ms-2">
                                <i class="bi bi-x-circle"></i> Abbrechen
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics for this vocabulary -->
<?php if ($stats['attempt_count'] > 0): ?>
    <div class="row mt-4">
        <div class="col-md-8 offset-md-2">
            <div class="card card-hover">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0"><i class="bi bi-bar-chart"></i> Lernstatistik für diese Vokabel</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Gesamt</h6>
                                    <h2 class="card-title mb-3"><?= $stats['total_success_rate'] ?>%</h2>
                                    <div class="progress mb-2" style="height: 20px;">
                                        <div class="progress-bar <?= Helper::getProgressBarColor($stats['total_success_rate']) ?>" role="progressbar"
                                             style="width: <?= $stats['total_success_rate'] ?>%;"
                                             aria-valuenow="<?= $stats['total_success_rate'] ?>" aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                    <p class="card-text">
										<?= $stats['correct_count'] ?> richtig von <?= $stats['attempt_count'] ?> Versuchen
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Englisch → Deutsch</h6>
                                    <h2 class="card-title mb-3"><?= $stats['source_to_target_rate'] ?>%</h2>
                                    <div class="progress mb-2" style="height: 20px;">
                                        <div class="progress-bar <?= Helper::getProgressBarColor($stats['source_to_target_rate']) ?>" role="progressbar"
                                             style="width: <?= $stats['source_to_target_rate'] ?>%;"
                                             aria-valuenow="<?= $stats['source_to_target_rate'] ?>" aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                    <p class="card-text">
										<?= $stats['source_to_target_correct'] ?> richtig von <?= $stats['source_to_target_count'] ?> Versuchen
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Deutsch → Englisch</h6>
                                    <h2 class="card-title mb-3"><?= $stats['target_to_source_rate'] ?>%</h2>
                                    <div class="progress mb-2" style="height: 20px;">
                                        <div class="progress-bar <?= Helper::getProgressBarColor($stats['target_to_source_rate']) ?>" role="progressbar"
                                             style="width: <?= $stats['target_to_source_rate'] ?>%;"
                                             aria-valuenow="<?= $stats['target_to_source_rate'] ?>" aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                    <p class="card-text">
										<?= $stats['target_to_source_correct'] ?> richtig von <?= $stats['target_to_source_count'] ?> Versuchen
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-3">
                        <div class="btn-group">
                            <a href="quiz.php?vocab_id=<?= $id ?>&mode=quiz&direction=source_to_target" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-right"></i> Englisch → Deutsch üben
                            </a>
                            <a href="quiz.php?vocab_id=<?= $id ?>&mode=quiz&direction=target_to_source" class="btn btn-outline-success">
                                <i class="bi bi-arrow-left"></i> Deutsch → Englisch üben
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

    <script>
		// Update importance display when slider changes
		document.getElementById('importance').addEventListener('input', function() {
			document.getElementById('importance_display').textContent = this.value;

			// Update badge color based on importance
			const badge = document.getElementById('importance_display');
			const value = parseInt(this.value);

			// Remove all existing classes
			badge.className = 'badge';

			// Add appropriate class based on value
			if (value === 1) {
				badge.classList.add('bg-danger');
			} else if (value === 2) {
				badge.classList.add('bg-warning');
			} else if (value === 3) {
				badge.classList.add('bg-primary');
			} else if (value === 4) {
				badge.classList.add('bg-success');
			} else if (value === 5) {
				badge.classList.add('bg-info');
			}
		});

		// Trigger the event on page load to set initial color
		document.addEventListener('DOMContentLoaded', function() {
			const event = new Event('input');
			document.getElementById('importance').dispatchEvent(event);
		});
    </script>

<?php

// Include footer
require_once 'footer.php';
?>