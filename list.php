<?php
/**
 * Vocabulary list page
 *
 * PHP version 8.0
 */

// Include configuration and database class
require_once 'config.php';
require_once 'VocabularyDatabase.php';

$vocabDB = $app->vocabDB;
$vtrequest = $app->request;

// Handle filters
$importanceFilter = isset($_GET['importance']) ? array_map('intval', (array)$_GET['importance']) : [];
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'date_added';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'DESC';

xlog($_SESSION);
$listId = $app->getListId();
if (empty($listId)) $vtrequest->redirect('lists');

$currentList = $vocabDB->getListById($listId);
xlog($currentList);

if (empty($currentList)) {
	$vtrequest->delSessionValue('selected_list_id');
	$vtrequest->redirect('lists');
}

if ($currentList->userId == $_SESSION['user_id']) $ownList = true;
else $ownList = false;

if ($ownList) {
	$lists = $vocabDB->getVocabularyListsByUser($_SESSION['user_id']); // alle Listen um die Listen auswaehlen zu koennen
}
else {
	// $tmp = new UserAuthentication($db);
    $currentListUser = $auth->loadUserById($currentList->userId);
    xlog($currentListUser);
}

// $currentList = array_filter($lists, function ($item) use ($listId) {
// 	return $item['id'] === $listId;
// });
// $currentList = array_shift($currentList);
;

// Get filtered vocabulary list
$vocabulary = $vocabDB->getVocabularyByList($listId, $importanceFilter, $searchTerm, $sortBy, $sortOrder);
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

// Include header
require_once 'header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card card-hover">
            <div class="card-header <?=$ownList ? 'bg-primary' : 'bg-success' ?> text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
					<?php if ($ownList) : ?>
                    <i class="bi bi-card-list"></i>
                    Vokabelliste: <?= htmlspecialchars($currentList->name) ?>
					<?php endif; ?>
					<?php if (!$ownList) : ?>
                    <i class="bi bi-globe"></i> Öffentliche Liste <?= htmlspecialchars($currentList->name) ?>
					<?php endif; ?>
                </h5>
                <div>
                    <a href="add.php" class="btn btn-sm btn-light me-2">
                        <i class="bi bi-plus-circle"></i> Neue Vokabel
                    </a>
                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
            </div>

            <div class="card-body">
                <?php if ($ownList and count($lists) > 1) : ?>
                <!-- List selection dropdown -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="list_selector" class="form-label">Liste auswählen:</label>
                        <select class="form-select" id="list_selector" onchange="changeList(this.value)">
							<?php foreach ($lists as $list): ?>
                                <option value="<?= $list->id ?>" <?= $listId === $list->id ? 'selected' : '' ?>>
									<?= htmlspecialchars($list->name) ?>
									<?php if ($list->id == 1): ?>(Standard)<?php endif; ?>
                                </option>
							<?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Filters (collapsible) -->
                <div class="collapse <?= (!empty($searchTerm) || !empty($importanceFilter)) ? 'show' : '' ?>" id="filterCollapse">
                    <form method="get" action="list.php" class="mb-4">
                        <input type="hidden" name="list_id" value="<?= $listId ?>">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" class="form-control" name="search" placeholder="Suche..."
                                           value="<?= htmlspecialchars($searchTerm) ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" name="importance[]" multiple>
                                    <option value="1" <?= in_array(1, $importanceFilter) ? 'selected' : '' ?>>Wichtigkeit 1</option>
                                    <option value="2" <?= in_array(2, $importanceFilter) ? 'selected' : '' ?>>Wichtigkeit 2</option>
                                    <option value="3" <?= in_array(3, $importanceFilter) ? 'selected' : '' ?>>Wichtigkeit 3</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <div class="d-flex">
                                    <button type="submit" class="btn btn-primary me-2">Filter anwenden</button>
									<?php if (!empty($searchTerm) || !empty($importanceFilter)): ?>
                                        <a href="list.php?list_id=<?= $listId ?>" class="btn btn-outline-secondary">Filter zurücksetzen</a>
									<?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                        <input type="hidden" name="order" value="<?= htmlspecialchars($sortOrder) ?>">
                    </form>
                </div>

                <!-- Results -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                        <tr>
                            <th>
                                <a href="<?= getSortUrl('word_source', $sortBy, $sortOrder) ?>" class="text-decoration-none">
									<?= htmlspecialchars($currentList->sourceLanguage) ?> <?= getSortIcon('word_source', $sortBy, $sortOrder) ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortUrl('word_target', $sortBy, $sortOrder) ?>" class="text-decoration-none">
									<?= htmlspecialchars($currentList->targetLanguage) ?> <?= getSortIcon('word_target', $sortBy, $sortOrder) ?>
                                </a>
                            </th>
                            <th>Notiz</th>
                            <th class="text-center">
    <span data-bs-toggle="tooltip" data-bs-placement="top" title="Erfolgsquote in den Abfragen">
        Erfolgsquote <i class="bi bi-info-circle text-muted"></i>
    </span>
                            </th>
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

                            <?php if ($ownList) : ?>
                            <th class="text-center">Aktionen</th>
                            <?php endif; ?>
                        </tr>
                        </thead>

                        <tbody>
						<?php if ($totalCount > 0): ?>
							<?php foreach ($vocabulary as $vocab) :
                                xlog($vocab);
								$stats = $vocabDB->getVocabularyQuizStats($vocab->id);
                            ?>
                                <tr class="importance-<?= $vocab->importance ?>" data-vocab-id="<?= $vocab->id ?>">
                                    <td data-vocab-source><?= htmlspecialchars($vocab->wordSource) ?></td>
                                    <td data-vocab-target><?= htmlspecialchars($vocab->wordTarget) ?></td>
                                    <td data-vocab-example class="fst-italic text-muted">
										<?php if (!empty($vocab->example_sentence)): ?>
											<?= htmlspecialchars($vocab->example_sentence) ?>
										<?php else: ?>
                                            <span class="text-muted fst-italic"></span>
										<?php endif; ?>
                                    </td>
                                    <td class="text-center">
										<?php if ($stats['attempt_count'] > 0): ?>
                                            <div class="progress" style="height: 24px;">
                                                <div class="progress-bar <?= Helper::getProgressBarColor($stats['total_success_rate']) ?>"
                                                     role="progressbar"
                                                     style="width: <?= $stats['total_success_rate'] ?>%;"
                                                     aria-valuenow="<?= $stats['total_success_rate'] ?>"
                                                     aria-valuemin="0"
                                                     aria-valuemax="100">
													<?= $stats['total_success_rate'] ?>%
                                                </div>
                                            </div>
                                            <small class="text-muted mt-1 d-block">
												<?= $stats['correct_count'] ?>/<?= $stats['attempt_count'] ?> Versuche
                                            </small>
										<?php else: ?>
                                            <span class="text-muted">Noch nicht geübt</span>
										<?php endif; ?>
                                    </td>

                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center">
											<?php if ($ownList) : ?>
                                            <button type="button" onclick="updateImportance(<?= $vocab->id ?>, 'decrease', this)" class="btn btn-sm btn-link p-0 <?= $vocab->importance <= 1 ? 'disabled' : '' ?>"
												<?= $vocab->importance <= 1 ? 'aria-disabled="true"' : '' ?> data-vocab-importance-btn="decrease">
                                                <i class="bi bi-dash-circle"></i>
                                            </button>
                                            <?php endif; ?>
                                            <span id="importance-badge-<?= $vocab->id ?>"
                                                  class="badge rounded-pill bg-<?= Helper::getImportanceBadgeColor($vocab->importance) ?> mx-2 px-3 py-2"
                                                  data-vocab-importance>
                                                    <?= $vocab->importance ?>
                                                </span>
											<?php if ($ownList) : ?>
                                            <button type="button" onclick="updateImportance(<?= $vocab->id ?>, 'increase', this)" class="btn btn-sm btn-link p-0  <?= $vocab->importance >= 5 ? 'disabled' : '' ?>"
												<?= $vocab->importance >= 5 ? 'aria-disabled="true"' : '' ?> data-vocab-importance-btn="increase">
                                                <i class="bi bi-plus-circle"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td class="text-center" data-vocab-date>
										<?= date('d.m.Y', strtotime($vocab->dateAdded)) ?>
                                    </td>


									<?php if ($ownList) : ?>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" onclick="editVocabulary(<?= $vocab->id ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <a href="quiz.php?vocab_id=<?= $vocab->id ?>" class="btn btn-outline-success">
                                                    <i class="bi bi-question-circle"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger delete-vocab-btn"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#confirmDeleteModal"
                                                        data-vocab-id="<?= $vocab->id ?>"
                                                        data-vocab-source="<?= htmlspecialchars($vocab->wordSource, ENT_QUOTES, 'UTF-8') ?>"
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                            <!-- Das spezifische Modal wurde hier entfernt -->
                                        </td>

                                    <?php endif; ?>
                                </tr>
							<?php endforeach; ?>
						<?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <p class="mb-0">Keine Vokabeln gefunden.</p>
									<?php if (!empty($searchTerm) || !empty($importanceFilter)): ?>
                                        <a href="list.php?list_id=<?= $listId ?>" class="btn btn-sm btn-outline-secondary mt-2">Filter zurücksetzen</a>
									<?php else: ?>
                                        <a href="add.php?list_id=<?= $listId ?>" class="btn btn-sm btn-primary mt-2">Erste Vokabel hinzufügen</a>
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
                        <a href="quiz.php?filtered=1<?= !empty($importanceFilter) ? '&importance[]=' . implode('&importance[]=', $importanceFilter) : '' ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>&list_id=<?= $listId ?>" class="btn btn-success">
                            <i class="bi bi-question-circle"></i> Diese Vokabeln abfragen
                        </a>
					<?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>


<?php
// Include modals and footer
require_once 'modal_edit.php';
require_once 'modal_delete.php';
require_once 'footer.php';
?>

<script src="vocab_edit.js"></script>
<script>
	// Function to change selected list
	function changeList(listId) {
		window.location.href = 'list.php?list_id=' + listId;
	}

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
		if (action === 'increase' && currentImportance < 3) {
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

</script>
