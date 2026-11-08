<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexHangoutRegistrationsRequest;
use App\Http\Resources\HangoutRegistrationResource;
use App\Services\HangoutRegistrationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin side of the GCCC Hangout: who registered.
 */
class HangoutRegistrationController extends Controller
{
    public function __construct(private readonly HangoutRegistrationService $service) {}

    /**
     * GET /api/admin/hangout/registrations
     *
     * Filters: search, game, support (with|without). Sort: sort + direction.
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
     * Every registration matching the same filters, as CSV.
     */
    public function export(IndexHangoutRegistrationsRequest $request): StreamedResponse
    {
        $query = $this->service->query($request->validated());
        $filename = 'gccc-hangout-2026-registrations-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so spreadsheet apps read names correctly
            $this->row($out, ['#', 'Full name', 'Email', 'Phone', 'WhatsApp', 'Game', 'Support pledged (NGN)', 'Registered', 'Last updated']);

            $number = 0;
            foreach ($query->lazy(500) as $registration) {
                $this->row($out, [
                    ++$number,
                    $this->text($registration->full_name),
                    $this->text($registration->email),
                    $registration->phone_number,
                    $registration->whatsapp_number ?? '',
                    $this->text($registration->game),
                    $registration->support_amount ?? '',
                    $registration->created_at?->toDateTimeString(),
                    $registration->updated_at?->toDateTimeString(),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function row($handle, array $values): void
    {
        fputcsv($handle, $values, ',', '"', '');
    }

    /**
     * Free text typed by the public: a leading = + - @ would run as a formula
     * when the CSV is opened in a spreadsheet, so it is neutralised.
     * (Phone numbers are validated to digits and + - ( ) only.)
     */
    private function text(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'{$value}" : $value;
    }
}
