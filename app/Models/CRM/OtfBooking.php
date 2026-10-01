<?php

namespace App\Models\CRM;

use App\Models\BaseModel;

/**
 * An OEM order-taking-form (OTF) booking row imported into CRM (`xlr8_crm_booking`), shown on the enquiry screen's OTF
 * list and detail page; not the dealership booking (`xlr8_booking_master`). Holds customer identifiers — never log
 * them (DEC-093).
 *
 * @property int $id
 * @property ?string $x8_enq_no
 * @property ?string $booking_number
 * @property ?string $booking_date
 * @property ?string $status
 * @property ?string $oem_code
 */
class OtfBooking extends BaseModel
{
    protected $table = 'xlr8_crm_booking';

    protected $fillable = [
        'x8_booking_no', 'x8_quotation_no', 'x8_enq_no', 'booking_number', 'booking_date', 'sc_mile_id', 'status',
        'cancellation_date', 'oem_code', 'customer_code', 'customer_name', 'customer_address', 'customer_city',
        'customer_tehsil', 'customer_district', 'customer_pan', 'customer_tan', 'customer_aadhar', 'invoice_no',
        'evaluation_no', 'so_no', 'otf_no', 'is_active',
    ];
}
