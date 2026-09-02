<?php

namespace App\DTOs\Auth;

use App\Http\Requests\Api\Auth\RegisterRequest;

readonly class RegisterUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $role,
        public string $phone,
        public ?string $address,
        public ?string $businessName,
        public ?string $businessAddress,
        public ?string $businessPhone,
    ) {}

    public static function fromRequest(RegisterRequest $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
            role: $request->string('role')->toString(),
            phone: $request->string('phone')->toString(),
            address: $request->input('address'),
            businessName: $request->input('business_name'),
            businessAddress: $request->input('business_address'),
            businessPhone: $request->input('business_phone'),
        );
    }
}
