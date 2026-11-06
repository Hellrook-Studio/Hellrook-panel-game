<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Pterodactyl\Services\Rf\LauncherStatusService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\GetServerRequest;

class RfStatusController extends ClientApiController
{
    /** Egg feature yang menandai server RF 4.55. */
    public const FEATURE = 'rf455';

    public function __construct(private Repository $cache, private LauncherStatusService $status)
    {
        parent::__construct();
    }

    /**
     * Status server RF dari port launcher (port game + 1): online, jumlah pemain, versi.
     * Disimpan di cache 10 detik supaya banyak tab yang terbuka tidak membanjiri server game.
     */
    public function __invoke(GetServerRequest $request, Server $server): array
    {
        if (!in_array(self::FEATURE, $server->egg->inherit_features ?? [], true)) {
            throw new NotFoundHttpException('This server does not run RF Online.');
        }

        $allocation = $server->allocation;
        $authPort = $allocation->port + 1;
        $host = config('rf.status_host') ?: (in_array($allocation->ip, ['0.0.0.0', '::'], true) ? 'host.docker.internal' : $allocation->ip);

        // Lock: saat cache kosong, hanya satu request yang membuka koneksi ke server game;
        // request lain menunggu lalu memakai hasil yang sama.
        $key = "rf:status:$server->uuid";
        $status = $this->cache->get($key);
        if ($status === null) {
            try {
                $status = Cache::lock("$key:lock", 6)->block(5, fn () => $this->cache->remember(
                    $key,
                    10,
                    fn () => $this->status->query($host, $authPort)
                ));
            } catch (LockTimeoutException) {
                $status = ['online' => false, 'ready' => false, 'players' => 0, 'max_sessions' => 0, 'game' => null, 'version' => null, 'error' => 'busy, try again'];
            }
        }

        return [
            'object' => 'rf_status',
            'attributes' => $status + ['auth_port' => $authPort],
        ];
    }
}
