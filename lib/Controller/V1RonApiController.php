<?php
declare(strict_types=1);

namespace OCA\V1RonTalk\Controller;

use OCA\V1RonTalk\Service\V1RonApiService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Proxies frontend requests to the WordPress V1RonDHM REST API.
 *
 * The Nextcloud Vue frontend calls this endpoint instead of talking
 * directly to WordPress (to keep the API key server-side).
 */
class V1RonApiController extends Controller {

    private V1RonApiService $v1ronApi;
    private IUserSession $userSession;
    private LoggerInterface $logger;

    public function __construct(
        string $appName,
        IRequest $request,
        V1RonApiService $v1ronApi,
        IUserSession $userSession,
        LoggerInterface $logger
    ) {
        parent::__construct($appName, $request);
        $this->v1ronApi = $v1ronApi;
        $this->userSession = $userSession;
        $this->logger = $logger;
    }

    /**
     * POST /api/v1ron/proxy
     *
     * Proxies an arbitrary request to the WordPress V1Ron API.
     *
     * Body: {
     *   endpoint: "characters",
     *   params: { user_id: "alice" },
     *   method: "GET"
     * }
     */
    public function proxy(): JSONResponse {
        $endpoint = $this->request->getParam('endpoint', '');
        $params   = $this->request->getParam('params', []);
        $method   = strtoupper($this->request->getParam('method', 'GET'));

        if (empty($endpoint)) {
            return new JSONResponse(['success' => false, 'error' => 'endpoint required'], 400);
        }

        // Auto-inject the current NC user's ID if not provided
        $user = $this->userSession->getUser();
        if ($user && !isset($params['user_id'])) {
            $params['user_id'] = $user->getUID();
        }

        $result = $this->v1ronApi->proxy($endpoint, $params, $method);
        return new JSONResponse($result, $result['success'] ? 200 : 500);
    }
}
