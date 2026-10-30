<?php
session_start();

if (!isset($_SESSION['authUser'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../config/dbconn.php';

if(isset($_POST['publish_form'])){
    $user_id = $_SESSION['authUser']['user_id'];
    $form_id = $_POST['form_id'];

    $sql = "SELECT * FROM forms WHERE form_id = $form_id AND created_by = $user_id";
    $sql_run = mysqli_query($conn, $sql);

    if(mysqli_num_rows($sql_run) > 0){
        $row = mysqli_fetch_assoc($sql_run);

        $sql = "UPDATE forms 
                        SET status = 'published' 
                        WHERE form_id = $form_id 
                        AND created_by = $user_id";
        $sql_run = mysqli_query($conn, $sql);

        if($sql_run){
            $form_link = "http://localhost/CTech%20Forms/views/admin/view_forms.php?token=" . urlencode($row['form_token']);
            $success_message = "Form published successfully. Form link: " . $form_link;

            header(
                "Location: ../views/admin/my_forms.php?id=$form_id&success=" .
                urlencode($success_message)
            );
            exit();
        } else {
            header("Location: ../views/admin/edit_forms.php?id=$form_id&error=" . urlencode("Failed to publish the form."));
            exit();
        }
    } else {
        header("Location: ../views/admin/edit_forms.php?error=" . urlencode("Form not found or access denied."));
        exit();
    }
}

