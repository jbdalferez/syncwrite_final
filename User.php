<?php
// User.php - User class
class User {
    private $conn;
    private $table = 'users';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($username, $password) {
        if ($this->conn === null) {
            throw new Exception("Database connection is not established.");
        }
        
        $query = "SELECT id, username, email, password, role, is_suspended FROM " . $this->table . " WHERE username = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if($row['is_suspended']) {
                return ['success' => false, 'message' => 'Account is suspended'];
            }
            if(password_verify($password, $row['password'])) {
                return ['success' => true, 'user' => $row];
            }
        }
        
        return ['success' => false, 'message' => 'Invalid credentials'];
    }

    public function register($username, $email, $password) {
        $query = "INSERT INTO " . $this->table . " (username, email, password) VALUES (:username, :email, :password)";
        $stmt = $this->conn->prepare($query);
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashed_password);

        try {
            $stmt->execute();
            return ['success' => true, 'message' => 'User registered successfully'];
        } catch(PDOException $e) {
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }

    public function searchUsers($query) {
        $search = "%{$query}%";
        $stmt = $this->conn->prepare("SELECT id, username, email FROM " . $this->table . " WHERE (username LIKE :query OR email LIKE :query) AND is_suspended = FALSE LIMIT 10");
        $stmt->bindParam(':query', $search);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllUsers() {
        $stmt = $this->conn->prepare("SELECT id, username, email, role, is_suspended, created_at FROM " . $this->table);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function toggleSuspension($user_id, $suspend) {
        $stmt = $this->conn->prepare("UPDATE " . $this->table . " SET is_suspended = :suspend WHERE id = :id");
        $stmt->bindParam(':suspend', $suspend, PDO::PARAM_BOOL);
        $stmt->bindParam(':id', $user_id);
        return $stmt->execute();
    }
}

    
?>