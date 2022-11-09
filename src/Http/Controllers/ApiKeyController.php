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
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\Http\Requests\Api\Client\Account\StoreApiKeyRequest;

class ApiKeyController extends ApplicationApiController
{
    /**
     * @var \Pterodactyl\Services\Api\KeyCreationService
     */
    private $keyCreationService;

    /**
     * @var \Illuminate\Contracts\Encryption\Encrypter
     */
    private $encrypter;

    /**
     * @var \Pterodactyl\Repositories\Eloquent\ApiKeyRepository
     */
    private $repository;

    /**
     * ApiKeyController constructor.
     */
    public function __construct(
        Encrypter $encrypter,
        KeyCreationService $keyCreationService,
        ApiKeyRepository $repository
    ) {
        parent::__construct();

        $this->encrypter = $encrypter;
        $this->keyCreationService = $keyCreationService;
        $this->repository = $repository;
    }

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
    public function store(StoreApiKeyRequest $request, $user_id)
    {
	    $tmp_user = User::find($user_id);

        $token = $tmp_user->createToken(
            request('description'),
            request('allowed_ips')
        );

        $result = $this->fractal->item($token->accessToken)
            ->transformWith($this->getTransformer(ApiKeyTransformer::class))
            ->toArray();

	    $result["meta"]["secret_token"] = $token->plainTextToken;
	    return $result;
    }

    /**
     * Deletes a given API key.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(GetUsersApiKeysRequest $request, User $user, string $identifier)
    {
        $response = $this->repository->deleteWhere([
            'key_type' => ApiKey::TYPE_ACCOUNT,
            'user_id' => $user->id,
            'identifier' => $identifier,
        ]);

        if (!$response) {
            throw new NotFoundHttpException();
        }

        return JsonResponse::create([], JsonResponse::HTTP_NO_CONTENT);
    }
}
