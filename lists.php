<?php
/**
 * Vocabulary lists management page
 *
 * PHP version 8.0
 */

// Include configuration and database class
require_once 'config.php';
require_once 'VocabularyDatabase.php';

// Initialize variables
$successMessage = '';
$errorMessage = '';

// Get database connection
$db = getDbConnection();
$vocabDB = new VocabularyDatabase($db);

// Handle list creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
	$name = trim($_POST['name'] ?? '');
	$description = trim($_POST['description'] ?? '');
	$source_language = trim($_POST['source_language'] ?? '');
	$target_language = trim($_POST['target_language'] ?? '');

	// Validate input
	if (empty($name)) {
		$errorMessage = 'Der Listenname darf nicht leer sein.';
	} else if (empty($source_language)) {
		$errorMessage = 'Die Ausgangssprache darf nicht leer sein.';
	} else if (empty($target_language)) {
		$errorMessage = 'Die Zielsprache darf nicht leer sein.';
	} else {
		// Create list
		$listId = $vocabDB->createList($name, $source_language, $target_language, $description);

		if ($listId) {
			$successMessage = 'Liste "' . htmlspecialchars($name) . '" erfolgreich erstellt.';
		} else {
			$errorMessage = 'Fehler beim Erstellen der Liste. Möglicherweise existiert bereits eine Liste mit diesem Namen.';
		}
	}
}
// Handle list update
else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
	$id = intval($_POST['id'] ?? 0);
	$name = trim($_POST['name'] ?? '');
	$description = trim($_POST['description'] ?? '');
	$source_language = trim($_POST['source_language'] ?? '');
	$target_language = trim($_POST['target_language'] ?? '');

	// Validate input
	if ($id <= 0) {
		$errorMessage = 'Ungültige Listen-ID.';
	} else if (empty($name)) {
		$errorMessage = 'Der Listenname darf nicht leer sein.';
	} else if (empty($source_language)) {
		$errorMessage = 'Die Ausgangssprache darf nicht leer sein.';
	} else if (empty($target_language)) {
		$errorMessage = 'Die Zielsprache darf nicht leer sein.';
	} else {
		// Update list
		$success = $vocabDB->updateList($id, $name, $source_language, $target_language, $description);

		if ($success) {
			$successMessage = 'Liste "' . htmlspecialchars($name) . '" erfolgreich aktualisiert.';
		} else {
			$errorMessage = 'Fehler beim Aktualisieren der Liste. Möglicherweise existiert bereits eine Liste mit diesem Namen.';
		}
	}
}
// Handle list deletion
else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
	$id = intval($_POST['id'] ?? 0);

	// Validate input
	if ($id <= 0) {
		$errorMessage = 'Ungültige Listen-ID.';
	} else if ($id === 1) {
		$errorMessage = 'Die Standardliste kann nicht gelöscht werden.';
	} else {
		// Get list name for confirmation message
		$list = $vocabDB->getListById($id);

		if (!$list) {
			$errorMessage = 'Liste nicht gefunden.';
		} else {
			// Delete list
			$success = $vocabDB->deleteList($id);

			if ($success) {
				$successMessage = 'Liste "' . htmlspecialchars($list['name']) . '" erfolgreich gelöscht. Vokabeln wurden in die Standardliste verschoben.';
			} else {
				$errorMessage = 'Fehler beim Löschen der Liste.';
			}
		}
	}
}

// Get all lists
$lists = $vocabDB->getAllLists();
xlog($lists);

// Common language options for dropdowns
$languages = [
	'Deutsch',
	'Englisch',
	'Französisch',
	'Spanisch',
	'Italienisch',
	'Niederländisch',
	'Portugiesisch',
	'Russisch',
	'Polnisch',
	'Tschechisch',
	'Japanisch',
	'Chinesisch',
	'Arabisch',
	'Türkisch',
	'Koreanisch',
	'Hindi',
	'Schwedisch',
	'Finnisch',
	'Dänisch',
	'Norwegisch',
	'Ungarisch',
	'Griechisch',
	'Hebräisch',
	'Latein',
];
// Include header
require_once 'header.php';
?>
    <div class="row">
        <div class="col-md-12">
            <div class="card card-hover mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-folder"></i> Vokabellisten</h5>
                    <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#createListModal">
                        <i class="bi bi-plus-circle"></i> Neue Liste
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Beschreibung</th>
                                <th class="text-center">Sprachen</th>
                                <th class="text-center">Vokabeln</th>
                                <th class="text-center">Aktionen</th>
                            </tr>
                            </thead>
                            <tbody>
							<?php if (count($lists) > 0): ?>
								<?php foreach ($lists as $list): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($list['name']) ?></td>
                                        <td>
											<?php if (!empty($list['description'])): ?>
												<?= htmlspecialchars($list['description']) ?>
											<?php else: ?>
                                                <span class="text-muted fst-italic">Keine Beschreibung</span>
											<?php endif; ?>
                                        </td>
                                        <td class="text-center">
											<?php if (!empty($list['source_language']) && !empty($list['target_language'])): ?>
                                                <span class="badge bg-info me-1"><?= htmlspecialchars($list['source_language']) ?></span>
                                                <i class="bi bi-arrow-right"></i>
                                                <span class="badge bg-info ms-1"><?= htmlspecialchars($list['target_language']) ?></span>
											<?php else: ?>
                                                <span class="text-muted fst-italic">Nicht definiert</span>
											<?php endif; ?>
                                        </td>
                                        <td class="text-center"><?= $list['vocabulary_count'] ?></td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" onclick="editList(<?= $list['id'] ?>, '<?= htmlspecialchars(addslashes($list['name'])) ?>', '<?= htmlspecialchars(addslashes($list['description'])) ?>', '<?= htmlspecialchars(addslashes($list['source_language'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($list['target_language'] ?? '')) ?>')">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <a href="list.php?list_id=<?= $list['id'] ?>" class="btn btn-outline-secondary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="quiz.php?list_id=<?= $list['id'] ?>" class="btn btn-outline-success">
                                                    <i class="bi bi-question-circle"></i>
                                                </a>
                                                <a href="export_csv.php?list_id=<?= $list['id'] ?>" class="btn btn-outline-info">
                                                    <i class="bi bi-file-earmark-arrow-down"></i>
                                                </a>
												<?php if ($list['id'] > 1): ?>
                                                    <button type="button" class="btn btn-outline-danger" onclick="confirmDeleteList(<?= $list['id'] ?>, '<?= htmlspecialchars(addslashes($list['name'])) ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
												<?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
								<?php endforeach; ?>
							<?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">Keine Listen gefunden.</td>
                                </tr>
							<?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create List Modal -->
    <div class="modal fade" id="createListModal" tabindex="-1" aria-labelledby="createListModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="createListModalLabel"><i class="bi bi-folder-plus"></i> Neue Liste erstellen</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="lists.php">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Beschreibung</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="source_language" class="form-label">Ausgangssprache <span class="text-danger">*</span></label>
                                <select class="form-select" id="source_language" name="source_language" required>
                                    <option value="">-- Bitte wählen --</option>
									<?php foreach ($languages as $lang): ?>
                                        <option value="<?= $lang ?>"><?= htmlspecialchars($lang) ?></option>
									<?php endforeach; ?>
                                    <option value="other">Andere...</option>
                                </select>
                                <div id="source_language_custom_container" class="mt-2 d-none">
                                    <input type="text" class="form-control" id="source_language_custom" placeholder="Benutzerdefinierte Sprache">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="target_language" class="form-label">Zielsprache <span class="text-danger">*</span></label>
                                <select class="form-select" id="target_language" name="target_language" required>
                                    <option value="">-- Bitte wählen --</option>
									<?php foreach ($languages as $lang): ?>
                                        <option value="<?= $lang ?>"><?= htmlspecialchars($lang) ?></option>
									<?php endforeach; ?>
                                    <option value="other">Andere...</option>
                                </select>
                                <div id="target_language_custom_container" class="mt-2 d-none">
                                    <input type="text" class="form-control" id="target_language_custom" placeholder="Benutzerdefinierte Sprache">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary">Erstellen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit List Modal -->
    <div class="modal fade" id="editListModal" tabindex="-1" aria-labelledby="editListModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editListModalLabel"><i class="bi bi-pencil"></i> Liste bearbeiten</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="lists.php">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Beschreibung</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_source_language" class="form-label">Ausgangssprache <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_source_language" name="source_language" required>
                                    <option value="">-- Bitte wählen --</option>
									<?php foreach ($languages as $lang): ?>
                                        <option value="<?= $lang ?>"><?= htmlspecialchars($lang) ?></option>
									<?php endforeach; ?>
                                    <option value="other">Andere...</option>
                                </select>
                                <div id="edit_source_language_custom_container" class="mt-2 d-none">
                                    <input type="text" class="form-control" id="edit_source_language_custom" placeholder="Benutzerdefinierte Sprache">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_target_language" class="form-label">Zielsprache <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_target_language" name="target_language" required>
                                    <option value="">-- Bitte wählen --</option>
									<?php foreach ($languages as $lang): ?>
                                        <option value="<?= $lang ?>"><?= htmlspecialchars($lang) ?></option>
									<?php endforeach; ?>
                                    <option value="other">Andere...</option>
                                </select>
                                <div id="edit_target_language_custom_container" class="mt-2 d-none">
                                    <input type="text" class="form-control" id="edit_target_language_custom" placeholder="Benutzerdefinierte Sprache">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete List Modal -->
    <div class="modal fade" id="deleteListModal" tabindex="-1" aria-labelledby="deleteListModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteListModalLabel"><i class="bi bi-trash"></i> Liste löschen</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="lists.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    <div class="modal-body">
                        <p>Möchtest du die Liste <strong id="delete_name"></strong> wirklich löschen?</p>
                        <p class="text-danger">Alle Vokabeln in dieser Liste werden in die Standardliste verschoben.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-danger">Löschen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
		// Function to prepare edit modal
		function editList(id, name, description, sourceLanguage, targetLanguage) {
			document.getElementById('edit_id').value = id;
			document.getElementById('edit_name').value = name;
			document.getElementById('edit_description').value = description;

			// Set source language
			const sourceSelect = document.getElementById('edit_source_language');
			let sourceFound = false;

			for (let i = 0; i < sourceSelect.options.length; i++) {
				if (sourceSelect.options[i].value === sourceLanguage) {
					sourceSelect.selectedIndex = i;
					sourceFound = true;
					break;
				}
			}

			if (!sourceFound && sourceLanguage) {
				sourceSelect.value = 'other';
				document.getElementById('edit_source_language_custom_container').classList.remove('d-none');
				document.getElementById('edit_source_language_custom').value = sourceLanguage;
			}

			// Set target language
			const targetSelect = document.getElementById('edit_target_language');
			let targetFound = false;

			for (let i = 0; i < targetSelect.options.length; i++) {
				if (targetSelect.options[i].value === targetLanguage) {
					targetSelect.selectedIndex = i;
					targetFound = true;
					break;
				}
			}

			if (!targetFound && targetLanguage) {
				targetSelect.value = 'other';
				document.getElementById('edit_target_language_custom_container').classList.remove('d-none');
				document.getElementById('edit_target_language_custom').value = targetLanguage;
			}

			const modal = new bootstrap.Modal(document.getElementById('editListModal'));
			modal.show();
		}

		// Function to prepare delete modal
		function confirmDeleteList(id, name) {
			document.getElementById('delete_id').value = id;
			document.getElementById('delete_name').textContent = name;

			const modal = new bootstrap.Modal(document.getElementById('deleteListModal'));
			modal.show();
		}

		// Handle custom language inputs
		document.addEventListener('DOMContentLoaded', function() {
			// For create form
			document.getElementById('source_language').addEventListener('change', function() {
				const customContainer = document.getElementById('source_language_custom_container');
				if (this.value === 'other') {
					customContainer.classList.remove('d-none');
					const customInput = document.getElementById('source_language_custom');
					customInput.setAttribute('name', 'source_language');
					this.removeAttribute('name');
				} else {
					customContainer.classList.add('d-none');
					const customInput = document.getElementById('source_language_custom');
					customInput.removeAttribute('name');
					this.setAttribute('name', 'source_language');
				}
			});

			document.getElementById('target_language').addEventListener('change', function() {
				const customContainer = document.getElementById('target_language_custom_container');
				if (this.value === 'other') {
					customContainer.classList.remove('d-none');
					const customInput = document.getElementById('target_language_custom');
					customInput.setAttribute('name', 'target_language');
					this.removeAttribute('name');
				} else {
					customContainer.classList.add('d-none');
					const customInput = document.getElementById('target_language_custom');
					customInput.removeAttribute('name');
					this.setAttribute('name', 'target_language');
				}
			});

			// For edit form
			document.getElementById('edit_source_language').addEventListener('change', function() {
				const customContainer = document.getElementById('edit_source_language_custom_container');
				if (this.value === 'other') {
					customContainer.classList.remove('d-none');
					const customInput = document.getElementById('edit_source_language_custom');
					customInput.setAttribute('name', 'source_language');
					this.removeAttribute('name');
				} else {
					customContainer.classList.add('d-none');
					const customInput = document.getElementById('edit_source_language_custom');
					customInput.removeAttribute('name');
					this.setAttribute('name', 'source_language');
				}
			});

			document.getElementById('edit_target_language').addEventListener('change', function() {
				const customContainer = document.getElementById('edit_target_language_custom_container');
				if (this.value === 'other') {
					customContainer.classList.remove('d-none');
					const customInput = document.getElementById('edit_target_language_custom');
					customInput.setAttribute('name', 'target_language');
					this.removeAttribute('name');
				} else {
					customContainer.classList.add('d-none');
					const customInput = document.getElementById('edit_target_language_custom');
					customInput.removeAttribute('name');
					this.setAttribute('name', 'target_language');
				}
			});
		});
    </script>

<?php
// Include footer
require_once 'footer.php';
?>