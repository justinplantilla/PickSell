<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Services\AdminNotificationService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * Reads an admin's notification inbox: category filters, date grouping, collapsing repeats, and
 * live resolution of registration alerts that another admin has already decided.
 */
class AdminNotificationInbox
{
    public const FILTERS = [
        'all' => 'All',
        'unread' => 'Unread',
        'registrations' => 'Registrations',
        'orders' => 'Orders & refunds',
        'logistics' => 'Logistics',
        'critical' => 'Critical',
    ];

    /** Event prefixes per category. Events are stored in the JSON `data` text column. */
    private const CATEGORY_EVENTS = [
        'registrations' => ['registration.'],
        'orders' => ['return.', 'refund.', 'financial.', 'complaint.'],
        'logistics' => ['delivery.', 'parcel.', 'order.exception'],
        'compliance' => ['compliance.'],
        'platform' => ['platform.'],
    ];

    /** Legacy order workflow alerts (e.g. old "Parcel handed over" rows) carry no event. */
    private const ORDER_WORKFLOW_TYPE = 'App\\Notifications\\OrderWorkflowUpdate';

    /** Same-title alerts on one day collapse into a single expandable group from this many. */
    private const GROUP_THRESHOLD = 3;

    public const RETENTION_DAYS = 90;

    public static function normalizeFilter(mixed $filter): string
    {
        return is_string($filter) && array_key_exists($filter, self::FILTERS) ? $filter : 'all';
    }

    public function query(User $admin, string $filter): Builder
    {
        $query = $admin->notifications()->getQuery();

        return match ($filter) {
            'unread' => $query->whereNull('read_at'),
            'critical' => AdminNotificationService::whereData($query, 'priority', 'critical'),
            'registrations', 'orders' => $query->where(fn (Builder $q) => $this->whereEvents($q, self::CATEGORY_EVENTS[$filter])),
            'logistics' => $query->where(fn (Builder $q) => $this->whereEvents($q, self::CATEGORY_EVENTS['logistics'])
                ->orWhere('type', self::ORDER_WORKFLOW_TYPE)),
            default => $query,
        };
    }

    /** @return array<string, int> unread count per filter tab */
    public function unreadCounts(User $admin): array
    {
        return collect(array_keys(self::FILTERS))
            ->mapWithKeys(fn (string $filter) => [$filter => $this->query($admin, $filter)->whereNull('read_at')->count()])
            ->all();
    }

    public function markRead(User $admin, string $filter): int
    {
        return $this->query($admin, $filter)->whereNull('read_at')->update(['read_at' => now()]);
    }

    /** Deletes admins' read notifications older than the retention window. */
    public function prune(int $days = self::RETENTION_DAYS): int
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', (new User())->getMorphClass())
            ->whereIn('notifiable_id', User::query()->where('role', 'admin')->select('id'))
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }

    /**
     * Turns one page of notifications into date sections of rows. A row is either a single item
     * (`kind` = item) or a collapsed group of same-title items (`kind` = group).
     *
     * @return Collection<int, array{label: string, rows: Collection}>
     */
    public function present(LengthAwarePaginator $page): Collection
    {
        $items = collect($page->items())->map(fn (DatabaseNotification $notification) => $this->item($notification));
        $this->resolveRegistrations($items);

        return $items
            ->groupBy(fn (array $item) => $this->dateLabel($item['created_at']))
            ->map(fn (Collection $dayItems, string $label) => [
                'label' => $label,
                'rows' => $this->collapse($dayItems),
            ])
            ->values();
    }

    private function item(DatabaseNotification $notification): array
    {
        $data = $notification->data;
        if (is_string($data)) {
            $data = json_decode($data, true) ?: [];
        }
        $data = is_array($data) ? $data : [];
        $category = $this->categoryFor($data['event'] ?? null, $notification->type);
        $url = self::safeUrl($data['url'] ?? null);

        return [
            'ids' => [$notification->id],
            'title' => (string) ($data['title'] ?? 'PickSell notification'),
            'message' => (string) ($data['message'] ?? 'Notification'),
            'highlight' => [],
            'url' => $url,
            'action' => $url ? ($category === 'registrations' ? 'Review' : 'Open') : null,
            'priority' => ($data['priority'] ?? 'normal') === 'critical' ? 'critical' : 'normal',
            'category' => $category,
            'created_at' => $notification->created_at,
            'unread' => $notification->read_at === null,
            'resolved' => null,
            'count' => 1,
        ];
    }

    private function categoryFor(?string $event, ?string $type): string
    {
        foreach (self::CATEGORY_EVENTS as $category => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (is_string($event) && str_starts_with($event, $prefix)) {
                    return $category;
                }
            }
        }

        return $type === self::ORDER_WORKFLOW_TYPE ? 'logistics' : 'general';
    }

    /**
     * Registration alerts reflect the application's current state, so an application another admin
     * already decided reads "Approved by …" and stops counting as unread.
     */
    private function resolveRegistrations(Collection $items): void
    {
        $applicantIds = $items
            ->map(fn (array $item) => $item['category'] === 'registrations' ? $this->registrationId($item['url']) : null)
            ->filter()
            ->unique();
        if ($applicantIds->isEmpty()) {
            return;
        }

        $applicants = User::query()
            ->whereKey($applicantIds->all())
            ->with('latestRegistrationReview.reviewer')
            ->get()
            ->keyBy('id');
        $newlyRead = [];

        $items->transform(function (array $item) use ($applicants, &$newlyRead) {
            $applicantId = $item['category'] === 'registrations' ? $this->registrationId($item['url']) : null;
            if ($applicantId === null) {
                return $item;
            }

            $applicant = $applicants->get($applicantId);
            if ($applicant) {
                $item['highlight'][] = $applicant->full_name;
            }
            if ($applicant?->status === 'pending') {
                return $item;
            }

            $reviewer = $applicant?->latestRegistrationReview?->reviewer;
            $item['resolved'] = match (true) {
                $applicant === null => ['tone' => 'neutral', 'label' => 'Applicant no longer exists'],
                $applicant->status === 'approved' => ['tone' => 'approved', 'label' => 'Approved'.($reviewer ? " by {$reviewer->full_name}" : '')],
                $applicant->status === 'disapproved' => ['tone' => 'rejected', 'label' => 'Disapproved'.($reviewer ? " by {$reviewer->full_name}" : '')],
                default => ['tone' => 'neutral', 'label' => 'Now '.str_replace('_', ' ', (string) $applicant->status)],
            };
            $item['action'] = $applicant ? 'View' : null;
            if ($item['unread']) {
                $newlyRead = [...$newlyRead, ...$item['ids']];
                $item['unread'] = false;
            }

            return $item;
        });

        if ($newlyRead !== []) {
            DatabaseNotification::query()->whereKey($newlyRead)->whereNull('read_at')->update(['read_at' => now()]);
        }
    }

    private function registrationId(?string $url): ?int
    {
        return $url && preg_match('#^/admin/registrations/(\d+)$#', $url, $matches) ? (int) $matches[1] : null;
    }

    private function dateLabel(CarbonInterface $date): string
    {
        return match (true) {
            $date->isToday() => 'Today',
            $date->isYesterday() => 'Yesterday',
            $date->greaterThanOrEqualTo(now()->subDays(7)->startOfDay()) => 'This week',
            default => 'Earlier',
        };
    }

    /** Merges exact repeats into one item with a count, then folds same-title runs into groups. */
    private function collapse(Collection $items): Collection
    {
        $merged = $items
            ->groupBy(fn (array $item) => $item['title']."\0".$item['message']."\0".($item['url'] ?? ''))
            ->map(fn (Collection $repeats) => array_merge($repeats->first(), [
                'ids' => $repeats->pluck('ids')->flatten()->all(),
                'count' => $repeats->count(),
                'unread' => $repeats->contains('unread', true),
            ]))
            ->values();

        return $merged
            ->groupBy('title')
            ->map(fn (Collection $same) => $same->count() >= self::GROUP_THRESHOLD
                ? [
                    'kind' => 'group',
                    'title' => $same->first()['title'],
                    'category' => $same->first()['category'],
                    'priority' => $same->contains('priority', 'critical') ? 'critical' : 'normal',
                    'created_at' => $same->first()['created_at'],
                    'unread' => $same->where('unread', true)->count(),
                    'items' => $same->all(),
                ]
                : $same->map(fn (array $item) => ['kind' => 'item'] + $item)->all())
            ->flatMap(fn (array $row) => isset($row['kind']) ? [$row] : $row)
            ->sortByDesc(fn (array $row) => $row['created_at']->getTimestamp())
            ->values();
    }

    /** @param list<string> $prefixes */
    private function whereEvents(Builder $query, array $prefixes): Builder
    {
        foreach ($prefixes as $prefix) {
            AdminNotificationService::whereData($query, 'event', $prefix, prefix: true, boolean: 'or');
        }

        return $query;
    }

    /** Escapes the message and bolds the applicant name, order numbers and waybills in it. */
    public static function highlight(string $message, array $terms = []): HtmlString
    {
        $html = e($message);
        foreach (array_filter(array_unique($terms)) as $term) {
            $escaped = e($term);
            $position = strpos($html, $escaped);
            if ($position !== false) {
                $html = substr_replace($html, '<strong>'.$escaped.'</strong>', $position, strlen($escaped));
            }
        }

        return new HtmlString(preg_replace('/(?<![\w>])(#?ORD-[A-Z0-9]+|WB-\d{6}-[A-Z0-9]+)\b/', '<strong>$1</strong>', $html));
    }

    /** Inner SVG markup (24×24, stroked) for a category's icon; critical alerts always use the warning sign. */
    public static function icon(string $category, string $priority = 'normal'): HtmlString
    {
        $paths = $priority === 'critical'
            ? '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>'
            : match ($category) {
                'registrations' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>',
                'orders' => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
                'logistics' => '<path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
                'compliance' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
                'platform' => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
                default => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
            };

        return new HtmlString($paths);
    }

    public static function safeUrl(mixed $url): ?string
    {
        return is_string($url)
            && str_starts_with($url, '/')
            && ! str_starts_with($url, '//')
            && ! str_contains($url, '\\')
            && ! preg_match('/[\x00-\x1F\x7F]/', $url)
            ? $url
            : null;
    }
}
