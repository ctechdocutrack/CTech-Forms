<?php
session_start();

if (!isset($_SESSION['authUser'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../config/dbconn.php';

// Ensure the form was submitted correctly.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['save_form'])) {
    header("Location: ../views/admin/create_forms.php");
    exit();
}

$user_id = (int) $_SESSION['authUser']['user_id'];
$form_title = trim($_POST['form_title'] ?? '');
$description = trim($_POST['description'] ?? '');
$sections = $_POST['sections'] ?? [];
$questions = $_POST['questions'] ?? [];

// Validate the form title.
if ($form_title === '') {
    header(
        "Location: ../views/admin/create_forms.php?error=" .
        urlencode('Please enter a form title.')
    );
    exit();
}

// Ensure sections and questions are arrays.
if (!is_array($sections) || count($sections) === 0) {
    $sections = [
        [
            'title' => 'Section 1',
            'description' => ''
        ]
    ];
}

if (!is_array($questions)) {
    $questions = [];
}

// Keep sections in their original builder order.
ksort($sections);
$sections = array_values($sections);

$form_token = bin2hex(random_bytes(32));

// Maps each section's builder index to its database section_id.
$sectionIdByIndex = [];

$questionTypes = [
    'short_answer',
    'paragraph',
    'multiple_choice',
    'checkboxes',
    'dropdown'
];

// Begin transaction so related records are saved together.
mysqli_begin_transaction($conn);

try {

    /*
    |--------------------------------------------------------------------------
    | 1. Save the form
    |--------------------------------------------------------------------------
    */

    $formStmt = mysqli_prepare(
        $conn,
        "INSERT INTO forms
            (form_title, description, status, created_by, form_token)
         VALUES (?, ?, 'draft', ?, ?)"
    );

    if (!$formStmt) {
        throw new Exception(
            'Could not prepare form insert: ' . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $formStmt,
        'ssis',
        $form_title,
        $description,
        $user_id,
        $form_token
    );

    if (!mysqli_stmt_execute($formStmt)) {
        throw new Exception(
            'Could not save form: ' . mysqli_stmt_error($formStmt)
        );
    }
    
    $form_id = (int) mysqli_insert_id($conn);

    mysqli_stmt_close($formStmt);


    /*
    |--------------------------------------------------------------------------
    | 2. Save sections
    |--------------------------------------------------------------------------
    */

    $sectionStmt = mysqli_prepare(
        $conn,
        "INSERT INTO form_sections
            (form_id, section_title, section_description, section_order)
         VALUES (?, ?, ?, ?)"
    );

    if (!$sectionStmt) {
        throw new Exception(
            'Could not prepare section insert: ' . mysqli_error($conn)
        );
    }

    foreach ($sections as $sectionIndex => $section) {

        if (!is_array($section)) {
            continue;
        }

        $section_title = trim($section['title'] ?? '');
        $section_description = trim($section['description'] ?? '');

        if ($section_title === '') {
            $section_title = 'Section ' . ($sectionIndex + 1);
        }

        $section_order = $sectionIndex + 1;

        mysqli_stmt_bind_param(
            $sectionStmt,
            'issi',
            $form_id,
            $section_title,
            $section_description,
            $section_order
        );

        if (!mysqli_stmt_execute($sectionStmt)) {
            throw new Exception(
                'Could not save section: ' . mysqli_stmt_error($sectionStmt)
            );
        }

        // Store the actual database ID for this section.
        $sectionIdByIndex[$sectionIndex] =
            (int) mysqli_insert_id($conn);
    }

    mysqli_stmt_close($sectionStmt);

    if (empty($sectionIdByIndex)) {
        throw new Exception('No section could be saved.');
    }


    /*
    |--------------------------------------------------------------------------
    | 3. Prepare question and option inserts
    |--------------------------------------------------------------------------
    */

    $questionStmt = mysqli_prepare(
        $conn,
        "INSERT INTO form_questions
            (
                form_id,
                section_id,
                question_title,
                question_type,
                is_required,
                question_order
            )
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    if (!$questionStmt) {
        throw new Exception(
            'Could not prepare question insert: ' . mysqli_error($conn)
        );
    }

    $optionStmt = mysqli_prepare(
        $conn,
        "INSERT INTO question_options
            (question_id, option_text, option_order)
         VALUES (?, ?, ?)"
    );

    if (!$optionStmt) {
        throw new Exception(
            'Could not prepare option insert: ' . mysqli_error($conn)
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 4. Save questions under their assigned sections
    |--------------------------------------------------------------------------
    */

    $question_order = 0;

    foreach ($questions as $question) {

        if (!is_array($question)) {
            continue;
        }

        $question_title = trim($question['title'] ?? '');

        // Ignore empty question cards.
        if ($question_title === '') {
            continue;
        }

        $question_type = $question['type'] ?? 'short_answer';

        // Accept only supported question types.
        if (!in_array($question_type, $questionTypes, true)) {
            $question_type = 'short_answer';
        }

        $is_required = isset($question['required']) ? 1 : 0;

        // The JavaScript sends the section's zero-based builder index.
        $section_index = isset($question['section_index'])
            ? (int) $question['section_index']
            : 0;

        // Fall back to the first saved section if the index is invalid.
        if (!isset($sectionIdByIndex[$section_index])) {
            $section_index = (int) array_key_first($sectionIdByIndex);
        }

        $section_id = $sectionIdByIndex[$section_index];

        mysqli_stmt_bind_param(
            $questionStmt,
            'iissii',
            $form_id,
            $section_id,
            $question_title,
            $question_type,
            $is_required,
            $question_order
        );

        if (!mysqli_stmt_execute($questionStmt)) {
            throw new Exception(
                'Could not save question: ' . mysqli_stmt_error($questionStmt)
            );
        }

        $question_id = (int) mysqli_insert_id($conn);


        /*
        |--------------------------------------------------------------------------
        | 5. Save options for choice-based questions
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $question_type,
                ['multiple_choice', 'checkboxes', 'dropdown'],
                true
            )
        ) {
            $options = $question['options'] ?? [];
            $option_order = 0;

            if (is_array($options)) {

                foreach ($options as $option) {

                    $option_text = trim((string) $option);

                    // Do not save blank options.
                    if ($option_text === '') {
                        continue;
                    }

                    mysqli_stmt_bind_param(
                        $optionStmt,
                        'isi',
                        $question_id,
                        $option_text,
                        $option_order
                    );

                    if (!mysqli_stmt_execute($optionStmt)) {
                        throw new Exception(
                            'Could not save question option: ' .
                            mysqli_stmt_error($optionStmt)
                        );
                    }

                    $option_order++;
                }
            }
        }

        $question_order++;
    }


    /*
    |--------------------------------------------------------------------------
    | 6. Finish and redirect
    |--------------------------------------------------------------------------
    */

    mysqli_stmt_close($questionStmt);
    mysqli_stmt_close($optionStmt);

    mysqli_commit($conn);

    header(
        "Location: ../views/admin/view_forms.php?token=" .
        urlencode($form_token)
    );
    exit();

} catch (Throwable $e) {

    // Undo all inserts if any operation fails.
    mysqli_rollback($conn);

    // Keep technical details in the PHP error log.
    error_log(
        'saveFormController.php error: ' . $e->getMessage()
    );

    header(
        "Location: ../views/admin/create_forms.php?error=" .
        urlencode(
            'The form could not be saved. Please check the form data and database structure, then try again.'
        )
    );
    exit();
}
?>