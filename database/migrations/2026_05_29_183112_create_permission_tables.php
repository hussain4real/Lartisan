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
        $teams = (bool) config('permission.teams');
        $tableNames = config('permission.table_names');
        throw_if(! is_array($tableNames) || $tableNames === [], 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        /** @var array{permissions: string, roles: string, model_has_permissions: string, model_has_roles: string, role_has_permissions: string} $tableNames */
        $tableNames = $tableNames;

        $columnNames = config('permission.column_names');
        throw_if(! is_array($columnNames), 'Error: column_names on config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        /** @var array{role_pivot_key?: string, permission_pivot_key?: string, team_foreign_key?: string, model_morph_key: string} $columnNames */
        $columnNames = $columnNames;
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $teamForeignKey = $columnNames['team_foreign_key'] ?? null;
        $isTesting = (bool) config('permission.testing');

        throw_if($teams && empty($teamForeignKey), 'Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        /**
         * See `docs/prerequisites.md` for suggested lengths on 'name' and 'guard_name' if "1071 Specified key was too long" errors are encountered.
         */
        Schema::create($tableNames['permissions'], static function (Blueprint $table) {
            $table->id(); // permission id
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        /**
         * See `docs/prerequisites.md` for suggested lengths on 'name' and 'guard_name' if "1071 Specified key was too long" errors are encountered.
         */
        Schema::create($tableNames['roles'], static function (Blueprint $table) use ($teams, $teamForeignKey, $isTesting) {
            $table->id(); // role id
            if ($teamForeignKey !== null && ($teams || $isTesting)) { // permission.testing is a fix for sqlite testing
                $table->unsignedBigInteger($teamForeignKey)->nullable();
                $table->index($teamForeignKey, 'roles_team_foreign_key_index');
            }
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            if ($teamForeignKey !== null && ($teams || $isTesting)) {
                $table->unique([$teamForeignKey, 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });

        Schema::create($tableNames['model_has_permissions'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission, $teamForeignKey, $teams) {
            $table->unsignedBigInteger($pivotPermission);

            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign($pivotPermission)
                ->references('id') // permission id
                ->on($tableNames['permissions'])
                ->cascadeOnDelete();
            if ($teamForeignKey !== null && $teams) {
                $table->unsignedBigInteger($teamForeignKey);
                $table->index($teamForeignKey, 'model_has_permissions_team_foreign_key_index');

                $table->primary([$teamForeignKey, $pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary');
            } else {
                $table->primary([$pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary');
            }
        });

        Schema::create($tableNames['model_has_roles'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotRole, $teamForeignKey, $teams) {
            $table->unsignedBigInteger($pivotRole);

            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign($pivotRole)
                ->references('id') // role id
                ->on($tableNames['roles'])
                ->cascadeOnDelete();
            if ($teamForeignKey !== null && $teams) {
                $table->unsignedBigInteger($teamForeignKey);
                $table->index($teamForeignKey, 'model_has_roles_team_foreign_key_index');

                $table->primary([$teamForeignKey, $pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary');
            } else {
                $table->primary([$pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary');
            }
        });

        Schema::create($tableNames['role_has_permissions'], static function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission) {
            $table->unsignedBigInteger($pivotPermission);
            $table->unsignedBigInteger($pivotRole);

            $table->foreign($pivotPermission)
                ->references('id') // permission id
                ->on($tableNames['permissions'])
                ->cascadeOnDelete();

            $table->foreign($pivotRole)
                ->references('id') // role id
                ->on($tableNames['roles'])
                ->cascadeOnDelete();

            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        $cacheStore = config('permission.cache.store');
        $cacheKey = config('permission.cache.key');

        throw_if(! is_string($cacheKey) || $cacheKey === '', 'Error: cache key on config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        app('cache')
            ->store(is_string($cacheStore) && $cacheStore !== 'default' ? $cacheStore : null)
            ->forget($cacheKey);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        throw_if(! is_array($tableNames) || $tableNames === [], 'Error: config/permission.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.');

        /** @var array{permissions: string, roles: string, model_has_permissions: string, model_has_roles: string, role_has_permissions: string} $tableNames */
        $tableNames = $tableNames;

        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
    }
};
