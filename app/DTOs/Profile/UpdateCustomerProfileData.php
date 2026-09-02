<?php

namespace App\DTOs\Profile;

use App\Http\Requests\Api\Profile\UpdateCustomerProfileRequest;
use Illuminate\Http\UploadedFile;

readonly class UpdateCustomerProfileData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public ?string $address,
        public ?UploadedFile $avatar,
    ) {}

    public static function fromRequest(UpdateCustomerProfileRequest $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            phone: $request->string('phone')->toString(),
            address: $request->input('address'),
            avatar: $request->file('avatar'),
        );
    }
}
