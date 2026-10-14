<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A dependency can name a fallback output: when the required output isn't
 * produced in a project (its package is switched off by the project's
 * scope), the consumer waits on the fallback instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dependency_rules', function (Blueprint $table) {
            $table->string('fallback_output_key')->nullable()->after('required_output_key');
        });

        // Bring an already-seeded library up to date without reseeding (the
        // seeder can't replace templates that existing projects use). Same
        // rules as the seeders.
        foreach (self::RULES as [$consumer, $required, $changes]) {
            DB::table('dependency_rules')
                ->where('required_output_key', $required)
                ->whereIn('consumer_template_id', DB::table('package_templates')->where('code', $consumer)->select('id'))
                ->update($changes);
        }
    }

    /** @var list<array{string, string, array<string, mixed>}> consumer template, required output, changes */
    private const RULES = [
        ['hvac_cr_30', 'hvac_basis_approved', ['fallback_output_key' => 'bod_concept_ga_approved']],
        ['con_tx_civil', 'civil_ifc_sr_fdn_approved', ['fallback_output_key' => 'civil_ifc_eqpad_approved']],
        ['ms_aux_power', 'switchroom_energised', ['fallback_output_key' => 'substation_energised']],
        ['ms_sr_energised', 'substation_energised', ['is_mandatory' => false]],
    ];

    public function down(): void
    {
        DB::table('dependency_rules')
            ->where('required_output_key', 'substation_energised')
            ->whereIn('consumer_template_id', DB::table('package_templates')->where('code', 'ms_sr_energised')->select('id'))
            ->update(['is_mandatory' => true]);

        Schema::table('dependency_rules', function (Blueprint $table) {
            $table->dropColumn('fallback_output_key');
        });
    }
};
