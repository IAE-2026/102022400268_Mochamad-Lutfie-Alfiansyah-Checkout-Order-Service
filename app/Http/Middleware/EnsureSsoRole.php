<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Models\User;
use App\Services\SsoJwtVerifier;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureSsoRole
{
    public function __construct(private readonly SsoJwtVerifier $verifier)
    {
    }

    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        if (! filter_var(config('services.sso.enabled'), FILTER_VALIDATE_BOOL)) {
            return $next($request);
        }

        $user = $request->attributes->get('sso_user');
        $roles = $request->attributes->get('sso_roles');

        if (! $user instanceof User || ! is_array($roles)) {
            $token = $request->bearerToken();

            if (! $token) {
                return ApiResponse::error('Missing SSO bearer token', null, 401);
            }

            try {
                $claims = $this->verifier->verify($token);
                [$user, $roles] = $this->mapClaimsToLocalUser($claims);
            } catch (Throwable $exception) {
                return ApiResponse::error($exception->getMessage(), null, 401);
            }

            Auth::setUser($user);
            $request->setUserResolver(fn (): User => $user);
            $request->attributes->set('sso_user', $user);
            $request->attributes->set('sso_roles', $roles);
        }

        $allowedRoles = array_values(array_filter(array_map(
            fn (string $role): string => Str::lower(trim($role)),
            $allowedRoles,
        )));

        if ($allowedRoles !== [] && collect($roles)->intersect($allowedRoles)->isEmpty()) {
            return ApiResponse::error('SSO role is not allowed to access this resource', null, 403);
        }

        return $next($request);
    }

    /**
     * @param array<string, mixed> $claims
     * @return array{0: User, 1: array<int, string>}
     */
    private function mapClaimsToLocalUser(array $claims): array
    {
        $provider = (string) config('services.sso.provider', 'cloud-dosen');
        $subject = (string) ($claims['sub'] ?? '');

        if ($subject === '') {
            throw new \RuntimeException('SSO token does not contain subject claim');
        }

        $email = (string) ($claims['email'] ?? "{$subject}@sso.local");
        $name = (string) ($claims['name'] ?? $claims['preferred_username'] ?? $email);
        $roles = $this->extractRoles($claims);

        $user = User::query()
            ->where('sso_provider', $provider)
            ->where('sso_subject', $subject)
            ->first();

        if (! $user) {
            $user = User::query()->where('email', $email)->first() ?? new User();
        }

        $user->fill([
            'name' => $name,
            'email' => $email,
            'sso_provider' => $provider,
            'sso_subject' => $subject,
            'sso_claims' => $claims,
        ]);

        if (! $user->exists) {
            $user->password = Str::random(40);
        }

        $user->save();

        $roleIds = collect($roles)
            ->map(fn (string $role): Role => Role::firstOrCreate(
                ['code' => $role],
                ['name' => Str::headline($role)],
            ))
            ->pluck('id')
            ->all();

        $user->roles()->sync($roleIds);
        $user->load('roles');

        return [$user, $roles];
    }

    /**
     * @param array<string, mixed> $claims
     * @return array<int, string>
     */
    private function extractRoles(array $claims): array
    {
        $claimName = (string) config('services.sso.role_claim', 'role');
        $value = data_get($claims, $claimName, $claims['roles'] ?? $claims['role'] ?? 'customer');
        $roles = is_array($value) ? $value : explode(',', (string) $value);

        return collect($roles)
            ->map(fn (mixed $role): string => Str::lower(trim((string) $role)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
