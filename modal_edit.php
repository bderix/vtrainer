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
                            <label for="edit_word_source" id="source_language" class="form-label">.<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_word_source" name="edit_word_source" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_word_target" id="target_language" class="form-label">.<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_word_target" name="edit_word_target" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_list_id" class="form-label">Liste <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_list_id" name="edit_list_id" required>
                            <!-- Listen werden dynamisch per JavaScript gefüllt -->
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_example_sentence" class="form-label">Beispielsatz</label>
                        <textarea class="form-control" id="edit_example_sentence" name="edit_example_sentence" rows="3"></textarea>
                    </div>


                    <div class="mb-3">
                        <label class="form-label">Wichtigkeit <span class="text-danger">*</span></label>
                        <div class="d-flex flex-wrap gap-2">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="edit_importance" id="edit_importance1" value="1">
                                <label class="form-check-label" for="edit_importance1">
                                    <span class="badge bg-danger">1</span> Niedrig
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="edit_importance" id="edit_importance2" value="2">
                                <label class="form-check-label" for="edit_importance3">
                                    <span class="badge bg-primary">2</span> Mittel
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="edit_importance" id="edit_importance3" value="3">
                                <label class="form-check-label" for="edit_importance3">
                                    <span class="badge bg-success">3</span> Hoch
                                </label>
                            </div>
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