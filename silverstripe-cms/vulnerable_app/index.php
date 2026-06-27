<?php
/**
 * Minimal standalone reproducer for SilverStripe CMS CAND-98c9146b58cd.
 *
 * This file intentionally mirrors the relevant source-code shape:
 *   - CMSMain::updatetreenodes($request) reads attacker-controlled ids.
 *   - CMSMain::getRecord($id) fetches a Page/SiteTree object by ID.
 *   - updatetreenodes() renders tree-node HTML and sibling IDs without
 *     checking $record->canView($member).
 *
 * It is not a full SilverStripe installation. It is a local-only, dependency-free
 * reproduction of the root cause at code/Controllers/CMSMain.php::updatetreenodes().
 */

final class HTTPRequest
{
    public function getVar(string $name): ?string
    {
        return isset($_GET[$name]) ? (string) $_GET[$name] : null;
    }
}

final class Member
{
    /** @var string[] */
    public array $groups;

    public function __construct(array $groups)
    {
        $this->groups = $groups;
    }

    public function inGroup(string $group): bool
    {
        return in_array($group, $this->groups, true);
    }
}

final class Page
{
    public int $ID;
    public string $Title;
    public int $ParentID;
    public int $Sort;
    public bool $restricted;

    public function __construct(int $id, string $title, int $parentID, int $sort, bool $restricted)
    {
        $this->ID = $id;
        $this->Title = $title;
        $this->ParentID = $parentID;
        $this->Sort = $sort;
        $this->restricted = $restricted;
    }

    public function getSortField(): string
    {
        return 'Sort';
    }

    /**
     * Simulates SiteTree::canView(): the page-level permission check that the
     * vulnerable updatetreenodes() path should have called but does not call.
     */
    public function canView(Member $member): bool
    {
        return !$this->restricted || $member->inGroup('secret_group');
    }
}

final class PageRepository
{
    /** @return array<int, Page> */
    public static function all(): array
    {
        return [
            10 => new Page(10, 'Public Home', 0, 1, false),
            42 => new Page(42, 'CONFIDENTIAL: Acquisition Roadmap', 0, 2, true),
            77 => new Page(77, 'Public About', 0, 3, false),
        ];
    }

    public static function byID(int $id): ?Page
    {
        $pages = self::all();
        return $pages[$id] ?? null;
    }

    /** @return Page[] */
    public static function siblingsWithGreaterSort(int $parentID, int $sort): array
    {
        return array_values(array_filter(self::all(), static function (Page $page) use ($parentID, $sort): bool {
            return $page->ParentID === $parentID && $page->Sort > $sort;
        }));
    }

    /** @return Page[] */
    public static function siblingsWithLowerSort(int $parentID, int $sort): array
    {
        return array_values(array_filter(self::all(), static function (Page $page) use ($parentID, $sort): bool {
            return $page->ParentID === $parentID && $page->Sort < $sort;
        }));
    }
}

final class CMSMain
{
    private Member $member;

    public function __construct(Member $member)
    {
        $this->member = $member;
    }

    /**
     * Mirrors CMSMain::getRecord(): fetch by ID, without object-level canView().
     */
    public function getRecord($id): ?Page
    {
        if (!$id || !is_numeric($id)) {
            return null;
        }
        return PageRepository::byID((int) $id);
    }

    private function renderTreeNode(Page $record): string
    {
        $title = htmlspecialchars($record->Title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<li class="class-SiteTree" data-id="' . $record->ID . '"'
            . ' data-recordtype="SilverStripe\\CMS\\Model\\SiteTree"'
            . ' title="' . $title . '">'
            . '<a href="/admin/pages/edit/show/' . $record->ID . '">' . $title . '</a>'
            . '</li>';
    }

    /**
     * VULNERABLE: reproduces CMSMain.php:664-721.
     * The missing guard is: if (!$record->canView($member)) continue;
     */
    public function updatetreenodes(HTTPRequest $request): string
    {
        $data = [];
        $ids = explode(',', $request->getVar('ids') ?? '');

        foreach ($ids as $id) {
            if ($id === '') {
                continue;
            }

            $record = $this->getRecord($id);
            if (!$record) {
                continue;
            }

            $nextCandidates = PageRepository::siblingsWithGreaterSort($record->ParentID, $record->Sort);
            usort($nextCandidates, static fn(Page $a, Page $b): int => $a->Sort <=> $b->Sort);
            $next = $nextCandidates[0] ?? null;

            $prev = null;
            if (!$next) {
                $prevCandidates = PageRepository::siblingsWithLowerSort($record->ParentID, $record->Sort);
                usort($prevCandidates, static fn(Page $a, Page $b): int => $b->Sort <=> $a->Sort);
                $prev = $prevCandidates[0] ?? null;
            }

            $data[$id] = [
                'html' => $this->renderTreeNode($record),
                'ParentID' => $record->ParentID,
                'NextID' => $next ? $next->ID : null,
                'PrevID' => $prev ? $prev->ID : null,
            ];
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /** Patched control endpoint used by the PoC to prove the missing guard matters. */
    public function updatetreenodes_patched(HTTPRequest $request): string
    {
        $data = [];
        $ids = explode(',', $request->getVar('ids') ?? '');

        foreach ($ids as $id) {
            if ($id === '') {
                continue;
            }
            $record = $this->getRecord($id);
            if (!$record || !$record->canView($this->member)) {
                continue;
            }
            $data[$id] = [
                'html' => $this->renderTreeNode($record),
                'ParentID' => $record->ParentID,
                'NextID' => null,
                'PrevID' => null,
            ];
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}

function json_response(int $status, string $body): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo $body;
}

function get_current_member(): ?Member
{
    // Simulates a CMS user who has controller-level CMS_ACCESS_CMSMain but lacks
    // the page-specific group that can view the confidential page.
    if (($_COOKIE['cms_session'] ?? '') === 'limited-cms-user') {
        return new Member(['CMS_ACCESS_CMSMain']);
    }
    if (($_COOKIE['cms_session'] ?? '') === 'secret-cms-user') {
        return new Member(['CMS_ACCESS_CMSMain', 'secret_group']);
    }
    return null;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
if ($path === '/health') {
    header('Content-Type: text/plain');
    echo 'ok';
    return;
}

$member = get_current_member();
if (!$member) {
    json_response(401, json_encode(['error' => 'login required']));
    return;
}

$cms = new CMSMain($member);
$request = new HTTPRequest();

if ($path === '/admin/pages/updatetreenodes') {
    json_response(200, $cms->updatetreenodes($request));
    return;
}
if ($path === '/admin/pages/updatetreenodes_patched') {
    json_response(200, $cms->updatetreenodes_patched($request));
    return;
}

json_response(404, json_encode(['error' => 'not found', 'path' => $path]));
