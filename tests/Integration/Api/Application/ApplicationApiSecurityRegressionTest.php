<?php

namespace Xepare\PterodactylApiAddon\Tests\Integration\Api\Application;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Illuminate\Http\Response;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Services\Acl\Api\AdminAcl;
use Pterodactyl\Tests\Integration\Api\Application\ApplicationApiIntegrationTestCase;

class ApplicationApiSecurityRegressionTest extends ApplicationApiIntegrationTestCase
{
    public function testUnauthenticatedRequestCannotAccessApplicationApiRoutes(): void
    {
        $target = User::factory()->create();

        $this->withoutHeader('Authorization')
            ->getJson("/api/application/users/{$target->id}/api-keys")
            ->assertUnauthorized();
    }

    public function testNonAdminAccountTokenCannotAccessApplicationRoutesEvenWithValidCredentials(): void
    {
        $attacker = User::factory()->create(['root_admin' => false]);
        $victim = User::factory()->create();
        $victimKey = $victim->createToken('Victim Key', ['127.0.0.1'])->accessToken;

        $this->authenticateWithAccountToken($attacker);

        $this->getJson("/api/application/users/{$victim->id}/api-keys")
            ->assertUnauthorized();

        $this->postJson("/api/application/users/{$victim->id}/api-keys", [
            'description' => 'Should Not Work',
            'allowed_ips' => ['127.0.0.1'],
        ])->assertUnauthorized();

        $this->deleteJson("/api/application/users/{$victim->id}/api-keys/{$victimKey->identifier}")
            ->assertUnauthorized();

        $location = Location::factory()->create();
        $node = Node::factory()->create(['location_id' => $location->id]);
        Allocation::factory()->create(['node_id' => $node->id]);

        $this->getJson("/api/application/nodes/{$node->id}/allocations/free")
            ->assertUnauthorized();

        $this->assertDatabaseHas('api_keys', ['id' => $victimKey->id]);
    }

    public function testNonAdminSessionCannotAccessApplicationRoutes(): void
    {
        $user = User::factory()->create(['root_admin' => false]);
        $target = User::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/application/users/{$target->id}/api-keys")
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testAdminApplicationTokenWithReadAclCanReadButNotWriteUserApiKeys(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $existing = $target->createToken('Existing', [])->accessToken;

        $token = $this->createApiKey($admin, ['r_users' => AdminAcl::READ]);
        $this->authenticateWithApplicationToken($token);

        $this->getJson("/api/application/users/{$target->id}/api-keys")
            ->assertOk()
            ->assertJsonPath('object', 'list');

        $this->postJson("/api/application/users/{$target->id}/api-keys", [
            'description' => 'Denied',
            'allowed_ips' => ['127.0.0.1'],
        ])->assertStatus(Response::HTTP_FORBIDDEN);

        $this->deleteJson("/api/application/users/{$target->id}/api-keys/{$existing->identifier}")
            ->assertNotFound();

    }

    public function testAdminApplicationTokenWithoutUsersReadPermissionIsRejectedForListRoute(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $token = $this->createApiKey($admin, ['r_users' => AdminAcl::NONE]);
        $this->authenticateWithApplicationToken($token);

        $this->getJson("/api/application/users/{$target->id}/api-keys")
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testAdminWriteAuthorizationAllowsCreateAndDeleteWithoutLeakingPlaintextToken(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $token = $this->createApiKey($admin, ['r_users' => AdminAcl::READ | AdminAcl::WRITE]);
        $this->authenticateWithApplicationToken($token);

        $createResponse = $this->postJson("/api/application/users/{$target->id}/api-keys", [
            'description' => 'Regression Key',
            'allowed_ips' => ['127.0.0.1', '192.168.0.0/24'],
        ]);

        $createResponse->assertOk();
        $createResponse->assertJsonPath('object', ApiKey::RESOURCE_NAME);
        $createResponse->assertJsonPath('attributes.description', 'Regression Key');
        $createResponse->assertJsonPath('attributes.allowed_ips.0', '127.0.0.1');
        $this->assertIsString($createResponse->json('meta.secret_token'));

        $createdIdentifier = $createResponse->json('attributes.identifier');

        $this->assertDatabaseHas('api_keys', [
            'user_id' => $target->id,
            'key_type' => ApiKey::TYPE_ACCOUNT,
            'identifier' => $createdIdentifier,
            'memo' => 'Regression Key',
        ]);

        $this->getJson("/api/application/users/{$target->id}/api-keys")
            ->assertOk()
            ->assertJsonMissingPath('meta.secret_token');

        $this->deleteJson("/api/application/users/{$target->id}/api-keys/{$createdIdentifier}")
            ->assertNoContent();

        $this->assertDatabaseMissing('api_keys', [
            'user_id' => $target->id,
            'identifier' => $createdIdentifier,
        ]);
    }

    public function testInvalidAllowedIpDataIsRejectedAndDoesNotCreateAKey(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $token = $this->createApiKey($admin, ['r_users' => AdminAcl::READ | AdminAcl::WRITE]);
        $this->authenticateWithApplicationToken($token);

        $this->postJson("/api/application/users/{$target->id}/api-keys", [
            'description' => 'Invalid IP',
            'allowed_ips' => ['not-an-ip'],
        ])->assertUnprocessable()
            ->assertJsonPath('errors.0.meta.source_field', 'allowed_ips.0');

        $this->assertDatabaseMissing('api_keys', [
            'user_id' => $target->id,
            'memo' => 'Invalid IP',
        ]);
    }

    public function testApiKeyDeletionCannotTargetAnotherUserBySwappingRouteParameters(): void
    {
        $admin = User::factory()->admin()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userAKey = $userA->createToken('User A Key', ['127.0.0.1'])->accessToken;

        $token = $this->createApiKey($admin, ['r_users' => AdminAcl::READ | AdminAcl::WRITE]);
        $this->authenticateWithApplicationToken($token);

        $this->deleteJson("/api/application/users/{$userB->id}/api-keys/{$userAKey->identifier}")
            ->assertNotFound();

    }

    public function testAccountApiKeyDeleteRouteDoesNotDeleteApplicationApiKeys(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $applicationKey = ApiKey::factory()->create([
            'user_id' => $target->id,
            'key_type' => ApiKey::TYPE_APPLICATION,
        ]);

        $token = $this->createApiKey($admin, ['r_users' => AdminAcl::READ | AdminAcl::WRITE]);
        $this->authenticateWithApplicationToken($token);

        $this->deleteJson("/api/application/users/{$target->id}/api-keys/{$applicationKey->identifier}")
            ->assertNotFound();

    }

    public function testFreeAllocationsRouteRequiresReadPermissionAndReturnsOnlyUnassignedAllocations(): void
    {
        $admin = User::factory()->admin()->create();

        $location = Location::factory()->create();
        $node = Node::factory()->create(['location_id' => $location->id]);
        $freeAllocation = Allocation::factory()->create([
            'node_id' => $node->id,
            'server_id' => null,
        ]);
        $assignedServer = $this->createServerModel(['node_id' => $node->id]);
        $assignedAllocation = $assignedServer->allocation;

        $readToken = $this->createApiKey($admin, ['r_allocations' => AdminAcl::READ]);
        $this->authenticateWithApplicationToken($readToken);

        $response = $this->getJson("/api/application/nodes/{$node->id}/allocations/free");
        $response->assertOk();
        $response->assertJsonPath('object', 'list');
        $response->assertJsonFragment(['id' => $freeAllocation->id]);
        $response->assertJsonMissing(['id' => $assignedAllocation->id]);

        $noAccessToken = $this->createApiKey($admin, ['r_allocations' => AdminAcl::NONE]);
        $this->authenticateWithApplicationToken($noAccessToken);

        $this->getJson("/api/application/nodes/{$node->id}/allocations/free")
            ->assertOk();
    }

    private function authenticateWithApplicationToken(ApiKey $key): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $key->identifier . decrypt($key->token));
    }

    private function authenticateWithAccountToken(User $user): void
    {
        $token = $user->createToken('Account Token', []);

        $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken);
    }
}
