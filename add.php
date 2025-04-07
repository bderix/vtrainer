<?php
/**
 * Add new vocabulary page
 *
 * PHP version 8.0
 */

// Include configuration and database class
require_once 'config.php';
require_once 'VocabularyDatabase.php';

// Initialize variables
$wordSource = '';
$wordTarget = '';
$exampleSentence = '';
$importance = 2;
$listId = $_SESSION['listId'] ?? 1; // Default list
$successMessage = '';
$errorMessage = '';
// Get database connection
$db = getDbConnection();
$vocabDB = new VocabularyDatabase($db);

// Get all vocabulary lists
$lists = $vocabDB->getAllLists();

// Get current list details
$currentList = $vocabDB->getListById($listId);
$sourceLanguage = $currentList['source_language'] ?? 'Quellwort';
$targetLanguage = $currentList['target_language'] ?? 'Zielwort';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// Get form data
	$wordSource = trim($_POST['word_source'] ?? '');
	$wordTarget = trim($_POST['word_target'] ?? '');
	$exampleSentence = trim($_POST['example_sentence'] ?? '');
	$importance = intval($_POST['importance'] ?? 3);
	$listId = intval($_POST['list_id'] ?? $listId);

	// Validate input
	$errors = [];

	if (empty($wordSource)) {
		$errors[] = 'Bitte gib das ' . $sourceLanguage . ' ein.';
	}

	if (empty($wordTarget)) {
		$errors[] = 'Bitte gib das ' . $targetLanguage . ' ein.';
	}

	if ($importance < 1 || $importance > 5) {
		$errors[] = 'Die Wichtigkeit muss zwischen 1 und 5 liegen.';
	}

	// Check if vocabulary already exists
	$stmt = $db->prepare('SELECT COUNT(*) FROM vocabulary WHERE word_source = ? AND word_target = ?');
	$stmt->execute([$wordSource, $wordTarget]);
	if ($stmt->fetchColumn() > 0) {
		$errors[] = 'Diese Vokabel existiert bereits.';
	}

	// Check if list exists
	$list = $vocabDB->getListById($listId);
	if (!$list) {
		$errors[] = 'Die ausgewählte Liste existiert nicht.';

		$listId = 1; // Fallback to default list
	}
	$_SESSION['listId'] = $listId;
	// If no errors, insert into database
	if (empty($errors)) {
		$newId = $vocabDB->addVocabulary($wordSource, $wordTarget, $exampleSentence, $importance, $listId);

		if ($newId) {
			$successMessage = 'Vokabel erfolgreich hinzugefügt!';

			// Clear form fields after successful submission
			$wordSource = '';
			$wordTarget = '';
			$exampleSentence = '';
			$importance = 3;
			// Keep the selected list
		} else {
			$errorMessage = 'Fehler beim Hinzufügen der Vokabel.';
		}
	} else {
		$errorMessage = implode('<br>', $errors);
	}
}

// Include header
require_once 'header.php';
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card card-hover">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-plus-circle"></i> Neue Vokabel hinzufügen</h5>
            </div>
            <div class="card-body">
                <form method="post" action="add.php">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="word_source" class="form-label" id="source_language_label"><?= htmlspecialchars($sourceLanguage) ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="word_source" name="word_source"
                                   value="<?= htmlspecialchars($wordSource) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="word_target" class="form-label" id="target_language_label"><?= htmlspecialchars($targetLanguage) ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="word_target" name="word_target"
                                   value="<?= htmlspecialchars($wordTarget) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="example_sentence" class="form-label">Notiz</label>
                        <textarea class="form-control" id="example_sentence" name="example_sentence"
                                  rows="3"><?= htmlspecialchars($exampleSentence) ?></textarea>
                        <div class="form-text">Eine Notiz, wie z.B. ein Beispielsatz, [Synonyme] etc.</div>
                    </div>

                    <div class="mb-3">
                        <label for="list_id" class="form-label">Liste <span class="text-danger">*</span></label>
                        <select class="form-select" id="list_id" name="list_id" required>
							<?php foreach ($lists as $list): ?>
                                <option value="<?= $list['id'] ?>" <?= $list['id'] == $listId ? 'selected' : '' ?>
                                        data-source="<?= htmlspecialchars($list['source_language'] ?? 'Quellwort') ?>"
                                        data-target="<?= htmlspecialchars($list['target_language'] ?? 'Zielwort') ?>">
									<?= htmlspecialchars($list['name']) ?>
									<?php if ($list['id'] == 1): ?>(Standard)<?php endif; ?>
                                </option>
							<?php endforeach; ?>
                        </select>
                        <div class="form-text">Die Liste, zu der diese Vokabel gehören soll</div>
                    </div>

                    <div class="mb-3 d-flex align-items-center gap-3">
                        <label class="form-label mb-0">Wichtigkeit <span class="text-danger">*</span></label>

                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="importance" id="importance1" value="1" <?= $importance == 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="importance1"><span class="badge bg-danger">1</span> Niedrig</label>
                        </div>

                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="importance" id="importance2" value="2" <?= $importance == 2 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="importance2"><span class="badge bg-primary">2</span> Mittel</label>
                        </div>

                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="importance" id="importance3" value="3" <?= $importance == 3 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="importance3"><span class="badge bg-success">3</span> Hoch</label>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Speichern
                        </button>
                        <a href="index.php" class="btn btn-secondary ms-2">
                            <i class="bi bi-x-circle"></i> Abbrechen
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Include recently added vocabulary section in a row with proper columns -->
<div class="row mt-4">
    <div class="col-md-8 offset-md-2">
        <!-- Include recently added vocabulary section -->
		<?php include 'recently_added.php'; ?>
    </div>
</div>

<!-- Quick add multiple words section -->
<div class="row mt-4">
    <div class="col-md-8 offset-md-2">
        <div class="card card-hover">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-list-check"></i> Mehrere Vokabeln hinzufügen
                </h5>
            </div>

            <div class="card-body">
                <p class="card-text">
                    Füge mehrere Vokabeln auf einmal hinzu. Jede Zeile sollte folgendes Format haben:
                    <code><span id="bulk_source_language"><?= htmlspecialchars($sourceLanguage) ?></span>;<span id="bulk_target_language"><?= htmlspecialchars($targetLanguage) ?></span>;Notiz;Wichtigkeit</code>
                </p>
                <form method="post" action="add_multiple.php">
                    <div class="mb-3">
                        <label for="multiple_vocab" class="form-label">Vokabelliste</label>
                        <textarea class="form-control font-monospace" id="multiple_vocab" name="multiple_vocab"
                                  rows="5" placeholder="house;Haus;Das ist ein Haus.;3"></textarea>
                    </div>

                    <!-- Neues Dropdown zur Listenauswahl -->
                    <div class="mb-3">
                        <label for="list_id_multiple" class="form-label">Liste auswählen</label>
                        <select class="form-select" id="list_id_multiple" name="list_id_multiple">
							<?php foreach ($lists as $list): ?>
                                <option value="<?= $list['id'] ?>" <?= $list['id'] == $listId ? 'selected' : '' ?>
                                        data-source="<?= htmlspecialchars($list['source_language'] ?? 'Quellwort') ?>"
                                        data-target="<?= htmlspecialchars($list['target_language'] ?? 'Zielwort') ?>">
									<?= htmlspecialchars($list['name']) ?>
									<?php if ($list['id'] == 1): ?>(Standard)<?php endif; ?>
                                </option>
							<?php endforeach; ?>
                        </select>
                        <div class="form-text">Die Liste, zu der alle importierten Vokabeln gehören sollen</div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-list-check"></i> Alle hinzufügen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<script>
	document.getElementById('word_source').focus();

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
			badge.classList.add('bg-primary');
		} else if (value === 3) {
			badge.classList.add('bg-success');
		}
	});

	// Trigger the event on page load to set initial color
	document.addEventListener('DOMContentLoaded', function() {
		const event = new Event('input');
		document.getElementById('importance').dispatchEvent(event);

		// Update labels when list selection changes
		document.getElementById('list_id').addEventListener('change', function() {
			const selectedOption = this.options[this.selectedIndex];
			const sourceLanguage = selectedOption.dataset.source || 'Quellwort';
			const targetLanguage = selectedOption.dataset.target || 'Zielwort';

			// Update form labels
			document.getElementById('source_language_label').textContent = sourceLanguage + ' *';
			document.getElementById('target_language_label').textContent = targetLanguage + ' *';

			// Update bulk add format example
			document.getElementById('bulk_source_language').textContent = sourceLanguage;
			document.getElementById('bulk_target_language').textContent = targetLanguage;
		});

		// Also handle the bulk add list selection
		document.getElementById('list_id_multiple').addEventListener('change', function() {
			const selectedOption = this.options[this.selectedIndex];
			const sourceLanguage = selectedOption.dataset.source || 'Quellwort';
			const targetLanguage = selectedOption.dataset.target || 'Zielwort';

			// Update bulk add format example
			document.getElementById('bulk_source_language').textContent = sourceLanguage;
			document.getElementById('bulk_target_language').textContent = targetLanguage;
		});
	});
</script>

<?php
// Include modals for edit and delete functionality
require_once 'modal_edit.php';
require_once 'modal_delete.php';

// Include footer
require_once 'footer.php';
?>

<!-- Include JavaScript for vocabulary editing functionality -->
<script src="vocab_edit.js"></script>