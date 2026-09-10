<?php
// app/Services/Vehicle/AccessoryImportService.php
// Thin compatibility wrapper — prefer AccessoryService directly.

namespace App\Services\Vehicle;

class AccessoryImportService
{
    public function __construct(protected AccessoryService $service) {}

    /**
     * @return array{success:bool,message:string,total_records:int,imported_count:int,skipped_count:int,errors_count:int}
     */
    public function execute(string $path, int $userId = 1, array $options = []): array
    {
        $result = $this->service->importExcelWithSheetOrder($path, $userId);

        return [
            'success'        => $result['success'],
            'message'        => $result['message'],
            'total_records'  => $result['total_records'],
            'imported_count' => $result['imported_count'],
            'skipped_count'  => $result['skipped_count'],
            'errors_count'   => $result['errors_count'],
        ];
    }
}
