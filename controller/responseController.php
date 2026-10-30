
<?php
session_start();

require_once '../config/dbconn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $form_token = trim($_POST['form_token'] ?? '');
    $answers = $_POST['answers'] ?? [];

    if ($form_token === '') {
        header("Location: ../views/admin/view_forms.php?error=" .
            urlencode("Invalid form link."));
        exit();
    }

    $form_token_sql = mysqli_real_escape_string($conn, $form_token);

    // Check if the form exists and is published
    $form_sql = "SELECT form_id, status
                 FROM forms
                 WHERE form_token = '$form_token_sql'
                 LIMIT 1";

    $form_result = mysqli_query($conn, $form_sql);

    if ($form_result && mysqli_num_rows($form_result) > 0) {

        $form = mysqli_fetch_assoc($form_result);

        if ($form['status'] !== 'published') {
            exit("This form is not available for responses.");
        }

        $form_id = (int) $form['form_id'];

        // Get the questions belonging to this form
        $question_sql = "SELECT question_id, question_type,
                                question_title, is_required
                         FROM form_questions
                         WHERE form_id = $form_id
                         ORDER BY question_order ASC";

        $question_result = mysqli_query($conn, $question_sql);

        if ($question_result) {

            $questions = [];
            $options_by_question = [];
            $error = '';

            while ($question = mysqli_fetch_assoc($question_result)) {

                $question_id = (int) $question['question_id'];
                $questions[$question_id] = $question;

                // Get valid options for choice-based questions
                if (in_array($question['question_type'], [
                    'multiple_choice',
                    'checkboxes',
                    'dropdown'
                ], true)) {

                    $option_sql = "SELECT option_text
                                   FROM question_options
                                   WHERE question_id = $question_id";

                    $option_result = mysqli_query($conn, $option_sql);

                    if (!$option_result) {
                        exit("Unable to load question options.");
                    }

                    $options_by_question[$question_id] = [];

                    while ($option = mysqli_fetch_assoc($option_result)) {
                        $options_by_question[$question_id][] =
                            $option['option_text'];
                    }
                }
            }

            // Validate and prepare the answers
            $saved_answers = [];

            foreach ($questions as $question_id => $question) {

                $type = $question['question_type'];
                $required = (int) $question['is_required'] === 1;
                $answer = $answers[$question_id] ?? '';

                if ($type === 'checkboxes') {

                    if (!is_array($answer)) {
                        $error = "Invalid answer submitted.";
                        break;
                    }

                    $answer = array_values(array_unique($answer));
                    $valid_options = $options_by_question[$question_id] ?? [];

                    foreach ($answer as $selected_option) {
                        if (
                            !is_string($selected_option) ||
                            !in_array($selected_option, $valid_options, true)
                        ) {
                            $error = "Invalid option selected.";
                            break 2;
                        }
                    }

                    if ($required && count($answer) === 0) {
                        $error = "Please answer all required questions.";
                        break;
                    }

                    $answer_text = json_encode(
                        $answer,
                        JSON_UNESCAPED_UNICODE
                    );

                } else {

                    if (!is_string($answer)) {
                        $error = "Invalid answer submitted.";
                        break;
                    }

                    $answer = trim($answer);

                    if ($required && $answer === '') {
                        $error = "Please answer all required questions.";
                        break;
                    }

                    // Validate selected options
                    if (in_array($type, [
                        'multiple_choice',
                        'dropdown'
                    ], true) && $answer !== '') {

                        $valid_options = $options_by_question[$question_id] ?? [];

                        if (!in_array($answer, $valid_options, true)) {
                            $error = "Invalid option selected.";
                            break;
                        }
                    }

                    $answer_text = $answer;
                }

                $saved_answers[$question_id] = $answer_text;
            }

            if ($error !== '') {
                header("Location: ../views/admin/view_forms.php?token=" .
                    urlencode($form_token) . "&error=" . urlencode($error));
                exit();
            }

            // Save the response and its answers
            mysqli_begin_transaction($conn);

            $response_sql = "INSERT INTO responses (form_id)
                             VALUES ($form_id)";

            
            if (mysqli_query($conn, $response_sql)) {

                $response_id = (int) mysqli_insert_id($conn);
                $save_success = true;

                foreach ($saved_answers as $question_id => $answer_text) {

                    $answer_sql_value = mysqli_real_escape_string(
                        $conn,
                        (string) $answer_text
                    );

                    $answer_sql = "INSERT INTO response_answers
                                (response_id, question_id, answer_text)
                                VALUES (
                                    $response_id,
                                    $question_id,
                                    '$answer_sql_value'
                                )";

                    if (!mysqli_query($conn, $answer_sql)) {
                        $save_success = false;
                        break;
                    }
                }

                if ($save_success) {

                    mysqli_commit($conn);

                    header(
                        "Location: ../views/admin/response_submitted.php?token=" .
                        urlencode($form_token)
                    );
                    exit();

                } else {

                    mysqli_rollback($conn);

                    error_log("Save answers failed: " . mysqli_error($conn));

                    header(
                        "Location: ../views/admin/view_forms.php?token=" .
                        urlencode($form_token) .
                        "&error=" .
                        urlencode("Unable to save your response. Please try again.")
                    );
                    exit();
                }

            } else {

                mysqli_rollback($conn);

                error_log("Save response failed: " . mysqli_error($conn));

                header(
                    "Location: ../views/admin/view_forms.php?token=" .
                    urlencode($form_token) .
                    "&error=" .
                    urlencode("Unable to submit your response. Please try again.")
                );
                exit();
            }


        } else {
            exit("Unable to load form questions.");
        }

    } else {
        http_response_code(404);
        exit("Form not found.");
    }

} else {
    header("Location: ../index.php");
    exit();
}
?>
