<?php

namespace App\DTOs\Field;

use App\Http\Requests\Api\Field\UpsertFieldRequest;

readonly class UpsertFieldData
{
    public function __construct(
        public string $name,
        public int $fieldTypeId,
        public string $address,
        public float $pricePerHour,
        public ?string $description,
        public array $images,
    ) {}

    public static function fromRequest(UpsertFieldRequest $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            fieldTypeId: $request->integer('field_type_id'),
            address: $request->string('address')->toString(),
            pricePerHour: $request->float('price_per_hour'),
            description: $request->input('description'),
            images: $request->file('images', []),
        );
    }
}
