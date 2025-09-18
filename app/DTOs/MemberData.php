<?php

namespace App\DTOs;

class MemberData
{
    public function __construct(
        public ?int $id,
        public string $first_name,
        public string $last_name,
        public string $email,
        public ?string $phone_number,
        public ?string $gender,
        public ?string $address,
        public ?string $community,
        public ?bool $worker,
        public ?string $status,
        public ?string $date_of_birth,
        public ?string $date_of_visit,
        public ?string $country,
        public ?string $city_or_state,
        public ?string $facebook,
        public ?string $instagram,
        public ?string $linkedin,
        public ?string $twitter,
        public ?string $password = null,
        public readonly array $unit_ids = [],
        public readonly array $leader_unit_ids = [],
    ) {
    }
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            first_name: $data['first_name'],
            last_name: $data['last_name'],
            email: $data['email'],
            phone_number: $data['phone_number'] ?? null,
            gender: $data['gender'] ?? null,
            address: $data['address'] ?? null,
            community: $data['community'] ?? null,
            worker: $data['worker'] ?? false,
            status: $data['status'] ?? 'active',
            date_of_birth: $data['date_of_birth'] ?? null,
            date_of_visit: $data['date_of_visit'] ?? null,
            country: $data['country'] ?? null,
            city_or_state: $data['city_or_state'] ?? null,
            facebook: $data['facebook'] ?? null,
            instagram: $data['instagram'] ?? null,
            linkedin: $data['linkedin'] ?? null,
            twitter: $data['twitter'] ?? null,
            password: $data['password'] ?? null,
        );
    }
}
