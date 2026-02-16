<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Http\Resources\EventResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    /**
     * Display a listing of events (PUBLIC)
     *
     * Filters: status, search, per_page
     */
    public function index(Request $request)
    {
        $query = Event::query();

        // Filter by status
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        // Search
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Order by date
        $order = $request->input('order', 'asc'); // asc for upcoming first, desc for latest first
        $query->orderByDate($order);

        // Pagination
        $perPage = $request->input('per_page', 15);
        $events = $query->paginate($perPage);

        return EventResource::collection($events);
    }

    /**
     * Display a single event (PUBLIC)
     */
    public function show($id)
    {
        $event = Event::findOrFail($id);

        return new EventResource($event);
    }

    /**
     * Store a new event (ADMIN ONLY)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'location' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'registration_link' => 'nullable|url',
            'registration_deadline' => 'nullable|date',
            'audio_streaming_link' => 'nullable|url',
            'video_streaming_link' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $path = $image->store('events', 'public');
            $data['image'] = '/storage/' . $path;
        }

        $event = Event::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Event created successfully',
            'data' => new EventResource($event)
        ], 201);
    }

    /**
     * Update an event (ADMIN ONLY)
     */
    public function update(Request $request, $id)
    {
        $event = Event::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'location' => 'sometimes|required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'registration_link' => 'nullable|url',
            'registration_deadline' => 'nullable|date',
            'audio_streaming_link' => 'nullable|url',
            'video_streaming_link' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($event->image) {
                $oldPath = str_replace('/storage/', '', $event->image);
                Storage::disk('public')->delete($oldPath);
            }

            $image = $request->file('image');
            $path = $image->store('events', 'public');
            $data['image'] = '/storage/' . $path;
        }

        $event->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Event updated successfully',
            'data' => new EventResource($event)
        ]);
    }

    /**
     * Delete an event (ADMIN ONLY)
     */
    public function destroy($id)
    {
        $event = Event::findOrFail($id);

        // Delete image if exists
        if ($event->image) {
            $path = str_replace('/storage/', '', $event->image);
            Storage::disk('public')->delete($path);
        }

        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully'
        ]);
    }

    /**
     * Get upcoming events (PUBLIC)
     */
    public function upcoming(Request $request)
    {
        $perPage = $request->input('per_page', 10);

        $events = Event::upcoming()
            ->orderByDate('asc')
            ->paginate($perPage);

        return EventResource::collection($events);
    }
}
