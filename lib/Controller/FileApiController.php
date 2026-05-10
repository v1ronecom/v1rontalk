<?php
declare(strict_types=1);

namespace OCA\V1RonTalk\Controller;

use OCA\V1RonTalk\Service\FileService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Exposes Nextcloud file operations for V1Ron characters.
 *
 * Characters (via the WordPress LLM) can call these endpoints
 * to read, write, search, and share files within Nextcloud.
 * Each request is scoped to the authenticated user.
 */
class FileApiController extends Controller {

    private FileService $fileService;

    public function __construct(
        string $appName,
        IRequest $request,
        FileService $fileService
    ) {
        parent::__construct($appName, $request);
        $this->fileService = $fileService;
    }

    /**
     * POST /api/file/read — Read a file's content.
     *
     * Body: { user_id: "alice", path: "Documents/report.txt" }
     */
    public function read(): JSONResponse {
        $ncUserId = $this->request->getParam('user_id', '');
        $path     = $this->request->getParam('path', '');

        if (empty($ncUserId) || empty($path)) {
            return new JSONResponse(['success' => false, 'error' => 'user_id and path required'], 400);
        }

        $result = $this->fileService->readFile($ncUserId, $path);
        $status = $result['success'] ? 200 : 404;

        return new JSONResponse($result, $status);
    }

    /**
     * POST /api/file/write — Write content to a file (create or overwrite).
     *
     * Body: { user_id: "alice", path: "Documents/note.txt", content: "Hello..." }
     */
    public function write(): JSONResponse {
        $ncUserId = $this->request->getParam('user_id', '');
        $path     = $this->request->getParam('path', '');
        $content  = $this->request->getParam('content', '');
        $mimeType = $this->request->getParam('mime', 'text/plain');

        if (empty($ncUserId) || empty($path)) {
            return new JSONResponse(['success' => false, 'error' => 'user_id and path required'], 400);
        }

        $result = $this->fileService->writeFile($ncUserId, $path, $content, $mimeType);
        return new JSONResponse($result, $result['success'] ? 200 : 500);
    }

    /**
     * POST /api/file/search — Search for files by name.
     *
     * Body: { user_id: "alice", query: "report", limit: 20 }
     */
    public function search(): JSONResponse {
        $ncUserId = $this->request->getParam('user_id', '');
        $query    = $this->request->getParam('query', '');
        $limit    = (int) $this->request->getParam('limit', 20);

        if (empty($ncUserId) || empty($query)) {
            return new JSONResponse(['success' => false, 'error' => 'user_id and query required'], 400);
        }

        $result = $this->fileService->searchFiles($ncUserId, $query, $limit);
        return new JSONResponse($result);
    }

    /**
     * POST /api/file/list — List directory contents.
     *
     * Body: { user_id: "alice", path: "/Documents" }
     */
    public function list(): JSONResponse {
        $ncUserId = $this->request->getParam('user_id', '');
        $path     = $this->request->getParam('path', '/');

        if (empty($ncUserId)) {
            return new JSONResponse(['success' => false, 'error' => 'user_id required'], 400);
        }

        $result = $this->fileService->listDirectory($ncUserId, $path);
        return new JSONResponse($result);
    }

    /**
     * POST /api/file/share — Create a share link for a file.
     *
     * Body: { user_id: "alice", path: "Documents/report.pdf", editable: false }
     */
    public function share(): JSONResponse {
        $ncUserId = $this->request->getParam('user_id', '');
        $path     = $this->request->getParam('path', '');
        $editable = (bool) $this->request->getParam('editable', false);

        if (empty($ncUserId) || empty($path)) {
            return new JSONResponse(['success' => false, 'error' => 'user_id and path required'], 400);
        }

        $result = $this->fileService->shareFile($ncUserId, $path, $editable);
        return new JSONResponse($result, $result['success'] ? 200 : 500);
    }
}
