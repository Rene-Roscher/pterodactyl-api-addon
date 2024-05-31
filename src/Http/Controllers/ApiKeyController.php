<?php

namespace Xepare\PterodactylApiAddon\Http\Controllers;

use Pterodactyl\Http\Controllers\Api\Application\ApplicationApiController;
use Xepare\PterodactylApiAddon\Http\Requests\GetUsersApiKeysRequest;
use Pterodactyl\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Api\KeyCreationService;
use Pterodactyl\Repositories\Eloquent\ApiKeyRepository;
use Pterodactyl\Transformers\Api\Client\ApiKeyTransformer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\Http\Requests\Api\Client\Account\StoreApiKeyRequest;

class ApiKeyController extends ApplicationApiController
{
    /**
     * Returns all of the API keys that exist for the given client.
     *
     * @return array
     */
    public function index(GetUsersApiKeysRequest $request, User $user)
    {
        dd($user);
        return $this->fractal->collection($user->apiKeys)
            ->transformWith($this->getTransformer(ApiKeyTransformer::class))
            ->toArray();
    }

    /**
     * Store a new API key for a user's account.
     *
     * @return array
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function store(User $user)
    {
                dd($user);
        $token = $user->createToken(
            request()->input('description'),
            request()->input('allowed_ips')
        );
        
        return $this->fractal->item($token->accessToken)
            ->transformWith($this->getTransformer(ApiKeyTransformer::class))
            ->addMeta([
                'secret_token' => $token->plainTextToken
            ])
            ->toArray();
    }

    /**
     * Deletes a given API key.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(GetUsersApiKeysRequest $request, User $user, string $identifier)
    {
        $key = $user->apiKeys()
            ->where('key_type', ApiKey::TYPE_ACCOUNT)
            ->where('identifier', $identifier)
            ->firstOrFail();
        
        $key->delete();

        return JsonResponse::create([], JsonResponse::HTTP_NO_CONTENT);
    }
}
