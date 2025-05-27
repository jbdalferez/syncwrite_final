<?php
// index.php - Main dashboard

// Start the session
session_start();

// Include necessary files
require_once 'config.php';
require_once 'session.php';
require_once 'Document.php';
require_once 'User.php';

// Function to check if the user is an admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Ensure the user is logged in
requireLogin();

// Check if the session variables are set
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    // Redirect to login if not set
    header('Location: login.php');
    exit();
}

// Connect to the database
$database = new Database();
$db = $database->connect();

// Create a Document object and fetch user documents
$document = new Document($db);
$documents = $document->getUserDocuments($_SESSION['user_id'], isAdmin());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .document-card {
            transition: transform 0.2s;
            cursor: pointer;
        }
        .document-card:hover {
            transform: translateY(-2px);
        }
        .sidebar {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <h5>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h5>
                <hr>
                <div class="d-grid gap-2">
                    <button class="btn btn-primary" onclick="createDocument()">
                        <i class="fas fa-plus"></i> New Document
                    </button>
                    <?php if(isAdmin()): ?>
                    <a href="admin.php" class="btn btn-outline-secondary">
                        <i class="fas fa-users-cog"></i> Admin Panel
                    </a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-outline-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- Main content -->
            <div class="col-md-9 col-lg-10 p-4">
                <h2>My Documents</h2>
                <hr>
                
                <div class="row">
                    <?php foreach($documents as $doc): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card document-card" onclick="openDocument(<?php echo $doc['id']; ?>)">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($doc['title']); ?></h5>
                                <p class="card-text text-muted">
                                    By: <?php echo htmlspecialchars($doc['author_name']); ?><br>
                                    <small>Updated: <?php echo date('M j, Y g:i A', strtotime($doc['updated_at'])); ?></small>
                                </p>
                                <?php if(isAdmin() && $doc['author_id'] != $_SESSION['user_id']): ?>
                                <span class="badge bg-info">Admin View</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Document Modal -->
    <div class="modal fade" id="createDocModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="createDocForm">
                        <div class="mb-3">
                            <label for="docTitle" class="form-label">Document Title</label>
                            <input type="text" class="form-control" id="docTitle" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitDocument()">Create</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
    function createDocument() {
        new bootstrap.Modal(document.getElementById('createDocModal')).show();
    }

    function submitDocument() {
        const title = document.getElementById('docTitle').value;
        if(!title.trim()) {
            alert('Please enter a document title');
            return;
        }

        // Disable the create button to prevent double-clicking
        const createBtn = document.querySelector('#createDocModal .btn-primary');
        const originalText = createBtn.textContent;
        createBtn.disabled = true;
        createBtn.textContent = 'Creating...';

        fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'create_document',
                title: title.trim()
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('API Response:', data); // Debug log
            
            if(data.success && data.document_id) {
                window.location.href = `editor.php?id=${data.document_id}`;
            } else {
                alert('Error creating document: ' + (data.message || 'Unknown error'));
                // Re-enable the button
                createBtn.disabled = false;
                createBtn.textContent = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error: ' + error.message);
            // Re-enable the button
            createBtn.disabled = false;
            createBtn.textContent = originalText;
        });
    }

    function openDocument(id) {
        window.location.href = `editor.php?id=${id}`;
    }
    </script>
</body>
</html>
