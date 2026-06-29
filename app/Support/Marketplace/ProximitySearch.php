<?php

namespace App\Support\Marketplace;

final readonly class ProximitySearch
{
    public const DEFAULT_RADIUS_KM = 25;

    public function __construct(
        public float $latitude,
        public float $longitude,
        public int $radiusKm = self::DEFAULT_RADIUS_KM,
    ) {}

    public function roundedLatitude(): float
    {
        return round($this->latitude, 3);
    }

    public function roundedLongitude(): float
    {
        return round($this->longitude, 3);
    }

    public function distanceTo(float|string|null $latitude, float|string|null $longitude): ?float
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        $targetLatitude = (float) $latitude;
        $targetLongitude = (float) $longitude;
        $earthRadiusKm = 6371.0088;

        $latitudeDelta = deg2rad($targetLatitude - $this->roundedLatitude());
        $longitudeDelta = deg2rad($targetLongitude - $this->roundedLongitude());
        $originLatitude = deg2rad($this->roundedLatitude());
        $destinationLatitude = deg2rad($targetLatitude);

        $angle = sin($latitudeDelta / 2) ** 2
            + cos($originLatitude) * cos($destinationLatitude) * sin($longitudeDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($angle), sqrt(1 - $angle));
    }

    /**
     * @return array{0: float, 1: float}
     */
    public function latitudeBounds(): array
    {
        $delta = $this->radiusKm / 111.32;

        return [
            max(-90, $this->roundedLatitude() - $delta),
            min(90, $this->roundedLatitude() + $delta),
        ];
    }

    /**
     * @return array{0: float, 1: float}
     */
    public function longitudeBounds(): array
    {
        $latitudeRadians = deg2rad($this->roundedLatitude());
        $kmPerDegree = max(1.0, abs(cos($latitudeRadians)) * 111.32);
        $delta = $this->radiusKm / $kmPerDegree;

        return [
            max(-180, $this->roundedLongitude() - $delta),
            min(180, $this->roundedLongitude() + $delta),
        ];
    }
}
