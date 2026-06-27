<?php
/**
 * Local-only simulation of dotplant2 SliderController delete actions.
 *
 * Source pattern reproduced:
 * - application/config/web.php enables Yii CSRF for normal web requests.
 * - Yii CSRF validation does not protect GET requests.
 * - application/backend/controllers/SliderController.php::behaviors() has AccessControl only,
 *   but no VerbFilter for delete actions.
 */

final class SliderState
{
    /** @var array<int,array<string,mixed>> */
    public array $sliders = [];
    /** @var array<int,array<string,mixed>> */
    public array $slides = [];
    /** @var array<int,array<string,mixed>> */
    public array $auditLog = [];
    public int $csrfChecks = 0;
    public int $csrfFailures = 0;

    public function __construct() { $this->reset(); }

    public function reset(): void
    {
        $this->sliders = [
            5 => ['id' => 5, 'name' => 'Homepage hero slider'],
            6 => ['id' => 6, 'name' => 'Secondary slider'],
        ];
        $this->slides = [
            101 => ['id' => 101, 'slider_id' => 5, 'title' => 'Summer sale slide'],
            102 => ['id' => 102, 'slider_id' => 5, 'title' => 'Winter sale slide'],
        ];
        $this->auditLog = [];
        $this->csrfChecks = 0;
        $this->csrfFailures = 0;
    }
}

final class RequestSim
{
    /** @param array<string,mixed> $params @param array<int,string> $roles */
    public function __construct(
        public string $method,
        public string $route,
        public array $params = [],
        public array $roles = [],
        public ?string $csrfToken = null
    ) {
        $this->method = strtoupper($method);
    }
}

final class ResponseSim
{
    public function __construct(public int $status, public mixed $body) {}
    /** @return array<string,mixed> */
    public function toArray(): array { return ['http_status' => $this->status, 'body' => $this->body]; }
}

final class VulnerableSliderApp
{
    public function __construct(private SliderState $state) {}

    public function dispatch(RequestSim $request): ResponseSim
    {
        // Normal Yii web CSRF protects unsafe non-GET requests only.
        if ($request->method !== 'GET' && !$this->validateCsrf($request)) {
            return new ResponseSim(403, ['ok' => false, 'error' => 'Invalid or missing CSRF token']);
        }

        return $this->dispatchSlider($request);
    }

    private function dispatchSlider(RequestSim $request): ResponseSim
    {
        if (!in_array('content manage', $request->roles, true)) {
            return new ResponseSim(403, ['ok' => false, 'error' => 'AccessControl denied: missing content manage']);
        }

        // Vulnerable behavior: no VerbFilter, so GET can dispatch state-changing delete actions.
        if ($request->route === '/backend/slider/delete-slide') {
            return $this->actionDeleteSlide((int)($request->params['id'] ?? 0), $request);
        }
        if ($request->route === '/backend/slider/delete') {
            return $this->actionDelete((int)($request->params['id'] ?? 0), $request);
        }
        return new ResponseSim(404, ['ok' => false, 'error' => 'Not found']);
    }

    private function actionDeleteSlide(int $id, RequestSim $request): ResponseSim
    {
        if (!isset($this->state->slides[$id])) {
            return new ResponseSim(404, ['ok' => false, 'error' => 'Slide not found']);
        }
        $sliderId = (int)$this->state->slides[$id]['slider_id'];
        unset($this->state->slides[$id]);
        $this->state->auditLog[] = ['action' => 'delete_slide', 'id' => $id, 'slider_id' => $sliderId, 'source_method' => $request->method];
        return new ResponseSim(302, ['redirect' => '/backend/slider/update?id=' . $sliderId]);
    }

    private function actionDelete(int $id, RequestSim $request): ResponseSim
    {
        if (!isset($this->state->sliders[$id])) {
            return new ResponseSim(404, ['ok' => false, 'error' => 'Slider not found']);
        }
        unset($this->state->sliders[$id]);
        foreach ($this->state->slides as $slideId => $slide) {
            if ((int)$slide['slider_id'] === $id) {
                unset($this->state->slides[$slideId]);
            }
        }
        $this->state->auditLog[] = ['action' => 'delete_slider', 'id' => $id, 'source_method' => $request->method];
        return new ResponseSim(302, ['redirect' => '/backend/slider/index']);
    }

    private function validateCsrf(RequestSim $request): bool
    {
        $this->state->csrfChecks++;
        if ($request->csrfToken !== 'valid-yii-csrf-token') {
            $this->state->csrfFailures++;
            return false;
        }
        return true;
    }
}

final class PatchedSliderApp
{
    public function __construct(private SliderState $state) {}

    public function dispatch(RequestSim $request): ResponseSim
    {
        // VerbFilter-style method enforcement before action dispatch.
        if (in_array($request->route, ['/backend/slider/delete-slide', '/backend/slider/delete'], true)
            && !in_array($request->method, ['POST', 'DELETE'], true)) {
            return new ResponseSim(405, ['ok' => false, 'error' => 'Slider delete actions require POST or DELETE']);
        }

        if ($request->method !== 'GET' && !$this->validateCsrf($request)) {
            return new ResponseSim(403, ['ok' => false, 'error' => 'Invalid or missing CSRF token']);
        }

        return $this->dispatchSlider($request);
    }

    private function dispatchSlider(RequestSim $request): ResponseSim
    {
        if (!in_array('content manage', $request->roles, true)) {
            return new ResponseSim(403, ['ok' => false, 'error' => 'AccessControl denied: missing content manage']);
        }

        if ($request->route === '/backend/slider/delete-slide') {
            return $this->deleteSlide((int)($request->params['id'] ?? 0), $request);
        }
        if ($request->route === '/backend/slider/delete') {
            return $this->deleteSlider((int)($request->params['id'] ?? 0), $request);
        }
        return new ResponseSim(404, ['ok' => false, 'error' => 'Not found']);
    }

    private function deleteSlide(int $id, RequestSim $request): ResponseSim
    {
        if (!isset($this->state->slides[$id])) {
            return new ResponseSim(404, ['ok' => false, 'error' => 'Slide not found']);
        }
        $sliderId = (int)$this->state->slides[$id]['slider_id'];
        unset($this->state->slides[$id]);
        $this->state->auditLog[] = ['action' => 'delete_slide', 'id' => $id, 'slider_id' => $sliderId, 'source_method' => $request->method, 'auth' => 'content manage + csrf'];
        return new ResponseSim(302, ['redirect' => '/backend/slider/update?id=' . $sliderId]);
    }

    private function deleteSlider(int $id, RequestSim $request): ResponseSim
    {
        if (!isset($this->state->sliders[$id])) {
            return new ResponseSim(404, ['ok' => false, 'error' => 'Slider not found']);
        }
        unset($this->state->sliders[$id]);
        foreach ($this->state->slides as $slideId => $slide) {
            if ((int)$slide['slider_id'] === $id) {
                unset($this->state->slides[$slideId]);
            }
        }
        $this->state->auditLog[] = ['action' => 'delete_slider', 'id' => $id, 'source_method' => $request->method, 'auth' => 'content manage + csrf'];
        return new ResponseSim(302, ['redirect' => '/backend/slider/index']);
    }

    private function validateCsrf(RequestSim $request): bool
    {
        $this->state->csrfChecks++;
        if ($request->csrfToken !== 'valid-yii-csrf-token') {
            $this->state->csrfFailures++;
            return false;
        }
        return true;
    }
}

/** @return array<string,mixed> */
function slider_snapshot(SliderState $state): array
{
    return [
        'sliders' => $state->sliders,
        'slides' => $state->slides,
        'csrf_checks' => $state->csrfChecks,
        'csrf_failures' => $state->csrfFailures,
        'audit_log' => $state->auditLog,
    ];
}
