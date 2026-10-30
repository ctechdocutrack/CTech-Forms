
<?php
session_start();

if (!isset($_SESSION['authUser'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../../config/dbconn.php';

$user_id = (int) $_SESSION['authUser']['user_id'];
$form_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$error = '';

// Validate form ID
if ($form_id <= 0) {
    header("Location: forms.php?error=" . urlencode("Invalid form ID."));
    exit();
}

// Load the form owned by the logged-in user
$sql = "SELECT * FROM forms WHERE form_id = $form_id AND created_by = $user_id LIMIT 1";
$sql_run = mysqli_query($conn, $sql);

if (!$sql_run) {
    error_log("Load form failed: " . mysqli_error($conn));
    header("Location: forms.php?error=" . urlencode("Unable to load the form."));
    exit();
}

if (mysqli_num_rows($sql_run) === 0) {
    header("Location: forms.php?error=" . urlencode("Form not found or access denied."));
    exit();
}

$form = mysqli_fetch_assoc($sql_run);

// Load the saved sections
$sections = [];

$section_sql = "
    SELECT section_id, form_id, section_title,
           section_description, section_order
    FROM form_sections
    WHERE form_id = $form_id
    ORDER BY section_order ASC, section_id ASC
";

$section_result = mysqli_query($conn, $section_sql);

if (!$section_result) {
    error_log("Load sections failed: " . mysqli_error($conn));
    $error = "Unable to load the form sections.";
} else {
    while ($section = mysqli_fetch_assoc($section_result)) {
        $sections[] = [
            'section_id' => (int) $section['section_id'],
            'section_title' => $section['section_title'],
            'section_description' => $section['section_description'] ?? '',
            'section_order' => (int) $section['section_order']
        ];
    }
}

// Load the saved questions
$questions = [];

$question_sql = "SELECT * FROM form_questions WHERE form_id = $form_id ORDER BY question_order ASC, question_id ASC";
$question_result = mysqli_query($conn, $question_sql);

if (!$question_result) {
    error_log("Load questions failed: " . mysqli_error($conn));
    $error = "Unable to load the form questions.";
} else {
    while ($question = mysqli_fetch_assoc($question_result)) {
        $question_id = (int) $question['question_id'];

        // Load options belonging to this question
        $options = [];

        $option_sql = "SELECT option_text FROM question_options WHERE question_id = $question_id ORDER BY option_order ASC, option_id ASC";
        $option_result = mysqli_query($conn, $option_sql);

        if ($option_result) {
            while ($option = mysqli_fetch_assoc($option_result)) {
                $options[] = $option['option_text'];
            }
        } else {
            error_log("Load options failed: " . mysqli_error($conn));
            $error = "Unable to load some question options.";
        }

        $questions[] = [
            'question_id' => (int) $question['question_id'],
            'question_title' => $question['question_title'],
            'question_type' => $question['question_type'],
            'is_required' => (int) $question['is_required'],
            'question_order' => (int) $question['question_order'],
            'section_id' => isset($question['section_id'])
                ? (int) $question['section_id']
                : null,
            'options' => $options
        ];
    }
}

// Preserve submitted values if available
$form_title = $_POST['form_title'] ?? $form['form_title'];
$description = $_POST['description'] ?? ($form['description'] ?? '');

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container-fluid bg-light min-vh-100 py-4">
    <div class="container">

        <!-- Page heading -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start mb-4">
            <div>
                <a href="my_forms.php" class="text-decoration-none text-primary small">
                    <i class="bi bi-arrow-left"></i> My forms
                </a>

                <h2 class="fw-bold mt-3 mb-2">Edit form</h2>

                <p class="text-secondary mb-0">
                    Edit questions, preview, and update your form.
                </p>
            </div>
            <?php 
            if ($form['status'] === 'published') {
                echo "<span class='badge bg-success-subtle text-success-emphasis mt-3'>
                        Published
                      </span>";
            } else {
                echo "<span class='badge bg-warning-subtle text-warning-emphasis mt-3'>
                        Draft
                      </span>";
            }
            ?>
        </div>

        <?php 
        if ($error !== '') {
            echo "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                    " . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                  </div>";
        }
        ?>

        <div class="row g-4">
            <!-- FORM EDITOR -->
            <div class="col-12 col-lg-8">
                <form method="POST" action="../../controller/updateFormController.php" id="builderForm">
                    <!-- Existing form ID -->
                    <input type="hidden" name="form_id" value="<?= (int) $form_id ?>">
                    <input type="hidden" name="form_action" id="form_action" value="save">
                    <!-- Form details -->
                    <div class="card border shadow-sm mb-3">
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <label for="form_title" class="form-label fw-semibold">Form title</label>
                                <input type="text" class="form-control" id="form_title" name="form_title" maxlength="255" placeholder="Untitled form" value="<?= htmlspecialchars($form_title, ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div>
                                <label for="description" class="form-label fw-semibold">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Form description"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic sections and questions -->
                    <div id="sectionsContainer"></div>

                    <div class="d-flex flex-wrap align-items-center gap-3 my-3">
                        <button type="button" class="btn btn-primary" id="addQuestion">
                            <i class="bi bi-plus-lg me-1"></i> Add question
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="addSection">
                            <i class="bi bi-plus-lg me-1"></i> Add section
                        </button>
                        <span class="small text-secondary">
                            Tip: use the arrows to reorder questions.
                        </span>
                    </div>

                    <hr>

                    <!-- Actions -->
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="submit" class="btn btn-primary" name="save_form" value="1" id="publishForm">
                            <i class="bi bi-floppy me-1"></i> Save changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- LIVE PREVIEW -->
            <div class="col-12 col-lg-4">
                <div class="card border shadow-sm sticky-lg-top" style="top: 1rem;">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                        <span class="small fw-bold text-secondary">
                            <i class="bi bi-eye me-1"></i> LIVE PREVIEW
                        </span>
                        <span class="badge bg-success">Live</span>
                    </div>
                    <div class="card-body border-top border-3 border-primary">
                        <h5 class="fw-bold text-break" id="previewTitle">
                            <?= htmlspecialchars($form_title, ENT_QUOTES, 'UTF-8') ?>
                        </h5>
                        <p class="text-secondary small mb-0 text-break" id="previewDescription" style="white-space: pre-wrap;">
                            <?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>

                    <div id="previewQuestions"></div>

                  <div class="card-footer bg-white text-center p-3">
                    <small class="text-secondary d-block mb-3">
                        <i class="bi bi-eye me-1"></i>
                        Preview only — changes are not published yet.
                    </small>
                    <form method="POST" action="../../controller/publishFormController.php" onsubmit="return confirm('Publish this form?');">
                        <input type="hidden" name="form_id" value="<?= (int) $form_id ?>">
                        <button type="submit" name="publish_form" value="1" class="btn btn-success w-100">
                            <i class="bi bi-send me-1"></i>
                            Publish form
                        </button>
                    </form>
                  </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Existing saved questions for the form builder JavaScript -->
<script>
window.savedSections = <?= json_encode(
    $sections,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

window.savedQuestions = <?= json_encode(
    $questions,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

window.editFormId = <?= (int) $form_id ?>;
</script>

<script src="../../assets/js/create_forms.js"></script>
<?php include '../includes/footer.php'; ?>
