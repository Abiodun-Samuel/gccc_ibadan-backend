<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\UploadService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EventController extends Controller
{
    public function __construct(protected UploadService $uploadService) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'   => ['sometimes', 'string'],
            'search'   => ['sometimes', 'string', 'max:255'],
            'order'    => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $events = Event::query()
            ->when($request->status,   fn($q) => $q->byStatus($request->status))
            ->when($request->search,   fn($q) => $q->search($request->search))
            ->orderByDate($request->input('order', 'asc'))
            ->paginate($request->integer('per_page', 15));

        return $this->successResponse(EventResource::collection($events), 'Events retrieved successfully.', Response::HTTP_OK);
    }

    /**
     * Show a single event (PUBLIC).
     */
    public function show(Event $event): JsonResponse
    {
        $event->load(['registrations']);
        return $this->successResponse(new EventResource($event), 'Event retrieved successfully.');
    }

    /**
     * Create a new event (ADMIN ONLY).
     */
    public function store(StoreEventRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            if (!empty($data['image'])) {
                $data['image'] = $this->uploadService->upload($data['image'], 'events');
            }

            $event = Event::create($data);

            return $this->successResponse(
                new EventResource($event),
                'Event created successfully.',
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    private function isBase64(string $value): bool
    {
        if (str_starts_with($value, 'data:')) {
            return true;
        }

        // Fallback: try decoding — valid base64 re-encodes to itself
        $decoded = base64_decode($value, strict: true);

        return $decoded !== false && base64_encode($decoded) === $value;
    }

    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        try {
            $data = $request->validated();

            if (isset($data['image'])) {
                if ($this->isBase64($data['image'])) {
                    if ($event->image) {
                        $this->uploadService->delete($event->image);
                    }

                    $data['image'] = $this->uploadService->upload($data['image'], 'events');
                } else {
                    unset($data['image']);
                }
            }

            $event->update($data);

            return $this->successResponse(
                new EventResource($event->fresh()),
                'Event updated successfully.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    /**
     * Update an existing event (ADMIN ONLY).
     */
    // public function update(UpdateEventRequest $request, Event $event): JsonResponse
    // {
    //     try {
    //         $data = $request->validated();

    //         if (isset($data['image'])) {
    //             if ($event->image) {
    //                 $this->uploadService->delete($event->image);
    //             }

    //             $data['image'] = $this->uploadService->upload($data['image'], 'events');
    //         }

    //         $event->update($data);

    //         return $this->successResponse(
    //             new EventResource($event->fresh()),
    //             'Event updated successfully.'
    //         );
    //     } catch (\Exception $e) {
    //         return $this->errorResponse(
    //             $e->getMessage(),
    //             Response::HTTP_INTERNAL_SERVER_ERROR
    //         );
    //     }
    // }

    /**
     * Delete an event (ADMIN ONLY).
     */
    public function destroy(Event $event): JsonResponse
    {
        try {
            if ($event->image) {
                $this->uploadService->delete($event->image);
            }

            $event->delete();

            return $this->successResponse(null, 'Event deleted successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    public function closest(): JsonResponse
    {
        $event = $this->resolveClosestEvent();

        if (!$event) {
            return $this->errorResponse('No events found.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(
            new EventResource($event),
            'Event retrieved successfully.'
        );
    }

    private function resolveClosestEvent(): ?Event
    {
        $timeNow = Carbon::now()->format('H:i:s');

        $todayUpcoming = Event::today()
            ->where(
                fn($q) => $q
                    ->whereNull('start_time')
                    ->orWhere('start_time', '>=', $timeNow)
            )
            ->orderByRaw('ISNULL(start_time) ASC')
            ->orderBy('start_time', 'asc')
            ->first();

        if ($todayUpcoming) {
            return $todayUpcoming;
        }

        $todayPast = Event::today()
            ->whereNotNull('start_time')
            ->where('start_time', '<', $timeNow)
            ->orderBy('start_time', 'desc')
            ->first();

        if ($todayPast) {
            return $todayPast;
        }

        return Event::afterToday()->orderByDateTime()->first()
            ?? Event::orderByDateTime()->first();
    }
}
