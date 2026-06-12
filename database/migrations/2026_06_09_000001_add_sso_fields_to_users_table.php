<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('sso_provider')->nullable()->after('id');
            $table->string('sso_subject')->nullable()->after('sso_provider');
            $table->json('sso_claims')->nullable()->after('remember_token');
            $table->unique(['sso_provider', 'sso_subject']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['sso_provider', 'sso_subject']);
            $table->dropColumn(['sso_provider', 'sso_subject', 'sso_claims']);
        });
    }
};
