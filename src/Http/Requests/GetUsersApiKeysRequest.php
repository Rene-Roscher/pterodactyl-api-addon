<?php


namespace Xepare\PterodactylApiAddon\Http\Requests;

use Pterodactyl\Http\Requests\Api\Application\ApplicationApiRequest;
use Pterodactyl\Services\Acl\Api\AdminAcl as Acl;

/**
 * Class GetUsersApiKeysRequest
 * @package Pterodactyl\Http\Requests\Api\Application\Users
 */
class GetUsersApiKeysRequest extends ApplicationApiRequest
{
    /**
     * @var string
     */
    protected $resource = Acl::RESOURCE_USERS;

    /**
     * @var int
     */
    protected $permission = Acl::READ;
}
