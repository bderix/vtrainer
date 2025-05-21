<?php if (isset($_SESSION['quiz_result'])): ?>
	<div class="card mb-4 <?= $_SESSION['quiz_result']['is_correct'] ? 'border-success' : 'border-danger' ?>">
		<div class="card-header <?= $_SESSION['quiz_result']['is_correct'] ? 'bg-success' : 'bg-danger' ?> text-white">
			<h5 class="card-title mb-0">
				<?php if ($_SESSION['quiz_result']['is_correct']): ?>
					<i class="bi bi-check-circle-fill"></i> Richtig!
				<?php else: ?>
					<i class="bi bi-x-circle-fill"></i> Leider falsch
				<?php endif; ?>

				<?php if (isset($_SESSION['quiz_result']['self_evaluated']) && $_SESSION['quiz_result']['self_evaluated']): ?>
					<span class="badge bg-outline-secondary float-end">
                        <i class="bi bi-hand-thumbs-<?= $_SESSION['quiz_result']['is_correct'] ? 'up' : 'down' ?>"></i>
                        Selbstbewertung
                    </span>
				<?php endif; ?>
			</h5>
		</div>

		<div class="card-body">
			<div class="row">
				<!-- Vokabel-Informationen -->
				<div class="col-md-7">
					<div class="mb-3">
						<h5>
							<?= htmlspecialchars($_SESSION['quiz_vocab']['word_source']) ?>
							<i class="bi bi-arrow-right text-primary"></i>
							<?= htmlspecialchars($_SESSION['quiz_vocab']['word_target']) ?>
						</h5>

						<?php if (!empty($_SESSION['quiz_vocab']['example_sentence'])): ?>
							<div class="border-start border-info border-3 ps-3 fst-italic text-muted small">
								<?= nl2br(htmlspecialchars($_SESSION['quiz_vocab']['example_sentence'])) ?>
							</div>
						<?php endif; ?>
					</div>

					<?php if (!$_SESSION['quiz_result']['is_correct']): ?>
						<div class="alert alert-danger">
							<div class="small text-muted">Deine Antwort:</div>
							<div class="fw-bold"><?= htmlspecialchars($_SESSION['quiz_result']['user_answer']) ?></div>

							<div class="small text-muted mt-2">Richtige Antwort:</div>
							<div class="fw-bold"><?= htmlspecialchars($_SESSION['quiz_result']['correct_answer']) ?></div>
						</div>
					<?php endif; ?>
				</div>


				<!-- Vokabel-Metadaten -->
				<div class="col-md-5">
					<div class="card bg-light h-100">
						<div class="card-body">
							<h6 class="card-subtitle mb-3 text-muted border-bottom pb-2">
								<i class="bi bi-info-circle"></i> Details
							</h6>

							<div class="row mb-2">
								<div class="col-5 text-muted">Wichtigkeit:</div>
								<div class="col-7">
                                    <span class="badge bg-<?= Helper::getImportanceBadgeColor($_SESSION['quiz_vocab']['importance']) ?>">
                                        <?= $_SESSION['quiz_vocab']['importance'] ?>
                                    </span>
								</div>
							</div>

							<?php if (!empty($_SESSION['quiz_vocab']['list_name'])): ?>
								<div class="row mb-2">
									<div class="col-5 text-muted">Liste:</div>
									<div class="col-7">
										<span class="badge bg-info"><?= htmlspecialchars($_SESSION['quiz_vocab']['list_name']) ?></span>
									</div>
								</div>
							<?php endif; ?>

							<div class="row mb-2">
								<div class="col-5 text-muted">Hinzugefügt:</div>
								<div class="col-7 small">
									<?= date('d.m.Y', strtotime($_SESSION['quiz_vocab']['date_added'])) ?>
								</div>
							</div>

							<?php if (isset($_SESSION['quiz_vocab']['stats'])): ?>
								<div class="row mb-2">
									<div class="col-5 text-muted">Erfolgsrate:</div>
									<div class="col-7">
										<div class="progress" style="height: 15px;">
											<div class="progress-bar <?= Helper::getProgressBarColor($_SESSION['quiz_vocab']['stats']['total_success_rate']) ?>"
												 role="progressbar" style="width: <?= $_SESSION['quiz_vocab']['stats']['total_success_rate'] ?>%;"
												 aria-valuenow="<?= $_SESSION['quiz_vocab']['stats']['total_success_rate'] ?>" aria-valuemin="0" aria-valuemax="100">
												<?= $_SESSION['quiz_vocab']['stats']['total_success_rate'] ?>%
											</div>
										</div>
										<div class="small text-muted mt-1">
											<?= $_SESSION['quiz_vocab']['stats']['correct_count'] ?> von <?= $_SESSION['quiz_vocab']['stats']['attempt_count'] ?> richtig
										</div>
									</div>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="card-footer bg-white d-flex justify-content-between">
			<a onclick="editVocabulary(<?= $_SESSION['quiz_vocab']['id']; ?>)" class="btn btn-sm btn-outline-primary">
				<i class="bi bi-pencil"></i> Bearbeiten
			</a>
			<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="alert">
                <i class="bi bi-x-circle"></i> Schließen
			</button>
		</div>
	</div>
	<?php unset($_SESSION['quiz_result']); ?>
<?php endif; ?>