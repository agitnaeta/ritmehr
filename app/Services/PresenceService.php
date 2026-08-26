<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Presence;
use App\Models\ScheduleDayOff;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PresenceService
{
    const TIME_ZONE = 'Asia/Jakarta';
    public function record(User $user){
        $time = Carbon::now(self::TIME_ZONE);
        return $this->writeRecord($user,$time);
    }

    public function updateCoordinate(Presence $presence, $lat, $lng){
        $branch = $this->branchFor($presence);

        $presence->lat = $lat;
        $presence->lng = $lng;
        $presence->branch_id = $branch?->id;
        $presence->outside = $this->inCoordinate($lat, $lng, $branch);
        $presence->saveQuietly();
        Log::channel('daily_log')->info($presence->toJson());
        return $presence;
    }
    public function recalCulateCoordinate(Presence $presence){
        if(isset($presence->lat) && isset($presence->lng)){
            $branch = $this->branchFor($presence);
            $presence->branch_id = $branch?->id;
            $presence->outside = $this->inCoordinate($presence->lat, $presence->lng, $branch);
            $presence->saveQuietly();
            Log::channel('daily_log')->info($presence->toJson());
            return $presence;
        }
    }

    /**
     * Which branch a presence should be judged against.
     *
     * The branch already recorded on the row wins, so recalculating history
     * cannot silently re-attribute it after someone transfers office.
     */
    public function branchFor(Presence $presence): ?Branch
    {
        if ($presence->branch_id) {
            return Branch::find($presence->branch_id);
        }

        $user = $presence->relationLoaded('user')
            ? $presence->user
            : User::find($presence->user_id);

        return $user?->branch;
    }

    public function writeRecord(User $user, Carbon $time): Presence
    {
        $presence = Presence::where('user_id',$user->id)
                            ->whereDate('created_at',Carbon::today(self::TIME_ZONE))
                            ->first();
        if(!$presence){
            return $this->login($user, $time);
        }else{
            return $this->logout($presence,$time);
        }
    }

    public function checkIfOffDay(User $user,Carbon $now): bool
    {
        $dayOffs = ScheduleDayOff::with('days')
            ->where('schedule_id',$user->schedule->id)
            ->get();

        $days =collect();
        $dayOffs->map(function ($dayOff) use ($days,$now){
            $now->locale('id_ID');
            $day = $now->isoFormat('dddd');
            if(Str::lower($day) == Str::lower($dayOff->days->name)){
                $days->push($dayOff);
            }
        });
        return $days->count() > 0;
    }

    public function useOffDays(User $user)
    {
        $dayOffs = ScheduleDayOff::with('days')
            ->where('schedule_id',$user->schedule->id)
            ->get();

        $days =collect();
        $dayOffs->map(function ($dayOff) use ($days){
            $days->push(Str::lower($dayOff->days->name));
        });
        return $days;
    }

    public function login(User $user, Carbon $now): Presence
    {
       $presence = new Presence();
       $presence->user_id = $user->id;
       $presence->in = $now->format('Y-m-d H:i:s');
       $presence->save();
       return $presence;
    }
    public function logout(Presence $presence, Carbon $now): Presence
    {
        $presence->out = $now->format('Y-m-d H:i:s');
        $presence->save();
        return $presence;
    }

    public function calculateLate(Presence $presence){
        $user = User::with('schedule')
                    ->where('id',$presence->user_id)
                    ->first();
        if(!isset($user->schedule)){
            return false;
        }

        if($presence->in !== null){
            // catatan waktu masuk
            $timeIn = Carbon::createFromFormat(
                "H:i:s",
                Str::substr($user->schedule->in,0,8),
                self::TIME_ZONE
            );

            // Absen masuk
            $presenceIn = Carbon::parse($presence->in,self::TIME_ZONE);

            // tanggal & jadwal masuk
            $scheduleIn = $presenceIn->copy()->setTimeFrom($timeIn);

            if($scheduleIn->lessThan($presenceIn)){
                $presence->late_minute = $scheduleIn->diffInMinutes($presenceIn);
                $presence->is_late = $presence->late_minute == 0 ? false : true;
                $presence->saveQuietly();
            }else{
                $presence->is_late = false;
                $presence->late_minute = 0;
                $presence->saveQuietly();
            }
        }
    }

    public function calculateOvertime(Presence $presence){
        $user = User::with('schedule')
                    ->where('id',$presence->user_id)
                    ->first();

        if(!isset($user->schedule)){
            return false;
        }
        $presenceIn = Carbon::parse($presence->in,self::TIME_ZONE);
        if ($this->checkIfOffDay($user, $presenceIn)){
            $presence->is_overtime = true;
            return $presence->saveQuietly();
        }

        if($presence->out !== null){
            // jadwal waktu keluar
            $overtimeIn = Carbon::createFromFormat("H:i:s",
                Str::substr($user->schedule->over_in,0,8),
                self::TIME_ZONE);

            // Absen keluar
            $presenceOut = Carbon::parse($presence->out,self::TIME_ZONE);

            // tanggal & jadwal masuk
            $scheduleOverIn = $presenceOut->copy()->setTimeFrom($overtimeIn);

            if($presenceOut->greaterThan($scheduleOverIn)){
                $presence->is_overtime = true;
                return $presence->saveQuietly();
            }else{
                $presence->is_overtime = false;
                return $presence->saveQuietly();
            }
        }
    }

    /**
     * Is this coordinate OUTSIDE the allowed geofence?
     *
     * Returns true for "outside", matching the `presences.outside` column.
     *
     * The reference point is the branch when one is given (M7), otherwise the
     * global office coordinates. The radius comes from the branch too, rather
     * than the 100 m that used to be hard-coded here.
     */
    function inCoordinate($latitudeFrom, $longitudeFrom, ?Branch $branch = null) {

        if ($branch && $branch->hasGeofence()) {
            $latitudeTo = (float) $branch->lat;
            $longitudeTo = (float) $branch->lng;
            $radius = max(1, (int) $branch->radius_meters);
        } else {
            $latitudeTo = (float) setting('office_lat', config('app.office_lat'));
            $longitudeTo = (float) setting('office_lng', config('app.office_lng'));
            $radius = max(1, (int) setting('office_radius', config('app.office_radius', 100)));
        }

        // With no reference point configured there is nothing to be outside
        // of — treat the scan as on-site rather than flagging everyone.
        if (! $latitudeTo && ! $longitudeTo) {
            return false;
        }

        $earthRadius = 6371000;
        // Convert degrees to radians
        $latFrom = deg2rad((float) $latitudeFrom);
        $lonFrom = deg2rad((float) $longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        // Calculate differences
        $latDiff = $latTo - $latFrom;
        $lonDiff = $lonTo - $lonFrom;

        // Haversine formula
        $a = sin($latDiff / 2) * sin($latDiff / 2) + cos($latFrom) * cos($latTo) * sin($lonDiff / 2) * sin($lonDiff / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        // Distance in meters
        $distance = $earthRadius * $c;

        Log::channel("daily_log")->info([
            'lat'=>$latitudeFrom,
            'lng'=>$longitudeFrom,
            'lat2'=>$latitudeTo,
            'lng2'=>$longitudeTo,
            'branch'=>$branch?->name,
            'radius'=>$radius,
            'distance'=>$distance,
        ]);

        return $distance > $radius;
    }
    public function calculateExtraTime(Presence $presence){
        $user = User::with('schedule')
            ->where('id',$presence->user_id)
            ->first();

        // Same guard as calculateLate()/calculateOvertime() — without a
        // schedule there is no "end of day" to measure extra time against.
        if(!isset($user->schedule)){
            return false;
        }

        // Nothing to measure until the employee has clocked out.
        if($presence->out === null){
            return false;
        }

        $logout = Carbon::parse($presence->out,self::TIME_ZONE);
        $timeScheduleOff = Carbon::parse($user->schedule->out,self::TIME_ZONE);
        $timeOverTimeStart = Carbon::parse($user->schedule->over_in,self::TIME_ZONE);
        $scheduleOut = $logout->copy()->setTimeFrom($timeScheduleOff);
        $overtimeStart = $logout->copy()->setTimeFrom($timeOverTimeStart);
        if($logout->gt($scheduleOut) && $logout->lessThan($overtimeStart)){
            $maxDiff = $scheduleOut->diffInMinutes($overtimeStart);
            $diff = $logout->diffInMinutes($scheduleOut);
            $presence->extra_time = $diff > $maxDiff ? $maxDiff : $diff;
            $presence->saveQuietly();
        }
    }
}
