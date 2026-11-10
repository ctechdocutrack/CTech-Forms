    <?php
    session_start();

    if (!isset($_SESSION['authUser'])) {
        header("Location: ../../index.php");
        exit();
    }

    require_once '../../config/dbconn.php';

    $user_id = (int) $_SESSION['authUser']['user_id'];
    $form_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($form_id <= 0) {
        header("Location: forms.php");
        exit();
    }

    // Verify that the form belongs to the logged-in user
    $stmt = mysqli_prepare(
        $conn,
        "SELECT form_id, form_title, description, status
        FROM forms
        WHERE form_id = ? AND created_by = ?
        LIMIT 1"
    );

    mysqli_stmt_bind_param($stmt, "ii", $form_id, $user_id);
    mysqli_stmt_execute($stmt);
    $form_result = mysqli_stmt_get_result($stmt);

    if (!$form_result || mysqli_num_rows($form_result) === 0) {
        http_response_code(404);
        exit("Form not found or access denied.");
    }

    $form = mysqli_fetch_assoc($form_result);

    // Get all submitted responses
    $responses_sql = "SELECT response_id, submitted_at
                    FROM responses
                    WHERE form_id = $form_id
                    ORDER BY submitted_at DESC";

    $responses_result = mysqli_query($conn, $responses_sql);

    // ========================================
    // VIEW INDIVIDUAL RESPONSE
    // ========================================
    $selected_response = null;
    $response_answers = [];

    if (isset($_GET['view']) && is_numeric($_GET['view'])) {

        $selected_response_id = (int) $_GET['view'];

        // Verify that this response belongs to the current form
        $selected_sql = "SELECT response_id, submitted_at
                        FROM responses
                        WHERE response_id = $selected_response_id
                        AND form_id = $form_id
                        LIMIT 1";

        $selected_result = mysqli_query($conn, $selected_sql);

        if ($selected_result && mysqli_num_rows($selected_result) > 0) {

            $selected_response = mysqli_fetch_assoc($selected_result);

            // Get questions and corresponding answers
            $answer_sql = "SELECT
                                fq.question_title,
                                fq.question_type,
                                fq.is_required,
                                ra.answer_text
                        FROM form_questions fq
                        LEFT JOIN response_answers ra
                                ON fq.question_id = ra.question_id
                                AND ra.response_id = $selected_response_id
                        WHERE fq.form_id = $form_id
                        ORDER BY fq.question_order ASC, fq.question_id ASC";

            $answer_result = mysqli_query($conn, $answer_sql);

            if ($answer_result) {
                while ($answer = mysqli_fetch_assoc($answer_result)) {

                    $answer_text = $answer['answer_text'] ?? '';

                    // Decode checkbox answers
                    $decoded = json_decode($answer_text, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $answer_text = implode(', ', array_map('strval', $decoded));
                    }

                    $answer['answer_text'] = $answer_text;

                    $response_answers[] = $answer;
                }
            }
        }
    }

    if (!$responses_result) {
        error_log("Load responses failed: " . mysqli_error($conn));
        exit("Unable to load responses.");
    }

    // ========================================
    // DOWNLOAD RESPONSES AS EXCEL-COMPATIBLE CSV
    // ========================================
    if (isset($_GET['download']) && $_GET['download'] === 'excel') {

        // Get questions for this form
        $questions_sql = "SELECT question_id, question_title, question_order
                        FROM form_questions
                        WHERE form_id = $form_id
                        ORDER BY question_order ASC, question_id ASC";

        $questions_result = mysqli_query($conn, $questions_sql);

        if (!$questions_result) {
            error_log("Load questions failed: " . mysqli_error($conn));
            exit("Unable to load form questions.");
        }

        $questions = [];

        while ($question = mysqli_fetch_assoc($questions_result)) {
            $questions[] = $question;
        }

        // Get every answer for responses belonging to this form.
        // LEFT JOIN keeps answers even when their question_id is invalid.
        $answers_sql = "SELECT
                            ra.response_id,
                            ra.question_id,
                            ra.answer_text,
                            fq.question_id AS matched_question_id
                        FROM response_answers ra
                        INNER JOIN responses r
                            ON ra.response_id = r.response_id
                        LEFT JOIN form_questions fq
                            ON ra.question_id = fq.question_id
                            AND fq.form_id = $form_id
                        WHERE r.form_id = $form_id
                        ORDER BY ra.response_id, ra.answer_id";

        $answers_result = mysqli_query($conn, $answers_sql);

        if (!$answers_result) {
            error_log("Export answers failed: " . mysqli_error($conn));
            exit("Unable to export responses.");
        }

        $answers = [];

        while ($answer = mysqli_fetch_assoc($answers_result)) {
            $response_id = (int) $answer['response_id'];
            $question_id = (int) $answer['matched_question_id'];
            $answer_text = $answer['answer_text'] ?? '';

            // Convert JSON checkbox arrays into readable text
            $decoded_answer = json_decode($answer_text, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_answer)) {
                $answer_text = implode(', ', array_map('strval', $decoded_answer));
            }

            // Only assign answers with a valid question ID.
            // Do not guess which question an invalid ID belongs to.
            if ($question_id > 0) {
                $answers[$response_id][$question_id] = $answer_text;
            }
        }

        
        // Generate filename from the form title
        $cleanTitle = preg_replace('/[\/\\\\:*?"<>|]+/', '', $form['form_title']);
        $cleanTitle = trim(preg_replace('/\s+/', '_', $cleanTitle), '._-');

        if ($cleanTitle === '') {
            $cleanTitle = 'Form_Responses';
        }

        $filename = $cleanTitle . '_' . date('Y-m-d') . '.csv';


        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $output = fopen('php://output', 'w');

        // UTF-8 BOM for Excel
        fwrite($output, "\xEF\xBB\xBF");

        // Protect spreadsheet cells against formula injection
        $safeCell = function ($value) {
            $value = (string) $value;

            if (preg_match('/^[\s\x00-\x1F]*[=+\-@]/', $value)) {
                return "'" . $value;
            }

            return $value;
        };

        // Header row
        $headers = ['Response ID', 'Date Submitted'];

        foreach ($questions as $question) {
            $headers[] = $safeCell($question['question_title']);
        }

        fputcsv($output, $headers, ',', '"', '');

        // Fetch responses independently so the result cursor is not reused.
        $export_responses_sql = "SELECT response_id, submitted_at
                                FROM responses
                                WHERE form_id = $form_id
                                ORDER BY submitted_at DESC";

        $export_responses_result = mysqli_query($conn, $export_responses_sql);

        if (!$export_responses_result) {
            fclose($output);
            error_log("Export responses failed: " . mysqli_error($conn));
            exit("Unable to export responses.");
        }

        while ($response = mysqli_fetch_assoc($export_responses_result)) {
            $response_id = (int) $response['response_id'];

            $row = [
                $response_id,
                $response['submitted_at']
            ];

            foreach ($questions as $question) {
                $question_id = (int) $question['question_id'];
                $answer_text = $answers[$response_id][$question_id] ?? '';
                $row[] = $safeCell($answer_text);
            }

            fputcsv($output, $row, ',', '"', '');
        }

        fclose($output);
        exit();
    }

    // Helper for safe HTML output
    function escapeOutput($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    include '../includes/header.php';
    include '../includes/navbar.php';
    ?>

    <div class="container py-4">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h2 class="mb-1">
                    <i class="bi bi-clipboard-data me-2"></i>
                    Form Responses
                </h2>

                <p class="text-muted mb-0">
                    <?= escapeOutput($form['form_title']) ?>
                </p>
            </div>

            <div class="d-flex gap-2">
                <a href="responses.php?id=<?= $form_id ?>&download=excel"
                class="btn btn-success">
                    <i class="bi bi-file-earmark-excel me-1"></i>
                    Download Excel
                </a>

                <a href="my_forms.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>
                    Back
                </a>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Submitted Responses</h5>

                <span class="badge bg-primary">
                    <?= mysqli_num_rows($responses_result) ?> Responses
                </span>
            </div>

            <div class="card-body">
                <?php if (mysqli_num_rows($responses_result) === 0): ?>

                    <div class="text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted"></i>
                        <h5 class="mt-3">No Responses Yet</h5>
                        <p class="text-muted mb-0">
                            No submitted responses for this form yet.
                        </p>
                    </div>

                <?php else: ?>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 80px;">No.</th>
                                    <th>Response ID</th>
                                    <th>Date Submitted</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php
                                $count = 1;
                                mysqli_data_seek($responses_result, 0);
                                ?>

                                <?php while ($response = mysqli_fetch_assoc($responses_result)): ?>

                                    <tr>
                                        <td><?= $count++ ?></td>

                                        <td>
                                            #<?= (int) $response['response_id'] ?>
                                        </td>

                                        <td>
                                            <?= escapeOutput($response['submitted_at']) ?>
                                        </td>

                                        <td class="text-center">

                                            <a href="responses.php?id=<?= $form_id ?>&view=<?= (int) $response['response_id'] ?>"
                                            class="btn btn-sm btn-primary">

                                                <i class="bi bi-eye me-1"></i>
                                                View Response

                                            </a>

                                        </td>
                                    </tr>

                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-muted small mt-2">
                        <i class="bi bi-info-circle me-1"></i>
                        Click Download Excel to download all responses.
                    </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($selected_response): ?>

    <div class="modal fade" id="responseModal" tabindex="-1"
        aria-labelledby="responseModalLabel" aria-hidden="true">

        <div class="modal-dialog modal-lg modal-dialog-scrollable">

            <div class="modal-content">

                <div class="modal-header">

                    <div>
                        <h5 class="modal-title" id="responseModalLabel">
                            <i class="bi bi-clipboard-check me-2"></i>
                            Student Response #<?= (int) $selected_response['response_id'] ?>
                        </h5>

                        <small class="text-muted">
                            Submitted:
                            <?= escapeOutput($selected_response['submitted_at']) ?>
                        </small>
                    </div>

                    <button type="button" class="btn-close"
                            data-bs-dismiss="modal"></button>

                </div>

                <div class="modal-body">

                    <?php if (empty($response_answers)): ?>

                        <div class="alert alert-warning">
                            No answers found for this response.
                        </div>

                    <?php else: ?>

                        <?php $number = 1; ?>

                        <?php foreach ($response_answers as $answer): ?>

                            <div class="card mb-3">

                                <div class="card-body">

                                    <h6 class="fw-semibold mb-3">
                                        <?= $number++ ?>.
                                        <?= escapeOutput($answer['question_title']) ?>

                                        <?php if ($answer['is_required']): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </h6>

                                    <?php if (trim($answer['answer_text']) !== ''): ?>

                                        <div class="bg-light rounded p-3">
                                            <?= nl2br(escapeOutput($answer['answer_text'])) ?>
                                        </div>

                                    <?php else: ?>

                                        <div class="text-muted fst-italic">
                                            No answer provided.
                                        </div>

                                    <?php endif; ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

                <div class="modal-footer">

                    <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal">
                        Close
                    </button>

                </div>

            </div>

        </div>

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {

        const responseModal = new bootstrap.Modal(
            document.getElementById('responseModal')
        );

        responseModal.show();

    });
    </script>

    <?php endif; ?>

    <?php include '../includes/footer.php'; ?>