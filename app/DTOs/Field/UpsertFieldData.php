<?php

namespace App\DTOs\Field;

use App\Http\Requests\Api\Field\UpsertFieldRequest;

readonly class UpsertFieldData
{
    public function __construct(
        public string $name,
        public int $fieldTypeId,
        public string $address,
        public ?string $city,
        public ?string $district,
        public float $pricePerHour,
        public ?string $description,
        public array $images,
        /** Chỉ chứa latitude/longitude khi request có gửi, để cập nhật không xoá nhầm toạ độ cũ. */
        public array $coordinates = [],
    ) {}

    public static function fromRequest(UpsertFieldRequest $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            fieldTypeId: $request->integer('field_type_id'),
            address: $request->string('address')->toString(),
            city: $request->input('city') ?: null,
            district: $request->input('district') ?: null,
            pricePerHour: $request->float('price_per_hour'),
            description: $request->input('description'),
            images: $request->file('images', []),
            coordinates: $request->hasAny(['latitude', 'longitude']) ? [
                'latitude' => $request->filled('latitude') ? $request->float('latitude') : null,
                'longitude' => $request->filled('longitude') ? $request->float('longitude') : null,
            ] : [],
        );
    }
}
