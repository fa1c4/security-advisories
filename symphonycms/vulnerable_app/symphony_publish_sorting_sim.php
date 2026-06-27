<?php
/**
 * Local-only minimal simulation of the vulnerable Symphony CMS data/control flow:
 *  - XSRF::validateRequest() validates only POST requests.
 *  - Sortable::initialize() reads sort/order from $_REQUEST.
 *  - contentPublish::sort() persists the sort/order to the configuration.
 *
 * It does not include or contact a real Symphony installation.
 */

class LocalConfigurationStore
{
    public array $data;
    public int $writeCount = 0;
    public string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?: sys_get_temp_dir() . '/symphony_sorting_config_' . getmypid() . '.json';
        $this->data = [
            'sorting' => [
                'section_articles_sortby' => 'id',
                'section_articles_order' => 'desc',
            ],
        ];
        $this->write();
        $this->writeCount = 0;
    }

    public function set(string $key, string $value, string $group): void
    {
        if (!isset($this->data[$group])) {
            $this->data[$group] = [];
        }
        $this->data[$group][$key] = $value;
    }

    public function get(string $key, string $group): ?string
    {
        return $this->data[$group][$key] ?? null;
    }

    public function write(): void
    {
        $this->writeCount++;
        file_put_contents($this->path, json_encode($this->data, JSON_PRETTY_PRINT));
    }
}

class LocalSymphony
{
    public static LocalConfigurationStore $configuration;

    public static function Configuration(): LocalConfigurationStore
    {
        return self::$configuration;
    }
}

class LocalXSRF
{
    public const TOKEN = 'server-side-xsrf-token';
    public static int $checks = 0;
    public static int $failures = 0;

    public static function validateRequest(bool $silent = false): bool
    {
        // Mirrors symphony/lib/toolkit/class.xsrf.php: validateRequest() only cares if we have a POST request.
        if (count($_POST) > 0) {
            self::$checks++;
            if (($_POST['xsrf'] ?? null) !== self::TOKEN) {
                self::$failures++;
                if ($silent) {
                    return false;
                }
                throw new RuntimeException('Invalid or missing required XSRF token');
            }
        }
        return true;
    }
}

class LocalField
{
    public function __construct(private string $handle, private bool $sortable = true) {}
    public function isSortable(): bool { return $this->sortable; }
}

class LocalFieldManager
{
    private ?string $field = null;
    public function select(): self { return $this; }
    public function field(string $sort): self { $this->field = $sort; return $this; }
    public function execute(): self { return $this; }
    public function next(): ?LocalField
    {
        $sortableFields = ['id', 'title', 'date', 'author'];
        return in_array($this->field, $sortableFields, true) ? new LocalField($this->field, true) : null;
    }
}

class LocalSection
{
    public function __construct(private string $handle = 'articles') {}
    public function get(string $key): ?string { return $key === 'handle' ? $this->handle : null; }
    public function getDefaultSortingField(): string { return 'id'; }
    public function getSortingField(): string
    {
        return LocalSymphony::Configuration()->get('section_' . $this->handle . '_sortby', 'sorting') ?? 'id';
    }
    public function getSortingOrder(): string
    {
        return LocalSymphony::Configuration()->get('section_' . $this->handle . '_order', 'sorting') ?? 'desc';
    }
    public function setSortingField(string $sort, bool $write = true): void
    {
        // Mirrors Section::setSortingField(): set config, optionally write.
        LocalSymphony::Configuration()->set('section_' . $this->handle . '_sortby', $sort, 'sorting');
        if ($write) {
            LocalSymphony::Configuration()->write();
        }
    }
    public function setSortingOrder(string $order, bool $write = true): void
    {
        // Mirrors Section::setSortingOrder(): set config, optionally write.
        LocalSymphony::Configuration()->set('section_' . $this->handle . '_order', $order, 'sorting');
        if ($write) {
            LocalSymphony::Configuration()->write();
        }
    }
}

class LocalContentPublish
{
    public bool $redirected = false;
    public array $redirects = [];
    public LocalSection $section;

    public function __construct()
    {
        $this->section = new LocalSection('articles');
    }

    public function sort(?string $sort, string $order, array $params = []): array
    {
        $params = array_merge(['unsort' => false, 'filters' => ''], $params);
        $section = $this->section;

        if ($params['unsort']) {
            $section->setSortingField('id', false);
            $section->setSortingOrder('desc');
            $this->redirected = true;
            $this->redirects[] = '/symphony/publish/articles/';
            return ['redirect' => true];
        }

        if (!$sort) {
            $sort = $section->getSortingField();
            $order = $section->getSortingOrder();
        } else {
            $sort = preg_replace('/[^a-zA-Z0-9_-]/', '', $sort);
            $field = (new LocalFieldManager())->select()->field($sort)->execute()->next();
            if (!$field || !$field->isSortable()) {
                $sort = $section->getDefaultSortingField();
            }

            // Mirrors content.publish.php: differing GET sort/order causes persistent config write and redirect.
            if ($sort !== $section->getSortingField() || $order !== $section->getSortingOrder()) {
                $section->setSortingField($sort, false);
                $section->setSortingOrder($order);
                $this->redirected = true;
                $this->redirects[] = '/symphony/publish/articles/';
                return ['redirect' => true];
            }
        }

        return [
            'redirect' => false,
            'sort' => $sort,
            'order' => $order,
        ];
    }
}

class LocalSortable
{
    public static function initialize(LocalContentPublish $object, &$result, &$sort, &$order, array $params = []): void
    {
        // Mirrors class.sortable.php: parameters come from $_REQUEST, so GET query strings are included.
        $sort = $_REQUEST['sort'] ?? null;
        $order = isset($_REQUEST['order']) ? ($_REQUEST['order'] === 'desc' ? 'desc' : 'asc') : 'asc';
        $result = $object->sort($sort, $order, $params);
    }
}

function local_reset_symphony_state(): void
{
    LocalSymphony::$configuration = new LocalConfigurationStore();
    LocalXSRF::$checks = 0;
    LocalXSRF::$failures = 0;
}

function local_get_sorting_state(): array
{
    return [
        'sortby' => LocalSymphony::Configuration()->get('section_articles_sortby', 'sorting'),
        'order' => LocalSymphony::Configuration()->get('section_articles_order', 'sorting'),
        'config_path' => LocalSymphony::Configuration()->path,
        'config_file' => json_decode(file_get_contents(LocalSymphony::Configuration()->path), true),
        'writes' => LocalSymphony::Configuration()->writeCount,
    ];
}

function local_simulate_symphony_request(string $method, array $query, array $post = [], bool $patched = false): array
{
    $_GET = $query;
    $_POST = strtoupper($method) === 'POST' ? $post : [];
    $_REQUEST = array_merge($_GET, $_POST);
    $_SERVER['REQUEST_METHOD'] = strtoupper($method);

    $before = local_get_sorting_state();
    $content = new LocalContentPublish();

    try {
        if ($patched && isset($_REQUEST['sort'])) {
            // Patch model: a state-changing sort persistence must not be reachable through GET;
            // it must be POST and must pass XSRF validation before modifying configuration.
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return [
                    'http_status' => 405,
                    'body' => ['ok' => false, 'error' => 'sort persistence requires POST'],
                    'before' => $before,
                    'after' => local_get_sorting_state(),
                    'csrf_checks' => LocalXSRF::$checks,
                    'csrf_failures' => LocalXSRF::$failures,
                ];
            }
        }

        LocalXSRF::validateRequest();
        $sort = null;
        $order = null;
        $result = null;
        LocalSortable::initialize($content, $result, $sort, $order, []);

        return [
            'http_status' => $content->redirected ? 302 : 200,
            'body' => ['ok' => true, 'result' => $result, 'redirected' => $content->redirected],
            'before' => $before,
            'after' => local_get_sorting_state(),
            'csrf_checks' => LocalXSRF::$checks,
            'csrf_failures' => LocalXSRF::$failures,
        ];
    } catch (RuntimeException $e) {
        return [
            'http_status' => 403,
            'body' => ['ok' => false, 'error' => $e->getMessage()],
            'before' => $before,
            'after' => local_get_sorting_state(),
            'csrf_checks' => LocalXSRF::$checks,
            'csrf_failures' => LocalXSRF::$failures,
        ];
    }
}
