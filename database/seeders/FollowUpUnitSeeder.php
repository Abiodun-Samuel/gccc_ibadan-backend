<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FollowUpUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $unit = Unit::firstOrCreate(['name' => 'Follow-up'], ['description' => 'Members assigned to follow up visitors', 'slug' => 'follow-up']);
        $media = Unit::firstOrCreate(['name' => 'Media'], ['description' => 'Media team', 'slug' => 'media']);

        $members = User::whereIn('email', [
            "olasunkanmi.gbadegesin@gmail.com",
            "agoroadeyemi65@gmail.com",
            "akinolatoluwalashe48@gmail.com",
            "Xtarife3@gmail.com",
            "oluwabukolaolanase@gmail.com",
            "isholaoyindamola93@gmail.com",
        ])->get();

        foreach ($members as $m) {
            $m->units()->syncWithoutDetaching([$unit->id => ['is_leader' => false]]);
        }
        $member = User::where('email', 'abiodunsamyemi@gmail.com')->first();
        $member->units()->syncWithoutDetaching([$unit->id => ['is_leader' => true]]);

        $member2 = User::where('email', "olatunjiinioluwa2308@gamil.com")->first();
        $member2->units()->syncWithoutDetaching([$unit->id => ['is_asst_leader' => true]]);
    }
}
