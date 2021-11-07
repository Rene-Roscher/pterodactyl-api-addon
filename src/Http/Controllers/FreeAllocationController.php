<?php

namespace Xepare\PterodactylApiAddon\Http\Controllers;

use Pterodactyl\Models\Node;
use Pterodactyl\Transformers\Api\Application\AllocationTransformer;
use Pterodactyl\Http\Controllers\Api\Application\ApplicationApiController;
use Pterodactyl\Http\Requests\Api\Application\Allocations\GetAllocationsRequest;

class FreeAllocationController extends ApplicationApiController
{

    /**
     * Return all free allocations that exist for a given node.
     */
    public function __invoke(GetAllocationsRequest $request, Node $node): array
    {
        $allocations = $node->allocations()->whereNull('server_id')->paginate($request->query('per_page') ?? 50);

        return $this->fractal->collection($allocations)
            ->transformWith($this->getTransformer(AllocationTransformer::class))
            ->toArray();
    }

}
