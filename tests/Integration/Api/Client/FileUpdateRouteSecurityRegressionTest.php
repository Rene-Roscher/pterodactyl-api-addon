<?php

namespace Xepare\PterodactylApiAddon\Tests\Integration\Api\Client;

use Pterodactyl\Models\User;
use Illuminate\Http\Response;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Permission;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

class FileUpdateRouteSecurityRegressionTest extends ClientApiIntegrationTestCase
{
    public function testUnauthenticatedRequestCannotAccessFileUpdateRoute(): void
    {
        [, $server] = $this->generateTestAccount();

        $this->postJson("/api/client/servers/{$server->uuid}/files/update", [
            'file' => '/config.yml',
            'content' => 'name: demo',
        ])->assertUnauthorized();
    }

    public function testServerOwnerWithValidSessionCanUpdateFile(): void
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

    public function testValidAccountTokenCannotUpdateFilesForUnrelatedServer(): void
    {
        $attacker = User::factory()->create();
        [, $victimServer] = $this->generateTestAccount();

        $this->authenticateWithAccountToken($attacker);

        $this->postJson("/api/client/servers/{$victimServer->uuid}/files/update", [
            'file' => '/config.yml',
            'content' => 'name: blocked',
        ])->assertUnauthorized();
    }

    public function testSubuserWithoutFileCreatePermissionIsRejected(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_WEBSOCKET_CONNECT]);

        $this->actingAs($user)
            ->postJson("/api/client/servers/{$server->uuid}/files/update", [
                'file' => '/config.yml',
                'content' => 'name: blocked',
            ])
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testSubuserWithFileCreatePermissionCanUpdateFiles(): void
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

    public function testApplicationApiTokenIsRejectedOnClientFileRoute(): void
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

    public function testRequestValidationRunsBeforeFileWrite(): void
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
        $token = $user->createToken('Client Key', []);
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
            ->andReturn(new Psr7Response());
    }
}
