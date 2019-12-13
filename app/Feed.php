<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Feed extends Model
{
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'uuid', 'comment', 'created_at', 'updated_at'
    ];

    protected $hidden = ['id'];

    /**
     * Returns the available keys stored in redis
     *
     * @return array
     */
    public function props() {
        return $this->fillable;
    }
}
