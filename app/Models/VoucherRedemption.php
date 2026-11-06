<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherRedemption extends Model
{
    protected $fillable = ['voucher_id', 'buyer_id', 'order_shipment_id', 'amount'];

    protected $casts = ['amount' => 'decimal:2'];

    public function voucher() { return $this->belongsTo(Voucher::class); }
}
