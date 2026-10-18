<?php

namespace Xepare\PterodactylApiAddon\Http\Requests;

use Pterodactyl\Services\Acl\Api\AdminAcl as Acl;
use Pterodactyl\Http\Requests\Api\Application\ApplicationApiRequest;

/**
 * Class GetUsersApiKeysRequest.
 */
class GetUsersApiKeysRequest extends ApplicationApiRequest
{
    protected ?string $resource = Acl::RESOURCE_USERS;

    protected int $permission = Acl::READ;
}
