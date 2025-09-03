<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('invoice_reminders', function (Blueprint $table) {
            $table->boolean('sent')->default(false)->after('archived_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('invoice_reminders', function (Blueprint $table) {
            $table->dropColumn('sent');
        });
    }
};
