<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('api_token', 512)->nullable()->after('code');
        });

        // Generate JWT-style token for existing companies
        DB::table('companies')->whereNull('api_token')->get()->each(function ($company) {
            $header  = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
            $payload = base64_encode(json_encode([
                'sub'  => $company->id,
                'code' => $company->code,
                'iat'  => now()->timestamp,
                'jti'  => Str::random(16),
            ]));
            $secret    = config('app.key');
            $signature = base64_encode(hash_hmac('sha256', "{$header}.{$payload}", $secret, true));
            $token     = "{$header}.{$payload}.{$signature}";

            DB::table('companies')->where('id', $company->id)->update(['api_token' => $token]);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('api_token');
        });
    }
};
