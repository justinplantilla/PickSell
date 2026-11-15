<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Truck papers and trip seals.
 *
 * - A truck carries its LTO papers: the CR (who owns it: CR no., MV file no., engine and chassis
 *   no.) and the OR of its yearly registration, which expires. A truck without them, or with an
 *   expired OR, cannot take a trip.
 * - Every renewal of a truck's OR/CR or a driver's license keeps what it replaced.
 * - A trip leaves sealed. The receiving hub marks the truck arrived and checks the seal before
 *   unloading; a broken or different seal opens an exception and unloading waits for the hub
 *   manager's approval. A trip not arrived by its due time is overdue.
 *
 * Trucks already registered get sample papers so they can keep taking trips; the hub manager
 * replaces them with the real ones (and uploads the photos) under Trucks & drivers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pr_vehicles', function (Blueprint $table) {
            $table->string('cr_no', 20)->nullable()->unique()->after('registered_owner');
            $table->string('mv_file_no', 20)->nullable()->unique()->after('cr_no');
            $table->string('engine_no', 30)->nullable()->after('mv_file_no');
            $table->string('chassis_no', 30)->nullable()->unique()->after('engine_no');
            $table->string('or_no', 20)->nullable()->after('chassis_no');
            $table->date('registration_expiry')->nullable()->after('or_no');
            $table->string('or_photo_path')->nullable()->after('registration_expiry');   // private disk
            $table->string('cr_photo_path')->nullable()->after('or_photo_path');
        });

        Schema::create('pr_fleet_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->nullable()->constrained('pr_vehicles')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('pr_drivers')->cascadeOnDelete();
            $table->string('document', 20);              // or_cr | license
            $table->json('previous');                    // the numbers, expiry and photo paths it replaced
            $table->foreignId('renewed_by')->nullable()->constrained('pr_staff')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('pr_manifests', function (Blueprint $table) {
            $table->string('seal_no', 30)->nullable()->unique()->after('driver_name');
            $table->timestamp('due_at')->nullable()->after('dispatched_at');
            $table->timestamp('arrived_at')->nullable()->after('due_at');
            $table->foreignId('arrived_by')->nullable()->after('arrived_at')->constrained('pr_staff')->nullOnDelete();
            $table->string('seal_status', 20)->nullable()->after('arrived_by');   // intact | broken | approved | none (left without one)
            $table->string('seal_found_no', 30)->nullable()->after('seal_status');
            $table->foreignId('seal_approved_by')->nullable()->after('seal_found_no')->constrained('pr_staff')->nullOnDelete();
            $table->timestamp('seal_approved_at')->nullable()->after('seal_approved_by');
        });

        Schema::table('pr_exceptions', function (Blueprint $table) {
            $table->enum('type', ['missing', 'damaged', 'mis_sorted', 'unlabeled', 'no_route', 'seal_broken'])->change();
        });

        // Trips already being unloaded or done left before seals: they count as arrived when first scanned.
        DB::table('pr_manifests')->whereIn('status', ['received', 'closed'])->whereNull('arrived_at')
            ->update(['arrived_at' => DB::raw('coalesce(received_at, closed_at, dispatched_at)'), 'seal_status' => 'none']);

        $this->samplePapers();
    }

    public function down(): void
    {
        DB::table('pr_exceptions')->where('type', 'seal_broken')->delete();
        Schema::table('pr_exceptions', function (Blueprint $table) {
            $table->enum('type', ['missing', 'damaged', 'mis_sorted', 'unlabeled', 'no_route'])->change();
        });

        Schema::table('pr_manifests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seal_approved_by');
            $table->dropConstrainedForeignId('arrived_by');
            $table->dropUnique(['seal_no']);
            $table->dropColumn(['seal_no', 'due_at', 'arrived_at', 'seal_status', 'seal_found_no', 'seal_approved_at']);
        });

        Schema::dropIfExists('pr_fleet_renewals');

        Schema::table('pr_vehicles', function (Blueprint $table) {
            $table->dropUnique(['cr_no']);
            $table->dropUnique(['mv_file_no']);
            $table->dropUnique(['chassis_no']);
            $table->dropColumn(['cr_no', 'mv_file_no', 'engine_no', 'chassis_no', 'or_no', 'registration_expiry', 'or_photo_path', 'cr_photo_path']);
        });
    }

    /**
     * Sample OR/CR for the trucks registered before papers were asked for. The OR runs to the end
     * of the month LTO assigns by the plate's last digit (1 = January … 0 = October), next year
     * at the latest.
     */
    private function samplePapers(): void
    {
        foreach (DB::table('pr_vehicles')->whereNull('cr_no')->orderBy('id')->get(['id', 'plate_no']) as $vehicle) {
            DB::table('pr_vehicles')->where('id', $vehicle->id)->update([
                'cr_no' => sprintf('%09d', 300000000 + $vehicle->id),
                'mv_file_no' => sprintf('0401-%011d', 21000000000 + $vehicle->id),
                'engine_no' => sprintf('4HK1-%06d', 500000 + $vehicle->id),
                'chassis_no' => sprintf('JALFRR90L%08d', 7000000 + $vehicle->id),
                'or_no' => sprintf('%09d', 800000000 + $vehicle->id),
                'registration_expiry' => self::registrationMonthEnd($vehicle->plate_no)->toDateString(),
            ]);
        }
    }

    private static function registrationMonthEnd(string $plate): \Illuminate\Support\Carbon
    {
        preg_match('/(\d)\D*$/', $plate, $m);
        $month = isset($m[1]) ? ((int) $m[1] ?: 10) : 12;
        $end = today()->setDate(today()->year, $month, 1)->endOfMonth()->startOfDay();

        return $end->lt(today()->addMonths(2)) ? $end->addYearNoOverflow()->endOfMonth()->startOfDay() : $end;
    }
};
