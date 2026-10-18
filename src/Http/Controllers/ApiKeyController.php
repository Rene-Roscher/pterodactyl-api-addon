<?php

namespace Xepare\PterodactylApiAddon\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\User;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Api\Application\ApplicationApiController;
use Pterodactyl\Transformers\Api\Client\ApiKeyTransformer;
use Xepare\PterodactylApiAddon\Http\Requests\GetUsersApiKeysRequest;
use Xepare\PterodactylApiAddon\Http\Requests\StoreUsersApiKeyRequest;
use Xepare\PterodactylApiAddon\Http\Requests\WriteUsersApiKeysRequest;

class ApiKeyController extends ApplicationApiController
{
    /**
     * Returns all of the API keys that exist for the given client.
     */
    public function index(GetUsersApiKeysRequest $request, User $user): array
    {
        return $this->fractal->collection(
            $user->apiKeys()->where('key_type', ApiKey::TYPE_ACCOUNT)->get()
        )
            ->transformWith($this->getTransformer(ApiKeyTransformer::class))
            ->toArray();
    }

    /**
     * Store a new API key for a user's account.
     *
     * @throws DisplayException
     */
    public function store(StoreUsersApiKeyRequest $request, User $user): array
    {
        $token = DB::transaction(function () use ($request, $user) {
            if ($user->apiKeys()->lockForUpdate()->count() >= 25) {
                throw new DisplayException('You have reached the account limit for number of API keys.');
            }

            return $user->createToken(
                $request->input('description'),
                $request->input('allowed_ips')
            );
        });

        Activity::event('user:api-key.create')
            ->subject($token->accessToken)
            ->property('identifier', $token->accessToken->identifier)
            ->log();

        return $this->fractal->item($token->accessToken)
            ->transformWith($this->getTransformer(ApiKeyTransformer::class))
            ->addMeta([
                'secret_token' => $token->plainTextToken,
            ])
            ->toArray();
    }

    /**
     * Deletes a given API key.
     */
    public function delete(WriteUsersApiKeysRequest $request, User $user, string $identifier): JsonResponse
    {
        /** @var ApiKey $key */
        $key = $user->apiKeys()
            ->where('key_type', ApiKey::TYPE_ACCOUNT)
            ->where('identifier', $identifier)
            ->firstOrFail();

        Activity::event('user:api-key.delete')
            ->property('identifier', $key->identifier)
            ->log();

        $key->delete();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }
}
