<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all permissions
        $permissions = [
            // User management. `user.view` = boleh membuka menu Users;
            // `user.view_all` = melihat seluruh karyawan, bukan hanya tim sendiri.
            'user.view', 'user.view_all', 'user.create', 'user.edit', 'user.delete',
            // Presence / attendance
            'presence.view', 'presence.create', 'presence.edit', 'presence.delete', 'presence.scan',
            // Salary
            'salary.view', 'salary.edit', 'salary.recalculate', 'salary.pay', 'salary.export',
            // Salary recap
            'salary_recap.view', 'salary_recap.edit', 'salary_recap.export', 'salary_recap.print',
            // Loan
            'loan.view', 'loan.create', 'loan.edit', 'loan.delete', 'loan.export',
            // Loan payment
            'loan_payment.view', 'loan_payment.create', 'loan_payment.edit', 'loan_payment.delete',
            // Leave
            'leave.view_all', 'leave.view_own', 'leave.request', 'leave.approve', 'leave.reject',
            'leave.configure', 'leave.manage_balance',
            // Schedule
            'schedule.view', 'schedule.edit', 'schedule.mass_update',
            // National holiday
            'national_holiday.view', 'national_holiday.edit',
            // Company profile
            'company_profile.view', 'company_profile.edit',
            // Accounting config
            'acc.view', 'acc.edit',
            // Internal accounting ledger (M12)
            'accounting.view', 'accounting.edit',
            // Reports
            'report.view', 'report.export',
            // Audit
            'audit.view',
            // Approval engine
            'approval.view_all', 'approval.act', 'approval.configure',
            // Organisation structure
            'org.view', 'org.edit',
            // Branches — geofence per cabang
            'branch.view', 'branch.edit',
            // Employee documents
            'document.view', 'document.edit',
            // Tax & BPJS rates
            'tax.view', 'tax.edit',
            // Recruitment (M09)
            'recruitment.view', 'recruitment.edit',
            // Performance (M10). performance.review_self = isi self-review sendiri.
            'performance.view', 'performance.edit', 'performance.review_self',
            // Role & permission management — super_admin only
            'role.view', 'role.edit', 'permission.view',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        // 1. Super Admin — full access
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions($permissions);

        // 2. HR Admin — HR operations
        $hrAdmin = Role::firstOrCreate(['name' => 'hr_admin', 'guard_name' => 'web']);
        $hrAdmin->syncPermissions([
            'user.view', 'user.view_all', 'user.create', 'user.edit', 'user.delete',
            'presence.view', 'presence.create', 'presence.edit', 'presence.delete', 'presence.scan',
            'salary.view', 'salary.edit', 'salary.recalculate', 'salary.pay', 'salary.export',
            'salary_recap.view', 'salary_recap.edit', 'salary_recap.export', 'salary_recap.print',
            'loan.view', 'loan.create', 'loan.edit', 'loan.delete', 'loan.export',
            'loan_payment.view', 'loan_payment.create', 'loan_payment.edit', 'loan_payment.delete',
            'leave.view_all', 'leave.view_own', 'leave.request', 'leave.approve', 'leave.reject',
            'leave.configure', 'leave.manage_balance',
            'schedule.view', 'schedule.edit', 'schedule.mass_update',
            'national_holiday.view', 'national_holiday.edit',
            'company_profile.view', 'company_profile.edit',
            'acc.view', 'acc.edit',
            'accounting.view', 'accounting.edit',
            'report.view', 'report.export',
            'audit.view',
            'approval.view_all', 'approval.act',
            'org.view', 'org.edit',
            'branch.view', 'branch.edit',
            'document.view', 'document.edit',
            'tax.view', 'tax.edit',
            'recruitment.view', 'recruitment.edit',
            'performance.view', 'performance.edit', 'performance.review_self',
            // Deliberately NOT granted: approval.configure, role.*, permission.*
            // — changing who can approve what is a super_admin decision.
        ]);

        // 3. Manager — team view + approvals.
        // Semuanya izin BACA saja. Daftar karyawan dan presensi disempitkan ke
        // bawahan langsung (lihat User::scopeVisibleTo) — punya `user.view`
        // tidak berarti melihat seluruh perusahaan.
        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager->syncPermissions([
            'user.view',
            'presence.view',
            'salary.view',
            'salary_recap.view',
            'loan.view',
            'loan_payment.view',
            'leave.view_all', 'leave.view_own', 'leave.approve', 'leave.reject',
            'schedule.view',
            'report.view', 'report.export',
            'approval.act',
            'org.view',
            // Manager menilai timnya + mengisi self-review sendiri.
            'performance.view', 'performance.edit', 'performance.review_self',
        ]);

        // 4. Employee — self-service only
        $employee = Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
        $employee->syncPermissions([
            'presence.view', 'presence.scan',
            'salary.view',
            'salary_recap.view',
            'loan.view',
            'loan_payment.view',
            'leave.view_own', 'leave.request',
            'schedule.view',
            // Karyawan mengisi self-review kinerjanya sendiri.
            'performance.review_self',
        ]);
    }
}
