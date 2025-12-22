<?php
class User {
    public ?int $id = null;
    public string $email;
    private string $passwordHash;
    public string $firstName;
    public string $lastName;
    public function __construct(string $email, string $password, string $firstName = '', string $lastName = '') {
        $this->email = $email;
        $this->passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $this->firstName = $firstName;
        $this->lastName = $lastName;
    }
    public function verifyPassword(string $password): bool {
        return password_verify($password, $this->passwordHash);
    }
}
