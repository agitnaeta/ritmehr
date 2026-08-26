<?php

namespace App\Notifications;

use App\Models\Notification;

/**
 * Title/body copy for every notification type, in one place.
 *
 * Each template is a callable receiving the payload array and returning
 * ['title' => ..., 'body' => ...]. Unknown types fall back to a generic
 * rendering so a typo produces a dull notification rather than an exception.
 */
class NotificationTemplates
{
    public static function render(string $type, array $data = []): array
    {
        $templates = self::all();

        if (! isset($templates[$type])) {
            return [
                'title' => $data['title'] ?? 'Notifikasi',
                'body'  => $data['body'] ?? '',
            ];
        }

        return $templates[$type]($data);
    }

    public static function labels(): array
    {
        return [
            Notification::LATE_ALERT         => 'Karyawan Terlambat',
            Notification::MISSING_CHECKIN    => 'Belum Absen Masuk',
            Notification::MISSING_CHECKOUT   => 'Belum Absen Pulang',
            Notification::OUT_OF_RADIUS      => 'Absen di Luar Radius',
            Notification::LEAVE_SUBMITTED    => 'Pengajuan Cuti Masuk',
            Notification::LEAVE_APPROVED     => 'Cuti Disetujui',
            Notification::LEAVE_REJECTED     => 'Cuti Ditolak',
            Notification::LEAVE_BALANCE_LOW  => 'Saldo Cuti Menipis',
            Notification::SALARY_PAID        => 'Gaji Dibayarkan',
            Notification::LOAN_CREATED       => 'Kasbon Dibuat',
            Notification::APPROVAL_PENDING   => 'Persetujuan Menunggu',
            Notification::APPROVAL_DIGEST    => 'Ringkasan Persetujuan',
            Notification::DOCUMENT_EXPIRING  => 'Dokumen Akan Kedaluwarsa',
        ];
    }

    private static function all(): array
    {
        return [
            Notification::LATE_ALERT => fn ($d) => [
                'title' => 'Karyawan Terlambat Hari Ini',
                'body'  => ($d['count'] ?? 0) . ' karyawan tercatat terlambat pada '
                           . ($d['date'] ?? '-') . '.',
            ],

            Notification::MISSING_CHECKIN => fn ($d) => [
                'title' => 'Belum Absen Masuk',
                'body'  => ($d['count'] ?? 0) . ' karyawan belum absen masuk pada '
                           . ($d['date'] ?? '-') . '.',
            ],

            Notification::MISSING_CHECKOUT => fn ($d) => [
                'title' => 'Belum Absen Pulang',
                'body'  => ($d['count'] ?? 0) . ' karyawan belum absen pulang pada '
                           . ($d['date'] ?? '-') . '.',
            ],

            Notification::OUT_OF_RADIUS => fn ($d) => [
                'title' => 'Absen di Luar Radius Kantor',
                'body'  => ($d['user_name'] ?? 'Seorang karyawan')
                           . ' melakukan absensi di luar radius kantor.',
            ],

            Notification::LEAVE_SUBMITTED => fn ($d) => [
                'title' => 'Pengajuan Cuti Baru',
                'body'  => ($d['user_name'] ?? 'Karyawan') . ' mengajukan '
                           . ($d['leave_type'] ?? 'cuti') . ' '
                           . ($d['total_days'] ?? '?') . ' hari ('
                           . ($d['start_date'] ?? '?') . ' s/d ' . ($d['end_date'] ?? '?')
                           . '). Menunggu persetujuan Anda.',
            ],

            Notification::LEAVE_APPROVED => fn ($d) => [
                'title' => 'Pengajuan Cuti Disetujui',
                'body'  => 'Pengajuan ' . ($d['leave_type'] ?? 'cuti') . ' Anda ('
                           . ($d['start_date'] ?? '?') . ' s/d ' . ($d['end_date'] ?? '?')
                           . ') telah disetujui.',
            ],

            Notification::LEAVE_REJECTED => fn ($d) => [
                'title' => 'Pengajuan Cuti Ditolak',
                'body'  => 'Pengajuan ' . ($d['leave_type'] ?? 'cuti') . ' Anda ditolak.'
                           . (isset($d['reason']) ? ' Alasan: ' . $d['reason'] : ''),
            ],

            Notification::LEAVE_BALANCE_LOW => fn ($d) => [
                'title' => 'Saldo Cuti Menipis',
                'body'  => 'Sisa saldo ' . ($d['leave_type'] ?? 'cuti') . ' Anda tinggal '
                           . ($d['remaining'] ?? 0) . ' hari.',
            ],

            Notification::SALARY_PAID => fn ($d) => [
                'title' => 'Gaji Telah Dibayarkan',
                'body'  => 'Gaji periode ' . ($d['period'] ?? '-') . ' sebesar '
                           . money((float) ($d['amount'] ?? 0))
                           . ' telah dibayarkan.',
            ],

            Notification::LOAN_CREATED => fn ($d) => [
                'title' => 'Kasbon Dibuat',
                'body'  => 'Kasbon sebesar '
                           . money((float) ($d['amount'] ?? 0))
                           . ' telah dicatat atas nama Anda.',
            ],

            Notification::APPROVAL_PENDING => fn ($d) => [
                'title' => 'Persetujuan Menunggu Tindakan',
                'body'  => 'Ada pengajuan ' . ($d['module'] ?? '') . ' dari '
                           . ($d['requester'] ?? 'karyawan') . ' yang menunggu persetujuan Anda.',
            ],

            Notification::APPROVAL_DIGEST => fn ($d) => [
                'title' => 'Ringkasan Persetujuan Tertunda',
                'body'  => 'Anda memiliki ' . ($d['count'] ?? 0)
                           . ' pengajuan yang menunggu persetujuan.',
            ],

            Notification::DOCUMENT_EXPIRING => fn ($d) => [
                'title' => 'Dokumen Akan Kedaluwarsa',
                'body'  => 'Dokumen ' . ($d['document_type'] ?? '') . ' milik '
                           . ($d['user_name'] ?? '-') . ' akan kedaluwarsa pada '
                           . ($d['expiry_date'] ?? '-') . '.',
            ],
        ];
    }
}
