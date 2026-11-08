<?php

namespace App\Config;

class PointRewards
{
    // done
    const USER_LOGIN                 = 'session.login';
    const VIDEO_WATCHED              = 'media.video_watched';
    const AUDIO_LISTENED             = 'media.audio_listened';
    const ATTENDANCE_MARKED          = 'attendance.marked';
    const MESSAGE_SENT               = 'engagement.message_sent';
    const MESSAGE_REPLIED            = 'engagement.message_replied';
    const FORM_SUBMITTED             = 'forms.submitted';
    const EVENT_REGISTERED           = 'events.registered';
    const PROFILE_UPDATED            = 'profile.updated';
    const USHER_ATTENDANCE_MARKED    = 'attendance.usher_marked';
    const FOLLOWUP_FEEDBACK_SUBMITTED = 'engagement.followup_submitted';
    // done

    /*--------------------------------------------------------------
    | Points Map
    --------------------------------------------------------------*/
    private const VALUES = [
        self::ATTENDANCE_MARKED           => 15,
        self::USHER_ATTENDANCE_MARKED     => 10,
        self::PROFILE_UPDATED             => 10,
        self::MESSAGE_SENT                => 5,
        self::MESSAGE_REPLIED             => 5,
        self::FOLLOWUP_FEEDBACK_SUBMITTED => 10,
        self::VIDEO_WATCHED               => 30,
        self::AUDIO_LISTENED              => 20,
        self::EVENT_REGISTERED            => 10,
        self::FORM_SUBMITTED              => 5,
        self::USER_LOGIN                  => 5,
    ];

    public static function get(string $action): int
    {
        return self::VALUES[$action] ?? 0;
    }

    /** All action keys — used for request validation */
    public static function keys(): array
    {
        return array_keys(self::VALUES);
    }

    /** Full map — useful for admin reward tables */
    public static function all(): array
    {
        return self::VALUES;
    }

    /** Grouped by category — for displaying rewards info in the UI */
    public static function grouped(): array
    {
        return [
            'Attendance' => [
                self::ATTENDANCE_MARKED           => self::VALUES[self::ATTENDANCE_MARKED],
                self::USHER_ATTENDANCE_MARKED     => self::VALUES[self::USHER_ATTENDANCE_MARKED],
            ],
            'Profile' => [
                self::PROFILE_UPDATED    => self::VALUES[self::PROFILE_UPDATED],
            ],
            'Engagement' => [
                self::MESSAGE_SENT                => self::VALUES[self::MESSAGE_SENT],
                self::MESSAGE_REPLIED             => self::VALUES[self::MESSAGE_REPLIED],
                self::FOLLOWUP_FEEDBACK_SUBMITTED => self::VALUES[self::FOLLOWUP_FEEDBACK_SUBMITTED],
            ],
            'Media' => [
                self::VIDEO_WATCHED  => self::VALUES[self::VIDEO_WATCHED],
                self::AUDIO_LISTENED => self::VALUES[self::AUDIO_LISTENED],
            ],
            'Events'     => [self::EVENT_REGISTERED   => self::VALUES[self::EVENT_REGISTERED]],
            'Forms'      => [self::FORM_SUBMITTED      => self::VALUES[self::FORM_SUBMITTED]],

            'Session'    => [self::USER_LOGIN => self::VALUES[self::USER_LOGIN]],
        ];
    }
}
