<?php
/**
 * Vocabulary list page
 *
 * PHP version 8.0
 */


exit;
// Include header
require_once 'config.php';

// Get database connection
$db = getDbConnection();

// Handle filters
$importanceFilter = isset($_GET['importance']) ? intval($_GET['importance']) : 0;
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'date_added';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build query
$query = 'SELECT * FROM vocabulary WHERE 1=1';
$params = [];

if ($importanceFilter > 0) {
	$query .= ' AND importance = ?';
	$params[] = $importanceFilter;
}

if (!empty($searchTerm)) {
	$query .= ' AND (word_source LIKE ? OR word_target LIKE ? OR example_sentence LIKE ?)';
	$searchPattern = '%' . $searchTerm . '%';
	$params[] = $searchPattern;
	$params[] = $searchPattern;
	$params[] = $searchPattern;
}

// Validate and apply sorting
$allowedSortColumns = ['word_source', 'word_target', 'importance', 'date_added'];
$allowedSortOrders = ['ASC', 'DESC'];

if (!in_array($sortBy, $allowedSortColumns)) {
	$sortBy = 'date_added';
}

if (!in_array($sortOrder, $allowedSortOrders)) {
	$sortOrder = 'DESC';
}

$query .= " ORDER BY $sortBy $sortOrder";

// Prepare and execute statement
$stmt = $db->prepare($query);
$stmt->execute($params);
$vocabulary = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalCount = count($vocabulary);

// Toggle sort order for links
function getToggleSortOrder($currentOrder) {
	return ($currentOrder === 'ASC') ? 'DESC' : 'ASC';
}

// Generate sort URL
function getSortUrl($column, $currentSortBy, $currentSortOrder) {
	$params = $_GET;
	$params['sort'] = $column;
	$params['order'] = ($currentSortBy === $column) ? getToggleSortOrder($currentSortOrder) : 'ASC';
	return '?' . http_build_query($params);
}

// Get sort icon
function getSortIcon($column, $currentSortBy, $currentSortOrder) {
	if ($currentSortBy !== $column) {
		return '<i class="bi bi-arrow-down-up text-muted"></i>';
	}

	return ($currentSortOrder === 'ASC')
		? '<i class="bi bi-sort-alpha-down"></i>'
		: '<i class="bi bi-sort-alpha-up"></i>';
}


require_once 'header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card card-hover">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-card-list"></i> Vokabelliste</h5>
                <a href="add.php" class="btn btn-sm btn-light">
                    <i class="bi bi-plus-circle"></i> Neue Vokabel
                </a>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <form method="get" action="list.php" class="mb-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" name="search" placeholder="Suche..."
                                       value="<?= htmlspecialchars($searchTerm) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" name="importance">
                                <option value="0" <?= $importanceFilter === 0 ? 'selected' : '' ?>>Alle Wichtigkeitsstufen</option>
                                <option value="1" <?= $importanceFilter === 1 ? 'selected' : '' ?>>Wichtigkeit 1 (Niedrig)</option>
                                <option value="2" <?= $importanceFilter === 2 ? 'selected' : '' ?>>Wichtigkeit 2</option>
                                <option value="3" <?= $importanceFilter === 3 ? 'selected' : '' ?>>Wichtigkeit 3 (Mittel)</option>
                                <option value="4" <?= $importanceFilter === 4 ? 'selected' : '' ?>>Wichtigkeit 4</option>
                                <option value="5" <?= $importanceFilter === 5 ? 'selected' : '' ?>>Wichtigkeit 5 (Hoch)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filter anwenden</button>
                        </div>
                    </div>

                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                    <input type="hidden" name="order" value="<?= htmlspecialchars($sortOrder) ?>">
                </form>

                <!-- Results -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                        <tr>
                            <th>
                                <a href="<?= getSortUrl('word_source', $sortBy, $sortOrder) ?>" class="text-decoration-none">
                                    Englisch <?= getSortIcon('word_source', $sortBy, $sortOrder) ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortUrl('word_target', $sortBy, $sortOrder) ?>" class="text-decoration-none">
                                    Deutsch <?= getSortIcon('word_target', $sortBy, $sortOrder) ?>
                                </a>
                            </th>
                            <th>Beispielsatz</th>
                            <th class="text-center">
                                <a href="<?= getSortUrl('importance', $sortBy, $sortOrder) ?>" class="text-decoration-none">
                                    Wichtigkeit <?= getSortIcon('importance', $sortBy, $sortOrder) ?>
                                </a>
                                <span class="ms-1 small text-muted" data-bs-toggle="tooltip" data-bs-placement="top"
                                      title="Wichtigkeit mit + und - anpassen">
                                        <i class="bi bi-info-circle"></i>
                                    </span>
                            </th>
                            <th class="text-center">
                                <a href="<?= getSortUrl('date_added', $sortBy, $sortOrder) ?>" class="text-decoration-none">
                                    Hinzugefügt am <?= getSortIcon('date_added', $sortBy, $sortOrder) ?>
                                </a>
                            </th>
                            <th class="text-center">Aktionen</th>
                        </tr>
                        </thead>
                        <tbody>
						<?php if ($totalCount > 0): ?>
							<?php foreach ($vocabulary as $vocab): ?>
                                <tr class="importance-<?= $vocab['importance'] ?>" data-vocab-id="<?= $vocab['id'] ?>">
                                    <td><?= htmlspecialchars($vocab['word_source']) ?></td>
                                    <td><?= htmlspecialchars($vocab['word_target']) ?></td>
                                    <td>
										<?php if (!empty($vocab['example_sentence'])): ?>
											<?= htmlspecialchars($vocab['example_sentence']) ?>
										<?php else: ?>
                                            <span class="text-muted fst-italic">Kein Beispiel</span>
										<?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <button type="button"
                                                    onclick="updateImportance(<?= $vocab['id'] ?>, 'decrease', this)"
                                                    class="btn btn-sm btn-link p-0 me-2 <?= $vocab['importance'] <= 1 ? 'disabled' : '' ?>"
												<?= $vocab['importance'] <= 1 ? 'aria-disabled="true"' : '' ?>>
                                                <i class="bi bi-dash-circle"></i>
                                            </button>
                                            <span id="importance-badge-<?= $vocab['id'] ?>" class="badge rounded-pill bg-<?= getImportanceBadgeColor($vocab['importance']) ?> mx-2 px-3 py-2">
                                                    <?= $vocab['importance'] ?>
                                                </span>
                                            <button type="button"
                                                    onclick="updateImportance(<?= $vocab['id'] ?>, 'increase', this)"
                                                    class="btn btn-sm btn-link p-0 ms-2 <?= $vocab['importance'] >= 5 ? 'disabled' : '' ?>"
												<?= $vocab['importance'] >= 5 ? 'aria-disabled="true"' : '' ?>>
                                                <i class="bi bi-plus-circle"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="text-center">
										<?= date('d.m.Y', strtotime($vocab['date_added'])) ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" onclick="editVocabulary(<?= $vocab['id'] ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="quiz.php?vocab_id=<?= $vocab['id'] ?>" class="btn btn-outline-success">
                                                <i class="bi bi-question-circle"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger"
                                                    data-bs-toggle="modal" data-bs-target="#deleteModal<?= $vocab['id'] ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>

                                        <!-- Delete Modal -->
                                        <div class="modal fade" id="deleteModal<?= $vocab['id'] ?>" tabindex="-1"
                                             aria-labelledby="deleteModalLabel<?= $vocab['id'] ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="deleteModalLabel<?= $vocab['id'] ?>">
                                                            Vokabel löschen
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        Möchtest du die Vokabel <strong><?= htmlspecialchars($vocab['word_source']) ?> -
															<?= htmlspecialchars($vocab['word_target']) ?></strong> wirklich löschen?
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                                                        <a href="delete.php?id=<?= $vocab['id'] ?>" class="btn btn-danger">Löschen</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
							<?php endforeach; ?>
						<?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <p class="mb-0">Keine Vokabeln gefunden.</p>
									<?php if (!empty($searchTerm) || $importanceFilter > 0): ?>
                                        <a href="list.php" class="btn btn-sm btn-outline-secondary mt-2">Filter zurücksetzen</a>
									<?php else: ?>
                                        <a href="add.php" class="btn btn-sm btn-primary mt-2">Erste Vokabel hinzufügen</a>
									<?php endif; ?>
                                </td>
                            </tr>
						<?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination placeholder (to be implemented) -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <p class="mb-0"><?= $totalCount ?> Vokabeln gefunden</p>

					<?php if ($totalCount > 0): ?>
                        <a href="quiz.php?filtered=1<?= $importanceFilter > 0 ? '&importance=' . $importanceFilter : '' ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>" class="btn btn-success">
                            <i class="bi bi-question-circle"></i> Diese Vokabeln abfragen
                        </a>
					<?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Edit Modal -->
<div class="modal fade" id="editVocabModal" tabindex="-1" aria-labelledby="editVocabModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editVocabModalLabel"><i class="bi bi-pencil"></i> Vokabel bearbeiten</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editVocabForm">
                    <input type="hidden" id="edit_vocab_id" name="edit_vocab_id">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_word_source" class="form-label">Englisch <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_word_source" name="edit_word_source" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_word_target" class="form-label">Deutsch <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_word_target" name="edit_word_target" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_example_sentence" class="form-label">Beispielsatz</label>
                        <textarea class="form-control" id="edit_example_sentence" name="edit_example_sentence" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="edit_importance" class="form-label">Wichtigkeit <span class="text-danger">*</span></label>
                        <div class="d-flex">
                            <input type="range" class="form-range flex-grow-1 me-2" id="edit_importance" name="edit_importance"
                                   min="1" max="5" step="1" value="3">
                            <span id="edit_importance_display" class="badge bg-primary">3</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted small">
                            <span>Niedrig (1)</span>
                            <span>Mittel (3)</span>
                            <span>Hoch (5)</span>
                        </div>
                    </div>
                </form>
                <div class="alert alert-danger d-none" id="editErrorMessage"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-primary" id="saveVocabButton">
                    <i class="bi bi-save"></i> Speichern
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once 'footer.php';
?>

<script>
	// Initialize tooltips
	document.addEventListener('DOMContentLoaded', function() {
		var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
		var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
			return new bootstrap.Tooltip(tooltipTriggerEl);
		});
	});

	// AJAX function to update importance
	function updateImportance(vocabId, action, buttonElement) {
		// Prevent action if button is disabled
		if (buttonElement.classList.contains('disabled')) {
			return;
		}

		// Get the current badge element
		const badgeElement = document.getElementById('importance-badge-' + vocabId);
		const currentImportance = parseInt(badgeElement.textContent.trim());

		// Calculate new importance value (for immediate UI update)
		let newImportance = currentImportance;
		if (action === 'increase' && currentImportance < 5) {
			newImportance = currentImportance + 1;
		} else if (action === 'decrease' && currentImportance > 1) {
			newImportance = currentImportance - 1;
		}

		// Make AJAX request
		fetch('update_importance_ajax.php?id=' + vocabId + '&action=' + action, {
			method: 'GET',
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			}
		})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					// Update badge text
					badgeElement.textContent = data.newImportance;

					// Update badge color
					badgeElement.className = 'badge rounded-pill mx-2 px-3 py-2';
					badgeElement.classList.add('bg-' + getImportanceBadgeColor(data.newImportance));

					// Find decrease and increase buttons
					const row = buttonElement.closest('tr');
					const decreaseButton = row.querySelector('button[onclick*="decrease"]');
					const increaseButton = row.querySelector('button[onclick*="increase"]');

					// Update button states
					if (data.newImportance <= 1) {
						decreaseButton.classList.add('disabled');
						decreaseButton.setAttribute('aria-disabled', 'true');
					} else {
						decreaseButton.classList.remove('disabled');
						decreaseButton.removeAttribute('aria-disabled');
					}

					if (data.newImportance >= 5) {
						increaseButton.classList.add('disabled');
						increaseButton.setAttribute('aria-disabled', 'true');
					} else {
						increaseButton.classList.remove('disabled');
						increaseButton.removeAttribute('aria-disabled');
					}

					// Show a temporary success message
					const feedback = document.createElement('div');
					feedback.className = 'position-fixed bottom-0 end-0 p-3';
					feedback.style.zIndex = '5000';
					feedback.innerHTML = `
                <div class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="bi bi-check-circle-fill"></i> Wichtigkeit aktualisiert!
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
					document.body.appendChild(feedback);

					const toast = new bootstrap.Toast(feedback.querySelector('.toast'));
					toast.show();

					// Remove toast after it's hidden
					feedback.addEventListener('hidden.bs.toast', () => {
						feedback.remove();
					});
				} else {
					// Show error message
					alert('Fehler: ' + data.message);
				}
			})
			.catch(error => {
				console.error('Error:', error);
				alert('Es ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.');
			});
	}

	// Handle editing vocabulary
	let editModal = null;
	let editErrorMessage = null;

	document.addEventListener('DOMContentLoaded', function() {
		// Initialize modal and error message element
		editModal = new bootstrap.Modal(document.getElementById('editVocabModal'));
		editErrorMessage = document.getElementById('editErrorMessage');

		// Handle importance slider change in edit modal
		document.getElementById('edit_importance').addEventListener('input', function() {
			updateImportanceDisplay('edit_importance', 'edit_importance_display');
		});

		// Handle save button click
		document.getElementById('saveVocabButton').addEventListener('click', saveVocabulary);
	});

	// Update importance badge display
	function updateImportanceDisplay(sliderId, displayId) {
		const slider = document.getElementById(sliderId);
		const display = document.getElementById(displayId);
		const value = parseInt(slider.value);

		display.textContent = value;

		// Update badge color
		display.className = 'badge';
		display.classList.add('bg-' + getImportanceBadgeColor(value));
	}

	// Load vocabulary data and show edit modal
	function editVocabulary(vocabId) {
		// Make sure we have the edit modal and error message elements
		if (!editModal) {
			editModal = new bootstrap.Modal(document.getElementById('editVocabModal'));
		}

		if (!editErrorMessage) {
			editErrorMessage = document.getElementById('editErrorMessage');
		}

		// Reset error message
		if (editErrorMessage) {
			editErrorMessage.classList.add('d-none');
			editErrorMessage.textContent = '';
		}

		// Fetch vocabulary data
		fetch('edit_ajax.php?id=' + vocabId, {
			method: 'GET',
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			}
		})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					const vocab = data.vocab;

					// Populate form fields
					document.getElementById('edit_vocab_id').value = vocab.id;
					document.getElementById('edit_word_source').value = vocab.word_source;
					document.getElementById('edit_word_target').value = vocab.word_target;
					document.getElementById('edit_example_sentence').value = vocab.example_sentence || '';
					document.getElementById('edit_importance').value = vocab.importance;

					// Update importance display
					updateImportanceDisplay('edit_importance', 'edit_importance_display');

					// Show modal
					editModal.show();
				} else {
					alert('Fehler beim Laden der Vokabel: ' + data.message);
				}
			})
			.catch(error => {
				console.error('Error:', error);
				alert('Es ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.');
			});
	}

	// Save vocabulary changes
	function saveVocabulary() {
		// Get form data
		const vocabId = document.getElementById('edit_vocab_id').value;
		const wordSource = document.getElementById('edit_word_source').value.trim();
		const wordTarget = document.getElementById('edit_word_target').value.trim();
		const exampleSentence = document.getElementById('edit_example_sentence').value.trim();
		const importance = document.getElementById('edit_importance').value;

		// Validate form
		if (!wordSource || !wordTarget) {
			editErrorMessage.textContent = 'Bitte fülle alle Pflichtfelder aus.';
			editErrorMessage.classList.remove('d-none');
			return;
		}

		// Prepare data
		const vocabData = {
			id: vocabId,
			word_source: wordSource,
			word_target: wordTarget,
			example_sentence: exampleSentence,
			importance: importance
		};

		// Send update request
		fetch('edit_ajax.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			},
			body: JSON.stringify(vocabData)
		})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					// Update row in the table
					updateVocabRow(data.vocab);

					// Close modal
					editModal.hide();

					// Show success toast
					showToast('Vokabel erfolgreich aktualisiert', 'success');
				} else {
					// Show error message
					editErrorMessage.textContent = data.message;
					editErrorMessage.classList.remove('d-none');
				}
			})
			.catch(error => {
				console.error('Error:', error);
				editErrorMessage.textContent = 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.';
				editErrorMessage.classList.remove('d-none');
			});
	}

	// Update vocabulary row in the table
	function updateVocabRow(vocab) {
		// Find the row
		const row = document.querySelector(`tr[data-vocab-id="${vocab.id}"]`);
		if (!row) return;

		// Update cells
		const cells = row.querySelectorAll('td');
		cells[0].textContent = vocab.word_source;
		cells[1].textContent = vocab.word_target;

		// Update example sentence (may be empty)
		if (vocab.example_sentence) {
			cells[2].innerHTML = vocab.example_sentence;
		} else {
			cells[2].innerHTML = '<span class="text-muted fst-italic">Kein Beispiel</span>';
		}

		// Update importance badge
		const importanceBadge = row.querySelector('#importance-badge-' + vocab.id);
		if (importanceBadge) {
			importanceBadge.textContent = vocab.importance;
			importanceBadge.className = 'badge rounded-pill mx-2 px-3 py-2 bg-' + getImportanceBadgeColor(vocab.importance);
		}

		// Update importance buttons
		const decreaseButton = row.querySelector('button[onclick*="decrease"]');
		const increaseButton = row.querySelector('button[onclick*="increase"]');

		if (decreaseButton) {
			if (parseInt(vocab.importance) <= 1) {
				decreaseButton.classList.add('disabled');
				decreaseButton.setAttribute('aria-disabled', 'true');
			} else {
				decreaseButton.classList.remove('disabled');
				decreaseButton.removeAttribute('aria-disabled');
			}
		}

		if (increaseButton) {
			if (parseInt(vocab.importance) >= 5) {
				increaseButton.classList.add('disabled');
				increaseButton.setAttribute('aria-disabled', 'true');
			} else {
				increaseButton.classList.remove('disabled');
				increaseButton.removeAttribute('aria-disabled');
			}
		}
	}

	// Show toast notification
	function showToast(message, type = 'success') {
		const feedback = document.createElement('div');
		feedback.className = 'position-fixed bottom-0 end-0 p-3';
		feedback.style.zIndex = '5000';
		feedback.innerHTML = `
        <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill'}"></i> ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
		document.body.appendChild(feedback);

		const toast = new bootstrap.Toast(feedback.querySelector('.toast'));
		toast.show();

		// Remove toast after it's hidden
		feedback.addEventListener('hidden.bs.toast', () => {
			feedback.remove();
		});
	}
</script>