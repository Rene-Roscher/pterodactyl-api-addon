<?php

namespace Xepare\PterodactylApiAddon\Tests\Integration\Api\Application;

use Illuminate\Http\Response;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Services\Acl\Api\AdminAcl;
use Pterodactyl\Tests\Integration\Api\Application\ApplicationApiIntegrationTestCase;

class ApplicationApiSecurityRegressionTest extends ApplicationApiIntegrationTestCase
{
    public function test_unauthenticated_request_cannot_access_application_api_routes(): void
    {
        $target = User::factory()->create();

        $this->withoutHeader('Authorization')
            ->getJson("/api/application/users/{$target->id}/api-keys")
            ->assertUnauthorized();
    }

    public function test_non_admin_account_token_cannot_access_application_routes_even_with_valid_credentials(): void
    {
        $attacker = User::factory()->create(['root_admin' => false]);
        $victim = User::factory()->create();
        $victimKey = $victim->createToken('Victim Key', ['127.0.0.1'])->accessToken;

        $this->authenticateWithAccountToken($attacker);

        $this->getJson("/api/application/users/{$victim->id}/api-keys")
            ->assertStatus(Response::HTTP_FORBIDDEN);

        $this->postJson("/api/application/users/{$victim->id}/api-keys", [
            'description' => 'Should Not Work',
            'allowed_ips' => ['127.0.0.1'],
        ])->assertStatus(Response::HTTP_FORBIDDEN);

        $this->deleteJson("/api/application/users/{$victim->id}/api-keys/{$victimKey->identifier}")
            ->assertStatus(Response::HTTP_FORBIDDEN);

        $node = Node::factory()->create();
        Allocation::factory()->create(['node_id' => $node->id]);

        $this->getJson("/api/application/nodes/{$node->id}/allocations/free")
            ->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertDatabaseHas('api_keys', ['id' => $victimKey->id]);
    }

    public function test_non_admin_session_cannot_access_application_routes(): void
    {
        $user = User::factory()->create(['root_admin' => false]);
        $target = User::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/application/users/{$target->id}/api-keys")
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_admin_application_token_with_read_acl_can_read_but_not_write_user_api_keys(): void
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
            ->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertDatabaseHas('api_keys', ['id' => $existing->id]);
    }

    public function test_admin_application_token_without_users_read_permission_is_rejected_for_list_route(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $token = $this->createApiKey($admin, ['r_users' => AdminAcl::NONE]);
        $this->authenticateWithApplicationToken($token);

        $this->getJson("/api/application/users/{$target->id}/api-keys")
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_admin_write_authorization_allows_create_and_delete_without_leaking_plaintext_token(): void
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
            ->assertNoContent()
            ->assertExactJson([]);

        $this->assertDatabaseMissing('api_keys', [
            'user_id' => $target->id,
            'identifier' => $createdIdentifier,
        ]);
    }

    public function test_invalid_allowed_ip_data_is_rejected_and_does_not_create_a_key(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $token = $this->createApiKey($admin, ['r_users' => AdminAcl::READ | AdminAcl::WRITE]);
        $this->authenticateWithApplicationToken($token);

        $before = ApiKey::query()->count();

        $this->postJson("/api/application/users/{$target->id}/api-keys", [
            'description' => 'Invalid IP',
            'allowed_ips' => ['not-an-ip'],
        ])->assertUnprocessable()
            ->assertJsonPath('errors.0.meta.source_field', 'allowed_ips.0');

        $this->assertSame($before, ApiKey::query()->count());
    }

    public function test_api_key_deletion_cannot_target_another_user_by_swapping_route_parameters(): void
    {
        $admin = User::factory()->admin()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userAKey = $userA->createToken('User A Key', ['127.0.0.1'])->accessToken;

        $token = $this->createApiKey($admin, ['r_users' => AdminAcl::READ | AdminAcl::WRITE]);
        $this->authenticateWithApplicationToken($token);

        $this->deleteJson("/api/application/users/{$userB->id}/api-keys/{$userAKey->identifier}")
            ->assertNotFound();

        $this->assertDatabaseHas('api_keys', ['id' => $userAKey->id]);
    }

    public function test_account_api_key_delete_route_does_not_delete_application_api_keys(): void
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

        $this->assertDatabaseHas('api_keys', ['id' => $applicationKey->id]);
    }

    public function test_free_allocations_route_requires_read_permission_and_returns_only_unassigned_allocations(): void
    {
        $admin = User::factory()->admin()->create();

        $node = Node::factory()->create();
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
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    private function authenticateWithApplicationToken(ApiKey $key): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $key->identifier . decrypt($key->token));
    }

    private function authenticateWithAccountToken(User $user): void
    {
        $token = $user->createToken('Account Token', ['127.0.0.1']);

        $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken);
    }
}
