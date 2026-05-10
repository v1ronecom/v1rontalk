<?php
declare(strict_types=1);

namespace OCA\V1RonTalk\Service;

use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IUserManager;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Service for reading, writing, searching, and sharing files in Nextcloud.
 * Characters use this to access user files.
 */
class FileService {

    private IRootFolder $rootFolder;
    private IUserManager $userManager;
    private ShareManager $shareManager;
    private LoggerInterface $logger;

    public function __construct(
        IRootFolder $rootFolder,
        IUserManager $userManager,
        ShareManager $shareManager,
        LoggerInterface $logger
    ) {
        $this->rootFolder = $rootFolder;
        $this->userManager = $userManager;
        $this->shareManager = $shareManager;
        $this->logger = $logger;
    }

    /**
     * Resolve a path like "user_id/path/to/file" or just "/path/to/file"
     * for the current user, or "user_id:path/to/file" format.
     */
    private function resolvePath(string $ncUserId, string $path): array {
        // Support format: "other_user:path/to/file"
        if (str_contains($path, ':')) {
            $parts = explode(':', $path, 2);
            $owner = $parts[0];
            $filePath = $parts[1];
            if ($this->userManager->get($owner)) {
                return [$owner, $filePath];
            }
        }
        return [$ncUserId, $path];
    }

    /**
     * Read a file's content as text.
     * Supported types: text files, PDFs (extracted), documents.
     */
    public function readFile(string $ncUserId, string $path): array {
        try {
            [$owner, $filePath] = $this->resolvePath($ncUserId, $path);
            $userFolder = $this->rootFolder->getUserFolder($owner);
            $node = $userFolder->get($filePath);

            if ($node->getType() !== \OCP\Files\FileInfo::TYPE_FILE) {
                return ['success' => false, 'error' => 'Path is not a file'];
            }

            $file = $node->getContent();
            $mimeType = $node->getMimeType();
            $size = $node->getSize();
            $name = $node->getName();

            // Read file based on type
            $content = '';
            $isText = true;

            if (str_starts_with($mimeType, 'text/') ||
                $mimeType === 'application/json' ||
                $mimeType === 'application/xml' ||
                $mimeType === 'application/yaml' ||
                $mimeType === 'application/javascript' ||
                $mimeType === 'application/csv') {
                $content = $node->getContent();
            } elseif ($mimeType === 'application/pdf') {
                // Extract text from PDF using pdftotext if available
                $content = $this->extractPdfText($node->getStorage()->getLocalFile($node->getInternalPath()));
                if (!$content) {
                    $content = '[PDF file - text extraction not available]';
                    $isText = false;
                }
            } elseif (str_starts_with($mimeType, 'image/')) {
                // Return image URL for reference
                return [
                    'success'  => true,
                    'type'     => 'image',
                    'name'     => $name,
                    'mime'     => $mimeType,
                    'size'     => $size,
                    'download_url' => $this->getDownloadUrl($owner, $filePath),
                ];
            } else {
                // Binary file — return metadata only
                return [
                    'success' => true,
                    'type'    => 'binary',
                    'name'    => $name,
                    'mime'    => $mimeType,
                    'size'    => $size,
                    'note'    => 'Binary file - content not readable as text',
                ];
            }

            return [
                'success' => true,
                'type'    => 'text',
                'name'    => $name,
                'path'    => $filePath,
                'mime'    => $mimeType,
                'size'    => $size,
                'content' => $content,
            ];
        } catch (NotFoundException $e) {
            return ['success' => false, 'error' => 'File not found: ' . $filePath];
        } catch (NotPermittedException $e) {
            return ['success' => false, 'error' => 'Permission denied'];
        } catch (\Exception $e) {
            $this->logger->error('FileService::readFile error: ' . $e->getMessage(), [
                'ncUserId' => $ncUserId,
                'path'     => $path,
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Write content to a file. Creates file if it doesn't exist.
     */
    public function writeFile(string $ncUserId, string $path, string $content, string $mimeType = 'text/plain'): array {
        try {
            [$owner, $filePath] = $this->resolvePath($ncUserId, $path);
            $userFolder = $this->rootFolder->getUserFolder($owner);

            // Ensure parent directory exists
            $dirPath = dirname($filePath);
            if ($dirPath !== '.' && $dirPath !== '/') {
                if (!$userFolder->nodeExists($dirPath)) {
                    $userFolder->newFolder($dirPath);
                }
            }

            if ($userFolder->nodeExists($filePath)) {
                $node = $userFolder->get($filePath);
                if ($node->getType() !== \OCP\Files\FileInfo::TYPE_FILE) {
                    return ['success' => false, 'error' => 'Path exists but is not a file'];
                }
                $node->putContent($content);
                $action = 'updated';
            } else {
                $node = $userFolder->newFile($filePath);
                $node->putContent($content);
                $action = 'created';
            }

            return [
                'success' => true,
                'action'  => $action,
                'path'    => $filePath,
                'size'    => strlen($content),
            ];
        } catch (NotPermittedException $e) {
            return ['success' => false, 'error' => 'Permission denied'];
        } catch (\Exception $e) {
            $this->logger->error('FileService::writeFile error: ' . $e->getMessage(), [
                'ncUserId' => $ncUserId,
                'path'     => $path,
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Search for files matching a query in the user's Nextcloud.
     */
    public function searchFiles(string $ncUserId, string $query, int $limit = 20): array {
        try {
            $userFolder = $this->rootFolder->getUserFolder($ncUserId);
            $results = [];

            $this->searchRecursive($userFolder, $query, $results, $limit, '');

            return [
                'success' => true,
                'files'   => array_slice($results, 0, $limit),
            ];
        } catch (\Exception $e) {
            $this->logger->error('FileService::searchFiles error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function searchRecursive($folder, string $query, array &$results, int $limit, string $path): void {
        if (count($results) >= $limit) return;

        foreach ($folder->getDirectoryListing() as $node) {
            $nodePath = $path ? $path . '/' . $node->getName() : $node->getName();

            if (stripos($node->getName(), $query) !== false) {
                $results[] = [
                    'name' => $node->getName(),
                    'path' => $nodePath,
                    'type' => $node->getType() === \OCP\Files\FileInfo::TYPE_FILE ? 'file' : 'folder',
                    'size' => $node->getSize(),
                    'mime' => $node->getMimeType(),
                ];
                if (count($results) >= $limit) return;
            }

            if ($node->getType() === \OCP\Files\FileInfo::TYPE_FOLDER) {
                // Skip hidden and trash
                $name = $node->getName();
                if (str_starts_with($name, '.') || $name === 'trash' || $name === 'cache') continue;
                $this->searchRecursive($node, $query, $results, $limit, $nodePath);
            }
        }
    }

    /**
     * List files in a directory.
     */
    public function listDirectory(string $ncUserId, string $path = '/'): array {
        try {
            [$owner, $dirPath] = $this->resolvePath($ncUserId, $path);
            $userFolder = $this->rootFolder->getUserFolder($owner);
            $node = $userFolder->get($dirPath);

            if ($node->getType() !== \OCP\Files\FileInfo::TYPE_FOLDER) {
                return ['success' => false, 'error' => 'Path is not a directory'];
            }

            $items = [];
            foreach ($node->getDirectoryListing() as $child) {
                $items[] = [
                    'name' => $child->getName(),
                    'path' => $dirPath . '/' . $child->getName(),
                    'type' => $child->getType() === \OCP\Files\FileInfo::TYPE_FILE ? 'file' : 'folder',
                    'size' => $child->getSize(),
                    'mime' => $child->getMimeType(),
                    'mtime' => $child->getMTime(),
                ];
            }

            return [
                'success' => true,
                'path'    => $dirPath,
                'items'   => $items,
            ];
        } catch (NotFoundException $e) {
            return ['success' => false, 'error' => 'Directory not found'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create a share link for a file.
     */
    public function shareFile(string $ncUserId, string $path, bool $editable = false): array {
        try {
            [$owner, $filePath] = $this->resolvePath($ncUserId, $path);
            $userFolder = $this->rootFolder->getUserFolder($owner);
            $node = $userFolder->get($filePath);

            $share = $this->shareManager->newShare();
            $share->setNode($node);
            $share->setShareType(IShare::TYPE_LINK);
            $share->setPermissions(
                $editable
                    ? \OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_UPDATE
                    : \OCP\Constants::PERMISSION_READ
            );
            $share->setLabel('V1Ron Character Share');
            $share = $this->shareManager->createShare($share);

            $token = $share->getToken();
            $link = $this->getBaseUrl() . '/s/' . $token;

            return [
                'success' => true,
                'url'     => $link,
                'token'   => $token,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function getBaseUrl(): string {
        return \OC::$server->get(\OCP\IURLGenerator::class)->getBaseUrl();
    }

    private function getDownloadUrl(string $userId, string $path): string {
        $urlGen = \OC::$server->get(\OCP\IURLGenerator::class);
        return $urlGen->linkToRoute('files.view.showFile', [
            'fileid' => base64_encode($userId . ':' . $path),
        ]);
    }

    /**
     * Extract text from a PDF file using pdftotext or a simple fallback.
     */
    private function extractPdfText(string $localPath): string {
        // Try shell pdftotext
        $escapedPath = escapeshellarg($localPath);
        $output = shell_exec("pdftotext {$escapedPath} - 2>/dev/null");
        if ($output && strlen(trim($output)) > 0) {
            return $output;
        }

        // If pdftotext is not available, try PHP's built-in approach
        if (class_exists('\Smalot\PdfParser\Parser')) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($localPath);
                return $pdf->getText();
            } catch (\Exception $e) {
                // Fall through
            }
        }

        // Last resort: raw text extraction
        $content = file_get_contents($localPath);
        if ($content === false) return '';

        // Simple regex to extract text between PDF text objects
        $text = '';
        if (preg_match_all('/\((.*?)\)\s*Tj/', $content, $matches)) {
            $text = implode(' ', $matches[1]);
        }

        return $text ?: '[Could not extract text from PDF]';
    }
}
