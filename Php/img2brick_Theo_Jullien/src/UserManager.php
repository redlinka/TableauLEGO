<?php

class UserManager {
    /** @var PDO */
    private $db;
    /** @var array */
    private $config;

    /**
     * Constructor
     * @param PDO $db The database connection
     */
    public function __construct(PDO $db) {
        $this->db = $db;
        $this->config = require dirname(__DIR__) . "/config.php";
    }

    public function getUserById(int $id) {
        $stmt = $this->db->prepare("SELECT * FROM user WHERE id = :id");
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateProfile(int $id, string $name, string $email, string $address): bool {
        // We check if the email changes to reset the verification
        $user = $this->getUserById($id);
        $isVerified = ($email === $user['email']) ? $user['is_verified'] : 0;

        $stmt = $this->db->prepare("
        UPDATE user 
        SET name = :name, email = :email, shipping_address = :address, is_verified = :is_verified 
        WHERE id = :id
    ");
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":address", $address);
        $stmt->bindValue(":is_verified", $isVerified, PDO::PARAM_INT);
        $stmt->bindParam(":id", $id);

        return $stmt->execute();
    }

    /**
     * Validates password complexity
     * @param string $password
     * @return bool
     */
    public function validatePassword(string $password): bool {
        // Check length
        if (strlen($password) < 12) {
            return false;
        }
        // Check uppercase, lowercase, numbers, special characters and whitespaces
        if (
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[0-9]/', $password) ||
            !preg_match('/[\W_]/', $password) ||
            preg_match('/\s/', $password)
        ) {
            return false;
        }
        return true;
    }

    /**
     * Changes user password with security checks
     * @return bool|string True on success, error code on failure
     */
    public function changePassword(int $userId, string $newPassword, string $confirmPassword) {
        $user = $this->getUserById($userId);
        if (!$user) return "user";

        $oldPassword = $user["password"];
        if ($newPassword !== $confirmPassword) return "match";
        if (!$this->validatePassword($newPassword)) return "password1";
        if (password_verify($newPassword, $oldPassword)) return "password2";

        $hashedPassword = password_hash($newPassword, PASSWORD_ARGON2ID);
        $stmt = $this->db->prepare("UPDATE user SET password = :password WHERE id = :id");
        $stmt->bindParam(":password", $hashedPassword);
        $stmt->bindParam(":id", $userId);
        return $stmt->execute() ? true : "db_error";
    }

    /**
     * Verifies the FriendlyCaptcha response
     * @param string|null $captchaResponse
     * @return bool
     */
    public function verifyCaptcha(?string $captchaResponse): bool {
        if (!$captchaResponse) {
            return false;
        }

        $apiKey = $this->config["FRC_API_KEY"] ?? null;
        $siteKey = $this->config["FRC_SITE_KEY"] ?? null;

        if (!$apiKey || !$siteKey) {
            return false;
        }

        $verifyUrl = "https://global.frcapi.com/api/v2/captcha/siteverify";
        $payload = json_encode([
            "response" => $captchaResponse,
            "sitekey" => $siteKey
        ]);

        $options = [
            "http" => [
                "header" => "Content-type: application/json\r\n" .
                    "X-API-Key: $apiKey\r\n",
                "method" => "POST",
                "content" => $payload
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($verifyUrl, false, $context);

        if ($result === false) {
            return false;
        }
        $resultData = json_decode($result, true);
        return isset($resultData["success"]) && $resultData["success"] === true;
    }

    /**
     * Fetches a user by their email address
     * @param string $email
     * @return array|bool User data array or false if not found
     */
    public function getUserByEmail(string $email) {
        $query = $this->db->prepare("SELECT * FROM user WHERE email = :email");
        $query->bindParam(":email", $email);
        $query->execute();
        return $query->fetch(PDO::FETCH_ASSOC);
    }
}