<?php
/**
 * Authentication and Session Management
 */

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

class Auth {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Register new user
     */
    public function register($username, $email, $password, $firstName, $lastName, $phone = null, $role = 'user') {
        try {
            // Check if user already exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }

            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password_hash, first_name, last_name, phone, role) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([$username, $email, $passwordHash, $firstName, $lastName, $phone, $role]);
            
            return ['success' => true, 'message' => 'User registered successfully', 'user_id' => $this->db->lastInsertId()];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }

    /**
     * Register new user with profile photo
     */
    public function registerWithPhoto($username, $email, $password, $firstName, $lastName, $phone = null, $role = 'user', $profilePhoto = null) {
        try {
            // Check if user already exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }

            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user with profile photo
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password_hash, first_name, last_name, phone, role, profile_photo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([$username, $email, $passwordHash, $firstName, $lastName, $phone, $role, $profilePhoto]);
            
            return ['success' => true, 'message' => 'User registered successfully', 'user_id' => $this->db->lastInsertId()];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }

    /**
     * Register new user with email verification (email_verified = 0)
     */
    public function registerWithEmailVerification($username, $email, $password, $firstName, $lastName, $phone = null, $role = 'user', $profilePhoto = null) {
        try {
            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user with email_verified = 0
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password_hash, first_name, last_name, phone, role, profile_photo, email_verified) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
            ");
            
            $stmt->execute([$username, $email, $passwordHash, $firstName, $lastName, $phone, $role, $profilePhoto]);
            
            return ['success' => true, 'message' => 'User registered successfully', 'user_id' => $this->db->lastInsertId()];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }

    /**
     * Login user
     */
    public function login($username, $password) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, email, password_hash, first_name, last_name, role, email_verified 
                FROM users 
                WHERE username = ? OR email = ?
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Set session variables (no email verification check for login)
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['login_time'] = time();

                return ['success' => true, 'message' => 'Login successful', 'user' => $user];
            } else {
                return ['success' => false, 'message' => 'Invalid username or password'];
            }
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Login failed: ' . $e->getMessage()];
        }
    }

    /**
     * Logout user
     */
    public function logout() {
        session_unset();
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['login_time']) && 
               (time() - $_SESSION['login_time'] < SESSION_LIFETIME);
    }

    /**
     * Get current user
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }

        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Check if user has role
     */
    public function hasRole($role) {
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }

    /**
     * Require login (redirect if not logged in)
     */
    public function requireLogin($redirectUrl = '/login.php') {
        if (!$this->isLoggedIn()) {
            header("Location: " . BASE_URL . $redirectUrl);
            exit();
        }
    }

    /**
     * Require specific role
     */
    public function requireRole($role, $redirectUrl = '/index.php') {
        $this->requireLogin();
        if (!$this->hasRole($role)) {
            header("Location: " . BASE_URL . $redirectUrl);
            exit();
        }
    }
}

// Initialize auth instance
$auth = new Auth();
?>
