<?php

namespace App\DTOs\Owner;

use App\Http\Requests\Api\Owner\SubmitApplicationRequest;
use Illuminate\Http\UploadedFile;

readonly class SubmitApplicationData
{
    /** @param array<string, UploadedFile> $documents keyed by OwnerDocument type */
    public function __construct(
        public string $ownerName,
        public string $ownerIdNumber,
        public string $businessName,
        public string $businessAddress,
        public string $businessPhone,
        public string $businessLicense,
        public ?string $description,
        public string $bankName,
        public string $bankAccountNumber,
        public string $bankAccountHolder,
        public ?string $ip,
        public array $documents,
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
            bankName: $request->string('bank_name')->toString(),
            bankAccountNumber: $request->string('bank_account_number')->toString(),
            bankAccountHolder: $request->string('bank_account_holder')->toString(),
            ip: $request->ip(),
            documents: $request->file('documents', []),
        );
    }
}
