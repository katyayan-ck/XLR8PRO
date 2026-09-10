<?php

namespace App\Services\Vehicle;

use App\Dtos\Vehicle\Segment\SegmentDto;
use App\Enums\ErrorCodeEnum;
use App\Exceptions\ApplicationException;
use App\Exceptions\GeneralException;
use App\Models\Vehicle\Segment;

class SegmentService
{
    public function getAll()
    {
        return Segment::orderBy('name')
            ->get();
    }

    public function getById(int $id): Segment
    {
        $segment = Segment::find($id);

        if (!$segment) {

            throw new GeneralException(
                'Segment not found',
                ErrorCodeEnum::SEGMENT_NOT_FOUND
            );
        }

        return $segment;
    }

    public function create(
        SegmentDto $dto
    ): Segment {

        if (
            Segment::where(
                'code',
                $dto->code
            )->exists()
        ) {

            throw new GeneralException(
                'Segment code already exists',
                ErrorCodeEnum::SEGMENT_ALREADY_EXISTS
            );
        }

        return Segment::create([
            'brand_code' => config('brand.code'),
            'code' => $dto->code,
            'name' => $dto->name,
            'is_active' => true,
            'created_by' => backpack_auth()->id(),
        ]);
    }

    public function update(
        int $id,
        SegmentDto $dto
    ): Segment {

        $segment = $this->getById($id);

        $segment->update([
            'code' => $dto->code,
            'name' => $dto->name,
            'updated_by' => backpack_auth()->id(),
        ]);

        return $segment->fresh();
    }

    public function delete(int $id): void
    {
        $segment = $this->getById($id);

        if ($segment->subSegments()->exists()) {

            throw new GeneralException(
                'Cannot delete segment because sub segments exist',
                ErrorCodeEnum::SEGMENT_DELETE_NOT_ALLOWED
            );
        }

        $segment->deleted_by = backpack_auth()->id();
        $segment->save();

        $segment->delete();
    }
}