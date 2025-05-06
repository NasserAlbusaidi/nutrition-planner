<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PlanItem extends Model
{
    use HasFactory;

            // Generally safer not to make these fillable, create them programmatically
            protected $guarded = ['id']; // Or specify fillable fields carefully

            /**
             * Get the plan that owns the item.
             */
            public function plan()
            {
                return $this->belongsTo(Plan::class);
            }

            /**
             * Get the product associated with the item (if any).
             */
            public function product()
            {
                return $this->belongsTo(Product::class); // Assumes product_id column
            }
}
