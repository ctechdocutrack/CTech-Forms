
<?php
session_start();

if (!isset($_SESSION['authUser'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../config/dbconn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_form']) || isset($_POST['form_action']))) {
    $user_id = (int) $_SESSION['authUser']['user_id'];
    $form_id = (int) ($_POST['form_id'] ?? 0);
    $form_title = mysqli_real_escape_string($conn,trim($_POST['form_title'] ?? ''));
    $description = mysqli_real_escape_string($conn,trim($_POST['description'] ?? ''));

    $questions = $_POST['questions'] ?? [];
    $sections = $_POST['sections'] ?? [];
    // Get existing form owned by the logged-in user
    $sql = "SELECT * FROM forms
            WHERE form_id = $form_id
            AND created_by = $user_id";
    $sql_run = mysqli_query($conn, $sql);

    if ($sql_run && mysqli_num_rows($sql_run) > 0) {
        $row = mysqli_fetch_assoc($sql_run);
        $old_title = $row['form_title'];
        $old_description = $row['description'];
        $form_changed = (
            $old_title !== $form_title ||
            $old_description !== $description
        );

        // Get existing sections
        $old_sections = [];

        $section_sql = "
            SELECT section_id, section_title, section_description, section_order
            FROM form_sections
            WHERE form_id = $form_id
            ORDER BY section_order ASC, section_id ASC
        ";

        $section_result = mysqli_query($conn, $section_sql);

        if (!$section_result) {
            die("Failed to load sections: " . mysqli_error($conn));
        }

        while ($section = mysqli_fetch_assoc($section_result)) {
            $old_sections[(int) $section['section_id']] = [
                'title' => trim($section['section_title'] ?? ''),
                'description' => trim($section['section_description'] ?? ''),
            ];

           
        }

         // Map each submitted section ID to its current position.
        $posted_section_index_by_id = [];

        foreach ($sections as $section_index => $section) {
            if (!is_array($section)) {
                continue;
            }

            $section_id = (int) ($section['section_id'] ?? 0);

            if ($section_id > 0) {
                $posted_section_index_by_id[$section_id] = (int) $section_index;
            }
        }

        // Check whether section titles/descriptions changed
        // and detect sections removed from the edit form.
        $submitted_section_ids = [];

        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $section_id = (int) ($section['section_id'] ?? 0);
            $new_title = trim($section['title'] ?? '');
            $new_description = trim($section['description'] ?? '');

            if ($section_id === 0) {
                // A newly added section
                $form_changed = true;
                continue;
            }

            if (!isset($old_sections[$section_id])) {
                $form_changed = true;
                continue;
            }

            $submitted_section_ids[] = $section_id;

            if (
                $old_sections[$section_id]['title'] !== $new_title ||
                $old_sections[$section_id]['description'] !== $new_description
            ) {
                $form_changed = true;
            }
        }

        // Detect existing sections that were removed
        foreach (array_keys($old_sections) as $old_section_id) {
            if (!in_array($old_section_id, $submitted_section_ids, true)) {
                $form_changed = true;
                break;
            }
        }
        // Compare existing questions and options
        $old_questions = [];
        $sql_questions = "SELECT *
                          FROM form_questions
                          WHERE form_id = $form_id
                          ORDER BY question_order ASC";

        $questions_run = mysqli_query($conn, $sql_questions);

        if ($questions_run) {
            while ($question = mysqli_fetch_assoc($questions_run)) {
                $question_id = (int) $question['question_id'];
                $question['options'] = [];
                $sql_options = "SELECT option_text
                                FROM question_options
                                WHERE question_id = $question_id
                                ORDER BY option_order ASC";

                $options_run = mysqli_query($conn, $sql_options);
                if ($options_run) {
                    while ($option = mysqli_fetch_assoc($options_run)) {
                        $question['options'][] = $option['option_text'];
                    }
                }
                $old_questions[] = $question;
            }
        }

        $new_questions = [];
        foreach ($questions as $question) {
            if (!is_array($question)) {
                continue;
            }
            $title = trim($question['title'] ?? '');
            $type = $question['type'] ?? 'short_answer';
            $required = isset($question['required']) ? 1 : 0;
            $options = $question['options'] ?? [];
            $clean_options = [];
            if (is_array($options)) {
                foreach ($options as $option) {
                    if (is_array($option)) {
                        $option = $option['option_text'] ?? '';
                    }

                    $clean_options[] = trim((string) $option);
                }
            }
            $new_questions[] = [
                'title' => $title,
                'type' => $type,
                'required' => $required,
                'options' => $clean_options,
                'section_index' => (int) ($question['section_index'] ?? 0)
            ];
        }
        $normalized_old_questions = [];

        foreach ($old_questions as $question) {
            $old_section_id = (int) ($question['section_id'] ?? 0);

            $old_section_index = $posted_section_index_by_id[$old_section_id]
                ?? -1;

            $normalized_old_questions[] = [
                'title' => $question['question_title'],
                'type' => $question['question_type'],
                'required' => (int) $question['is_required'],
                'options' => $question['options'],
                'section_index' => $old_section_index
            ];
        }
        if ($normalized_old_questions !== $new_questions) {
            $form_changed = true;
        }
        // No changes: do not run UPDATE or replace questions
        if (!$form_changed) {
            header(
                "Location: ../views/admin/edit_forms.php?id=$form_id&success=" .
                urlencode("No changes detected.")
            );
            exit();
        }
        mysqli_begin_transaction($conn);
        // Update form details only when changed
        if (
            $old_title !== $form_title ||
            $old_description !== $description
        ) {
            $sql = "UPDATE forms
                    SET form_title = '$form_title',
                        description = '$description'
                    WHERE form_id = $form_id
                    AND created_by = $user_id";

            if (!mysqli_query($conn, $sql)) {
                mysqli_rollback($conn);

                header(
                    "Location: ../views/admin/edit_forms.php?id=$form_id&error=" .
                    urlencode("Failed to update form.")
                );
                exit();
            }
        }

        // Update or insert sections
        $section_order = 1;
        $section_ids_by_index = [];

        foreach ($sections as $section_index => $section) {
            if (!is_array($section)) {
                continue;
            }

            $section_id = (int) ($section['section_id'] ?? 0);
            $section_title = trim($section['title'] ?? '');
            $section_description = trim($section['description'] ?? '');

            if ($section_title === '') {
                $section_title = 'Section ' . $section_order;
            }

            $section_title = mysqli_real_escape_string($conn, $section_title);
            $section_description = mysqli_real_escape_string(
                $conn,
                $section_description
            );

            if ($section_id > 0 && isset($old_sections[$section_id])) {
                // Update an existing section belonging to this form
                $section_update_sql = "
                    UPDATE form_sections
                    SET section_title = '$section_title',
                        section_description = '$section_description',
                        section_order = $section_order
                    WHERE section_id = $section_id
                    AND form_id = $form_id
                ";

                if (!mysqli_query($conn, $section_update_sql)) {
                    mysqli_rollback($conn);
                    die("Failed to update section: " . mysqli_error($conn));
                }

                // Map the submitted section index to its existing database ID
                $section_ids_by_index[(int) $section_index] = $section_id;

            } else {
                // Insert a newly added section
                $section_insert_sql = "
                    INSERT INTO form_sections
                        (form_id, section_title, section_description, section_order)
                    VALUES
                        ($form_id, '$section_title', '$section_description', $section_order)
                ";

                if (!mysqli_query($conn, $section_insert_sql)) {
                    mysqli_rollback($conn);
                    die("Failed to insert section: " . mysqli_error($conn));
                }

                // Map the submitted section index to the newly created database ID
                $section_ids_by_index[(int) $section_index] = (int) mysqli_insert_id($conn);
            }

            $section_order++;
        }

        // Replace questions only when the questions changed
        if ($normalized_old_questions !== $new_questions) {

            // Delete options first to avoid foreign-key constraint errors
            $sql = "DELETE qo
                    FROM question_options qo
                    INNER JOIN form_questions fq
                        ON qo.question_id = fq.question_id
                    WHERE fq.form_id = $form_id";

            if (!mysqli_query($conn, $sql)) {
                mysqli_rollback($conn);

                header(
                    "Location: ../views/admin/edit_forms.php?id=$form_id&error=" .
                    urlencode("Failed to delete question options.")
                );
                exit();
            }

            // Now delete the old questions
            $sql = "DELETE FROM form_questions
                    WHERE form_id = $form_id";

            if (mysqli_query($conn, $sql)) {

                $allowed_types = [
                    'short_answer',
                    'paragraph',
                    'multiple_choice',
                    'checkboxes',
                    'dropdown'
                ];

                $question_order = 0;
                $save_success = true;

                foreach ($new_questions as $question) {

                    $question_title = mysqli_real_escape_string(
                        $conn,
                        $question['title']
                    );

                    $question_type = $question['type'];

                    if (!in_array($question_type, $allowed_types, true)) {
                        $question_type = 'short_answer';
                    }

                    $question_type = mysqli_real_escape_string(
                        $conn,
                        $question_type
                    );

                    $is_required = $question['required'];

                    // Get the database section ID for this question's section index
                    $section_index = (int) ($question['section_index'] ?? 0);
                    $section_id = $section_ids_by_index[$section_index] ?? 0;

                    if ($section_id <= 0) {
                        $save_success = false;
                        break;
                    }

                    $sql = "INSERT INTO form_questions
                            (form_id, section_id, question_title, question_type,
                            is_required, question_order)
                            VALUES
                            ($form_id, $section_id, '$question_title', '$question_type',
                            $is_required, $question_order)";

                    if (mysqli_query($conn, $sql)) {

                        $question_id = mysqli_insert_id($conn);

                        if (in_array(
                            $question_type,
                            ['multiple_choice', 'checkboxes', 'dropdown'],
                            true
                        )) {
                            $option_order = 0;

                            foreach ($question['options'] as $option) {

                                $option_text = mysqli_real_escape_string(
                                    $conn,
                                    $option
                                );

                                $sql = "INSERT INTO question_options
                                        (question_id, option_text, option_order)
                                        VALUES
                                        ($question_id, '$option_text',
                                         $option_order)";

                                if (mysqli_query($conn, $sql)) {
                                    $option_order++;
                                } else {
                                    $save_success = false;
                                    break;
                                }
                            }
                        }

                        if (!$save_success) {
                            break;
                        }

                        $question_order++;

                    } else {
                        $save_success = false;
                        break;
                    }
                }

                if (!$save_success) {
                    mysqli_rollback($conn);

                    header(
                        "Location: ../views/admin/edit_forms.php?id=$form_id&error=" .
                        urlencode("Failed to update questions or options.")
                    );
                    exit();
                }

            } else {
                mysqli_rollback($conn);

                header(
                    "Location: ../views/admin/edit_forms.php?id=$form_id&error=" .
                    urlencode("Failed to delete old questions.")
                );
                exit();
            }
        }

        // Delete sections that were removed from the submitted form
        $submitted_section_ids = [];

        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $section_id = (int) ($section['section_id'] ?? 0);

            if ($section_id > 0 && isset($old_sections[$section_id])) {
                $submitted_section_ids[] = $section_id;
            }
        }

        $removed_section_ids = array_diff(
            array_keys($old_sections),
            $submitted_section_ids
        );

        foreach ($removed_section_ids as $removed_section_id) {
            $removed_section_id = (int) $removed_section_id;

            $sql = "DELETE FROM form_sections
                    WHERE section_id = $removed_section_id
                    AND form_id = $form_id";

            if (!mysqli_query($conn, $sql)) {
                mysqli_rollback($conn);

                header(
                    "Location: ../views/admin/edit_forms.php?id=$form_id&error=" .
                    urlencode("Failed to delete removed section.")
                );
                exit();
            }
        }

        mysqli_commit($conn);

        header(
            "Location: ../views/admin/edit_forms.php?id=$form_id&success=" .
            urlencode("Form updated successfully.")
        );
        exit();

    } else {
        header(
            "Location: ../views/admin/forms.php?error=" .
            urlencode("Form not found.")
        );
        exit();
    }

} else {
    header("Location: ../views/admin/forms.php");
    exit();
}
?>
