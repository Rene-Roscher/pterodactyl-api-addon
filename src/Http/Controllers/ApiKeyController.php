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
     * @var \Pterodactyl\Services\Api\KeyCreationService
     */
    //private $keyCreationService;

    /**
     * @var \Illuminate\Contracts\Encryption\Encrypter
     */
    //private $encrypter;

    /**
     * @var \Pterodactyl\Repositories\Eloquent\ApiKeyRepository
     */
    //private $repository;

    /**
     * ApiKeyController constructor.
     */
    //public function __construct(
    //    Encrypter $encrypter,
    //    KeyCreationService $keyCreationService,
    //    ApiKeyRepository $repository
    //) {
    //    parent::__construct();

    //    $this->encrypter = $encrypter;
    //    $this->keyCreationService = $keyCreationService;
    //    $this->repository = $repository;
    //}

    /**
     * Returns all of the API keys that exist for the given client.
     *
     * @return array
     */
    public function index(GetUsersApiKeysRequest $request, User $user)
    {
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
    public function store(StoreApiKeyRequest $request, User $user)
    {
        //$key = $this->keyCreationService->setKeyType(ApiKey::TYPE_ACCOUNT)->handle([
        //    'user_id' => $user->id,
        //    'memo' => $request->input('description'),
        //    'allowed_ips' => $request->input('allowed_ips') ?? [],
        //]);

        $token = $user->createToken(
            $request->input('description'),
            $request->input('allowed_ips')
        );
        
        return $this->fractal->item($key)
            ->transformWith($this->getTransformer(ApiKeyTransformer::class))
            ->addMeta([
                //'secret_token' => $this->encrypter->decrypt($key->token),
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
        //$response = $this->repository->deleteWhere([
        //    'key_type' => ApiKey::TYPE_ACCOUNT,
        //    'user_id' => $user->id,
        //    'identifier' => $identifier,
        //]);

        $key = $user->apiKeys()
            ->where('key_type', ApiKey::TYPE_ACCOUNT)
            ->where('identifier', $identifier)
            ->firstOrFail();
        
        $key->delete();

        return JsonResponse::create([], JsonResponse::HTTP_NO_CONTENT);
    }
}
