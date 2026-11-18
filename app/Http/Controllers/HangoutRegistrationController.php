<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexHangoutRegistrationsRequest;
use App\Http\Resources\HangoutRegistrationResource;
use App\Services\HangoutRegistrationService;
use App\Support\SimpleXlsx;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Admin side of the GCCC Hangout: who registered.
 */
class HangoutRegistrationController extends Controller
{
    private const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __construct(private readonly HangoutRegistrationService $service) {}

    /**
     * GET /api/admin/hangout/registrations
     *
     * Filters: search, game, menu (a meal, or 'none'), support (with|without).
     * Sort: sort + direction.
     */
    public function index(IndexHangoutRegistrationsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $page = $this->service->query($filters)
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();

        return $this->successResponse([
            'registrations' => HangoutRegistrationResource::collection($page->getCollection()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
                'from'         => $page->firstItem(),
                'to'           => $page->lastItem(),
                'links'        => $page->linkCollection()->toArray(),
            ],
            'summary' => $this->service->summary(),
        ], 'Hangout registrations retrieved successfully.');
    }

    /**
     * GET /api/admin/hangout/registrations/export
     *
     * An Excel workbook for the registrations matching the same filters: the
     * adults with their full details, the children they're bringing (with
     * their parent's contact), then a summary sheet (all registrations) with
     * the cost estimate at adult and children's prices, people and meals.
     */
    public function export(IndexHangoutRegistrationsRequest $request): Response
    {
        $filters = $request->validated();
        $registrations = fn() => $this->service->query($filters)->lazy(500);

        $adults = function () use ($registrations) {
            $number = 0;
            foreach ($registrations() as $registration) {
                yield [
                    ++$number,
                    $registration->full_name,
                    $registration->gender ?? 'Not given',
                    $registration->email,
                    $registration->phone_number,
                    $registration->whatsapp_number,
                    $registration->game,
                    $this->service->gamePrice($registration->game, 'adult'),
                    $registration->menu ?? 'Not chosen yet',
                    $registration->children->count(),
                    $registration->support_amount !== null ? (float) $registration->support_amount : null,
                    $registration->created_at,
                    $registration->updated_at,
                ];
            }
        };

        $children = function () use ($registrations) {
            $number = 0;
            foreach ($registrations() as $registration) {
                foreach ($registration->children as $child) {
                    yield [
                        ++$number,
                        $child->full_name,
                        $child->gender,
                        $child->game,
                        $this->service->gamePrice($child->game, 'child'),
                        $child->menu,
                        $registration->full_name,
                        $registration->phone_number,
                        $registration->email,
                    ];
                }
            }
        };

        $xlsx = (new SimpleXlsx())
            ->addSheet('Registrations', [
                ['title' => '#', 'type' => 'integer', 'width' => 6],
                ['title' => 'Full name', 'type' => 'text', 'width' => 28],
                ['title' => 'Gender', 'type' => 'text', 'width' => 11],
                ['title' => 'Email', 'type' => 'text', 'width' => 32],
                ['title' => 'Phone', 'type' => 'text', 'width' => 17],
                ['title' => 'WhatsApp', 'type' => 'text', 'width' => 17],
                ['title' => 'Game', 'type' => 'text', 'width' => 28],
                ['title' => 'Game price (₦)', 'type' => 'money', 'width' => 15],
                ['title' => 'Meal', 'type' => 'text', 'width' => 18],
                ['title' => 'Children', 'type' => 'integer', 'width' => 10],
                ['title' => 'Support pledged (₦)', 'type' => 'money', 'width' => 19],
                ['title' => 'Registered', 'type' => 'date', 'width' => 19],
                ['title' => 'Last updated', 'type' => 'date', 'width' => 19],
            ], $adults())
            ->addSheet('Children', [
                ['title' => '#', 'type' => 'integer', 'width' => 6],
                ['title' => 'Child’s name', 'type' => 'text', 'width' => 28],
                ['title' => 'Gender', 'type' => 'text', 'width' => 11],
                ['title' => 'Game', 'type' => 'text', 'width' => 28],
                ['title' => 'Game price (₦)', 'type' => 'money', 'width' => 15],
                ['title' => 'Meal', 'type' => 'text', 'width' => 18],
                ['title' => 'Parent or guardian', 'type' => 'text', 'width' => 28],
                ['title' => 'Parent’s phone', 'type' => 'text', 'width' => 17],
                ['title' => 'Parent’s email', 'type' => 'text', 'width' => 32],
            ], $children())
            ->addSheet('Summary', [
                ['title' => 'Item', 'type' => 'text', 'width' => 30],
                ['title' => 'Adults', 'type' => 'integer', 'width' => 9],
                ['title' => 'Children', 'type' => 'integer', 'width' => 10],
                ['title' => 'Adult price (₦)', 'type' => 'money', 'width' => 15],
                ['title' => 'Child price (₦)', 'type' => 'money', 'width' => 15],
                ['title' => 'Total (₦)', 'type' => 'money', 'width' => 17],
                ['title' => 'Notes', 'type' => 'text', 'width' => 90],
            ], $this->summaryRows($this->service->summary()))
            ->toString();

        $filename = 'gccc-hangout-2026-registrations-' . now()->format('Y-m-d') . '.xlsx';

        return response($xlsx, 200, [
            'Content-Type'        => self::XLSX,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-store',
        ]);
    }

    /**
     * The summary sheet, for every registration: the cost estimate (adults and
     * children each at their own price), people, meals, gender and support.
     * Columns: item, adults, children, adult price, child price, total, notes.
     */
    private function summaryRows(array $summary): array
    {
        $estimate = $summary['estimate'];
        $entry = $estimate['entry_fee'];
        $note = fn(array $price) => ($price['confirmed'] ? 'Confirmed. ' : 'Estimate only. ') . $price['source'];

        $rows = [['values' => ['Cost estimate at Agodi Gardens prices'], 'bold' => true]];
        foreach ($estimate['games'] as $game) {
            $notes = $game['subtotal'] === null ? 'No price yet: not in the totals.' : $note($game);
            if ($game['subtotal'] !== null && $game['unpriced']) {
                $notes = "{$game['unpriced']} without a price for their age, not counted. {$notes}";
            }
            $rows[] = [
                $game['game'], $game['adults'], $game['children'], $game['adult_price'], $game['child_price'], $game['subtotal'], $notes,
            ];
        }
        // The three totals, each on its own line.
        $rows[] = ['values' => [
            'Game fees total', null, null, null, null, $estimate['games_total'],
            $estimate['unpriced_players'] ? "{$estimate['unpriced_players']} people's games have no price yet and aren't counted." : null,
        ], 'bold' => true];
        $rows[] = ['values' => [
            'Gate fees total', $entry['adults'], $entry['children'], $entry['adult_price'], $entry['child_price'], $entry['total'],
            $entry['adult_price'] !== null || $entry['child_price'] !== null ? 'Everyone coming. ' . $note($entry) : 'No gate fee set.',
        ], 'bold' => true];
        $rows[] = ['values' => ['Estimated total', null, null, null, null, $estimate['total'], 'Game fees plus gate fees.'], 'bold' => true];

        $rows[] = [];
        $rows[] = ['values' => ['People', $summary['adults'], $summary['children'], null, null, null, "{$summary['people']} in all."], 'bold' => true];

        $rows[] = [];
        $rows[] = ['values' => ['Meals'], 'bold' => true];
        foreach ($summary['by_menu'] as $menu) {
            $rows[] = [$menu['name'], $menu['adults'], $menu['children'], null, null, null, "{$menu['count']} plates."];
        }
        $rows[] = ['Not chosen yet', $summary['without_menu'], null, null, null, null, 'Adults who registered before meals were added; they need to update their registration.'];

        $rows[] = [];
        $rows[] = ['values' => ['Gender'], 'bold' => true];
        foreach ($summary['by_gender'] as $gender) {
            $rows[] = [$gender['name'], $gender['adults'], $gender['children']];
        }
        $rows[] = ['Not given', $summary['without_gender'], null, null, null, null, 'Adults who registered before gender was asked; they need to update their registration.'];

        $rows[] = [];
        $rows[] = ['values' => ['Support pledged', null, null, null, null, $summary['total_pledged'], "{$summary['pledges']} pledges."], 'bold' => true];

        return $rows;
    }
}
