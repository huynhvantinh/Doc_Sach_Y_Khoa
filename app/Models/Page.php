<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    protected $casts = [
        'raw_json' => 'array',
        'processed_json' => 'array',
        'response_json' => 'array',
    ];

    public function book() {
        return $this->belongsTo(Book::class);
    }

    // Danh sách tất cả bài giảng của trang
    public function lectures() {
        return $this->hasMany(Lecture::class);
    }

    // Lấy bài giảng mới nhất để hiển thị mặc định
    public function latestLecture()
    {
        return $this->hasOne(Lecture::class)->latestOfMany();
    }

}
