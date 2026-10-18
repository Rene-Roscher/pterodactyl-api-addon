<?php

namespace Xepare\PterodactylApiAddon\Tests\Integration\Api\Client;

use Illuminate\Http\Response;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Permission;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

class FileUpdateRouteSecurityRegressionTest extends ClientApiIntegrationTestCase
{
    public function test_unauthenticated_request_cannot_access_file_update_route(): void
    {
        [, $server] = $this->generateTestAccount();

        $this->postJson("/api/client/servers/{$server->uuid}/files/update", [
            'file' => '/config.yml',
            'content' => 'name: demo',
        ])->assertUnauthorized();
    }

    public function test_server_owner_with_valid_session_can_update_file(): void
    {
        [$user, $server] = $this->generateTestAccount();

        $this->expectFileWrite($server, '/config.yml', 'name: demo');

        $this->actingAs($user)
            ->postJson("/api/client/servers/{$server->uuid}/files/update", [
                'file' => '/config.yml',
                'content' => 'name: demo',
            ])
            ->assertStatus(Response::HTTP_NO_CONTENT);
    }

    public function test_valid_account_token_cannot_update_files_for_unrelated_server(): void
    {
        $attacker = User::factory()->create();
        [, $victimServer] = $this->generateTestAccount();

        $this->authenticateWithAccountToken($attacker);

        $this->postJson("/api/client/servers/{$victimServer->uuid}/files/update", [
            'file' => '/config.yml',
            'content' => 'name: blocked',
        ])->assertNotFound();
    }

    public function test_subuser_without_file_create_permission_is_rejected(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_WEBSOCKET_CONNECT]);

        $this->actingAs($user)
            ->postJson("/api/client/servers/{$server->uuid}/files/update", [
                'file' => '/config.yml',
                'content' => 'name: blocked',
            ])
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_subuser_with_file_create_permission_can_update_files(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_FILE_CREATE]);

        $this->expectFileWrite($server, '/config.yml', 'name: allowed');

        $this->actingAs($user)
            ->postJson("/api/client/servers/{$server->uuid}/files/update", [
                'file' => '/config.yml',
                'content' => 'name: allowed',
            ])
            ->assertStatus(Response::HTTP_NO_CONTENT);
    }

    public function test_application_api_token_is_rejected_on_client_file_route(): void
    {
        $user = User::factory()->create();
        [, $server] = $this->generateTestAccount();

        $applicationKey = ApiKey::factory()->create([
            'user_id' => $user->id,
            'key_type' => ApiKey::TYPE_APPLICATION,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $applicationKey->identifier . decrypt($applicationKey->token))
            ->postJson("/api/client/servers/{$server->uuid}/files/update", [
                'file' => '/config.yml',
                'content' => 'name: blocked',
            ])
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_request_validation_runs_before_file_write(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_FILE_CREATE]);

        $this->actingAs($user)
            ->postJson("/api/client/servers/{$server->uuid}/files/update", [
                'content' => 'missing file path',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.meta.source_field', 'file');
    }

    private function authenticateWithAccountToken(User $user): void
    {
        $token = $user->createToken('Client Key', ['127.0.0.1']);
        $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken);
    }

    private function expectFileWrite(Server $server, string $file, string $content): void
    {
        $mock = $this->mock(DaemonFileRepository::class);

        $mock->expects('setServer')
            ->with(\Mockery::on(fn (Server $value) => $value->is($server)))
            ->andReturnSelf();

        $mock->expects('putContent')
            ->with($file, $content)
            ->andReturnNull();
    }
}
