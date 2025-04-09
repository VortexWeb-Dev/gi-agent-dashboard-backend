<?php

require_once __DIR__ . "/../services/CacheService.php";
require_once __DIR__ . "/../services/ResponseService.php";
require_once __DIR__ . "/BitrixController.php";

class PerformanceController extends BitrixController
{
    private CacheService $cache;
    private ResponseService $response;

    public function __construct()
    {
        parent::__construct();
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

        $cacheKey = "performance_" . $id;
        $cached = $this->cache->get($cacheKey);

        if ($cached !== false) {
            $this->response->sendSuccess(200, $cached);
            return;
        }

        $user = $this->getUserById($id);
        if (!$user) {
            $this->response->sendError(404, "User not found");
            return;
        }

        $performanceData = $this->getPerformanceData($id);

        $this->cache->set($cacheKey, $performanceData);
        $this->response->sendSuccess(200, $performanceData);
    }

    private function getUserById(string $id): ?array
    {
        $user = $this->getUser($id);
        if (!$user) {
            return null;
        }
        return $user;
    }

    private function getPerformanceData(string $id): array
    {
        $currentMonth = date('F Y');

        $user = $this->getUserById($id, ['NAME', 'LAST_NAME', 'WORK_POSITION', 'PERSONAL_PHOTO', 'EMAIL', 'UF_SKYPE_LINK', 'UF_ZOOM', 'UF_XING', 'UF_LINKEDIN', 'UF_FACEBOOK', 'UF_TWITTER', 'UF_SKYPE']);
        $userName = trim($user['NAME'] . ' ' . $user['LAST_NAME']) ?? '';
        $userRole = $user['WORK_POSITION'] ?? '';
        $userPhoto = $user['PERSONAL_PHOTO'] ?? '';
        $userEmail = $user['EMAIL'] ?? '';
        $skypeChat = $user['UF_SKYPE_LINK'] ?? '';
        $zoom = $user['UF_ZOOM'] ?? '';
        $xing = $user['UF_XING'] ?? '';
        $linkedin = $user['UF_LINKEDIN'] ?? '';
        $facebook = $user['UF_FACEBOOK'] ?? '';
        $twitter = $user['UF_TWITTER'] ?? '';
        $skype = $user['UF_SKYPE'] ?? '';

        $totalAds = $this->getAllUserAds(['ufCrm37AgentEmail' => $userEmail], ['ufCrm37Status', 'ufCrm37PfEnable', 'ufCrm37BayutEnable', 'ufCrm37DubizzleEnable', 'ufCrm37Price']);
        $publishedAds = array_filter($totalAds, function ($ad) {
            return $ad['ufCrm37Status'] === 'PUBLISHED';
        });
        $liveAds = array_filter($totalAds, function ($ad) {
            return $ad['ufCrm37Status'] === 'LIVE';
        });
        $draftAds = array_filter($totalAds, function ($ad) {
            return $ad['ufCrm37Status'] === 'DRAFT';
        });

        $pfAds = array_filter($totalAds, function ($ad) {
            return $ad['ufCrm37PfEnable'] === 'Y' && $ad['ufCrm37Status'] === 'PUBLISHED';
        });
        $bayutAds = array_filter($totalAds, function ($ad) {
            return $ad['ufCrm37BayutEnable'] === 'Y' && $ad['ufCrm37Status'] === 'PUBLISHED';
        });
        $dubizzleAds = array_filter($totalAds, function ($ad) {
            return $ad['ufCrm37DubizzleEnable'] === 'Y' && $ad['ufCrm37Status'] === 'PUBLISHED';
        });
        $websiteAds = array_filter($totalAds, function ($ad) {
            return $ad['ufCrm37WebsiteEnable'] === 'Y' && $ad['ufCrm37Status'] === 'PUBLISHED';
        });

        $totalWorth = array_sum(array_map(function ($ad) {
            if ($ad['ufCrm37Status'] !== 'PUBLISHED') {
                return 0;
            }

            return $ad['ufCrm37Price'];
        }, $totalAds));

        return [
            'month' => $currentMonth,
            'role' => $userRole,
            'employee' => $userName,
            'employee_photo' => $userPhoto,
            'skype' => $skype,
            'skypeChat' => $skypeChat,
            'zoom' => $zoom,
            'xing' => $xing,
            'linkedin' => $linkedin,
            'facebook' => $facebook,
            'twitter' => $twitter,
            'liveAds' => count($liveAds),
            'totalWorthOfAds' => $totalWorth,
            'publishedAds' => count($publishedAds),
            'draftAds' => count($draftAds),
            'pfAds' => count($pfAds),
            'bayutAds' => count($bayutAds),
            'dubizzleAds' => count($dubizzleAds),
            'websiteAds' => count($websiteAds),
            'totalAds' => count($totalAds)
        ];
    }
}
