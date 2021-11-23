<?php

namespace Xepare\PterodactylApiAddon\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Services\Nodes\NodeJWTService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Transformers\Daemon\FileObjectTransformer;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\CopyFileRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\PullFileRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\ListFilesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\ChmodFilesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\DeleteFileRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\RenameFileRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\CreateFolderRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\CompressFilesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\DecompressFilesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\GetFileContentsRequest;
use Xepare\PterodactylApiAddon\Http\Requests\WriteFileContentRequest;

class FileController extends ClientApiController
{
    /**
     * @var \Pterodactyl\Repositories\Wings\DaemonFileRepository
     */
    private $fileRepository;

    /**
     * FileController constructor.
     */
    public function __construct(
        DaemonFileRepository $fileRepository
    ) {
        parent::__construct();

        $this->fileRepository = $fileRepository;
    }

    /**
     * Writes the contents of the specified file to the server.
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function __invoke(WriteFileContentRequest $request, Server $server): JsonResponse
    {
        $server->audit(AuditLog::SERVER__FILESYSTEM_WRITE, function (AuditLog $audit, Server $server) use ($request) {
            $audit->subaction = 'write_content';
            $audit->metadata = ['file' => $request->get('file')];

            $this->fileRepository
                ->setServer($server)
                ->putContent($request->get('file'), $request->get('content') ?: '');
        });

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

}
