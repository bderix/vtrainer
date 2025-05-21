<?php
/**
 * Vocabulary lists management page
 *
 * PHP version 8.0
 */

// Include configuration and database class
require_once 'config.php';
global $app;

$vocabDB = $app->vocabDB;
$vtrequest = $app->request;

// Initialize variables
$successMessage = '';
$errorMessage = '';

// Assuming $languages is defined somewhere, e.g., in config.php or fetched
if (!isset($languages)) {
	$languages = ['Englisch', 'Deutsch', 'Französisch', 'Spanisch', 'Italienisch']; // Beispiel Sprachen
}

xlog($_POST);
xlog($vtrequest);
xlog($_SERVER['REQUEST_METHOD']);

if ($vtrequest->isPostRequest()) {

	// Handle list creation
	if ($vtrequest->getAction() == 'create') {
		$name = $vtrequest->post('name', '');
		$description = $vtrequest->post('description', '');
		$source_language = $vtrequest->post('source_language', '');
		$target_language = $vtrequest->post('target_language', '');
		$is_private = $vtrequest->post('is_private') == 'on' ? 1 : 0;
        $newList = new Liste(0, $name, $source_language, $target_language, $description, $is_private, $_SESSION['user_id']);

		if (empty($name)) {
			$errorMessage = 'Der Listenname darf nicht leer sein.';
		} else if (empty($source_language)) {
			$errorMessage = 'Die Ausgangssprache darf nicht leer sein.';
		} else if (empty($target_language)) {
			$errorMessage = 'Die Zielsprache darf nicht leer sein.';
		} else {
			$listId = $vocabDB->createList($name, $description, $source_language, $target_language, $_SESSION['user_id'], $is_private);
			if ($listId) {
				$successMessage = 'Liste "' . htmlspecialchars($name) . '" erfolgreich erstellt.';
			} else {
				$errorMessage = 'Fehler beim Erstellen der Liste. Möglicherweise existiert bereits eine Liste mit diesem Namen.';
			}
		}
        xlog($errorMessage);

	} // Handle list update
	else if ($vtrequest->getAction() === 'update') {
		$id = $vtrequest->post('id', 0);
		$name = $vtrequest->post('name', '');
		$description = $vtrequest->post('description', '');
		$source_language = $vtrequest->post('source_language', '');
		$target_language = $vtrequest->post('target_language', '');
		$is_private = $vtrequest->post('is_private') == 'on' ? 1 : 0;

		if ($id <= 0) {
			$errorMessage = 'Ungültige Listen-ID.';
		} else if (empty($name)) {
			$errorMessage = 'Der Listenname darf nicht leer sein.';
		} else if (empty($source_language)) {
			$errorMessage = 'Die Ausgangssprache darf nicht leer sein.';
		} else if (empty($target_language)) {
			$errorMessage = 'Die Zielsprache darf nicht leer sein.';
		} else {
			if (!userCanEditList($id, $vocabDB)) {
				$errorMessage = 'Du hast keine Berechtigung zum Bearbeiten dieser Liste.';
			} else {
				$success = $vocabDB->updateList($id, $name, $source_language, $target_language, $description, $is_private);
				if ($success) {
					$successMessage = 'Liste "' . htmlspecialchars($name) . '" erfolgreich aktualisiert.';
				} else {
					$errorMessage = 'Fehler beim Aktualisieren der Liste. Möglicherweise existiert bereits eine Liste mit diesem Namen.';
                    xlog($errorMessage);
				}
			}
		}
	} // Handle list deletion
	else if ($vtrequest->getAction() === 'delete') {
		$id = $vtrequest->post('id', 0);
		if ($id <= 0) {
			$errorMessage = 'Ungültige Listen-ID.';
		} else if ($id === 1) {
			$errorMessage = 'Die Standardliste kann nicht gelöscht werden.';
		} else {
			if (!userCanEditList($id, $vocabDB)) {
				$errorMessage = 'Du hast keine Berechtigung zum Löschen dieser Liste.';
			} else {
				$list = $vocabDB->getListById($id);
				if (!$list) {
					$errorMessage = 'Liste nicht gefunden.';
				} else {
					$success = $vocabDB->deleteList($id);
					if ($success) {
						$successMessage = 'Liste "' . htmlspecialchars($list['name']) . '" erfolgreich gelöscht. Vokabeln wurden in die Standardliste verschoben.';
					} else {
						$errorMessage = 'Fehler beim Löschen der Liste.';
					}
				}
			}
		}

	}
	else if ($vtrequest->getAction() === 'toggle_privacy') {
		$id = $vtrequest->post('id', 0);
		$is_private = intval($vtrequest->post('is_private', 1));

		if ($id <= 0) {
			$errorMessage = 'Ungültige Listen-ID.';
		} else {
			if (!userCanEditList($id, $vocabDB)) {
				$errorMessage = 'Du hast keine Berechtigung zum Ändern der Privatsphäre-Einstellungen dieser Liste.';
			} else {
				$list = $vocabDB->getListById($id);
				if (!$list) {
					$errorMessage = 'Liste nicht gefunden.';
				} else {
					$success = $vocabDB->updateList(
						$id,
						$list['name'],
						$list['source_language'],
						$list['target_language'],
						$list['description'],
						$is_private
					);
					if ($success) {
						$privacyText = $is_private ? 'privat' : 'öffentlich';
						$successMessage = 'Liste "' . htmlspecialchars($list['name']) . '" ist jetzt ' . $privacyText . '.';
					} else {
						$errorMessage = 'Fehler beim Aktualisieren der Privatsphäre-Einstellungen.';
					}
				}
			}
		}
	}
}
$ownLists = $app->userListen->getLists();
$publicLists = $vocabDB->getPublicVocabularyLists($_SESSION['user_id']);

require_once 'header.php';

?>
    <div class="row">
        <div class="col-md-12">
            <div class="card card-hover mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-folder"></i> Meine Vokabellisten</h5>
                    <button type="button" class="btn btn-sm btn-light" onclick="openListFormModal('create')">
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
                                <th class="text-center">Privatsphäre</th>
                                <th class="text-center">Aktionen</th>
                            </tr>
                            </thead>
                            <tbody>
							<?php if (count($ownLists) > 0): ?>
								<?php foreach ($ownLists as $list): ?>
                                    <tr>
                                        <td><a href="list.php?list_id=<?= $list->id ?>" class="text-decoration-none"><?= htmlspecialchars($list->name) ?></a></td>
                                        <td>
											<?php if (!empty($list->description)): ?>
												<?= htmlspecialchars($list->description) ?>
											<?php else: ?>
                                                <span class="text-muted fst-italic">Keine Beschreibung</span>
											<?php endif; ?>
                                        </td>
                                        <td class="text-center">
											<?php if (!empty($list->sourceLanguage) && !empty($list->targetLanguage)): ?>
                                                <span class="badge bg-info me-1"><?= htmlspecialchars($list->sourceLanguage) ?></span>
                                                <i class="bi bi-arrow-right"></i>
                                                <span class="badge bg-info ms-1"><?= htmlspecialchars($list->targetLanguage) ?></span>
											<?php else: ?>
                                                <span class="text-muted fst-italic">Nicht definiert</span>
											<?php endif; ?>
                                        </td>
                                        <td class="text-center"><?= $list->vocabularyCount ?></td>
                                        <td class="text-center">
											<?php
											$is_private = isset($list->isPrivate) ? $list->isPrivate : (isset($list->is_private) ? $list->is_private : 1);
											$privacy_badge_class = $is_private ? 'bg-danger' : 'bg-success';
											$privacy_icon = $is_private ? 'bi-lock-fill' : 'bi-unlock-fill';
											$privacy_text = $is_private ? 'Privat' : 'Öffentlich';
											$new_privacy_value = $is_private ? 0 : 1;
											?>
                                            <form method="post" action="lists.php" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_privacy">
                                                <input type="hidden" name="id" value="<?= $list->id ?>">
                                                <input type="hidden" name="is_private" value="<?= $new_privacy_value ?>">
                                                <button type="submit" class="btn btn-sm badge <?= $privacy_badge_class ?>" title="Klicken zum Umschalten">
                                                    <i class="bi <?= $privacy_icon ?> me-1"></i> <?= $privacy_text ?>
                                                </button>
                                            </form>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" onclick='openListFormModal("edit", <?= json_encode($list, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>)'>
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <a href="quiz.php?list_id=<?= $list->id ?>" class="btn btn-outline-success">
                                                    <i class="bi bi-question-circle"></i>
                                                </a>
                                                <a href="export_csv.php?list_id=<?= $list->id ?>" class="btn btn-outline-info">
                                                    <i class="bi bi-file-earmark-arrow-down"></i>
                                                </a>
												<?php if ($list->id > 1): // Standardliste (ID 1) nicht löschbar ?>
                                                    <button type="button" class="btn btn-outline-danger" onclick="confirmDeleteList(<?= $list->id ?>, '<?= htmlspecialchars(addslashes($list->name)) ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
												<?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
								<?php endforeach; ?>
							<?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">Du hast noch keine eigenen Listen erstellt.</td>
                                </tr>
							<?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php if (count($publicLists) > 0): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="card card-hover mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0"><i class="bi bi-globe"></i> Öffentliche Listen</h5>
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
                                <th class="text-center">Ersteller</th>
                                <th class="text-center">Aktionen</th>
                            </tr>
                            </thead>
                            <tbody>
							<?php foreach ($publicLists as $list): ?>
                                <tr>
                                    <td><a href="list.php?list_id=<?= $list->id ?>" class="text-decoration-none"><?= htmlspecialchars($list->name) ?></a></td>
                                    <td>
										<?php if (!empty($list->description)): ?>
											<?= htmlspecialchars($list->description) ?>
										<?php else: ?>
                                            <span class="text-muted fst-italic">Keine Beschreibung</span>
										<?php endif; ?>
                                    </td>
                                    <td class="text-center">
										<?php if (!empty($list->sourceLanguage) && !empty($list->targetLanguage)): ?>
                                            <span class="badge bg-info me-1"><?= htmlspecialchars($list->sourceLanguage) ?></span>
                                            <i class="bi bi-arrow-right"></i>
                                            <span class="badge bg-info ms-1"><?= htmlspecialchars($list->targetLanguage) ?></span>
										<?php else: ?>
                                            <span class="text-muted fst-italic">Nicht definiert</span>
										<?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= $list->vocabularyCount ?></td>
                                    <td class="text-center">
										<?php if (!empty($list->username)): ?>
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($list->username) ?>
                                            </span>
										<?php else: ?>
                                            <span class="text-muted fst-italic">Unbekannt</span>
										<?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="quiz.php?list_id=<?= $list->id ?>" class="btn btn-outline-success">
                                                <i class="bi bi-question-circle"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-primary" onclick="copyList(<?= $list->id ?>, '<?= htmlspecialchars(addslashes($list->name)) ?>')">
                                                <i class="bi bi-files"></i>
                                            </button>
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
<?php endif; ?>

    <!-- Unified Create/Edit List Modal -->
    <div class="modal fade" id="listFormModal" tabindex="-1" aria-labelledby="listFormModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="listFormModalLabel">Vokabelliste</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div id='modal_err'></div>

                <form method="post" action="lists.php" id="listForm">
                    <input type="hidden" name="action" id="list_action" value="create">
                    <input type="hidden" name="id" id="list_id" value="0">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="form_name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="form_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="form_description" class="form-label">Beschreibung</label>
                            <textarea class="form-control" id="form_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="form_source_language" class="form-label">Ausgangssprache <span class="text-danger">*</span></label>
                                <select class="form-select" id="form_source_language" name="source_language" required>
                                    <option value="">-- Bitte wählen --</option>
									<?php foreach ($languages as $lang): ?>
                                        <option value="<?= htmlspecialchars($lang) ?>"><?= htmlspecialchars($lang) ?></option>
									<?php endforeach; ?>
                                    <option value="other">Andere...</option>
                                </select>
                                <div id="form_source_language_custom_container" class="mt-2 d-none">
                                    <input type="text" class="form-control" id="form_source_language_custom" placeholder="Benutzerdefinierte Sprache">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="form_target_language" class="form-label">Zielsprache <span class="text-danger">*</span></label>
                                <select class="form-select" id="form_target_language" name="target_language" required>
                                    <option value="">-- Bitte wählen --</option>
									<?php foreach ($languages as $lang): ?>
                                        <option value="<?= htmlspecialchars($lang) ?>"><?= htmlspecialchars($lang) ?></option>
									<?php endforeach; ?>
                                    <option value="other">Andere...</option>
                                </select>
                                <div id="form_target_language_custom_container" class="mt-2 d-none">
                                    <input type="text" class="form-control" id="form_target_language_custom" placeholder="Benutzerdefinierte Sprache">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="form_is_private" name="is_private" value="on" checked>
                            <label class="form-check-label" for="form_is_private">
                                <i class="bi bi-lock-fill me-1"></i> Private Liste
                                <small class="form-text d-block text-muted">
                                    Private Listen sind nur für dich sichtbar. Öffentliche Listen können von allen Benutzern gesehen werden.
                                </small>
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary" id="listFormSubmitButton">Erstellen</button>
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

    <!-- Copy List Modal -->
    <div class="modal fade" id="copyListModal" tabindex="-1" aria-labelledby="copyListModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="copyListModalLabel"><i class="bi bi-files"></i> Liste kopieren</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="copy_list.php">
                    <input type="hidden" name="source_list_id" id="copy_list_id">
                    <div class="modal-body">
                        <p>Du bist dabei, die Liste <strong id="copy_list_name"></strong> zu kopieren.</p>
                        <p>Alle Vokabeln dieser Liste werden in deine neue Liste übertragen.</p>
                        <div class="mb-3">
                            <label for="copy_new_name" class="form-label">Name der neuen Liste <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="copy_new_name" name="new_name" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="copy_is_private" name="is_private" value="on" checked>
                            <label class="form-check-label" for="copy_is_private">
                                <i class="bi bi-lock-fill me-1"></i> Private Liste
                                <small class="form-text d-block text-muted">
                                    Private Listen sind nur für dich sichtbar. Öffentliche Listen können von allen Benutzern gesehen werden.
                                </small>
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary">Kopieren</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
		// Deklariere Variablen für Modal-Instanz und Elemente im äußeren Scope,
		// damit sie in Funktionen zugänglich sind.
		let listFormModalInstance;
		const listFormModalElement = document.getElementById('listFormModal');
		const listForm = document.getElementById('listForm');
		const listFormModalLabel = document.getElementById('listFormModalLabel');
		const listActionInput = document.getElementById('list_action');
		const listIdInput = document.getElementById('list_id');
		const listFormSubmitButton = document.getElementById('listFormSubmitButton');

		const formNameInput = document.getElementById('form_name');
		const formDescriptionInput = document.getElementById('form_description');
		const formSourceLanguageSelect = document.getElementById('form_source_language');
		const formTargetLanguageSelect = document.getElementById('form_target_language');
		const formIsPrivateCheckbox = document.getElementById('form_is_private');

		const formSourceLangCustomContainer = document.getElementById('form_source_language_custom_container');
		const formSourceLangCustomInput = document.getElementById('form_source_language_custom');
		const formTargetLangCustomContainer = document.getElementById('form_target_language_custom_container');
		const formTargetLangCustomInput = document.getElementById('form_target_language_custom');

		// Funktion zum Öffnen und Vorbereiten des Modals
		function openListFormModal(mode, listData = null, errMsg = null) {
			if (!listFormModalInstance) {
				// Dieser Fall sollte selten auftreten, wenn DOMContentLoaded korrekt abgewartet wurde
				// und Bootstrap JS geladen ist.
				if (listFormModalElement) {
					listFormModalInstance = new bootstrap.Modal(listFormModalElement);
				} else {
					console.error("listFormModalElement nicht gefunden. Modal kann nicht initialisiert werden.");
					return;
				}
			}

			listForm.reset(); // Setzt die meisten Formularfelder zurück

			const modalErrMsg = listFormModalElement.querySelector('#modal_err');

			// Manuelles Zurücksetzen für benutzerdefinierte Sprachfelder und deren Zustand
			formSourceLangCustomContainer.classList.add('d-none');
			formSourceLangCustomInput.value = '';
			formSourceLanguageSelect.setAttribute('name', formSourceLanguageSelect.dataset.originalName || 'source_language');
			formSourceLangCustomInput.removeAttribute('name');

			formTargetLangCustomContainer.classList.add('d-none');
			formTargetLangCustomInput.value = '';
			formTargetLanguageSelect.setAttribute('name', formTargetLanguageSelect.dataset.originalName || 'target_language');
			formTargetLangCustomInput.removeAttribute('name');


			if (mode === 'create') {
				listFormModalLabel.innerHTML = '<i class="bi bi-folder-plus"></i> Neue Liste erstellen';
				listActionInput.value = 'create';
				listIdInput.value = '0';
				listFormSubmitButton.textContent = 'Erstellen';
				listFormSubmitButton.className = 'btn btn-primary'; // Setzt Klasse komplett neu
				formIsPrivateCheckbox.checked = true; // Standard für neue Listen
			} else if (mode === 'edit' && listData) {
				listFormModalLabel.innerHTML = '<i class="bi bi-pencil"></i> Liste bearbeiten';
				listActionInput.value = 'update';
				listIdInput.value = listData.id;
				listFormSubmitButton.textContent = 'Speichern';
				listFormSubmitButton.className = 'btn btn-success'; // Setzt Klasse komplett neu
			}
			if (listData) {
				formNameInput.value = listData.name;
				formDescriptionInput.value = listData.description || '';
				// Berücksichtige, dass das Objekt `isPrivate` oder `is_private` haben könnte
				formIsPrivateCheckbox.checked = listData.isPrivate === 1 || listData.is_private === 1;

				setLanguageField(formSourceLanguageSelect, formSourceLangCustomContainer, formSourceLangCustomInput, listData.sourceLanguage);
				setLanguageField(formTargetLanguageSelect, formTargetLangCustomContainer, formTargetLangCustomInput, listData.targetLanguage);
			}
			if (errMsg && errMsg != '') {
				modalErrMsg.innerHTML = errMsg;
				modalErrMsg.className = "alert alert-danger";
            }
			else {
				modalErrMsg.innerHTML = '';
				modalErrMsg.className = "";
            }
			updatePrivacyLabel(formIsPrivateCheckbox, formIsPrivateCheckbox.nextElementSibling); // Label aktualisieren
			listFormModalInstance.show();
		}

		function setLanguageField(selectElement, customContainer, customInput, languageValue) {
			let found = false;
			const originalSelectName = selectElement.dataset.originalName || selectElement.getAttribute('name');
			// Stellen Sie sicher, dass data-original-name gesetzt ist, falls nicht vorhanden
			if (!selectElement.dataset.originalName) {
				selectElement.dataset.originalName = originalSelectName;
			}

			for (let i = 0; i < selectElement.options.length; i++) {
				if (selectElement.options[i].value === languageValue) {
					selectElement.selectedIndex = i;
					found = true;
					break;
				}
			}

			if (!found && languageValue) { // Sprache nicht in der Liste -> "Andere..."
				selectElement.value = 'other';
				customContainer.classList.remove('d-none');
				customInput.value = languageValue;
				customInput.setAttribute('name', originalSelectName);
				selectElement.removeAttribute('name');
			} else { // Sprache in der Liste gefunden oder keine Sprache gesetzt
				customContainer.classList.add('d-none');
				customInput.value = '';
				customInput.removeAttribute('name');
				selectElement.setAttribute('name', originalSelectName);
				if (selectElement.value === 'other' && !languageValue) { // Falls "Andere..." ausgewählt war, aber keine Custom-Sprache mehr da ist
					selectElement.value = ""; // Zurücksetzen auf "Bitte wählen"
				}
			}
		}

		function confirmDeleteList(id, name) {
			document.getElementById('delete_id').value = id;
			document.getElementById('delete_name').textContent = name;
			const deleteModalElement = document.getElementById('deleteListModal');
			if (deleteModalElement) {
				const deleteModalInstance = new bootstrap.Modal(deleteModalElement);
				deleteModalInstance.show();
			}
		}

		function copyList(id, name) {
			document.getElementById('copy_list_id').value = id;
			document.getElementById('copy_list_name').textContent = name;
			document.getElementById('copy_new_name').value = "Kopie von " + name;
			const copyIsPrivateCheckbox = document.getElementById('copy_is_private');
			copyIsPrivateCheckbox.checked = true; // Standard für Kopien: Privat
			updatePrivacyLabel(copyIsPrivateCheckbox, copyIsPrivateCheckbox.nextElementSibling);

			const copyModalElement = document.getElementById('copyListModal');
			if (copyModalElement) {
				const copyModalInstance = new bootstrap.Modal(copyModalElement);
				copyModalInstance.show();
			}
		}

		function setupCustomLanguageHandler(selectElement, customContainer, customInput) {
			const originalName = selectElement.dataset.originalName || selectElement.getAttribute('name');
			if (!selectElement.dataset.originalName) {
				selectElement.dataset.originalName = originalName;
			}

			selectElement.addEventListener('change', function() {
				if (this.value === 'other') {
					customContainer.classList.remove('d-none');
					customInput.setAttribute('name', originalName);
					selectElement.removeAttribute('name');
					customInput.focus();
				} else {
					customContainer.classList.add('d-none');
					customInput.removeAttribute('name');
					customInput.value = '';
					selectElement.setAttribute('name', originalName);
				}
			});
		}

		function updatePrivacyLabel(checkbox, label) {
			const icon = label.querySelector('i');
			// Sicherer auf Textknoten zugreifen
			const textNode = Array.from(label.childNodes).find(node => node.nodeType === Node.TEXT_NODE && node.textContent.trim().length > 0);
			const smallText = label.querySelector('small');

			if (checkbox.checked) {
				icon.classList.remove('bi-unlock-fill');
				icon.classList.add('bi-lock-fill');
				if (textNode) textNode.nodeValue = ' Private Liste ';
			} else {
				icon.classList.remove('bi-lock-fill');
				icon.classList.add('bi-unlock-fill');
				if (textNode) textNode.nodeValue = ' Öffentliche Liste ';
			}
			if (smallText) {
				smallText.innerHTML = checkbox.checked ?
					'Private Listen sind nur für dich sichtbar. Öffentliche Listen können von allen Benutzern gesehen werden.' :
					'Öffentliche Listen können von allen Benutzern gesehen werden. Private Listen sind nur für dich sichtbar.';
			}
		}

		document.addEventListener('DOMContentLoaded', function() {
			// Initialisiere die Haupt-Modal-Instanz hier, nachdem das DOM bereit ist.
			if (listFormModalElement) {
				listFormModalInstance = new bootstrap.Modal(listFormModalElement);
			} else {
				console.error("Das Modal-Element #listFormModal wurde nicht gefunden.");
			}

			// Event-Listener für benutzerdefinierte Sprachen
			if (formSourceLanguageSelect) setupCustomLanguageHandler(formSourceLanguageSelect, formSourceLangCustomContainer, formSourceLangCustomInput);
			if (formTargetLanguageSelect) setupCustomLanguageHandler(formTargetLanguageSelect, formTargetLangCustomContainer, formTargetLangCustomInput);

			// Event-Listener für Privatsphäre-Checkbox im Hauptformular
			if (formIsPrivateCheckbox) {
				formIsPrivateCheckbox.addEventListener('change', function() {
					updatePrivacyLabel(this, this.nextElementSibling);
				});
				// Initialisiere das Label beim Laden der Seite
				updatePrivacyLabel(formIsPrivateCheckbox, formIsPrivateCheckbox.nextElementSibling);
			}

			// Event-Listener für Privatsphäre-Checkbox im Kopier-Modal
			const copyIsPrivateCheckbox = document.getElementById('copy_is_private');
			if (copyIsPrivateCheckbox) {
				copyIsPrivateCheckbox.addEventListener('change', function() {
					updatePrivacyLabel(this, this.nextElementSibling);
				});
				// Initialisiere das Label beim Laden der Seite
				updatePrivacyLabel(copyIsPrivateCheckbox, copyIsPrivateCheckbox.nextElementSibling);
			}

			// Prüfen, ob das Modal nach einem serverseitigen Fehler erneut geöffnet werden soll
			<?php if ($errorMessage): ?>
			// Die PHP-Variablen $modalAction, $reopenModalData und $modalError
			// werden hier direkt ins JavaScript geschrieben.
			// Stelle sicher, dass $reopenModalData als JSON-String korrekt übergeben wird.
			// Und $modalError keine Anführungszeichen enthält, die das JS brechen.
			openListFormModal(
				'create',
				<?php echo json_encode($newList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                '<?= addslashes($errorMessage) ?>'
			);
			<?php endif; ?>
		});
    </script>

<?php
require_once 'footer.php';
?>