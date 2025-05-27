<?php
// admin.php - Admin panel
require_once 'config.php';
require_once 'session.php';
require_once 'User.php';

requireLogin();

if(!isAdmin()) {
    header('Location: index.php');
    exit();
}

$database = new Database();
$db = $database->connect();
$user = new User($db);
$users = $user->getAllUsers();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Document System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <span class="navbar-text">
                <i class="fas fa-users-cog"></i> Admin Panel
            </span>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>User Management</h2>
        
        <!-- Alert for messages -->
        <div id="alertMessage" class="alert alert-dismissible fade" role="alert" style="display: none;">
            <span id="alertText"></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user_row): ?>
                    <tr id="user-row-<?php echo $user_row['id']; ?>">
                        <td><?php echo $user_row['id']; ?></td>
                        <td><?php echo htmlspecialchars($user_row['username']); ?></td>
                        <td><?php echo htmlspecialchars($user_row['email']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $user_row['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                <?php echo ucfirst($user_row['role']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $user_row['is_suspended'] ? 'warning' : 'success'; ?>" 
                                  id="status-badge-<?php echo $user_row['id']; ?>">
                                <?php echo $user_row['is_suspended'] ? 'Suspended' : 'Active'; ?>
                            </span>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($user_row['created_at'])); ?></td>
                        <td>
                            <?php if($user_row['id'] != $_SESSION['user_id']): ?>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" 
                                       id="suspend_<?php echo $user_row['id']; ?>" 
                                       <?php echo $user_row['is_suspended'] ? 'checked' : ''; ?>
                                       onchange="toggleSuspension(<?php echo $user_row['id']; ?>, this.checked)">
                                <label class="form-check-label" for="suspend_<?php echo $user_row['id']; ?>">
                                    Suspend
                                </label>
                            </div>
                            <button class="btn btn-sm btn-outline-danger mt-1" 
                                    onclick="deleteUser(<?php echo $user_row['id']; ?>, '<?php echo htmlspecialchars($user_row['username']); ?>')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Statistics Section -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5>Total Users</h5>
                        <h3><?php echo count($users); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5>Active Users</h5>
                        <h3><?php echo count(array_filter($users, function($u) { return !$u['is_suspended']; })); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5>Suspended Users</h5>
                        <h3><?php echo count(array_filter($users, function($u) { return $u['is_suspended']; })); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5>Admin Users</h5>
                        <h3><?php echo count(array_filter($users, function($u) { return $u['role'] === 'admin'; })); ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete user <strong id="deleteUsername"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete User</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        let deleteUserId = null;

        function showAlert(message, type = 'success') {
            const alertDiv = document.getElementById('alertMessage');
            const alertText = document.getElementById('alertText');
            
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertText.textContent = message;
            alertDiv.style.display = 'block';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                alertDiv.style.display = 'none';
            }, 5000);
        }

        function toggleSuspension(userId, isSuspended) {
            fetch('ajax/toggle_suspension.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId,
                    is_suspended: isSuspended
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the status badge
                    const statusBadge = document.getElementById(`status-badge-${userId}`);
                    if (isSuspended) {
                        statusBadge.textContent = 'Suspended';
                        statusBadge.className = 'badge bg-warning';
                    } else {
                        statusBadge.textContent = 'Active';
                        statusBadge.className = 'badge bg-success';
                    }
                    
                    showAlert(data.message, 'success');
                } else {
                    showAlert(data.message || 'An error occurred', 'danger');
                    // Revert the checkbox
                    document.getElementById(`suspend_${userId}`).checked = !isSuspended;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred while updating user status', 'danger');
                // Revert the checkbox
                document.getElementById(`suspend_${userId}`).checked = !isSuspended;
            });
        }

        function deleteUser(userId, username) {
            deleteUserId = userId;
            document.getElementById('deleteUsername').textContent = username;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }

        document.getElementById('confirmDelete').addEventListener('click', function() {
            if (deleteUserId) {
                fetch('ajax/delete_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: deleteUserId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the user row from the table
                        const userRow = document.getElementById(`user-row-${deleteUserId}`);
                        userRow.remove();
                        
                        showAlert(data.message, 'success');
                        
                        // Close the modal
                        const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
                        deleteModal.hide();
                        
                        // Refresh statistics (you might want to update this dynamically)
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        showAlert(data.message || 'An error occurred', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('An error occurred while deleting user', 'danger');
                });
                
                deleteUserId = null;
            }
        });

        // Reset deleteUserId when modal is closed
        document.getElementById('deleteModal').addEventListener('hidden.bs.modal', function() {
            deleteUserId = null;
        });
    </script>
</body>
</html>