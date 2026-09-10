<?php

namespace App\Imports\Concerns;

use App\Models\Admin\{Person, PersonContact, PersonAddress, PersonBankingDetail};
use App\Imports\ValueObjects\EmployeeRowDTO;

trait PersonBuilder
{
    public function buildPerson(EmployeeRowDTO $dto): Person
    {
        $personCode = $dto->derivePersonCode();

        $person = Person::withTrashed()->where('person_code', $personCode)->first();

        if ($person) {
            $person->fill([
                'first_name'   => $dto->firstName,
                'last_name'    => $dto->lastName,
                'display_name' => $dto->displayName,
                'pan_no'       => $dto->panNo     ?? $person->pan_no,
                'aadhaar_no'   => $dto->aadhaarNo ?? $person->aadhaar_no,
                'dob'          => $dto->dob        ?? $person->dob,
                'gender'       => $dto->gender     ?? $person->gender,
                'updated_by'   => 1,
            ])->save();
        } else {
            $person = Person::create([
                'person_code'    => $personCode,
                'entity_type'    => 'individual',
                'salutation'     => $dto->salutation,
                'first_name'     => $dto->firstName,
                'last_name'      => $dto->lastName,
                'display_name'   => $dto->displayName,
                'gender'         => $dto->gender,
                'dob'            => $dto->dob,
                'marital_status' => $dto->maritalStatus,
                'father_name'    => $dto->fatherName,
                'blood_group'    => $dto->bloodGroup,
                'nationality'    => $dto->nationality,
                'pan_no'         => $dto->panNo,
                'aadhaar_no'     => $dto->aadhaarNo,
                'created_by'     => 1,
                'updated_by'     => 1,
            ]);
        }

        $this->buildPersonContacts($person, $dto);

        if ($dto->addressLine1) {
            $this->buildPersonAddress($person, $dto);
        }

        if ($dto->accountNumber) {
            $this->buildPersonBanking($person, $dto);
        }

        return $person;
    }

    private function buildPersonContacts(Person $person, EmployeeRowDTO $dto): void
    {
        if ($dto->mobile) {
            $this->upsertContact($person->id, 'Mobile', 'Primary', $dto->mobile);
        }

        if ($dto->officialMobile && $dto->officialMobile !== $dto->mobile) {
            $this->upsertContact($person->id, 'Mobile', 'Office', $dto->officialMobile);
        }

        if ($dto->email) {
            $this->upsertContact($person->id, 'Email', 'Primary', $dto->email);
        }

        if ($dto->officialEmail && $dto->officialEmail !== $dto->email) {
            $this->upsertContact($person->id, 'Email', 'Office', $dto->officialEmail);
        }
    }

    private function upsertContact(int $personId, string $dataType, string $contactType, string $detail): void
    {
        $exists = PersonContact::where('person_id', $personId)
            ->where('data_type', $dataType)
            ->where('contact_type', $contactType)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            PersonContact::where('person_id', $personId)
                ->where('data_type', $dataType)
                ->where('contact_type', $contactType)
                ->update(['contact_detail' => $detail, 'updated_by' => 1]);
        } else {
            PersonContact::create([
                'person_id'      => $personId,
                'data_type'      => $dataType,
                'contact_type'   => $contactType,
                'contact_detail' => $detail,
                'created_by'     => 1,
                'updated_by'     => 1,
            ]);
        }
    }

    private function buildPersonAddress(Person $person, EmployeeRowDTO $dto): void
    {
        PersonAddress::firstOrCreate(
            ['person_id' => $person->id, 'address_type' => 'Primary'],
            [
                'address_line_1' => $dto->addressLine1,
                'address_line_2' => $dto->addressLine2,
                'city'           => $dto->city,
                'state'          => $dto->state,
                'pincode'        => $dto->pincode,
                'country'        => 'India',
                'created_by'     => 1,
                'updated_by'     => 1,
            ]
        );
    }

    private function buildPersonBanking(Person $person, EmployeeRowDTO $dto): void
    {
        PersonBankingDetail::firstOrCreate(
            ['person_id' => $person->id, 'account_type' => 'Primary'],
            [
                'bank_name'           => $dto->bankName,
                'account_number'      => $dto->accountNumber,
                'ifsc_code'           => $dto->ifscCode,
                'account_holder_name' => $dto->accountHolderName ?? $dto->displayName,
                'is_verified'         => false,
                'created_by'          => 1,
                'updated_by'          => 1,
            ]
        );
    }
}
