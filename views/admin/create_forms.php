
<?php
session_start();

if (!isset($_SESSION['authUser'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../../config/dbconn.php';

$error = '';


include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container">

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    
    <div class="container-fluid bg-light min-vh-100 py-4">

        <div class="container">

            <!-- Page heading -->
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <a href="my_forms.php" class="text-decoration-none text-primary small">
                        <i class="bi bi-arrow-left"></i> My forms
                    </a>

                    <h2 class="fw-bold mt-3 mb-2">Form builder</h2>

                    <p class="text-secondary mb-0">
                        Edit questions, preview, and publish.
                    </p>
                </div>

                <span class="badge bg-warning-subtle text-warning-emphasis mt-3">
                    Draft
                </span>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="row g-4">

                <!-- FORM EDITOR -->
                <div class="col-12 col-lg-8">

                    <form method="POST" action="../../controller/saveFormController.php" id="builderForm">
                        <input type="hidden" name="form_action" id="form_action" value="save">
                        <!-- Form details -->
                        <div class="card border shadow-sm mb-3">
                            <div class="card-body p-4">

                                <div class="mb-3">
                                    <label for="form_title" class="form-label fw-semibold">
                                        Form title
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="form_title"
                                        name="form_title"
                                        maxlength="255"
                                        placeholder="Untitled form"
                                        value="<?= htmlspecialchars($_POST['form_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        required>
                                </div>

                                <div>
                                    <label for="description" class="form-label fw-semibold">
                                        Description
                                    </label>

                                    <textarea
                                        class="form-control"
                                        id="description"
                                        name="description"
                                        rows="3"
                                        placeholder="Form description"><?= htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>

                            </div>
                        </div>
  
                        <!-- Dynamic Sections and Questions -->
                        <div id="sectionsContainer"></div>

                        <div class="d-flex flex-wrap align-items-center gap-3 my-3">
                            <button type="button"
                                    class="btn btn-outline-primary"
                                    id="addSection">
                                <i class="bi bi-folder-plus me-1"></i>
                                Add section
                            </button>

                            <button type="button"
                                    class="btn btn-primary"
                                    id="addQuestion">
                                <i class="bi bi-plus-lg me-1"></i>
                                Add question
                            </button>

                            <span class="small text-secondary">
                                Add sections and organize your questions.
                            </span>
                        </div>


                        <hr>

                        <!-- Actions -->
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <button type="submit" class="btn btn-primary" name="save_form" id="publishForm" value="1">
                                <i class="bi bi-floppy me-1"></i>
                                Save changes
                            </button>
                        </div>

                    </form>
                </div>

                <!-- LIVE PREVIEW -->
                <div class="col-12 col-lg-4">

                    <div class="card border shadow-sm sticky-lg-top"
                        style="top: 1rem;">

                        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                            <span class="small fw-bold text-secondary">
                                <i class="bi bi-eye me-1"></i>
                                LIVE PREVIEW
                            </span>

                            <span class="badge bg-success">Live</span>
                        </div>

                        <div class="card-body border-top border-3 border-primary">
                            <h5 class="fw-bold text-break" id="previewTitle">
                                Untitled form
                            </h5>

                            <p class="text-secondary small mb-0 text-break"
                            id="previewDescription"
                            style="white-space: pre-wrap;"></p>
                        </div>

                        <div id="previewQuestions"></div>

                        <div class="card-footer bg-white text-center p-3">
                            <small class="text-secondary d-block mb-3">
                                <i class="bi bi-eye me-1"></i>
                                Preview only — changes are not published yet.
                            </small>
                        </div>

                    </div>

                </div>

            </div>
        </div>
    </div>



</div>


<script src="../../assets/js/create_forms.js"></script>
<?php include '../includes/footer.php'; ?>
