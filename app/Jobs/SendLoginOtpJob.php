<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Mail\LoginOtpMail;

class SendLoginOtpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $name;
    public $email;
    public $otp;

    /**
     * Create a new job instance.
     */
    public function __construct($name, $email, $otp)
    {
        $this->name = $name;
        $this->email = $email;
        $this->otp = $otp;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $systemName = config('app.name');
        $imageUrl = asset(config('otp.logo_path'));

        $subject = str_replace(
            ':app',
            $systemName,
            config('otp.subjects.login')
        );

        Mail::to($this->email)->send(
            new LoginOtpMail(
                name: $this->name,
                otp: $this->otp,
                systemName: $systemName,
                imageUrl: $imageUrl,
                subjectLine: $subject
            )
        );
    }
}