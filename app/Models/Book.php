<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    public function pages() {
        return $this->hasMany(Page::class);
    }

    public function lectures() {
        // Lấy trực tiếp tất cả bài giảng thuộc cuốn sách này qua bảng trung gian pages
        return $this->hasManyThrough(Lecture::class, Page::class);
    }
}
