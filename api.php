<?php
// api.php - API endpoints with enhanced error handling
require_once 'config.php';
require_once 'session.php';
require_once 'User.php';
require_once 'Document.php';

// Add error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

try {
    requireLogin();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Login required: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->connect();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input: ' . json_last_error_msg()]);
    exit;
}

$action = $input['action'] ?? '';

// Log the action for debugging
error_log("API Action: " . $action . " | User ID: " . ($_SESSION['user_id'] ?? 'none'));

switch($action) {
    case 'create_document':
        try {
            // Validate required fields
            if (empty($input['title'])) {
                echo json_encode(['success' => false, 'message' => 'Title is required']);
                break;
            }
            
            if (!isset($_SESSION['user_id'])) {
                echo json_encode(['success' => false, 'message' => 'User not logged in']);
                break;
            }
            
            $document = new Document($db);
            $result = $document->create($input['title'], '', $_SESSION['user_id']);
            
            // Handle different return formats from Document::create()
            if ($result === false || $result === null) {
                echo json_encode(['success' => false, 'message' => 'Failed to create document']);
            } else if (is_numeric($result)) {
                // If create() returns just the document ID
                echo json_encode(['success' => true, 'document_id' => (int)$result]);
            } else if (is_array($result) && isset($result['success'])) {
                // If create() already returns a structured response
                echo json_encode($result);
            } else if (is_array($result) && isset($result['id'])) {
                // If create() returns array with 'id' field
                echo json_encode(['success' => true, 'document_id' => (int)$result['id']]);
            } else {
                // Fallback - assume it's a document ID
                echo json_encode(['success' => true, 'document_id' => (int)$result]);
            }
        } catch (Exception $e) {
            error_log("Document creation error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        break;
        
    case 'update_document':
        try {
            $document = new Document($db);
            $doc_id = $input['document_id'] ?? null;
            
            if (!$doc_id) {
                echo json_encode(['success' => false, 'message' => 'Document ID required']);
                break;
            }
            
            if($document->hasAccess($doc_id, $_SESSION['user_id'], 'write') ||
               $document->getDocument($doc_id)['author_id'] == $_SESSION['user_id']) {
                $success = $document->update($doc_id, $input['content'], $_SESSION['user_id']);
                echo json_encode(['success' => $success]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
            }
        } catch (Exception $e) {
            error_log("Document update error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        break;
        
    case 'search_users':
        try {
            $user = new User($db);
            $query = $input['query'] ?? '';
            $users = $user->searchUsers($query);
            echo json_encode($users);
        } catch (Exception $e) {
            error_log("User search error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Search error: ' . $e->getMessage()]);
        }
        break;
        
    case 'grant_access':
        try {
            $document = new Document($db);
            $doc_id = $input['document_id'] ?? null;
            
            if (!$doc_id) {
                echo json_encode(['success' => false, 'message' => 'Document ID required']);
                break;
            }
            
            if($document->getDocument($doc_id)['author_id'] == $_SESSION['user_id'] || isAdmin()) {
                $success = $document->grantAccess($doc_id, $input['user_id'], $input['permission']);
                echo json_encode(['success' => $success]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
            }
        } catch (Exception $e) {
            error_log("Grant access error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Access error: ' . $e->getMessage()]);
        }
        break;
        
    case 'send_message':
        try {
            $document = new Document($db);
            $doc_id = $input['document_id'] ?? null;
            
            if (!$doc_id) {
                echo json_encode(['success' => false, 'message' => 'Document ID required']);
                break;
            }
            
            if($document->hasAccess($doc_id, $_SESSION['user_id']) || isAdmin()) {
                $success = $document->addMessage($doc_id, $_SESSION['user_id'], $input['message']);
                echo json_encode(['success' => $success]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
            }
        } catch (Exception $e) {
            error_log("Send message error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Message error: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_messages':
        try {
            $document = new Document($db);
            $doc_id = $input['document_id'] ?? null;
            
            if (!$doc_id) {
                echo json_encode([]);
                break;
            }
            
            if($document->hasAccess($doc_id, $_SESSION['user_id']) || isAdmin()) {
                $messages = $document->getMessages($doc_id);
                echo json_encode($messages);
            } else {
                echo json_encode([]);
            }
        } catch (Exception $e) {
            error_log("Get messages error: " . $e->getMessage());
            echo json_encode([]);
        }
        break;
        
    case 'toggle_suspension':
        try {
            if(isAdmin()) {
                $user = new User($db);
                $success = $user->toggleSuspension($input['user_id'], $input['suspend']);
                echo json_encode(['success' => $success]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
            }
        } catch (Exception $e) {
            error_log("Toggle suspension error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Suspension error: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
}
?>