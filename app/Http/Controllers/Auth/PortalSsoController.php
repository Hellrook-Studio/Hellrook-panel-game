<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Facades\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Login sekali klik dari portal customer.
 *
 * Token: base64url(payload JSON) "." hex(HMAC-SHA256(bagian payload, PORTAL_SSO_SECRET)).
 * Payload: {"uid": id user panel, "srv": identifier server atau "", "exp": unix, "nonce": acak}.
 * Token berlaku paling lama 2 menit, sekali pakai, dan tidak bisa dipakai untuk akun admin.
 *
 * Kalau browser sudah login sebagai user lain, sesi tidak langsung diganti: user diminta
 * konfirmasi lewat form POST (dengan CSRF token). Tanpa ini, link SSO milik orang lain yang
 * dikirim ke korban akan diam-diam mengganti sesi panel korban.
 */
class PortalSsoController extends Controller
{
    private const MAX_TTL = 120;

    public function __invoke(Request $request): RedirectResponse|Response
    {
        $token = (string) $request->query('token', '');
        [$payload, $user] = $this->verify($token);

        if (Auth::guard()->check() && Auth::guard()->id() !== $user->id) {
            return response()->view('auth.portal-sso-confirm', [
                'token' => $token,
                'current' => Auth::guard()->user(),
                'target' => $user,
            ]);
        }

        return $this->complete($request, $payload, $user);
    }

    /**
     * Konfirmasi berganti akun (POST dari halaman konfirmasi).
     */
    public function confirm(Request $request): RedirectResponse
    {
        [$payload, $user] = $this->verify((string) $request->input('token', ''));

        return $this->complete($request, $payload, $user);
    }

    /**
     * Memeriksa tanda tangan dan masa berlaku token, tanpa memakai nonce-nya.
     *
     * @return array{0: array, 1: User}
     */
    private function verify(string $token): array
    {
        $secret = (string) config('portal.sso_secret');
        if (strlen($secret) < 32) {
            throw new HttpException(404);
        }

        $parts = explode('.', $token);
        if (count($parts) !== 2 || !hash_equals(hash_hmac('sha256', $parts[0], $secret), strtolower($parts[1]))) {
            throw new HttpException(403, 'Invalid login link.');
        }

        $payload = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/'), true), true);
        $now = CarbonImmutable::now()->getTimestamp();
        if (
            !is_array($payload)
            || !is_int($payload['uid'] ?? null)
            || !is_int($payload['exp'] ?? null)
            || !is_string($payload['nonce'] ?? null)
            || strlen($payload['nonce']) < 16
            || $payload['exp'] < $now
            || $payload['exp'] > $now + self::MAX_TTL
        ) {
            throw new HttpException(403, 'This login link has expired. Open the panel again from the portal.');
        }

        /** @var User|null $user */
        $user = User::query()->find($payload['uid']);
        if (!$user || $user->root_admin) {
            throw new HttpException(403, 'This account cannot log in through the portal.');
        }

        return [$payload, $user];
    }

    /**
     * Memakai nonce (sekali pakai), lalu login sebagai user itu.
     */
    private function complete(Request $request, array $payload, User $user): RedirectResponse
    {
        // Cache::add hanya berhasil sekali per nonce.
        if (!Cache::add('portal-sso:' . $payload['nonce'], true, self::MAX_TTL * 2)) {
            throw new HttpException(403, 'This login link was already used. Open the panel again from the portal.');
        }

        if (Auth::guard()->id() !== $user->id) {
            if (Auth::guard()->check()) {
                Auth::guard()->logout();
            }
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            Auth::guard()->login($user);
            $request->session()->regenerate();

            Activity::event('auth:portal-sso')->withRequestMetadata()->subject($user)->log();
        }

        $server = $payload['srv'] ?? '';
        if (is_string($server) && preg_match('/^[a-f0-9]{8}$/', $server)) {
            return redirect('/server/' . $server);
        }

        return redirect('/');
    }
}
