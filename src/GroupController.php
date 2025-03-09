<?php

namespace App;

require_once __DIR__ . '/../src/Exceptions.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class GroupController
{
    private Database $db;
    use ResponseTrait;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Lists all groups.
     * @throws DatabaseException
     */
    public function listGroups(Request $request, Response $response, array $args): Response
    {
        try {
            $groups = $this->db->getAllGroups();
        } catch (\PDOException $e) {
            throw new DatabaseException('Failed to list groups: ' . $e->getMessage(), 500, $e);
        }
        return $this->jsonResponse($response, $groups);
    }

    /**
     * Creates group.
     * @throws BadRequestException
     * @throws DatabaseException
     */
    public function createGroup(Request $request, Response $response, array $args): Response
    {
        $data = (array)$request->getParsedBody();
        $name = $data['name'] ?? '';
        if (empty($name) || !is_string($name) || trim($name) === '') {
            throw new BadRequestException('Group name is required and must be a non-empty string', 400);
        }

        $userId = $request->getAttribute('user_id');
        try {
            $groupId = $this->db->createGroup($data['name'], $userId);
        } catch (\PDOException $e) {
            throw new DatabaseException('Failed to create group: ' . $e->getMessage(), 500, $e);
        }

        $group = ['id' => $groupId, 'name' => $data['name']];
        return $this->jsonResponse($response, $group, 201);
    }

    /**
     * Add user to group.
     * @throws NotFoundException
     * @throws DatabaseException
     */
    public function joinGroup(Request $request, Response $response, array $args): Response
    {
        $groupId = (int)$args['group_id'];
        if (!$this->db->groupExists($groupId)) {
            throw new NotFoundException('Group not found', 404);
        }

        $userId = $request->getAttribute('user_id');
        try {
            $this->db->joinGroup($userId, $groupId);
        } catch (\PDOException $e) {
            throw new DatabaseException('Failed to join group: ' . $e->getMessage(), 500, $e);
        }

        return $this->jsonResponse($response, ['status' => 'joined']);
    }
}