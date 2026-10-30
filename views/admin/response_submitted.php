<?php
session_start();

require_once '../../config/dbconn.php';

$form_token = trim($_GET['token'] ?? '');
$form = null;

if ($form_token !== '') {

    $form_token_sql = mysqli_real_escape_string($conn, $form_token);

    $form_sql = "SELECT form_title, form_token
                 FROM forms
                 WHERE form_token = '$form_token_sql'
                 LIMIT 1";

    $form_result = mysqli_query($conn, $form_sql);

    if ($form_result && mysqli_num_rows($form_result) > 0) {
        $form = mysqli_fetch_assoc($form_result);
    }
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container-fluid bg-light min-vh-100 py-5">
    <div class="container">

        <div class="row justify-content-center mt-4">
            <div class="col-12 col-md-9 col-lg-7 col-xl-6">

                <?php if ($form === null): ?>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center p-4 p-md-5">

                            <i class="bi bi-file-earmark-x display-1 text-secondary"></i>

                            <h2 class="fw-bold mt-3">
                                Form Unavailable
                            </h2>

                            <p class="text-secondary mb-4">
                                The form could not be found. Please check the form link.
                            </p>

                            <a href="view_forms.php" class="btn btn-primary">
                                <i class="bi bi-arrow-left me-1"></i>
                                Back
                            </a>

                        </div>
                    </div>

                <?php else: ?>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center p-4 p-md-5 border-top border-4 border-success">

                            <div class="mb-4">
                                <i class="bi bi-check-circle-fill display-1 text-success"></i>
                            </div>

                            <span class="badge text-bg-success mb-3">
                                Submission Successful
                            </span>

                            <h1 class="h2 fw-bold mb-3">
                                Thank You!
                            </h1>

                            <p class="text-secondary">
                                Your response has been submitted successfully.
                                We appreciate your time and effort in completing this form.
                            </p>

                            <hr class="my-4">

                            <div class="alert alert-success text-start" role="alert">
                                <i class="bi bi-shield-check me-2"></i>
                                Your response has been recorded in the system.
                            </div>

                            <p class="text-secondary small mb-4">
                                You may now return to the form page.
                                Thank you for participating!
                            </p>

                            <a
                                href="view_forms.php?token=<?= urlencode($form['form_token']) ?>"
                                class="btn btn-primary"
                            >
                                <i class="bi bi-arrow-left me-2"></i>
                                Create another response
                            </a>

                        </div>
                    </div>

                <?php endif; ?>

            </div>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>