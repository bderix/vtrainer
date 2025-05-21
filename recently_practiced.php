<?php
/**
 * Recently practiced vocabulary component
 *
 * This file displays the most recently practiced vocabulary items
 * and can be included where needed.
 *
 * PHP version 8.0
 */

$recentlyPracticed = $app->userListen->getRecentlyPracticed();
xlog($recentlyPracticed);

?>

	<div class="card card-hover">
		<div class="card-header bg-success text-white">
			<h5 class="card-title mb-0"><i class="bi bi-clock-history"></i> Zuletzt geübt</h5>
		</div>
		<div class="card-body p-2">
			<?php if (count($recentlyPracticed) > 0): ?>
				<div class="list-group list-group-flush">
					<?php foreach ($recentlyPracticed as $vocab): ?>
						<div class="list-group-item p-1" data-vocab-id="<?= $vocab['id'] ?>">
							<div class="d-flex w-100 justify-content-between align-items-center">
								<div>
									<h6 class="mb-1" data-vocab-words><?= htmlspecialchars($vocab['word_source']) ?> - <?= htmlspecialchars($vocab['word_target']) ?></h6>
									<small class="text-muted" data-vocab-stats>Zuletzt: <?= date('d.m.Y H:i', strtotime($vocab['last_practiced'])) ?></small>
								</div>
								<div class="d-flex align-items-center">
									<small class="text-muted me-3" data-vocab-success>
										<?= $vocab['correct_count'] ?>/<?= $vocab['total_attempts'] ?>
										(<?= round(($vocab['correct_count'] / $vocab['total_attempts']) * 100) ?>%)
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
						</div>
					<?php endforeach; ?>
				</div>
			<?php else: ?>
				<p class="card-text text-center">Noch keine Abfragen durchgeführt. <a href="quiz.php">Jetzt üben</a></p>
			<?php endif; ?>
		</div>
	</div>