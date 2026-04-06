<?php

namespace App\Services;

class RouteOptimizerService
{
    /**
     * Optimize the order of stops using nearest-neighbor algorithm.
     * Each stop should have: id, name, latitude, longitude, address
     *
     * @param array $stops
     * @param array|null $origin [latitude, longitude] - starting point
     * @return array Optimized stops in order
     */
    public function optimize(array $stops, ?array $origin = null): array
    {
        if (count($stops) <= 1) {
            return $stops;
        }

        $optimized = [];
        $remaining = $stops;

        // Start from origin or first stop
        $currentLat = $origin[0] ?? $remaining[0]['latitude'];
        $currentLng = $origin[1] ?? $remaining[0]['longitude'];

        if ($origin) {
            // Origin is separate from stops
        } else {
            $optimized[] = array_shift($remaining);
            $currentLat = $optimized[0]['latitude'];
            $currentLng = $optimized[0]['longitude'];
        }

        while (count($remaining) > 0) {
            $nearestIdx = 0;
            $nearestDist = PHP_FLOAT_MAX;

            foreach ($remaining as $idx => $stop) {
                $dist = $this->haversineDistance(
                    $currentLat, $currentLng,
                    $stop['latitude'], $stop['longitude']
                );
                if ($dist < $nearestDist) {
                    $nearestDist = $dist;
                    $nearestIdx = $idx;
                }
            }

            $nearest = $remaining[$nearestIdx];
            $optimized[] = $nearest;
            $currentLat = $nearest['latitude'];
            $currentLng = $nearest['longitude'];
            unset($remaining[$nearestIdx]);
            $remaining = array_values($remaining);
        }

        return $optimized;
    }

    /**
     * Generate a Google Maps directions URL with optimized waypoints.
     */
    public function generateGoogleMapsUrl(array $stops, ?array $origin = null): string
    {
        if (empty($stops)) {
            return 'https://www.google.com/maps';
        }

        $baseUrl = 'https://www.google.com/maps/dir/';

        $points = [];

        // Add origin if provided
        if ($origin) {
            $points[] = $origin[0] . ',' . $origin[1];
        }

        // Add all stops in optimized order
        foreach ($stops as $stop) {
            $points[] = $stop['latitude'] . ',' . $stop['longitude'];
        }

        return $baseUrl . implode('/', $points);
    }

    /**
     * Calculate total distance of the route in km.
     */
    public function calculateTotalDistance(array $stops): float
    {
        $total = 0;
        for ($i = 0; $i < count($stops) - 1; $i++) {
            $total += $this->haversineDistance(
                $stops[$i]['latitude'], $stops[$i]['longitude'],
                $stops[$i + 1]['latitude'], $stops[$i + 1]['longitude']
            );
        }
        return round($total, 2);
    }

    /**
     * Haversine distance between two points in km.
     */
    protected function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * asin(sqrt($a));

        return $earthRadius * $c;
    }
}
