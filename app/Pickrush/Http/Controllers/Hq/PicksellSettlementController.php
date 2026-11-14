<?php

namespace App\Pickrush\Http\Controllers\Hq;

use App\Pickrush\Http\Controllers\PortalController;
use App\Pickrush\Models\CompanySettlement;
use App\Pickrush\Services\Hq\PicksellSettlement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** HQ finance paying PickSell what the company owes, and answering PickSell's disputes. */
class PicksellSettlementController extends PortalController
{
    private const SLIP_RULES = 'required|file|mimes:jpg,jpeg,png,pdf|max:5120';

    private const SLIP_MESSAGES = [
        'slip.required' => 'Attach a photo or PDF of the deposit slip.',
        'slip.mimes' => 'The deposit slip must be a JPG, PNG or PDF.',
        'slip.max' => 'The deposit slip must be 5 MB or smaller.',
    ];

    public function __construct(private PicksellSettlement $picksell) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'total' => 'required|numeric',
            'reference_no' => 'required|string|max:60',
            'slip' => self::SLIP_RULES,
            'note' => 'nullable|string|max:500',
        ], self::SLIP_MESSAGES);

        return $this->attempt(fn () => $this->picksell->submit($this->staff(), $data['reference_no'], $request->file('slip'), $data['note'] ?? null, (float) $data['total']),
            fn (CompanySettlement $s) => '₱'.number_format((float) $s->amount, 2).' settlement sent to PickSell. PickSell will check it against its bank statement.');
    }

    public function topUp(Request $request, CompanySettlement $settlement)
    {
        $data = $request->validate([
            'shortfall' => 'required|numeric|min:0',
            'reference_no' => 'required|string|max:60',
            'slip' => self::SLIP_RULES,
        ], self::SLIP_MESSAGES);

        return $this->attempt(fn () => $this->picksell->topUp($this->staff(), $settlement, $data['reference_no'], $request->file('slip'), (float) $data['shortfall']),
            fn (CompanySettlement $s) => '₱'.number_format((float) $s->amount, 2).' top-up sent to PickSell.');
    }

    public function contest(Request $request, CompanySettlement $settlement)
    {
        $data = $request->validate([
            'explanation' => 'required|string|min:10|max:1000',
            'evidence' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ], ['evidence.mimes' => 'The evidence must be a JPG, PNG or PDF.', 'evidence.max' => 'The evidence must be 5 MB or smaller.']);

        return $this->attempt(fn () => $this->picksell->contest($this->staff(), $settlement, $data['explanation'], $request->file('evidence')),
            'Sent back to PickSell to check again.');
    }

    /** The slip this company attached to its settlement. */
    public function slip(CompanySettlement $settlement)
    {
        abort_unless($settlement->slip_path && Storage::disk('local')->exists($settlement->slip_path), 404);

        return Storage::disk('local')->response($settlement->slip_path);
    }
}
