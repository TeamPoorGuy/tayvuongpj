<?php

namespace App\DTOs\Owner;

use App\Http\Requests\Api\Owner\SubmitApplicationRequest;
use Illuminate\Http\UploadedFile;

readonly class SubmitApplicationData
{
    public function __construct(
        public string $ownerName,
        public string $ownerIdNumber,
        public string $businessName,
        public string $businessAddress,
        public string $businessPhone,
        public string $businessLicense,
        public ?string $description,
        public ?UploadedFile $licenseFile,
    ) {}

    public static function fromRequest(SubmitApplicationRequest $request): self
    {
        return new self(
            ownerName: $request->string('owner_name')->toString(),
            ownerIdNumber: $request->string('owner_id_number')->toString(),
            businessName: $request->string('business_name')->toString(),
            businessAddress: $request->string('business_address')->toString(),
            businessPhone: $request->string('business_phone')->toString(),
            businessLicense: $request->string('business_license')->toString(),
            description: $request->input('description'),
            licenseFile: $request->file('license_file'),
        );
    }
}
