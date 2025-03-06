<?php
namespace App;
require_once __DIR__ . '/../src/Exceptions.php';

use PDO;

class Database
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {

        $fullDbPath = $dbPath !== ':memory:'
            ? realpath(__DIR__ . '/..') ?: getcwd() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dbPath)
            : $dbPath;

        // Open SQLite connection
        try {
            $this->pdo = new \PDO("sqlite:$fullDbPath", null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_PERSISTENT => false,
            ]);
            $this->pdo->exec("PRAGMA synchronous = FULL");
        } catch (\PDOException $e) {
            throw new \RuntimeException("Failed to initialize database at $fullDbPath: " . $e->getMessage());
        }

        $this->initializeTables();
    }

    private function initializeTables(): void
    {
        $schema = file_get_contents(__DIR__ . '/../schema.sql');
        $this->pdo->exec($schema);
    }

    public function getUserIdByUsername(string $username): ?int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetchColumn() ?: null;
    }

    public function createUser(string $username): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO users (username) VALUES (?)");
        $stmt->execute([$username]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getAllGroups(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM groups");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createGroup(string $name, int $userId): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO groups (name, created_by) VALUES (?, ?)");
        $stmt->execute([$name, $userId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function groupExists(int $groupId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM groups WHERE id = ?");
        $stmt->execute([$groupId]);
        return $stmt->fetchColumn() > 0;
    }

    public function joinGroup(int $userId, int $groupId): void
    {
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO group_members (group_id, user_id) VALUES (?, ?)");
        $stmt->execute([$groupId, $userId]);
    }

    public function isUserInGroup(int $userId, int $groupId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$groupId, $userId]);
        return $stmt->fetchColumn() > 0;
    }

    public function sendMessage(int $groupId, int $userId, string $message): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO messages (group_id, user_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$groupId, $userId, $message]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getMessageById(int $messageId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM messages WHERE id = ?");
        $stmt->execute([$messageId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getMessagesByGroup(int $groupId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM messages WHERE group_id = ? ORDER BY timestamp ASC");
        $stmt->execute([$groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}