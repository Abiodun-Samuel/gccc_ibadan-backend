<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Services\MailService;
use DB;
use Illuminate\Http\Request;


class TestController extends Controller
{
    protected MailService $mailService;

    public function __construct(MailService $mailService)
    {
        $this->mailService = $mailService;
    }

    public function index2(Request $request){
        $user=$request->user();
        if ($user->hasRole(RoleEnum::FIRST_TIMER->value) && !$user->hasRole(RoleEnum::MEMBER->value)) {
            return;
        }
        $sundayCount = $user->attendances()
            ->present()
            ->whereHas('service', fn($q) =>
                $q->where('day_of_week', 'saturday')
            )
            ->count();

        if ($sundayCount >= 4) {
            try {
                DB::beginTransaction();
                $user->assignRole(RoleEnum::ADMIN->value);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return $e->getMessage();
            }
        }

            return $sundayCount;

    }

    // public function index()
    // {
    //     $data = [
    //         "mail_template_key" => "2d6f.63afa6f2690c5939.k1.78191f80-9993-11f0-98ed-525400d4bb1c.1997dc86978",
    //         "from" => [
    //             "address" => "admin@gcccibadan.org",
    //             "name" => "Daphne from GCCC IBADAN"
    //         ],
    //         "to" => [
    //             [
    //                 "email_address" => [
    //                     "address" => "abiodunsamyemi@gmail.com",
    //                     "name" => "Daphne"
    //                 ]
    //             ]
    //         ],
    //         "merge_info" => [
    //             "name" => "Daphne",
    //         ]
    //     ];

    //     try {
    //         $result = $this->mailService->sendEmail($data);

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Email sent successfully',
    //             // 'data' => $result
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to send email',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
}
