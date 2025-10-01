<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    public function index()
    {
        $media = Media::latest('published_at')->get();
        return $this->successResponse($media, '');
    }

    public function fetchFromYouTube()
    {
        $apiKey = config('services.youtube.key');
        $channelId = config('services.youtube.channel_id');

        $url = "https://www.googleapis.com/youtube/v3/search";

        $response = Http::get($url, [
            'part' => 'snippet',
            'channelId' => $channelId,
            'order' => 'date',
            'maxResults' => 20,
            'type' => 'video',
            'key' => $apiKey,
        ]);

        if ($response->failed()) {
            return $this->errorResponse('Failed to fetch YouTube videos', Response::HTTP_BAD_REQUEST);
        }

        $items = $response->json('items');

        foreach ($items as $item) {
            Media::updateOrCreate(
                ['video_id' => $item['id']['videoId']],
                [
                    'title' => $item['snippet']['title'],
                    'description' => $item['snippet']['description'],
                    'thumbnail_default' => $item['snippet']['thumbnails']['default']['url'] ?? null,
                    'thumbnail_medium' => $item['snippet']['thumbnails']['medium']['url'] ?? null,
                    'thumbnail_high' => $item['snippet']['thumbnails']['high']['url'] ?? null,
                    'channel_id' => $item['snippet']['channelId'],
                    'channel_title' => $item['snippet']['channelTitle'],
                    'published_at' => Carbon::parse($item['snippet']['publishedAt'])->format('Y-m-d H:i:s')
                ]
            );
        }
        $this->successResponse([], 'Videos imported successfully');
    }
}
