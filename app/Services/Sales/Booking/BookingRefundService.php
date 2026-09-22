<?php

namespace App\Services\Sales\Booking;

use App\Models\Module\Booking\Booking;
use App\Models\Module\Booking\Xl_Refunds;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Business logic for the Booking Refund sub-domain (requestRefund(),
 * refundView()/refundUpdate(), refundedUpdate(), rejectedView()),
 * extracted from BookingCrudController as part of the Sales-system
 * refactor (Phase 4) - see docs/refactor/ai-changelogs-DD-MM-YYYY.md.
 */
class BookingRefundService
{
    private const STATUS_NAMES = [
        1 => 'Live',
        2 => 'Invoiced',
        3 => 'Cancelled',
        4 => 'Refund Queued',
        5 => 'Refunded',
        6 => 'On Hold',
        7 => 'Refund Rejected',
        8 => 'Pending',
    ];

    /** Media field => collection name, shared by both requestRefund() and its display screens. */
    private const REQUEST_MEDIA_MAP = [
        'acc_proof' => 'acc-proof',
        'aadhar' => 'aadhar',
        'pan' => 'pan',
    ];

    /**
     * Resolves the display data both refundView() and rejectedView() need
     * for the latest refund record on a booking - the two screens'
     * "refund" detail array is byte-for-byte identical in the original,
     * differing only in which Media Library accessor they call
     * (getFirstMediaUrl() vs getFirstMedia()->getUrl()) to reach the same
     * URL, so unified here into one method.
     *
     * @return array{refund: ?Xl_Refunds, amount: float, deduction: float, acc_proof: string, aadhar: string, pan: string, pay_proof: string, refundDetails: ?array<string, mixed>}
     */
    public function resolveRefundDisplayData(Booking $booking): array
    {
        $refund = Xl_Refunds::where('entity_type', 'booking')
            ->where('entity_id', $booking->id)
            ->latest('id')
            ->first();

        $amount = $booking->booking_amount ?? 0;
        $deduction = 0;
        $accProof = '';
        $aadhar = '';
        $pan = '';
        $payProof = '';
        $refundDetails = null;

        if ($refund) {
            $deduction = ($booking->booking_amount ?? 0) - ($refund->amount ?? 0);

            $accProof = $refund->getFirstMediaUrl('acc-proof') ?: $refund->getFirstMediaUrl('acc_proof') ?: '';
            $aadhar = $refund->getFirstMediaUrl('aadhar') ?: $refund->getFirstMediaUrl('aadhaar') ?: '';
            $pan = $refund->getFirstMediaUrl('pan') ?: '';
            $payProof = $refund->getFirstMediaUrl('pay-proof') ?: $refund->getFirstMediaUrl('pay_proof') ?: '';

            $refundDetails = [
                'remaining_amount' => $refund->amount ?? 0,
                'bank_name' => $refund->bank_name ?? 'N/A',
                'branch_name' => $refund->branch_name ?? 'N/A',
                'account_type' => $refund->account_type ?? 'N/A',
                'account_number' => $refund->account_number ?? 'N/A',
                'holder_name' => $refund->holder_name ?? 'N/A',
                'ifsc_code' => $refund->ifsc_code ?? 'N/A',
                'details' => $refund->details ?? 'N/A',
                'req_date' => $refund->req_date ? Carbon::parse($refund->req_date)->format('d-M-Y') : 'N/A',
                'ref_date' => $refund->ref_date ? Carbon::parse($refund->ref_date)->format('d-M-Y') : 'N/A',
                'mode' => $refund->mode ?? 'N/A',
                'transaction_details' => $refund->transaction_details ?? 'N/A',
                'remark' => $refund->remark ?? 'N/A',
            ];
        }

        return compact('refund', 'amount', 'deduction', 'accProof', 'aadhar', 'pan', 'payProof', 'refundDetails');
    }

    /**
     * Applies a validated refund request: creates the Xl_Refunds row,
     * attaches whichever of acc_proof/aadhar/pan were uploaded (a failed
     * individual upload is logged and skipped, not fatal to the whole
     * request - preserves the original's inner try/catch), moves the
     * booking to status 4 (Refund Queued), and records history.
     *
     * BUG-103 (known-bugs-report.md): the "Refund Requested Again"
     * history branch checks $booking->status AFTER the status has
     * already been overwritten to 4 by the update() call above it, so it
     * compares 4 == 7 and never fires - preserved exactly, not silently
     * fixed, since the intended check (comparing the OLD status) is an
     * unambiguous one-line fix but changes user-visible history output.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<string, ?UploadedFile>  $files  keyed by acc_proof/aadhar/pan
     */
    public function apply(Booking $booking, array $validated, array $files): Xl_Refunds
    {
        $refund = Xl_Refunds::create([
            'entity_type' => 'booking',
            'entity_id' => $booking->id,
            'bank_name' => strtoupper(trim($validated['bank_name'] ?? '')),
            'branch_name' => strtoupper(trim($validated['branch_name'] ?? '')),
            'account_type' => $validated['account_type'] ?? null,
            'account_number' => trim($validated['account_number'] ?? ''),
            'holder_name' => trim($validated['holder_name'] ?? ''),
            'ifsc_code' => strtoupper(trim($validated['ifsc_code'] ?? '')),
            'req_date' => now()->format('Y-m-d'),
            'req_by' => backpack_auth()->id() ?? 'unknown',
            'amount' => (float) ($validated['remaining_amount'] ?? 0),
            'details' => trim($validated['deduction_reason'] ?? ''),
        ]);

        foreach (self::REQUEST_MEDIA_MAP as $field => $collection) {
            $file = $files[$field] ?? null;

            if ($file && $file->isValid()) {
                try {
                    $refund->addMedia($file)
                        ->withCustomProperties(['document_type' => $field])
                        ->toMediaCollection($collection, 'public');
                } catch (\Exception $mediaEx) {
                    Log::error('REFUND_MEDIA_UPLOAD_FAILED', [
                        'refund_id' => $refund->id,
                        'field' => $field,
                        'collection' => $collection,
                        'message' => $mediaEx->getMessage(),
                    ]);
                }
            }
        }

        $booking->update([
            'status' => 4,
            'refund_request_date' => now(),
        ]);

        if ($booking->status == 7) {
            $booking->addHistory(
                'commented',
                'Refund Requested Again',
                'Refund requested again after rejection.',
                [
                    'old_status' => 'Refund Rejected',
                    'new_status' => 'Refund Queued',
                ],
                null,
                backpack_user()
            );
        }

        $booking->addHistory(
            'commented',
            'Refund Requested',
            'Customer refund request has been submitted .',
            [
                'refund_amount' => $refund->amount ?? 0,
                'booking_amount' => $booking->booking_amount ?? 0,
                'deduction_amount' => $validated['deduction'] ?? 0,
                'bank_name' => $validated['bank_name'] ?? 'N/A',
                'account_holder' => $validated['holder_name'] ?? 'N/A',
                'account_number' => $validated['account_number'] ?? 'N/A',
                'ifsc_code' => $validated['ifsc_code'] ?? 'N/A',
                'deduction_reason' => $validated['deduction_reason'] ?? 'N/A',
                'status' => 'refund_requested',
            ],
            null,
            backpack_user()
        );

        return $refund;
    }

    /**
     * Applies the refundUpdate() flow: attaches the (mandatory) payment
     * proof, marks the booking Refunded (status 5), and records history.
     * Assumes an Xl_Refunds row already exists for this booking (caller
     * checks and redirects with an error otherwise, matching the
     * original's early-return shape).
     *
     * @param  array<string, mixed>  $validated
     */
    public function applyRefundUpdate(Booking $booking, Xl_Refunds $refund, array $validated, ?UploadedFile $payProof): Xl_Refunds
    {
        $refund->update([
            'ref_date' => $validated['hidden_ref'] ?? $validated['ref_date'],
            'ref_by' => backpack_auth()->id(),
            'mode' => $validated['mode'],
            'transaction_details' => $validated['transaction_details'],
            'remark' => $validated['remark'],
        ]);

        if ($payProof && $payProof->isValid()) {
            $refund->clearMediaCollection('pay-proof');
            $refund->addMedia($payProof)->toMediaCollection('pay-proof');
        }

        $oldStatus = $booking->status;
        $newStatus = 5;

        $oldName = self::STATUS_NAMES[$oldStatus] ?? 'Unknown';
        $newName = self::STATUS_NAMES[$newStatus] ?? 'Unknown';

        $booking->update([
            'status' => $newStatus,
            'refund_date' => now()->format('Y-m-d'),
        ]);

        $booking->addHistory(
            'commented',
            'Refund Completed',
            'Refund processed successfully.',
            [
                'old_status' => $oldName,
                'new_status' => $newName,
                'refund_date' => $validated['hidden_ref'] ?? $validated['ref_date'],
                'mode' => $validated['mode'],
                'transaction_details' => $validated['transaction_details'],
                'remark' => $validated['remark'],
            ],
            null,
            backpack_user()
        );

        return $refund;
    }

    /**
     * Applies the refundedUpdate() flow: edits an already-Refunded
     * booking's refund record (date/mode/transaction details/remark/pay
     * proof) without touching booking status, logging a human-readable
     * diff into booking history.
     *
     * @param  array<string, mixed>  $validated
     */
    public function applyRefundedUpdate(Booking $booking, Xl_Refunds $refund, array $validated, ?UploadedFile $payProof): Xl_Refunds
    {
        $changes = [];

        $newRefDate = $validated['hidden_ref'] ?? $validated['ref_date'];
        if ($refund->ref_date != $newRefDate) {
            $changes[] = 'Refund Date changed from '
                .($refund->ref_date ? Carbon::parse($refund->ref_date)->format('d-M-Y') : 'N/A')
                .' to '.Carbon::parse($newRefDate)->format('d-M-Y');
            $refund->ref_date = $newRefDate;
        }

        if ($refund->mode != $validated['mode']) {
            $changes[] = "Mode of Payment changed from {$refund->mode} to {$validated['mode']}";
            $refund->mode = $validated['mode'];
        }

        if ($refund->transaction_details != ($validated['transaction_details'] ?? null)) {
            $changes[] = 'Transaction Details updated';
            $refund->transaction_details = $validated['transaction_details'] ?? null;
        }

        if ($refund->remark != ($validated['remark'] ?? null)) {
            $changes[] = 'Remarks updated';
            $refund->remark = $validated['remark'] ?? null;
        }

        if ($payProof && $payProof->isValid()) {
            $refund->clearMediaCollection('pay-proof');
            $refund->addMedia($payProof)->toMediaCollection('pay-proof');
            $changes[] = 'Payment Proof updated';
        }

        $refund->ref_by = backpack_auth()->id();
        $refund->save();

        $booking->addHistory(
            'commented',
            'Refund Details Updated',
            'Refund details modified .',
            [
                'changes' => $changes,
            ],
            null,
            backpack_user()
        );

        return $refund;
    }
}
