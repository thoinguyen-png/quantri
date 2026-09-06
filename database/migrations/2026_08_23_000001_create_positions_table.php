<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('positions')) {
            Schema::create('positions', function (Blueprint $table) {
                $table->id();
                $table->string('name')->comment('Tên chức vụ tiếng Việt');
                $table->string('name_en')->nullable()->comment('Tên chức vụ tiếng Anh in thẻ');
                $table->string('code', 50)->unique()->comment('Slug định danh');
                $table->string('system_role', 30)->default('staff')->comment('Nhóm quyền hệ thống: admin, manager, cashier, staff');
                $table->integer('sort_order')->default(0)->comment('Thứ tự sắp xếp hiển thị');
                $table->boolean('is_active')->default(true)->comment('Trạng thái hoạt động');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
