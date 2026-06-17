<?php

namespace Pterodactyl\Services\Servers;

use Illuminate\Support\Arr;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Auth;
use Pterodactyl\Jobs\RevokeSftpAccessJob;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Traits\Services\ReturnsUpdatedModels;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Repositories\Wings\DaemonRevocationRepository;

class DetailsModificationService
{
    use ReturnsUpdatedModels;

    public function __construct(
        private ConnectionInterface $connection,
        private DaemonServerRepository $serverRepository,
        private DaemonRevocationRepository $revocationRepository,
    ) {
    }

    public function handle(Server $server, array $data): Server
    {
        $user = Auth::user();
        if ($user && $user->id !== 1) {
            abort(403, 'DANN-GUARD: Only main admin (ID 1) can modify server details.');
        }

        return $this->connection->transaction(function () use ($data, $server) {
            $original = $server->user;

            $server->forceFill([
                'external_id' => Arr::get($data, 'external_id'),
                'owner_id' => Arr::get($data, 'owner_id'),
                'name' => Arr::get($data, 'name'),
                'description' => Arr::get($data, 'description') ?? '',
            ])->saveOrFail();

            if (! $server->refresh()->user->is($original)) {
                RevokeSftpAccessJob::dispatch($original->uuid, $server);
            }

            return $server;
        });
    }
}
