<?php

namespace App\Support;

final class DemoMedia
{
    /** Replace this class with an Eloquent-backed repository at integration time. */
    public static function all(): array
    {
        $today = today();
        $yesterday = $today->copy()->subDay();
        $first = $today->copy()->setTime(14, 35);
        $second = $today->copy()->setTime(11, 20);
        $third = $yesterday->copy()->setTime(19, 10);

        return [
            ['id' => 'moment-001', 'title' => 'An evening at the paddock', 'filename' => 'IMG_1942.jpg', 'type' => 'image', 'src' => asset('media/paddock.jpg'), 'poster' => asset('media/paddock.jpg'), 'alt' => 'Guest overlooking a motorsport hospitality venue at dusk', 'date' => $first->toDateString(), 'date_display' => $first->format('d-m-Y'), 'captured_at' => $first->toIso8601String(), 'time_display' => $first->format('h:i A')],
            ['id' => 'moment-002', 'title' => 'The celebration', 'filename' => 'IMG_1946.jpg', 'type' => 'image', 'src' => asset('media/hospitality.jpg'), 'poster' => asset('media/hospitality.jpg'), 'alt' => 'Two guests enjoying a celebration beside a classic green car', 'date' => $second->toDateString(), 'date_display' => $second->format('d-m-Y'), 'captured_at' => $second->toIso8601String(), 'time_display' => $second->format('h:i A')],
            ['id' => 'moment-003', 'title' => 'Paddock film', 'filename' => 'VID_1951.mp4', 'type' => 'video', 'src' => asset('media/paddock-preview.mp4'), 'poster' => asset('media/arrival.jpg'), 'alt' => 'Short sample video beside a green racing car', 'date' => $third->toDateString(), 'date_display' => $third->format('d-m-Y'), 'captured_at' => $third->toIso8601String(), 'time_display' => $third->format('h:i A')],
        ];
    }

    public static function sorted(string $sort): array
    {
        if ($sort === 'none') {
            return self::all();
        }

        $date = $sort === 'yesterday' ? today()->subDay()->toDateString() : today()->toDateString();

        return array_values(array_filter(self::all(), fn (array $item) => $item['date'] === $date));
    }

    public static function find(string $id): ?array
    {
        foreach (self::all() as $item) {
            if ($item['id'] === $id) {
                return $item;
            }
        }

        return null;
    }
}
