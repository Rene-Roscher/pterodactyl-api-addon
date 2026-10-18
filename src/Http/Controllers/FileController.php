<?php

namespace Xepare\PterodactylApiAddon\Http\Controllers;

use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Xepare\PterodactylApiAddon\Http\Requests\WriteFileContentRequest;

class FileController extends ClientApiController
{
    /**
     * @var DaemonFileRepository
     */
    private $fileRepository;

    /**
     * FileController constructor.
     */
    public function __construct(
        DaemonFileRepository $fileRepository,
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
        $file = $request->get('file');

        $this->fileRepository
            ->setServer($server)
            ->putContent($file, $request->get('content') ?: '');

        Activity::event('server:file.write')
            ->subject($server)
            ->property('file', $file)
            ->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }
}
