<?php

namespace App;

require_once __DIR__ . '/../src/Exceptions.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MessageController
{
    private Database $db;
    use ResponseTrait;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * INSERT MESSAGE WITH GROUP, USER IN TABLE.
     * @throws ForbiddenException
     * @throws BadRequestException
     * @throws DatabaseException
     * @throws NotFoundException
     */
    public function sendMessage(Request $request, Response $response, array $args): Response
    {
        $groupId = (int)$args['group_id'];
        if (!$this->db->groupExists($groupId)) {
            throw new NotFoundException('Group not found', 404);
        }

        $userId = $request->getAttribute('user_id');
        if (!$this->db->isUserInGroup($userId, $groupId)) {
            throw new ForbiddenException('User must join the group to send messages', 403);
        }

        $data = $request->getParsedBody();
        if (!$this->validateString($data['message'] ?? null)) {
            throw new BadRequestException('Message is required and must be a non-empty string', 400);
        }

        try {
            $messageId = $this->db->sendMessage($groupId, $userId, $data['message']);
            $message = $this->db->getMessageById($messageId);
        } catch (\PDOException $e) {
            throw new DatabaseException('Failed to send message: ' . $e->getMessage(), 500, $e);
        }

        return $this->jsonResponse($response, $message, 201);
    }

    /**
     * FETCH ALL MESSAGES OF GROUP.
     * @throws NotFoundException
     * @throws DatabaseException
     */
    public function listMessages(Request $request, Response $response, array $args): Response
    {
        $groupId = (int)$args['group_id'];
        if (!$this->db->groupExists($groupId)) {
            throw new NotFoundException('Group not found', 404);
        }

        try {
            $messages = $this->db->getMessagesByGroup($groupId);
        } catch (\PDOException $e) {
            throw new DatabaseException('Failed to list messages: ' . $e->getMessage(), 500, $e);
        }

        return $this->jsonResponse($response, $messages);
    }

    private function validateString(?string $value): bool
    {
        return isset($value) && is_string($value) && !empty(trim($value));
    }
}