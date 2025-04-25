<?php
/**
 * Vocabulary lists management page
 *
 * PHP version 8.0
 */

// Include configuration and database class
require_once 'config.php';
require_once 'VocabularyDatabase.php';

// Get database connection
$db = getDbConnection();
require_once 'auth_integration.php';

// Initialize variables
$successMessage = '';
$errorMessage = '';

$vocabDB = new VocabularyDatabase($db);

// Handle list creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $source_language = trim($_POST['source_language'] ?? '');
    $target_language = trim($_POST['target_language'] ?? '');
    $is_private = isset($_POST['is_private']) ? 1 : 0;

    // Validate input
    if (empty($name)) {
        $errorMessage = 'Der Listenname darf nicht leer sein.';
    } else if (empty($source_language)) {
        $errorMessage = 'Die Ausgangssprache darf nicht leer sein.';
    } else if (empty($target_language)) {
        $errorMessage = 'Die Zielsprache darf nicht leer sein.';
    } else {
        // Create list
        $listId = $vocabDB->createList($name, $description, $source_language, $target_language, $_SESSION['user_id'], $is_private);

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
    $is_private = isset($_POST['is_private']) ? 1 : 0;

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
        // Check permission
        if (!userCanEditList($id, $vocabDB)) {
            $errorMessage = 'Du hast keine Berechtigung zum Bearbeiten dieser Liste.';
        } else {
            // Update list
            $success = $vocabDB->updateList($id, $name, $source_language, $target_language, $description, $is_private);

            if ($success) {
                $successMessage = 'Liste "' . htmlspecialchars($name) . '" erfolgreich aktualisiert.';
            } else {
                $errorMessage = 'Fehler beim Aktualisieren der Liste. Möglicherweise existiert bereits eine Liste mit diesem Namen.';
            }
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
        // Check permission
        if (!userCanEditList($id, $vocabDB)) {
            $errorMessage = 'Du hast keine Berechtigung zum Löschen dieser Liste.';
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
}

// Toggle privacy setting
else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_privacy') {
    $id = intval($_POST['id'] ?? 0);
    $is_private = intval($_POST['is_private'] ?? 1);

    // Validate input
    if ($id <= 0) {
        $errorMessage = 'Ungültige Listen-ID.';
    } else {
        // Check permission
        if (!userCanEditList($id, $vocabDB)) {
            $errorMessage = 'Du hast keine Berechtigung zum Ändern der Privatsphäre-Einstellungen dieser Liste.';
        } else {
            // Get list info for update
            $list = $vocabDB->getListById($id);

            if (!$list) {
                $errorMessage = 'Liste nicht gefunden.';
            } else {
                // Update list privacy setting
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

// Get own lists
$ownLists = $vocabDB->getVocabularyListsByUser($_SESSION['user_id']);

// Get public lists from other users
$publicLists = $vocabDB->getPublicVocabularyLists($_SESSION['user_id']);

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
                    <h5 class="card-title mb-0"><i class="bi bi-folder"></i> Meine Vokabellisten</h5>
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
                                <th class="text-center">Privatsphäre</th>
                                <th class="text-center">Aktionen</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (count($ownLists) > 0): ?>
                                <?php foreach ($ownLists as $list): xlog($list); ?>
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
                                            <?php
                                            // Privatsphäre-Einstellung mit Umschaltmöglichkeit
                                            $is_private = isset($list['is_private']) ? $list['is_private'] : 1;
                                            $privacy_badge_class = $is_private ? 'bg-danger' : 'bg-success';
                                            $privacy_icon = $is_private ? 'bi-lock-fill' : 'bi-unlock-fill';
                                            $privacy_text = $is_private ? 'Privat' : 'Öffentlich';
                                            $new_privacy_value = $is_private ? 0 : 1;
                                            ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_privacy">
                                                <input type="hidden" name="id" value="<?= $list['id'] ?>">
                                                <input type="hidden" name="is_private" value="<?= $new_privacy_value ?>">
                                                <button type="submit" class="btn btn-sm badge <?= $privacy_badge_class ?>"
                                                        title="Klicken zum Umschalten">
                                                    <i class="bi <?= $privacy_icon ?> me-1"></i> <?= $privacy_text ?>
                                                </button>
                                            </form>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" onclick='editList(<?= json_encode($list, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>)'>
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

    <!-- Öffentliche Listen anderer Benutzer -->
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
                                        <?php if (!empty($list['username'])): ?>
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($list['username']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">Unbekannt</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="list.php?list_id=<?= $list['id'] ?>" class="btn btn-outline-secondary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="quiz.php?list_id=<?= $list['id'] ?>" class="btn btn-outline-success">
                                                <i class="bi bi-question-circle"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-primary" onclick="copyList(<?= $list['id'] ?>, '<?= htmlspecialchars(addslashes($list['name'])) ?>')">
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
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_private" name="is_private" checked>
                            <label class="form-check-label" for="is_private">
                                <i class="bi bi-lock-fill me-1"></i> Private Liste
                                <small class="form-text d-block text-muted">
                                    Private Listen sind nur für dich sichtbar. Öffentliche Listen können von allen Benutzern gesehen werden.
                                </small>
                            </label>
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
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="edit_is_private" name="is_private">
                            <label class="form-check-label" for="edit_is_private">
                                <i class="bi bi-lock-fill me-1"></i> Private Liste
                                <small class="form-text d-block text-muted">
                                    Private Listen sind nur für dich sichtbar. Öffentliche Listen können von allen Benutzern gesehen werden.
                                </small>
                            </label>
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
                            <input type="checkbox" class="form-check-input" id="copy_is_private" name="is_private" checked>
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
        // Function to prepare edit modal
        function editList(list) {
			document.getElementById('edit_id').value = list.id;
			document.getElementById('edit_name').value = list.name;
			document.getElementById('edit_description').value = list.description;
			document.getElementById('edit_is_private').checked = list.isPrivate === 1;

			// Set source language
			const sourceSelect = document.getElementById('edit_source_language');
			let sourceFound = false;

			for (let i = 0; i < sourceSelect.options.length; i++) {
				if (sourceSelect.options[i].value === list.source_language) {
					sourceSelect.selectedIndex = i;
					sourceFound = true;
					break;
				}
			}

			if (!sourceFound && list.source_language) {
				sourceSelect.value = 'other';
				document.getElementById('edit_source_language_custom_container').classList.remove('d-none');
				document.getElementById('edit_source_language_custom').value = list.source_language;
			}

			// Set target language
			const targetSelect = document.getElementById('edit_target_language');
			let targetFound = false;

			for (let i = 0; i < targetSelect.options.length; i++) {
				if (targetSelect.options[i].value === list.target_language) {
					targetSelect.selectedIndex = i;
					targetFound = true;
					break;
				}
			}

			if (!targetFound && list.target_language) {
				targetSelect.value = 'other';
				document.getElementById('edit_target_language_custom_container').classList.remove('d-none');
				document.getElementById('edit_target_language_custom').value = list.target_language;
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

		// Function to prepare copy modal for public lists
		function copyList(id, name) {
			document.getElementById('copy_list_id').value = id;
			document.getElementById('copy_list_name').textContent = name;
			document.getElementById('copy_new_name').value = "Kopie von " + name;

			const modal = new bootstrap.Modal(document.getElementById('copyListModal'));
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

			// Privacy toggle in list form
			const isPrivateCheckbox = document.getElementById('is_private');
			const isPrivateLabel = isPrivateCheckbox.nextElementSibling;

			isPrivateCheckbox.addEventListener('change', function() {
				updatePrivacyLabel(this, isPrivateLabel);
			});

			// Privacy toggle in edit form
			const editIsPrivateCheckbox = document.getElementById('edit_is_private');
			const editIsPrivateLabel = editIsPrivateCheckbox.nextElementSibling;

			editIsPrivateCheckbox.addEventListener('change', function() {
				updatePrivacyLabel(this, editIsPrivateLabel);
			});

			// Privacy toggle in copy form
			const copyIsPrivateCheckbox = document.getElementById('copy_is_private');
			const copyIsPrivateLabel = copyIsPrivateCheckbox.nextElementSibling;

			copyIsPrivateCheckbox.addEventListener('change', function() {
				updatePrivacyLabel(this, copyIsPrivateLabel);
			});

			// Funktion zum Aktualisieren der Privatsphäre-Label
			function updatePrivacyLabel(checkbox, label) {
				const icon = label.querySelector('i');

				if (checkbox.checked) {
					// Privat
					icon.classList.remove('bi-unlock-fill');
					icon.classList.add('bi-lock-fill');
					label.innerHTML = icon.outerHTML + ' Private Liste' +
						'<small class="form-text d-block text-muted">' +
						'Private Listen sind nur für dich sichtbar. Öffentliche Listen können von allen Benutzern gesehen werden.' +
						'</small>';
				} else {
					// Öffentlich
					icon.classList.remove('bi-lock-fill');
					icon.classList.add('bi-unlock-fill');
					label.innerHTML = icon.outerHTML + ' Öffentliche Liste' +
						'<small class="form-text d-block text-muted">' +
						'Öffentliche Listen können von allen Benutzern gesehen werden. Private Listen sind nur für dich sichtbar.' +
						'</small>';
				}
			}
		});
    </script>

<?php
// Include footer
require_once 'footer.php';
?>