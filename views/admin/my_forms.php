
<?php
session_start();

if (!isset($_SESSION['authUser'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../../config/dbconn.php';

// Get only the forms created by the logged-in user
$user_id = (int) $_SESSION['authUser']['user_id'];

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container min-vh-100 py-4">
    <div class="container-fluid px-3 px-md-4">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
            <div>
                <h2 class="fw-bold mb-2">My forms</h2>
                <p class="text-secondary mb-0">
                    Create, edit, publish, and review your forms.
                </p>
            </div>

            <a href="create_forms.php" class="btn btn-primary mt-3 mt-md-0">
                <i class="bi bi-plus-lg me-1"></i> Create new form
            </a>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_GET['success']) ?>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Close">
                </button>
            </div>
        <?php endif; ?>

        <?php 
        $sql = "SELECT *
                FROM forms
                WHERE created_by = $user_id
                ORDER BY updated_at DESC";
        $sql_run = mysqli_query($conn, $sql);

        if (mysqli_num_rows($sql_run) > 0) {
            while ($row = mysqli_fetch_assoc($sql_run)) {
                $form_id = $row['form_id'];
                $form_title = $row['form_title'];
                $description = $row['description'];
                $status = $row['status'];
                $created_by = $row['created_by'];
                $form_token = $row['form_token'];

                echo "
                <div class='card border rounded-3 mb-3 shadow-sm'>
                    <div class='card-body p-3 p-md-4'>
                        <div class='d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3'>

                            <div class='d-flex align-items-center gap-3'>
                                <div class='rounded-3 d-flex align-items-center justify-content-center flex-shrink-0'
                                        style='width: 46px; height: 46px; background-color: #f1ebfa;'>
                                    <i class='bi bi-layout-text-window-reverse fs-5 text-primary'></i>
                                </div>

                                <div>
                                    <h5 class='fw-bold mb-1'>$form_title</h5>
                                    <p class='text-secondary mb-1'>" . (!empty($description) ? $description : 'No description') . "</p>
                                    <small class='text-secondary'>
                                        0 responses
                                        &middot;
                                        Updated " . date('Y-m-d H:i:s', strtotime($row['updated_at'])) . "
                                        &middot;
                                        " . ($status === 'published' ? "<span class='text-success fw-semibold'>Published</span>" : "<span class='text-warning-emphasis fw-semibold'>Draft</span>") . "
                                    </small>
                                </div>
                            </div>

                            <div class='d-flex flex-wrap gap-2 justify-content-md-end'>
                                <a href='edit_forms.php?id=$form_id' class='btn btn-outline-secondary'>Edit</a>
                                <a href='view_forms.php?token=$form_token' class='btn btn-outline-secondary'>Open form</a>
                                <a href='responses.php?id=$form_id' class='btn btn-outline-secondary'>Responses</a>
                            </div>

                        </div>
                    </div>
                </div>";
            }
            
        } elseif ($sql_run) {
            // Show this only when the user has no forms
            echo "
            <div class='card border-0 shadow-sm'>
                <div class='card-body text-center py-5'>
                    <i class='bi bi-file-earmark-text display-4 text-secondary'></i>
                    <h5 class='mt-3'>No forms yet</h5>
                    <p class='text-secondary'>
                        Create your first form to get started.
                    </p>
                    <a href='create_forms.php' class='btn btn-primary'>
                        <i class='bi bi-plus-lg me-1'></i> Create new form
                    </a>
                </div>
            </div>";

        } else {
            error_log("Fetch forms failed: " . mysqli_error($conn));

            echo "<div class='alert alert-danger'>
                    Unable to load your forms. Please try again.
                </div>";
        }
        ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
