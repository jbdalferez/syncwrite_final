<?php
// editor.php - Document Editor
require_once 'config.php';
require_once 'session.php';
require_once 'Document.php';

requireLogin();

$doc_id = $_GET['id'] ?? 0;
if(!$doc_id) {
    header('Location: index.php');
    exit();
}

$database = new Database();
$db = $database->connect();
$document = new Document($db);

// Check access
if(!$document->hasAccess($doc_id, $_SESSION['user_id']) && !isAdmin()) {
    header('Location: index.php');
    exit();
}

$doc = $document->getDocument($doc_id);
if(!$doc) {
    header('Location: index.php');
    exit();
}

$can_edit = $document->hasAccess($doc_id, $_SESSION['user_id'], 'write') || $doc['author_id'] == $_SESSION['user_id'] || isAdmin();
$activity_logs = $document->getActivityLogs($doc_id);
$messages = $document->getMessages($doc_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($doc['title']); ?> - Document Editor</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.0.0/tinymce.min.js"></script>
    <style>
        .editor-container { 
            min-height: 500px; 
            position: relative;
        }
        .sidebar-panel { 
            max-height: 400px; 
            overflow-y: auto; 
        }
        .message-input { 
            position: sticky; 
            bottom: 0; 
            background: white; 
            padding: 10px; 
            border-top: 1px solid #dee2e6; 
            margin-top: auto;
        }
        .save-indicator {
            position: fixed;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: 3px;
            z-index: 9999;
            font-size: 12px;
            transition: opacity 0.3s;
        }
        .save-indicator.saving {
            background: #ffc107;
            color: #212529;
        }
        .save-indicator.saved {
            background: #28a745;
            color: white;
        }
        .save-indicator.error {
            background: #dc3545;
            color: white;
        }
        .messages-container {
            display: flex;
            flex-direction: column;
            height: 500px;
        }
        .messages-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }
        .message-item {
            margin-bottom: 10px;
            padding: 8px;
            border-radius: 5px;
            background: #f8f9fa;
        }
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        .user-result {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
            background: #f8f9fa;
        }
        .activity-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .panel-header {
            position: sticky;
            top: 0;
            background: white;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            margin: -15px -15px 15px -15px;
            z-index: 10;
        }
    </style>
</head>
<body>
    <!-- Save Status Indicator -->
    <div id="saveIndicator" class="save-indicator" style="display: none;"></div>

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-file-alt"></i> <?php echo htmlspecialchars($doc['title']); ?>
                </span>
                <?php if($doc['author_id'] == $_SESSION['user_id'] || isAdmin()): ?>
                <button class="btn btn-outline-light btn-sm me-2" onclick="showUserSearch()" title="Share Document">
                    <i class="fas fa-user-plus"></i> Share
                </button>
                <?php endif; ?>
                <button class="btn btn-outline-light btn-sm me-2" onclick="toggleActivityLog()" title="View Activity Log">
                    <i class="fas fa-history"></i> Activity
                </button>
                <button class="btn btn-outline-light btn-sm" onclick="toggleMessages()" title="View Messages">
                    <i class="fas fa-comments"></i> Messages
                    <span id="messagesBadge" class="badge bg-danger ms-1" style="display: none;">0</span>
                </button>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Main Editor -->
            <div class="col-md-8 p-4">
                <div class="editor-container">
                    <textarea id="editor"><?php echo htmlspecialchars($doc['content']); ?></textarea>
                    <?php if(!$can_edit): ?>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-lock"></i> You have read-only access to this document.
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div class="col-md-4 border-start" style="height: 100vh; overflow-y: auto;">
                <!-- User Search Panel -->
                <div id="userSearchPanel" class="p-3" style="display: none;">
                    <div class="panel-header">
                        <h5 class="mb-0"><i class="fas fa-user-plus"></i> Share Document</h5>
                    </div>
                    <div class="mb-3">
                        <input type="text" class="form-control" id="userSearch" placeholder="Search users by username or email..." onkeyup="searchUsers()" autocomplete="off">
                        <div id="searchResults" class="mt-3"></div>
                    </div>
                    
                    <!-- Current Access List -->
                    <div class="mt-4">
                        <h6>Current Access</h6>
                        <div id="currentAccess">
                            <div class="text-muted">Loading...</div>
                        </div>
                    </div>
                </div>

                <!-- Activity Log Panel -->
                <div id="activityPanel" class="p-3" style="display: none;">
                    <div class="panel-header">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Activity Log</h5>
                    </div>
                    <div class="sidebar-panel">
                        <?php if(empty($activity_logs)): ?>
                        <div class="text-muted text-center py-3">No activity yet</div>
                        <?php else: ?>
                        <?php foreach($activity_logs as $log): ?>
                        <div class="activity-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                                    <div class="text-muted small"><?php echo htmlspecialchars($log['action']); ?></div>
                                    <?php if(!empty($log['details'])): ?>
                                    <div class="text-muted small"><?php echo htmlspecialchars($log['details']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted"><?php echo date('M j, g:i A', strtotime($log['timestamp'])); ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Messages Panel -->
                <div id="messagesPanel" class="p-3" style="display: none;">
                    <div class="panel-header">
                        <h5 class="mb-0"><i class="fas fa-comments"></i> Messages</h5>
                    </div>
                    <div class="messages-container">
                        <div class="messages-list" id="messagesList">
                            <?php if(empty($messages)): ?>
                            <div class="text-muted text-center py-3">No messages yet</div>
                            <?php else: ?>
                            <?php foreach($messages as $message): ?>
                            <div class="message-item">
                                <div class="message-header">
                                    <strong><?php echo htmlspecialchars($message['username']); ?></strong>
                                    <small class="text-muted"><?php echo date('M j, g:i A', strtotime($message['timestamp'])); ?></small>
                                </div>
                                <div><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="message-input">
                            <div class="input-group">
                                <input type="text" class="form-control" id="messageInput" placeholder="Type a message..." maxlength="500">
                                <button class="btn btn-primary" onclick="sendMessage()" id="sendButton">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        let saveTimeout;
        let currentPanel = null;
        let messageCount = 0;
        let isEditorInitialized = false;

        // Initialize TinyMCE
        tinymce.init({
            selector: '#editor',
            plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount autosave',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat | wordcount',
            height: 500,
            readonly: <?php echo $can_edit ? 'false' : 'true'; ?>,
            autosave_interval: '30s',
            autosave_prefix: 'doc_<?php echo $doc_id; ?>_',
            setup: function (editor) {
                editor.on('init', function() {
                    isEditorInitialized = true;
                });
                
                editor.on('input', function () {
                    if (isEditorInitialized && <?php echo $can_edit ? 'true' : 'false'; ?>) {
                        clearTimeout(saveTimeout);
                        showSaveStatus('Saving...', 'saving');
                        saveTimeout = setTimeout(autoSave, 2000);
                    }
                });
            }
        });

        function autoSave() {
            if (!isEditorInitialized) return;
            
            const content = tinymce.get('editor').getContent();
            
            fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'update_document',
                    document_id: <?php echo $doc_id; ?>,
                    content: content
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    showSaveStatus('Saved', 'saved');
                } else {
                    showSaveStatus('Save failed', 'error');
                }
            })
            .catch(error => {
                console.error('Save error:', error);
                showSaveStatus('Save failed', 'error');
            });
        }

        function showSaveStatus(message, type) {
            const indicator = document.getElementById('saveIndicator');
            indicator.textContent = message;
            indicator.className = `save-indicator ${type}`;
            indicator.style.display = 'block';
            
            if (type === 'saved' || type === 'error') {
                setTimeout(() => {
                    indicator.style.display = 'none';
                }, 3000);
            }
        }

        function showUserSearch() {
            togglePanel('userSearchPanel');
            if(currentPanel === 'userSearchPanel') {
                loadCurrentAccess();
            }
        }

        function toggleActivityLog() {
            togglePanel('activityPanel');
        }

        function toggleMessages() {
            togglePanel('messagesPanel');
            if(currentPanel === 'messagesPanel') {
                loadMessages();
                messageCount = 0;
                updateMessagesBadge();
            }
        }

        function togglePanel(panelId) {
            const panels = ['userSearchPanel', 'activityPanel', 'messagesPanel'];
            panels.forEach(id => {
                document.getElementById(id).style.display = 'none';
            });
            
            if(currentPanel === panelId) {
                currentPanel = null;
            } else {
                document.getElementById(panelId).style.display = 'block';
                currentPanel = panelId;
            }
        }

        function searchUsers() {
            const query = document.getElementById('userSearch').value.trim();
            if(query.length < 2) {
                document.getElementById('searchResults').innerHTML = '';
                return;
            }

            fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'search_users',
                    query: query,
                    document_id: <?php echo $doc_id; ?>
                })
            })
            .then(response => response.json())
            .then(data => {
                let html = '';
                if (data.length === 0) {
                    html = '<div class="text-muted text-center py-2">No users found</div>';
                } else {
                    data.forEach(user => {
                        html += `
                            <div class="user-result">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong>${escapeHtml(user.username)}</strong>
                                        <div class="small text-muted">${escapeHtml(user.email)}</div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="grantAccess(${user.id}, 'read')">
                                        <i class="fas fa-eye"></i> Read
                                    </button>
                                    <button class="btn btn-sm btn-primary" onclick="grantAccess(${user.id}, 'write')">
                                        <i class="fas fa-edit"></i> Write
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                }
                document.getElementById('searchResults').innerHTML = html;
            })
            .catch(error => {
                console.error('Search error:', error);
                document.getElementById('searchResults').innerHTML = '<div class="text-danger">Search failed</div>';
            });
        }

        function grantAccess(userId, permission) {
            fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'grant_access',
                    document_id: <?php echo $doc_id; ?>,
                    user_id: userId,
                    permission: permission
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    showAlert('Access granted successfully!', 'success');
                    document.getElementById('userSearch').value = '';
                    document.getElementById('searchResults').innerHTML = '';
                    loadCurrentAccess();
                } else {
                    showAlert(data.message || 'Failed to grant access', 'error');
                }
            })
            .catch(error => {
                console.error('Grant access error:', error);
                showAlert('Failed to grant access', 'error');
            });
        }

        function loadCurrentAccess() {
            fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'get_document_access',
                    document_id: <?php echo $doc_id; ?>
                })
            })
            .then(response => response.json())
            .then(data => {
                let html = '';
                if (data.length === 0) {
                    html = '<div class="text-muted">No shared access</div>';
                } else {
                    data.forEach(access => {
                        html += `
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <div>
                                    <strong>${escapeHtml(access.username)}</strong>
                                    <div class="small text-muted">${escapeHtml(access.email)}</div>
                                </div>
                                <div>
                                    <span class="badge bg-${access.permission === 'write' ? 'primary' : 'secondary'}">${access.permission}</span>
                                    <button class="btn btn-sm btn-outline-danger ms-1" onclick="revokeAccess(${access.user_id})" title="Remove Access">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                }
                document.getElementById('currentAccess').innerHTML = html;
            });
        }

        function revokeAccess(userId) {
            if (!confirm('Are you sure you want to revoke access for this user?')) {
                return;
            }

            fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'revoke_access',
                    document_id: <?php echo $doc_id; ?>,
                    user_id: userId
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    showAlert('Access revoked successfully!', 'success');
                    loadCurrentAccess();
                } else {
                    showAlert(data.message || 'Failed to revoke access', 'error');
                }
            });
        }

        function sendMessage() {
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            
            if(!message) return;

            const sendButton = document.getElementById('sendButton');
            sendButton.disabled = true;

            fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'send_message',
                    document_id: <?php echo $doc_id; ?>,
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    messageInput.value = '';
                    loadMessages();
                } else {
                    showAlert(data.message || 'Failed to send message', 'error');
                }
            })
            .catch(error => {
                console.error('Send message error:', error);
                showAlert('Failed to send message', 'error');
            })
            .finally(() => {
                sendButton.disabled = false;
            });
        }

        function loadMessages() {
            fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'get_messages',
                    document_id: <?php echo $doc_id; ?>
                })
            })
            .then(response => response.json())
            .then(data => {
                let html = '';
                if (data.length === 0) {
                    html = '<div class="text-muted text-center py-3">No messages yet</div>';
                } else {
                    data.forEach(message => {
                        const date = new Date(message.timestamp);
                        html += `
                            <div class="message-item">
                                <div class="message-header">
                                    <strong>${escapeHtml(message.username)}</strong>
                                    <small class="text-muted">${date.toLocaleDateString()} ${date.toLocaleTimeString()}</small>
                                </div>
                                <div>${escapeHtml(message.message).replace(/\n/g, '<br>')}</div>
                            </div>
                        `;
                    });
                }
                document.getElementById('messagesList').innerHTML = html;
                
                // Scroll to bottom
                const messagesList = document.getElementById('messagesList');
                messagesList.scrollTop = messagesList.scrollHeight;
            })
            .catch(error => {
                console.error('Load messages error:', error);
            });
        }

        function updateMessagesBadge() {
            const badge = document.getElementById('messagesBadge');
            if (messageCount > 0) {
                badge.textContent = messageCount;
                badge.style.display = 'inline';
            } else {
                badge.style.display = 'none';
            }
        }

        function showAlert(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show`;
            alertDiv.style.cssText = 'position: fixed; top: 60px; right: 10px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            `;
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                if (alertDiv.parentElement) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Allow Enter key to send messages
        document.getElementById('messageInput').addEventListener('keypress', function(e) {
            if(e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // Check for new messages every 30 seconds
        setInterval(() => {
            if(currentPanel === 'messagesPanel') {
                loadMessages();
            } else {
                // Check for new messages and update badge
                fetch('api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'get_message_count',
                        document_id: <?php echo $doc_id; ?>
                    })
                })
                .then(response => response.json())
                .then(data => {
                    const newCount = data.count - messageCount;
                    if (newCount > 0) {
                        messageCount = data.count;
                        updateMessagesBadge();
                    }
                });
            }
        }, 30000);

        // Initialize message count
        messageCount = <?php echo count($messages); ?>;

        // Auto-focus message input when panel opens
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.target.id === 'messagesPanel' && mutation.target.style.display !== 'none') {
                    setTimeout(() => {
                        document.getElementById('messageInput').focus();
                    }, 100);
                }
            });
        });

        observer.observe(document.getElementById('messagesPanel'), {
            attributes: true,
            attributeFilter: ['style']
        });
    </script>
</body>
</html>