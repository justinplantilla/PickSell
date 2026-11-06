<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReviewVote extends Model
{
    protected $fillable = ['product_review_id', 'user_id'];
}
