<?php

require_once __DIR__ . "/../crest/crest.php";
require_once __DIR__ . "/../services/CacheService.php";
require_once __DIR__ . "/../services/ResponseService.php";

class RankingController
{
    private CacheService $cache;
    private ResponseService $response;

    public function __construct()
    {
        $this->cache = new CacheService(300);
        $this->response = new ResponseService();
    }

    public function processRequest(string $method, ?string $id): void
    {
        if ($method !== 'GET') {
            $this->response->sendError(405, "Method Not Allowed");
            return;
        }

        if (!$id) {
            $this->response->sendError(400, "Missing required parameter 'id'");
            return;
        }

        if (!is_numeric($id)) {
            $this->response->sendError(400, "Parameter 'id' must be a number");
            return;
        }

        $cacheKey = "ranking_" . $id;
        $cached = $this->cache->get($cacheKey);

        if ($cached !== false) {
            $this->response->sendSuccess(200, $cached);
            return;
        }

        $data = [
            'id' => $id,
            'message' => 'Ranking data retrieved successfully.',
            'timestamp' => time()
        ];

        $this->cache->set($cacheKey, $data);
        $this->response->sendSuccess(200, $data);
    }
}
