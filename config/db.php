<?php
// config/db.php - Database connection file

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', ''); // Default WAMP password is empty
define('DB_NAME', 'pet_adoption_care_guide');
define('DB_CHARSET', 'utf8mb4');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

class Database {
    private $connection;
    private static $instance = null;
    
    // Private constructor to prevent direct instantiation
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->connection = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
            
        } catch (PDOException $e) {
            die("Database Connection Failed: " . $e->getMessage());
        }
    }
    
    // Get singleton instance
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Get PDO connection
    public function getConnection() {
        return $this->connection;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization - MUST be public
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Function to get database connection (for convenience)
function getDB() {
    return Database::getInstance()->getConnection();
}

// Test connection function
function testConnection() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT 1");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Common database helper functions
class DBHelper {
    
    // Execute a query and return results
    public static function select($query, $params = []) {
        try {
            $db = getDB();
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Database Select Error: " . $e->getMessage());
            return false;
        }
    }
    
    // Execute a query and return single row
    public static function selectOne($query, $params = []) {
        try {
            $db = getDB();
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Database SelectOne Error: " . $e->getMessage());
            return false;
        }
    }
    
    // Insert data and return last insert ID
    public static function insert($query, $params = []) {
        try {
            $db = getDB();
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            return $db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Database Insert Error: " . $e->getMessage());
            return false;
        }
    }
    
    // Update/Delete and return affected rows
    public static function execute($query, $params = []) {
        try {
            $db = getDB();
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Database Execute Error: " . $e->getMessage());
            return false;
        }
    }
    
    // Check if record exists
    public static function exists($table, $conditions = []) {
        try {
            $whereClause = '';
            $params = [];
            
            if (!empty($conditions)) {
                $whereClause = ' WHERE ' . implode(' AND ', array_map(function($key) {
                    return "$key = :$key";
                }, array_keys($conditions)));
                $params = $conditions;
            }
            
            $query = "SELECT COUNT(*) as count FROM $table" . $whereClause;
            $result = self::selectOne($query, $params);
            
            return $result && $result['count'] > 0;
        } catch (Exception $e) {
            error_log("Database Exists Error: " . $e->getMessage());
            return false;
        }
    }
    
    // Get total count from table
    public static function count($table, $conditions = []) {
        try {
            $whereClause = '';
            $params = [];
            
            if (!empty($conditions)) {
                $whereClause = ' WHERE ' . implode(' AND ', array_map(function($key) {
                    return "$key = :$key";
                }, array_keys($conditions)));
                $params = $conditions;
            }
            
            $query = "SELECT COUNT(*) as count FROM $table" . $whereClause;
            $result = self::selectOne($query, $params);
            
            return $result ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            error_log("Database Count Error: " . $e->getMessage());
            return 0;
        }
    }
}

// Security helper functions
class Security {
    
    // Sanitize input
    public static function sanitize($input) {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    // Validate email
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    // Hash password
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    // Verify password
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    // Generate random token
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
}

// Session helper functions
class Session {
    
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public static function set($key, $value) {
        self::start();
        $_SESSION[$key] = $value;
    }
    
    public static function get($key, $default = null) {
        self::start();
        return $_SESSION[$key] ?? $default;
    }
    
    public static function has($key) {
        self::start();
        return isset($_SESSION[$key]);
    }
    
    public static function remove($key) {
        self::start();
        unset($_SESSION[$key]);
    }
    
    public static function destroy() {
        self::start();
        session_destroy();
    }
    
    public static function isLoggedIn() {
        return self::has('user_id') && self::has('user_type');
    }
    
    public static function getUserId() {
        return self::get('user_id');
    }
    
    public static function getUserType() {
        return self::get('user_type');
    }
}

// Initialize session
Session::start();

// Optional: Test connection on include (comment out in production)
/*
if (!testConnection()) {
    die("Failed to connect to database. Please check your configuration.");
}
*/
?>