<?php
// Document.php - Document class with improved error handling
class Document {
    private $conn;
    private $table = 'documents';

    public function __construct($db) {
        if ($db === null) {
            throw new Exception("Database connection cannot be null");
        }
        $this->conn = $db;
    }

    public function create($title, $content, $author_id) {
        try {
            $query = "INSERT INTO " . $this->table . " (title, content, author_id) VALUES (:title, :content, :author_id)";
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':author_id', $author_id);

            if($stmt->execute()) {
                $doc_id = $this->conn->lastInsertId();
                $this->logActivity($doc_id, $author_id, 'Document Created', 'Created document: ' . $title);
                return ['success' => true, 'document_id' => $doc_id];
            }
            return ['success' => false, 'message' => 'Failed to create document'];
        } catch(PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function update($document_id, $content, $user_id) {
        try {
            $query = "UPDATE " . $this->table . " SET content = :content, updated_at = NOW() WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':id', $document_id);

            if($stmt->execute()) {
                $this->logActivity($document_id, $user_id, 'Content Updated', 'Document content modified');
                return true;
            }
            return false;
        } catch(PDOException $e) {
            error_log("Document update error: " . $e->getMessage());
            return false;
        }
    }

    public function getDocument($document_id) {
        try {
            $query = "SELECT d.*, u.username as author_name FROM " . $this->table . " d 
                      JOIN users u ON d.author_id = u.id WHERE d.id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $document_id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get document error: " . $e->getMessage());
            return false;
        }
    }

    public function getUserDocuments($user_id, $is_admin = false) {
        try {
            if($is_admin) {
                $query = "SELECT d.*, u.username as author_name FROM " . $this->table . " d 
                          JOIN users u ON d.author_id = u.id ORDER BY d.updated_at DESC";
                $stmt = $this->conn->prepare($query);
            } else {
                $query = "SELECT d.*, u.username as author_name FROM " . $this->table . " d 
                          JOIN users u ON d.author_id = u.id 
                          WHERE d.author_id = :user_id 
                          OR d.id IN (SELECT document_id FROM document_access WHERE user_id = :user_id2)
                          ORDER BY d.updated_at DESC";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->bindParam(':user_id2', $user_id);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get user documents error: " . $e->getMessage());
            return [];
        }
    }

    public function grantAccess($document_id, $user_id, $permission = 'read') {
        try {
            $query = "INSERT INTO document_access (document_id, user_id, permission) VALUES (:doc_id, :user_id, :permission)
                      ON DUPLICATE KEY UPDATE permission = :permission2";
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':doc_id', $document_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':permission', $permission);
            $stmt->bindParam(':permission2', $permission);

            if($stmt->execute()) {
                $this->logActivity($document_id, $user_id, 'Access Granted', 'User granted ' . $permission . ' access');
                return true;
            }
            return false;
        } catch(PDOException $e) {
            error_log("Grant access error: " . $e->getMessage());
            return false;
        }
    }

    public function hasAccess($document_id, $user_id, $required_permission = 'read') {
        try {
            // Check if user is author
            $query = "SELECT author_id FROM " . $this->table . " WHERE id = :doc_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':doc_id', $document_id);
            $stmt->execute();
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($doc && $doc['author_id'] == $user_id) {
                return true;
            }

            // Check document access
            $query = "SELECT permission FROM document_access WHERE document_id = :doc_id AND user_id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':doc_id', $document_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $access = $stmt->fetch(PDO::FETCH_ASSOC);

            if($access) {
                if($required_permission === 'read') return true;
                if($required_permission === 'write' && $access['permission'] === 'write') return true;
            }

            return false;
        } catch(PDOException $e) {
            error_log("Check access error: " . $e->getMessage());
            return false;
        }
    }

    public function getActivityLogs($document_id) {
        try {
            $query = "SELECT al.*, u.username FROM activity_logs al 
                      JOIN users u ON al.user_id = u.id 
                      WHERE al.document_id = :doc_id ORDER BY al.timestamp DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':doc_id', $document_id);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get activity logs error: " . $e->getMessage());
            return [];
        }
    }

    public function getMessages($document_id) {
        try {
            $query = "SELECT m.*, u.username FROM messages m 
                      JOIN users u ON m.user_id = u.id 
                      WHERE m.document_id = :doc_id ORDER BY m.timestamp ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':doc_id', $document_id);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get messages error: " . $e->getMessage());
            return [];
        }
    }

    public function addMessage($document_id, $user_id, $message) {
        try {
            $query = "INSERT INTO messages (document_id, user_id, message) VALUES (:doc_id, :user_id, :message)";
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':doc_id', $document_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':message', $message);

            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Add message error: " . $e->getMessage());
            return false;
        }
    }

    private function logActivity($document_id, $user_id, $action, $details) {
        try {
            $query = "INSERT INTO activity_logs (document_id, user_id, action, details) VALUES (:doc_id, :user_id, :action, :details)";
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':doc_id', $document_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':details', $details);

            $stmt->execute();
        } catch(PDOException $e) {
            error_log("Log activity error: " . $e->getMessage());
            // Don't return false here as this is a private method for logging
        }
    }
}
?>