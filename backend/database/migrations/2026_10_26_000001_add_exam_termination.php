<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->timestamp('terminated_at')->nullable();
            $table->string('termination_reason', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('exam_sessions', fn (Blueprint $table) => $table->dropColumn(['terminated_at', 'termination_reason']));
    }
};
