<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class UsherAttendanceSeeder extends Seeder
{
    public function run(): void
    {

        $jsonData = [
            [
                "Timestamp" => "3/10/2024 11:03:22",
                "Male" => 14,
                "Female" => 18,
                "Children" => 3,
                "Date" => "3/1/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 35,
                "Month of service?" => "March",
                "Year" => 2024
            ],
            [
                "Timestamp" => "3/10/2024 11:03:06",
                "Male" => 24,
                "Female" => 32,
                "Children" => 4,
                "Date" => "3/3/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 60,
                "Month of service?" => "March",
                "Year" => 2024
            ],
            [
                "Timestamp" => "3/12/2024 9:01:02",
                "Male" => 19,
                "Female" => 23,
                "Children" => 7,
                "Date" => "3/5/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 49,
                "Month of service?" => "March",
                "Year" => 2024
            ],
            [
                "Timestamp" => "3/12/2024 9:17:03",
                "Male" => 10,
                "Female" => 16,
                "Children" => 8,
                "Date" => "3/8/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 34,
                "Month of service?" => "March",
                "Year" => 2024
            ],
            [
                "Timestamp" => "3/12/2024 9:18:04",
                "Male" => 24,
                "Female" => 27,
                "Children" => 9,
                "Date" => "3/10/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 60,
                "Month of service?" => "March",
                "Year" => 2024
            ],
            [
                "Timestamp" => "3/12/2024 11:36:07",
                "Male" => 16,
                "Female" => 24,
                "Children" => 8,
                "Date" => "3/12/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 48,
                "Month of service?" => "March",
                "Year" => 2024
            ],
            [
                "Timestamp" => "3/16/2024 11:09:08",
                "Male" => 1,
                "Female" => 20,
                "Children" => 3,
                "Date" => "3/1/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 24,
                "Month of service?" => "March",
                "Year" => 2024
            ],
            [
                "Timestamp" => "3/17/2024 14:04:03",
                "Male" => 19,
                "Female" => 31,
                "Children" => 9,
                "Date" => "3/17/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 59,
                "Month of service?" => "March",
                "Year" => 2024
            ],
            [
                "Timestamp" => "3/24/2024 1:16:31",
                "Male" => 16,
                "Female" => 27,
                "Children" => 7,
                "Date" => "3/19/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 50,
                "Month of service?" => "March",
                "Year" => 2024
            ],
            [
                "Timestamp" => "3/24/2024 1:44:29",
                "Male" => 1,
                "Female" => 22,
                "Children" => 8,
                "Date" => "3/22/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 31,
                "Month of service?" => "March",
                "Year" => 2024
            ],
            [
                "Timestamp" => "3/24/2024 3:29:31",
                "Male" => 2,
                "Female" => 32,
                "Children" => 9,
                "Date" => "3/24/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 43,
                "Month of service?" => "March",
                "Year" => 2024
            ],
            [
                "Timestamp" => "4/8/2024 14:26:48",
                "Male" => 14,
                "Female" => 21,
                "Children" => 10,
                "Date" => "3/26/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 45,
                "Month of service?" => "March",
                "Year" => 2024
            ],
            [
                "Timestamp" => "4/8/2024 14:28:00",
                "Male" => 24,
                "Female" => 38,
                "Children" => 10,
                "Date" => "3/29/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 72,
                "Month of service?" => "March",
                "Year" => 2024
            ],
            [
                "Timestamp" => "4/8/2024 14:28:31",
                "Male" => 24,
                "Female" => 38,
                "Children" => 9,
                "Date" => "3/30/2024",
                "Service Day" => "Saturday",
                "Total Attendance" => 71,
                "Month of service?" => "March",
                "Year" => 2024
            ],
            [
                "Timestamp" => "4/8/2024 14:29:04",
                "Male" => 26,
                "Female" => 36,
                "Children" => 10,
                "Date" => "3/31/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 72,
                "Month of service?" => "March",
                "Year" => 2024
            ],
            [
                "Timestamp" => "4/8/2024 14:03:03",
                "Male" => 8,
                "Female" => 13,
                "Children" => 3,
                "Date" => "4/2/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 24,
                "Month of service?" => "April",
                "Year" => 2024
            ],
            [
                "Timestamp" => "4/8/2024 14:36:26",
                "Male" => 26,
                "Female" => 33,
                "Children" => 10,
                "Date" => "4/7/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 69,
                "Month of service?" => "April",
                "Year" => 2024
            ],
            [
                "Timestamp" => "4/14/2024 1:13:32",
                "Male" => 18,
                "Female" => 30,
                "Children" => "",
                "Date" => "4/9/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 48,
                "Month of service?" => "April",
                "Year" => 2024
            ],
            [
                "Timestamp" => "4/14/2024 1:14:19",
                "Male" => 1,
                "Female" => 18,
                "Children" => 6,
                "Date" => "4/12/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 25,
                "Month of service?" => "April",
                "Year" => 2024
            ],
            [
                "Timestamp" => "4/14/2024 3:44:37",
                "Male" => 23,
                "Female" => 34,
                "Children" => 8,
                "Date" => "4/14/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 65,
                "Month of service?" => "April",
                "Year" => 2024
            ],
            [
                "Timestamp" => "4/16/2024 6:37:01",
                "Male" => 30,
                "Female" => 1,
                "Children" => 6,
                "Date" => "4/16/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 37,
                "Month of service?" => "April",
                "Year" => 2024
            ],
            [
                "Timestamp" => "4/16/2024 6:39:03",
                "Male" => 0,
                "Female" => 120,
                "Children" => 30,
                "Date" => "4/16/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 150,
                "Month of service?" => "April",
                "Year" => 2024
            ],
            [
                "Timestamp" => "4/16/2024 6:44:18",
                "Male" => 120,
                "Female" => 300,
                "Children" => 67,
                "Date" => "4/16/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 487,
                "Month of service?" => "April",
                "Year" => 2024
            ],
            [
                "Timestamp" => "4/16/2024 16:06:17",
                "Male" => 13,
                "Female" => 22,
                "Children" => 8,
                "Date" => "4/16/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 43,
                "Month of service?" => "April",
                "Year" => 2024
            ],
            [
                "Timestamp" => "4/19/2024 11:07:03",
                "Male" => 11,
                "Female" => 17,
                "Children" => 6,
                "Date" => "4/19/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 34,
                "Month of service?" => "April",
                "Year" => 2024
            ],
            [
                "Timestamp" => "4/21/2024 3:37:38",
                "Male" => 2,
                "Female" => 36,
                "Children" => 9,
                "Date" => "4/21/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 47,
                "Month of service?" => "April",
                "Year" => 2024
            ],
            [
                "Timestamp" => "4/26/2024 2:29:34",
                "Male" => 11,
                "Female" => 22,
                "Children" => 7,
                "Date" => "4/23/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 40,
                "Month of service?" => "April",
                "Year" => 2024
            ],
            [
                "Timestamp" => "4/27/2024 :39:01",
                "Male" => 14,
                "Female" => 23,
                "Children" => 6,
                "Date" => "4/26/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 43,
                "Month of service?" => "April",
                "Year" => 2024
            ],
            [
                "Timestamp" => "4/28/2024 6:21:11",
                "Male" => 31,
                "Female" => 39,
                "Children" => 8,
                "Date" => "4/28/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 78,
                "Month of service?" => "April",
                "Year" => 2024
            ],
            [
                "Timestamp" => "/2/2024 3:37:10",
                "Male" => 12,
                "Female" => 23,
                "Children" => 8,
                "Date" => "4/30/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 43,
                "Month of service?" => "April",
                "Year" => 2024
            ],
            [
                "Timestamp" => "//2024 9:2:34",
                "Male" => 22,
                "Female" => 3,
                "Children" => 8,
                "Date" => "5/2/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 33,
                "Month of service?" => "May",
                "Year" => 2024
            ],
            [
                "Timestamp" => "/10/2024 10:22:7",
                "Male" => 19,
                "Female" => 22,
                "Children" => 3,
                "Date" => "5/5/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 44,
                "Month of service?" => "May",
                "Year" => 2024
            ],
            [
                "Timestamp" => "/12/2024 1:18:02",
                "Male" => 2,
                "Female" => 32,
                "Children" => 9,
                "Date" => "5/12/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 43,
                "Month of service?" => "May",
                "Year" => 2024
            ],
            [
                "Timestamp" => "/19/2024 2:46:29",
                "Male" => 21,
                "Female" => 26,
                "Children" => 6,
                "Date" => "5/19/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 53,
                "Month of service?" => "May",
                "Year" => 2024
            ],
            [
                "Timestamp" => "6/6/2024 1:31:33",
                "Male" => 4,
                "Female" => 60,
                "Children" => 9,
                "Date" => "5/23/2024",
                "Service Day" => "Thursday",
                "Total Attendance" => 73,
                "Month of service?" => "May",
                "Year" => 2024
            ],
            [
                "Timestamp" => "6/6/2024 4:32:08",
                "Male" => 42,
                "Female" => 46,
                "Children" => 10,
                "Date" => "5/24/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 98,
                "Month of service?" => "May",
                "Year" => 2024
            ],
            [
                "Timestamp" => "6/6/2024 4:03:16",
                "Male" => 47,
                "Female" => 3,
                "Children" => 9,
                "Date" => "5/25/2024",
                "Service Day" => "Saturday",
                "Total Attendance" => 59,
                "Month of service?" => "May",
                "Year" => 2024
            ],
            [
                "Timestamp" => "6/6/2024 4:03:03",
                "Male" => 38,
                "Female" => 4,
                "Children" => 10,
                "Date" => "5/26/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 52,
                "Month of service?" => "May",
                "Year" => 2024
            ],
            [
                "Timestamp" => "6/6/2024 4:36:33",
                "Male" => 0,
                "Female" => 7,
                "Children" => 9,
                "Date" => "5/31/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 16,
                "Month of service?" => "May",
                "Year" => 2024
            ],
            [
                "Timestamp" => "6/4/2024 11:23:39",
                "Male" => 27,
                "Female" => 31,
                "Children" => 7,
                "Date" => "6/2/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 65,
                "Month of service?" => "June",
                "Year" => 2024
            ],
            [
                "Timestamp" => "6/4/2024 11:26:01",
                "Male" => 20,
                "Female" => 23,
                "Children" => 7,
                "Date" => "6/4/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 50,
                "Month of service?" => "June",
                "Year" => 2024
            ],
            [
                "Timestamp" => "6/7/2024 11:09:38",
                "Male" => 13,
                "Female" => 13,
                "Children" => 3,
                "Date" => "6/7/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 29,
                "Month of service?" => "June",
                "Year" => 2024
            ],
            [
                "Timestamp" => "6/9/2024 3:21:27",
                "Male" => 29,
                "Female" => 41,
                "Children" => 9,
                "Date" => "6/9/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 79,
                "Month of service?" => "June",
                "Year" => 2024
            ],
            [
                "Timestamp" => "6/11/2024 11:29:38",
                "Male" => 12,
                "Female" => 18,
                "Children" => "",
                "Date" => "6/11/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 30,
                "Month of service?" => "June",
                "Year" => 2024
            ],
            [
                "Timestamp" => "6/14/2024 11:24:09",
                "Male" => 12,
                "Female" => 18,
                "Children" => 2,
                "Date" => "6/14/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 32,
                "Month of service?" => "June",
                "Year" => 2024
            ],
            [
                "Timestamp" => "6/16/2024 4:16:29",
                "Male" => 20,
                "Female" => 3,
                "Children" => 8,
                "Date" => "6/16/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 31,
                "Month of service?" => "June",
                "Year" => 2024
            ],
            [
                "Timestamp" => "6/27/2024 4:17:23",
                "Male" => 24,
                "Female" => 32,
                "Children" => 8,
                "Date" => "6/23/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 64,
                "Month of service?" => "June",
                "Year" => 2024
            ],
            [
                "Timestamp" => "6/30/2024 4:07:30",
                "Male" => 24,
                "Female" => 33,
                "Children" => 9,
                "Date" => "6/30/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 66,
                "Month of service?" => "June",
                "Year" => 2024
            ],
            [
                "Timestamp" => "7/3/2024 22:33:20",
                "Male" => 16,
                "Female" => 20,
                "Children" => 7,
                "Date" => "7/2/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 43,
                "Month of service?" => "June",
                "Year" => 2024
            ],
            [
                "Timestamp" => "7/3/2024 22:03:34",
                "Male" => 1,
                "Female" => 23,
                "Children" => 3,
                "Date" => "6/2/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 27,
                "Month of service?" => "June",
                "Year" => 2024
            ],
            [
                "Timestamp" => "7/3/2024 23:46:03",
                "Male" => 24,
                "Female" => 33,
                "Children" => 9,
                "Date" => "6/30/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 66,
                "Month of service?" => "June",
                "Year" => 2024
            ],
            [
                "Timestamp" => "7/7/2024 4:14:01",
                "Male" => 21,
                "Female" => 34,
                "Children" => 7,
                "Date" => "7/7/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 62,
                "Month of service?" => "July",
                "Year" => 2024
            ],
            [
                "Timestamp" => "7/9/2024 12:06:23",
                "Male" => 14,
                "Female" => 21,
                "Children" => 6,
                "Date" => "7/9/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 41,
                "Month of service?" => "July",
                "Year" => 2024
            ],
            [
                "Timestamp" => "7/14/2024 2:20:01",
                "Male" => 23,
                "Female" => 36,
                "Children" => 8,
                "Date" => "7/14/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 67,
                "Month of service?" => "July",
                "Year" => 2024
            ],
            [
                "Timestamp" => "7/20/2024 7:07:48",
                "Male" => 17,
                "Female" => 18,
                "Children" => 3,
                "Date" => "7/16/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 38,
                "Month of service?" => "July",
                "Year" => 2024
            ],
            [
                "Timestamp" => "7/21/2024 3:40:04",
                "Male" => 24,
                "Female" => 33,
                "Children" => 10,
                "Date" => "7/21/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 67,
                "Month of service?" => "July",
                "Year" => 2024
            ],
            [
                "Timestamp" => "7/26/2024 14:21:10",
                "Male" => 11,
                "Female" => 18,
                "Children" => 7,
                "Date" => "7/26/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 36,
                "Month of service?" => "July",
                "Year" => 2024
            ],
            [
                "Timestamp" => "7/28/2024 3:29:30",
                "Male" => 26,
                "Female" => 33,
                "Children" => 8,
                "Date" => "7/28/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 67,
                "Month of service?" => "July",
                "Year" => 2024
            ],
            [
                "Timestamp" => "7/30/2024 12:37:18",
                "Male" => 17,
                "Female" => 19,
                "Children" => 4,
                "Date" => "7/30/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 40,
                "Month of service?" => "July",
                "Year" => 2024
            ],
            [
                "Timestamp" => "8/4/2024 13:30:08",
                "Male" => 2,
                "Female" => 4,
                "Children" => 10,
                "Date" => "8/4/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 16,
                "Month of service?" => "August",
                "Year" => 2024
            ],
            [
                "Timestamp" => "8//2024 16:19:44",
                "Male" => "Ayomikun taiwo",
                "Female" => "Male",
                "Children" => "Not applicable",
                "Date" => "8/6/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 0,
                "Month of service?" => "August",
                "Year" => 2024
            ],
            [
                "Timestamp" => "8/6/2024 12:04:19",
                "Male" => 18,
                "Female" => 24,
                "Children" => 8,
                "Date" => "8/6/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 50,
                "Month of service?" => "August",
                "Year" => 2024
            ],
            [
                "Timestamp" => "8/9/2024 11:06:12",
                "Male" => 13,
                "Female" => 17,
                "Children" => 2,
                "Date" => "8/9/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 32,
                "Month of service?" => "August",
                "Year" => 2024
            ],
            [
                "Timestamp" => "8/11/2024 3::48",
                "Male" => 2,
                "Female" => 33,
                "Children" => 11,
                "Date" => "8/11/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 46,
                "Month of service?" => "August",
                "Year" => 2024
            ],
            [
                "Timestamp" => "8/13/2024 11:42:38",
                "Male" => 14,
                "Female" => 19,
                "Children" => 4,
                "Date" => "8/13/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 37,
                "Month of service?" => "August",
                "Year" => 2024
            ],
            [
                "Timestamp" => "8/16/2024 11:20:00",
                "Male" => 4,
                "Female" => 6,
                "Children" => 1,
                "Date" => "8/16/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 11,
                "Month of service?" => "August",
                "Year" => 2024
            ],
            [
                "Timestamp" => "8/18/2024 6:23:19",
                "Male" => 24,
                "Female" => 34,
                "Children" => 13,
                "Date" => "8/18/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 71,
                "Month of service?" => "August",
                "Year" => 2024
            ],
            [
                "Timestamp" => "8/26/2024 14:28:42",
                "Male" => 23,
                "Female" => 38,
                "Children" => 8,
                "Date" => "8/2/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 69,
                "Month of service?" => "August",
                "Year" => 2024
            ],
            [
                "Timestamp" => "8/28/2024 2:14:49",
                "Male" => 42,
                "Female" => 43,
                "Children" => 9,
                "Date" => "8/27/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 94,
                "Month of service?" => "August",
                "Year" => 2024
            ],
            [
                "Timestamp" => "8/28/2024 14:20:30",
                "Male" => 34,
                "Female" => 39,
                "Children" => 9,
                "Date" => "8/28/2024",
                "Service Day" => "Wednesday",
                "Total Attendance" => 82,
                "Month of service?" => "August",
                "Year" => 2024
            ],
            [
                "Timestamp" => "8/29/2024 13:44:30",
                "Male" => 37,
                "Female" => 42,
                "Children" => 9,
                "Date" => "8/29/2024",
                "Service Day" => "Thursday",
                "Total Attendance" => 88,
                "Month of service?" => "August",
                "Year" => 2024
            ],
            [
                "Timestamp" => "9/1/2024 2:03:06",
                "Male" => 26,
                "Female" => 34,
                "Children" => 9,
                "Date" => "9/1/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 69,
                "Month of service?" => "September",
                "Year" => 2024
            ],
            [
                "Timestamp" => "9/3/2024 13:01:03",
                "Male" => 1,
                "Female" => 24,
                "Children" => 3,
                "Date" => "9/3/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 28,
                "Month of service?" => "September",
                "Year" => 2024
            ],
            [
                "Timestamp" => "9/6/2024 11:08:38",
                "Male" => 1,
                "Female" => 17,
                "Children" => 3,
                "Date" => "9/6/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 21,
                "Month of service?" => "September",
                "Year" => 2024
            ],
            [
                "Timestamp" => "9/10/2024 4:02:49",
                "Male" => 30,
                "Female" => 39,
                "Children" => 9,
                "Date" => "9/8/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 78,
                "Month of service?" => "September",
                "Year" => 2024
            ],
            [
                "Timestamp" => "9/10/2024 14:27:04",
                "Male" => 20,
                "Female" => 22,
                "Children" => 7,
                "Date" => "9/10/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 49,
                "Month of service?" => "September",
                "Year" => 2024
            ],
            [
                "Timestamp" => "9/14/2024 0:08:00",
                "Male" => 9,
                "Female" => 21,
                "Children" => 3,
                "Date" => "9/14/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 33,
                "Month of service?" => "September",
                "Year" => 2024
            ],
            [
                "Timestamp" => "9/1/2024 2:01:00",
                "Male" => 26,
                "Female" => 3,
                "Children" => 10,
                "Date" => "9/1/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 71,
                "Month of service?" => "September",
                "Year" => 2024
            ],
            [
                "Timestamp" => "9/17/2024 13:30:43",
                "Male" => 16,
                "Female" => 21,
                "Children" => 3,
                "Date" => "9/17/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 40,
                "Month of service?" => "September",
                "Year" => 2024
            ],
            [
                "Timestamp" => "9/20/2024 13:39:01",
                "Male" => 13,
                "Female" => 16,
                "Children" => 7,
                "Date" => "9/20/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 36,
                "Month of service?" => "September",
                "Year" => 2024
            ],
            [
                "Timestamp" => "9/21/2024 6:01:33",
                "Male" => 24,
                "Female" => 31,
                "Children" => 3,
                "Date" => "9/21/2024",
                "Service Day" => "BCLF",
                "Total Attendance" => 8,
                "Month of service?" => "September",
                "Year" => 2024
            ],
            [
                "Timestamp" => "9/23/2024 23:06:48",
                "Male" => 26,
                "Female" => 38,
                "Children" => 10,
                "Date" => "9/22/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 74,
                "Month of service?" => "September",
                "Year" => 2024
            ],
            [
                "Timestamp" => "9/24/2024 12:00:21",
                "Male" => 18,
                "Female" => 26,
                "Children" => 8,
                "Date" => "9/24/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 2,
                "Month of service?" => "September",
                "Year" => 2024
            ],
            [
                "Timestamp" => "9/29/2024 14:29:41",
                "Male" => 27,
                "Female" => 40,
                "Children" => 7,
                "Date" => "9/29/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 74,
                "Month of service?" => "September",
                "Year" => 2024
            ],
            [
                "Timestamp" => "10/2/2024 11:07:19",
                "Male" => 17,
                "Female" => 34,
                "Children" => 8,
                "Date" => "10/1/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 9,
                "Month of service?" => "October",
                "Year" => 2024
            ],
            [
                "Timestamp" => "10/6/2024 10:00:29",
                "Male" => 27,
                "Female" => 43,
                "Children" => 11,
                "Date" => "10/6/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 81,
                "Month of service?" => "October",
                "Year" => 2024
            ],
            [
                "Timestamp" => "10/8/2024 12::26",
                "Male" => 16,
                "Female" => 24,
                "Children" => 3,
                "Date" => "10/8/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 43,
                "Month of service?" => "October",
                "Year" => 2024
            ],
            [
                "Timestamp" => "10/13/2024 4:47:49",
                "Male" => 1,
                "Female" => 24,
                "Children" => 3,
                "Date" => "10/13/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 42,
                "Month of service?" => "October",
                "Year" => 2024
            ],
            [
                "Timestamp" => "10/13/2024 4:49:33",
                "Male" => 22,
                "Female" => 37,
                "Children" => 9,
                "Date" => "10/13/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 68,
                "Month of service?" => "October",
                "Year" => 2024
            ],
            [
                "Timestamp" => "10/16/2024 1:11:17",
                "Male" => 14,
                "Female" => 23,
                "Children" => 7,
                "Date" => "10/1/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 44,
                "Month of service?" => "October",
                "Year" => 2024
            ],
            [
                "Timestamp" => "10/20/2024 22:07:12",
                "Male" => 10,
                "Female" => 14,
                "Children" => 3,
                "Date" => "10/18/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 27,
                "Month of service?" => "October",
                "Year" => 2024
            ],
            [
                "Timestamp" => "10/20/2024 22:08:24",
                "Male" => 26,
                "Female" => 33,
                "Children" => 9,
                "Date" => "10/20/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 68,
                "Month of service?" => "October",
                "Year" => 2024
            ],
            [
                "Timestamp" => "10/22/2024 14:29:09",
                "Male" => 20,
                "Female" => 23,
                "Children" => 3,
                "Date" => "10/22/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 46,
                "Month of service?" => "October",
                "Year" => 2024
            ],
            [
                "Timestamp" => "10/26/2024 2:03:12",
                "Male" => 12,
                "Female" => 20,
                "Children" => 7,
                "Date" => "10/2/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 39,
                "Month of service?" => "October",
                "Year" => 2024
            ],
            [
                "Timestamp" => "11/4/2024 9:37:04",
                "Male" => 26,
                "Female" => 43,
                "Children" => 8,
                "Date" => "10/27/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 77,
                "Month of service?" => "October",
                "Year" => 2024
            ],
            [
                "Timestamp" => "11/4/2024 9:38:43",
                "Male" => 17,
                "Female" => 31,
                "Children" => 3,
                "Date" => "10/29/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 1,
                "Month of service?" => "October",
                "Year" => 2024
            ],
            [
                "Timestamp" => "11/4/2024 9:39:24",
                "Male" => 13,
                "Female" => 2,
                "Children" => "",
                "Date" => "11/1/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 43,
                "Month of service?" => "November",
                "Year" => 2024
            ],
            [
                "Timestamp" => "11/4/2024 9:40:16",
                "Male" => 27,
                "Female" => 40,
                "Children" => 9,
                "Date" => "11/3/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 76,
                "Month of service?" => "November",
                "Year" => 2024
            ],
            [
                "Timestamp" => "11//2024 10::0",
                "Male" => 16,
                "Female" => 27,
                "Children" => 8,
                "Date" => "11/5/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 1,
                "Month of service?" => "November",
                "Year" => 2024
            ],
            [
                "Timestamp" => "11/10/2024 21:00:09",
                "Male" => 30,
                "Female" => 38,
                "Children" => 8,
                "Date" => "11/10/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 76,
                "Month of service?" => "November",
                "Year" => 2024
            ],
            [
                "Timestamp" => "11/12/2024 11:18:28",
                "Male" => 1,
                "Female" => 22,
                "Children" => 2,
                "Date" => "11/12/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 39,
                "Month of service?" => "November",
                "Year" => 2024
            ],
            [
                "Timestamp" => "11/1/2024 13:43:12",
                "Male" => 16,
                "Female" => 19,
                "Children" => 3,
                "Date" => "11/1/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 38,
                "Month of service?" => "November",
                "Year" => 2024
            ],
            [
                "Timestamp" => "11/18/2024 12:39:20",
                "Male" => 29,
                "Female" => 4,
                "Children" => 9,
                "Date" => "11/17/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 83,
                "Month of service?" => "November",
                "Year" => 2024
            ],
            [
                "Timestamp" => "11/19/2024 12:06:28",
                "Male" => 16,
                "Female" => 27,
                "Children" => 7,
                "Date" => "11/19/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 0,
                "Month of service?" => "November",
                "Year" => 2024
            ],
            [
                "Timestamp" => "11/22/2024 11:01:14",
                "Male" => 21,
                "Female" => 22,
                "Children" => 6,
                "Date" => "11/22/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 49,
                "Month of service?" => "November",
                "Year" => 2024
            ],
            [
                "Timestamp" => "11/24/2024 14:46:06",
                "Male" => 29,
                "Female" => 44,
                "Children" => 9,
                "Date" => "11/24/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 82,
                "Month of service?" => "November",
                "Year" => 2024
            ],
            [
                "Timestamp" => "11/26/2024 12:24:06",
                "Male" => 13,
                "Female" => 32,
                "Children" => 8,
                "Date" => "11/26/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 3,
                "Month of service?" => "November",
                "Year" => 2024
            ],
            [
                "Timestamp" => "12/4/2024 2:42:06",
                "Male" => 20,
                "Female" => 28,
                "Children" => 8,
                "Date" => "12/3/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 6,
                "Month of service?" => "December",
                "Year" => 2024
            ],
            [
                "Timestamp" => "12/8/2024 20:02:03",
                "Male" => 10,
                "Female" => 20,
                "Children" => 3,
                "Date" => "12/6/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 33,
                "Month of service?" => "December",
                "Year" => 2024
            ],
            [
                "Timestamp" => "12/8/2024 20:03:14",
                "Male" => 2,
                "Female" => 38,
                "Children" => 10,
                "Date" => "12/8/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 73,
                "Month of service?" => "December",
                "Year" => 2024
            ],
            [
                "Timestamp" => "12/11/2024 4:04:18",
                "Male" => 2,
                "Female" => 39,
                "Children" => 8,
                "Date" => "12/10/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 72,
                "Month of service?" => "December",
                "Year" => 2024
            ],
            [
                "Timestamp" => "12/13/2024 12:27:01",
                "Male" => 10,
                "Female" => 23,
                "Children" => 3,
                "Date" => "12/13/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 36,
                "Month of service?" => "December",
                "Year" => 2024
            ],
            [
                "Timestamp" => "12/1/2024 1:30:08",
                "Male" => 19,
                "Female" => 34,
                "Children" => 8,
                "Date" => "12/1/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 61,
                "Month of service?" => "December",
                "Year" => 2024
            ],
            [
                "Timestamp" => "12/27/2024 13:00:46",
                "Male" => 17,
                "Female" => 17,
                "Children" => 6,
                "Date" => "12/27/2024",
                "Service Day" => "Friday",
                "Total Attendance" => 40,
                "Month of service?" => "December",
                "Year" => 2024
            ],
            [
                "Timestamp" => "12/31/2024 2:41:01",
                "Male" => 18,
                "Female" => 26,
                "Children" => 9,
                "Date" => "12/29/2024",
                "Service Day" => "Sunday",
                "Total Attendance" => 3,
                "Month of service?" => "December",
                "Year" => 2024
            ],
            [
                "Timestamp" => "1/4/0202 12:20:27",
                "Male" => 23,
                "Female" => 30,
                "Children" => 8,
                "Date" => "12/31/2024",
                "Service Day" => "Tuesday",
                "Total Attendance" => 63,
                "Month of service?" => "December",
                "Year" => 2024
            ],
            [
                "Timestamp" => "1/4/0202 12:22:37",
                "Male" => 13,
                "Female" => 24,
                "Children" => 3,
                "Date" => "1/3/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 40,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1/4/0202 12:23:32",
                "Male" => 12,
                "Female" => 20,
                "Children" => 4,
                "Date" => "1/4/2025",
                "Service Day" => "Saturday",
                "Total Attendance" => 36,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1//202 2:32:6",
                "Male" => 23,
                "Female" => 32,
                "Children" => 8,
                "Date" => "1/5/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 63,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1/6/0202 11:42:22",
                "Male" => 12,
                "Female" => 24,
                "Children" => 6,
                "Date" => "1/6/2025",
                "Service Day" => "Monday",
                "Total Attendance" => 42,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1/7/0202 11:17:33",
                "Male" => 14,
                "Female" => 29,
                "Children" => 8,
                "Date" => "1/7/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 1,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1/8/0202 13:13:43",
                "Male" => 13,
                "Female" => 31,
                "Children" => 7,
                "Date" => "1/8/2025",
                "Service Day" => "Wednesday",
                "Total Attendance" => 1,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1/10/0202 9:10:07",
                "Male" => 12,
                "Female" => 31,
                "Children" => 7,
                "Date" => "1/9/2025",
                "Service Day" => "Thursday",
                "Total Attendance" => 50,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1/10/0202 10:23:27",
                "Male" => 11,
                "Female" => 31,
                "Children" => 8,
                "Date" => "1/10/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 50,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1/11/0202 11:38:13",
                "Male" => 9,
                "Female" => 26,
                "Children" => 6,
                "Date" => "1/11/2025",
                "Service Day" => "Saturday",
                "Total Attendance" => 41,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1/12/0202 3:04:07",
                "Male" => 22,
                "Female" => 42,
                "Children" => 8,
                "Date" => "1/12/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 72,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1/14/2025 0:18:52",
                "Male" => 18,
                "Female" => 30,
                "Children" => 6,
                "Date" => "1/13/2025",
                "Service Day" => "Monday",
                "Total Attendance" => 54,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1/14/2025 10:27:28",
                "Male" => 18,
                "Female" => 36,
                "Children" => 8,
                "Date" => "1/14/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 62,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1/15/2025 9:51:19",
                "Male" => 17,
                "Female" => 34,
                "Children" => 8,
                "Date" => "1/15/2025",
                "Service Day" => "Wednesday",
                "Total Attendance" => 59,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1/16/2025 10:24:56",
                "Male" => 20,
                "Female" => 31,
                "Children" => 8,
                "Date" => "1/16/2025",
                "Service Day" => "Thursday",
                "Total Attendance" => 59,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1/19/2025 1:28:49",
                "Male" => 15,
                "Female" => 34,
                "Children" => 8,
                "Date" => "1/17/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 57,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1/19/2025 1:30:17",
                "Male" => 26,
                "Female" => 41,
                "Children" => 8,
                "Date" => "1/19/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 75,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1/23/2025 9:51:30",
                "Male" => 20,
                "Female" => 31,
                "Children" => 6,
                "Date" => "1/22/2025",
                "Service Day" => "Wednesday",
                "Total Attendance" => 57,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1/24/2025 13:32:08",
                "Male" => 19,
                "Female" => 37,
                "Children" => 8,
                "Date" => "1/23/2025",
                "Service Day" => "Thursday",
                "Total Attendance" => 64,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1/24/2025 13:33:10",
                "Male" => 22,
                "Female" => 27,
                "Children" => 8,
                "Date" => "1/24/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 57,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1/27/2025 0:13:07",
                "Male" => 13,
                "Female" => 25,
                "Children" => 3,
                "Date" => "1/25/2025",
                "Service Day" => "Saturday",
                "Total Attendance" => 41,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1/27/2025 0:14:02",
                "Male" => 31,
                "Female" => 41,
                "Children" => 9,
                "Date" => "1/26/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 81,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1/29/2025 3:42:25",
                "Male" => 22,
                "Female" => 32,
                "Children" => 7,
                "Date" => "1/27/2025",
                "Service Day" => "Monday",
                "Total Attendance" => 61,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "1/29/2025 3:43:04",
                "Male" => 25,
                "Female" => 34,
                "Children" => 7,
                "Date" => "1/28/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 66,
                "Month of service?" => "January",
                "Year" => 2025
            ],
            [
                "Timestamp" => "2/2/2025 2:40:43",
                "Male" => 29,
                "Female" => 43,
                "Children" => 8,
                "Date" => "2/2/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 80,
                "Month of service?" => "February",
                "Year" => 2025
            ],
            [
                "Timestamp" => "4/11/2025 23:18:32",
                "Male" => 22,
                "Female" => 37,
                "Children" => 6,
                "Date" => "2/4/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 65,
                "Month of service?" => "February",
                "Year" => 2025
            ],
            [
                "Timestamp" => "2/16/2025 1:56:29",
                "Male" => 12,
                "Female" => 23,
                "Children" => 3,
                "Date" => "2/7/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 38,
                "Month of service?" => "February",
                "Year" => 2025
            ],
            [
                "Timestamp" => "",
                "Male" => 25,
                "Female" => 37,
                "Children" => 8,
                "Date" => "2/9/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 70,
                "Month of service?" => "February",
                "Year" => ""
            ],
            [
                "Timestamp" => "",
                "Male" => 19,
                "Female" => 36,
                "Children" => 6,
                "Date" => "2/11/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 61,
                "Month of service?" => "February",
                "Year" => ""
            ],
            [
                "Timestamp" => "2/16/2025 1:57:51",
                "Male" => 17,
                "Female" => 28,
                "Children" => "",
                "Date" => "2/14/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 52,
                "Month of service?" => "February",
                "Year" => 2025
            ],
            [
                "Timestamp" => "2/16/2025 2:23:36",
                "Male" => 29,
                "Female" => 43,
                "Children" => 8,
                "Date" => "2/16/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 80,
                "Month of service?" => "February",
                "Year" => 2025
            ],
            [
                "Timestamp" => "2/18/2025 11:07:29",
                "Male" => 14,
                "Female" => 33,
                "Children" => 3,
                "Date" => "2/18/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 50,
                "Month of service?" => "February",
                "Year" => 2025
            ],
            [
                "Timestamp" => "2/21/2025 9:54:01",
                "Male" => 13,
                "Female" => 25,
                "Children" => 2,
                "Date" => "2/21/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 40,
                "Month of service?" => "February",
                "Year" => 2025
            ],
            [
                "Timestamp" => "2/23/2025 3:33:20",
                "Male" => 26,
                "Female" => 38,
                "Children" => 8,
                "Date" => "2/23/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 72,
                "Month of service?" => "February",
                "Year" => 2025
            ],
            [
                "Timestamp" => "2/25/2025 11:37:34",
                "Male" => 12,
                "Female" => 33,
                "Children" => 6,
                "Date" => "2/25/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 51,
                "Month of service?" => "February",
                "Year" => 2025
            ],
            [
                "Timestamp" => "",
                "Male" => 16,
                "Female" => 22,
                "Children" => 3,
                "Date" => "2/28/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 41,
                "Month of service?" => "February",
                "Year" => ""
            ],
            [
                "Timestamp" => "3/1/2025 10:11:52",
                "Male" => 18,
                "Female" => 34,
                "Children" => 4,
                "Date" => "3/1/2025",
                "Service Day" => "Tarry Saturday",
                "Total Attendance" => 56,
                "Month of service?" => "March",
                "Year" => 2025
            ],
            [
                "Timestamp" => "3/2/2025 2:38:44",
                "Male" => 26,
                "Female" => 44,
                "Children" => 8,
                "Date" => "3/2/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 78,
                "Month of service?" => "March",
                "Year" => 2025
            ],
            [
                "Timestamp" => "3/4/2025 21:33:47",
                "Male" => 16,
                "Female" => 36,
                "Children" => 9,
                "Date" => "3/4/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => "",
                "Month of service?" => "March",
                "Year" => 2025
            ],
            [
                "Timestamp" => "3/7/2025 10:12:01",
                "Male" => 15,
                "Female" => 22,
                "Children" => 4,
                "Date" => "3/7/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 41,
                "Month of service?" => "March",
                "Year" => 2025
            ],
            [
                "Timestamp" => "3/11/2025 5:49:56",
                "Male" => 27,
                "Female" => 51,
                "Children" => 8,
                "Date" => "3/9/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 76,
                "Month of service?" => "March",
                "Year" => 2025
            ],
            [
                "Timestamp" => "3/11/2025 15:04:26",
                "Male" => 14,
                "Female" => 29,
                "Children" => 7,
                "Date" => "3/11/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 50,
                "Month of service?" => "March",
                "Year" => 2025
            ],
            [
                "Timestamp" => "",
                "Male" => "",
                "Female" => "",
                "Children" => "",
                "Date" => "3/14/2025",
                "Service Day" => "Friday",
                "Total Attendance" => "",
                "Month of service?" => "March",
                "Year" => ""
            ],
            [
                "Timestamp" => "3/16/2025 2:32:23",
                "Male" => 27,
                "Female" => 40,
                "Children" => 9,
                "Date" => "3/16/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 76,
                "Month of service?" => "March",
                "Year" => 2025
            ],
            [
                "Timestamp" => "3/19/2025 0:51:01",
                "Male" => 15,
                "Female" => 30,
                "Children" => 3,
                "Date" => "3/18/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 48,
                "Month of service?" => "March",
                "Year" => 2025
            ],
            [
                "Timestamp" => "3/23/2025 1:46:09",
                "Male" => 13,
                "Female" => 17,
                "Children" => 7,
                "Date" => "3/21/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 37,
                "Month of service?" => "March",
                "Year" => 2025
            ],
            [
                "Timestamp" => "",
                "Male" => 23,
                "Female" => 33,
                "Children" => 8,
                "Date" => "3/23/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 64,
                "Month of service?" => "March",
                "Year" => ""
            ],
            [
                "Timestamp" => "3/25/2025 12:38:19",
                "Male" => 15,
                "Female" => 24,
                "Children" => 8,
                "Date" => "3/25/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 47,
                "Month of service?" => "March",
                "Year" => 2025
            ],
            [
                "Timestamp" => "3/27/2025 12:13:49",
                "Male" => 41,
                "Female" => 49,
                "Children" => 7,
                "Date" => "3/27/2025",
                "Service Day" => "Thursday",
                "Total Attendance" => 97,
                "Month of service?" => "March",
                "Year" => 2025
            ],
            [
                "Timestamp" => "3/28/2025 12:57:41",
                "Male" => 33,
                "Female" => 47,
                "Children" => 8,
                "Date" => "3/28/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 88,
                "Month of service?" => "March",
                "Year" => 2025
            ],
            [
                "Timestamp" => "3/30/2025 1:00:00",
                "Male" => 31,
                "Female" => 44,
                "Children" => 9,
                "Date" => "3/29/2025",
                "Service Day" => "Saturday",
                "Total Attendance" => 84,
                "Month of service?" => "March",
                "Year" => 2025
            ],
            [
                "Timestamp" => "3/30/2025 3:52:09",
                "Male" => 37,
                "Female" => 46,
                "Children" => 10,
                "Date" => "3/30/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 91,
                "Month of service?" => "March",
                "Year" => 2025
            ],
            [
                "Timestamp" => "4/1/2025 11:06:13",
                "Male" => 14,
                "Female" => 33,
                "Children" => 9,
                "Date" => "4/1/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 56,
                "Month of service?" => "April",
                "Year" => 2025
            ],
            [
                "Timestamp" => "",
                "Male" => "",
                "Female" => "",
                "Children" => "",
                "Date" => "4/4/2025",
                "Service Day" => "Friday",
                "Total Attendance" => "",
                "Month of service?" => "April",
                "Year" => ""
            ],
            [
                "Timestamp" => "4/6/2025 4:25:32",
                "Male" => 25,
                "Female" => 43,
                "Children" => 10,
                "Date" => "4/6/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 78,
                "Month of service?" => "April",
                "Year" => 2025
            ],
            [
                "Timestamp" => "4/8/2025 11:50:47",
                "Male" => 20,
                "Female" => 28,
                "Children" => 9,
                "Date" => "4/8/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 57,
                "Month of service?" => "April",
                "Year" => 2025
            ],
            [
                "Timestamp" => "",
                "Male" => 11,
                "Female" => 20,
                "Children" => 3,
                "Date" => "4/11/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 34,
                "Month of service?" => "April",
                "Year" => ""
            ],
            [
                "Timestamp" => "4/13/2025 3:12:11",
                "Male" => 25,
                "Female" => 40,
                "Children" => 10,
                "Date" => "4/13/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 75,
                "Month of service?" => "April",
                "Year" => 2025
            ],
            [
                "Timestamp" => "",
                "Male" => 15,
                "Female" => 24,
                "Children" => 3,
                "Date" => "4/15/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 42,
                "Month of service?" => "April",
                "Year" => ""
            ],
            [
                "Timestamp" => "4/18/2025 12:19:08",
                "Male" => 22,
                "Female" => 36,
                "Children" => 4,
                "Date" => "4/18/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 62,
                "Month of service?" => "April",
                "Year" => 2025
            ],
            [
                "Timestamp" => "4/20/2025 4:10:39",
                "Male" => 29,
                "Female" => 45,
                "Children" => 5,
                "Date" => "4/20/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 79,
                "Month of service?" => "April",
                "Year" => 2025
            ],
            [
                "Timestamp" => "4/23/2025 0:13:08",
                "Male" => 12,
                "Female" => 23,
                "Children" => 4,
                "Date" => "4/22/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 39,
                "Month of service?" => "April",
                "Year" => 2025
            ],
            [
                "Timestamp" => "4/25/2025 11:12:20",
                "Male" => 11,
                "Female" => 19,
                "Children" => 9,
                "Date" => "4/25/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 39,
                "Month of service?" => "April",
                "Year" => 2025
            ],
            [
                "Timestamp" => "4/27/2025 3:14:29",
                "Male" => 29,
                "Female" => 43,
                "Children" => 12,
                "Date" => "4/27/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 84,
                "Month of service?" => "April",
                "Year" => 2025
            ],
            [
                "Timestamp" => "",
                "Male" => 17,
                "Female" => 24,
                "Children" => 12,
                "Date" => "4/29/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 53,
                "Month of service?" => "April",
                "Year" => ""
            ],
            [
                "Timestamp" => "5/1/2025 5:45:28",
                "Male" => 17,
                "Female" => 32,
                "Children" => 11,
                "Date" => "5/1/2025",
                "Service Day" => "Word Feast",
                "Total Attendance" => 60,
                "Month of service?" => "May",
                "Year" => 2025
            ],
            [
                "Timestamp" => "5/4/2025 3:04:36",
                "Male" => 16,
                "Female" => 27,
                "Children" => 8,
                "Date" => "5/3/2025",
                "Service Day" => "Saturday",
                "Total Attendance" => 51,
                "Month of service?" => "May",
                "Year" => 2025
            ],
            [
                "Timestamp" => "5/4/2025 3:05:40",
                "Male" => 28,
                "Female" => 42,
                "Children" => 13,
                "Date" => "5/4/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 83,
                "Month of service?" => "May",
                "Year" => 2025
            ],
            [
                "Timestamp" => "5/6/2025 12:13:32",
                "Male" => 19,
                "Female" => 34,
                "Children" => 8,
                "Date" => "5/6/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 61,
                "Month of service?" => "May",
                "Year" => 2025
            ],
            [
                "Timestamp" => "5/9/2025 11:11:39",
                "Male" => 28,
                "Female" => 36,
                "Children" => 7,
                "Date" => "5/8/2025",
                "Service Day" => "Thursday (Global bible study)",
                "Total Attendance" => 71,
                "Month of service?" => "May",
                "Year" => 2025
            ],
            [
                "Timestamp" => "5/9/2025 11:12:29",
                "Male" => 11,
                "Female" => 24,
                "Children" => 3,
                "Date" => "5/9/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 38,
                "Month of service?" => "May",
                "Year" => 2025
            ],
            [
                "Timestamp" => "5/11/2025 4:19:29",
                "Male" => 26,
                "Female" => 49,
                "Children" => 11,
                "Date" => "5/11/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 86,
                "Month of service?" => "May",
                "Year" => 2025
            ],
            [
                "Timestamp" => "5/13/2025 12:20:54",
                "Male" => 22,
                "Female" => 31,
                "Children" => 4,
                "Date" => "5/13/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 57,
                "Month of service?" => "May",
                "Year" => 2025
            ],
            [
                "Timestamp" => "5/18/2025 2:49:24",
                "Male" => 29,
                "Female" => 55,
                "Children" => 13,
                "Date" => "5/18/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 97,
                "Month of service?" => "May",
                "Year" => 2025
            ],
            [
                "Timestamp" => "5/20/2025 11:45:41",
                "Male" => 21,
                "Female" => 34,
                "Children" => 9,
                "Date" => "5/20/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 64,
                "Month of service?" => "May",
                "Year" => 2025
            ],
            [
                "Timestamp" => "",
                "Male" => 18,
                "Female" => 35,
                "Children" => 1,
                "Date" => "5/23/2025",
                "Service Day" => "Friday (Vigil)",
                "Total Attendance" => 54,
                "Month of service?" => "May",
                "Year" => 2025
            ],
            [
                "Timestamp" => "5/25/2025 3:54:36",
                "Male" => 33,
                "Female" => 50,
                "Children" => 11,
                "Date" => "5/25/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 94,
                "Month of service?" => "May",
                "Year" => 2025
            ],
            [
                "Timestamp" => "",
                "Male" => 25,
                "Female" => 37,
                "Children" => 8,
                "Date" => "5/27/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 70,
                "Month of service?" => "May",
                "Year" => 2025
            ],
            [
                "Timestamp" => "5/30/2025 12:40:07",
                "Male" => 19,
                "Female" => 24,
                "Children" => 3,
                "Date" => "5/30/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 46,
                "Month of service?" => "May",
                "Year" => 2025
            ],
            [
                "Timestamp" => "6/1/2025 3:03:26",
                "Male" => 40,
                "Female" => 52,
                "Children" => 10,
                "Date" => "6/1/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 102,
                "Month of service?" => "June",
                "Year" => 2025
            ],
            [
                "Timestamp" => "6/3/2025 16:34:32",
                "Male" => 30,
                "Female" => 36,
                "Children" => 8,
                "Date" => "6/3/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 74,
                "Month of service?" => "June",
                "Year" => 2025
            ],
            [
                "Timestamp" => "6/7/2025 3:55:48",
                "Male" => 19,
                "Female" => 26,
                "Children" => 7,
                "Date" => "6/6/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 52,
                "Month of service?" => "May",
                "Year" => 2025
            ],
            [
                "Timestamp" => "6/8/2025 5:40:01",
                "Male" => 4,
                "Female" => 13,
                "Children" => 9,
                "Date" => "6/8/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 26,
                "Month of service?" => "June",
                "Year" => 2025
            ],
            [
                "Timestamp" => "6/13/2025 17:09:15",
                "Male" => 13,
                "Female" => 27,
                "Children" => 8,
                "Date" => "6/13/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 48,
                "Month of service?" => "June",
                "Year" => 2025
            ],
            [
                "Timestamp" => "6/15/2025 3:28:19",
                "Male" => 41,
                "Female" => 59,
                "Children" => 12,
                "Date" => "6/15/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 112,
                "Month of service?" => "June",
                "Year" => 2025
            ],
            [
                "Timestamp" => "6/18/2025 11:23:50",
                "Male" => 22,
                "Female" => 30,
                "Children" => 2,
                "Date" => "6/17/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 54,
                "Month of service?" => "June",
                "Year" => 2025
            ],
            [
                "Timestamp" => "6/22/2025 3:42:56",
                "Male" => 41,
                "Female" => 54,
                "Children" => 12,
                "Date" => "6/22/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 107,
                "Month of service?" => "June",
                "Year" => 2025
            ],
            [
                "Timestamp" => "6/22/2025 8:48:35",
                "Male" => 18,
                "Female" => 27,
                "Children" => 3,
                "Date" => "6/20/2025",
                "Service Day" => "Vigil service",
                "Total Attendance" => 48,
                "Month of service?" => "June",
                "Year" => 2025
            ],
            [
                "Timestamp" => "6/24/2025 11:44:35",
                "Male" => 24,
                "Female" => 31,
                "Children" => 4,
                "Date" => "6/24/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 59,
                "Month of service?" => "June",
                "Year" => 2025
            ],
            [
                "Timestamp" => "6/28/2025 3:28:03",
                "Male" => 16,
                "Female" => 23,
                "Children" => 5,
                "Date" => "6/27/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 44,
                "Month of service?" => "June",
                "Year" => 2025
            ],
            [
                "Timestamp" => "6/29/2025 4:07:09",
                "Male" => 41,
                "Female" => 56,
                "Children" => 11,
                "Date" => "6/29/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 108,
                "Month of service?" => "June",
                "Year" => 2025
            ],
            [
                "Timestamp" => "7/1/2025 11:19:43",
                "Male" => 24,
                "Female" => 36,
                "Children" => 8,
                "Date" => "7/1/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 68,
                "Month of service?" => "July",
                "Year" => 2025
            ],
            [
                "Timestamp" => "7/2/2025 12:33:21",
                "Male" => 17,
                "Female" => 26,
                "Children" => 8,
                "Date" => "7/2/2025",
                "Service Day" => "Wednesday service",
                "Total Attendance" => 51,
                "Month of service?" => "July",
                "Year" => 2025
            ],
            [
                "Timestamp" => "7/5/2025 12:29:26",
                "Male" => 16,
                "Female" => 29,
                "Children" => 8,
                "Date" => "7/5/2025",
                "Service Day" => "Saturday (7days)",
                "Total Attendance" => 53,
                "Month of service?" => "July",
                "Year" => 2025
            ],
            [
                "Timestamp" => "7/6/2025 7:18:05",
                "Male" => 35,
                "Female" => 51,
                "Children" => 11,
                "Date" => "7/6/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 97,
                "Month of service?" => "July",
                "Year" => 2025
            ],
            [
                "Timestamp" => "7/8/2025 13:06:45",
                "Male" => 19,
                "Female" => 27,
                "Children" => 4,
                "Date" => "7/8/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 50,
                "Month of service?" => "July",
                "Year" => 2025
            ],
            [
                "Timestamp" => "7/11/2025 13:14:30",
                "Male" => 19,
                "Female" => 26,
                "Children" => 8,
                "Date" => "7/11/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 53,
                "Month of service?" => "July",
                "Year" => 2025
            ],
            [
                "Timestamp" => "7/13/2025 4:04:56",
                "Male" => 39,
                "Female" => 51,
                "Children" => 11,
                "Date" => "7/13/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 101,
                "Month of service?" => "July",
                "Year" => 2025
            ],
            [
                "Timestamp" => "7/15/2025 12:40:06",
                "Male" => 28,
                "Female" => 33,
                "Children" => 6,
                "Date" => "7/15/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 67,
                "Month of service?" => "July",
                "Year" => 2025
            ],
            [
                "Timestamp" => "7/20/2025 14:02:03",
                "Male" => 32,
                "Female" => 51,
                "Children" => 11,
                "Date" => "7/20/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 94,
                "Month of service?" => "July",
                "Year" => 2025
            ],
            [
                "Timestamp" => "7/20/2025 14:06:23",
                "Male" => 19,
                "Female" => 29,
                "Children" => 2,
                "Date" => "7/18/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 50,
                "Month of service?" => "July",
                "Year" => 2025
            ],
            [
                "Timestamp" => "7/22/2025 12:24:56",
                "Male" => 25,
                "Female" => 33,
                "Children" => 6,
                "Date" => "7/22/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 64,
                "Month of service?" => "July",
                "Year" => 2025
            ],
            [
                "Timestamp" => "7/23/2025 2:04:17",
                "Male" => 25,
                "Female" => 33,
                "Children" => 6,
                "Date" => "7/23/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 64,
                "Month of service?" => "July",
                "Year" => 2025
            ],
            [
                "Timestamp" => "7/27/2025 3:10:12",
                "Male" => 42,
                "Female" => 48,
                "Children" => 10,
                "Date" => "7/27/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 100,
                "Month of service?" => "July",
                "Year" => 2025
            ],
            [
                "Timestamp" => "7/29/2025 11:29:03",
                "Male" => 20,
                "Female" => 32,
                "Children" => 8,
                "Date" => "7/29/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 60,
                "Month of service?" => "July",
                "Year" => 2025
            ],
            [
                "Timestamp" => "8/8/2025 3:09:18",
                "Male" => 23,
                "Female" => 33,
                "Children" => 8,
                "Date" => "8/5/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 64,
                "Month of service?" => "August",
                "Year" => 2025
            ],
            [
                "Timestamp" => "8/8/2025 12:34:47",
                "Male" => 15,
                "Female" => 25,
                "Children" => 4,
                "Date" => "8/8/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 44,
                "Month of service?" => "August",
                "Year" => 2025
            ],
            [
                "Timestamp" => "8/10/2025 3:39:29",
                "Male" => 39,
                "Female" => 51,
                "Children" => 13,
                "Date" => "8/10/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 113,
                "Month of service?" => "August",
                "Year" => 2025
            ],
            [
                "Timestamp" => "8/12/2025 12:16:10",
                "Male" => 28,
                "Female" => 37,
                "Children" => 4,
                "Date" => "8/12/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 69,
                "Month of service?" => "August",
                "Year" => 2025
            ],
            [
                "Timestamp" => "8/15/2025 11:03:20",
                "Male" => 15,
                "Female" => 21,
                "Children" => 4,
                "Date" => "8/15/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 40,
                "Month of service?" => "August",
                "Year" => 2025
            ],
            [
                "Timestamp" => "8/17/2025 3:22:54",
                "Male" => 35,
                "Female" => 51,
                "Children" => 10,
                "Date" => "8/17/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 96,
                "Month of service?" => "August",
                "Year" => 2025
            ],
            [
                "Timestamp" => "8/19/2025 13:24:07",
                "Male" => 27,
                "Female" => 36,
                "Children" => 8,
                "Date" => "8/19/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 61,
                "Month of service?" => "August",
                "Year" => 2025
            ],
            [
                "Timestamp" => "8/22/2025 11:32:40",
                "Male" => 17,
                "Female" => 24,
                "Children" => 8,
                "Date" => "8/22/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 49,
                "Month of service?" => "August",
                "Year" => 2025
            ],
            [
                "Timestamp" => "8/24/2025 3:55:07",
                "Male" => 43,
                "Female" => 56,
                "Children" => 9,
                "Date" => "8/24/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 108,
                "Month of service?" => "August",
                "Year" => 2025
            ],
            [
                "Timestamp" => "8/26/2025 13:38:15",
                "Male" => 33,
                "Female" => 41,
                "Children" => 8,
                "Date" => "8/26/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 82,
                "Month of service?" => "August",
                "Year" => 2025
            ],
            [
                "Timestamp" => "8/29/2025 13:30:57",
                "Male" => 21,
                "Female" => 23,
                "Children" => 4,
                "Date" => "8/29/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 48,
                "Month of service?" => "August",
                "Year" => 2025
            ],
            [
                "Timestamp" => "8/31/2025 3:39:47",
                "Male" => 43,
                "Female" => 50,
                "Children" => 11,
                "Date" => "8/31/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 104,
                "Month of service?" => "August",
                "Year" => 2025
            ],
            [
                "Timestamp" => "9/2/2025 14:51:24",
                "Male" => 25,
                "Female" => 36,
                "Children" => 8,
                "Date" => "9/2/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 69,
                "Month of service?" => "September",
                "Year" => 2025
            ],
            [
                "Timestamp" => "9/6/2025 2:57:41",
                "Male" => 27,
                "Female" => 38,
                "Children" => 7,
                "Date" => "9/6/2025",
                "Service Day" => "Saturday",
                "Total Attendance" => 72,
                "Month of service?" => "September",
                "Year" => 2025
            ],
            [
                "Timestamp" => "9/7/2025 4:13:45",
                "Male" => 44,
                "Female" => 53,
                "Children" => 10,
                "Date" => "9/7/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 107,
                "Month of service?" => "September",
                "Year" => 2025
            ],
            [
                "Timestamp" => "9/9/2025 12:29:41",
                "Male" => 27,
                "Female" => 34,
                "Children" => 4,
                "Date" => "9/9/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 65,
                "Month of service?" => "September",
                "Year" => 2025
            ],
            [
                "Timestamp" => "9/14/2025 2:46:05",
                "Male" => 38,
                "Female" => 53,
                "Children" => 10,
                "Date" => "9/14/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 101,
                "Month of service?" => "September",
                "Year" => 2025
            ],
            [
                "Timestamp" => "9/16/2025 13:35:33",
                "Male" => 30,
                "Female" => 33,
                "Children" => 9,
                "Date" => "9/16/2025",
                "Service Day" => "Tuesday",
                "Total Attendance" => 72,
                "Month of service?" => "September",
                "Year" => 2025
            ],
            [
                "Timestamp" => "9/20/2025 7:54:56",
                "Male" => 17,
                "Female" => 27,
                "Children" => 4,
                "Date" => "9/19/2025",
                "Service Day" => "Friday",
                "Total Attendance" => 48,
                "Month of service?" => "September",
                "Year" => 2025
            ],
            [
                "Timestamp" => "9/21/2025 13:18:34",
                "Male" => 43,
                "Female" => 57,
                "Children" => 11,
                "Date" => "9/21/2025",
                "Service Day" => "Sunday",
                "Total Attendance" => 111,
                "Month of service?" => "September",
                "Year" => 2025
            ]
        ];
        $attendanceRecords = [];
        $skippedRows = [];

        foreach ($jsonData as $index => $row) {
            $rawDate = trim($row['Date'] ?? '');

            // Parse and normalize date
            $parsedDate = $this->parseDate($rawDate);

            if (!$parsedDate) {
                $skippedRows[] = [
                    'row' => $index + 1,
                    'raw_date' => $rawDate
                ];
                continue;
            }

            // service_day (machine-friendly: sunday, tuesday, etc.)
            $serviceDay = strtolower($parsedDate->format('l'));

            // service_day_desc (from JSON column "Service Day")
            $serviceDayDesc = trim($row['Service Day'] ?? $parsedDate->format('l'));

            $attendanceRecords[] = [
                'male' => (int) $row['Male'],
                'female' => (int) $row['Female'],
                'children' => (int) $row['Children'],
                'total_attendance' => (int) $row['Total Attendance'],
                'service_date' => $parsedDate->format('Y-m-d'),
                'service_day' => $serviceDay,
                'service_day_desc' => $serviceDayDesc,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert into DB
        if (!empty($attendanceRecords)) {
            DB::table('usher_attendances')->insert($attendanceRecords);
            $this->command->info(count($attendanceRecords) . ' attendance records seeded successfully!');
        } else {
            $this->command->warn('No attendance records were inserted.');
        }

        // Show skipped rows for debugging
        if (!empty($skippedRows)) {
            $this->command->warn('Skipped rows due to unrecognized date formats:');
            foreach ($skippedRows as $skip) {
                $this->command->warn("Row {$skip['row']}: {$skip['raw_date']}");
            }
        }
    }

    /**
     * Parses a date from multiple possible formats.
     */
    private function parseDate(string $date): ?Carbon
    {
        if (empty($date)) {
            return null;
        }

        $formats = [
            'm-d-y',   // e.g., 03-01-24
            'n/j/Y',   // e.g., 3/17/2024
            'm/d/Y',   // e.g., 03/17/2024
            'Y-m-d',   // e.g., 2024-03-17
        ];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $date);
                if ($parsed !== false) {
                    return $parsed;
                }
            } catch (\Exception $e) {
                // Try next format
            }
        }

        return null; // No matching format
    }
}
