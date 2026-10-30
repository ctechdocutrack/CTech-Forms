<?php
session_start();

require_once '../../config/dbconn.php';

$form_token = trim($_GET['token'] ?? '');

$form = null;
$form_id = 0;
$sections = [];
$questionsBySection = [];
$questionCount = 0;

if ($form_token !== '') {
    // Load form securely using its token.
    $formStmt = mysqli_prepare(
        $conn,
        "SELECT * FROM forms WHERE form_token = ? LIMIT 1"
    );

    if ($formStmt) {
        mysqli_stmt_bind_param($formStmt, 's', $form_token);
        mysqli_stmt_execute($formStmt);
        $formResult = mysqli_stmt_get_result($formStmt);
        $form = mysqli_fetch_assoc($formResult) ?: null;
        mysqli_stmt_close($formStmt);
    }

    if ($form !== null) {
        $form_id = (int) $form['form_id'];

        // Load sections in their saved order.
        $sectionStmt = mysqli_prepare(
            $conn,
            "SELECT section_id, section_title, section_description,
                    section_order
             FROM form_sections
             WHERE form_id = ?
             ORDER BY section_order ASC, section_id ASC"
        );

        if (!$sectionStmt) {
            error_log('Load sections failed: ' . mysqli_error($conn));
            exit('Unable to load the form sections.');
        }

        mysqli_stmt_bind_param($sectionStmt, 'i', $form_id);
        mysqli_stmt_execute($sectionStmt);
        $sectionResult = mysqli_stmt_get_result($sectionStmt);

        while ($section = mysqli_fetch_assoc($sectionResult)) {
            $sections[(int) $section['section_id']] = $section;
        }

        mysqli_stmt_close($sectionStmt);

        // Support older forms that do not have saved sections.
        if (empty($sections)) {
            $sections[0] = [
                'section_id' => 0,
                'section_title' => 'Section 1',
                'section_description' => '',
                'section_order' => 1
            ];
        }

        // Initialize question groups.
        foreach ($sections as $sectionId => $section) {
            $questionsBySection[$sectionId] = [];
        }

        // Load questions, including their section assignment.
        $questionStmt = mysqli_prepare(
            $conn,
            "SELECT question_id, section_id, question_title,
                    question_type, is_required, question_order
             FROM form_questions
             WHERE form_id = ?
             ORDER BY question_order ASC, question_id ASC"
        );

        if (!$questionStmt) {
            error_log('Load questions failed: ' . mysqli_error($conn));
            exit('Unable to load the form questions.');
        }

        mysqli_stmt_bind_param($questionStmt, 'i', $form_id);
        mysqli_stmt_execute($questionStmt);
        $questionResult = mysqli_stmt_get_result($questionStmt);

        // Use the first section for questions with an invalid section ID.
        $firstSectionId = (int) array_key_first($sections);

        while ($question = mysqli_fetch_assoc($questionResult)) {
            $questionId = (int) $question['question_id'];
            $sectionId = (int) ($question['section_id'] ?? 0);

            if (!array_key_exists($sectionId, $questionsBySection)) {
                $sectionId = $firstSectionId;
            }

            // Load options for this question.
            $options = [];

            $optionStmt = mysqli_prepare(
                $conn,
                "SELECT option_text
                 FROM question_options
                 WHERE question_id = ?
                 ORDER BY option_order ASC, option_id ASC"
            );

            if (!$optionStmt) {
                error_log('Load options failed: ' . mysqli_error($conn));
                exit('Unable to load the question options.');
            }

            mysqli_stmt_bind_param($optionStmt, 'i', $questionId);
            mysqli_stmt_execute($optionStmt);
            $optionResult = mysqli_stmt_get_result($optionStmt);

            while ($option = mysqli_fetch_assoc($optionResult)) {
                $options[] = $option['option_text'];
            }

            mysqli_stmt_close($optionStmt);

            $question['options'] = $options;
            $questionsBySection[$sectionId][] = $question;
            $questionCount++;
        }

        mysqli_stmt_close($questionStmt);
    } else {
        http_response_code(404);
    }
}

$totalSections = count($sections);
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/navbar.php'; ?>

<style>
    .form-section-page {
        display: none;
    }

    .form-section-page.active {
        display: block;
    }

    .section-progress {
        height: 6px;
        border-radius: 10px;
        overflow: hidden;
        background-color: #e9ecef;
    }

    .section-progress-bar {
        height: 100%;
        width: 0;
        background-color: var(--bs-primary);
        transition: width 0.25s ease;
    }

    .question-card {
        scroll-margin-top: 100px;
    }

    .question-error {
        color: #dc3545;
        font-size: 0.875rem;
        margin-top: 8px;
    }
</style>

<div class="container-fluid bg-light min-vh-100 py-4">
    <div class="container" style="max-width: 800px;">

        <?php if ($form === null): ?>

            <div class="card border-0 shadow-sm mt-5">
                <div class="card-body text-center p-5">
                    <i class="bi bi-file-earmark-x display-4 text-secondary"></i>
                    <h3 class="fw-bold mt-3">Form unavailable</h3>
                    <p class="text-secondary mb-0">
                        This form does not exist or is unavailable.
                    </p>
                </div>
            </div>

        <?php else: ?>

            <!-- Form title and description -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-md-5 border-top border-4 border-primary">

                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show"
                             role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <strong>Response Submitted!</strong>
                            <?= htmlspecialchars(
                                $_GET['success'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                            <button type="button"
                                    class="btn-close"
                                    data-bs-dismiss="alert"
                                    aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show"
                             role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= htmlspecialchars(
                                $_GET['error'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                            <button type="button"
                                    class="btn-close"
                                    data-bs-dismiss="alert"
                                    aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <h1 class="h2 fw-bold text-break">
                        <?= htmlspecialchars(
                            $form['form_title'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </h1>

                    <?php if (!empty($form['description'])): ?>
                        <p class="text-secondary mb-0 text-break"
                           style="white-space: pre-wrap;"><?= htmlspecialchars(
                               $form['description'],
                               ENT_QUOTES,
                               'UTF-8'
                           ) ?></p>
                    <?php endif; ?>

                    <hr>

                    <small class="text-secondary">
                        <span class="text-danger">*</span>
                        Required question
                    </small>
                </div>
            </div>

            <?php if ($questionCount === 0): ?>

                <div class="alert alert-info">
                    This form has no questions yet.
                </div>

            <?php else: ?>

                <!-- Section progress -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between mb-2">
                            <small class="text-secondary"
                                   id="sectionCounter">
                                Section 1 of <?= $totalSections ?>
                            </small>

                            <small class="text-secondary"
                                   id="sectionPercent">
                                <?= $totalSections === 1 ? '100%' : '0%' ?>
                            </small>
                        </div>

                        <div class="section-progress">
                            <div class="section-progress-bar"
                                 id="sectionProgressBar"></div>
                        </div>
                    </div>
                </div>

                <form id="responseForm"
                      method="POST"
                      action="../../controller/responseController.php"
                      novalidate>

                    <input type="hidden"
                           name="form_token"
                           value="<?= htmlspecialchars(
                               $form['form_token'],
                               ENT_QUOTES,
                               'UTF-8'
                           ) ?>">

                    <?php
                    $sectionIndex = 0;
                    $questionNumber = 0;
                    ?>

                    <?php foreach ($sections as $sectionId => $section): ?>

                        <div class="form-section-page <?= $sectionIndex === 0 ? 'active' : '' ?>"
                             data-section-index="<?= $sectionIndex ?>">

                            <!-- Section title and description -->
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-body p-4">
                                    <h2 class="h4 fw-bold text-break">
                                        <?= htmlspecialchars(
                                            $section['section_title'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </h2>

                                    <?php if (!empty($section['section_description'])): ?>
                                        <p class="text-secondary mb-0 text-break"
                                           style="white-space: pre-wrap;"><?= htmlspecialchars(
                                               $section['section_description'],
                                               ENT_QUOTES,
                                               'UTF-8'
                                           ) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php foreach ($questionsBySection[$sectionId] as $question): ?>

                                <?php
                                $questionNumber++;
                                $questionId = (int) $question['question_id'];
                                $required = (int) $question['is_required'] === 1;
                                $inputName = "answers[$questionId]";
                                $questionType = $question['question_type'];
                                ?>

                                <div class="card border-0 shadow-sm mb-3 question-card"
                                     data-required="<?= $required ? '1' : '0' ?>"
                                     data-question-type="<?= htmlspecialchars(
                                         $questionType,
                                         ENT_QUOTES,
                                         'UTF-8'
                                     ) ?>">

                                    <div class="card-body p-4">

                                        <div class="form-label fw-semibold mb-3">
                                            <?= $questionNumber ?>.
                                            <?= htmlspecialchars(
                                                $question['question_title'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                            <?php if ($required): ?>
                                                <span class="text-danger">*</span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($questionType === 'short_answer'): ?>

                                            <input
                                                type="text"
                                                class="form-control"
                                                name="<?= $inputName ?>"
                                                maxlength="2000"
                                                placeholder="Your answer">

                                        <?php elseif ($questionType === 'paragraph'): ?>

                                            <textarea
                                                class="form-control"
                                                name="<?= $inputName ?>"
                                                rows="4"
                                                maxlength="10000"
                                                placeholder="Your answer"></textarea>

                                        <?php elseif ($questionType === 'multiple_choice'): ?>

                                            <?php if (count($question['options']) > 0): ?>

                                                <?php foreach ($question['options'] as $optionIndex => $option): ?>

                                                    <div class="form-check mb-2">
                                                        <input
                                                            class="form-check-input"
                                                            type="radio"
                                                            name="<?= $inputName ?>"
                                                            id="q<?= $questionId ?>_o<?= $optionIndex ?>"
                                                            value="<?= htmlspecialchars(
                                                                $option,
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>">

                                                        <label
                                                            class="form-check-label"
                                                            for="q<?= $questionId ?>_o<?= $optionIndex ?>">
                                                            <?= htmlspecialchars(
                                                                $option,
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>
                                                        </label>
                                                    </div>

                                                <?php endforeach; ?>

                                            <?php else: ?>

                                                <p class="text-secondary small mb-0">
                                                    No options available.
                                                </p>

                                            <?php endif; ?>

                                        <?php elseif ($questionType === 'checkboxes'): ?>

                                            <?php if (count($question['options']) > 0): ?>

                                                <?php foreach ($question['options'] as $optionIndex => $option): ?>

                                                    <div class="form-check mb-2">
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            name="<?= $inputName ?>[]"
                                                            id="q<?= $questionId ?>_o<?= $optionIndex ?>"
                                                            value="<?= htmlspecialchars(
                                                                $option,
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>">

                                                        <label
                                                            class="form-check-label"
                                                            for="q<?= $questionId ?>_o<?= $optionIndex ?>">
                                                            <?= htmlspecialchars(
                                                                $option,
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>
                                                        </label>
                                                    </div>

                                                <?php endforeach; ?>

                                                <?php if ($required): ?>
                                                    <div class="form-text">
                                                        Please select at least one option.
                                                    </div>
                                                <?php endif; ?>

                                            <?php else: ?>

                                                <p class="text-secondary small mb-0">
                                                    No options available.
                                                </p>

                                            <?php endif; ?>

                                        <?php elseif ($questionType === 'dropdown'): ?>

                                            <select
                                                class="form-select"
                                                name="<?= $inputName ?>">

                                                <option value="">
                                                    Choose an option
                                                </option>

                                                <?php foreach ($question['options'] as $option): ?>

                                                    <option value="<?= htmlspecialchars(
                                                        $option,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>">
                                                        <?= htmlspecialchars(
                                                            $option,
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>
                                                    </option>

                                                <?php endforeach; ?>

                                            </select>

                                        <?php endif; ?>

                                        <div class="question-error d-none"
                                             aria-live="polite">
                                            This question is required.
                                        </div>

                                    </div>
                                </div>

                            <?php endforeach; ?>

                            <!-- Navigation buttons -->
                            <div class="d-flex justify-content-between align-items-center gap-3 mb-5">

                                <button
                                    type="button"
                                    class="btn btn-outline-secondary back-button"
                                    <?= $sectionIndex === 0 ? 'disabled' : '' ?>>
                                    <i class="bi bi-arrow-left me-1"></i>
                                    Back
                                </button>

                                <div class="d-flex gap-2">

                                    <?php if ($form['status'] === 'published' && $sectionIndex === $totalSections - 1): ?>

                                        <button type="reset"
                                                class="btn btn-outline-secondary">
                                            Clear answers
                                        </button>

                                        <button
                                            type="submit"
                                            name="submit_response"
                                            value="1"
                                            class="btn btn-primary">
                                            <i class="bi bi-send me-1"></i>
                                            Submit response
                                        </button>

                                    <?php elseif ($sectionIndex < $totalSections - 1): ?>

                                        <button
                                            type="button"
                                            class="btn btn-primary next-button">
                                            Next
                                            <i class="bi bi-arrow-right ms-1"></i>
                                        </button>

                                    <?php else: ?>

                                        <span class="text-secondary small align-self-center">
                                            End of form preview
                                        </span>

                                    <?php endif; ?>

                                </div>
                            </div>

                        </div>

                        <?php $sectionIndex++; ?>

                    <?php endforeach; ?>

                </form>

            <?php endif; ?>

            <!-- Publish button for draft forms -->
            <?php if ($form['status'] === 'draft'): ?>

                <div class="card border-0 shadow-sm mb-5">
                    <div class="card-body">
                        <p class="text-secondary small">
                            This form is currently a draft. Publish it when
                            you are ready to collect responses.
                        </p>

                        <form method="POST"
                              action="../../controller/publishFormController.php"
                              onsubmit="return confirm('Publish this form?');">

                            <input type="hidden"
                                   name="form_id"
                                   value="<?= (int) $form_id ?>">

                            <button
                                type="submit"
                                name="publish_form"
                                value="1"
                                class="btn btn-success">
                                <i class="bi bi-send me-1"></i>
                                Publish form
                            </button>

                        </form>
                    </div>
                </div>

            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('responseForm');

    if (!form) {
        return;
    }

    const sections = Array.from(
        form.querySelectorAll('.form-section-page')
    );

    if (sections.length === 0) {
        return;
    }

    const counter = document.getElementById('sectionCounter');
    const percent = document.getElementById('sectionPercent');
    const progressBar = document.getElementById('sectionProgressBar');

    let currentSection = 0;

    function showSection(index) {
        currentSection = Math.max(
            0,
            Math.min(index, sections.length - 1)
        );

        sections.forEach(function (section, sectionIndex) {
            section.classList.toggle(
                'active',
                sectionIndex === currentSection
            );
        });

        const currentNumber = currentSection + 1;
        const progress = Math.round(
            (currentNumber / sections.length) * 100
        );

        counter.textContent =
            'Section ' + currentNumber + ' of ' + sections.length;

        percent.textContent = progress + '%';
        progressBar.style.width = progress + '%';

        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    function validateSection(section) {
        let firstInvalid = null;
        const cards = section.querySelectorAll('.question-card');

        cards.forEach(function (card) {
            const required = card.dataset.required === '1';
            const type = card.dataset.questionType;
            const error = card.querySelector('.question-error');

            if (error) {
                error.classList.add('d-none');
            }

            card.querySelectorAll('.is-invalid').forEach(function (field) {
                field.classList.remove('is-invalid');
            });

            if (!required) {
                return;
            }

            let valid = true;
            let fieldToFocus = null;

            if (type === 'multiple_choice') {
                const radios = card.querySelectorAll(
                    'input[type="radio"]'
                );

                valid = Array.from(radios).some(function (radio) {
                    return radio.checked;
                });

                fieldToFocus = radios[0] || null;

            } else if (type === 'checkboxes') {
                const checkboxes = card.querySelectorAll(
                    'input[type="checkbox"]'
                );

                valid = Array.from(checkboxes).some(function (checkbox) {
                    return checkbox.checked;
                });

                fieldToFocus = checkboxes[0] || null;

            } else {
                const field = card.querySelector(
                    'input:not([type="radio"]):not([type="checkbox"]), ' +
                    'textarea, select'
                );

                valid = !!field && field.value.trim() !== '';
                fieldToFocus = field;
            }

            if (!valid) {
                if (error) {
                    error.classList.remove('d-none');
                }

                if (fieldToFocus) {
                    fieldToFocus.classList.add('is-invalid');
                }

                if (!firstInvalid) {
                    firstInvalid = fieldToFocus || card;
                }
            }
        });

        if (firstInvalid) {
            firstInvalid.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });

            if (typeof firstInvalid.focus === 'function') {
                firstInvalid.focus({ preventScroll: true });
            }

            return false;
        }

        return true;
    }

    // Next: validate only the section currently being answered.
    form.querySelectorAll('.next-button').forEach(function (button) {
        button.addEventListener('click', function () {
            const activeSection = sections[currentSection];

            if (!validateSection(activeSection)) {
                return;
            }

            showSection(currentSection + 1);
        });
    });

    // Back: return to the previous section without clearing answers.
    form.querySelectorAll('.back-button').forEach(function (button) {
        button.addEventListener('click', function () {
            if (currentSection > 0) {
                showSection(currentSection - 1);
            }
        });
    });

    // Validate all sections before submitting the response.
    form.addEventListener('submit', function (event) {
        for (let i = 0; i < sections.length; i++) {
            if (!validateSection(sections[i])) {
                event.preventDefault();
                showSection(i);

                // Re-run validation after displaying the correct section.
                validateSection(sections[i]);
                return;
            }
        }
    });

    // Clear answers and return to the first section.
    form.addEventListener('reset', function () {
        window.setTimeout(function () {
            form.querySelectorAll('.question-error').forEach(function (error) {
                error.classList.add('d-none');
            });

            form.querySelectorAll('.is-invalid').forEach(function (field) {
                field.classList.remove('is-invalid');
            });

            showSection(0);
        }, 0);
    });

    // Update the active section when the page first loads.
    showSection(0);
});
</script>

<?php include '../includes/footer.php'; ?>