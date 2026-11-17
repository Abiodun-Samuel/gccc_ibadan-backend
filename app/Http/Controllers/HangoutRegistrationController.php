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
     * An Excel workbook: every registration matching the same filters, with
     * each one's full details, then a summary sheet (all registrations) with
     * the meal counts and the cost estimate.
     */
    public function export(IndexHangoutRegistrationsRequest $request): Response
    {
        $prices = config('hangout.game_prices', []);
        $rows = function () use ($request, $prices) {
            $number = 0;
            foreach ($this->service->query($request->validated())->lazy(500) as $registration) {
                $price = $prices[$registration->game]['amount'] ?? null;
                yield [
                    ++$number,
                    $registration->full_name,
                    $registration->email,
                    $registration->phone_number,
                    $registration->whatsapp_number,
                    $registration->game,
                    $price !== null ? (float) $price : null,
                    $registration->menu ?? 'Not chosen yet',
                    $registration->support_amount !== null ? (float) $registration->support_amount : null,
                    $registration->created_at,
                    $registration->updated_at,
                ];
            }
        };

        $xlsx = (new SimpleXlsx())
            ->addSheet('Registrations', [
                ['title' => '#', 'type' => 'integer', 'width' => 6],
                ['title' => 'Full name', 'type' => 'text', 'width' => 28],
                ['title' => 'Email', 'type' => 'text', 'width' => 32],
                ['title' => 'Phone', 'type' => 'text', 'width' => 17],
                ['title' => 'WhatsApp', 'type' => 'text', 'width' => 17],
                ['title' => 'Game', 'type' => 'text', 'width' => 28],
                ['title' => 'Game price (₦)', 'type' => 'money', 'width' => 15],
                ['title' => 'Meal', 'type' => 'text', 'width' => 18],
                ['title' => 'Support pledged (₦)', 'type' => 'money', 'width' => 19],
                ['title' => 'Registered', 'type' => 'date', 'width' => 19],
                ['title' => 'Last updated', 'type' => 'date', 'width' => 19],
            ], $rows())
            ->addSheet('Summary', [
                ['title' => 'Item', 'type' => 'text', 'width' => 30],
                ['title' => 'People', 'type' => 'integer', 'width' => 10],
                ['title' => 'Price each (₦)', 'type' => 'money', 'width' => 16],
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

    /** The summary sheet: cost estimate, meals and support, for every registration. */
    private function summaryRows(array $summary): array
    {
        $estimate = $summary['estimate'];
        $entry = $estimate['entry_fee'];
        $note = fn(array $price) => ($price['confirmed'] ? 'Confirmed. ' : 'Estimate only. ') . $price['source'];

        $rows = [['values' => ['Cost estimate at Agodi Gardens prices'], 'bold' => true]];
        foreach ($estimate['games'] as $game) {
            $rows[] = [
                $game['game'], $game['count'], $game['price'], $game['subtotal'],
                $game['price'] === null ? 'No price yet: not in the totals.' : $note($game),
            ];
        }
        // The three totals, each on its own line.
        $rows[] = ['values' => [
            'Game fees total', null, null, $estimate['games_total'],
            $estimate['unpriced_players'] ? "{$estimate['unpriced_players']} players' games have no price yet and aren't counted." : null,
        ], 'bold' => true];
        $rows[] = ['values' => [
            'Gate fees total', $summary['total'], $entry['price'], $entry['total'],
            $entry['price'] !== null ? 'Everyone registered. ' . $note($entry) : 'No gate fee set.',
        ], 'bold' => true];
        $rows[] = ['values' => ['Estimated total', null, null, $estimate['total'], 'Game fees plus gate fees.'], 'bold' => true];

        $rows[] = [];
        $rows[] = ['values' => ['Meals'], 'bold' => true];
        foreach ($summary['by_menu'] as $menu) {
            $rows[] = [$menu['name'], $menu['count']];
        }
        $rows[] = ['Not chosen yet', $summary['without_menu'], null, null, 'Registered before meals were added; they need to update their registration.'];

        $rows[] = [];
        $rows[] = ['values' => ['Support pledged', $summary['pledges'], null, $summary['total_pledged']], 'bold' => true];

        return $rows;
    }
}
