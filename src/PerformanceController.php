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

        $isYearly = isset($_GET['yearly']) && $_GET['yearly'] === 'true';

        if ($isYearly) {
            $year = date('Y');
            $currentMonth = (int)date('n');

            // Get user info just once
            $user = $this->getUserById($id, [
                'NAME',
                'LAST_NAME',
                'WORK_POSITION',
                'PERSONAL_PHOTO',
                'EMAIL',
                'UF_SKYPE_LINK',
                'UF_ZOOM',
                'UF_XING',
                'UF_LINKEDIN',
                'UF_FACEBOOK',
                'UF_TWITTER',
                'UF_SKYPE'
            ]);

            if (!$user) {
                $this->response->sendError(404, "User not found");
                return;
            }

            $userData = $this->getUserDataArray($user);
            $yearlyPerformance = [];

            for ($month = 1; $month <= $currentMonth; $month++) {
                $key = "performance_month_{$id}_{$year}_{$month}";
                $cached = $this->cache->get($key);

                if ($cached !== false) {
                    $yearlyPerformance = array_merge($yearlyPerformance, $cached);
                    continue;
                }

                $monthData = $this->getMonthlyPerformanceData($id, $month, $year, $user['EMAIL'] ?? '');
                $this->cache->set($key, $monthData);
                $yearlyPerformance = array_merge($yearlyPerformance, $monthData);
            }

            $userData['performance'] = $yearlyPerformance;
            $this->response->sendSuccess(200, $userData);
            return;
        }

        // Default: current month only
        $cacheKey = "performance_" . $id;
        $cached = $this->cache->get($cacheKey);

        if ($cached !== false) {
            $this->response->sendSuccess(200, $cached);
            return;
        }

        $user = $this->getUserById($id, [
            'NAME',
            'LAST_NAME',
            'WORK_POSITION',
            'PERSONAL_PHOTO',
            'EMAIL',
            'UF_SKYPE_LINK',
            'UF_ZOOM',
            'UF_XING',
            'UF_LINKEDIN',
            'UF_FACEBOOK',
            'UF_TWITTER',
            'UF_SKYPE'
        ]);

        if (!$user) {
            $this->response->sendError(404, "User not found");
            return;
        }

        $performanceData = $this->getPerformanceData($id, $user);

        $this->cache->set($cacheKey, $performanceData);
        $this->response->sendSuccess(200, $performanceData);
    }

    private function getUserById(string $id, array $fields = []): ?array
    {
        $user = $this->getUser($id, $fields);
        if (!$user) {
            return null;
        }
        return $user;
    }

    private function getUserDataArray(array $user): array
    {
        return [
            'employee' => trim($user['NAME'] . ' ' . $user['LAST_NAME']) ?? '',
            'role' => $user['WORK_POSITION'] ?? '',
            'employee_photo' => $user['PERSONAL_PHOTO'] ?? '',
            'skype' => $user['UF_SKYPE'] ?? '',
            'skypeChat' => $user['UF_SKYPE_LINK'] ?? '',
            'zoom' => $user['UF_ZOOM'] ?? '',
            'xing' => $user['UF_XING'] ?? '',
            'linkedin' => $user['UF_LINKEDIN'] ?? '',
            'facebook' => $user['UF_FACEBOOK'] ?? '',
            'twitter' => $user['UF_TWITTER'] ?? '',
        ];
    }

    private function getMonthlyPerformanceData(string $id, int $month, int $year, string $userEmail): array
    {
        $date = DateTime::createFromFormat('!m', $month);
        $monthName = $date->format('F');

        // Get and filter ads
        $ads = $this->getAllUserAds(['ufCrm37AgentEmail' => $userEmail], [
            'ufCrm37Status',
            'ufCrm37PfEnable',
            'ufCrm37BayutEnable',
            'ufCrm37DubizzleEnable',
            'ufCrm37WebsiteEnable',
            'ufCrm37Price',
            'createdTime'
        ]);

        // Filter for month/year
        $ads = array_filter($ads, function ($ad) use ($year, $month) {
            if (empty($ad['createdTime'])) return false;

            $created = strtotime($ad['createdTime']);
            return (int)date('Y', $created) === (int)$year && (int)date('n', $created) === (int)$month;
        });

        $published = array_filter($ads, fn($ad) => $ad['ufCrm37Status'] === 'PUBLISHED');
        $live = array_filter($ads, fn($ad) => $ad['ufCrm37Status'] === 'LIVE');
        $draft = array_filter($ads, fn($ad) => $ad['ufCrm37Status'] === 'DRAFT');

        $pf = array_filter($published, fn($ad) => $ad['ufCrm37PfEnable'] === 'Y');
        $bayut = array_filter($published, fn($ad) => $ad['ufCrm37BayutEnable'] === 'Y');
        $dubizzle = array_filter($published, fn($ad) => $ad['ufCrm37DubizzleEnable'] === 'Y');
        $website = array_filter($published, fn($ad) => $ad['ufCrm37WebsiteEnable'] === 'Y');

        $worth = array_sum(array_map(
            fn($ad) =>
            $ad['ufCrm37Status'] === 'PUBLISHED' ? (float)$ad['ufCrm37Price'] : 0,
            $ads
        ));

        return [
            $monthName => [
                'liveAds' => count($live),
                'totalWorthOfAds' => $worth,
                'publishedAds' => count($published),
                'draftAds' => count($draft),
                'pfAds' => count($pf),
                'bayutAds' => count($bayut),
                'dubizzleAds' => count($dubizzle),
                'websiteAds' => count($website),
                'totalAds' => count($ads)
            ]
        ];
    }

    private function getPerformanceData(string $id, array $user = null): array
    {
        $year = date('Y');
        $month = (int)date('n'); // Current month

        if ($user === null) {
            $user = $this->getUserById($id, [
                'NAME',
                'LAST_NAME',
                'WORK_POSITION',
                'PERSONAL_PHOTO',
                'EMAIL',
                'UF_SKYPE_LINK',
                'UF_ZOOM',
                'UF_XING',
                'UF_LINKEDIN',
                'UF_FACEBOOK',
                'UF_TWITTER',
                'UF_SKYPE'
            ]);
        }

        $userData = $this->getUserDataArray($user);
        $monthlyData = $this->getMonthlyPerformanceData($id, $month, $year, $user['EMAIL'] ?? '');
        $userData['performance'] = $monthlyData;

        return $userData;
    }
}
