<?php
final class GrafikartProfileSim
{
    private array $user;

    public function __construct(private bool $csrfExceptAll)
    {
        $this->user = [
            'id' => 42,
            'name' => 'Original User',
            'email' => 'original@example.test',
            'email_verified_at' => '2026-01-01T00:00:00Z',
        ];
    }

    public function getUser(): array
    {
        return $this->user;
    }

    public function handle(string $method, string $path, array $post, array $session): array
    {
        $middleware = ['web', 'auth', 'verified'];
        $csrfConfig = $this->csrfExceptAll ? ['except' => ['*']] : ['except' => []];

        if (empty($session['auth_user_id']) || empty($session['verified'])) {
            return ['http_status' => 401, 'body' => ['error' => 'auth/verified middleware rejected request']];
        }

        if (!$this->csrfExceptAll && $method !== 'GET') {
            if (!isset($post['_token']) || $post['_token'] !== ($session['csrf_token'] ?? null)) {
                return ['http_status' => 419, 'body' => [
                    'error' => 'csrf middleware rejected request: missing or invalid _token',
                    'csrf_config' => $csrfConfig,
                    'registered_middleware' => $middleware,
                ]];
            }
        }

        if ($method === 'POST' && $path === '/profil') {
            unset($post['_token']);
            foreach (['name', 'email'] as $field) {
                if (array_key_exists($field, $post)) {
                    $this->user[$field] = $post[$field];
                }
            }
            if (array_key_exists('email', $post)) {
                $this->user['email_verified_at'] = null;
            }
            return ['http_status' => 200, 'body' => [
                'updated' => true,
                'route' => 'POST /profil',
                'controller' => 'App\\Http\\Front\\UserController::update',
                'csrf_config' => $csrfConfig,
                'registered_middleware' => $middleware,
                'user' => $this->user,
            ]];
        }

        return ['http_status' => 404, 'body' => ['error' => 'route not found']];
    }
}
