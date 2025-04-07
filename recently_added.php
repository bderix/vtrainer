<?php
/**
 * Recently added vocabulary component
 *
 * This file displays the most recently added vocabulary items
 * and can be included on multiple pages.
 *
 * PHP version 8.0
 */

// Check if database and vocabDB are already defined
if (!isset($db) || !isset($vocabDB)) {
	// Include configuration and database class if not already included
	require_once 'config.php';
	require_once 'VocabularyDatabase.php';

	// Get database connection
	$db = getDbConnection();
	$vocabDB = new VocabularyDatabase($db);
}

// Get recently added vocabulary (last 5 entries)
$recentVocabulary = $vocabDB->getRecentVocabulary(5);
?>

        <div class="card card-hover">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-plus-circle"></i> Zuletzt hinzugefügt</h5>
            </div>
            <div class="card-body p-2">
				<?php if (count($recentVocabulary) > 0): ?>
                    <div class="list-group list-group-flush">
						<?php foreach ($recentVocabulary as $vocab): ?>
                            <div class="list-group-item p-1 importance-<?= $vocab['importance'] ?>" data-vocab-id="<?= $vocab['id'] ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1" data-vocab-words><?= htmlspecialchars($vocab['word_source']) ?> - <?= htmlspecialchars($vocab['word_target']) ?></h6>
                                    <div>
                                        <small class="text-muted me-2">
                                            <i class="bi bi-star-fill text-warning"></i>
                                            <span data-vocab-importance><?= $vocab['importance'] ?></span>
                                        </small>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editVocabulary(<?= $vocab['id'] ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    onclick="confirmDeleteVocab(<?= $vocab['id'] ?>, '<?= htmlspecialchars(addslashes($vocab['word_source'])) ?>', '<?= htmlspecialchars(addslashes($vocab['word_target'])) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
								<?php if (!empty($vocab['example_sentence'])): ?>
                                    <small class="text-muted" data-vocab-example><?= htmlspecialchars($vocab['example_sentence']) ?></small>
								<?php else: ?>
                                    <small class="text-muted d-none" data-vocab-example></small>
								<?php endif; ?>
                            </div>
						<?php endforeach; ?>
                    </div>
				<?php else: ?>
                    <p class="card-text text-center">Noch keine Vokabeln vorhanden. <a href="add.php">Jetzt hinzufügen</a></p>
				<?php endif; ?>
            </div>
        </div>
